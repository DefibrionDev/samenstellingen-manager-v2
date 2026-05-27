CREATE TABLE afas_prijslijsten (
    id TEXT PRIMARY KEY,
    omschrijving TEXT NOT NULL,
    gesynchroniseerd_op TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
