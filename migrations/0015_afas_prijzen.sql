CREATE TABLE afas_prijzen (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    itemcode TEXT NOT NULL,
    prijslijst_id TEXT NOT NULL,
    debiteur_id TEXT NULL,
    verkoopprijs_cents INTEGER NOT NULL,
    staffel_aantal INTEGER NULL,
    geldig_van TEXT NOT NULL,
    geldig_tot TEXT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX afas_prijzen_itemcode ON afas_prijzen(itemcode);
CREATE INDEX afas_prijzen_prijslijst ON afas_prijzen(prijslijst_id);
