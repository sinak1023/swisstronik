'use strict';
const db = require('../db');
const api = require('./api');
const rt = require('../realtime');

const insertMsg = db.prepare(`
  INSERT INTO messages (user_id, agent_id, direction, type, text, file_id, file_name, mime,
                        local_path, reply_to_message_id, bale_message_id, read_by_agent, status, created_at)
  VALUES (@user_id,@agent_id,@direction,@type,@text,@file_id,@file_name,@mime,
          @local_path,@reply_to_message_id,@bale_message_id,@read_by_agent,@status,@created_at)
`);

function emitNew(full) {
  // Inbound goes live to the assigned agent + admins. Outgoing/system messages
  // are only pushed to admins — the sending agent already shows them optimistically,
  // which avoids the message appearing twice in the agent's own UI.
  if (full.direction === 'in') rt.toAgentAndAdmin(full.agent_id, 'message:new', full);
  else rt.toAdmin('message:new', full);
}

function recordMessage(fields) {
  const row = {
    user_id: fields.user_id,
    agent_id: fields.agent_id ?? null,
    direction: fields.direction,
    type: fields.type || 'text',
    text: fields.text || '',
    file_id: fields.file_id || '',
    file_name: fields.file_name || '',
    mime: fields.mime || '',
    local_path: fields.local_path || '',
    reply_to_message_id: fields.reply_to_message_id ?? null,
    bale_message_id: fields.bale_message_id ?? null,
    read_by_agent: fields.read_by_agent ? 1 : 0,
    status: fields.status || 'sent',
    created_at: fields.created_at || Date.now(),
  };
  const info = insertMsg.run(row);
  const full = db.prepare('SELECT * FROM messages WHERE id=?').get(info.lastInsertRowid);
  emitNew(full);
  return full;
}

function baleReplyMarkup(replyToBaleId, extraMarkup) {
  const m = {};
  if (replyToBaleId) m.reply_to_message_id = replyToBaleId;
  if (extraMarkup) m.reply_markup = extraMarkup;
  return m;
}

// Update delivery status of an outgoing message and notify the agent (clock -> check).
function updateStatus(id, agentId, status, extra = {}) {
  const sets = ['status=@status'];
  const params = { id, status };
  if (extra.bale_message_id !== undefined) { sets.push('bale_message_id=@bale_message_id'); params.bale_message_id = extra.bale_message_id || null; }
  if (extra.file_id !== undefined && extra.file_id) { sets.push('file_id=@file_id'); params.file_id = extra.file_id; }
  db.prepare(`UPDATE messages SET ${sets.join(', ')} WHERE id=@id`).run(params);
  rt.toAgentAndAdmin(agentId, 'message:status', { id, status, bale_message_id: extra.bale_message_id, file_id: extra.file_id, retry_after: extra.retry_after, description: extra.description });
}

const MEDIA_METHODS = {
  voice: { method: 'sendVoice', field: 'voice' },
  photo: { method: 'sendPhoto', field: 'photo' },
  video: { method: 'sendVideo', field: 'video' },
  audio: { method: 'sendAudio', field: 'audio' },
  document: { method: 'sendDocument', field: 'document' },
};

function extractFileId(result, kind) {
  if (!result) return '';
  const obj = result[kind] || (Array.isArray(result.photo) ? result.photo[result.photo.length - 1] : null);
  return obj && obj.file_id ? obj.file_id : '';
}

// ---- Synchronous senders (used by the bot onboarding/survey flow) ----
async function sendText(userId, text, opts = {}) {
  const result = await api.call('sendMessage', {
    chat_id: userId, text, ...baleReplyMarkup(opts.replyToBaleId, opts.reply_markup),
  });
  return recordMessage({
    user_id: userId, agent_id: opts.agentId ?? null, direction: opts.direction || 'out',
    type: 'text', text, bale_message_id: result && result.message_id,
    reply_to_message_id: opts.replyDbId ?? null, read_by_agent: 1, status: 'sent',
  });
}

async function sendMedia(userId, kind, file, opts = {}) {
  const conf = MEDIA_METHODS[kind] || MEDIA_METHODS.document;
  const fields = { chat_id: userId };
  if (opts.caption) fields.caption = opts.caption;
  Object.assign(fields, baleReplyMarkup(opts.replyToBaleId, opts.reply_markup));
  let result;
  if (file.file_id) { fields[conf.field] = file.file_id; result = await api.call(conf.method, fields); }
  else result = await api.callForm(conf.method, fields, conf.field, file);
  return recordMessage({
    user_id: userId, agent_id: opts.agentId ?? null, direction: opts.direction || 'out',
    type: kind, text: opts.caption || '', file_id: file.file_id || extractFileId(result, kind),
    file_name: file.name || '', mime: file.mime || '', local_path: opts.local_path || '',
    bale_message_id: result && result.message_id, reply_to_message_id: opts.replyDbId ?? null,
    read_by_agent: 1, status: 'sent',
  });
}

// ---- Async agent flow: record instantly as 'pending', deliver in background ----
function recordPendingText(userId, agentId, text, replyDbId) {
  return recordMessage({
    user_id: userId, agent_id: agentId, direction: 'out', type: 'text', text,
    reply_to_message_id: replyDbId ?? null, read_by_agent: 1, status: 'pending',
  });
}
function recordPendingMedia(userId, agentId, kind, file, caption, replyDbId) {
  return recordMessage({
    user_id: userId, agent_id: agentId, direction: 'out', type: kind, text: caption || '',
    file_name: file.name || '', mime: file.mime || '', reply_to_message_id: replyDbId ?? null,
    read_by_agent: 1, status: 'pending',
  });
}

async function deliverText(dbMsg, userId, text, opts = {}) {
  try {
    const result = await api.call('sendMessage', { chat_id: userId, text, ...baleReplyMarkup(opts.replyToBaleId) });
    updateStatus(dbMsg.id, dbMsg.agent_id, 'sent', { bale_message_id: result && result.message_id });
  } catch (e) {
    updateStatus(dbMsg.id, dbMsg.agent_id, 'failed', { retry_after: e.retryAfter || 0, description: e.message });
  }
}

async function deliverMedia(dbMsg, userId, kind, file, opts = {}) {
  const conf = MEDIA_METHODS[kind] || MEDIA_METHODS.document;
  const fields = { chat_id: userId };
  if (opts.caption) fields.caption = opts.caption;
  Object.assign(fields, baleReplyMarkup(opts.replyToBaleId));
  try {
    let result;
    if (file.file_id) { fields[conf.field] = file.file_id; result = await api.call(conf.method, fields); }
    else result = await api.callForm(conf.method, fields, conf.field, file);
    updateStatus(dbMsg.id, dbMsg.agent_id, 'sent', {
      bale_message_id: result && result.message_id, file_id: file.file_id || extractFileId(result, kind),
    });
  } catch (e) {
    updateStatus(dbMsg.id, dbMsg.agent_id, 'failed', { retry_after: e.retryAfter || 0, description: e.message });
  }
}

module.exports = {
  recordMessage, sendText, sendMedia, MEDIA_METHODS,
  recordPendingText, recordPendingMedia, deliverText, deliverMedia,
};
