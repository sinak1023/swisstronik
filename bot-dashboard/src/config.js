require('dotenv').config();

function required(name, fallback) {
  const value = process.env[name];
  if (value === undefined || value === '') return fallback;
  return value;
}

module.exports = {
  port: parseInt(required('PORT', '3000'), 10),
  sessionSecret: required('SESSION_SECRET', 'dev-only-secret-change-me'),
  adminUsername: required('ADMIN_USERNAME', 'admin'),
  adminPasswordHash: required('ADMIN_PASSWORD_HASH', ''),
  cookieSecure: required('COOKIE_SECURE', 'false') === 'true',
  broadcastDelayMs: parseInt(required('BROADCAST_DELAY_MS', '350'), 10),
  // Effectively "never expires" - the cookie is refreshed on every request (rolling session)
  // and only cleared on explicit logout, so ~10 years covers any realistic idle period.
  sessionMaxAgeMs: 10 * 365 * 24 * 60 * 60 * 1000,
};
