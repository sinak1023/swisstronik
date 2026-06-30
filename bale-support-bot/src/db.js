'use strict';
const path = require('path');
const fs = require('fs');
const Database = require('better-sqlite3');
const config = require('./config');

fs.mkdirSync(config.dataDir, { recursive: true });
const db = new Database(path.join(config.dataDir, 'app.db'));
db.pragma('journal_mode = WAL');
db.pragma('foreign_keys = ON');

db.exec(`
CREATE TABLE IF NOT EXISTS admins (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT UNIQUE NOT NULL,
  password_hash TEXT NOT NULL,
  created_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS agents (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  description TEXT DEFAULT '',
  photo_path TEXT DEFAULT '',
  welcome_voice_path TEXT DEFAULT '',
  username TEXT UNIQUE NOT NULL,
  password_hash TEXT NOT NULL,
  share REAL NOT NULL DEFAULT 1,   -- weight for distribution
  active INTEGER NOT NULL DEFAULT 1,
  created_at INTEGER NOT NULL
);

-- Bale users (customers). id = bale chat id
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY,           -- bale chat id
  first_name TEXT DEFAULT '',
  last_name TEXT DEFAULT '',
  username TEXT DEFAULT '',
  agent_id INTEGER,                 -- assigned agent
  accepted_rules INTEGER NOT NULL DEFAULT 0,
  state TEXT NOT NULL DEFAULT 'new',-- new | accepted | active
  pending_survey_id INTEGER,        -- survey awaiting answer
  created_at INTEGER NOT NULL,
  last_message_at INTEGER,
  FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS messages (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  agent_id INTEGER,
  direction TEXT NOT NULL,          -- 'in' (user->bot) | 'out' (agent->user) | 'system'
  type TEXT NOT NULL DEFAULT 'text',-- text | voice | photo | video | document | audio | sticker | other
  text TEXT DEFAULT '',
  file_id TEXT DEFAULT '',          -- bale file_id
  file_name TEXT DEFAULT '',
  mime TEXT DEFAULT '',
  local_path TEXT DEFAULT '',       -- cached media path (for outgoing uploads)
  reply_to_message_id INTEGER,      -- our messages.id this replies to
  bale_message_id INTEGER,          -- message_id returned by Bale
  pinned INTEGER NOT NULL DEFAULT 0,
  read_by_agent INTEGER NOT NULL DEFAULT 0,
  created_at INTEGER NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_messages_user ON messages(user_id, created_at);
CREATE INDEX IF NOT EXISTS idx_messages_agent ON messages(agent_id, created_at);

-- daily satisfaction / dissatisfaction marks set by an agent for a user
CREATE TABLE IF NOT EXISTS satisfaction (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  agent_id INTEGER NOT NULL,
  kind TEXT NOT NULL,               -- 'satisfied' | 'dissatisfied'
  reason TEXT DEFAULT '',
  day TEXT NOT NULL,                -- YYYY-MM-DD (local)
  created_at INTEGER NOT NULL,
  UNIQUE(user_id, agent_id, day),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
);

-- survey campaigns
CREATE TABLE IF NOT EXISTS surveys (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  agent_id INTEGER NOT NULL,
  period_weeks INTEGER NOT NULL,    -- 1,2,3,4,5(=4+)
  question TEXT NOT NULL,
  sent_count INTEGER NOT NULL DEFAULT 0,
  target_count INTEGER NOT NULL DEFAULT 0,
  status TEXT NOT NULL DEFAULT 'pending', -- pending | sending | done
  created_at INTEGER NOT NULL,
  FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS survey_responses (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  survey_id INTEGER NOT NULL,
  user_id INTEGER NOT NULL,
  rating INTEGER NOT NULL,          -- 1..5
  created_at INTEGER NOT NULL,
  UNIQUE(survey_id, user_id),
  FOREIGN KEY (survey_id) REFERENCES surveys(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS settings (
  key TEXT PRIMARY KEY,
  value TEXT
);
`);

module.exports = db;
