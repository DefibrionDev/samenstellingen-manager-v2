CREATE TABLE group_accessoires (
    group_id INTEGER NOT NULL,
    accessoire_id INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (group_id, accessoire_id),
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (accessoire_id) REFERENCES accessoires(id) ON DELETE CASCADE
);
