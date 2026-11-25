CREATE TABLE intelligence_usage (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  tender_id INTEGER NOT NULL REFERENCES tenders(id) ON DELETE CASCADE,
  period TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE UNIQUE INDEX idx_intelligence_usage_unique ON intelligence_usage(user_id, tender_id, period);
CREATE INDEX idx_intelligence_usage_user_period ON intelligence_usage(user_id, period);
