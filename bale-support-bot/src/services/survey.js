'use strict';
const db = require('../db');
const api = require('../bale/api');
const rt = require('../realtime');

const DEFAULT_QUESTION =
  'از روند پیشرفت و کیفیت پشتیبانی ما چه امتیازی می‌دهید؟\nلطفاً از ۱ (ضعیف) تا ۵ (عالی) انتخاب کنید 👇';

// Tenure buckets (days) for each period option
function tenureRange(weeks) {
  const day = 86400000;
  if (weeks >= 5) return { min: 28 * day, max: Infinity }; // 4+ weeks
  return { min: (weeks - 1) * 7 * day, max: weeks * 7 * day };
}

function eligibleUsers(agentId, weeks) {
  const { min, max } = tenureRange(weeks);
  const now = Date.now();
  const rows = db
    .prepare(`SELECT id, created_at FROM users WHERE agent_id=? AND accepted_rules=1`)
    .all(agentId);
  return rows.filter((u) => {
    const tenure = now - u.created_at;
    return tenure >= min && tenure < max;
  });
}

function ratingKeyboard(surveyId) {
  const row = [];
  for (let i = 1; i <= 5; i++) row.push({ text: `${i} ⭐`, callback_data: `sv:${surveyId}:${i}` });
  return { inline_keyboard: [row] };
}

// Create a survey campaign and start sending in the background (rate-limit safe)
function createAndSend(agentId, periodWeeks, question) {
  const users = eligibleUsers(agentId, periodWeeks);
  const q = (question && question.trim()) || DEFAULT_QUESTION;
  const info = db
    .prepare(
      `INSERT INTO surveys (agent_id, period_weeks, question, sent_count, target_count, status, created_at)
       VALUES (?,?,?,0,?, 'sending', ?)`
    )
    .run(agentId, periodWeeks, q, users.length, Date.now());
  const surveyId = info.lastInsertRowid;

  // fire and forget
  sendToUsers(surveyId, agentId, q, users).catch((e) => console.error('survey send error', e.message));

  return db.prepare('SELECT * FROM surveys WHERE id=?').get(surveyId);
}

async function sendToUsers(surveyId, agentId, question, users) {
  let sent = 0;
  for (const u of users) {
    let attempts = 0;
    while (attempts < 4) {
      try {
        await api.call('sendMessage', {
          chat_id: u.id,
          text: question,
          reply_markup: ratingKeyboard(surveyId),
        });
        db.prepare('UPDATE users SET pending_survey_id=? WHERE id=?').run(surveyId, u.id);
        sent++;
        break;
      } catch (e) {
        attempts++;
        if (e.retryAfter) {
          await sleep((e.retryAfter + 1) * 1000); // respect Bale limit
        } else if (attempts < 4) {
          await sleep(1000 * attempts);
        } else {
          console.error(`survey: giving up on user ${u.id}: ${e.message}`);
        }
      }
    }
    db.prepare('UPDATE surveys SET sent_count=? WHERE id=?').run(sent, surveyId);
    rt.toAdmin('survey:progress', { survey_id: surveyId, sent, target: users.length });
  }
  db.prepare("UPDATE surveys SET status='done', sent_count=? WHERE id=?").run(sent, surveyId);
  rt.toAdmin('survey:done', { survey_id: surveyId, sent, target: users.length });
}

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

module.exports = { createAndSend, eligibleUsers, DEFAULT_QUESTION, tenureRange };
