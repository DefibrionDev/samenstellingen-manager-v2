-- Slice 45.0: website-entiteit + publicatie-state per base.
--
-- Een website is een AFAS-bestemming (bv. "Reseller NL", "Reseller FR") met
-- z'n eigen vrije-veld-paar voor Sync_* en Tonen_*. Per base kan via
-- base_publications worden vastgelegd op welke websites die gepubliceerd is;
-- accessoire-varianten erven die publicatie van hun base.
-- Zie PLAN.md §25.

CREATE TABLE websites (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    ff_sync_uuid TEXT NOT NULL,
    ff_tonen_uuid TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE base_publications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    base_id INTEGER NOT NULL,
    website_id INTEGER NOT NULL,
    published INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (base_id, website_id),
    FOREIGN KEY (base_id) REFERENCES group_bases(id) ON DELETE CASCADE,
    FOREIGN KEY (website_id) REFERENCES websites(id) ON DELETE CASCADE
);
