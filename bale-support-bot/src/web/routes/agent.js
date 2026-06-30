'use strict';
const express = require('express');
const multer = require('multer');
const db = require('../../db');
const { requireAuth } = require('../auth');
const messaging = require('../../bale/messaging');
const { BaleError } = require('../../bale/api');
const stats = require('../../services/stats');
const rt = require('../../realtime');
const { todayStr } = require('../../util');

const router = express.Router();
const upload = multer({ storage: multer.memoryStorage(), limits: { fileSize: 50 * 1024 * 1024 } });

router.use(requireAuth('agent'));

function agentId(req) {
  return req.user.id;
}
function ownsUser(aid, userId) {
  const u = db.prepare('SELECT * FROM users WHERE id=? AND agent_id=?').get(userId, aid);
  return u;
}

// profile + agent card
router.get('/me', (req, res) => {
  const a = db
    .prepare('SELECT id,name,description,photo_path,username,share FROM agents WHERE id=?')
    .get(agentId(req));
  res.json({ agent: a });
});

// stats summary for a preset
router.get('/stats', (req, res) => {
  const preset = req.query.preset || 'day';
  const day = req.query.day || todayStr();
  const { fromTs, toTs } = stats.rangeForPreset(preset, day);
  res.json(stats.agentSummary(agentId(req), fromTs, toTs));
});

// daily time series between two days
router.get('/stats/series', (req, res) => {
  const to = req.query.to || todayStr();
  const from = req.query.from || to;
  res.json({ series: stats.agentDailySeries(agentId(req), from, to) });
});

// chat list (telegram-like)
router.get('/chats', (req, res) => {
  const aid = agentId(req);
  const day = todayStr();
  // previews ignore bot/system onboarding messages — agent only cares about the real dialogue
  const rows = db
    .prepare(
      `SELECT u.id, u.first_name, u.last_name, u.username, u.last_message_at,
              (SELECT text FROM messages m WHERE m.user_id=u.id AND m.direction IN ('in','out') ORDER BY m.created_at DESC LIMIT 1) last_text,
              (SELECT type FROM messages m WHERE m.user_id=u.id AND m.direction IN ('in','out') ORDER BY m.created_at DESC LIMIT 1) last_type,
              (SELECT direction FROM messages m WHERE m.user_id=u.id AND m.direction IN ('in','out') ORDER BY m.created_at DESC LIMIT 1) last_dir,
              (SELECT COUNT(*) FROM messages m WHERE m.user_id=u.id AND m.direction='in' AND m.read_by_agent=0) unread,
              (SELECT kind FROM satisfaction s WHERE s.user_id=u.id AND s.agent_id=u.agent_id AND s.day=?) sat_today
       FROM users u WHERE u.agent_id=? AND u.accepted_rules=1
       ORDER BY COALESCE(u.last_message_at, u.created_at) DESC`
    )
    .all(day, aid);
  res.json({ chats: rows });
});

// messages of a user (and mark read)
router.get('/chats/:userId/messages', (req, res) => {
  const aid = agentId(req);
  const userId = Number(req.params.userId);
  if (!ownsUser(aid, userId)) return res.status(404).json({ error: 'not found' });
  // agent sees only the real conversation (in/out), not bot onboarding/system messages
  const msgs = db
    .prepare("SELECT * FROM messages WHERE user_id=? AND direction IN ('in','out') ORDER BY created_at ASC LIMIT 1000")
    .all(userId);
  // mark inbound as read
  const changed = db
    .prepare("UPDATE messages SET read_by_agent=1 WHERE user_id=? AND direction='in' AND read_by_agent=0")
    .run(userId);
  if (changed.changes > 0) rt.toAdmin('chat:read', { user_id: userId, agent_id: aid });
  const u = db.prepare('SELECT id,first_name,last_name,username FROM users WHERE id=?').get(userId);
  const satToday = db
    .prepare('SELECT kind,reason FROM satisfaction WHERE user_id=? AND agent_id=? AND day=?')
    .get(userId, aid, todayStr());
  res.json({ user: u, messages: msgs, satisfaction_today: satToday || null });
});

router.post('/chats/:userId/read', (req, res) => {
  const userId = Number(req.params.userId);
  if (!ownsUser(agentId(req), userId)) return res.status(404).json({ error: 'not found' });
  db.prepare("UPDATE messages SET read_by_agent=1 WHERE user_id=? AND direction='in' AND read_by_agent=0").run(userId);
  rt.toAdmin('chat:read', { user_id: userId, agent_id: agentId(req) });
  res.json({ ok: true });
});

// pinned messages for a user
router.get('/chats/:userId/pins', (req, res) => {
  const userId = Number(req.params.userId);
  if (!ownsUser(agentId(req), userId)) return res.status(404).json({ error: 'not found' });
  const pins = db
    .prepare('SELECT * FROM messages WHERE user_id=? AND pinned=1 ORDER BY created_at DESC')
    .all(userId);
  res.json({ pins });
});

router.post('/messages/:id/pin', (req, res) => {
  const id = Number(req.params.id);
  const m = db.prepare('SELECT * FROM messages WHERE id=?').get(id);
  if (!m || !ownsUser(agentId(req), m.user_id)) return res.status(404).json({ error: 'not found' });
  const pinned = m.pinned ? 0 : 1;
  db.prepare('UPDATE messages SET pinned=? WHERE id=?').run(pinned, id);
  res.json({ ok: true, pinned });
});

// send a text message / reply
router.post('/chats/:userId/send', async (req, res) => {
  const aid = agentId(req);
  const userId = Number(req.params.userId);
  if (!ownsUser(aid, userId)) return res.status(404).json({ error: 'not found' });
  const text = (req.body.text || '').toString();
  if (!text.trim()) return res.status(400).json({ error: 'متن خالی است' });
  const { replyToBaleId, replyDbId } = resolveReply(req.body.replyDbId, userId);
  try {
    const msg = await messaging.sendText(userId, text, { agentId: aid, replyToBaleId, replyDbId });
    res.json({ ok: true, message: msg });
  } catch (e) {
    handleSendError(e, res);
  }
});

// send media (voice/photo/video/document/audio)
router.post('/chats/:userId/send-media', upload.single('file'), async (req, res) => {
  const aid = agentId(req);
  const userId = Number(req.params.userId);
  if (!ownsUser(aid, userId)) return res.status(404).json({ error: 'not found' });
  if (!req.file) return res.status(400).json({ error: 'فایلی ارسال نشد' });
  const kind = pickKind(req.body.kind, req.file.mimetype);
  const { replyToBaleId, replyDbId } = resolveReply(req.body.replyDbId, userId);
  const file = { buffer: req.file.buffer, name: req.file.originalname, mime: req.file.mimetype };
  try {
    const msg = await messaging.sendMedia(userId, kind, file, {
      agentId: aid,
      caption: req.body.caption || '',
      replyToBaleId,
      replyDbId,
    });
    res.json({ ok: true, message: msg });
  } catch (e) {
    handleSendError(e, res);
  }
});

// daily satisfaction / dissatisfaction mark (one per user per day)
router.post('/chats/:userId/satisfaction', (req, res) => {
  const aid = agentId(req);
  const userId = Number(req.params.userId);
  if (!ownsUser(aid, userId)) return res.status(404).json({ error: 'not found' });
  const kind = req.body.kind;
  if (!['satisfied', 'dissatisfied'].includes(kind)) return res.status(400).json({ error: 'kind invalid' });
  const day = todayStr();
  db.prepare(
    `INSERT INTO satisfaction (user_id, agent_id, kind, reason, day, created_at)
     VALUES (?,?,?,?,?,?)
     ON CONFLICT(user_id,agent_id,day) DO UPDATE SET kind=excluded.kind, reason=excluded.reason, created_at=excluded.created_at`
  ).run(userId, aid, kind, (req.body.reason || '').toString(), day, Date.now());
  rt.toAdmin('satisfaction:update', { user_id: userId, agent_id: aid, kind, day });
  res.json({ ok: true, kind });
});

// ---- helpers ----
function resolveReply(replyDbId, userId) {
  if (!replyDbId) return { replyToBaleId: null, replyDbId: null };
  const orig = db.prepare('SELECT id, bale_message_id, user_id FROM messages WHERE id=?').get(Number(replyDbId));
  if (!orig || orig.user_id !== userId) return { replyToBaleId: null, replyDbId: null };
  return { replyToBaleId: orig.bale_message_id || null, replyDbId: orig.id };
}

function pickKind(provided, mime) {
  if (provided && messaging.MEDIA_METHODS[provided]) return provided;
  if (!mime) return 'document';
  if (mime.startsWith('image/')) return 'photo';
  if (mime.startsWith('video/')) return 'video';
  if (mime === 'audio/ogg') return 'voice';
  if (mime.startsWith('audio/')) return 'audio';
  return 'document';
}

function handleSendError(e, res) {
  if (e instanceof BaleError && e.retryAfter) {
    return res.status(429).json({ error: 'rate_limited', retry_after: e.retryAfter, description: e.message });
  }
  if (e instanceof BaleError) {
    return res.status(502).json({ error: 'bale_error', description: e.message });
  }
  console.error('send error:', e);
  res.status(500).json({ error: 'server_error', description: e.message });
}

module.exports = router;
