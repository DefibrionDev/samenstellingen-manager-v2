-- Slice 38.0: optioneel variant-label per base voor hardware-varianten
-- binnen dezelfde groep (4G-radio bij Mindray, USB/WiFi/3G bij LIFEPAK).
-- VariantNamingPolicy plakt dit tussen <model> en <taal-suffix>; NULL = huidig gedrag.

ALTER TABLE group_bases ADD COLUMN variant_label TEXT NULL;
