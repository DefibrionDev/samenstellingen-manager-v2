-- Slice WC-0.0: WooCommerce stores-registry + producten-snapshot.
--
-- Eén tabel `woocommerce_stores` met REST-credentials per shop (basis-URL,
-- consumer key/secret, configureerbare AFAS-itemcode-meta-key). Eén tabel
-- `woocommerce_products` met simple + variable + variation in dezelfde
-- structuur, gelinkt aan stores. Variations hebben wc_parent_id gevuld.
-- Zie PLAN.md §3.

CREATE TABLE woocommerce_stores (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    base_url TEXT NOT NULL,
    consumer_key TEXT NOT NULL,
    consumer_secret TEXT NOT NULL,
    afas_itemcode_meta_key TEXT NOT NULL DEFAULT '_afas_itemcode',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE woocommerce_products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    store_id INTEGER NOT NULL,
    wc_product_id INTEGER NOT NULL,
    wc_parent_id INTEGER NULL,
    type TEXT NOT NULL CHECK (type IN ('simple', 'variable', 'variation')),
    sku TEXT NULL,
    afas_itemcode TEXT NULL,
    name TEXT NOT NULL,
    status TEXT NOT NULL,
    permalink TEXT NULL,
    synced_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (store_id, wc_product_id),
    FOREIGN KEY (store_id) REFERENCES woocommerce_stores(id) ON DELETE CASCADE
);

CREATE INDEX idx_wc_products_afas ON woocommerce_products(afas_itemcode);
CREATE INDEX idx_wc_products_store ON woocommerce_products(store_id);
