'use strict';
require('dotenv').config();
const path = require('path');

const config = {
  baleToken: process.env.BALE_BOT_TOKEN || '',
  baleApiBase: (process.env.BALE_API_BASE || 'https://tapi.bale.ai').replace(/\/$/, ''),
  port: parseInt(process.env.PORT || '3000', 10),
  jwtSecret: process.env.JWT_SECRET || 'dev_insecure_secret_change_me',
  adminUsername: process.env.ADMIN_USERNAME || 'admin',
  adminPassword: process.env.ADMIN_PASSWORD || 'admin12345',
  minIntervalMs: parseInt(process.env.BALE_MIN_INTERVAL_MS || '350', 10),
  publicBaseUrl: (process.env.PUBLIC_BASE_URL || 'http://localhost:3000').replace(/\/$/, ''),
  root: path.resolve(__dirname, '..'),
  uploadsDir: path.resolve(__dirname, '..', 'uploads'),
  dataDir: path.resolve(__dirname, '..', 'data'),
  publicDir: path.resolve(__dirname, '..', 'public'),
};

module.exports = config;
