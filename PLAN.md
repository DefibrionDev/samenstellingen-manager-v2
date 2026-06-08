# PLAN.md — WooCommerce-koppeling

> **Vorige cyclus** (AFAS-management): zie `PLAN-AFAS.md` + `TODO-AFAS.md` — afgesloten met slice 51 (intent-based publicatie-sync + consistente missend-telling). Dat plan blijft de bron voor alle AFAS-werk: BOM-equality, variant-naming, publicatie-state, prijs-drift, base-/sticker-policy.

Deze cyclus voegt een **tweede synchronisatie-target** toe: de samenstellingen die we in AFAS managen moeten óók consistent gepubliceerd staan in één of meer WooCommerce-shops (`defibrion.nl`, `defibrion.fr`, `defibrion.be`, …). De eerste stap is read-only inventarisatie: maak zichtbaar welke base + variant-itemcodes in welke shop staan, met welke status (published / draft / private). Mutaties komen pas in latere cycli.

## 1. Scope van cyclus 1

### Wel

- Verbinding met de WooCommerce REST API per geregistreerde shop (multi-store from day 1).
- Ophalen van **simple** + **variable** producten + hun variations.
- Extractie van een vrij configureerbare **AFAS-itemcode-meta-key** (b.v. `_afas_itemcode`, `afas_item_nummer`) uit `meta_data`.
- Lokale snapshot in SQLite naast de bestaande AFAS-snapshot.
- Index-CLI's + read-only UI-pagina die per AFAS-itemcode tonen: waar gepubliceerd, wat is de WooCommerce-status, ontbreekt de koppeling, of staat 'r een product op de shop dat niet in onze AFAS-managed-lijst voorkomt.

### Niet

- Producten **maken** of **wijzigen** in WooCommerce. Geen `wc:create`/`wc:update`-CLI's in deze cyclus.
- Prijssync, voorraadsync, image-management.
- Categorie-management, attribuut-management.
- Klantorders, verkoop-data, of andere WooCommerce-modules.
- Multi-language WPML/Polylang-vertaalkoppelingen.

Doel: dezelfde "wat staat waar en is dat consistent?"-zichtbaarheid die we voor AFAS al hebben (cyclus 1 PLAN-AFAS §25–§31), nu uitgebreid naar WooCommerce. Mutatie-CLI's volgen in cyclus 2 of 3 zodra de read-only fase stabiel is.

## 2. Begrippen

- **WooCommerce store** — één WordPress-installatie met WooCommerce-plugin actief. Heeft een base-URL (b.v. `https://defibrion.nl`), en een REST API authenticated via **consumer key + secret** (WC's eigen credential-paar, los van WordPress-users). Eén store kan meerdere talen draaien (WPML), maar dat behandelen we voorlopig als één entiteit per WC-instance.
- **Simple product** — WC product zonder variaties. Heeft één `id`, één SKU, één set meta. Mapt 1-op-1 naar één AFAS-itemcode (de base zelf óf een specifieke variant).
- **Variable product** — WC product mét variaties. Heeft één parent (`type=variable`) + N variations (elk een eigen WC-record met eigen `id`, SKU, meta). Variations kunnen elk een eigen `_afas_itemcode`-meta hebben.
- **AFAS-itemcode-meta** — de meta-key die de WC-product/variation linkt aan een AFAS-samenstelling. Configureerbaar per shop (default `_afas_itemcode`); waarde is een string die match'd tegen `afas_samenstellingen.itemcode`.
- **Index-rij** — `(afas_itemcode × store) → {wc_id, wc_type, wc_parent_id, status, naam}`. Eén AFAS-itemcode kan in meerdere stores gepubliceerd zijn → meerdere index-rijen.
- **Drift** voor deze cyclus: AFAS-itemcode ontbreekt in store waar 'ie wel verwacht wordt (= base is gemarkeerd published voor die store), of staat in de store maar niet in onze AFAS-managed-lijst (orphan-WC-product), of staat dubbel (twee WC-records met dezelfde AFAS-itemcode-meta).

## 3. Conceptueel model + schema-aanpak

### Stores-registry

```sql
CREATE TABLE woocommerce_stores (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,           -- bv. "defibrion.nl"
    base_url TEXT NOT NULL,              -- https://defibrion.nl  (zonder trailing /, zonder /wp-json)
    consumer_key TEXT NOT NULL,          -- WC ck_...
    consumer_secret TEXT NOT NULL,       -- WC cs_...
    afas_itemcode_meta_key TEXT NOT NULL DEFAULT '_afas_itemcode',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

`consumer_key` + `consumer_secret` in plain text is risico — voor MVP geaccepteerd want de SQLite-DB staat lokaal achter de developer-machine. Latere encryptie (bv. via `sodium_crypto_secretbox` met een key uit `.env`) is een eigen slice indien nodig.

### Producten-snapshot

```sql
CREATE TABLE woocommerce_products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    store_id INTEGER NOT NULL,
    wc_product_id INTEGER NOT NULL,             -- de id uit WooCommerce REST
    wc_parent_id INTEGER NULL,                  -- NULL voor simple + variable-parent; gevuld voor variations
    type TEXT NOT NULL,                         -- 'simple' | 'variable' | 'variation'
    sku TEXT NULL,                              -- WC sku-veld
    afas_itemcode TEXT NULL,                    -- uit meta_data; NULL = niet aan AFAS gelinkt
    name TEXT NOT NULL,                         -- WC name (parent) of attribute-string (variation)
    status TEXT NOT NULL,                       -- publish | draft | private | pending | trash
    permalink TEXT NULL,                        -- handig voor UI-links
    synced_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (store_id, wc_product_id),
    FOREIGN KEY (store_id) REFERENCES woocommerce_stores(id) ON DELETE CASCADE
);

CREATE INDEX idx_wc_products_afas ON woocommerce_products(afas_itemcode);
CREATE INDEX idx_wc_products_store ON woocommerce_products(store_id);
```

Eén tabel voor parents + variations — de `type`-kolom + `wc_parent_id` houden de hiërarchie. Dat past beter bij de "index per itemcode"-vraag dan twee aparte tabellen, want we joinen toch altijd `(store, afas_itemcode)` ongeacht of het een simple of variation is.

### Geen domeincontract voor WC-client zonder interface

Per CLAUDE.md: `WooCommerceClient` is een interface (`Domain/Woo/`), implementatie via een HTTP-client wrapper (`Infrastructure/Woo/Http/HttpWooCommerceClient`) + een `InMemoryWooCommerceClient` voor tests. Geen directe calls naar de `automattic/woocommerce` SDK in de business-logic. Mogelijk gebruiken we die SDK onderaan de hood, maar wrappen 'm volledig.

Methods op de interface (minimaal):

```php
interface WooCommerceClient {
    /** @return list<WooProduct> */
    public function fetchAllProducts(): array;

    /** @return list<WooProductVariation> */
    public function fetchAllVariationsFor(int $variableProductId): array;
}
```

`WooProduct` + `WooProductVariation` zijn value objects met `id`, `type`, `sku`, `name`, `status`, `permalink`, `metaByKey: array<string, string>`, en (voor variations) `parentId`. Paging zit in de implementatie — de interface levert flat lijsten.

## 4. Auth-aanpak

WooCommerce REST API ondersteunt twee auth-modi:

- **HTTPS Basic-Auth** — `Authorization: Basic base64(ck:cs)`. Werkt direct over HTTPS, eenvoud. Vereiste dat de shop HTTPS heeft.
- **OAuth 1.0a over HTTP** — voor lokale/staging-shops zonder TLS. Vereist signature-berekening per request.

Voor MVP: alleen Basic-Auth. `defibrion.nl/.fr/.be` draaien HTTPS, dus geen reden voor OAuth-complexiteit. De implementatie kan later OAuth toevoegen via een `WooAuthStrategy`-discriminant indien nodig.

Library: Guzzle (zit al in `composer.json` voor AFAS-client) — bouwt een dunne `HttpWooCommerceClient` op dezelfde Guzzle-instance.

## 5. CLI-oppervlak

- `wc:store:add <name> <base-url> <consumer-key> <consumer-secret> [--meta-key=…]` — registreer een shop. Default meta-key = `_afas_itemcode`.
- `wc:store:list` — toon geregistreerde shops (consumer-secret gemaskerd).
- `wc:store:remove <name>` — cascade ruimt `woocommerce_products` voor die shop op.
- `wc:pull [--store=name]` — fetch alle producten + variations per shop, vervangt de lokale snapshot. Default: alle shops. Per shop: clear bestaande rijen + insert nieuwe. Geen partial-update logic in MVP — full refresh is simpel en idempotent.
- `wc:index [--store=name]` — toon de index per AFAS-itemcode × store: `(itemcode, store, wc_id, type, status, naam)`. Optie `--missing` toont alleen AFAS-itemcodes die in geen enkele shop staan. Optie `--orphan` toont WC-producten zonder matching AFAS-itemcode (in onze managed-lijst).

Read-only dus geen `--apply`-flag nodig in deze cyclus.

## 6. UI-oppervlak

Nieuwe pagina `/woocommerce` (read-only, conform CLAUDE.md UI-policy):

- Tab "Index": tabel met kolommen `AFAS-itemcode | Naam (uit AFAS) | Aanwezig op: defibrion.nl | defibrion.fr | …`. Per shop-kolom een chip met status (✓ publish, ◐ draft, ✗ ontbreekt). Klik op een chip → detail-paneel of permalink.
- Tab "Orphans": WC-producten zonder match in onze AFAS-managed-lijst, per shop gegroepeerd. Verwijst naar CLI om op te schonen.
- Tab "Stores": geregistreerde shops + laatste `wc:pull`-tijd.

Geen mutatie-knoppen.

## 7. Slices

- **Slice WC-0** — `composer require guzzlehttp/guzzle` is al voldoende; geen automattic/woocommerce SDK nodig (we praten direct REST). Migratie `0024_woocommerce_stores_and_products.sql`. `Domain/Woo/`: `WooCommerceStore`, `WooProduct`, `WooProductVariation`, `WooCommerceClient` interface, `WooCommerceStoreRepository`, `WooProductRepository` interfaces. InMemory + Sqlite implementaties + contract-tests.
- **Slice WC-1** — Stores-registry CLI: `wc:store:add`, `wc:store:list`, `wc:store:remove`. Validatie: unieke naam, base-URL begint met `https://`, ck/cs niet-leeg. Stores-tabel wordt cascade-ruimer.
- **Slice WC-2** — `HttpWooCommerceClient` implementatie + `WooPullHandler`. `wc:pull` CLI: fetch producten met `?per_page=100&status=any&context=edit` (laatste geeft `meta_data` terug), variations via `/products/{id}/variations`. Per page itereren tot leeg. AFAS-itemcode uit `meta_data` matchen op de configurable key per store. Snapshot-replace per store (`DELETE FROM woocommerce_products WHERE store_id = ?` + bulk-insert).
- **Slice WC-3** — `wc:index` CLI met `--missing` / `--orphan` filters. Toont per AFAS-itemcode in welke shops 'ie gepubliceerd staat, of welke AFAS-managed itemcodes geen enkele shop hebben, of welke WC-producten geen AFAS-link hebben.
- **Slice WC-4** — UI `/woocommerce`: tabs Index / Orphans / Stores. JSON-endpoints `/api/wc/index`, `/api/wc/orphans`, `/api/wc/stores`. Read-only.

Vier slices, allemaal read-only. Mutatie-cyclus volgt later.

## 8. Buiten scope (toekomstige cycli)

- **Cyclus 2 candidates**: `wc:create-missing` (POST nieuwe simple/variable + variations), `wc:update-prices` (sync AFAS-prijzen naar WC), publish/unpublish-state-sync vanuit `base_publications`-tabel (PLAN-AFAS §25).
- **Cyclus 3 candidates**: image-management, categorie-mapping, attribuut-mapping, multi-language (WPML-koppeling).
- **Onbepaald**: voorraadsync, order-sync, klant-sync — vermoedelijk nooit, want AFAS is het systeem-of-record en WC verkoopt door naar AFAS via een aparte koppeling.

## 9. Bekende risico's

- **Meta-data-formaat verschilt per plugin**: ACF, Custom Fields, WooCommerce Subscriptions, etc. kunnen `meta_data` anders structureren. Voor MVP gaan we uit van plain meta met scalar string-waarde. Edge-cases (array-waarden, JSON-encoded strings) loggen we als waarschuwing in `wc:pull` zonder te crashen.
- **Variations zonder eigen meta**: WC laat parent-meta erven, maar in onze use-case heeft elke variation een eigen AFAS-itemcode (anders kunnen we niet linken). Variations zonder de meta-key worden in de snapshot opgenomen met `afas_itemcode = NULL` en in `wc:index --orphan` zichtbaar.
- **WooCommerce REST API rate-limit**: bestaat niet standaard, maar reverse-proxies (Cloudflare) kunnen 'm afdwingen. Implementatie respecteert `Retry-After`-headers; voor MVP geen exponential backoff nodig.
- **AFAS-itemcode-hergebruik tussen shops**: één AFAS-itemcode kan op meerdere shops staan (`defibrion.nl` én `defibrion.fr` verkopen `21011`). Schema staat dat toe (`UNIQUE(store_id, wc_product_id)`, geen unique op `afas_itemcode`). Index aggregeert per `(itemcode, store)`.
- **Synchronisatie van AFAS-snapshot en WC-snapshot**: we pullen los van elkaar. UI moet voor de index gemarkeerd kunnen worden met "WC-data is van X uur geleden". Voor MVP: laat `synced_at` per rij zien.

## 10. WooCommerce health-check (slice WC-5 — concept)

### Probleem

Cyclus 1 (WC-0 t/m WC-4) gaf zichtbaarheid op de presence van AFAS-managed itemcodes in elke shop. Maar **presence alleen is niet genoeg** — de WC-plugin van Defibrion converteert AFAS-samenstellingen naar variable-product-structuren (één variable parent per family, variations voor elke base + accessoire-combinatie). Wanneer een managed itemcode in WC als `simple` belandt (i.p.v. variation onder de juiste variable parent), is dat een **bug-signaal**: de plugin heeft 'm óf nooit geconverteerd (legacy import), óf hij behandelt 'm verkeerd (Itemcode_Parent niet gezet bij AFAS-creatie → plugin valt terug op simple).

Concrete observatie tijdens debugging: na slice 53 bleven 5 bases in WC als `simple` staan (`11144`, `11154`, `11162`, `11166`, `21012`) terwijl AFAS hun `Itemcode_Parent` net was rechtgezet — de plugin loopt achter op de AFAS-state en wij hebben geen audit om dit systematisch te zien.

### Aanpak

Een dedicated `audit:wc-health`-CLI + `/woocommerce` UI-tab die per managed AFAS-itemcode (84 bases + 639 matched variants) één van vier statussen toont per geregistreerde shop:

- ✅ **OK**: aanwezig als `variable` (voor heads) OF `variation` (voor non-heads + variants), publish-status `publish`.
- ⚠️ **wrong-type**: aanwezig in WC, maar `simple` — moet variation worden.
- ⚠️ **not-publish**: aanwezig met juist type, maar status `draft`/`private`/etc.
- ❌ **missing**: helemaal niet in WC.

Status-bepaling per itemcode:
1. Lookup in `woocommerce_products` op `afas_itemcode = X` voor de shop.
2. Bepaal verwachte type:
   - Family-head (= `group_bases.afas_itemcode === group.familyHeadItemcode`) → verwacht `variable`.
   - Alle andere managed-itemcodes (non-head bases + accessoire-variants) → verwacht `variation`.
3. Vergelijk → status.

### Slices

- **Slice WC-5.0** — `WcHealthAuditHandler` + VO's. Output: `list<WcHealthRow>` met `afasItemcode`, `expectedType` (`variable`|`variation`), per shop `WcHealthCell` (`?wcProductId, ?actualType, ?status, healthStatus enum`). Hergebruik `WooProductRepository::findByAfasItemcode`. Unit-tests in `tests/Application/Woo/`.
- **Slice WC-5.1** — `audit:wc-health [--store=<name>] [--missing] [--wrong-type] [--not-publish]` CLI. Output: tabel itemcode | expected-type | per-shop status. Filter-flags voor focus op één probleem-categorie.
- **Slice WC-5.2** — UI: nieuwe tab "Health" op `/woocommerce`. Tabel met afas-itemcode + status-chips per shop (groen/oranje/rood). Filter-checkboxes voor missing/wrong-type/not-publish. JSON-endpoint `/api/wc/health`. Vitest + PHP-test.
- **Slice WC-5.3** — Live verificatie. Run `audit:wc-health --wrong-type` → toont de 5 bekende simples (`11144` etc.) plus eventueel andere. Run zonder filter → totaal overzicht. UI-tab opent met dezelfde data.

### Niet in scope

- **Auto-fix**: deze cyclus blijft read-only. De plugin moet zelf de conversie doen (gegeven correcte AFAS-data). Onze tool signaleert, fixt niet.
- **Sticker-status, prijsdrift, attribuut-validatie**: dat zijn aparte audits. Health-check focust strikt op `type`-mismatch + presence.
