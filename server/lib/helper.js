const {
  downloadContentFromMessage,
  getContentType,
} = require("@whiskeysockets/baileys");
const axios = require("axios");
const fs = require("fs");
const mime = require("mime-types");
const path = require("path");

function unwrapMessageContent(message = {}) {
  if (!message || typeof message !== "object") {
    return {};
  }

  if (message.ephemeralMessage?.message) {
    return unwrapMessageContent(message.ephemeralMessage.message);
  }

  if (message.viewOnceMessage?.message) {
    return unwrapMessageContent(message.viewOnceMessage.message);
  }

  if (message.viewOnceMessageV2?.message) {
    return unwrapMessageContent(message.viewOnceMessageV2.message);
  }

  if (message.viewOnceMessageV2Extension?.message) {
    return unwrapMessageContent(message.viewOnceMessageV2Extension.message);
  }

  if (message.documentWithCaptionMessage?.message) {
    return unwrapMessageContent(message.documentWithCaptionMessage.message);
  }

  if (message.editedMessage?.message) {
    return unwrapMessageContent(message.editedMessage.message);
  }

  return message;
}

function formatReceipt(value) {
  if (!value) {
    return value;
  }

  if (value.includes("@g.us") || value.includes("@s.whatsapp.net")) {
    return value;
  }

  let digits = String(value).replace(/\D/g, "");
  if (!digits) {
    return value;
  }

  if (digits.startsWith("0")) {
    digits = `62${digits.slice(1)}`;
  }

  return `${digits}@s.whatsapp.net`;
}

async function asyncForEach(items, callback) {
  for (let index = 0; index < items.length; index += 1) {
    await callback(items[index], index, items);
  }
}

function removeForbiddenCharacters(value = "") {
  return String(value).replace(/[\x00-\x1F\x7F-\x9F'\\"]/g, "");
}

async function bufferFromStream(stream) {
  const chunks = [];
  for await (const chunk of stream) {
    chunks.push(chunk);
  }

  return Buffer.concat(chunks);
}

function extractTextMessage(message = {}) {
  const normalizedMessage = unwrapMessageContent(message);
  const messageType = getContentType(normalizedMessage);
  const content = messageType ? normalizedMessage[messageType] : null;

  switch (messageType) {
    case "conversation":
      return normalizedMessage.conversation || "";
    case "extendedTextMessage":
      return content?.text || "";
    case "imageMessage":
    case "videoMessage":
    case "documentMessage":
      return content?.caption || "";
    case "buttonsResponseMessage":
      return content?.selectedDisplayText || content?.selectedButtonId || "";
    case "listResponseMessage":
      return content?.title || content?.singleSelectReply?.selectedRowId || "";
    case "templateButtonReplyMessage":
      return content?.selectedDisplayText || content?.selectedId || "";
    default:
      return "";
  }
}

async function parseIncomingMessage(message) {
  const normalizedMessage = unwrapMessageContent(message?.message || {});
  const command = removeForbiddenCharacters(extractTextMessage(normalizedMessage).toLowerCase().trim());
  const remoteJid = message?.key?.remoteJid || "";
  const from = remoteJid.split("@")[0] || "";
  let bufferImage;

  if (normalizedMessage?.imageMessage) {
    const stream = await downloadContentFromMessage(normalizedMessage.imageMessage, "image");
    const buffer = await bufferFromStream(stream);
    bufferImage = buffer.toString("base64");
  }

  return {
    command,
    bufferImage,
    from,
    messageType: getContentType(normalizedMessage),
  };
}

function getSavedPhoneNumber(token) {
  const formatted = formatReceipt(token);
  return String(formatted).replace("@s.whatsapp.net", "");
}

async function resolveMimeType(mediaUrl, fallbackType) {
  const fileMime = mime.lookup(mediaUrl);
  if (fileMime) {
    return fileMime;
  }

  try {
    const response = await axios.head(mediaUrl, { timeout: 10000 });
    return response.headers["content-type"] || fallbackType || "application/octet-stream";
  } catch (error) {
    return fallbackType || "application/octet-stream";
  }
}

async function prepareMediaMessage(sock, details) {
  const mediaType = details.mediatype === "document" ? "document" : details.mediatype;
  const mimeType = await resolveMimeType(details.media, mediaType === "audio" ? "audio/mpeg" : undefined);
  const payload = {
    [mediaType]: {
      url: details.media,
    },
  };

  if (details.caption && mediaType !== "audio") {
    payload.caption = details.caption;
  }

  if (mediaType === "document") {
    payload.fileName = details.fileName || path.basename(details.media || "document");
    payload.mimetype = mimeType;
  }

  if (mediaType === "audio") {
    payload.mimetype = mimeType;
    payload.ptt = Boolean(details.ptt);
  }

  if (mediaType === "video" && fs.existsSync(path.join(process.cwd(), "public", "images", "video-cover.png"))) {
    payload.jpegThumbnail = Uint8Array.from(
      fs.readFileSync(path.join(process.cwd(), "public", "images", "video-cover.png"))
    );
    payload.gifPlayback = false;
  }

  return payload;
}

module.exports = {
  formatReceipt,
  asyncForEach,
  removeForbiddenCharacters,
  unwrapMessageContent,
  parseIncomingMessage,
  getSavedPhoneNumber,
  prepareMediaMessage,
};
