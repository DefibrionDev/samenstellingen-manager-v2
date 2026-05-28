-- Slice 37.0: per-taal naam-velden voor accessoires en groups.
-- Bestaande `groups.model_name` wordt model_name_nl; nieuwe model_name_fr/en erbij.
-- Accessoires krijgen naam_kort_nl/fr/en voor canonical-suffix in variant-namen.

ALTER TABLE accessoires ADD COLUMN naam_kort_nl TEXT NULL;
ALTER TABLE accessoires ADD COLUMN naam_kort_fr TEXT NULL;
ALTER TABLE accessoires ADD COLUMN naam_kort_en TEXT NULL;

ALTER TABLE groups RENAME COLUMN model_name TO model_name_nl;
ALTER TABLE groups ADD COLUMN model_name_fr TEXT NULL;
ALTER TABLE groups ADD COLUMN model_name_en TEXT NULL;
