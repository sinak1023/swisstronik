const path = require('path');
const http = require('http');
const express = require('express');
const session = require('express-session');
const multer = require('multer');
const { Server } = require('socket.io');

const config = require('./src/config');
const { checkCredentials, requireAuth, requireBot } = require('./src/auth');
const bale = require('./src/baleClient');
const { parseCsv, isNumericId } = require('./src/csvParser');
const { uploads, attachments, broadcasts, put } = require('./src/store');
const { runBroadcast } = require('./src/broadcast');

const app = express();
const server = http.createServer(app);
const io = new Server(server);

if (config.cookieSecure) app.set('trust proxy', 1);

app.set('view engine', 'ejs');
app.set('views', path.join(__dirname, 'views'));
app.use(express.json());
app.use(express.urlencoded({ extended: false }));
app.use(express.static(path.join(__dirname, 'public')));

const sessionMiddleware = session({
  secret: config.sessionSecret,
  resave: false,
  saveUninitialized: false,
  rolling: true, // every request refreshes the expiry, so an active session never runs out
  cookie: {
    maxAge: config.sessionMaxAgeMs,
    httpOnly: true,
    secure: config.cookieSecure,
    sameSite: 'lax',
  },
});
app.use(sessionMiddleware);

const upload = multer({ storage: multer.memoryStorage(), limits: { fileSize: 50 * 1024 * 1024 } });

// Wraps multer so oversized/malformed uploads return a clean JSON error instead of
// falling through to Express's default HTML error page.
function uploadSingle(field) {
  const middleware = upload.single(field);
  return (req, res, next) => {
    middleware(req, res, (err) => {
      if (!err) return next();
      if (err.code === 'LIMIT_FILE_SIZE') {
        return res.status(400).json({ ok: false, error: 'حجم فایل بیش از حد مجاز (۵۰ مگابایت) است' });
      }
      return res.status(400).json({ ok: false, error: 'خطا در آپلود فایل: ' + err.message });
    });
  };
}

// ---------- Auth ----------

app.get('/login', (req, res) => {
  if (req.session.user) return res.redirect('/');
  res.render('login', { error: null });
});

app.post('/login', (req, res) => {
  const { username, password } = req.body;
  try {
    if (checkCredentials(username, password)) {
      req.session.user = username;
      return res.redirect('/');
    }
    return res.render('login', { error: 'نام کاربری یا رمز عبور اشتباه است' });
  } catch (err) {
    return res.render('login', { error: err.message });
  }
});

app.post('/logout', (req, res) => {
  req.session.destroy(() => {
    res.redirect('/login');
  });
});

// ---------- Dashboard ----------

app.get('/', requireAuth, (req, res) => {
  res.render('dashboard', {
    botInfo: (req.session.bale && req.session.bale.me) || null,
  });
});

app.post('/api/bot/connect', requireAuth, async (req, res) => {
  const { token } = req.body;
  if (!token || !token.trim()) {
    return res.status(400).json({ ok: false, error: 'توکن نمی‌تواند خالی باشد' });
  }
  try {
    const me = await bale.getMe(token.trim());
    req.session.bale = { token: token.trim(), me };
    res.json({ ok: true, me });
  } catch (err) {
    res.status(400).json({ ok: false, error: err.message || 'توکن نامعتبر است' });
  }
});

app.post('/api/bot/disconnect', requireAuth, (req, res) => {
  delete req.session.bale;
  res.json({ ok: true });
});

// ---------- CSV upload ----------

const PREVIEW_LIMIT = 2000;

app.post('/api/upload/csv', requireAuth, requireBot, uploadSingle('file'), (req, res) => {
  if (!req.file) return res.status(400).json({ ok: false, error: 'فایلی ارسال نشده است' });
  try {
    const { columns, rows } = parseCsv(req.file.buffer);
    if (columns.length === 0) {
      return res.status(400).json({ ok: false, error: 'فایل CSV خالی است یا قابل خواندن نیست' });
    }
    const uploadId = put(uploads, { columns, rows, sessionId: req.sessionID });
    res.json({
      ok: true,
      uploadId,
      columns,
      totalRows: rows.length,
      truncated: rows.length > PREVIEW_LIMIT,
      preview: rows.slice(0, PREVIEW_LIMIT),
    });
  } catch (err) {
    res.status(400).json({ ok: false, error: 'خطا در خواندن فایل CSV: ' + err.message });
  }
});

// ---------- Attachment upload (photo / document sent alongside the message) ----------

app.post('/api/upload/attachment', requireAuth, requireBot, uploadSingle('file'), (req, res) => {
  if (!req.file) return res.status(400).json({ ok: false, error: 'فایلی ارسال نشده است' });
  const kind = req.file.mimetype.startsWith('image/') ? 'photo' : 'document';
  const attachmentId = put(attachments, {
    buffer: req.file.buffer,
    filename: req.file.originalname,
    mimetype: req.file.mimetype,
    kind,
    sessionId: req.sessionID,
  });
  res.json({ ok: true, attachmentId, kind, filename: req.file.originalname, size: req.file.size });
});

// ---------- Broadcast ----------

app.post('/api/broadcast/start', requireAuth, requireBot, (req, res) => {
  const { uploadId, idColumn, selectedIndices, text, parseMode, attachmentId, delayMs } = req.body;

  const uploadEntry = uploads.get(uploadId);
  if (!uploadEntry || uploadEntry.sessionId !== req.sessionID) {
    return res.status(400).json({ ok: false, error: 'فایل بارگذاری‌شده یافت نشد، دوباره آپلود کنید' });
  }
  if (!idColumn || !uploadEntry.columns.includes(idColumn)) {
    return res.status(400).json({ ok: false, error: 'ستون شناسه کاربران انتخاب نشده است' });
  }
  if ((!text || !text.trim()) && !attachmentId) {
    return res.status(400).json({ ok: false, error: 'متن پیام یا فایل ضمیمه را وارد کنید' });
  }

  let rows = uploadEntry.rows;
  if (Array.isArray(selectedIndices)) {
    const set = new Set(selectedIndices);
    rows = rows.filter((_, i) => set.has(i));
  }

  const recipients = [...new Set(
    rows.map((r) => String(r[idColumn]).trim()).filter((v) => isNumericId(v))
  )];

  if (recipients.length === 0) {
    return res.status(400).json({ ok: false, error: 'هیچ شناسه عددی معتبری در ستون انتخاب‌شده یافت نشد' });
  }

  let attachment;
  if (attachmentId) {
    const entry = attachments.get(attachmentId);
    if (!entry || entry.sessionId !== req.sessionID) {
      return res.status(400).json({ ok: false, error: 'فایل ضمیمه یافت نشد، دوباره آپلود کنید' });
    }
    attachment = { buffer: entry.buffer, filename: entry.filename, kind: entry.kind };
  }

  const broadcastId = put(broadcasts, {
    status: 'pending',
    total: recipients.length,
    sent: 0,
    failed: 0,
    log: [],
    sessionId: req.sessionID,
  });

  const token = req.session.bale.token;
  const delay = Number.isFinite(+delayMs) && +delayMs >= 0 ? +delayMs : config.broadcastDelayMs;

  runBroadcast(io, broadcastId, {
    token,
    recipients,
    text: text ? text.trim() : '',
    parseMode: !!parseMode,
    attachment,
    delayMs: delay,
  }).catch((err) => {
    const state = broadcasts.get(broadcastId);
    if (state) state.status = 'error';
    io.to(`broadcast:${broadcastId}`).emit('broadcast:complete', {
      broadcastId,
      sent: state ? state.sent : 0,
      failed: state ? state.failed : 0,
      total: recipients.length,
      status: 'error',
      error: err.message,
    });
  });

  res.json({ ok: true, broadcastId, total: recipients.length });
});

app.get('/api/broadcast/:id/status', requireAuth, (req, res) => {
  const state = broadcasts.get(req.params.id);
  if (!state || state.sessionId !== req.sessionID) {
    return res.status(404).json({ ok: false, error: 'یافت نشد' });
  }
  res.json({
    ok: true,
    status: state.status,
    total: state.total,
    sent: state.sent,
    failed: state.failed,
    log: state.log.slice(-500),
  });
});

app.post('/api/broadcast/:id/cancel', requireAuth, (req, res) => {
  const state = broadcasts.get(req.params.id);
  if (!state || state.sessionId !== req.sessionID) {
    return res.status(404).json({ ok: false, error: 'یافت نشد' });
  }
  if (state.status === 'running' || state.status === 'pending') state.status = 'cancelled';
  res.json({ ok: true });
});

// ---------- Socket.IO ----------

const wrapMiddleware = (middleware) => (socket, next) => middleware(socket.request, {}, next);
io.use(wrapMiddleware(sessionMiddleware));

io.on('connection', (socket) => {
  const req = socket.request;
  if (!req.session || !req.session.user) {
    socket.disconnect(true);
    return;
  }

  socket.on('broadcast:subscribe', (broadcastId) => {
    const state = broadcasts.get(broadcastId);
    if (!state || state.sessionId !== req.sessionID) return;
    socket.join(`broadcast:${broadcastId}`);
  });
});

// ---------- Fallback error handling ----------

app.use((req, res) => {
  if (req.path.startsWith('/api/')) return res.status(404).json({ ok: false, error: 'یافت نشد' });
  res.status(404).send('یافت نشد');
});

// Any error thrown/passed to next() by a route above lands here instead of
// crashing the process or leaking Express's default HTML/stack-trace page.
app.use((err, req, res, next) => {
  console.error('Unhandled request error:', err);
  if (res.headersSent) return next(err);
  if (req.path.startsWith('/api/')) return res.status(500).json({ ok: false, error: 'خطای داخلی سرور' });
  res.status(500).send('خطای داخلی سرور');
});

// Safety net: a bug in some rarely-hit code path shouldn't be able to take the
// whole dashboard down while a broadcast to hundreds/thousands of users is running.
// Per-recipient failures (bot blocked/stopped by a user, network hiccup, etc.) are
// already caught inside runBroadcast and simply logged as "failed" - this is only
// for truly unexpected errors elsewhere in the process.
process.on('unhandledRejection', (reason) => {
  console.error('Unhandled promise rejection:', reason);
});

process.on('uncaughtException', (err) => {
  console.error('Uncaught exception, shutting down for a clean restart:', err);
  process.exit(1);
});

function shutdown(signal) {
  console.log(`Received ${signal}, shutting down...`);
  server.close(() => process.exit(0));
  // Force-exit if connections don't close in time.
  setTimeout(() => process.exit(0), 5000).unref();
}
process.on('SIGTERM', () => shutdown('SIGTERM'));
process.on('SIGINT', () => shutdown('SIGINT'));

server.listen(config.port, () => {
  console.log(`Bale bot dashboard listening on http://localhost:${config.port}`);
});
