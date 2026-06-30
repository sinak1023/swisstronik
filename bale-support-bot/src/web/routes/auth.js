'use strict';
const express = require('express');
const { login, sign, requireAuth } = require('../auth');
const router = express.Router();

router.post('/login', (req, res) => {
  const { username, password } = req.body || {};
  if (!username || !password) return res.status(400).json({ error: 'username/password required' });
  const ident = login(String(username).trim(), String(password));
  if (!ident) return res.status(401).json({ error: 'نام کاربری یا رمز عبور اشتباه است' });
  const token = sign(ident);
  res.json({ token, user: ident });
});

router.get('/me', requireAuth(), (req, res) => {
  res.json({ user: req.user });
});

module.exports = router;
