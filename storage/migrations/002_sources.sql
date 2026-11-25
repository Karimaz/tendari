CREATE TABLE sources (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  region TEXT,
  connector_class TEXT NOT NULL,
  enabled INTEGER NOT NULL DEFAULT 0,
  fetch_interval_min INTEGER NOT NULL DEFAULT 360,
  last_fetched_at TEXT,
  last_status TEXT,
  last_error TEXT,
  config TEXT
);
