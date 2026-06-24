-- PS-1a (PLAN.md §12): persisteer de AFAS free-field publicatie-state (Sync_*/Tonen_*
-- per website, gemapt op hun UUID) in de lokale snapshot. Hiermee kan de
-- "online maar niet toegekend"-audit lokaal lezen (snel, testbaar) i.p.v. live
-- Get_Artikelen te bevragen. Wordt bij elke afas:pull ververst.
CREATE TABLE afas_free_field_state (
    afas_itemcode TEXT NOT NULL,
    free_field_uuid TEXT NOT NULL,
    enabled INTEGER NOT NULL,
    PRIMARY KEY (afas_itemcode, free_field_uuid)
);
