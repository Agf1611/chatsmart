const { Boom } = require("@hapi/boom");
const {
  default: makeWASocket,
  Browsers,
  DisconnectReason,
  fetchLatestBaileysVersion,
  useMultiFileAuthState,
  makeCacheableSignalKeyStore,
} = require("@whiskeysockets/baileys");
const QRCode = require("qrcode");
const fs = require("fs");
const path = require("path");
const NodeCache = require("node-cache");

const { setStatus } = require("./database/index");
const { IncomingMessage } = require("./controllers/incomingMessage");
const { formatReceipt, getSavedPhoneNumber, prepareMediaMessage } = require("./lib/helper");
const MAIN_LOGGER = require("./lib/pino");

const logger = MAIN_LOGGER.child({});
const msgRetryCounterCache = new NodeCache();
const sessions = {};
const qrcode = {};
const pairingCode = {};
const activeConnectPromises = {};
const reconnectAfterAuthReset = {};

const DEFAULT_PP =
  "https://upload.wikimedia.org/wikipedia/commons/thumb/6/6b/WhatsApp.svg/1200px-WhatsApp.svg.png";

function getCredentialPath(token) {
  return path.join(process.cwd(), "credentials", String(token));
}

function isSocketConnected(token) {
  return Boolean(sessions[token]?.user?.id);
}

async function getPpUrl(token, jid) {
  try {
    if (!sessions[token]) {
      return DEFAULT_PP;
    }

    return await sessions[token].profilePictureUrl(jid);
  } catch (error) {
    return DEFAULT_PP;
  }
}

async function emitConnectedState(token, io) {
  let currentJid = String(sessions[token]?.user?.id || "").split(":")[0];
  if (!currentJid) {
    return;
  }

  currentJid = `${currentJid}@s.whatsapp.net`;
  const ppUrl = await getPpUrl(token, currentJid);
  io?.emit("connection-open", {
    token,
    user: sessions[token].user,
    ppUrl,
  });
}

function clearMemoryState(token) {
  delete sessions[token];
  delete qrcode[token];
  delete pairingCode[token];
}

function removeCredentialFolder(token) {
  const credentialPath = getCredentialPath(token);
  if (fs.existsSync(credentialPath)) {
    fs.rmSync(credentialPath, { recursive: true, force: true });
  }
}

async function clearConnection(token, options = {}) {
  clearMemoryState(token);
  await setStatus(token, "Disconnect");

  if (options.removeCreds) {
    removeCredentialFolder(token);
  }
}

async function createSocket(token, io, usePairingCode) {
  const { version, isLatest } = await fetchLatestBaileysVersion();
  console.log("using WA v", version.join("."), ", isLatest:", isLatest);

  const { state, saveCreds } = await useMultiFileAuthState(getCredentialPath(token));
  const socket = makeWASocket({
    version,
    browser: Browsers.macOS("Desktop", "Mpedia"),
    logger,
    printQRInTerminal: false,
    auth: {
      creds: state.creds,
      keys: makeCacheableSignalKeyStore(state.keys, logger),
    },
    msgRetryCounterCache,
    generateHighQualityLinkPreview: true,
  });

  sessions[token] = socket;

  socket.ev.on("connection.update", async (update) => {
    const { connection, lastDisconnect, qr } = update;

    if (qr) {
      delete reconnectAfterAuthReset[token];
      try {
        qrcode[token] = await QRCode.toDataURL(qr);
        io?.emit("qrcode", {
          token,
          data: qrcode[token],
          message: "please scan",
        });
      } catch (error) {
        console.log(error);
      }
    }

    if (connection === "open") {
      delete reconnectAfterAuthReset[token];
      await setStatus(token, "Connected");
      delete qrcode[token];
      delete pairingCode[token];
      await emitConnectedState(token, io);
    }

    if (connection === "close") {
      const statusCode =
        lastDisconnect?.error instanceof Boom
          ? lastDisconnect.error.output?.statusCode
          : lastDisconnect?.error?.output?.statusCode;

      await setStatus(token, "Disconnect");

      if (
        statusCode === DisconnectReason.loggedOut ||
        statusCode === DisconnectReason.badSession ||
        statusCode === DisconnectReason.multideviceMismatch
      ) {
        await clearConnection(token, { removeCreds: true });

        if (!reconnectAfterAuthReset[token]) {
          reconnectAfterAuthReset[token] = true;
          io?.emit("message", {
            token,
            message: usePairingCode
              ? "Session invalid. Requesting a new pairing code..."
              : "Session invalid. Requesting a new QR code...",
          });
          setTimeout(() => {
            connectToWhatsApp(token, io, usePairingCode).catch((error) => console.log(error));
          }, 1000);
          return;
        }

        delete reconnectAfterAuthReset[token];
        io?.emit("Unauthorized", { token });
        io?.emit("message", {
          token,
          message: "Connection closed. You are logged out.",
        });
        return;
      }

      if (statusCode === DisconnectReason.connectionReplaced) {
        io?.emit("message", {
          token,
          message: "Connection was lost",
        });
        await clearConnection(token);
        return;
      }

      io?.emit("message", {
        token,
        message: "Connection was lost",
      });

      await clearConnection(token);

      if (fs.existsSync(getCredentialPath(token))) {
        setTimeout(() => {
          connectToWhatsApp(token, io, usePairingCode).catch((error) => console.log(error));
        }, 1000);
      }
    }
  });

  socket.ev.on("creds.update", saveCreds);
  socket.ev.on("messages.upsert", (upsert) => IncomingMessage(upsert, socket));

  if (usePairingCode && !state.creds?.me) {
    try {
      await socket.waitForSocketOpen();
      await new Promise((resolve) => setTimeout(resolve, 5000));
      pairingCode[token] = await socket.requestPairingCode(getSavedPhoneNumber(token));
      delete reconnectAfterAuthReset[token];
      io?.emit("code", {
        token,
        data: pairingCode[token],
        message: "Go to whatsapp -> link device -> link with phone number, and pairing with this code.",
      });
    } catch (error) {
      io?.emit("message", {
        token,
        message: "Time out, please refresh page",
      });
    }
  }

  return socket;
}

const connectToWhatsApp = async (token, io = null, usePairingCode = false) => {
  if (qrcode[token] && !usePairingCode) {
    io?.emit("qrcode", {
      token,
      data: qrcode[token],
      message: "please scan",
    });

    return {
      status: false,
      sock: sessions[token],
      qrcode: qrcode[token],
      message: "please scan with your Whatsapp Accountt",
    };
  }

  if (pairingCode[token] && usePairingCode) {
    io?.emit("code", {
      token,
      data: pairingCode[token],
      message: "Go to whatsapp -> link device -> link with phone number, and pairing with this code.",
    });

    return {
      status: false,
      code: pairingCode[token],
      message: "pairing with that code",
    };
  }

  if (isSocketConnected(token)) {
    await emitConnectedState(token, io);
    delete qrcode[token];
    delete pairingCode[token];
    return {
      status: true,
      message: "Already connected",
    };
  }

  if (activeConnectPromises[token]) {
    await activeConnectPromises[token];
    return {
      status: isSocketConnected(token),
      sock: sessions[token],
      qrcode: qrcode[token],
      code: pairingCode[token],
      message: isSocketConnected(token) ? "Already connected" : "Processing",
    };
  }

  activeConnectPromises[token] = createSocket(token, io, usePairingCode);

  try {
    await activeConnectPromises[token];
  } finally {
    delete activeConnectPromises[token];
  }

  return {
    status: isSocketConnected(token),
    sock: sessions[token],
    qrcode: qrcode[token],
    code: pairingCode[token],
    message: isSocketConnected(token) ? "Already connected" : "Processing",
  };
};

async function connectWaBeforeSend(token) {
  if (isSocketConnected(token)) {
    return true;
  }

  if (!fs.existsSync(getCredentialPath(token))) {
    return false;
  }

  await connectToWhatsApp(token);

  for (let attempt = 0; attempt < 15; attempt += 1) {
    if (isSocketConnected(token)) {
      return true;
    }

    if (qrcode[token] || pairingCode[token]) {
      return false;
    }

    await new Promise((resolve) => setTimeout(resolve, 1000));
  }

  return false;
}

async function ensureSocket(token) {
  const connected = await connectWaBeforeSend(token);
  if (!connected || !sessions[token]) {
    return null;
  }

  return sessions[token];
}

const sendText = async (token, number, text) => {
  try {
    const socket = await ensureSocket(token);
    if (!socket) {
      return false;
    }

    return await socket.sendMessage(formatReceipt(number), { text });
  } catch (error) {
    return false;
  }
};

const sendMessage = async (token, number, payload) => {
  try {
    const socket = await ensureSocket(token);
    if (!socket) {
      return false;
    }

    const normalizedPayload = typeof payload === "string" ? JSON.parse(payload) : payload;
    return await socket.sendMessage(formatReceipt(number), normalizedPayload);
  } catch (error) {
    return false;
  }
};

async function sendMedia(token, number, type, media, caption, ptt, fileName) {
  try {
    const socket = await ensureSocket(token);
    if (!socket) {
      return false;
    }

    if (type === "audio") {
      return await socket.sendMessage(formatReceipt(number), {
        audio: { url: media },
        ptt: Boolean(ptt) && ptt !== "false",
        mimetype: "audio/mpeg",
      });
    }

    const preparedPayload = await prepareMediaMessage(socket, {
      caption: caption || "",
      fileName,
      media,
      mediatype: type !== "video" && type !== "image" ? "document" : type,
      ptt: Boolean(ptt) && ptt !== "false",
    });

    return await socket.sendMessage(formatReceipt(number), preparedPayload);
  } catch (error) {
    return false;
  }
}

async function sendButtonMessage(token, number, button, message, footer, image) {
  try {
    const socket = await ensureSocket(token);
    if (!socket) {
      return false;
    }

    const buttons = button.map((item, index) => ({
      buttonId: String(index),
      buttonText: {
        displayText: item.displayText,
      },
      type: 1,
    }));

    const payload = image
      ? {
          image: { url: image },
          caption: message,
          footer,
          buttons,
          headerType: 4,
          viewOnce: true,
        }
      : {
          text: message,
          footer,
          buttons,
          headerType: 1,
          viewOnce: true,
        };

    return await socket.sendMessage(formatReceipt(number), payload);
  } catch (error) {
    console.log(error);
    return false;
  }
}

async function sendTemplateMessage(token, number, button, text, footer, image) {
  try {
    const socket = await ensureSocket(token);
    if (!socket) {
      return false;
    }

    const payload = image
      ? {
          caption: text,
          footer,
          templateButtons: button,
          image: { url: image },
          viewOnce: true,
        }
      : {
          text,
          footer,
          templateButtons: button,
          viewOnce: true,
        };

    return await socket.sendMessage(formatReceipt(number), payload);
  } catch (error) {
    console.log(error);
    return false;
  }
}

async function sendListMessage(token, number, list, text, footer, title, buttonText) {
  try {
    const socket = await ensureSocket(token);
    if (!socket) {
      return false;
    }

    return await socket.sendMessage(
      formatReceipt(number),
      {
        text,
        footer,
        title,
        buttonText,
        sections: [list],
      },
      {
        ephemeralExpiration: 604800,
      }
    );
  } catch (error) {
    console.log(error);
    return false;
  }
}

async function sendPollMessage(token, number, name, options, countable) {
  try {
    const socket = await ensureSocket(token);
    if (!socket) {
      return false;
    }

    return await socket.sendMessage(formatReceipt(number), {
      poll: {
        name,
        values: options,
        selectableCount: Number(countable),
      },
    });
  } catch (error) {
    console.log(error);
    return false;
  }
}

async function fetchGroups(token) {
  try {
    const socket = await ensureSocket(token);
    if (!socket) {
      return false;
    }

    const groups = await socket.groupFetchAllParticipating();
    return Object.values(groups);
  } catch (error) {
    return false;
  }
}

async function isExist(token, number) {
  try {
    const socket = await ensureSocket(token);
    if (!socket) {
      return false;
    }

    const jid = formatReceipt(number);
    if (jid.includes("@g.us")) {
      return true;
    }

    const [result] = await socket.onWhatsApp(jid);
    return Boolean(result?.exists || result?.jid);
  } catch (error) {
    return false;
  }
}

async function deleteCredentials(token, io = null) {
  io?.emit("message", {
    token,
    message: "Connecting.. (1)..",
  });

  try {
    if (sessions[token]) {
      try {
        await sessions[token].logout();
      } catch (error) {
        sessions[token].ws?.close();
      }
    }

    await clearConnection(token, { removeCreds: true });

    io?.emit("Unauthorized", { token });
    io?.emit("message", {
      token,
      message: "Connection closed. You are logged out.",
    });

    return {
      status: true,
      message: "Deleting session and credential",
    };
  } catch (error) {
    console.log(error);
    return {
      status: true,
      message: "Nothing deleted",
    };
  }
}

async function initialize(req, res) {
  const { token } = req.body;
  if (!token) {
    return res.send({ status: false, message: "Wrong Parameterss" });
  }

  if (!fs.existsSync(getCredentialPath(token))) {
    return res.send({ status: false, message: `${token} connection failed` });
  }

  sessions[token] = undefined;
  const connected = await connectWaBeforeSend(token);
  if (connected) {
    return res.status(200).json({ status: true, message: `${token} connection restored` });
  }

  return res.status(200).json({ status: false, message: `${token} connection failed` });
}

async function restoreSessions(io = null) {
  const credentialsRoot = path.join(process.cwd(), "credentials");
  if (!fs.existsSync(credentialsRoot)) {
    return [];
  }

  const tokens = fs
    .readdirSync(credentialsRoot, { withFileTypes: true })
    .filter((entry) => entry.isDirectory())
    .map((entry) => entry.name)
    .filter(Boolean);

  const results = [];

  for (const token of tokens) {
    try {
      const connected = await connectWaBeforeSend(token);
      results.push({ token, connected });
    } catch (error) {
      console.log(`Failed to restore session ${token}`, error.message);
      results.push({ token, connected: false });
    }
  }

  return results;
}

module.exports = {
  connectToWhatsApp,
  sendText,
  sendMedia,
  sendButtonMessage,
  sendTemplateMessage,
  sendListMessage,
  sendPollMessage,
  isExist,
  getPpUrl,
  fetchGroups,
  deleteCredentials,
  sendMessage,
  initialize,
  connectWaBeforeSend,
  restoreSessions,
  sock: sessions,
};
