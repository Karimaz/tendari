CREATE TABLE notifications (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  tender_id INTEGER NOT NULL REFERENCES tenders(id) ON DELETE CASCADE,
  channel TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending',
  attempts INTEGER NOT NULL DEFAULT 0,
  sent_at TEXT, error TEXT,
  UNIQUE (user_id, tender_id, channel)
);
CREATE INDEX idx_notifications_pending ON notifications(status, id);
