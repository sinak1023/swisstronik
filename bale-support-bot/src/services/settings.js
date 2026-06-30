'use strict';
const db = require('../db');

const getStmt = db.prepare('SELECT value FROM settings WHERE key=?');
const setStmt = db.prepare(
  'INSERT INTO settings (key,value) VALUES (?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value'
);

function get(key, def = null) {
  const r = getStmt.get(key);
  return r ? r.value : def;
}
function set(key, value) {
  setStmt.run(key, value == null ? '' : String(value));
}

const DEFAULT_RULES = `سلام و وقت بخیر 👋

به پشتیبانی ما خوش آمدید.

قوانین استفاده از پشتیبانی:
۱) لطفاً سوال خود را واضح و کامل مطرح کنید.
۲) از ارسال پیام‌های نامرتبط و تبلیغاتی خودداری کنید.
۳) پاسخگویی توسط کارشناسان در ساعات کاری انجام می‌شود.
۴) با احترام با کارشناسان گفتگو کنید.

برای استفاده از پشتیبانی، لطفاً قوانین بالا را تایید کنید.`;

function getRulesText() {
  return get('rules_text', DEFAULT_RULES) || DEFAULT_RULES;
}
function getWelcomeVoicePath() {
  return get('welcome_voice_path', '') || '';
}

module.exports = { get, set, getRulesText, getWelcomeVoicePath, DEFAULT_RULES };
