CREATE TABLE saved_searches (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  name TEXT NOT NULL,
  keywords TEXT NOT NULL DEFAULT '',
  exclude_keywords TEXT DEFAULT '',
  countries TEXT NOT NULL DEFAULT '',
  sectors TEXT NOT NULL DEFAULT '',
  funding_types TEXT NOT NULL DEFAULT '',
  deadline_within_days INTEGER,
  active INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
