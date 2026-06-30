'use strict';
const path = require('path');
const http = require('http');
const express = require('express');
const { Server } = require('socket.io');
const config = require('../config');
const { verify } = require('./auth');
const rt = require('../realtime');

function createServer() {
  const app = express();
  app.use(express.json({ limit: '2mb' }));
  app.use(express.urlencoded({ extended: true }));

  // API routes
  app.use('/api/auth', require('./routes/auth'));
  app.use('/api/admin', require('./routes/admin'));
  app.use('/api/agent', require('./routes/agent'));
  app.use('/api/media', require('./routes/media'));

  // uploaded files (agent photos, welcome voices) — public read
  app.use('/uploads', express.static(config.uploadsDir, { maxAge: '7d' }));

  // static panels
  app.use(express.static(config.publicDir));
  app.get('/admin', (req, res) => res.sendFile(path.join(config.publicDir, 'admin.html')));
  app.get('/agent', (req, res) => res.sendFile(path.join(config.publicDir, 'agent.html')));
  app.get('/', (req, res) => res.sendFile(path.join(config.publicDir, 'login.html')));

  app.use((req, res) => res.status(404).json({ error: 'not found' }));

  const server = http.createServer(app);
  const io = new Server(server, { cors: { origin: true } });

  io.use((socket, next) => {
    const token = socket.handshake.auth && socket.handshake.auth.token;
    const claims = token && verify(token);
    if (!claims) return next(new Error('unauthorized'));
    socket.user = claims;
    next();
  });

  io.on('connection', (socket) => {
    const u = socket.user;
    if (u.role === 'admin') socket.join('admin');
    if (u.role === 'agent') socket.join(`agent:${u.id}`);
  });

  rt.setIo(io);
  return server;
}

module.exports = { createServer };
