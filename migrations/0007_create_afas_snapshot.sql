CREATE TABLE afas_samenstellingen (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    itemcode TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    itemcode_parent TEXT NULL,
    synced_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE afas_samenstelling_bom (
    afas_samenstelling_id INTEGER NOT NULL,
    component_itemcode TEXT NOT NULL,
    PRIMARY KEY (afas_samenstelling_id, component_itemcode),
    FOREIGN KEY (afas_samenstelling_id) REFERENCES afas_samenstellingen(id) ON DELETE CASCADE
);
