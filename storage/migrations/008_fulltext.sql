CREATE VIRTUAL TABLE tenders_fts USING fts5(title, organization, description, search_text);

-- keep FTS in sync with tenders
CREATE TRIGGER tenders_ai AFTER INSERT ON tenders BEGIN
  INSERT INTO tenders_fts(rowid, title, organization, description, search_text)
  VALUES (new.id, new.title, new.organization, new.description, new.search_text);
END;
CREATE TRIGGER tenders_ad AFTER DELETE ON tenders BEGIN
  DELETE FROM tenders_fts WHERE rowid = old.id;
END;
CREATE TRIGGER tenders_au AFTER UPDATE ON tenders BEGIN
  DELETE FROM tenders_fts WHERE rowid = old.id;
  INSERT INTO tenders_fts(rowid, title, organization, description, search_text)
  VALUES (new.id, new.title, new.organization, new.description, new.search_text);
END;
