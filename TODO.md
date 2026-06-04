# TODO.md — WooCommerce-koppeling

> Vorige cyclus (AFAS-management) is gearchiveerd in `TODO-AFAS.md`. Dit document tracked alleen de WooCommerce-cyclus.

Driven door `PLAN.md` §1–§9. Per CLAUDE.md: één todo per keer, TDD red-green-refactor, mark-and-move-on, per-fase commit + push.

---

## Slice WC-0 — Domein + schema + repositories

Eindbeeld: migratie `0024_woocommerce_stores_and_products.sql` is gedraaid. Domain-VO's (`WooCommerceStore`, `WooProduct`, `WooProductVariation`) + interfaces (`WooCommerceClient`, `WooCommerceStoreRepository`, `WooProductRepository`) bestaan met InMemory + Sqlite implementaties. Contract-tests dekken CRUD + queries die latere slices nodig hebben. Zie PLAN §3.

### Sub-slice WC-0.0 — Schema + migratie
- [x] Migratie `migrations/0024_woocommerce_stores_and_products.sql` aangemaakt met 2 tabellen + UNIQUE/FK/CHECK-constraints + 2 indexen. Auto-discovered door de bestaande `Migrator`; sqlite-snapshot pakt 'm bij eerste TestDatabase-init op.

### Sub-slice WC-0.1 — Domein-VO's + interfaces
- [x] `WooCommerceStore`, `WooProduct`, `WooProductVariation` als readonly VO's. `WooCommerceClient`, `WooCommerceStoreRepository`, `WooProductRepository` interfaces incl. `WooCommerceStoreNotFoundException`.

### Sub-slice WC-0.2 — InMemory + Sqlite implementaties + contract-tests
- [x] `WooCommerceStoreRepositoryContractTestCase` met 7 scenario's (save+id, custom meta-key, findByName/All, delete + cascade-effect via `findAll`, duplicate-name throws). `InMemory` + `Sqlite` impl + concrete tests beide groen.
- [x] `WooProductRepositoryContractTestCase` met 7 scenario's (replaceForStore persists, replace clears prior, andere stores ongemoeid, findByAfasItemcode cross-store, NULL-itemcode skip, empty-list-replace clears, variation behoudt parentId + type). InMemory + Sqlite impl met FK CASCADE + transactionele bulk-replace.
- [x] `make check` groen. → 506 PHP-tests / 1270 assertions + PHPStan 0 errors + CS-Fixer schoon.

---

## Slice WC-1 — Stores-registry CLI

Eindbeeld: shops kunnen via CLI worden toegevoegd, gelist, verwijderd. Cascade ruimt producten op. Zie PLAN §5.

### Sub-slice WC-1.0 — `wc:store:add` + handler
- [x] `AddWooStore` command-VO + `AddWooStoreHandler` met validatie: lege name/ck/cs → exception, non-https-url → exception, duplicate-name → exception. Trimt trailing-slash van base-url voordat 'ie opslaat.
- [x] `AddWooStoreCommand` Symfony CLI (`wc:store:add <name> <base-url> <ck> <cs> [--meta-key=_afas_itemcode]`). `InvalidWooStoreException` → `FAILURE` met `[ERROR]`-output, succes → `[OK] Store '<name>' toegevoegd (id=<n>)`.
- [x] 8 handler-tests: succes + custom meta-key + trailing-slash-trim + 5 rejection-paden.

### Sub-slice WC-1.1 — `wc:store:list` + `wc:store:remove`
- [x] `ListWooStoresCommand`: tabel `id | name | base_url | meta_key | consumer_key (eerste 6 chars + …)`. Empty-set → note.
- [x] `RemoveWooStore`-VO + `RemoveWooStoreHandler` (throwt `WooCommerceStoreNotFoundException` als naam onbekend) + `RemoveWooStoreCommand` (`wc:store:remove <name> [--force]`, confirm-prompt tenzij `--force`).
- [x] `Container` uitgebreid met `wooStoreRepository()` + `wooProductRepository()`; bin/samenstellingen registreert alle 3 wc-commands.
- [x] `make check` groen → 516 PHP-tests / 1285 assertions (+10 nieuwe) + PHPStan 0 errors + CS-Fixer schoon.
- [x] End-to-end smoke: `wc:store:add defibrion.nl https://defibrion.nl ck_demo cs_demo` → list toont gemaskerde ck → `wc:store:remove defibrion.nl --force` → list leeg. ✓

---

## Slice WC-2 — `wc:pull` + HTTP-client

Eindbeeld: één `wc:pull --store=defibrion.nl` haalt alle producten + variations binnen via REST, mapt ze (incl. AFAS-itemcode-extractie uit meta), en vervangt de snapshot voor die store. Zonder `--store` doet 'ie alle geregistreerde shops. Zie PLAN §4 + §7.

### Sub-slice WC-2.0 — `HttpWooCommerceClient` + meta-extractie
- [x] `WooMetaDataExtractor::extract()` static helper met 8 unit-tests (key gevonden, custom-key, missing-key, lege array, array-value → null, leeg-string → null, int-coercie naar string, malformed entry zonder `key` overgeslagen).
- [x] `HttpWooCommerceClient` met paginatie (`per_page=100` + `page=N`-loop tot lege page of <100-pagina). `context=edit` voor `meta_data`-veld. Filtert types `simple` + `variable` (grouped/external worden niet door ons beheerd).
- [x] `HttpWooCommerceClientFactory` bouwt per store een Guzzle-client met Basic-Auth (ck/cs) op `{baseUrl}/wp-json/wc/v3/`.
- [x] `InMemoryWooCommerceClient` als test-fake (constructor neemt vaste product- + variations-lijsten).
- [x] 4 mock-handler-tests op `HttpWooCommerceClient`: paginatie tot empty (101 items over 2 pages), filtering van unsupported types, variations-endpoint, empty-first-page returnt `[]`.

### Sub-slice WC-2.1 — `PullWooStoreHandler` + CLI
- [x] `PullWooStore` (`?string $storeName`) + `PullWooStoreResult` (`array<string, int> $itemsByStore`).
- [x] `WooCommerceClientFactory`-interface (application-layer) + `HttpWooCommerceClientFactory` (infra).
- [x] `PullWooStoreHandler`: per store fetch producten, voor elke variable-parent variations erbij, één flat lijst → `replaceForStore`. 5 unit-tests (mix simple+variable, snapshot-replace, scoped-by-name, onbekende store throwt, lege-set).
- [x] `PullWooStoreCommand` (`wc:pull [--store=<name>]`): output-tabel met store-naam + item-count + totaal. Onbekende store → `[ERROR]`, lege registry → `[NOTE]`.
- [x] Container + bin-bootstrap met factory wired. `make check` groen → 533 PHP-tests / 1319 assertions + PHPStan 0 errors + CS-Fixer schoon.

### Sub-slice WC-2.2 — Live verificatie
- [x] `reseller.defibrion.nl` geregistreerd met productie-ck/cs uit `~/projects/woocommerce-copy-translater/.env`. Meta-key: `_afas_artikelnummer` (uit `bin/export-afas-grouping` van datzelfde project).
- [x] `wc:pull --store=reseller.defibrion.nl` haalde **2577 items** binnen: 449 simple + 76 variable parents + 2052 variations. Pull duurde ~30s (4 pages × ~25 variable parents met ieder 1 variations-call = ~80 requests).
- [x] SQL-checks: 2574/2577 hebben AFAS-meta-link (99,9%); statusverdeling 969 publish / 1554 private / 54 draft. Random samples (`11652-60212`, `52113-60222`, `10299`) bevestigen correcte meta-extractie.
- [x] Kruisreferentie met onze AFAS-managed-set: 566 van 639 managed-itemcodes (89%) zijn op de shop aanwezig; 73 ontbreken; 1978 WC-items wijzen naar AFAS-itemcodes die niet in onze managed-set zitten (taal-siblings, gearchiveerd).

---

## Slice WC-3 — `wc:index` CLI

Eindbeeld: `wc:index` toont per AFAS-itemcode in welke shops 'ie gepubliceerd staat. Filters voor drift-detectie. Zie PLAN §5.

### Sub-slice WC-3.0 — Index-handler
- [ ] `ListWooIndex` command-VO (`?string $storeName`, `bool $missingOnly`, `bool $orphanOnly`) + `ListWooIndexHandler`: combineert `group_bases.afas_itemcode` + `group_variants.afas_samenstelling_itemcode` (uit AFAS-snapshot) met `woocommerce_products` (gegroepeerd per afas_itemcode × store). Output: list van `WooIndexRow` met `afasItemcode`, `afasName` (uit AFAS-snapshot), en per geregistreerde store een sub-VO `{wcProductId, wcType, status, name, permalink}` of `null` als ontbrekend.
- [ ] `--missing` filter: alleen rijen waar minstens één store-kolom `null` is. `--orphan` filter: WC-producten zonder match in onze AFAS-managed-set (separate query-pad via `WooProductRepository::findOrphansForStore`).
- [ ] Repository-uitbreidingen: `WooProductRepository::findGroupedByAfasItemcode(): array<string, list<…>>` voor de hoofd-index, `findOrphansForStore(int $storeId): list<…>` voor de orphan-mode. Contract-tests uitbreiden.

### Sub-slice WC-3.1 — `wc:index` CLI + output-formatting
- [ ] `wc:index [--store=<name>] [--missing] [--orphan]` command. Output: tabel met `Itemcode | Naam` + één kolom per store (`✓` publish, `◐` draft, `✗` ontbreekt). Bij `--orphan`: tabel met `Store | WC-id | Type | SKU | Naam | Status | Permalink`.
- [ ] Geen tests met live-data; alleen unit-tests op de handler (in-memory fixtures).
- [ ] `make check` groen.

---

## Slice WC-4 — UI `/woocommerce`

Eindbeeld: read-only React-pagina `/woocommerce` met tabs Index / Orphans / Stores. JSON-endpoints in `Interface/Http`. Strict read-only conform CLAUDE.md UI-policy.

### Sub-slice WC-4.0 — JSON-endpoints
- [ ] `ListWooStoresController` → `GET /api/wc/stores` — geeft per store `id, name, base_url, meta_key, last_synced_at` (max van `woocommerce_products.synced_at` per store).
- [ ] `ListWooIndexController` → `GET /api/wc/index` — wrapt `ListWooIndexHandler` met default params (geen filter).
- [ ] `ListWooOrphansController` → `GET /api/wc/orphans` — gebruikt `--orphan`-pad.
- [ ] PHP-tests in `tests/Interface/Http/ApiTest.php` voor alle 3 endpoints.

### Sub-slice WC-4.1 — React-pagina
- [ ] `web/src/pages/Woocommerce.tsx` met 3 tabs (MUI Tabs). Index-tab: DataGrid met itemcode + kolom-per-store (icon + tooltip). Orphans-tab: per-store gegroepeerde lijst. Stores-tab: tabel met name + last-synced.
- [ ] `web/src/api.ts` uitgebreid met types + fetch-functies.
- [ ] Vitest: per tab minimaal 1 test (fixture-data → expected rendering).
- [ ] Routing in `App.tsx` + nav-link in de hoofdmenu.

### Sub-slice WC-4.2 — Live verificatie
- [ ] Open `/woocommerce` in de UI met minstens 1 store geregistreerd + 1 succesvolle `wc:pull`. Verifieer dat: Index-tab AFAS-itemcodes toont met correcte ✓/◐/✗-status; Orphans-tab WC-producten zonder AFAS-link toont; Stores-tab last-synced-tijd correct toont.
