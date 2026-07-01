const bcrypt = require('bcryptjs');
const config = require('./config');

function checkCredentials(username, password) {
  if (!config.adminPasswordHash) {
    throw new Error(
      'ADMIN_PASSWORD_HASH is not set. Run "npm run hash-password -- yourpassword" and add it to .env'
    );
  }
  if (username !== config.adminUsername) return false;
  return bcrypt.compareSync(password, config.adminPasswordHash);
}

function requireAuth(req, res, next) {
  if (req.session && req.session.user) return next();
  if (req.path.startsWith('/api/')) return res.status(401).json({ ok: false, error: 'ابتدا وارد شوید' });
  return res.redirect('/login');
}

function requireBot(req, res, next) {
  if (req.session && req.session.bale && req.session.bale.token) return next();
  return res.status(400).json({ ok: false, error: 'ابتدا توکن بازو را وارد و تایید کنید' });
}

module.exports = { checkCredentials, requireAuth, requireBot };
