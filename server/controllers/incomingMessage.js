const axios = require("axios");
const { parseIncomingMessage, formatReceipt, prepareMediaMessage } = require("../lib/helper");
const {
  isExistsEqualCommand,
  isExistsContainCommand,
  getFirstChatRules,
  hasIncomingMessageLog,
  saveIncomingMessageLog,
  getUrlWebhook,
  getRegisteredPhonebookIdsForNumber,
  isContactPaused,
  pauseContactForOperator,
  resumeContactPause,
  saveAutoReplyHistory,
} = require("../database/model");

require("dotenv").config();

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

function isResumeBotCommand(command) {
  const normalizedCommand = String(command || "").trim().toLowerCase();
  return normalizedCommand === "menu";
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
  if (!matchedReply) {
    return false;
  }

  const normalizedKeyword = String(matchedReply.keyword || "").trim().toLowerCase();
  const normalizedName = String(matchedReply.name || "").trim().toLowerCase();
  const normalizedCommand = String(command || "").trim().toLowerCase();

  return (
    normalizedKeyword === "hubungi admin" ||
    normalizedName.includes("hubungi admin") ||
    normalizedCommand === "hubungi admin"
  );
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
  return jid.endsWith("@s.whatsapp.net") || jid.endsWith("@g.us");
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
        lines.push(`${index + 1}. ${rowTitle}${rowDescription ? ` - ${rowDescription}` : ""}`);
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

  if (payload.text) {
    return "text";
  }

  if (payload.image || payload.video || payload.document || payload.audio || payload.type) {
    return "media";
  }

  if (Array.isArray(payload.sections)) {
    return "list";
  }

  if (Array.isArray(payload.buttons)) {
    return "button";
  }

  if (Array.isArray(payload.templateButtons)) {
    return "template";
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
    "templateButtonReplyMessage",
    "imageMessage",
    "videoMessage",
    "documentMessage",
  ];

  return supportedTypes.includes(messageType) && String(command || "").trim() !== "";
}

async function requestInternalAiReply({ matchedReply, message, command, participant, pushName, deviceBody }) {
  const appUrl = process.env.APP_URL;
  const internalToken = process.env.AI_INTERNAL_TOKEN || process.env.APP_KEY;

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
        matched_rule_id: matchedReply.id,
        bot_id: matchedReply.ai_bot_id || matchedReply.reply?.ai_bot_id,
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
  if (typeof replyPayload === "string") {
    await sock.sendMessage(
      message.key.remoteJid,
      { text: replyPayload },
      { quoted: shouldQuote ? message : undefined }
    );
    return true;
  }

  if (replyPayload && typeof replyPayload === "object" && "type" in replyPayload) {
    if (replyPayload.type === "audio") {
      await sock.sendMessage(
        message.key.remoteJid,
        {
          audio: { url: replyPayload.url },
          ptt: true,
          mimetype: "audio/mpeg",
        },
        { quoted: shouldQuote ? message : undefined }
      );
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

    await sock.sendMessage(message.key.remoteJid, preparedPayload, {
      quoted: shouldQuote ? message : undefined,
    });
    return true;
  }

  if (replyPayload && typeof replyPayload === "object" && "quoted" in replyPayload) {
    delete replyPayload.quoted;
  }

  await sock.sendMessage(message.key.remoteJid, replyPayload, {
    quoted: shouldQuote ? message : undefined,
  });
  return true;
}

const IncomingMessage = async (upsert, sock) => {
  try {
    if (!upsert?.messages?.length) {
      return;
    }

    const message = upsert.messages[0];
    if (!message?.message || message?.key?.fromMe) {
      return;
    }

    if (message?.key?.remoteJid === "status@broadcast") {
      return;
    }

    const participant = message.key.participant ? formatReceipt(message.key.participant) : undefined;
    const { command, bufferImage, from, messageType } = await parseIncomingMessage(message);
    const pushName = message.pushName || "";
    const deviceBody = String(sock.user.id).split(":")[0];
    const chatIdentity = participant || message.key.remoteJid;
    const senderNumber = participant ? participant.replace("@s.whatsapp.net", "") : from;
    const isFirstChat = !(await hasIncomingMessageLog(deviceBody, chatIdentity));
    const isPausedForOperator = await isContactPaused(deviceBody, chatIdentity);

    if (isPausedForOperator) {
      if (isResumeBotCommand(command)) {
        await resumeContactPause(deviceBody, chatIdentity);
        console.log("[autoreply] contact-resumed-by-menu", {
          deviceBody,
          chatIdentity,
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

    console.log("[autoreply] incoming", {
      deviceBody,
      from,
      senderNumber,
      command,
      messageType,
      isFirstChat,
      matchedFirstChat: Boolean(matchedFirstChatReply),
      matchedEqual: Boolean(matchedEqualReply),
      matchedContain: Boolean(matchedContainReply),
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
      } else {
        replyPayload = normalizeWebhookResponse(matchedReply.reply);
      }

      console.log("[autoreply] matched-rule", {
        id: matchedReply.id,
        keyword: matchedReply.keyword,
        type: matchedReply.type,
        transportPolicy: matchedReply.transport_policy || "text_fallback",
        shouldQuote,
      });
    } else {
      const webhookUrl = await getUrlWebhook(deviceBody);
      if (!webhookUrl) {
        console.log("[autoreply] no-match-no-webhook", { deviceBody, command });
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

      shouldQuote = Boolean(replyPayload?.quoted);
    }

    const finalPayload = replaceTemplateVariables(replyPayload, pushName);
    const transportDecision = resolveTransportPayload(finalPayload, message.key.remoteJid, matchedReply);
    const transportPayload = transportDecision.payload;
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
    await saveAutoReplyHistory(
      deviceBody,
      normalizePhoneNumber(senderNumber) || chatIdentity,
      inferHistoryType(transportPayload, matchedReply),
      extractHistoryMessage(transportPayload),
      JSON.stringify(transportPayload || {}),
      "success",
      matchedReply
        ? `Auto reply: ${matchedReply.name || matchedReply.keyword || matchedReply.type} | transport=${transportDecision.policy}:${transportDecision.mode}`
        : `Webhook auto reply | transport=${transportDecision.mode}`
    );

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
