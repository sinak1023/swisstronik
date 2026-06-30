'use strict';
const jwt = require('jsonwebtoken');
const bcrypt = require('bcryptjs');
const db = require('../db');
const config = require('../config');

function sign(payload) {
  return jwt.sign(payload, config.jwtSecret, { expiresIn: '30d' });
}

function verify(token) {
  try {
    return jwt.verify(token, config.jwtSecret);
  } catch {
    return null;
  }
}

function login(username, password) {
  const admin = db.prepare('SELECT * FROM admins WHERE username=?').get(username);
  if (admin && bcrypt.compareSync(password, admin.password_hash)) {
    return { role: 'admin', id: admin.id, username: admin.username };
  }
  const agent = db.prepare('SELECT * FROM agents WHERE username=?').get(username);
  if (agent && agent.active && bcrypt.compareSync(password, agent.password_hash)) {
    return { role: 'agent', id: agent.id, username: agent.username, name: agent.name };
  }
  return null;
}

function tokenFromReq(req) {
  const h = req.headers.authorization || '';
  if (h.startsWith('Bearer ')) return h.slice(7);
  if (req.query && req.query.token) return req.query.token;
  return null;
}

function requireAuth(role) {
  return (req, res, next) => {
    const token = tokenFromReq(req);
    const claims = token && verify(token);
    if (!claims) return res.status(401).json({ error: 'unauthorized' });
    if (role && claims.role !== role) return res.status(403).json({ error: 'forbidden' });
    // ensure agent still exists & active
    if (claims.role === 'agent') {
      const a = db.prepare('SELECT active FROM agents WHERE id=?').get(claims.id);
      if (!a || !a.active) return res.status(403).json({ error: 'agent_disabled' });
    }
    req.user = claims;
    next();
  };
}

module.exports = { sign, verify, login, requireAuth, hash: (p) => bcrypt.hashSync(p, 10) };
