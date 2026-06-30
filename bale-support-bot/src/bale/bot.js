'use strict';
const path = require('path');
const db = require('../db');
const config = require('../config');
const api = require('./api');
const { sendText, sendMedia, recordMessage } = require('./messaging');
const settings = require('../services/settings');
const { pickAgentForNewUser } = require('../services/assignment');
const rt = require('../realtime');

const ACCEPT_CB = 'accept_rules';

// ---------- user helpers ----------
function ensureUser(from, chat) {
  const id = chat.id;
  let u = db.prepare('SELECT * FROM users WHERE id=?').get(id);
  if (!u) {
    db.prepare(
      `INSERT INTO users (id, first_name, last_name, username, state, accepted_rules, created_at)
       VALUES (?,?,?,?, 'new', 0, ?)`
    ).run(id, from.first_name || '', from.last_name || '', from.username || '', Date.now());
    u = db.prepare('SELECT * FROM users WHERE id=?').get(id);
    rt.toAdmin('user:new', u);
  }
  return u;
}

function rulesKeyboard() {
  return { inline_keyboard: [[{ text: '✅ قوانین را می‌پذیرم', callback_data: ACCEPT_CB }]] };
}

async function sendRules(userId) {
  await sendText(userId, settings.getRulesText(), { direction: 'system', reply_markup: rulesKeyboard() });
}

// Assign agent + send welcome voice; mark user active
async function acceptAndAssign(user) {
  let agentId = user.agent_id;
  if (!agentId) {
    agentId = pickAgentForNewUser();
  }
  db.prepare(`UPDATE users SET accepted_rules=1, state='active', agent_id=? WHERE id=?`).run(agentId, user.id);
  const updated = db.prepare('SELECT * FROM users WHERE id=?').get(user.id);
  rt.toAdmin('user:update', updated);
  if (agentId) rt.toAgent(agentId, 'user:assigned', updated);

  // (the original rules message is edited in-place to append the confirmation
  //  line in handleCallback; we don't resend the rules text here.)

  // welcome voice: prefer assigned agent's voice, else global
  let voicePath = '';
  if (agentId) {
    const ag = db.prepare('SELECT welcome_voice_path FROM agents WHERE id=?').get(agentId);
    if (ag && ag.welcome_voice_path) voicePath = ag.welcome_voice_path;
  }
  if (!voicePath) voicePath = settings.getWelcomeVoicePath();

  if (voicePath) {
    const abs = path.isAbsolute(voicePath) ? voicePath : path.join(config.uploadsDir, voicePath);
    try {
      await sendMedia(user.id, 'voice', { path: abs, name: path.basename(abs), mime: 'audio/ogg' }, {
        agentId,
        direction: 'system',
        local_path: path.relative(config.uploadsDir, abs),
      });
    } catch (e) {
      console.error('welcome voice send failed:', e.message);
    }
  }
  await sendText(user.id, 'از این پس می‌توانید پیام خود را ارسال کنید. کارشناس ما در اسرع وقت پاسخگوست. 🌹', {
    agentId,
    direction: 'system',
  });
}

// ---------- incoming message classification ----------
function classify(msg) {
  if (msg.voice) return { type: 'voice', file_id: msg.voice.file_id, mime: msg.voice.mime_type || 'audio/ogg' };
  if (msg.photo) {
    const ph = msg.photo[msg.photo.length - 1];
    return { type: 'photo', file_id: ph.file_id, mime: 'image/jpeg' };
  }
  if (msg.video) return { type: 'video', file_id: msg.video.file_id, mime: msg.video.mime_type || 'video/mp4' };
  if (msg.audio) return { type: 'audio', file_id: msg.audio.file_id, mime: msg.audio.mime_type || 'audio/mpeg' };
  if (msg.document)
    return {
      type: 'document',
      file_id: msg.document.file_id,
      mime: msg.document.mime_type || '',
      file_name: msg.document.file_name || '',
    };
  if (msg.sticker) return { type: 'sticker', file_id: msg.sticker.file_id, mime: '' };
  return { type: 'text', file_id: '', mime: '' };
}

// ---------- update handlers ----------
async function handleMessage(msg) {
  const from = msg.from || {};
  const chat = msg.chat || {};
  if (chat.type && chat.type !== 'private') return; // only private chats
  const user = ensureUser(from, chat);
  const text = msg.text || '';

  if (text.startsWith('/start')) {
    if (user.accepted_rules) {
      await sendText(user.id, 'شما قبلاً قوانین را تایید کرده‌اید. پیام خود را ارسال کنید. ✅', {
        agentId: user.agent_id,
        direction: 'system',
      });
    } else {
      await sendRules(user.id);
    }
    return;
  }

  if (!user.accepted_rules) {
    await sendText(user.id, 'برای استفاده از پشتیبانی ابتدا باید قوانین را تایید کنید 👇', { direction: 'system' });
    await sendRules(user.id);
    return;
  }

  // active user → store inbound message + notify agent
  const c = classify(msg);
  let replyDbId = null;
  if (msg.reply_to_message && msg.reply_to_message.message_id) {
    const orig = db
      .prepare('SELECT id FROM messages WHERE user_id=? AND bale_message_id=?')
      .get(user.id, msg.reply_to_message.message_id);
    if (orig) replyDbId = orig.id;
  }
  recordMessage({
    user_id: user.id,
    agent_id: user.agent_id,
    direction: 'in',
    type: c.type,
    text: c.type === 'text' ? text : msg.caption || '',
    file_id: c.file_id,
    file_name: c.file_name || '',
    mime: c.mime,
    reply_to_message_id: replyDbId,
    bale_message_id: msg.message_id,
    read_by_agent: 0,
  });
  db.prepare('UPDATE users SET last_message_at=? WHERE id=?').run(Date.now(), user.id);
}

async function handleCallback(cb) {
  const data = cb.data || '';
  const from = cb.from || {};
  const msg = cb.message || {};
  const chatId = msg.chat ? msg.chat.id : from.id;

  if (data === ACCEPT_CB) {
    const user = ensureUser(from, { id: chatId, type: 'private' });
    if (!user.accepted_rules) {
      // edit the rules message to show confirmation appended
      try {
        await api.call('editMessageText', {
          chat_id: chatId,
          message_id: msg.message_id,
          text: settings.getRulesText() + '\n\n✅ قوانین را تایید کردم.',
        });
      } catch (e) {
        /* ignore edit failures */
      }
      await acceptAndAssign(user);
    }
    await answerCb(cb.id, 'قوانین تایید شد ✅');
    return;
  }

  // survey rating: sv:<surveyId>:<rating>
  if (data.startsWith('sv:')) {
    const [, sid, rating] = data.split(':');
    await handleSurveyAnswer(chatId, parseInt(sid, 10), parseInt(rating, 10), msg.message_id, cb.id);
    return;
  }

  await answerCb(cb.id, '');
}

async function handleSurveyAnswer(userId, surveyId, rating, messageId, cbId) {
  const survey = db.prepare('SELECT * FROM surveys WHERE id=?').get(surveyId);
  if (!survey || !(rating >= 1 && rating <= 5)) {
    await answerCb(cbId, 'نظرسنجی نامعتبر است.');
    return;
  }
  try {
    db.prepare(
      `INSERT INTO survey_responses (survey_id, user_id, rating, created_at) VALUES (?,?,?,?)
       ON CONFLICT(survey_id,user_id) DO UPDATE SET rating=excluded.rating, created_at=excluded.created_at`
    ).run(surveyId, userId, rating, Date.now());
    db.prepare('UPDATE users SET pending_survey_id=NULL WHERE id=? AND pending_survey_id=?').run(userId, surveyId);
    try {
      await api.call('editMessageText', {
        chat_id: userId,
        message_id: messageId,
        text: `${survey.question}\n\n✅ امتیاز شما ثبت شد: ${'⭐'.repeat(rating)} (${rating}/5)\nاز همراهی شما سپاسگزاریم.`,
      });
    } catch {
      /* ignore */
    }
    rt.toAdmin('survey:response', { survey_id: surveyId, user_id: userId, rating });
    await answerCb(cbId, 'امتیاز شما ثبت شد ✅');
  } catch (e) {
    await answerCb(cbId, 'ثبت امتیاز ناموفق بود.');
  }
}

async function answerCb(id, text) {
  try {
    await api.call('answerCallbackQuery', { callback_query_id: id, text: text || '' });
  } catch {
    /* ignore */
  }
}

// ---------- polling loop ----------
let offset = 0;
let running = false;

async function pollOnce() {
  const updates = await api.call('getUpdates', { offset, limit: 50, timeout: 25 });
  if (!Array.isArray(updates)) return;
  for (const up of updates) {
    offset = up.update_id + 1;
    try {
      if (up.message) await handleMessage(up.message);
      else if (up.callback_query) await handleCallback(up.callback_query);
      else if (up.edited_message) {
        /* ignore edits for now */
      }
    } catch (e) {
      console.error('update handling error:', e.message);
    }
  }
}

async function startPolling() {
  if (!config.baleToken) {
    console.warn('⚠️  BALE_BOT_TOKEN is not set — bot polling disabled. Panel still runs.');
    return;
  }
  running = true;
  // drop pending webhook so getUpdates works
  try {
    await api.call('deleteWebhook', {});
  } catch {
    /* ignore */
  }
  console.log('🤖 Bale bot polling started.');
  while (running) {
    try {
      await pollOnce();
    } catch (e) {
      const wait = e.retryAfter ? e.retryAfter * 1000 : 3000;
      console.error('poll error:', e.message, '→ retry in', wait, 'ms');
      await new Promise((r) => setTimeout(r, wait));
    }
  }
}

function stopPolling() {
  running = false;
}

module.exports = { startPolling, stopPolling, sendRules };
