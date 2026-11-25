CREATE TABLE tender_aliases (
  tender_id INTEGER NOT NULL REFERENCES tenders(id) ON DELETE CASCADE,
  source_id INTEGER NOT NULL REFERENCES sources(id),
  source_ref TEXT,
  url TEXT,
  PRIMARY KEY (tender_id, source_id)
);
