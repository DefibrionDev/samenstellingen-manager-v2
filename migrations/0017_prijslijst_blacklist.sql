CREATE TABLE prijslijst_blacklist (
    prijslijst_id TEXT PRIMARY KEY,
    reden TEXT NOT NULL,
    aangemaakt_op TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
