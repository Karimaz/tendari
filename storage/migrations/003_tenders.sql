CREATE TABLE tenders (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  canonical_id TEXT NOT NULL UNIQUE,
  title TEXT NOT NULL,
  description TEXT DEFAULT '',
  organization TEXT DEFAULT '',
  source_id INTEGER NOT NULL REFERENCES sources(id),
  source_ref TEXT,
  source_url TEXT NOT NULL,
  countries TEXT NOT NULL DEFAULT '',
  sectors TEXT NOT NULL DEFAULT '',
  funding_type TEXT,
  value_amount REAL, value_currency TEXT,
  published_at TEXT, deadline_at TEXT,
  status TEXT NOT NULL DEFAULT 'open',
  raw TEXT,
  first_seen_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now')),
  search_text TEXT
);
CREATE INDEX idx_tenders_deadline ON tenders(deadline_at);
CREATE INDEX idx_tenders_published ON tenders(published_at);
CREATE INDEX idx_tenders_status ON tenders(status);
CREATE INDEX idx_tenders_first_seen ON tenders(first_seen_at);
