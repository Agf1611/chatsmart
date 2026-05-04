const { dbQuery } = require("./index");
const cache = require("./../lib/cache");

const { myCache } = cache;

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

async function isExistsEqualCommand(command, deviceBody) {
  const cacheKey = `equal:${deviceBody}:${command}`;
  if (myCache.has(cacheKey)) {
    return myCache.get(cacheKey);
  }

  const rows = await dbQuery(
    `SELECT autoreplies.* 
     FROM autoreplies
     INNER JOIN devices ON devices.id = autoreplies.device_id
     WHERE autoreplies.keyword = ?
       AND autoreplies.type_keyword = 'Equal'
       AND devices.body = ?
       AND autoreplies.status = 'active'
     ORDER BY autoreplies.updated_at DESC`,
    [command, deviceBody]
  );

  myCache.set(cacheKey, rows);
  return rows;
}

async function isExistsContainCommand(command, deviceBody) {
  const cacheKey = `contain:${deviceBody}:${command}`;
  if (myCache.has(cacheKey)) {
    return myCache.get(cacheKey);
  }

  const rows = await dbQuery(
    `SELECT autoreplies.* 
     FROM autoreplies
     INNER JOIN devices ON devices.id = autoreplies.device_id
     WHERE ? LIKE CONCAT('%', autoreplies.keyword, '%')
       AND autoreplies.type_keyword = 'Contain'
       AND devices.body = ?
       AND autoreplies.status = 'active'
     ORDER BY autoreplies.priority ASC, autoreplies.updated_at DESC`,
    [command, deviceBody]
  );

  myCache.set(cacheKey, rows);
  return rows;
}

async function getFirstChatRules(deviceBody) {
  const cacheKey = `first-chat:${deviceBody}`;
  if (myCache.has(cacheKey)) {
    return myCache.get(cacheKey);
  }

  const rows = await dbQuery(
    `SELECT autoreplies.*
     FROM autoreplies
     INNER JOIN devices ON devices.id = autoreplies.device_id
     WHERE autoreplies.trigger_event = 'first_chat'
       AND devices.body = ?
       AND autoreplies.status = 'active'
     ORDER BY autoreplies.priority ASC, autoreplies.updated_at DESC`,
    [deviceBody]
  );

  myCache.set(cacheKey, rows);
  return rows;
}

async function hasIncomingMessageLog(deviceBody, chatJid) {
  const rows = await dbQuery(
    `SELECT incoming_message_logs.id
     FROM incoming_message_logs
     INNER JOIN devices ON devices.id = incoming_message_logs.device_id
     WHERE devices.body = ?
       AND incoming_message_logs.chat_jid = ?
     LIMIT 1`,
    [deviceBody, chatJid]
  );

  return rows.length > 0;
}

async function saveIncomingMessageLog(deviceBody, chatJid) {
  await dbQuery(
    `INSERT INTO incoming_message_logs (device_id, chat_jid, message_count, first_received_at, last_received_at, created_at, updated_at)
     SELECT devices.id, ?, 1, NOW(), NOW(), NOW(), NOW()
     FROM devices
     WHERE devices.body = ?
     ON DUPLICATE KEY UPDATE
       message_count = message_count + 1,
       last_received_at = NOW(),
       updated_at = NOW()`,
    [chatJid, deviceBody]
  );
}

async function getUrlWebhook(deviceBody) {
  const cacheKey = `webhook:${deviceBody}`;
  if (myCache.has(cacheKey)) {
    return myCache.get(cacheKey);
  }

  const rows = await dbQuery("SELECT webhook FROM devices WHERE body = ? LIMIT 1", [deviceBody]);
  const webhook = rows.length > 0 ? rows[0].webhook : null;
  myCache.set(cacheKey, webhook);
  return webhook;
}

async function getActiveAiBotForDevice(deviceBody) {
  const cacheKey = `active-ai-bot:${deviceBody}`;
  if (myCache.has(cacheKey)) {
    return myCache.get(cacheKey);
  }

  const rows = await dbQuery(
    `SELECT ai_bots.*
     FROM ai_bots
     INNER JOIN devices ON devices.id = ai_bots.device_id
     WHERE devices.body = ?
       AND ai_bots.status = 'active'
     ORDER BY ai_bots.updated_at DESC
     LIMIT 1`,
    [deviceBody]
  );

  const bot = rows.length > 0 ? rows[0] : null;
  myCache.set(cacheKey, bot);
  return bot;
}

async function getRegisteredPhonebookIdsForNumber(userId, senderNumber) {
  const normalizedNumber = normalizePhoneNumber(senderNumber);
  const cacheKey = `registered-phonebooks:${userId}:${normalizedNumber}`;
  if (myCache.has(cacheKey)) {
    return myCache.get(cacheKey);
  }

  if (!normalizedNumber) {
    return [];
  }

  const rows = await dbQuery(
    `SELECT DISTINCT tag_id
     FROM contacts
     WHERE user_id = ?
       AND number = ?`,
    [userId, normalizedNumber]
  );

  const tagIds = rows.map((row) => Number(row.tag_id)).filter(Boolean);
  myCache.set(cacheKey, tagIds);
  return tagIds;
}

async function isContactPaused(deviceBody, chatJid) {
  const rows = await dbQuery(
    `SELECT ai_conversations.id
     FROM ai_conversations
     INNER JOIN devices ON devices.id = ai_conversations.device_id
     WHERE devices.body = ?
       AND ai_conversations.chat_jid = ?
       AND ai_conversations.status = 'paused'
       AND (ai_conversations.paused_until IS NULL OR ai_conversations.paused_until > NOW())
     ORDER BY ai_conversations.updated_at DESC
     LIMIT 1`,
    [deviceBody, chatJid]
  );

  return rows.length > 0;
}

async function pauseContactForOperator(deviceBody, chatJid, senderNumber, contactName, contextType, reason) {
  const bots = await dbQuery(
    `SELECT ai_bots.id, ai_bots.user_id, ai_bots.device_id
     FROM ai_bots
     INNER JOIN devices ON devices.id = ai_bots.device_id
     WHERE devices.body = ?
       AND ai_bots.status = 'active'
     ORDER BY ai_bots.updated_at DESC
     LIMIT 1`,
    [deviceBody]
  );

  if (!bots.length) {
    return false;
  }

  const bot = bots[0];
  const existing = await dbQuery(
    `SELECT id
     FROM ai_conversations
     WHERE ai_bot_id = ?
       AND device_id = ?
       AND chat_jid = ?
     LIMIT 1`,
    [bot.id, bot.device_id, chatJid]
  );

  if (existing.length) {
    await dbQuery(
      `UPDATE ai_conversations
       SET contact_name = COALESCE(NULLIF(?, ''), contact_name),
           context_type = ?,
           status = 'paused',
           paused_until = NULL,
           paused_reason = ?,
           last_message_at = NOW(),
           updated_at = NOW()
       WHERE id = ?`,
      [contactName || "", contextType || "personal", reason, existing[0].id]
    );
  } else {
    await dbQuery(
      `INSERT INTO ai_conversations (
          user_id,
          ai_bot_id,
          device_id,
          chat_jid,
          contact_name,
          context_type,
          status,
          paused_until,
          paused_reason,
          short_summary,
          last_user_message,
          last_bot_message,
          last_message_at,
          last_bot_reply_at,
          created_at,
          updated_at
       ) VALUES (?, ?, ?, ?, ?, ?, 'paused', NULL, ?, NULL, NULL, NULL, NOW(), NULL, NOW(), NOW())`,
      [
        bot.user_id,
        bot.id,
        bot.device_id,
        chatJid,
        contactName || null,
        contextType || "personal",
        reason,
      ]
    );
  }

  return true;
}

async function resumeContactPause(deviceBody, chatJid) {
  await dbQuery(
    `UPDATE ai_conversations
     INNER JOIN devices ON devices.id = ai_conversations.device_id
     SET ai_conversations.status = 'active',
         ai_conversations.paused_until = NULL,
         ai_conversations.paused_reason = NULL,
         ai_conversations.updated_at = NOW()
     WHERE devices.body = ?
       AND ai_conversations.chat_jid = ?`,
    [deviceBody, chatJid]
  );

  return true;
}

async function saveAutoReplyHistory(deviceBody, number, type, message, payload, status = "success", note = null) {
  await dbQuery(
    `INSERT INTO message_histories (
        user_id,
        device_id,
        number,
        type,
        message,
        payload,
        status,
        send_by,
        note,
        created_at,
        updated_at
     )
     SELECT
        devices.user_id,
        devices.id,
        ?,
        ?,
        ?,
        ?,
        ?,
        'api',
        ?,
        NOW(),
        NOW()
     FROM devices
     WHERE devices.body = ?
     LIMIT 1`,
    [number, type, message, payload, status, note, deviceBody]
  );
}

module.exports = {
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
};
