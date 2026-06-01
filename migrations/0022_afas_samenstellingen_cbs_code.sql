-- Slice 44.0: snapshot van CBS-goederencode per AFAS-samenstelling.
-- Wordt gevuld door HttpAfasSamenstellingenFetcher tijdens elke afas:pull,
-- en gebruikt door audit:missing-cbs om referentie-samenstellingen op te
-- sporen die nog geen CBS hebben — zonder CBS kan variants:fix-missing
-- geen nieuwe varianten POST'en (zie PLAN.md §24).

ALTER TABLE afas_samenstellingen ADD COLUMN cbs_code TEXT NULL;
