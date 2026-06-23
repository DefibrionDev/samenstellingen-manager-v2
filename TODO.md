# TODO.md — WooCommerce-koppeling

> Vorige cyclus (AFAS-management) is gearchiveerd in `TODO-AFAS.md`. Dit document tracked alleen de WooCommerce-cyclus.

Driven door `PLAN.md` §1–§11. Per CLAUDE.md: één todo per keer, TDD red-green-refactor, mark-and-move-on, per-fase commit + push.

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
- [x] `ListWooIndex` command-VO + `WooIndexCell` / `WooIndexRow` / `WooOrphanRow` / `WooIndexResult` value-objects.
- [x] `ListWooIndexHandler`: bouwt managed-set uit `group_bases.afas_itemcode` + `group_variants.afas_samenstelling_itemcode` (matched), itereert per geregistreerde store de WC-producten, vult cells voor managed-matches en orphan-rijen voor non-matches (NULL-meta + niet-in-onze-set beide). Sorteert rows alfabetisch op itemcode.
- [x] `--missing` filter: alleen rijen met ≥1 null-cell. `--orphan` filter wordt in de CLI gebruikt om alleen `result->orphans` te renderen.
- [x] Geen aparte repo-methoden; bestaande `findAllForStore` is genoeg en handler doet de classificatie in PHP — eenvoudiger en gegeven de scale (~2.5k items) snel genoeg.
- [x] 5 handler-tests: rows-acrosss-stores, missing-filter, orphans, store-scope, lege managed-set.

### Sub-slice WC-3.1 — `wc:index` CLI + output-formatting
- [x] `ListWooIndexCommand` met `--store=<name>`, `--missing`, `--orphan`. Default-output: tabel met itemcode + één kolom per store (`✓ id` publish, `◐ id (status)` draft/private/pending, `✗` ontbreekt). `--orphan`: tabel met `Store | WC-id | Type | SKU | Naam | Status | AFAS-meta`. Lege resultaten → succes-notice.
- [x] Container/bin-bootstrap wire-up. `make check` groen → 538 PHP-tests / 1365 assertions + PHPStan 0 errors + CS-Fixer schoon.
- [x] Live verificatie op `reseller.defibrion.nl`: `--missing` toont **73 ontbrekende managed-itemcodes** (Defibtech VIEW, Lifeline, Reanibex varianten); `--orphan` toont **1981 orphans** (1978 niet-managed AFAS-links + 3 zonder meta) — sluit aan op de WC-2.2-tellingen.

---

## Slice WC-4 — UI `/woocommerce`

Eindbeeld: read-only React-pagina `/woocommerce` met tabs Index / Orphans / Stores. JSON-endpoints in `Interface/Http`. Strict read-only conform CLAUDE.md UI-policy.

### Sub-slice WC-4.0 — JSON-endpoints
- [x] `ListWooStoresController` → `GET /api/wc/stores`: `[{id, name, baseUrl, metaKey, itemCount}]`. `itemCount` = aantal rijen in `woocommerce_products` voor die store (handiger dan `last_synced_at` voor de UI).
- [x] `ListWooIndexController` → `GET /api/wc/index`: response shape `{stores: [{id, name}], rows: [{afasItemcode, cells: [{storeId, storeName, cell|null}]}]}`. Query-params `?store=...` + `?missing=1`.
- [x] `ListWooOrphansController` → `GET /api/wc/orphans`: flat list met store + WC-product-details + (optional) afasItemcode.
- [x] PHP-test `exposesWooEndpoints` in `ApiTest.php`: seed 1 group + 1 store + 2 products (1 managed-match + 1 orphan) → 3 endpoints geven verwachte payloads. State-cleanup via `DELETE FROM woocommerce_products/stores` aan de start.
- [x] Container + AppFactory wired-up; 3 routes onder `/api/wc/`.

### Sub-slice WC-4.1 — React-pagina
- [x] `web/src/pages/Woocommerce.tsx` met 3 MUI Tabs (Index / Orphans / Stores). Index: MUI Table met sticky header, itemcode + één kolom per store; `<StatusChip>`-component voor cell-rendering (✓ groen / ◐ oranje / − grijs + tooltip met wc-id + naam). Orphans: tabel met clickable WC-id (link naar permalink), Naam, Status, AFAS-meta (chip "geen meta" als null). Stores: name + base-url-link + meta-key + items-in-snapshot.
- [x] `web/src/api.ts` uitgebreid met `WooStore`, `WooIndexCell`, `WooIndexCellEntry`, `WooIndexRow`, `WooIndexResponse`, `WooOrphan` types + `listWooStores/Index/Orphans` fetch-functies.
- [x] Vitest in `web/src/pages/Woocommerce.test.tsx`: 3 tests (Index-tab toont itemcode + store-kolommen, Orphans-tab toont WC-producten zonder match, Stores-tab toont snapshot-aantal). Fetch wordt gemocked per URL.
- [x] Routing in `main.tsx` + nav-link in `App.tsx` onder Settings.
- [x] `make check` groen → 539 PHP-tests / 1378 assertions + 21 vitest-tests (incl. 3 nieuwe) + PHPStan 0 errors + CS-Fixer schoon.

### Sub-slice WC-4.2 — Live verificatie
- [x] `/woocommerce` in Chrome geopend. Alle drie tabs werken met live data van `reseller.defibrion.nl`:
  - Index: AFAS-itemcodes (b.v. `064.1308-SAM-DE`, `064.1308-SAM-DE-60110`) met ✓-icon in de `reseller.defibrion.nl`-kolom.
  - Orphans: WC-producten zoals PRESTAN-Instructor-Kit (`PP-INSTRKIT-VAR`), `PP-FMP-300M-VAR` + variations — clickable WC-id-links, status-mix draft/private.
  - Stores: `reseller.defibrion.nl | https://reseller.defibrion.nl | _afas_artikelnummer | 2577` items.

---

## Slice WC-5 — WooCommerce health-check (managed itemcodes ↔ verwachte WC-type)

Eindbeeld: `audit:wc-health` toont per managed AFAS-itemcode in welke shop 'ie staat én of het correcte WC-type wordt gebruikt. Family-head → moet `variable` zijn; non-head bases + accessoire-variants → moeten `variation` zijn onder de juiste parent. Mismatches (b.v. `simple` waar `variation` verwacht) krijgen een gele waarschuwing; afwezigheid een rode. UI-tab "Health" op `/woocommerce` toont dezelfde data per shop-kolom. Zie PLAN.md §10.

Aanleiding: na slice 53 stonden er nog 5 managed bases (`11144`, `11154`, `11162`, `11166`, `21012`) als `simple` in WC — terwijl onze tool ze als variation onder family-heads `11197`/`21019` verwacht. Geen audit-flow → ontdek je 't pas bij toeval. Health-check vult dat gat.

### Sub-slice WC-5.0 — Handler + VO's
- [x] `AuditWcHealth` + `WcHealthRow` (`afasItemcode`, `expectedType: 'variable'|'variation'`, `cellsByStore: array<int, WcHealthCell>`).
- [x] `WcHealthCell` (`wcProductId: ?int`, `actualType: ?string`, `status: ?string`, `healthStatus: enum WcHealthStatus { Ok, WrongType, NotPublish, Missing }`).
- [x] `WcHealthAuditHandler`: family-heads → variable, rest → variation. Pre-loads producten per store + groepeert op afas_itemcode voor O(1)-lookup. Bij meerdere hits prioriteert match op verwacht type (anders eerste hit).
- [x] 6 handler-tests: head als variable+publish → Ok; matched variant als variation → Ok; non-head als simple → WrongType; missing → Missing; head als draft → NotPublish; store-filter respect.

### Sub-slice WC-5.1 — `audit:wc-health` CLI
- [x] `AuditWcHealthCommand` (`audit:wc-health [--store=<name>] [--missing] [--wrong-type] [--not-publish]`). Filter-flags OR-combineren.
- [x] Cel-rendering: `✓ {id}`, `⚠ {actualType}:{id}`, `◐ {id} ({status})`, `✗`. Bij `--store=...` één kolom; default alle stores.
- [x] Container/bin-wiring (audit-pad buiten creds-block).

### Sub-slice WC-5.2 — UI-tab "Health" + JSON-endpoint
- [x] `ListWcHealthController` op `GET /api/wc/health` met optionele `?store=<name>`. Response: `{ stores, rows: [{afasItemcode, expectedType, cells: [...]}] }`.
- [x] AppFactory wiring; PHP-test in `ApiTest::exposesWooEndpoints` uitgebreid (asserties op de fixture-rij `11111` → wrong-type/simple).
- [x] React: extra tab "Health" op `/woocommerce`. `<HealthCellChip>`-component met 4 status-renderingen (groen Ok, oranje wrong-type, oranje-outlined not-publish, grijze missing).
- [x] Filter-chip-rij bovenaan (`all` / `wrong-type` / `not-publish` / `missing`) — default `wrong-type` zoals user-relevantie. Teller `X / Y itemcodes`.
- [x] `web/src/api.ts` uitgebreid met `WcHealthStatus`/`WcHealthCellEntry`/`WcHealthRow`/`WcHealthResponse` + `listWcHealth` fetcher.
- [x] Vitest "Health-tab toont wrong-type chip" toegevoegd. `make check` + vitest groen → 564 PHP-tests / 1489 assertions + 22 vitest + PHPStan 0 errors.

### Sub-slice WC-5.3 — Live verificatie
- [x] `wc:pull --store=reseller.defibrion.nl` ververst snapshot.
- [x] `audit:wc-health --wrong-type` toonde **3 rijen** (plugin had de Lifepak CR2-vol cases al opgelost tussen pulls):
  - `11043` (Defibtech Lifeline VIEW semi head) → variation #4871 (verwacht variable)
  - `11043-91116` (variant) → simple #5495 (verwacht variation)
  - `21019` (Mindray C1 vol head) → variation #979 (verwacht variable)
- [x] UI: `/woocommerce` → tab "Health" toont 3 oranje chips in de reseller.defibrion.nl-kolom; teller `3 / 639 itemcodes`. Filter-chips wisselen werkt.

---

## Slice NM — No_match-audit-pagina

Eindbeeld: een aparte, read-only audit die **álle** `no_match`-varianten toont (groep, base, accessoire, verwachte BOM-itemcodes), met per rij of er al een AFAS-compositie met de verwachte itemcode bestaat (en welke), plus — indien aanwezig — een compositie met exact de juiste BOM. Surfaces: CLI + JSON-endpoint + web-UI-pagina. Raakt de bestaande missing-variants-audit en `fix-missing` niet. Zie PLAN.md §11.

Aanleiding: de bestaande missing-variants-audit verbergt no_match-rijen zodra een samenstelling met de voorgestelde itemcode al bestaat (`ListMissingVariantsHandler` regel 57). Daardoor zie je niet welke no_match-varianten eigenlijk al een (afwijkende) compositie in AFAS hebben — bv. `21012-FR-60110` (no_match, maar de samenstelling bestaat; diens BOM mist component `81211`).

### Sub-slice NM-0 — Handler + row-DTO
- [x] `ListNoMatchVariants` command-VO + `NoMatchVariantRow` DTO (`groep`, `familyHead`, `baseNaam`, `baseAfasSku`, `accessoireItemcode`, `accessoireLabel`, `verwachteBom: list<string>`, `verwachteItemcode`, `bestaandeAfasItemcode: ?string`, `exacteBomMatchItemcode: ?string`).
- [x] `ListNoMatchVariantsHandler`: itereert alle groepen → variants → houdt `afas_status === 'no_match'` met een afleidbare verwachte BOM. Bepaalt per rij de verwachte itemcode (base-afas-sku + accessoire) en zet `bestaandeAfasItemcode` via `AfasSamenstellingenRepository::findByItemcode`; zet `exacteBomMatchItemcode` via een BOM-key-index over álle samenstellingen (canonical wint van duplicaten). Base-afas-sku afgeleid uit de matched base-variant van dezelfde base (zelfde aanpak als de missing-variants-audit).
- [x] Unit-tests (in-memory AFAS-snapshot): no_match met bestaande verwachte itemcode → rij met `bestaandeAfasItemcode` gevuld; no_match zonder → null; `matched`-variant verschijnt niet; compositie met exact de juiste BOM → `exacteBomMatchItemcode` gevuld.
- [x] BOM-verschil t.o.v. de bestaande compositie: `ontbrekendeItemcodes` ("mist") + `extraItemcodes` ("teveel") op de DTO; handler vult ze via set-diff tegen `bestaandeAfasItemcode`'s BOM. (Op verzoek: tonen welke itemcodes missen/teveel zijn.)

### Sub-slice NM-1 — CLI + JSON-endpoint
- [x] `AuditNoMatchVariantsCommand` (`audit:no-match`): tabel groep | base | accessoire | verwachte_bom | bestaat_in_afas | mist | teveel. Lege set → succes-notice; note telt rijen + exacte-BOM-duplicaten.
- [x] `ListNoMatchVariantsController` → `GET /api/wc/no-match` (JSON: alle DTO-velden incl. `ontbrekendeItemcodes`/`extraItemcodes`). AppFactory + bin-bootstrap wiring.
- [x] PHP-tests: ApiTest-endpoint (drift-rij met `bestaandeAfasItemcode` + `mist`) + CLI-test (tabel toont bestaande itemcode + "teveel"-code).

### Sub-slice NM-2 — Web-UI-pagina
- [x] React-pagina `NoMatchVariants.tsx` (route `/no-match` + nav-link onder Audits): DataGrid met groep, base, accessoire, verwachte BOM, "bestaat in AFAS", "mist", "teveel".
- [x] `web/src/api.ts` `NoMatchVariantRow` type + `listNoMatchVariants` fetcher.
- [x] Vitest: pagina rendert de no_match-rij + bestaande itemcode + ontbrekende itemcode. (Tevens een pre-existing `tsc`-fout in `ProductTypeIssues.test.tsx` opgeruimd zodat `tsc -b` weer groen is.)

### Sub-slice NM-3 — Live verificatie
- [x] CLI + HTTP-endpoint tegen de echte snapshot: **87 no_match-varianten** shop-breed; `21012-FR-60110` / `21012-FR-60223` tonen `bestaat_in_afas = 21012-FR-60110/-60223` met `mist = 81211`, `teveel = —`.
- [x] UI-pagina `/no-match` geopend: rendert de 87 rijen incl. de 21012-FR-cases (mist 81211) én een echte "teveel"-case (Defibtech: mist 70112, teveel 10788).
