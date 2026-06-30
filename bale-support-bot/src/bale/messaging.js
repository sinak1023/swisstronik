'use strict';
const db = require('../db');
const api = require('./api');
const rt = require('../realtime');

const insertMsg = db.prepare(`
  INSERT INTO messages (user_id, agent_id, direction, type, text, file_id, file_name, mime,
                        local_path, reply_to_message_id, bale_message_id, read_by_agent, created_at)
  VALUES (@user_id,@agent_id,@direction,@type,@text,@file_id,@file_name,@mime,
          @local_path,@reply_to_message_id,@bale_message_id,@read_by_agent,@created_at)
`);

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
    created_at: fields.created_at || Date.now(),
  };
  const info = insertMsg.run(row);
  const full = db.prepare('SELECT * FROM messages WHERE id=?').get(info.lastInsertRowid);
  // realtime: notify the assigned agent + admins
  rt.toAgentAndAdmin(full.agent_id, 'message:new', full);
  return full;
}

function baleReplyMarkup(replyToBaleId, extraMarkup) {
  const m = {};
  if (replyToBaleId) m.reply_to_message_id = replyToBaleId;
  if (extraMarkup) m.reply_markup = extraMarkup;
  return m;
}

// --- High level senders (used by bot system flow and agent replies) ---

async function sendText(userId, text, opts = {}) {
  const result = await api.call('sendMessage', {
    chat_id: userId,
    text,
    ...baleReplyMarkup(opts.replyToBaleId, opts.reply_markup),
  });
  return recordMessage({
    user_id: userId,
    agent_id: opts.agentId ?? null,
    direction: opts.direction || 'out',
    type: 'text',
    text,
    bale_message_id: result && result.message_id,
    reply_to_message_id: opts.replyDbId ?? null,
    read_by_agent: 1,
  });
}

const MEDIA_METHODS = {
  voice: { method: 'sendVoice', field: 'voice' },
  photo: { method: 'sendPhoto', field: 'photo' },
  video: { method: 'sendVideo', field: 'video' },
  audio: { method: 'sendAudio', field: 'audio' },
  document: { method: 'sendDocument', field: 'document' },
};

// file: { path?|buffer?, name, mime } OR { file_id } to resend by id
async function sendMedia(userId, kind, file, opts = {}) {
  const conf = MEDIA_METHODS[kind] || MEDIA_METHODS.document;
  const fields = { chat_id: userId };
  if (opts.caption) fields.caption = opts.caption;
  Object.assign(fields, baleReplyMarkup(opts.replyToBaleId, opts.reply_markup));

  let result;
  if (file.file_id) {
    fields[conf.field] = file.file_id;
    result = await api.call(conf.method, fields);
  } else {
    result = await api.callForm(conf.method, fields, conf.field, file);
  }
  // try to capture returned file_id for caching
  let fileId = file.file_id || '';
  if (result) {
    const obj = result[kind] || (Array.isArray(result.photo) ? result.photo[result.photo.length - 1] : null);
    if (obj && obj.file_id) fileId = obj.file_id;
  }
  return recordMessage({
    user_id: userId,
    agent_id: opts.agentId ?? null,
    direction: opts.direction || 'out',
    type: kind,
    text: opts.caption || '',
    file_id: fileId,
    file_name: file.name || '',
    mime: file.mime || '',
    local_path: opts.local_path || '',
    bale_message_id: result && result.message_id,
    reply_to_message_id: opts.replyDbId ?? null,
    read_by_agent: 1,
  });
}

module.exports = { recordMessage, sendText, sendMedia, MEDIA_METHODS };
