'use strict';
const express = require('express');
const multer = require('multer');
const path = require('path');
const fs = require('fs');
const db = require('../../db');
const config = require('../../config');
const { requireAuth, hash } = require('../auth');
const stats = require('../../services/stats');
const settings = require('../../services/settings');
const surveySvc = require('../../services/survey');
const rt = require('../../realtime');
const { todayStr } = require('../../util');

const router = express.Router();
router.use(requireAuth('admin'));

// disk storage for agent photo / welcome voice / rules voice
const storage = multer.diskStorage({
  destination: (req, file, cb) => cb(null, config.uploadsDir),
  filename: (req, file, cb) => {
    const ext = path.extname(file.originalname) || '';
    cb(null, `${file.fieldname}_${Date.now()}_${Math.random().toString(36).slice(2, 8)}${ext}`);
  },
});
const upload = multer({ storage, limits: { fileSize: 50 * 1024 * 1024 } });
const agentUpload = upload.fields([
  { name: 'photo', maxCount: 1 },
  { name: 'welcome_voice', maxCount: 1 },
]);

// ---------------- overview / dashboard ----------------
router.get('/overview', (req, res) => {
  const preset = req.query.preset || 'day';
  const day = req.query.day || todayStr();
  const { fromTs, toTs } = stats.rangeForPreset(preset, day);
  const agents = db.prepare('SELECT * FROM agents ORDER BY created_at ASC').all();
  const list = agents.map((a) => {
    const s = stats.agentSummary(a.id, fromTs, toTs);
    const surveys = stats.agentSurveyStats(a.id);
    const latest = surveys[0] || null;
    return {
      id: a.id,
      name: a.name,
      description: a.description,
      photo_path: a.photo_path,
      username: a.username,
      share: a.share,
      active: a.active,
      ...s,
      latest_survey: latest,
    };
  });
  const totals = {
    users: db.prepare('SELECT COUNT(*) c FROM users WHERE accepted_rules=1').get().c,
    agents: agents.length,
    active_agents: agents.filter((a) => a.active).length,
  };
  res.json({ agents: list, totals, preset, day });
});

// ---------------- agents CRUD ----------------
router.get('/agents', (req, res) => {
  res.json({ agents: db.prepare('SELECT id,name,description,photo_path,welcome_voice_path,username,share,active,created_at FROM agents ORDER BY created_at ASC').all() });
});

router.post('/agents', agentUpload, (req, res) => {
  const { name, description, username, password, share } = req.body || {};
  if (!name || !username || !password)
    return res.status(400).json({ error: 'نام، نام کاربری و رمز عبور الزامی است' });
  const exists = db.prepare('SELECT id FROM agents WHERE username=?').get(username);
  if (exists) return res.status(409).json({ error: 'این نام کاربری قبلاً استفاده شده است' });
  const photo = req.files && req.files.photo ? path.basename(req.files.photo[0].path) : '';
  const voice = req.files && req.files.welcome_voice ? path.basename(req.files.welcome_voice[0].path) : '';
  const info = db
    .prepare(
      `INSERT INTO agents (name, description, photo_path, welcome_voice_path, username, password_hash, share, active, created_at)
       VALUES (?,?,?,?,?,?,?,1,?)`
    )
    .run(name, description || '', photo, voice, username.trim(), hash(String(password)), Number(share) || 1, Date.now());
  const agent = db.prepare('SELECT id,name,description,photo_path,welcome_voice_path,username,share,active FROM agents WHERE id=?').get(info.lastInsertRowid);
  rt.toAdmin('agent:new', agent);
  res.json({ ok: true, agent });
});

router.put('/agents/:id', agentUpload, (req, res) => {
  const id = Number(req.params.id);
  const a = db.prepare('SELECT * FROM agents WHERE id=?').get(id);
  if (!a) return res.status(404).json({ error: 'not found' });
  const b = req.body || {};
  const name = b.name ?? a.name;
  const description = b.description ?? a.description;
  const share = b.share !== undefined ? Number(b.share) || 0 : a.share;
  const active = b.active !== undefined ? (b.active === 'true' || b.active === '1' || b.active === true ? 1 : 0) : a.active;
  let username = a.username;
  if (b.username && b.username.trim() !== a.username) {
    const dup = db.prepare('SELECT id FROM agents WHERE username=? AND id<>?').get(b.username.trim(), id);
    if (dup) return res.status(409).json({ error: 'نام کاربری تکراری است' });
    username = b.username.trim();
  }
  let photo = a.photo_path;
  let voice = a.welcome_voice_path;
  if (req.files && req.files.photo) photo = path.basename(req.files.photo[0].path);
  if (req.files && req.files.welcome_voice) voice = path.basename(req.files.welcome_voice[0].path);
  const pwd = b.password ? hash(String(b.password)) : a.password_hash;
  db.prepare(
    `UPDATE agents SET name=?, description=?, photo_path=?, welcome_voice_path=?, username=?, password_hash=?, share=?, active=? WHERE id=?`
  ).run(name, description, photo, voice, username, pwd, share, active, id);
  const agent = db.prepare('SELECT id,name,description,photo_path,welcome_voice_path,username,share,active FROM agents WHERE id=?').get(id);
  rt.toAdmin('agent:update', agent);
  res.json({ ok: true, agent });
});

router.delete('/agents/:id', (req, res) => {
  const id = Number(req.params.id);
  // reassign their users to NULL (will be re-picked on next message? keep simple: unassign)
  db.prepare('UPDATE users SET agent_id=NULL WHERE agent_id=?').run(id);
  db.prepare('DELETE FROM agents WHERE id=?').run(id);
  rt.toAdmin('agent:delete', { id });
  res.json({ ok: true });
});

// bulk update shares (percentages)
router.post('/agents/shares', (req, res) => {
  const items = (req.body && req.body.shares) || [];
  const tx = db.transaction((arr) => {
    for (const it of arr) db.prepare('UPDATE agents SET share=? WHERE id=?').run(Number(it.share) || 0, Number(it.id));
  });
  tx(items);
  rt.toAdmin('agent:shares', { items });
  res.json({ ok: true });
});

// users assigned to an agent (with per-user today stats + satisfaction label)
router.get('/agents/:id/users', (req, res) => {
  const id = Number(req.params.id);
  const day = todayStr();
  const rows = db
    .prepare(
      `SELECT u.id, u.first_name, u.last_name, u.username, u.last_message_at, u.created_at,
              (SELECT COUNT(*) FROM messages m WHERE m.user_id=u.id AND m.direction='in') total_in,
              (SELECT COUNT(*) FROM messages m WHERE m.user_id=u.id AND m.direction='out') total_out,
              (SELECT COUNT(*) FROM messages m WHERE m.user_id=u.id AND m.direction='in' AND m.read_by_agent=0) unread,
              (SELECT kind FROM satisfaction s WHERE s.user_id=u.id AND s.agent_id=u.agent_id AND s.day=?) sat_today
       FROM users u WHERE u.agent_id=? ORDER BY COALESCE(u.last_message_at,u.created_at) DESC`
    )
    .all(day, id);
  res.json({ users: rows });
});

// read a user's chat (admin, read-only)
router.get('/users/:userId/messages', (req, res) => {
  const userId = Number(req.params.userId);
  const u = db.prepare('SELECT id,first_name,last_name,username,agent_id FROM users WHERE id=?').get(userId);
  if (!u) return res.status(404).json({ error: 'not found' });
  const msgs = db.prepare('SELECT * FROM messages WHERE user_id=? ORDER BY created_at ASC LIMIT 2000').all(userId);
  res.json({ user: u, messages: msgs });
});

router.post('/users/:userId/reassign', (req, res) => {
  const userId = Number(req.params.userId);
  const agentId = req.body.agent_id ? Number(req.body.agent_id) : null;
  if (agentId && !db.prepare('SELECT id FROM agents WHERE id=?').get(agentId))
    return res.status(400).json({ error: 'agent not found' });
  db.prepare('UPDATE users SET agent_id=? WHERE id=?').run(agentId, userId);
  rt.toAdmin('user:reassign', { user_id: userId, agent_id: agentId });
  if (agentId) rt.toAgent(agentId, 'user:assigned', db.prepare('SELECT * FROM users WHERE id=?').get(userId));
  res.json({ ok: true });
});

// ---------------- settings (rules text + welcome voice) ----------------
router.get('/settings', (req, res) => {
  res.json({
    rules_text: settings.getRulesText(),
    welcome_voice_path: settings.getWelcomeVoicePath(),
    bot_token_set: Boolean(config.baleToken),
  });
});

router.post('/settings', upload.fields([{ name: 'welcome_voice', maxCount: 1 }]), (req, res) => {
  if (req.body.rules_text !== undefined) settings.set('rules_text', req.body.rules_text);
  if (req.files && req.files.welcome_voice)
    settings.set('welcome_voice_path', path.basename(req.files.welcome_voice[0].path));
  res.json({ ok: true, rules_text: settings.getRulesText(), welcome_voice_path: settings.getWelcomeVoicePath() });
});

// ---------------- surveys ----------------
router.post('/surveys', (req, res) => {
  const agentId = Number(req.body.agent_id);
  const weeks = Number(req.body.period_weeks);
  if (!db.prepare('SELECT id FROM agents WHERE id=?').get(agentId))
    return res.status(400).json({ error: 'agent not found' });
  if (!(weeks >= 1 && weeks <= 5)) return res.status(400).json({ error: 'period invalid' });
  const eligible = surveySvc.eligibleUsers(agentId, weeks).length;
  if (eligible === 0) return res.status(400).json({ error: 'هیچ کاربری در این بازه برای این پشتیبان وجود ندارد' });
  const survey = surveySvc.createAndSend(agentId, weeks, req.body.question);
  res.json({ ok: true, survey, eligible });
});

router.get('/surveys', (req, res) => {
  const agentId = req.query.agent_id ? Number(req.query.agent_id) : null;
  if (agentId) return res.json({ surveys: stats.agentSurveyStats(agentId) });
  const agents = db.prepare('SELECT id,name FROM agents').all();
  res.json({ agents: agents.map((a) => ({ ...a, surveys: stats.agentSurveyStats(a.id) })) });
});

// survey responses detail (per user)
router.get('/surveys/:id/responses', (req, res) => {
  const sid = Number(req.params.id);
  const rows = db
    .prepare(
      `SELECT r.rating, r.created_at, u.id user_id, u.first_name, u.last_name, u.username
       FROM survey_responses r JOIN users u ON u.id=r.user_id WHERE r.survey_id=? ORDER BY r.created_at DESC`
    )
    .all(sid);
  res.json({ responses: rows });
});

// ---------------- satisfaction report (filterable) ----------------
router.get('/satisfaction', (req, res) => {
  const where = [];
  const params = [];
  if (req.query.agent_id) {
    where.push('s.agent_id=?');
    params.push(Number(req.query.agent_id));
  }
  if (req.query.kind && ['satisfied', 'dissatisfied'].includes(req.query.kind)) {
    where.push('s.kind=?');
    params.push(req.query.kind);
  }
  if (req.query.from) {
    where.push('s.day>=?');
    params.push(req.query.from);
  }
  if (req.query.to) {
    where.push('s.day<=?');
    params.push(req.query.to);
  }
  if (req.query.user) {
    where.push('(u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ? OR u.id=?)');
    const q = `%${req.query.user}%`;
    params.push(q, q, q, Number(req.query.user) || 0);
  }
  const sql = `SELECT s.id, s.kind, s.reason, s.day, s.created_at,
                      u.id user_id, u.first_name, u.last_name, u.username,
                      a.id agent_id, a.name agent_name
               FROM satisfaction s
               JOIN users u ON u.id=s.user_id
               JOIN agents a ON a.id=s.agent_id
               ${where.length ? 'WHERE ' + where.join(' AND ') : ''}
               ORDER BY s.created_at DESC LIMIT 1000`;
  res.json({ items: db.prepare(sql).all(...params) });
});

module.exports = router;
