CREATE TABLE group_bases (
    group_id INTEGER NOT NULL,
    itemcode TEXT NOT NULL,
    language_code TEXT NOT NULL,
    name TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (group_id, itemcode),
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE
);
