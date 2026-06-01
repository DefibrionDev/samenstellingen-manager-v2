-- Slice 41.1: itemcode-leidende deduplicatie voor bases.
--
-- Vervangt UNIQUE (group_id, name) uit migratie 0006 door een partial-UNIQUE
-- op (group_id, afas_itemcode) WHERE afas_itemcode IS NOT NULL. Bases zonder
-- SKU kunnen nu dezelfde naam delen — daarvoor blijft alleen een non-unique
-- index op (group_id, name) voor lookup-performance.
--
-- SQLite kan geen DROP CONSTRAINT: gebruik de officiële 12-step recreate.
-- FK's worden tijdens recreate gepauzeerd.

PRAGMA foreign_keys = OFF;

CREATE TABLE group_bases_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    group_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    language_code TEXT NULL,
    afas_itemcode TEXT NULL,
    variant_label TEXT NULL,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE
);

INSERT INTO group_bases_new (id, group_id, name, created_at, language_code, afas_itemcode, variant_label)
SELECT id, group_id, name, created_at, language_code, afas_itemcode, variant_label
FROM group_bases;

DROP TABLE group_bases;
ALTER TABLE group_bases_new RENAME TO group_bases;

CREATE INDEX idx_group_bases_name ON group_bases(group_id, name);
CREATE UNIQUE INDEX idx_group_bases_afas_itemcode
    ON group_bases(group_id, afas_itemcode)
    WHERE afas_itemcode IS NOT NULL;

PRAGMA foreign_keys = ON;
