CREATE TABLE IF NOT EXISTS tender_files (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  tender_id INTEGER NOT NULL REFERENCES tenders(id) ON DELETE CASCADE,
  url TEXT NOT NULL,
  label TEXT,
  kind TEXT NOT NULL DEFAULT 'document',   -- document | page
  mime TEXT,
  filename TEXT,
  size INTEGER,
  sha256 TEXT,
  storage_path TEXT,
  status TEXT NOT NULL DEFAULT 'pending',  -- pending | ready | failed
  error TEXT,
  fetched_at TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE UNIQUE INDEX IF NOT EXISTS idx_tender_files_url ON tender_files(tender_id, url);
CREATE INDEX IF NOT EXISTS idx_tender_files_tender ON tender_files(tender_id);
CREATE INDEX IF NOT EXISTS idx_tender_files_status ON tender_files(status);
