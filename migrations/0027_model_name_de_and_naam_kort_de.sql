-- Duitse taal-bucket voor de naam-templates (VariantNamingPolicy):
-- DE-bases renderen als "AED Paket: {model_name_de} (...) mit {naam_kort_de}".
ALTER TABLE groups ADD COLUMN model_name_de TEXT NULL;
ALTER TABLE accessoires ADD COLUMN naam_kort_de TEXT NULL;
