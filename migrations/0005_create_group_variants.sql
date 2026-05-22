CREATE TABLE group_variants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    group_id INTEGER NOT NULL,
    base_itemcode TEXT NOT NULL,
    accessoire_id INTEGER NULL,
    afas_samenstelling_itemcode TEXT NULL,
    afas_status TEXT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id, base_itemcode) REFERENCES group_bases(group_id, itemcode) ON DELETE CASCADE,
    FOREIGN KEY (accessoire_id) REFERENCES accessoires(id) ON DELETE CASCADE
);

CREATE UNIQUE INDEX group_variants_unique
    ON group_variants(group_id, base_itemcode, COALESCE(accessoire_id, 0));
