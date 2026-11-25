CREATE TABLE awards (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  canonical_id TEXT NOT NULL UNIQUE,
  tender_id INTEGER REFERENCES tenders(id) ON DELETE SET NULL,
  source_id INTEGER NOT NULL REFERENCES sources(id),
  source_ref TEXT,
  source_url TEXT NOT NULL,
  title TEXT NOT NULL,
  description TEXT DEFAULT '',
  buyer TEXT DEFAULT '',
  winner TEXT DEFAULT '',
  contract_ref TEXT,
  countries TEXT NOT NULL DEFAULT '',
  sectors TEXT NOT NULL DEFAULT '',
  amount REAL,
  currency TEXT,
  awarded_at TEXT,
  raw TEXT,
  search_text TEXT,
  first_seen_at TEXT NOT NULL DEFAULT (datetime('now')),
  updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_awards_tender ON awards(tender_id);
CREATE INDEX idx_awards_awarded ON awards(awarded_at);
CREATE INDEX idx_awards_amount ON awards(amount);
CREATE INDEX idx_awards_currency ON awards(currency);
CREATE INDEX idx_awards_first_seen ON awards(first_seen_at);

CREATE VIRTUAL TABLE awards_fts USING fts5(title, buyer, winner, description, search_text);

-- keep FTS in sync with awards
CREATE TRIGGER awards_ai AFTER INSERT ON awards BEGIN
  INSERT INTO awards_fts(rowid, title, buyer, winner, description, search_text)
  VALUES (new.id, new.title, new.buyer, new.winner, new.description, new.search_text);
END;
CREATE TRIGGER awards_ad AFTER DELETE ON awards BEGIN
  DELETE FROM awards_fts WHERE rowid = old.id;
END;
CREATE TRIGGER awards_au AFTER UPDATE ON awards BEGIN
  DELETE FROM awards_fts WHERE rowid = old.id;
  INSERT INTO awards_fts(rowid, title, buyer, winner, description, search_text)
  VALUES (new.id, new.title, new.buyer, new.winner, new.description, new.search_text);
END;
