const { Boom } = require("@hapi/boom");
const {
  default: makeWASocket,
  Browsers,
  DEFAULT_CONNECTION_CONFIG,
  DisconnectReason,
  fetchLatestBaileysVersion,
  useMultiFileAuthState,
  makeCacheableSignalKeyStore,
  jidNormalizedUser,
  generateWAMessageFromContent,
  proto,
} = require("@whiskeysockets/baileys");
const QRCode = require("qrcode");
const fs = require("fs");
const path = require("path");
const NodeCache = require("node-cache");
const decodeWaMessage = require("@whiskeysockets/baileys/lib/Utils/decode-wa-message");

const { setStatus, dbQuery } = require("./database/index");
const { IncomingMessage } = require("./controllers/incomingMessage");
const { formatReceipt, normalizeLookupNumber, getSavedPhoneNumber, prepareMediaMessage } = require("./lib/helper");
const MAIN_LOGGER = require("./lib/pino");

const logger = MAIN_LOGGER.child({});
const msgRetryCounterCache = new NodeCache();
const recipientJidCache = new NodeCache({ stdTTL: 24 * 60 * 60, useClones: false });
const sessions = {};
const qrcode = {};
const pairingCode = {};
const activeConnectPromises = {};
const reconnectAfterAuthReset = {};
const reconnectTimers = {};
const socketGenerations = {};

const DEFAULT_PP =
  "https://upload.wikimedia.org/wikipedia/commons/thumb/6/6b/WhatsApp.svg/1200px-WhatsApp.svg.png";
const DEFAULT_WA_VERSION = Array.isArray(DEFAULT_CONNECTION_CONFIG?.version)
  ? [...DEFAULT_CONNECTION_CONFIG.version]
  : [2, 2329, 9];

function isNewsletterJid(jid) {
  return String(jid || "").toLowerCase().endsWith("@newsletter");
}

function patchBaileysNewsletterDecode() {
  if (!decodeWaMessage || decodeWaMessage.__chatsmartNewsletterPatched) {
    return;
  }

  const originalDecryptMessageNode = decodeWaMessage.decryptMessageNode;
  if (typeof originalDecryptMessageNode !== "function") {
    return;
  }

  decodeWaMessage.decryptMessageNode = function patchedDecryptMessageNode(stanza, meId, meLid, repository, baileysLogger) {
    if (isNewsletterJid(stanza?.attrs?.from)) {
      const remoteJid = String(stanza?.attrs?.from || "");
      const participant = stanza?.attrs?.participant;
      const pushName = stanza?.attrs?.notify;

      return {
        fullMessage: {
          key: {
            remoteJid,
            fromMe: false,
            id: stanza?.attrs?.id,
            participant,
          },
          messageTimestamp: Number(stanza?.attrs?.t || 0),
          pushName,
          broadcast: true,
        },
        category: stanza?.attrs?.category,
        author: participant || remoteJid,
        async decrypt() {
          return;
        },
      };
    }

    return originalDecryptMessageNode(stanza, meId, meLid, repository, baileysLogger);
  };

  decodeWaMessage.__chatsmartNewsletterPatched = true;
}

patchBaileysNewsletterDecode();

function looksLikeWindowsPath(rawPath) {
  return /^[a-zA-Z]:[\\/]/.test(String(rawPath || "").trim());
}

function resolveCredentialsRoot() {
  const configuredPath = String(process.env.WA_CREDENTIALS_PATH || "").trim();
  const serverType = String(process.env.TYPE_SERVER || "").toLowerCase();

  if (configuredPath && !(serverType === "hosting" && looksLikeWindowsPath(configuredPath))) {
    return path.resolve(configuredPath);
  }

  const fallbackPath = path.join(process.cwd(), "storage", "app", "wa-sessions");
  if (serverType === "hosting" && configuredPath && looksLikeWindowsPath(configuredPath)) {
    console.log(`Ignoring Windows WA_CREDENTIALS_PATH on hosting (${configuredPath}); using ${fallbackPath}`);
  }

  return path.resolve(fallbackPath);
}

const CREDENTIALS_ROOT = resolveCredentialsRoot();
const LEGACY_CREDENTIALS_ROOT = path.resolve(path.join(process.cwd(), "credentials"));

function credentialDirHasCreds(dirPath) {
  return fs.existsSync(path.join(dirPath, "creds.json"));
}

function listCredentialDirectories(rootPath) {
  if (!fs.existsSync(rootPath)) {
    return [];
  }

  return fs.readdirSync(rootPath, { withFileTypes: true })
    .filter((entry) => entry.isDirectory() && entry.name)
    .map((entry) => entry.name);
}

function createRecipientCacheKey(token, number) {
  const normalizedNumber = normalizeLookupNumber(number);
  if (!token || !normalizedNumber) {
    return "";
  }

  return `${token}:${normalizedNumber}`;
}

function getCachedRecipientJids(token, number) {
  const cacheKey = createRecipientCacheKey(token, number);
  if (!cacheKey) {
    return [];
  }

  const cached = recipientJidCache.get(cacheKey);
  return Array.isArray(cached) ? cached.filter(Boolean) : [];
}

function rememberRecipientJids(token, number, jids) {
  const cacheKey = createRecipientCacheKey(token, number);
  if (!cacheKey) {
    return;
  }

  const normalizedJids = (Array.isArray(jids) ? jids : [jids])
    .map((jid) => jidNormalizedUser(String(jid || "").trim()))
    .filter(Boolean);

  if (normalizedJids.length === 0) {
    return;
  }

  recipientJidCache.set(cacheKey, Array.from(new Set(normalizedJids)));
}

async function loadStoredRecipientJids(token, number) {
  const normalizedNumber = normalizeLookupNumber(number);
  if (!token || !normalizedNumber) {
    return [];
  }

  try {
    const rows = await dbQuery(
      `SELECT incoming_message_logs.chat_jid
       FROM incoming_message_logs
       INNER JOIN devices ON devices.id = incoming_message_logs.device_id
       WHERE devices.body = ?
         AND incoming_message_logs.chat_jid LIKE ?
       ORDER BY incoming_message_logs.last_received_at DESC
       LIMIT 5`,
      [String(token), `${normalizedNumber}%@s.whatsapp.net`]
    );

    const jids = rows
      .map((row) => jidNormalizedUser(row?.chat_jid || ""))
      .filter(Boolean);

    if (jids.length > 0) {
      rememberRecipientJids(token, normalizedNumber, jids);
    }

    return jids;
  } catch (error) {
    return [];
  }
}

function parseConfiguredWaVersion(rawVersion) {
  if (!rawVersion) {
    return null;
  }

  const parts = String(rawVersion)
    .split(/[,\s.]+/)
    .map((value) => Number.parseInt(value, 10))
    .filter((value) => Number.isInteger(value) && value >= 0);

  return parts.length >= 3 ? parts.slice(0, 3) : null;
}

async function resolveWaVersion() {
  const envVersion = parseConfiguredWaVersion(process.env.BAILEYS_VERSION);
  if (envVersion) {
    return {
      version: envVersion,
      isLatest: false,
      source: "env",
    };
  }

  const shouldFetchLatest = String(process.env.BAILEYS_FETCH_LATEST || "").toLowerCase() === "true";
  if (shouldFetchLatest) {
    try {
      const result = await fetchLatestBaileysVersion({ timeout: 10000 });
      return {
        version: result.version,
        isLatest: result.isLatest,
        source: "remote",
      };
    } catch (error) {
      console.log("Failed fetching latest Baileys version, using bundled version.", error.message);
    }
  }

  return {
    version: [...DEFAULT_WA_VERSION],
    isLatest: false,
    source: "bundled",
  };
}

function getCredentialPath(token) {
  return path.join(CREDENTIALS_ROOT, String(token));
}

function getLegacyCredentialPath(token) {
  return path.join(LEGACY_CREDENTIALS_ROOT, String(token));
}

function copyCredentialDirectory(sourcePath, targetPath) {
  fs.mkdirSync(path.dirname(targetPath), { recursive: true });
  fs.cpSync(sourcePath, targetPath, { recursive: true, force: true });
}

function migrateLegacyCredentialToken(token) {
  const primaryPath = getCredentialPath(token);
  const legacyPath = getLegacyCredentialPath(token);

  fs.mkdirSync(CREDENTIALS_ROOT, { recursive: true });

  if (!credentialDirHasCreds(primaryPath) && credentialDirHasCreds(legacyPath)) {
    try {
      copyCredentialDirectory(legacyPath, primaryPath);
      console.log(`Migrated legacy WA session for ${token} to ${primaryPath}`);
    } catch (error) {
      console.log(`Failed migrating legacy WA session for ${token}:`, error.message);
    }
  }
}

function migrateLegacyCredentialSessions() {
  for (const token of listCredentialDirectories(LEGACY_CREDENTIALS_ROOT)) {
    migrateLegacyCredentialToken(token);
  }
}

function resolveCredentialPath(token) {
  migrateLegacyCredentialToken(token);
  fs.mkdirSync(CREDENTIALS_ROOT, { recursive: true });
  return getCredentialPath(token);
}

function hasCredentialState(token) {
  migrateLegacyCredentialToken(token);
  return credentialDirHasCreds(getCredentialPath(token));
}

function listStoredTokens() {
  migrateLegacyCredentialSessions();

  return listCredentialDirectories(CREDENTIALS_ROOT)
    .filter((token) => credentialDirHasCreds(getCredentialPath(token)));
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
  const currentSession = sessions[token];
  if (!currentSession?.user?.id) {
    return;
  }

  let currentJid = String(currentSession.user.id || "").split(":")[0];
  if (!currentJid) {
    return;
  }

  currentJid = `${currentJid}@s.whatsapp.net`;
  const ppUrl = await getPpUrl(token, currentJid);
  io?.emit("connection-open", {
    token,
    user: currentSession.user,
    ppUrl,
  });
}

function clearReconnectTimer(token) {
  if (reconnectTimers[token]) {
    clearTimeout(reconnectTimers[token]);
    delete reconnectTimers[token];
  }
}

function markSocketGeneration(token) {
  const generation = (socketGenerations[token] || 0) + 1;
  socketGenerations[token] = generation;
  return generation;
}

function isCurrentSocket(token, socket) {
  return sessions[token] === socket;
}

function safeEndSocket(socket) {
  if (!socket || typeof socket.end !== "function") {
    return;
  }

  try {
    socket.end(undefined);
  } catch (error) {
    console.log("Failed closing stale socket:", error.message);
  }
}

function scheduleReconnect(token, io, usePairingCode, delayMs, reason) {
  if (!hasCredentialState(token)) {
    return;
  }

  clearReconnectTimer(token);
  reconnectTimers[token] = setTimeout(() => {
    delete reconnectTimers[token];

    if (isSocketConnected(token) || activeConnectPromises[token]) {
      return;
    }

    connectToWhatsApp(token, io, usePairingCode).catch((error) => {
      console.log(`Reconnect failed for ${token}${reason ? ` (${reason})` : ""}:`, error.message);
    });
  }, Math.max(1000, Number(delayMs) || 1000));
}

function clearMemoryState(token) {
  delete sessions[token];
  delete qrcode[token];
  delete pairingCode[token];
}

function removeCredentialFolder(token) {
  for (const credentialPath of [getCredentialPath(token), getLegacyCredentialPath(token)]) {
    if (fs.existsSync(credentialPath)) {
      fs.rmSync(credentialPath, { recursive: true, force: true });
    }
  }
}

async function clearConnection(token, options = {}) {
  if (options.expectedSocket && !isCurrentSocket(token, options.expectedSocket)) {
    return;
  }

  clearReconnectTimer(token);
  clearMemoryState(token);
  await setStatus(token, "Disconnect");

  if (options.removeCreds) {
    removeCredentialFolder(token);
  }
}

async function createSocket(token, io, usePairingCode) {
  const { version, isLatest, source } = await resolveWaVersion();
  console.log("using WA v", version.join("."), ", source:", source, ", isLatest:", isLatest);

  const credentialPath = resolveCredentialPath(token);
  const { state, saveCreds } = await useMultiFileAuthState(credentialPath);
  const previousSocket = sessions[token];
  const generation = markSocketGeneration(token);
  const socket = makeWASocket({
    version,
    browser: Browsers.macOS("Desktop", "Mpedia"),
    logger,
    printQRInTerminal: false,
    connectTimeoutMs: 60_000,
    defaultQueryTimeoutMs: 60_000,
    retryRequestDelayMs: 2_000,
    auth: {
      creds: state.creds,
      keys: makeCacheableSignalKeyStore(state.keys, logger),
    },
    msgRetryCounterCache,
    generateHighQualityLinkPreview: false,
    markOnlineOnConnect: false,
    syncFullHistory: false,
    fireInitQueries: false,
    shouldIgnoreJid: (jid) => isNewsletterJid(jid),
  });

  sessions[token] = socket;
  clearReconnectTimer(token);

  if (previousSocket && previousSocket !== socket) {
    safeEndSocket(previousSocket);
  }

  socket.ev.on("connection.update", async (update) => {
    try {
      if (!isCurrentSocket(token, socket)) {
        return;
      }

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
        clearReconnectTimer(token);
        delete reconnectAfterAuthReset[token];
        await setStatus(token, "Connected");
        delete qrcode[token];
        delete pairingCode[token];
        console.log(`WA connection opened for ${token} on socket generation ${generation}`);
        await emitConnectedState(token, io);
      }

      if (connection === "close") {
        clearReconnectTimer(token);
        const statusCode =
          lastDisconnect?.error instanceof Boom
            ? lastDisconnect.error.output?.statusCode
            : lastDisconnect?.error?.output?.statusCode;
        const disconnectMessage = lastDisconnect?.error?.message || lastDisconnect?.error?.data || "unknown";
        console.log(`WA connection closed for ${token}. statusCode=${statusCode || "n/a"} reason=${disconnectMessage}`);

        await setStatus(token, "Disconnect");

        if (statusCode === DisconnectReason.loggedOut) {
          await clearConnection(token, { removeCreds: true, expectedSocket: socket });

          if (!reconnectAfterAuthReset[token]) {
            reconnectAfterAuthReset[token] = true;
            io?.emit("message", {
              token,
              message: usePairingCode
                ? "Session invalid. Requesting a new pairing code..."
                : "Session invalid. Requesting a new QR code...",
            });
            scheduleReconnect(token, io, usePairingCode, 1000, "loggedOut");
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

        if (
          statusCode === DisconnectReason.badSession ||
          statusCode === DisconnectReason.multideviceMismatch
        ) {
          io?.emit("message", {
            token,
            message: "Session sync issue detected. Retrying existing credentials...",
          });

          await clearConnection(token, { expectedSocket: socket });

          if (hasCredentialState(token)) {
            scheduleReconnect(token, io, usePairingCode, 5000, "auth-sync");
          } else {
            io?.emit("Unauthorized", { token });
          }

          return;
        }

        if (statusCode === DisconnectReason.connectionReplaced) {
          io?.emit("message", {
            token,
            message: "Connection was replaced. Retrying session...",
          });
          await clearConnection(token, { expectedSocket: socket });
          scheduleReconnect(token, io, usePairingCode, 3000, "connection-replaced");
          return;
        }

        io?.emit("message", {
          token,
          message: "Connection was lost",
        });

        await clearConnection(token, { expectedSocket: socket });

        if (hasCredentialState(token)) {
          scheduleReconnect(token, io, usePairingCode, 1000, "connection-close");
        }
      }
    } catch (error) {
      console.log(`Unhandled WA connection.update error for ${token}:`, error);
    }
  });

  socket.ev.on("creds.update", async () => {
    try {
      await saveCreds();
    } catch (error) {
      console.log(`Failed saving credentials for ${token}:`, error);
    }
  });
  socket.ev.on("messages.upsert", (upsert) => {
    for (const message of upsert?.messages || []) {
      const remoteJid = jidNormalizedUser(message?.key?.remoteJid || "");
      if (remoteJid.endsWith("@s.whatsapp.net")) {
        rememberRecipientJids(token, remoteJid, remoteJid);
      }
    }

    IncomingMessage(upsert, socket).catch((error) => {
      console.log(`Unhandled IncomingMessage error for ${token}:`, error);
    });
  });

  if (usePairingCode && !state.creds?.registered) {
    try {
      const savedPhoneNumber = getSavedPhoneNumber(token);
      console.log(`Requesting pairing code for ${token} with phone ${savedPhoneNumber}`);
      await socket.waitForSocketOpen();
      await new Promise((resolve) => setTimeout(resolve, 3000));
      pairingCode[token] = await socket.requestPairingCode(savedPhoneNumber);
      delete reconnectAfterAuthReset[token];
      io?.emit("code", {
        token,
        data: pairingCode[token],
        message: "Go to whatsapp -> link device -> link with phone number, and pairing with this code.",
      });
    } catch (error) {
      console.log(`Failed requesting pairing code for ${token}:`, error.message);
      io?.emit("message", {
        token,
        message: `Failed requesting pairing code: ${error.message || "unknown error"}`,
      });
    }
  }

  return socket;
}

const connectToWhatsApp = async (token, io = null, usePairingCode = false) => {
  if (usePairingCode && hasCredentialState(token) && !isSocketConnected(token)) {
    const credentialPath = resolveCredentialPath(token);
    const credsPath = path.join(credentialPath, "creds.json");

    try {
      if (fs.existsSync(credsPath)) {
        const creds = JSON.parse(fs.readFileSync(credsPath, "utf8"));
        if (creds?.registered === false) {
          console.log(`Removing stale unregistered pairing state for ${token}`);
          removeCredentialFolder(token);
          delete pairingCode[token];
          delete qrcode[token];
        }
      }
    } catch (error) {
      console.log(`Failed checking pairing state for ${token}:`, error.message);
    }
  }

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

  if (!hasCredentialState(token)) {
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

async function relayStructuredToResolvedRecipient(token, socket, number, content, options = {}) {
  const candidates = await buildRecipientCandidates(token, socket, number);
  for (const jid of candidates) {
    try {
      const waMessage = generateWAMessageFromContent(jid, content, {
        userJid: socket.user.id,
        ...options,
      });
      await socket.relayMessage(jid, waMessage.message, {
        messageId: waMessage.key.id,
      });
      rememberRecipientJids(token, number, jid);
      return waMessage;
    } catch (error) {
      continue;
    }
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

async function lookupWhatsAppJids(socket, number) {
  const normalizedNumber = normalizeLookupNumber(number);
  if (!normalizedNumber) {
    return [];
  }

  try {
    const results = await socket.onWhatsApp(normalizedNumber);
    return (results || [])
      .map((entry) => jidNormalizedUser(entry?.jid || ""))
      .filter(Boolean);
  } catch (error) {
    return [];
  }
}

async function buildRecipientCandidates(token, socket, number) {
  const rawNumber = String(number || "").trim();
  if (!rawNumber) {
    return [];
  }

  if (rawNumber.includes("@g.us") || rawNumber.includes("@s.whatsapp.net") || rawNumber.includes("@lid")) {
    return [jidNormalizedUser(rawNumber)];
  }

  const normalizedNumber = normalizeLookupNumber(rawNumber);
  if (!normalizedNumber) {
    return [];
  }

  const candidates = [];
  const pushCandidate = (candidate) => {
    const normalizedCandidate = jidNormalizedUser(String(candidate || "").trim());
    if (normalizedCandidate && !candidates.includes(normalizedCandidate)) {
      candidates.push(normalizedCandidate);
    }
  };

  const cachedCandidates = getCachedRecipientJids(token, normalizedNumber);
  cachedCandidates.forEach(pushCandidate);

  const storedCandidates = candidates.length > 0 ? [] : await loadStoredRecipientJids(token, normalizedNumber);
  storedCandidates.forEach(pushCandidate);

  const lookupCandidates = candidates.length > 0 ? [] : await lookupWhatsAppJids(socket, normalizedNumber);
  if (lookupCandidates.length > 0) {
    rememberRecipientJids(token, normalizedNumber, lookupCandidates);
  }
  lookupCandidates.forEach(pushCandidate);
  pushCandidate(formatReceipt(normalizedNumber));

  return candidates;
}

async function sendToResolvedRecipient(token, socket, number, payload, options) {
  const candidates = await buildRecipientCandidates(token, socket, number);
  for (const jid of candidates) {
    try {
      const response = await socket.sendMessage(jid, payload, options);
      if (response) {
        rememberRecipientJids(token, number, jid);
        return response;
      }
    } catch (error) {
      continue;
    }
  }

  return false;
}

const sendText = async (token, number, text) => {
  try {
    const socket = await ensureSocket(token);
    if (!socket) {
      return false;
    }

    return await sendToResolvedRecipient(token, socket, number, { text });
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
    return await sendToResolvedRecipient(token, socket, number, normalizedPayload);
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
      return await sendToResolvedRecipient(token, socket, number, {
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

    return await sendToResolvedRecipient(token, socket, number, preparedPayload);
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

    if (image) {
      const payload = {
        image: { url: image },
        caption: message,
        footer,
        buttons,
        headerType: 4,
        viewOnce: true,
      };

      return await sendToResolvedRecipient(token, socket, number, payload);
    }

    return await relayStructuredToResolvedRecipient(
      token,
      socket,
      number,
      proto.Message.fromObject({
        buttonsMessage: {
          contentText: message,
          footerText: footer,
          headerType: proto.Message.ButtonsMessage.HeaderType.EMPTY,
          buttons: buttons.map((item) => ({
            buttonId: item.buttonId,
            buttonText: { displayText: item.buttonText.displayText },
            type: proto.Message.ButtonsMessage.Button.Type.RESPONSE,
          })),
        },
      })
    );
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

    return await sendToResolvedRecipient(token, socket, number, payload);
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

    return await relayStructuredToResolvedRecipient(
      token,
      socket,
      number,
      proto.Message.fromObject({
        listMessage: {
          title,
          description: text,
          buttonText,
          footerText: footer,
          listType: proto.Message.ListMessage.ListType.SINGLE_SELECT,
          sections: [list],
        },
      }),
      { ephemeralExpiration: 604800 }
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

    return await sendToResolvedRecipient(token, socket, number, {
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

    const rawNumber = String(number || "").trim();
  if (rawNumber.includes("@g.us") || rawNumber.includes("@s.whatsapp.net") || rawNumber.includes("@lid")) {
    return true;
  }

    const cachedCandidates = getCachedRecipientJids(token, rawNumber);
    if (cachedCandidates.length > 0) {
      return true;
    }

    const storedCandidates = await loadStoredRecipientJids(token, rawNumber);
    if (storedCandidates.length > 0) {
      return true;
    }

    const lookupCandidates = await lookupWhatsAppJids(socket, rawNumber);
    if (lookupCandidates.length > 0) {
      rememberRecipientJids(token, rawNumber, lookupCandidates);
    }
    return lookupCandidates.length > 0;
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

  if (!hasCredentialState(token)) {
    return res.send({ status: false, message: `${token} connection failed` });
  }

  if (isSocketConnected(token)) {
    await emitConnectedState(token, req.io);
    return res.status(200).json({ status: true, message: `${token} already connected` });
  }

  if (activeConnectPromises[token]) {
    await activeConnectPromises[token];
    if (isSocketConnected(token)) {
      await emitConnectedState(token, req.io);
      return res.status(200).json({ status: true, message: `${token} connection restored` });
    }
  }

  const connected = await connectWaBeforeSend(token);
  if (connected) {
    return res.status(200).json({ status: true, message: `${token} connection restored` });
  }

  return res.status(200).json({ status: false, message: `${token} connection failed` });
}

async function restoreSessions(io = null) {
  const tokens = listStoredTokens();
  if (tokens.length === 0) {
    return [];
  }

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
