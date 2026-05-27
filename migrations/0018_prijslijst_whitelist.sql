-- Switch van blacklist naar whitelist: fresh start (oude entries vervallen).
DROP TABLE IF EXISTS prijslijst_blacklist;

CREATE TABLE prijslijst_whitelist (
    prijslijst_id TEXT PRIMARY KEY,
    reden TEXT NOT NULL,
    aangemaakt_op TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
