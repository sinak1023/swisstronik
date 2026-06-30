'use strict';
const fs = require('fs');
const config = require('./config');
const db = require('./db');
const { hash } = require('./web/auth');
const { createServer } = require('./web/server');
const bot = require('./bale/bot');

fs.mkdirSync(config.uploadsDir, { recursive: true });

// ensure a first admin exists
function ensureAdmin() {
  const count = db.prepare('SELECT COUNT(*) c FROM admins').get().c;
  if (count === 0) {
    db.prepare('INSERT INTO admins (username, password_hash, created_at) VALUES (?,?,?)').run(
      config.adminUsername,
      hash(config.adminPassword),
      Date.now()
    );
    console.log(`👤 Initial admin created: username="${config.adminUsername}" (set via .env)`);
  }
}

function main() {
  ensureAdmin();
  const server = createServer();
  server.listen(config.port, () => {
    console.log(`🌐 Panel running on ${config.publicBaseUrl}  (port ${config.port})`);
    console.log(`   • Login:  ${config.publicBaseUrl}/`);
    console.log(`   • Admin:  ${config.publicBaseUrl}/admin`);
    console.log(`   • Agent:  ${config.publicBaseUrl}/agent`);
  });
  bot.startPolling().catch((e) => console.error('bot failed to start:', e.message));

  process.on('SIGINT', () => {
    bot.stopPolling();
    process.exit(0);
  });

  // Never let a stray client-aborted request or background rejection crash the server
  process.on('unhandledRejection', (e) => console.error('unhandledRejection:', e && e.message));
  process.on('uncaughtException', (e) => {
    if (e && (e.message === 'Request aborted' || e.code === 'ECONNRESET' || e.code === 'ECONNABORTED')) return;
    console.error('uncaughtException:', e && e.message);
  });
}

main();
