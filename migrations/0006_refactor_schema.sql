-- Slice 5 refactor: id-based lookups, geen taal-veld, base-items table.
-- Demo-data uit slice 3-4 wordt vervangen.

DROP TABLE IF EXISTS group_variants;
DROP TABLE IF EXISTS group_base_items;
DROP TABLE IF EXISTS group_bases;
DROP TABLE IF EXISTS group_accessoires;
DROP INDEX IF EXISTS group_variants_unique;

-- groups: family_head_itemcode wordt UNIQUE zodat het als business-id kan dienen.
-- Rebuild om de constraint toe te voegen (SQLite ondersteunt geen ADD CONSTRAINT).
CREATE TABLE groups_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    family_head_itemcode TEXT NOT NULL UNIQUE,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
INSERT INTO groups_new (id, name, family_head_itemcode, created_at, updated_at)
    SELECT id, name, family_head_itemcode, created_at, updated_at FROM groups;
DROP TABLE groups;
ALTER TABLE groups_new RENAME TO groups;

CREATE TABLE group_bases (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    group_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (group_id, name),
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE
);

CREATE TABLE group_base_items (
    base_id INTEGER NOT NULL,
    itemcode TEXT NOT NULL,
    name TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (base_id, itemcode),
    FOREIGN KEY (base_id) REFERENCES group_bases(id) ON DELETE CASCADE
);

CREATE TABLE group_accessoires (
    group_id INTEGER NOT NULL,
    accessoire_id INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (group_id, accessoire_id),
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (accessoire_id) REFERENCES accessoires(id) ON DELETE CASCADE
);

CREATE TABLE group_variants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    base_id INTEGER NOT NULL,
    accessoire_id INTEGER NULL,
    afas_samenstelling_itemcode TEXT NULL,
    afas_status TEXT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (base_id) REFERENCES group_bases(id) ON DELETE CASCADE,
    FOREIGN KEY (accessoire_id) REFERENCES accessoires(id) ON DELETE CASCADE
);

CREATE UNIQUE INDEX group_variants_unique
    ON group_variants(base_id, COALESCE(accessoire_id, 0));
