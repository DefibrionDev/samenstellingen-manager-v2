-- Slice 55.0: webshop-classificatie per AFAS-samenstelling.
-- Product_type___01_ ("AED pakket") en Product_type___02_ ("350P") komen uit
-- de PowerBI_Item GetConnector (Get_Artikelen levert ze niet) en worden tijdens
-- elke afas:pull op de samenstellingen-snapshot gezet. We slaan de description
-- op (niet de AFAS enum-id) voor weergave; de fix-writer reresolvet naar de
-- enum-id bij het schrijven. Zie PLAN-AFAS.md §35.

ALTER TABLE afas_samenstellingen ADD COLUMN product_type_01 TEXT NULL;
ALTER TABLE afas_samenstellingen ADD COLUMN product_type_02 TEXT NULL;
