const axios = require("axios");
const { generateWAMessageFromContent, jidNormalizedUser, proto } = require("@whiskeysockets/baileys");
const { parseIncomingMessage, formatReceipt, prepareMediaMessage } = require("../lib/helper");
const {
  isExistsEqualCommand,
  isExistsContainCommand,
  getFirstChatRules,
  hasIncomingMessageLog,
  saveIncomingMessageLog,
  getUrlWebhook,
  getActiveAiBotForDevice,
  getRegisteredPhonebookIdsForNumber,
  isContactPaused,
  pauseContactForOperator,
  resumeContactPause,
  saveAutoReplyHistory,
} = require("../database/model");

require("dotenv").config();

const PERSISTENT_MENU_ITEMS = [
  { key: "daftar pemasangan", label: "Daftar pemasangan" },
  { key: "lihat paket", label: "Lihat paket" },
  { key: "voucher hotspot", label: "Voucher hotspot" },
  { key: "cek tagihan", label: "Cek tagihan" },
  { key: "cara bayar", label: "Cara bayar" },
  { key: "lapor gangguan", label: "Lapor gangguan" },
  { key: "restart modem", label: "Restart modem" },
];

const BOT_OUTGOING_MESSAGE_IDS = new Map();
const RECENT_STRUCTURED_BOT_SENDS = new Map();
const RECENT_INCOMING_MESSAGE_IDS = new Map();
const RECENT_INCOMING_COMMANDS = new Map();

function buildProtoListMessage(replyPayload) {
  const normalizedSections = Array.isArray(replyPayload?.sections)
    ? replyPayload.sections
        .map((section, sectionIndex) => {
          const rows = Array.isArray(section?.rows)
            ? section.rows
                .map((row, rowIndex) => ({
                  title: row?.title ? String(row.title) : `Opsi ${rowIndex + 1}`,
                  rowId: row?.rowId ? String(row.rowId) : `row-${sectionIndex + 1}-${rowIndex + 1}`,
                  description: row?.description ? String(row.description) : "",
                }))
                .filter((row) => row.title)
            : [];

          if (rows.length === 0) {
            return null;
          }

          return {
            title: section?.title ? String(section.title) : `Pilihan ${sectionIndex + 1}`,
            rows,
          };
        })
        .filter(Boolean)
    : [];

  if (normalizedSections.length === 0) {
    return null;
  }

  const nativeFlowParams = {
    title: replyPayload?.title ? String(replyPayload.title) : "Pilih Menu",
    sections: normalizedSections.map((section) => ({
      title: section.title,
      rows: section.rows.map((row) => ({
        id: row.rowId,
        title: row.title,
        description: row.description || "",
      })),
    })),
  };

  return proto.Message.fromObject({
    buttonsMessage: {
      contentText: replyPayload?.text ? String(replyPayload.text) : "",
      footerText: replyPayload?.footer ? String(replyPayload.footer) : "",
      headerType: proto.Message.ButtonsMessage.HeaderType.EMPTY,
      buttons: [
        {
          buttonId: "native-flow-menu",
          buttonText: {
            displayText: replyPayload?.buttonText ? String(replyPayload.buttonText) : "Pilih Menu",
          },
          type: proto.Message.ButtonsMessage.Button.Type.NATIVE_FLOW,
          nativeFlowInfo: {
            name: "single_select",
            paramsJson: JSON.stringify(nativeFlowParams),
          },
        },
      ],
    },
  });
}

function buildProtoButtonsMessage(replyPayload) {
  const buttons = Array.isArray(replyPayload?.buttons)
    ? replyPayload.buttons.map((button, index) => ({
        buttonId: button?.buttonId ? String(button.buttonId) : `button-${index + 1}`,
        buttonText: {
          displayText: button?.buttonText?.displayText
            ? String(button.buttonText.displayText)
            : `Tombol ${index + 1}`,
        },
        type: proto.Message.ButtonsMessage.Button.Type.RESPONSE,
      }))
    : [];

  return proto.Message.fromObject({
    buttonsMessage: {
      contentText: replyPayload?.text ? String(replyPayload.text) : "",
      footerText: replyPayload?.footer ? String(replyPayload.footer) : "",
      headerType: proto.Message.ButtonsMessage.HeaderType.EMPTY,
      buttons,
    },
  });
}

function hasNativeFlowButton(replyPayload) {
  return Array.isArray(replyPayload?.buttons)
    && replyPayload.buttons.some((button) =>
      Number(button?.type) === Number(proto.Message.ButtonsMessage.Button.Type.NATIVE_FLOW)
      || button?.nativeFlowInfo
    );
}

function buildProtoInteractiveMessage(replyPayload) {
  if (!replyPayload || typeof replyPayload !== "object" || Array.isArray(replyPayload)) {
    return null;
  }

  if (Array.isArray(replyPayload.sections)) {
    return buildProtoListMessage(replyPayload);
  }

  if (Array.isArray(replyPayload.buttons)) {
    return buildProtoButtonsMessage(replyPayload);
  }

  return null;
}

function resolveReplyTargetJid(message) {
  const key = message?.key || {};
  const remoteJid = String(key.remoteJid || "").trim();
  if (!remoteJid || remoteJid.endsWith("@g.us") || remoteJid.endsWith("@broadcast")) {
    return remoteJid;
  }

  const phoneJid = [key.participantPn, key.senderPn, key.participant, remoteJid]
    .map((value) => jidNormalizedUser(String(value || "").trim()))
    .find((value) => value.endsWith("@s.whatsapp.net"));

  return phoneJid || remoteJid;
}

function shouldReplyToContext(replyWhen, remoteJid) {
  if (replyWhen === "All") {
    return true;
  }

  if (replyWhen === "Group") {
    return remoteJid.includes("@g.us");
  }

  if (replyWhen === "Personal") {
    return !remoteJid.includes("@g.us");
  }

  return false;
}

function isGroupChat(remoteJid) {
  return String(remoteJid || "").includes("@g.us");
}

function trackBotOutgoingMessage(messageId) {
  if (!messageId) {
    return;
  }

  BOT_OUTGOING_MESSAGE_IDS.set(String(messageId), Date.now());
}

function isBotOutgoingMessage(messageId) {
  if (!messageId) {
    return false;
  }

  const key = String(messageId);
  const timestamp = BOT_OUTGOING_MESSAGE_IDS.get(key);
  if (!timestamp) {
    return false;
  }

  BOT_OUTGOING_MESSAGE_IDS.delete(key);
  return true;
}

function pruneBotOutgoingMessages() {
  const cutoff = Date.now() - 5 * 60 * 1000;
  for (const [messageId, timestamp] of BOT_OUTGOING_MESSAGE_IDS.entries()) {
    if (timestamp < cutoff) {
      BOT_OUTGOING_MESSAGE_IDS.delete(messageId);
    }
  }

  const structuredCutoff = Date.now() - 30 * 1000;
  for (const [jid, timestamp] of RECENT_STRUCTURED_BOT_SENDS.entries()) {
    if (timestamp < structuredCutoff) {
      RECENT_STRUCTURED_BOT_SENDS.delete(jid);
    }
  }

  const incomingMessageCutoff = Date.now() - 5 * 60 * 1000;
  for (const [messageId, timestamp] of RECENT_INCOMING_MESSAGE_IDS.entries()) {
    if (timestamp < incomingMessageCutoff) {
      RECENT_INCOMING_MESSAGE_IDS.delete(messageId);
    }
  }

  const incomingCommandCutoff = Date.now() - 15 * 1000;
  for (const [commandKey, timestamp] of RECENT_INCOMING_COMMANDS.entries()) {
    if (timestamp < incomingCommandCutoff) {
      RECENT_INCOMING_COMMANDS.delete(commandKey);
    }
  }
}

function trackRecentStructuredBotSend(remoteJid) {
  if (!remoteJid) {
    return;
  }

  RECENT_STRUCTURED_BOT_SENDS.set(String(remoteJid), Date.now());
}

function isRecentStructuredBotSend(remoteJid) {
  if (!remoteJid) {
    return false;
  }

  const timestamp = RECENT_STRUCTURED_BOT_SENDS.get(String(remoteJid));
  if (!timestamp) {
    return false;
  }

  RECENT_STRUCTURED_BOT_SENDS.delete(String(remoteJid));
  return Date.now() - timestamp <= 30 * 1000;
}

function isDuplicateIncomingMessageId(messageId) {
  if (!messageId) {
    return false;
  }

  const key = String(messageId);
  const timestamp = RECENT_INCOMING_MESSAGE_IDS.get(key);
  if (!timestamp) {
    return false;
  }

  return Date.now() - timestamp <= 5 * 60 * 1000;
}

function trackIncomingMessageId(messageId) {
  if (!messageId) {
    return;
  }

  RECENT_INCOMING_MESSAGE_IDS.set(String(messageId), Date.now());
}

function buildIncomingCommandKey(chatIdentity, command, messageType) {
  const normalizedChatIdentity = String(chatIdentity || "").trim();
  const normalizedCommand = String(command || "").trim().toLowerCase();
  const normalizedMessageType = String(messageType || "").trim();

  if (!normalizedChatIdentity || !normalizedCommand) {
    return "";
  }

  return `${normalizedChatIdentity}::${normalizedMessageType}::${normalizedCommand}`;
}

function isDuplicateIncomingCommand(chatIdentity, command, messageType) {
  const key = buildIncomingCommandKey(chatIdentity, command, messageType);
  if (!key) {
    return false;
  }

  const timestamp = RECENT_INCOMING_COMMANDS.get(key);
  if (!timestamp) {
    return false;
  }

  return Date.now() - timestamp <= 8 * 1000;
}

function trackIncomingCommand(chatIdentity, command, messageType) {
  const key = buildIncomingCommandKey(chatIdentity, command, messageType);
  if (!key) {
    return;
  }

  RECENT_INCOMING_COMMANDS.set(key, Date.now());
}

function getCurrentRuleDateParts() {
  const timeZone = process.env.APP_TIMEZONE || process.env.TZ || "Asia/Jakarta";
  const formatter = new Intl.DateTimeFormat("en-US", {
    timeZone,
    weekday: "long",
    hour: "2-digit",
    minute: "2-digit",
    hour12: false,
  });

  const parts = formatter.formatToParts(new Date());
  const weekday = parts.find((part) => part.type === "weekday");
  const hour = parts.find((part) => part.type === "hour");
  const minute = parts.find((part) => part.type === "minute");

  return {
    weekday: weekday ? weekday.value.toLowerCase() : "",
    currentTime: `${hour ? hour.value : "00"}:${minute ? minute.value : "00"}`,
  };
}

function normalizeScheduleDays(rawDays) {
  if (!rawDays) {
    return [];
  }

  if (Array.isArray(rawDays)) {
    return rawDays.map((day) => String(day).toLowerCase());
  }

  if (typeof rawDays === "string") {
    try {
      const parsed = JSON.parse(rawDays);
      return Array.isArray(parsed) ? parsed.map((day) => String(day).toLowerCase()) : [];
    } catch (error) {
      return [];
    }
  }

  return [];
}

function isScheduledRuleActive(rule) {
  if (!rule || rule.schedule_mode !== "scheduled") {
    return true;
  }

  const { weekday, currentTime } = getCurrentRuleDateParts();
  const days = normalizeScheduleDays(rule.schedule_days);

  if (days.length > 0 && !days.includes(weekday)) {
    return false;
  }

  if (!rule.schedule_start || !rule.schedule_end) {
    return true;
  }

  const start = String(rule.schedule_start).slice(0, 5);
  const end = String(rule.schedule_end).slice(0, 5);

  if (start <= end) {
    return currentTime >= start && currentTime <= end;
  }

  return currentTime >= start || currentTime <= end;
}

function normalizePhoneNumber(value) {
  let digits = String(value || "").replace(/\D/g, "");
  if (!digits) {
    return "";
  }

  if (digits.startsWith("00")) {
    digits = digits.slice(2);
  }

  if (digits.startsWith("0")) {
    return `62${digits.slice(1)}`;
  }

  if (digits.startsWith("8")) {
    return `62${digits}`;
  }

  return digits;
}

function normalizeCustomerName(pushName) {
  return String(pushName || "").replace(/\s+/g, " ").trim();
}

function buildCustomerDisplayName(pushName) {
  const name = normalizeCustomerName(pushName);
  return name ? `Kak ${name}` : "Kak";
}

function resolveMenuShortcut(command) {
  const normalizedCommand = String(command || "").trim().toLowerCase();
  const match = normalizedCommand.match(/^([1-7])(?:[\).\-\s].*)?$/);
  if (!match) {
    return null;
  }

  const selectedItem = PERSISTENT_MENU_ITEMS[Number(match[1]) - 1];
  return selectedItem ? selectedItem.key : null;
}

function resolveIncomingCommand(command) {
  return resolveMenuShortcut(command) || String(command || "").trim().toLowerCase();
}

function isResumeBotCommand(command) {
  const normalizedCommand = String(command || "").trim().toLowerCase();
  return normalizedCommand === "menu";
}

function isMenuCommand(command) {
  const normalizedCommand = String(command || "").trim().toLowerCase();
  return normalizedCommand === "menu" || normalizedCommand === "manu";
}

function isPaymentProofMessage(messageType, command) {
  const normalizedCommand = String(command || "").trim().toLowerCase();

  if (["imageMessage", "videoMessage", "documentMessage"].includes(messageType) && !normalizedCommand) {
    return true;
  }

  return [
    "bukti tf",
    "bukti transfer",
    "foto bukti",
    "struk transfer",
    "sudah transfer",
    "transfer sudah",
  ].some((phrase) => normalizedCommand.includes(phrase));
}

function isRegistrationSubmissionMessage(command) {
  const normalizedCommand = String(command || "").trim().toLowerCase();
  return [
    "pendaftaran wifi bumdes sickas",
    "pendaftaran wifi",
    "id form: wifi-",
    "link pdf formulir:",
    "form: wifi-",
  ].some((phrase) => normalizedCommand.includes(phrase));
}

function buildMenuButtonPayload(text, footer = "") {
  return {
    text,
    buttons: [
      {
        buttonId: "menu",
        buttonText: { displayText: "Menu" },
        type: 1,
      },
    ],
    footer,
    headerType: 1,
    viewOnce: true,
  };
}

function attachMenuButton(replyPayload) {
  if (
    replyPayload &&
    typeof replyPayload === "object" &&
    !Array.isArray(replyPayload) &&
    !replyPayload.type &&
    !Array.isArray(replyPayload.buttons) &&
    !Array.isArray(replyPayload.sections) &&
    !Array.isArray(replyPayload.templateButtons) &&
    typeof replyPayload.text === "string"
  ) {
    const normalizedText = replyPayload.text.toLowerCase();
    if (normalizedText.includes("tombol menu") || normalizedText.includes("tekan tombol menu")) {
      return buildMenuButtonPayload(replyPayload.text, replyPayload.footer || "");
    }
  }

  return replyPayload;
}

function buildPersistentMenuPayload(pushName) {
  const name = buildCustomerDisplayName(pushName);
  return {
    text:
      `Halo ${name}, silakan pilih kebutuhan Kakak dengan balas angka di bawah ini ya.\n\n` +
      "Contoh:\n" +
      "- Balas *1* untuk daftar pemasangan\n" +
      "- Balas *3* untuk voucher hotspot\n" +
      "- Balas *6* untuk lapor gangguan",
    footer: "",
    title: "Menu SICKAS WiFi",
    buttonText: "Lihat Menu",
    sections: [
      {
        title: "Layanan Utama",
        rows: PERSISTENT_MENU_ITEMS.map((item, index) => ({
          title: `${index + 1}. ${item.label}`,
          rowId: item.key,
          description: `Balas ${index + 1}`,
        })),
      },
    ],
  };
}

function shouldSendPersistentMenu(matchedReply, replyPayload, remoteJid) {
  if (!matchedReply) {
    return false;
  }

  if (matchedReply.trigger_event === "first_chat") {
    return false;
  }

  if (String(remoteJid || "").includes("@lid")) {
    return false;
  }

  if (Array.isArray(replyPayload?.sections)) {
    return false;
  }

  if (hasNativeFlowButton(replyPayload)) {
    return false;
  }

  const normalizedKeyword = String(matchedReply.keyword || "").trim().toLowerCase();
  const normalizedName = String(matchedReply.name || "").trim().toLowerCase();

  return !(
    normalizedKeyword === "menu" ||
    normalizedName.includes("welcome") ||
    normalizedName.includes("menu cs") ||
    normalizedName.includes("salam")
  );
}

async function pickEligibleReply(replies, remoteJid, senderNumber) {
  const normalizedSender = normalizePhoneNumber(senderNumber);
  for (const reply of replies) {
    if (!shouldReplyToContext(reply.reply_when, remoteJid)) {
      continue;
    }

    if (reply.contact_tag_id) {
      const registeredTagIds = await getRegisteredPhonebookIdsForNumber(reply.user_id, normalizedSender);
      if (!registeredTagIds.includes(Number(reply.contact_tag_id))) {
        continue;
      }
    }

    if (!isScheduledRuleActive(reply)) {
      continue;
    }

    return reply;
  }

  return null;
}

function replaceTemplateVariables(payload, pushName) {
  if (typeof payload === "string") {
    return payload.replace(/{name}/g, pushName || "");
  }

  if (Array.isArray(payload)) {
    return payload.map((item) => replaceTemplateVariables(item, pushName));
  }

  if (payload && typeof payload === "object") {
    return Object.fromEntries(
      Object.entries(payload).map(([key, value]) => [key, replaceTemplateVariables(value, pushName)])
    );
  }

  return payload;
}

function shouldPauseForOperatorHandoff(matchedReply, command) {
  return false;
}

function buildRegistrationThankYouPayload(pushName) {
  const name = buildCustomerDisplayName(pushName);
  return {
    text:
      `Halo ${name}, terima kasih sudah mendaftar di *SICKAS WiFi - BUMDes SICKAS*.\n\n` +
      "Data pendaftaran Anda sudah kami terima. Silakan tunggu proses selanjutnya dari admin ya.",
  };
}

function buildTroubleshootingReply(pushName) {
  const name = buildCustomerDisplayName(pushName);
  return {
    text:
      `Halo ${name}, silakan coba pengecekan berikut ya:\n` +
      "1. Lihat indikator modem.\n" +
      "2. Pastikan lampu Power menyala normal.\n" +
      "3. Jika lampu LOS merah atau berkedip merah, kemungkinan ada gangguan jalur.\n" +
      "4. Cabut adaptor modem selama 1 menit, lalu colok kembali.\n" +
      "5. Tunggu 3 sampai 5 menit sampai lampu stabil.\n" +
      "6. Coba tes internet kembali.\n\n" +
      "Kalau masih bermasalah, balas di chat ini dengan nama pelanggan, alamat pemasangan, dan kondisi lampu modem ya.",
  };
}

function shouldUseTroubleshootingReply(matchedReply) {
  if (!matchedReply) {
    return false;
  }

  const normalizedKeyword = String(matchedReply.keyword || "").trim().toLowerCase();
  return normalizedKeyword === "lapor gangguan" || normalizedKeyword === "gangguan" || normalizedKeyword === "lemot";
}

function buildPaymentConfirmationPayload(pushName) {
  const name = buildCustomerDisplayName(pushName);
  return {
    text:
      `Halo ${name}, terima kasih. Jika pembayaran sudah dilakukan, silakan tunggu proses pengecekan ya.\n\n` +
      "Admin akan mengecek pembayaran Anda di chat ini.",
  };
}

function shouldUsePaymentConfirmation(matchedReply) {
  if (!matchedReply) {
    return false;
  }

  return String(matchedReply.keyword || "").trim().toLowerCase() === "bayar";
}

function normalizeReplyPayloadForContext(replyPayload, matchedReply, pushName) {
  if (shouldUseTroubleshootingReply(matchedReply)) {
    return buildTroubleshootingReply(pushName);
  }

  if (shouldUsePaymentConfirmation(matchedReply)) {
    return buildPaymentConfirmationPayload(pushName);
  }

  return replyPayload;
}

function normalizeWebhookResponse(response) {
  if (response === undefined || response === null || response === false) {
    return null;
  }

  if (typeof response === "string") {
    try {
      return JSON.parse(response);
    } catch (error) {
      return response;
    }
  }

  return response;
}

function isInteractivePayload(replyPayload) {
  if (!replyPayload || typeof replyPayload !== "object" || Array.isArray(replyPayload)) {
    return false;
  }

  return (
    Array.isArray(replyPayload.buttons) ||
    Array.isArray(replyPayload.sections) ||
    Array.isArray(replyPayload.templateButtons)
  );
}

function isListPayload(replyPayload) {
  return (
    replyPayload &&
    typeof replyPayload === "object" &&
    !Array.isArray(replyPayload) &&
    Array.isArray(replyPayload.sections)
  );
}

function supportsSafeInteractiveSync(remoteJid) {
  const jid = String(remoteJid || "").toLowerCase();
  return jid.endsWith("@s.whatsapp.net") || jid.endsWith("@g.us") || jid.endsWith("@lid");
}

function extractSenderNumber(message, fallback = "") {
  const personalSenderJid =
    String(message?.key?.participantPn || "").trim() ||
    String(message?.key?.senderPn || "").trim() ||
    String(message?.key?.participant || "").trim() ||
    "";

  const candidate = personalSenderJid || String(fallback || "").trim();
  return candidate ? candidate.split("@")[0] : "";
}

function interactivePayloadToText(replyPayload) {
  if (!isInteractivePayload(replyPayload)) {
    return replyPayload;
  }

  const lines = [];
  const mainText = replyPayload.text || replyPayload.caption || "";
  if (mainText) {
    lines.push(mainText);
  }

  if (Array.isArray(replyPayload.sections)) {
    replyPayload.sections.forEach((section) => {
      const title = section?.title ? String(section.title).trim() : "";
      if (title) {
        lines.push("");
        lines.push(title);
      }

      const rows = Array.isArray(section?.rows) ? section.rows : [];
      rows.forEach((row, index) => {
        const rowTitle = row?.title ? String(row.title).trim() : `Opsi ${index + 1}`;
        const rowDescription = row?.description ? String(row.description).trim() : "";
        const linePrefix = /^\d+[\.\)]\s+/.test(rowTitle) ? "" : `${index + 1}. `;
        lines.push(`${linePrefix}${rowTitle}${rowDescription ? ` - ${rowDescription}` : ""}`);
      });
    });
  }

  if (Array.isArray(replyPayload.buttons)) {
    lines.push("");
    replyPayload.buttons.forEach((button, index) => {
      const label = button?.buttonText?.displayText || button?.buttonId || `Tombol ${index + 1}`;
      lines.push(`${index + 1}. ${String(label).trim()}`);
    });
  }

  if (Array.isArray(replyPayload.templateButtons)) {
    lines.push("");
    replyPayload.templateButtons.forEach((button, index) => {
      const urlLabel = button?.urlButton?.displayText;
      const callLabel = button?.callButton?.displayText;
      const label = urlLabel || callLabel || `Template ${index + 1}`;
      lines.push(`${index + 1}. ${String(label).trim()}`);
    });
  }

  if (replyPayload.footer) {
    lines.push("");
    lines.push(replyPayload.footer);
  }

  return {
    text: lines.filter((line, index, items) => {
      if (line !== "") {
        return true;
      }

      return index > 0 && items[index - 1] !== "";
    }).join("\n").trim(),
  };
}

function normalizeReplyPayloadForTransport(replyPayload, remoteJid) {
  return resolveTransportPayload(replyPayload, remoteJid, null).payload;
}

function resolveTransportPayload(replyPayload, remoteJid, matchedReply) {
  const policy = String(matchedReply?.transport_policy || "").trim() || "text_fallback";
  const defaultDecision = {
    payload: replyPayload,
    mode: "original",
    policy,
    reason: "non-interactive",
  };

  if (!isInteractivePayload(replyPayload)) {
    return defaultDecision;
  }

  if (policy === "text_fallback") {
    return {
      payload: interactivePayloadToText(replyPayload),
      mode: "fallback_text",
      policy,
      reason: "policy-forced",
    };
  }

  if (isListPayload(replyPayload)) {
    return {
      payload: replyPayload,
      mode: "interactive",
      policy,
      reason: "list-allowed",
    };
  }

  if (supportsSafeInteractiveSync(remoteJid)) {
    return {
      payload: replyPayload,
      mode: "interactive",
      policy,
      reason: "safe-sync-target",
    };
  }

  return {
    payload: interactivePayloadToText(replyPayload),
    mode: "fallback_text",
    policy,
    reason: "unsafe-sync-target",
  };
}

function inferHistoryType(payload, matchedReply) {
  if (matchedReply?.type === "ai") {
    return "text";
  }

  if (typeof payload === "string") {
    return "text";
  }

  if (!payload || typeof payload !== "object") {
    return matchedReply?.type || "text";
  }

  if (Array.isArray(payload.sections)) {
    return "list";
  }

  if (Array.isArray(payload.buttons)) {
    if (hasNativeFlowButton(payload)) {
      return "list";
    }

    return "button";
  }

  if (Array.isArray(payload.templateButtons)) {
    return "template";
  }

  if (payload.text) {
    return "text";
  }

  if (payload.image || payload.video || payload.document || payload.audio || payload.type) {
    return "media";
  }

  return matchedReply?.type || "text";
}

function extractHistoryMessage(payload) {
  if (typeof payload === "string") {
    return payload;
  }

  if (!payload || typeof payload !== "object") {
    return "";
  }

  return String(payload.text || payload.caption || payload.footer || "Auto reply sent").trim();
}

async function sendWebhook({ command, bufferImage, from, url, participant }) {
  try {
    const response = await axios.post(
      url,
      {
        message: command,
        bufferImage: bufferImage || null,
        from,
        participant,
      },
      {
        headers: {
          "Content-Type": "application/json; charset=utf-8",
        },
        timeout: 15000,
      }
    );

    return response.data;
  } catch (error) {
    console.log("error send webhook", error.message);
    return false;
  }
}

function isTextLikeMessage(messageType, command) {
  const supportedTypes = [
    "conversation",
    "extendedTextMessage",
    "buttonsResponseMessage",
    "listResponseMessage",
    "interactiveResponseMessage",
    "templateButtonReplyMessage",
    "imageMessage",
    "videoMessage",
    "documentMessage",
  ];

  return supportedTypes.includes(messageType) && String(command || "").trim() !== "";
}

async function requestInternalAiReply({ matchedReply, botId, aiRoute, message, command, participant, pushName, deviceBody }) {
  const appUrl = process.env.APP_URL;
  const internalToken = process.env.AI_INTERNAL_TOKEN;

  if (!appUrl || !internalToken) {
    console.log("[ai-bot] internal endpoint config missing");
    return null;
  }

  try {
    const response = await axios.post(
      `${String(appUrl).replace(/\/$/, "")}/internal/ai/respond`,
      {
        device_body: deviceBody,
        chat_jid: message.key.remoteJid,
        participant,
        push_name: pushName,
        incoming_text: command,
        matched_rule_id: matchedReply ? matchedReply.id : null,
        bot_id: botId || matchedReply?.ai_bot_id || matchedReply?.reply?.ai_bot_id || null,
        ai_route: aiRoute || (matchedReply ? "rule" : "default"),
        context_type: message.key.remoteJid.includes("@g.us") ? "group" : "personal",
        whatsapp_message_id: message.key.id,
      },
      {
        headers: {
          "X-Internal-AI-Token": internalToken,
          "Content-Type": "application/json; charset=utf-8",
        },
        timeout: 30000,
      }
    );

    return normalizeWebhookResponse(response.data);
  } catch (error) {
    console.log("[ai-bot] internal request failed", error.message);
    return null;
  }
}

async function sendAutoReply(sock, message, replyPayload, shouldQuote) {
  const targetJid = resolveReplyTargetJid(message);
  const quotedMessage = shouldQuote && targetJid === message.key.remoteJid ? message : undefined;

  console.log("[autoreply] resolved-target", {
    incomingJid: message?.key?.remoteJid || null,
    senderPn: message?.key?.senderPn || message?.key?.participantPn || null,
    targetJid,
  });

  if (typeof replyPayload === "string") {
      const response = await sock.sendMessage(
        targetJid,
        { text: replyPayload },
        { quoted: quotedMessage }
      );
      trackBotOutgoingMessage(response?.key?.id);
      return true;
    }

  if (replyPayload && typeof replyPayload === "object" && "type" in replyPayload) {
    if (replyPayload.type === "audio") {
        const response = await sock.sendMessage(
          targetJid,
          {
            audio: { url: replyPayload.url },
            ptt: true,
            mimetype: "audio/mpeg",
          },
          { quoted: quotedMessage }
        );
        trackBotOutgoingMessage(response?.key?.id);
        return true;
      }

    const preparedPayload = await prepareMediaMessage(sock, {
      caption: replyPayload.caption || "",
      fileName: replyPayload.filename,
      media: replyPayload.url,
      mediatype:
        replyPayload.type !== "video" && replyPayload.type !== "image" ? "document" : replyPayload.type,
      ptt: replyPayload.ptt,
    });

      const response = await sock.sendMessage(targetJid, preparedPayload, {
        quoted: quotedMessage,
      });
      trackBotOutgoingMessage(response?.key?.id);
      return true;
    }

  if (replyPayload && typeof replyPayload === "object" && "quoted" in replyPayload) {
    delete replyPayload.quoted;
  }

  const protoContent = buildProtoInteractiveMessage(replyPayload);
  if (protoContent) {
    if (Array.isArray(replyPayload?.sections)) {
      const previewPayload = interactivePayloadToText(replyPayload);
      if (previewPayload?.text) {
        const previewResponse = await sock.sendMessage(
          targetJid,
          { text: previewPayload.text },
          { quoted: quotedMessage }
        );
        trackBotOutgoingMessage(previewResponse?.key?.id);
      }
    }

    const generatedMessage = generateWAMessageFromContent(targetJid, protoContent, {
      userJid: sock.user.id,
      quoted: quotedMessage,
    });

    await sock.relayMessage(targetJid, generatedMessage.message, {
      messageId: generatedMessage.key.id,
    });
    trackBotOutgoingMessage(generatedMessage.key.id);
    trackRecentStructuredBotSend(message?.key?.remoteJid || targetJid);
    return true;
  }

  const response = await sock.sendMessage(targetJid, replyPayload, {
    quoted: quotedMessage,
  });
  trackBotOutgoingMessage(response?.key?.id);
  return true;
}

const IncomingMessage = async (upsert, sock) => {
  try {
    pruneBotOutgoingMessages();

    if (!upsert?.messages?.length) {
      return;
    }

    const message = upsert.messages[0];
    if (!message?.message) {
      return;
    }

    if (message?.key?.fromMe) {
      if (isBotOutgoingMessage(message?.key?.id)) {
        return;
      }

      if (isRecentStructuredBotSend(message?.key?.remoteJid)) {
        return;
      }

      if (message?.key?.remoteJid && message?.key?.remoteJid !== "status@broadcast") {
        const deviceBody = String(sock.user.id).split(":")[0];
        const participant = message.key.participant ? formatReceipt(message.key.participant) : undefined;
        const chatIdentity = participant || message.key.remoteJid;
        const senderNumber = participant
          ? participant.replace("@s.whatsapp.net", "")
          : String(message.key.remoteJid || "").split("@")[0];

        await pauseContactForOperator(
          deviceBody,
          chatIdentity,
          senderNumber,
          message.pushName || "",
          message.key.remoteJid.includes("@g.us") ? "group" : "personal",
          "Paused automatically after manual admin reply"
        );

        console.log("[autoreply] paused-by-admin-manual-message", {
          deviceBody,
          chatIdentity,
          messageId: message?.key?.id || null,
        });
      }

      return;
    }

    if (message?.key?.remoteJid === "status@broadcast") {
      return;
    }

    if (isGroupChat(message?.key?.remoteJid)) {
      console.log("[autoreply] skipped-group-chat", {
        remoteJid: message?.key?.remoteJid || null,
        messageId: message?.key?.id || null,
      });
      return;
    }

    const incomingMessageId = String(message?.key?.id || "").trim();
    if (isDuplicateIncomingMessageId(incomingMessageId)) {
      console.log("[autoreply] duplicate-message-skipped", {
        messageId: incomingMessageId,
        remoteJid: message?.key?.remoteJid || null,
      });
      return;
    }
    trackIncomingMessageId(incomingMessageId);

    const participant = message.key.participant ? formatReceipt(message.key.participant) : undefined;
    const { command: rawCommand, bufferImage, from, messageType, senderJid } = await parseIncomingMessage(message);
    const command = resolveIncomingCommand(rawCommand);
    const pushName = message.pushName || "";
    const customerDisplayName = buildCustomerDisplayName(pushName);
    const deviceBody = String(sock.user.id).split(":")[0];
    const chatIdentity = participant || message.key.remoteJid;
    const senderNumber = participant ? participant.replace("@s.whatsapp.net", "") : extractSenderNumber(message, senderJid || from);
    const isFirstChat = !(await hasIncomingMessageLog(deviceBody, chatIdentity));
    const isPausedForOperator = await isContactPaused(deviceBody, chatIdentity);

    if (isDuplicateIncomingCommand(chatIdentity, rawCommand, messageType)) {
      console.log("[autoreply] duplicate-command-skipped", {
        deviceBody,
        chatIdentity,
        rawCommand,
        messageType,
        messageId: incomingMessageId || null,
      });
      return;
    }
    trackIncomingCommand(chatIdentity, rawCommand, messageType);

    if (isPaymentProofMessage(messageType, command)) {
      await saveIncomingMessageLog(deviceBody, chatIdentity);
      console.log("[autoreply] payment-proof-silent", {
        deviceBody,
        chatIdentity,
        messageType,
      });
      return;
    }

    if (isMenuCommand(command)) {
      const menuPayload = buildPersistentMenuPayload(pushName);
      await sendAutoReply(sock, message, menuPayload, false);
      try {
        await saveAutoReplyHistory(
          deviceBody,
          normalizePhoneNumber(senderNumber) || chatIdentity,
          "button",
          extractHistoryMessage(menuPayload),
          JSON.stringify(menuPayload),
          "success",
          "Auto reply: direct menu shortcut"
        );
      } catch (historyError) {
        console.log("[autoreply] history-save-failed", historyError?.message || historyError);
      }

      await saveIncomingMessageLog(deviceBody, chatIdentity);
      console.log("[autoreply] direct-menu-sent", {
        deviceBody,
        chatIdentity,
        command: rawCommand,
      });
      return true;
    }

    if (isRegistrationSubmissionMessage(command)) {
      const registrationPayload = buildRegistrationThankYouPayload(pushName);
      await sendAutoReply(sock, message, registrationPayload, false);
      await sendAutoReply(sock, message, buildPersistentMenuPayload(pushName), false);
      await saveIncomingMessageLog(deviceBody, chatIdentity);
      console.log("[autoreply] registration-thankyou", {
        deviceBody,
        chatIdentity,
        command,
      });
      return;
    }

    if (isPausedForOperator) {
      if (command) {
        await resumeContactPause(deviceBody, chatIdentity);
        console.log("[autoreply] contact-resumed-by-customer-trigger", {
          deviceBody,
          chatIdentity,
          command,
        });
      } else {
        await saveIncomingMessageLog(deviceBody, chatIdentity);
        console.log("[autoreply] contact-paused", {
          deviceBody,
          chatIdentity,
          command,
        });
        return;
      }
    }

    let shouldQuote = false;
    let replyPayload;
    let replySource = "webhook";

    const firstChatReplies = isFirstChat ? await getFirstChatRules(deviceBody) : [];
    const matchedFirstChatReply = isFirstChat
      ? await pickEligibleReply(firstChatReplies, message.key.remoteJid, senderNumber)
      : null;
    const equalReply = await isExistsEqualCommand(command, deviceBody);
    const matchedEqualReply = await pickEligibleReply(equalReply, message.key.remoteJid, senderNumber);
    const containReply = matchedEqualReply ? null : await isExistsContainCommand(command, deviceBody);
    const matchedContainReply = containReply
      ? await pickEligibleReply(containReply, message.key.remoteJid, senderNumber)
      : null;
    const matchedReply = matchedFirstChatReply || matchedEqualReply || matchedContainReply;
    const defaultAiBot = !matchedReply && isTextLikeMessage(messageType, command)
      ? await getActiveAiBotForDevice(deviceBody)
      : null;

    console.log("[autoreply] incoming", {
      deviceBody,
      from,
      senderNumber,
      command,
      rawCommand,
      messageType,
      isFirstChat,
      matchedFirstChat: Boolean(matchedFirstChatReply),
      matchedEqual: Boolean(matchedEqualReply),
      matchedContain: Boolean(matchedContainReply),
      defaultAiBot: defaultAiBot ? { id: defaultAiBot.id, name: defaultAiBot.name } : null,
    });

    if (matchedReply) {
      shouldQuote = Boolean(matchedReply.is_quoted);
      if (matchedReply.type === "ai") {
        if (!isTextLikeMessage(messageType, command)) {
          console.log("[ai-bot] non-text input ignored", {
            deviceBody,
            messageType,
            ruleId: matchedReply.id,
          });
          await saveIncomingMessageLog(deviceBody, chatIdentity);
          return;
        }

        const aiResponse = await requestInternalAiReply({
          matchedReply,
          botId: matchedReply.ai_bot_id || matchedReply.reply?.ai_bot_id,
          aiRoute: "rule",
          message,
          command,
          participant,
          pushName,
          deviceBody,
        });

        replyPayload = aiResponse?.reply || null;
        if (!replyPayload) {
          await saveIncomingMessageLog(deviceBody, chatIdentity);
          console.log("[ai-bot] no reply payload returned", {
            ruleId: matchedReply.id,
            message: aiResponse?.message || "silent",
          });
          return;
        }
        replySource = "rule_ai";
      } else {
        replyPayload = normalizeWebhookResponse(matchedReply.reply);
        replySource = "rule";
      }

      console.log("[autoreply] matched-rule", {
        id: matchedReply.id,
        keyword: matchedReply.keyword,
        type: matchedReply.type,
        transportPolicy: matchedReply.transport_policy || "text_fallback",
        shouldQuote,
      });
    } else {
      if (defaultAiBot) {
        console.log("[ai-bot] default-bot-selected", {
          deviceBody,
          botId: defaultAiBot.id,
          botName: defaultAiBot.name,
        });

        const aiResponse = await requestInternalAiReply({
          matchedReply: null,
          botId: defaultAiBot.id,
          aiRoute: "default",
          message,
          command,
          participant,
          pushName,
          deviceBody,
        });

        replyPayload = aiResponse?.reply || null;
        if (replyPayload) {
          replySource = "default_ai";
          console.log("[ai-bot] default-reply", {
            deviceBody,
            botId: defaultAiBot.id,
            message: aiResponse?.message || "ok",
          });
        } else {
          console.log("[ai-bot] default-no-reply", {
            deviceBody,
            botId: defaultAiBot.id,
            message: aiResponse?.message || "silent",
          });
        }
      }

      if (!replyPayload) {
        const webhookUrl = await getUrlWebhook(deviceBody);
        if (!webhookUrl) {
          console.log("[autoreply] no-match-no-webhook", { deviceBody, command, defaultAiBot: Boolean(defaultAiBot) });
          await saveIncomingMessageLog(deviceBody, chatIdentity);
          return;
        }

        const webhookReply = await sendWebhook({
          command,
          bufferImage,
          from,
          url: webhookUrl,
          participant,
        });

        replyPayload = normalizeWebhookResponse(webhookReply);
        if (!replyPayload) {
          await saveIncomingMessageLog(deviceBody, chatIdentity);
          return;
        }

        replySource = "webhook";
      }

      shouldQuote = Boolean(replyPayload?.quoted);
    }

    const contextualPayload = normalizeReplyPayloadForContext(replyPayload, matchedReply, pushName);
    const finalPayload = replaceTemplateVariables(contextualPayload, customerDisplayName);
    const transportDecision = resolveTransportPayload(finalPayload, message.key.remoteJid, matchedReply);
    const transportPayload = attachMenuButton(transportDecision.payload);
    if (transportDecision.mode === "fallback_text") {
      console.log("[autoreply] interactive-fallback-text", {
        deviceBody,
        remoteJid: message.key.remoteJid,
        ruleId: matchedReply?.id || null,
        policy: transportDecision.policy,
        reason: transportDecision.reason,
      });
    }

    await sendAutoReply(sock, message, transportPayload, shouldQuote);
    if (shouldSendPersistentMenu(matchedReply, transportPayload, message.key.remoteJid)) {
      await sendAutoReply(sock, message, buildPersistentMenuPayload(pushName), false);
      console.log("[autoreply] persistent-menu-sent", {
        deviceBody,
        command,
        rawCommand,
        ruleId: matchedReply?.id || null,
      });
    }
    try {
      await saveAutoReplyHistory(
        deviceBody,
        normalizePhoneNumber(senderNumber) || chatIdentity,
        inferHistoryType(transportPayload, matchedReply),
        extractHistoryMessage(transportPayload),
        JSON.stringify(transportPayload || {}),
        "success",
        matchedReply
          ? `Auto reply: ${matchedReply.name || matchedReply.keyword || matchedReply.type} | transport=${transportDecision.policy}:${transportDecision.mode}`
          : replySource === "default_ai"
            ? `AI default auto reply | transport=${transportDecision.policy}:${transportDecision.mode}`
            : `Webhook auto reply | transport=${transportDecision.mode}`
      );
    } catch (historyError) {
      console.log("[autoreply] history-save-failed", historyError?.message || historyError);
    }

    if (shouldPauseForOperatorHandoff(matchedReply, command)) {
      await pauseContactForOperator(
        deviceBody,
        chatIdentity,
        senderNumber,
        pushName,
        message.key.remoteJid.includes("@g.us") ? "group" : "personal",
        "Paused automatically after customer selected hubungi admin"
      );
      console.log("[autoreply] contact-paused-after-admin-handoff", {
        deviceBody,
        chatIdentity,
      });
    }

    await saveIncomingMessageLog(deviceBody, chatIdentity);
    console.log("[autoreply] reply-sent", {
      deviceBody,
      command,
      rawCommand,
      source: replySource,
      transportPolicy: transportDecision.policy,
      transportMode: transportDecision.mode,
      type:
        typeof transportPayload === "string"
          ? "text"
          : transportPayload?.type || Object.keys(transportPayload || {})[0],
    });
    return true;
  } catch (error) {
    console.log(error);
    console.log("[autoreply] reply-failed", error?.message || error);
    return false;
  }
};

module.exports = { IncomingMessage };
