# TODO — samenstellingen-manager

Per todo geldt TDD red-green-refactor; dat hoeft niet als aparte stap te staan. Per slice eindigt het werk met een handmatige verificatie en een commit + push (zie CLAUDE.md).

---

## Slice 1 — groep definiëren via CLI ✅

Eindbeeld bereikt: `group:create <name> <family-head-itemcode>` legt een groep vast in lokale SQLite. Geen AFAS. Domain → application → infrastructure → interface gescheiden, gedeelde repository-contract-tests voor in-memory en SQLite.

### Fase 0 — Project scaffolding ✅
- [x] `composer.json` met PHP 8.5+ requirement, PSR-4 autoload voor `Defibrion\Samenstellingen\` → `src/`, dev deps: `phpunit/phpunit`, `phpstan/phpstan`, `friendsofphp/php-cs-fixer` (of `squizlabs/php_codesniffer`).
- [x] Mappenstructuur: `src/`, `tests/`, `bin/`, `migrations/`, `config/`, `tmp/`.
- [x] `.gitignore`: `vendor/`, `tmp/`, `*.sqlite`, `.env`, `.phpunit.result.cache`.
- [x] `phpunit.xml` met twee suites (`unit`, `integration`) en bootstrap in `tests/bootstrap.php`.
- [x] `phpstan.neon` op level 8, scant `src/` en `tests/`.
- [x] `Makefile` met targets `test`, `lint`, `stan`, `check`, `help`.
- [x] `.env.example` met `SAMENSTELLINGEN_DB_PATH`.
- [x] Leeg `bin/samenstellingen` PHP-entry-script met shebang en sanity-output, chmod +x.
- [x] `make check` is groen op het lege project.
- [x] **Commit + push** "fase 0: scaffolding".

### Fase 1 — Domein: `Group` value object ✅
- [x] `Group` readonly class in `src/Domain/Group/Group.php` met `name` en `familyHeadItemcode`, beide niet-leeg (whitespace telt als leeg), getrimd. Validatie in de constructor met `InvalidArgumentException`.

### Fase 2 — Repository-contract + in-memory fake ✅
- [x] `GroupRepository` interface in `src/Domain/Group/` met `save(Group $g): void` en `findByName(string $name): ?Group`.
- [x] `GroupAlreadyExistsException` in `src/Domain/Group/`, gegooid bij een duplicate naam.
- [x] `InMemoryGroupRepository` in `src/Infrastructure/Persistence/InMemory/`, met round-trip-gedrag en duplicate-detectie.

### Fase 3 — SQLite-repository ✅
- [x] Migratiebestand `migrations/0001_create_groups.sql` met de `groups`-tabel uit PLAN.md §3 (`naming_template` weglaten voor nu).
- [x] Kleine migrator in `src/Infrastructure/Persistence/Sqlite/Migrator.php` die SQL-bestanden in volgorde toepast.
- [x] `SqliteGroupRepository implements GroupRepository`, geparametriseerd op een PDO-instance, met dezelfde gedragsgaranties als de in-memory variant.
- [x] `tests/bootstrap.php` migreert de test-database één keer per phpunit-process; tests cleanen rijen tussen runs, geen re-migrate.
- [x] Gedeelde abstracte testbasis zodat beide repository-implementaties dezelfde contract-tests draaien.

### Fase 4 — Application: create-group use case ✅
- [x] `CreateGroup` command-DTO in `src/Application/Group/` met `name` en `familyHeadItemcode`.
- [x] `CreateGroupHandler` in `src/Application/Group/` met `GroupRepository` via de constructor; legt een `Group` vast en retourneert hem; laat `GroupAlreadyExistsException` doorgaan.

### Fase 5 — CLI: het create-group commando ✅
- [x] `symfony/console` toevoegen aan `composer.json`.
- [x] `CreateGroupCommand` in `src/Interface/Cli/` (commando `group:create <name> <family-head-itemcode>`) die `CreateGroupHandler` aanroept; vangt `GroupAlreadyExistsException` op en rendert "Groep '<naam>' bestaat al" met niet-nul exit code.
- [x] `bin/samenstellingen` als dunne bootstrap: één gedeelde PDO, repo, handler, command — geregistreerd in de Symfony Console application.
- [x] **Handmatige verificatie**: `bin/samenstellingen group:create "Reanibex 100 Semi-Auto" 52112` tegen een echt lokaal SQLite-bestand, dan opnieuw uitvoeren voor de duplicate-melding. Inspecteer met `sqlite3`.
- [x] `make check` is groen.
- [x] **Commit + push** "slice 1: groep definiëren via CLI".

---

## Slice 2 — groep tonen via CLI

Eindbeeld: een CLI-commando neemt een groepsnaam en print de groep-details (naam + family-head itemcode). Niet-bestaande groep → exit code 1 met duidelijke melding. Reads gaan via de application-laag voor consistente architectuur. Slice 3 breidt de output uit met bases en accessoires.

### Fase 1 — Domein: not-found exception
- [x] `GroupNotFoundException` in `src/Domain/Group/`, gegooid wanneer een query op naam niets oplevert.

### Fase 2 — Application: show-group use case
- [x] `ShowGroup` query-DTO in `src/Application/Group/` met `name`.
- [x] `ShowGroupHandler` in `src/Application/Group/` met `GroupRepository` via constructor; retourneert `Group` of gooit `GroupNotFoundException`.

### Fase 3 — CLI: het show-group commando
- [x] `ShowGroupCommand` in `src/Interface/Cli/` (commando `group:show <name>`) die `ShowGroupHandler` aanroept; rendert naam + family-head itemcode als horizontale tabel via `SymfonyStyle`. Vangt `GroupNotFoundException` op en rendert "Groep '<naam>' niet gevonden" + exit code 1.
- [x] `bin/samenstellingen` registreert het nieuwe commando.

### Fase 4 — Handmatige verificatie + commit
- [x] `bin/samenstellingen group:show "Reanibex 100 Semi-Auto"` toont de groep; `... group:show "Onbekend"` geeft foutmelding + exit code 1.
- [x] `make check` is groen.
- [x] **Commit + push** "slice 2: groep tonen via CLI".

---

## Slice 3 — bases (1:N) en accessoires (M:N catalogus) beheren via CLI

Eindbeeld: na `group:create` kun je met `group:add-base` taal-specifieke bases aan een groep toevoegen, met `accessoire:create` losse accessoires in de catalogus zetten, en met `group:add-accessoire` accessoires aan groepen koppelen. `group:show` toont vervolgens beide.

Design-keuzes (zie ook §Beslissingen):
- **Bases** zijn 1:N — taal+model-specifiek per groep. `GroupBase` value object met `itemcode`, `languageCode`, `name`.
- **Accessoires** zijn M:N — een eigen catalogus + join-tabel. `Accessoire` value object met `itemcode`, `label`. Eén catalogusrij wordt aan meerdere groepen gelinkt.
- Foreign keys gebruiken surrogate `group_id` / `accessoire_id` (INTEGER). De repository-API blijft op `string $groupName` / `string $itemcode` — SQLite-impl vertaalt naar id.
- Aparte CLI-stap voor catalogus (`accessoire:create`) en koppeling (`group:add-accessoire`).

### Fase 1 — Migraties
- [x] `migrations/0002_create_group_bases.sql`: tabel `group_bases (group_id, itemcode, language_code, name)`, PK `(group_id, itemcode)`, FK `group_id → groups(id) ON DELETE CASCADE`.
- [x] `migrations/0003_create_accessoires.sql`: catalogustabel `accessoires (id PK AUTOINCREMENT, itemcode UNIQUE, label)`.
- [x] `migrations/0004_create_group_accessoires.sql`: join-tabel `group_accessoires (group_id, accessoire_id)`, PK `(group_id, accessoire_id)`, beide FK's `ON DELETE CASCADE`.

### Fase 2 — Domein
- [x] `GroupBase` readonly value object in `src/Domain/Group/` met `itemcode`, `languageCode`, `name` — alle drie niet-leeg, getrimd.
- [x] `Accessoire` readonly value object in `src/Domain/Accessoire/` met `itemcode` en `label` — beide niet-leeg, getrimd.
- [x] Exceptions:
  - `BaseAlreadyExistsException` in `src/Domain/Group/`.
  - `AccessoireAlreadyExistsException` in `src/Domain/Accessoire/`.
  - `AccessoireNotFoundException` in `src/Domain/Accessoire/`.
  - `AccessoireAlreadyLinkedException` in `src/Domain/Group/`.

### Fase 3 — Repository-contracten + in-memory fakes
- [x] `GroupBaseRepository` interface in `src/Domain/Group/`.
- [x] `AccessoireRepository` interface in `src/Domain/Accessoire/`.
- [x] `GroupAccessoireRepository` interface in `src/Domain/Group/`.
- [x] In-memory implementaties voor alle drie.
- [x] Gedeelde abstracte contract-testbases per repo-type.

### Fase 4 — SQLite-implementaties
- [x] `SqliteGroupBaseRepository`, `SqliteAccessoireRepository`, `SqliteGroupAccessoireRepository`.
- [x] `TestDatabase::truncate()` schrapt ook `group_accessoires`, `accessoires`, `group_bases`.

### Fase 5 — Application use cases
- [x] `AddBaseToGroup` + `AddBaseToGroupHandler`.
- [x] `CreateAccessoire` + `CreateAccessoireHandler`.
- [x] `AddAccessoireToGroup` + `AddAccessoireToGroupHandler`.

### Fase 6 — CLI commando's
- [x] `AddBaseCommand` — `group:add-base`.
- [x] `CreateAccessoireCommand` — `accessoire:create`.
- [x] `AddAccessoireCommand` — `group:add-accessoire`.
- [x] Alle drie mappen domein-excepties op duidelijke meldingen + exit code 1.
- [x] `bin/samenstellingen` registreert alle drie.

### Fase 7 — Show-output uitbreiden
- [x] `ShowGroupHandler` retourneert nu een `GroupOverview` read-model (groep + bases + gelinkte accessoires).
- [x] `ShowGroupCommand` rendert basistabel + bases-tabel + accessoires-tabel. Lege secties tonen `(geen bases)` / `(geen accessoires)`.

### Fase 8 — Handmatige verificatie + commit
- [x] `group:add-base` werkt; duplicate → exit 1; onbekende groep → exit 1.
- [x] `accessoire:create` werkt; duplicate → exit 1.
- [x] `group:add-accessoire` werkt; onbekende accessoire → exit 1; dubbele link → exit 1.
- [x] `group:show` toont groep + bases + accessoires. M:N geverifieerd: dezelfde accessoire 60112 aan twee groepen gekoppeld.
- [x] `make check` is groen (73 tests / 147 assertions).
- [x] **Commit + push** "slice 3: bases en accessoires beheren via CLI".

---

## Slice 4 — variantmatrix automatisch genereren en tonen

Eindbeeld: bij elke `group:add-base` en `group:add-accessoire` worden de relevante variant-rijen in een nieuwe `group_variants`-tabel aangemaakt. `group:show` toont de variantmatrix als extra sectie. AFAS-mapping (samenstelling-SKU, status) blijft voor later, maar de kolommen daarvoor zijn al gereserveerd.

Design-keuzes:
- Variant-rij = combinatie van één base met optioneel één accessoire. `accessoire_id = NULL` betekent base-only.
- Auto-regenerate: handlers voor add-base en add-accessoire roepen na succesvolle save de variant-regenerator aan. Geen apart CLI-commando.
- Idempotent: regenerate insert ontbrekende combinaties, laat bestaande staan (inclusief al toegekende AFAS-data).
- Hard CASCADE: variant verdwijnt automatisch met zijn base of accessoire. Eventuele AFAS-koppeling gaat dan ook verloren.

### Fase 1 — Migratie
- [x] `migrations/0005_create_group_variants.sql` met composite-FK naar `group_bases` en partial unique index voor base-only rijen.

### Fase 2 — Domein
- [x] `GroupVariant` readonly value object met denormalisatie-velden (baseLanguageCode, baseName, accessoireLabel).
- [x] `GroupVariantRepository` interface: `regenerateForGroup`, `findAllForGroup`.

### Fase 3 — Repository-implementaties + contract-tests
- [x] `InMemoryGroupVariantRepository` met cartesisch-product-berekening.
- [x] `SqliteGroupVariantRepository` met `INSERT OR IGNORE` + JOIN-denormalisatie + orphan-cleanup.
- [x] Gedeelde contract-testbasis (idempotentie, lege groep, volledige 6-variant matrix, GroupNotFound).

### Fase 4 — Application: variants integreren in bestaande handlers
- [x] `AddBaseToGroupHandler` roept `regenerateForGroup()` na save aan.
- [x] `AddAccessoireToGroupHandler` idem.
- [x] Tests uitgebreid: variant-rijen aanwezig na elke add.

### Fase 5 — Show-output uitbreiden
- [x] `GroupOverview` heeft `variants` veld.
- [x] `ShowGroupHandler` haalt variants op.
- [x] `ShowGroupCommand` toont "Varianten"-sectie met 6-koloms tabel + `(nog niet bekend)` placeholder voor AFAS-SKU.

### Fase 6 — Handmatige verificatie + commit
- [x] End-to-end demo: 2 bases × (geen + 2 accessoires) = 6 varianten geproduceerd zonder expliciete regenerate-stap.
- [x] `make check` groen (85 tests / 170 assertions).
- [x] **Commit + push** "slice 4: variantmatrix automatisch genereren en tonen".

---

## Slice 5 — schema-refactor + verwachte BOM lokaal modelleren

Eindbeeld: één consistent ID-gebaseerd model. Groepen worden gelookupt via hun `family_head_itemcode` (UNIQUE), bases via een surrogate `id`, accessoires via hun `itemcode` (UNIQUE). Bases hebben geen `itemcode`-anker en geen `language_code` meer — alleen `name`. Alle items van een base (incl. de AED) staan als gelijkwaardige rijen in een nieuwe `group_base_items` tabel. `group:show` toont per variant de volledige verwachte BOM uit lokale data.

Design-keuzes (zie ook §Beslissingen):
- Refactor van slice 3 en 4 schema. Demo-data gaat verloren.
- Groep-lookups via `family_head_itemcode`. UNIQUE-constraint toegevoegd.
- Bases hebben geen taal-veld of itemcode-anker meer. Identifier = surrogate `id`.
- AED is gewoon één van de items in `group_base_items` — geen special-case.
- BOM per variant is afgeleid bij weergave uit: `group_base_items` + (optioneel) accessoire.

### Fase 1 — Schema refactor
- [x] `migrations/0006_refactor_schema.sql` — herbouwt groups (UNIQUE family-head), drop+create group_bases (id PK, geen itemcode/language), creëert group_base_items, herbouwt group_variants (base_id FK).

### Fase 2 — Domein refactor
- [x] `GroupBase` → `(?id, name)`.
- [x] `GroupBaseItem` nieuw.
- [x] `GroupVariant` op `baseId`.
- [x] Excepties: `BaseNotFoundException`, `BaseItemAlreadyExistsException`; `GroupAlreadyExistsException` + `GroupNotFoundException` krijgen factory voor family-head itemcode.

### Fase 3 — Repositories refactor
- [x] Alle 6 repositories + in-memory + sqlite implementaties.
- [x] Contract-testbases voor 5 repository-types (Group, GroupBase, GroupBaseItem, GroupAccessoire, GroupVariant) draaien tegen InMemory en SQLite.

### Fase 4 — Application refactor
- [x] DTOs en handlers op family-head itemcode (writes/reads) en base-id (base-items).

### Fase 5 — CLI refactor
- [x] `group:show <family-head-itemcode>`.
- [x] `group:add-base <family-head-itemcode> <name>` toont base-id na succes.
- [x] `group:add-base-item <base-id> <itemcode> <name>`.
- [x] `group:add-accessoire <family-head-itemcode> <accessoire-itemcode>`.
- [x] `bin/samenstellingen` bedraad alle handlers/commands.

### Fase 6 — Verwachte BOM in `group:show`
- [x] `BomItem` + `GroupVariantWithBom` read-modellen.
- [x] `ShowGroupHandler` bouwt per variant de BOM (group_base_items + optioneel accessoire).
- [x] `ShowGroupCommand` rendert per variant een sub-tabel + AFAS-SKU regel.

### Fase 7 — Handmatige verificatie + commit
- [x] End-to-end demo opgebouwd: 1 groep, 2 bases (NL + FR), elk 5 items, 2 accessoires.
- [x] 6 varianten elk met hun complete BOM zichtbaar in `group:show 52112`.
- [x] `make check` groen (108 tests / 214 assertions).
- [x] **Commit + push** "slice 5: schema-refactor + verwachte BOM lokaal modelleren".

---

## Slice 6 — AFAS-matching: koppel lokale varianten aan echte AFAS-samenstellingen

Eindbeeld: `group:sync-afas <family-head>` haalt alle AFAS samenstellingen onder dit Itemcode_Parent op, vergelijkt hun BOMs met onze lokale verwachte BOMs per variant, en zet per variant:
- **Match gevonden**: `afas_samenstelling_itemcode` = AFAS-SKU, `afas_status` = `matched`.
- **Geen match**: `afas_status` = `no_match`, SKU blijft NULL.
- **Meerdere matches**: harde error (zou bij goede AFAS-staat nooit mogen gebeuren).

Design-keuzes:
- Echte AFAS HTTP-client in deze slice. `guzzlehttp/guzzle` als dependency.
- Match-criterium: BOM van AFAS-samenstelling = exact dezelfde set itemcodes als de lokale variant's verwachte BOM (set-gelijkheid, quantity wordt genegeerd).
- AFAS-data wordt niet lokaal gecached: elke sync queryt AFAS fresh.
- `AfasSamenstellingenFetcher` interface zodat tests in-memory kunnen draaien zonder credentials.

### Fase 1 — Composer + env
- [x] `composer require guzzlehttp/guzzle`.
- [x] `.env.example` uitbreiden met `AFAS_BASE_URL` en `AFAS_TOKEN`.

### Fase 2 — Domein
- [x] `AfasSamenstelling` readonly value object.
- [x] `AmbiguousMatchException`.
- [x] `VariantMatcher` domain service.

### Fase 3 — Fetcher abstractie + fake
- [x] `AfasSamenstellingenFetcher` interface.
- [x] `InMemoryAfasSamenstellingenFetcher` voor tests.

### Fase 4 — HTTP-implementatie
- [x] `AfasHttpClient` met Guzzle.
- [x] `HttpAfasSamenstellingenFetcher` via Get_Artikelen + easylinq_stock_item + easylinq_stock_item_parts.
- [x] `AfasClientFactory::fromEnv()`.

### Fase 5 — Repository-uitbreiding
- [x] `GroupVariant.afasStatus` veld.
- [x] `GroupVariantRepository.markMatched` / `markNoMatch`.
- [x] In-memory + SQLite implementaties.

### Fase 6 — Application
- [x] `SyncGroupAgainstAfas` + handler retourneert `SyncSummary`.

### Fase 7 — CLI
- [x] `SyncGroupAgainstAfasCommand` (`group:sync-afas`).
- [x] Commando registreert zich alleen als AFAS-credentials in env staan.
- [x] `bin/samenstellingen` bedraadt fetcher + handler.

### Fase 8 — Show-output uitbreiden
- [x] `ShowGroupCommand` toont per variant status `✓ matched` / `✗ no_match` / `— niet gecheckt`.

### Fase 9 — Handmatige verificatie + commit
- [x] `make check` groen (114 tests / 229 assertions).
- [x] Refactor naar aparte `afas:pull` + lokale snapshot-tabellen (`afas_samenstellingen`, `afas_samenstelling_bom`); geen `Itemcode_Parent`-filter meer.
- [x] Live tegen echte AFAS: `afas:pull` haalde 1894 samenstellingen op; `group:sync-afas 52112` vond AFAS-SKU `52112` voor de NL-base variant.
- [x] `group:show 52112` toont `AFAS: ✓ matched (52112)`.
- [x] **Commit + push** "slice 6: AFAS-matching via lokale snapshot".

---

## Slice 7 — bulk-import bases + accessoires uit reanibex-CSV

Eindbeeld: `group:import-csv <csv-file> <family-head>` leest een CSV met `(samenstelling_itemcode, samenstelling_naam, aed_article, aed_article_naam)` rijen en:
- Per unieke `aed_article` één base aanmaken (44 voor reanibex-semi-with-safeset.csv) met naam uit de base-only-rij.
- Per uniek accessoire-suffix uit variant-SKUs (`52124-60110` → `60110`): catalogus-entry + link met groep.
- Idempotent: herhalen voegt niets dubbel toe, slaat bestaande over.

Design-keuzes:
- Geen BOM auto-fill in deze slice (komt later via AFAS-snapshot-overname).
- Accessoire-label uit `samenstelling_naam` parsen na " avec " / " + " / " with "; mislukt parsing → placeholder `"Accessoire <itemcode>"`.
- Bestaande groep nodig — `group:create` moet vooraf gerund zijn voor het family-head.

### Fase 1 — Domein
- [x] `CsvSamenstellingenRow` value object.
- [x] `CsvSamenstellingenReader` interface + `FileCsvSamenstellingenReader`.

### Fase 2 — Application
- [x] `ImportSamenstellingenCsv` + handler: bases per `aed_article`, accessoires uit variant-suffixes. Labels parsed via " avec " / " + " / " with " / " incl. ".
- [x] `ImportSummary` DTO met counts.
- [x] Idempotent (skip-counters bij her-run).

### Fase 3 — CLI
- [x] `ImportSamenstellingenCsvCommand` — `group:import-csv`.
- [x] `bin/samenstellingen` bedraadt het commando.
- [x] Safety in `SyncGroupAgainstAfasHandler`: lege expectedBom → meteen `no_match` (voorkomt ambigu-error tegen AFAS-rijen met lege BOM).

### Fase 4 — Handmatige verificatie + commit
- [x] `group:create "Reanibex 100 Semi-Auto" 52112`.
- [x] `group:import-csv reanibex-semi-with-safeset.csv 52112` → 44 bases + 7 accessoires + 352 varianten (BOMs leeg).
- [x] `group:show 52112` toont volledige matrix.
- [x] `afas:pull` (1894 samenstellingen) + `group:sync-afas 52112`: 0 matches verwacht zolang base-items leeg zijn — slice 8 (BOM auto-fill uit AFAS-snapshot) vult dit aan.
- [x] `make check` groen (119 tests / 248 assertions).
- [x] **Commit + push** "slice 7: bulk-import bases en accessoires uit CSV".

---

## Slice 8 — bulk-import portal-CSV met AFAS-BOM auto-fill + duplicate-detectie

Eindbeeld: `group:import-portal-csv <csv-file>` leest de "AED's portalen" CSV (kolommen `Code, Groep, Item, …`) en bouwt per CSV-rij de complete tool-state op uit de AFAS-snapshot. Daarbij detecteert `afas:pull` voortaan ook duplicates in AFAS (samenstellingen met identieke BOM) en markeert één canonical per groep.

Werkverdeling per CSV-rij:
1. Groep aanmaken (op naam uit `Groep`-kolom) als die nog niet bestaat.
2. Find AFAS-samenstellingen (**alleen canonicals**, geen base-only met `-` in SKU) waarvan de BOM het CSV-artikel bevat.
3. Voor elke gevonden canonical: een base aanmaken in de groep met de samenstelling-naam + alle BOM-items.

Design-keuzes:
- Duplicate-detectie bij `afas:pull`: groepeer samenstellingen op hun BOM-set; in elke set met ≥2 leden wordt de **laagste itemcode** canonical. De rest krijgt `duplicate_of_itemcode = <canonical>`.
- Matcher en import gebruiken alleen canonicals (filtert duplicates eruit).
- Per groep: `family_head_itemcode` = AFAS `Itemcode_Parent` van de eerste gevonden samenstelling in die groep (consistent met PLAN.md "willekeurige sibling als anker").
- DB wordt vooraf leeggegooid: `afas:pull` blijft, maar groups/bases/accessoires/variants worden gewist.

### Fase 1 — Migratie + duplicate-detectie
- [x] `migrations/0008_afas_duplicates.sql`.
- [x] `DuplicateDetector` domain service (gedeeld door InMemory + Sqlite repo); markeert canonical = laagste itemcode per identieke BOM-set.
- [x] `AfasSamenstelling` value object: extra veld `duplicateOfItemcode`, helper `isCanonical()` + `bomKey()`.
- [x] Hotfix: `AfasSamenstelling`-constructor cast itemcodes naar string voor sort (PHP-array-keys converteerden numerieke strings naar int).

### Fase 2 — Canonical-only lookups
- [x] `findAllCanonical()` + `findAllDuplicates()` op repo (InMemory + Sqlite).
- [x] `SyncGroupAgainstAfasHandler` matcht alleen tegen canonicals.

### Fase 3 — AfasSamenstellingLookup service
- [x] `AfasSamenstellingLookup::findCanonicalBaseOnlyContaining()`.

### Fase 4 — Portal-CSV reader + import-application
- [x] `PortalCsvRow`, `PortalCsvReader`, `FilePortalCsvReader`.
- [x] `ToolDataWiper` interface + `SqliteToolDataWiper` (wist tool-tabellen, AFAS-snapshot blijft).
- [x] `ImportPortalCsv` + handler: groepeert per `Groep`, bepaalt family-head uit AFAS Itemcode_Parent, maakt bases + BOM-items.
- [x] `PortalImportSummary` met counts en lijst van unresolved rijen.

### Fase 5 — CLI
- [x] `ImportPortalCsvCommand` — `group:import-portal-csv`.
- [x] `AfasListDuplicatesCommand` — `afas:list-duplicates`.
- [x] `bin/samenstellingen` bedraadt beide.

### Fase 6 — Handmatige verificatie + commit
- [x] `afas:pull` → 1894 samenstellingen + 146 duplicates gedetecteerd.
- [x] `group:import-portal-csv "AED's portalen.csv"` → 22 groepen, 94 bases, 315 base-items, 33 unresolved (taal-suffix-codes die niet in AFAS-BOMs voorkomen).
- [x] `group:sync-afas` over alle groepen: **94/94 perfecte matches**, 0 missing.
- [x] `afas:list-duplicates` toont alle 146 duplicate-paren, inclusief gevallen waar variant-SKUs (zoals `10041-60110`) een BOM hebben die identiek is aan de base — AFAS-data-fouten die nu zichtbaar zijn.
- [x] `make check` is groen (119 tests / 248 assertions).
- [x] **Commit + push** "slice 8: portal-CSV import + AFAS duplicate-detectie".

---

## Slice 10 — export missing-list als CSV (actie-lijst voor AFAS-team)

Eindbeeld: `audit:export-missing <output.csv>` schrijft alle variant-rijen met `afas_status = 'no_match'` naar een CSV. Per ontbrekende variant alles wat het AFAS-team nodig heeft om hem aan te maken: groep, base-naam, base AFAS-SKU, accessoire-info, en de complete verwachte BOM.

CSV-kolommen:
- `groep` — naam van de groep
- `base_naam` — AFAS-naam van de base-samenstelling
- `base_afas_sku` — AFAS-SKU van de matched base-only variant (zodat AFAS-team weet wat te dupliceren)
- `accessoire_itemcode`, `accessoire_label`
- `verwachte_bom` — komma-gescheiden itemcodes (base-items + accessoire)
- `verwachte_sku_voorstel` — `{base_afas_sku}-{accessoire_itemcode}` (naamconventie, ter suggestie)

### Fase 1 — Implementatie + verificatie
- [x] `MissingVariantRow` DTO en `ListMissingVariants` query + handler in `src/Application/Audit/`.
- [x] `GroupRepository::findAll()` toegevoegd (InMemory + Sqlite).
- [x] CLI command `ExportMissingVariantsCommand` — `audit:export-missing <output.csv>` schrijft de CSV.
- [x] `bin/samenstellingen` bedraadt.
- [x] Live verificatie: 232 ontbrekende varianten geëxporteerd; CSV bevat groep / base / base_afas_sku / accessoire / verwachte BOM / verwachte SKU-voorstel.
- [x] `make check` is groen (119 tests / 248 assertions).
- [x] **Commit + push** "slice 10: export missing-list als CSV".

---

## Slice 11 — taal-veld op `group_bases` + `Taal`-kolom in portal-CSV

Eindbeeld: elke base krijgt een expliciete `language_code` (NL/FR/DE/UK/EN/WAL/…), gevoed door een nieuwe `Taal`-kolom in de portal-CSV. Items binnen een base houden géén taal (taal-neutraal qua schema). Accessoires blijven taal-neutraal (één label, één rij). `group:show` toont de taal-kolom op de Bases-sectie. Geen wijzigingen aan AFAS-schrijven nog — dat komt in slice 12.

Design-keuzes:
- Taal is een eigenschap van de **base als geheel**, niet van individuele items.
- Bron van waarheid: `Taal`-kolom in `AED's portalen.csv`. Geen auto-detectie (te onbetrouwbaar bij naam-drift).
- Bij meerdere bases per CSV-rij (bv. één CSV-rij voor article 50013 resolveert naar AFAS-SKU 51013 én 52112): alle resulterende bases erven dezelfde taal uit de CSV-rij.
- Bestaande tests aanpassen voor de nieuwe constructor-parameter.

### Fase 1 — Migratie + domein
- [x] `migrations/0009_base_language.sql`.
- [x] `GroupBase` value object: `?string $languageCode` (3e optioneel arg).

### Fase 2 — Repository + persistence
- [x] `SqliteGroupBaseRepository` schrijft + leest `language_code`. InMemory blijft transparant via VO.

### Fase 3 — Application
- [x] `AddBaseToGroup` DTO + handler zetten taal door naar repo.
- [x] `AddBaseCommand` CLI heeft `--language|-l` option (optioneel; leeg = onbekend).

### Fase 4 — Portal-CSV uitbreiding
- [x] `PortalCsvRow.taal` + helper `languageCode(): ?string`.
- [x] `FilePortalCsvReader` leest `Taal`-kolom (optioneel — fallback null als niet aanwezig).
- [x] `ImportPortalCsvHandler` zet `row.languageCode()` op alle aangemaakte bases voor die CSV-rij.

### Fase 5 — Show-output uitbreiden
- [x] `ShowGroupCommand` Bases-sectie toont `ID | Taal | Naam` (— bij null).

### Fase 6 — Handmatige verificatie + commit
- [x] `group:show 52112` toont taal-kolom (lege voor bestaande data, gevuld na re-import met `Taal`-kolom in CSV).
- [x] `make check` groen (119 tests / 248 assertions).
- [x] **Commit + push** "slice 11: taal-veld op bases + Taal-kolom in portal-CSV".

---

## Slice 12 — Accessoires-catalogus drijft base/variant-detectie + ambiguïteit-check

Eindbeeld: bij portal-CSV-import is "base vs variant" niet meer een SKU-heuristiek (taal-suffix `-FR` vs accessoire-suffix `-60110`), maar gebruikt de tool de geregistreerde accessoires (`accessoires.itemcode`) als filter. Een AFAS-samenstelling is een **variant** als z'n BOM een geregistreerde accessoire bevat, anders een **base**. Levert één article-code méér dan één base-kandidaat op, dan stopt de import met een ambiguïteit-rapport (welke AFAS-samenstellingen kwalificeren beide).

Achtergrond: wij definiëren de basisitems én de accessoires zelf — die catalog is de ground truth. De huidige `isBaseOnly()`-SKU-heuristiek raadt en raakt op edge cases mis. Bij ambiguïteit weigert de tool een tie-breaker te kiezen; de user lost het op in AFAS (dubbele samenstelling weghalen, BOM corrigeren) en draait dan opnieuw.

### Fase 1 — AccessoireRepository::findAll() + wipe spaart catalogus
- [x] `AccessoireRepository::findAll(): list<Accessoire>` — interface, InMemory, Sqlite, contracttest.
- [x] `SqliteToolDataWiper` laat tabel `accessoires` met rust (`group_accessoires` verdwijnt al via CASCADE op `groups`).

### Fase 2 — Lookup: accessoires-lijst als variant-filter
- [x] Vervang `AfasSamenstellingLookup::findCanonicalBaseOnlyContaining()` door `findCanonicalBasesContaining(string $articleCode, list<string> $accessoireItemcodes): list<AfasSamenstelling>`. Base = canonical + BOM bevat article-code + GEEN van de accessoire-codes.
- [x] Verwijder `AfasSamenstelling::isBaseOnly()` — niet meer in gebruik.

### Fase 3 — Import-handler met accessoires-injectie + ambiguïteit-check
- [x] `ImportPortalCsvHandler` krijgt `AccessoireRepository` injected.
- [x] Bij start: laad alle accessoire-itemcodes; lege catalogus → `RuntimeException` met instructie "definieer eerst de accessoires via `accessoire:create` voordat de portal-CSV geïmporteerd kan worden".
- [x] Pre-validate per CSV-rij: 0 kandidaten → unresolved (reden "geen base"); >1 kandidaten → unresolved (reden "ambiguïteit: AFAS bevat meerdere base-kandidaten A, B").

### Fase 4 — CLI-rapportage
- [x] CLI-output benoemt beide failure-modes (geen kandidaat / ambigu) en actie-tekst verwijst naar AFAS-opschoning voor ambigue gevallen.

### Fase 5 — Tests, lint, live-run
- [x] Bestaande tests aanpassen aan nieuwe signatures (lookup-method, handler-constructor).
- [x] `make check` groen (135 tests / 299 assertions).
- [x] Live: 7 standaard accessoires (60110, 60112, 60122, 60212, 60213, 60222, 60223) toegevoegd; `group:import-portal-csv` rapporteert 42 unresolved (25 geen kandidaat, 17 ambigu) en laat DB onveranderd.

### Fase 6 — Commit + push
- [x] **Commit + push** "slice 12: accessoires-catalogus drijft base/variant-detectie + ambiguïteit-check".

---

## Slice 12.1 — BOM-blacklist als extra base-disqualifier

Eindbeeld: naast de accessoires-catalogus kan de user losse BOM-itemcodes blacklisten. Een AFAS-samenstelling wiens BOM een geblackliste itemcode bevat, telt niet meer als base-kandidaat. Eerste use case: de Waalse stickerset `81311` — voor onze portal-CSV is dat geen base-taal, dus 11135 (BOM `… 81311 …`) hoort niet als kandidaat te verschijnen tegenover 11132 (FR-stickerset).

Mechanisme is parallel aan accessoires: een lijst geblockeerde BOM-codes wordt samen met de accessoire-codes aan `findCanonicalBasesContaining` gevoed. Lookup-API blijft ongewijzigd; alleen de handler combineert de twee bronnen.

### Fase 1 — Schema + domain
- [x] Migration `0010_bom_blacklist.sql`: tabel `bom_blacklist (itemcode TEXT PRIMARY KEY, reason TEXT NOT NULL)`.
- [x] `BomBlacklistEntry` value object (`itemcode`, `reason`).
- [x] `BomBlacklistRepository` interface + `BomCodeAlreadyBlacklistedException`.

### Fase 2 — Persistence + contracttest
- [x] `InMemoryBomBlacklistRepository`.
- [x] `SqliteBomBlacklistRepository`.
- [x] `BomBlacklistRepositoryContractTestCase` (save, find, findAll, duplicate-error).
- [x] `SqliteToolDataWiper` laat `bom_blacklist` met rust (geen wijziging nodig — wiper noemt de tabel niet, dus 'ie blijft automatisch staan; getest in `SqliteToolDataWiperTest::leavesBomBlacklistIntact`).

### Fase 3 — CLI
- [x] `BlacklistBomCommand` — `samenstelling:blacklist-bom <itemcode> '<reden>'`.
- [x] `ListBomBlacklistCommand` — `samenstelling:list-blacklist` (toont tabel).

### Fase 4 — Integratie in portal-import
- [x] `ImportPortalCsvHandler` krijgt `BomBlacklistRepository` injected; combineert accessoire-codes + blacklist-codes tot `blockedBomCodes`.
- [x] Test: na blacklisten van 81311 verdwijnt 11135 als kandidaat voor article 10132 (`blacklistedBomCodeRemovesCandidateFromAmbiguity`).

### Fase 5 — Tests, lint, live-run
- [x] `make check` groen (147 tests / 323 assertions).
- [x] Live: `samenstelling:blacklist-bom 81311 …` + 60511/60123/60612 als accessoire toegevoegd → ambiguïteiten gedaald van 17 naar 6 (totaal unresolved 42 → 32).

### Fase 6 — Commit + push
- [x] **Commit + push** "slice 12.1: BOM-blacklist als extra base-disqualifier".

---

## Slice 12.2 — `group:import-portal-csv` non-blocking

Eindbeeld: dezelfde rapportage van onresolveerbare/ambigue rijen, maar de import blokkeert niet meer. Wel-resolveerbare rijen (exact 1 base-kandidaat) worden geïmporteerd; rijen met 0 of >1 kandidaten blijven in het rapport staan en worden overgeslagen. CLI eindigt met SUCCESS in plaats van FAILURE (tenzij accessoires-catalog leeg is — dat blijft een harde fout).

Reden: voor de huidige AFAS-staat blijven er altijd wat onopgeloste codes over (ambigue duplicates, codes zonder pakket). Door alles wat wel werkt direct te importeren krijgen we incrementele voortgang, en blijft het rapport als action-item-lijst staan.

### Fase 1 — Gedrag + tests
- [x] Test: bij mix resolveerbare + unresolved rijen worden de resolveerbare geïmporteerd, het rapport blijft compleet (`importsResolvableRowsAlongsideUnresolvedReport`).
- [x] `ImportPortalCsvHandler` verwijdert de early-return op `unresolved !== []`. `importRow` en `resolveFamilyHead` importeren alleen bij `count($candidates) === 1`.
- [x] CLI rapporteert unresolved als warning (geen FAILURE).
- [x] Bestaande tests aanpassen aan nieuwe semantiek (geen wijziging nodig — bestaande unresolved-tests verwachten al `basesCreated === 0` voor groepen waar elke rij unresolved is).

### Fase 2 — Lint + live + commit
- [x] `make check` groen (148 tests / 328 assertions).
- [x] Live: import importeert 21 groepen + 60 bases + 224 base-items, rapporteert 28 unresolved als warning, exit 0.
- [x] **Commit + push** "slice 12.2: import-portal-csv non-blocking".

---

## Slice 12.4 — AFAS-artikel-snapshot voor BOM-labels

Eindbeeld: de BOM-tabel in de UI toont per itemcode de échte AFAS-naam (nu staat label==itemcode na de portal-CSV-import). De fetcher heeft `Get_Artikelen` al in handen — we hoeven alleen de namen apart te bewaren en in de show-controller te joinen.

### Fase 1 — Schema + domain
- [x] Migration `0012_afas_articles.sql`: tabel `afas_articles (itemcode TEXT PRIMARY KEY, name TEXT NOT NULL)`.
- [x] `AfasArticle` value-object + `AfasArticleRepository` interface met `replaceSnapshot(list<AfasArticle>)` + `findByItemcode(string)`.
- [x] InMemory + Sqlite + contracttest (`INSERT OR REPLACE` om duplicate itemcodes uit AFAS af te vangen).

### Fase 2 — Sync
- [x] `AfasArticlesFetcher`-interface + `HttpAfasArticlesFetcher` (hergebruikt `AfasHttpClient`).
- [x] `afas:pull` sync ook artikel-namen (samen met de samenstellingen-snapshot); output toont aantal.

### Fase 3 — Resolveer in show-controller
- [x] `ShowGroupController` haalt label op via `AfasArticleRepository`; fallback op itemcode als artikel niet bekend is.
- [x] ApiTest bijwerken.

### Fase 4 — Lint + live + commit
- [x] `make check` groen (160 tests / 368 assertions).
- [x] `afas:pull` levert 1894 samenstellingen + 11318 artikelen; browser toont echte AFAS-namen in BOM-tabel.
- [x] **Commit + push** "slice 12.4: AFAS-artikel-snapshot voor BOM-labels".

---

## Slice 15 — Web UI fase 2 (resterende read-only data)

Eindbeeld: alles wat we via de CLI uitlezen is ook via de UI inzichtelijk — accessoires-catalogus, BOM-blacklist, gekoppelde accessoires per groep, gegenereerde varianten met AFAS-status, en (met name!) een top-level missing-variants pagina voor het AFAS-team.

Beslissingen (uit PLAN.md §11): sub-resources voor groep-data, AFAS-snapshot niet nu, missing-variants pagina wél.

Strict read-only. Geen mutaties.

### Sub-slice B1 — AppBar-navigatie + groep-detail tabs-skeleton
- [x] AppBar krijgt links: Groepen, Accessoires, Blacklist, Missing (actieve link gemarkeerd).
- [x] React Router: route `/groups/:familyHead/:tab?` met `bases` als default; tabs zijn bookmarkable.
- [x] MUI `Tabs` in `GroupDetail.tsx` — alle drie tabs actief.

### Sub-slice B2 — Accessoires-catalogus
- [x] `GET /api/accessoires` + controller + ApiTest.
- [x] `web/src/pages/AccessoiresList.tsx` op `/accessoires` + Vitest-test.

### Sub-slice B3 — BOM-blacklist
- [x] `GET /api/bom-blacklist` + controller + ApiTest.
- [x] `web/src/pages/BlacklistList.tsx` op `/blacklist` + Vitest-test.

### Sub-slice B4 — Groep-detail tab "Accessoires"
- [x] `GET /api/groups/{familyHead}/accessoires` + controller + ApiTest (incl. 404 voor onbekende groep).
- [x] `AccessoiresTab` in `GroupDetail` met empty-state Alert.

### Sub-slice B5 — Groep-detail tab "Varianten"
- [x] `GET /api/groups/{familyHead}/variants` + controller + ApiTest.
- [x] `VariantsTab` met DataGrid + status-chip (matched/no_match/no_local).

### Sub-slice B6 — Missing-variants top-level pagina ⭐
- [x] `GET /api/missing-variants` controller; hergebruikt `ListMissingVariantsHandler`.
- [x] `web/src/pages/MissingVariants.tsx` op `/missing` met DataGrid + "Exporteer CSV"-knop (frontend-side blob-download).
- [x] Vitest-test op rendering en export-knop-state.

### Fase Afronding
- [x] `make check` groen (166 PHP-tests / 394 assertions).
- [x] `npm --prefix web run test` groen (5 Vitest-tests).
- [x] Live in browser: alle nieuwe pagina's + tabs renderen correct met echte data.
- [x] **Commit + push** "slice 15: web UI fase 2 (catalogi + groep-tabs + missing-variants)".

---

## Slice 16 — Auto-sync na `afas:pull` en `group:import-portal-csv`

Eindbeeld: na een verse AFAS-snapshot (`afas:pull`) of een portal-CSV-import (`group:import-portal-csv`) wordt direct `group:sync-afas` voor alle groepen uitgevoerd, zodat de `afas_status` op de varianten en daarmee de missing-variants pagina onmiddellijk actueel is. Geen extra flag — gebeurt vanzelf.

Reden: nu moet je na pull/import handmatig `group:sync-afas` per groep draaien (en het CLI-commando bestaat alleen per groep). Voor de UI betekent dit dat /missing en de varianten-tab stale staan tot je de sync uitvoert. Eén implicietere call lost dat op.

### Fase 1 — Domein + handler
- [x] `SyncAllGroupsHandler` itereert over alle groepen en delegeert naar `SyncGroupAgainstAfasHandler`.
- [x] `SyncAllSummary` met `groupsProcessed`, `matched`, `noMatch`, `groupsSkipped`, `skipReasons`.
- [x] Defensief: lege snapshot → summary met `groupsSkipped` + reden, geen throw.
- [x] Unit-test met InMemory-repos (3 scenarios).

### Fase 2 — Integratie in `afas:pull`
- [x] `PullAfasSamenstellingenHandler` roept `SyncAllGroupsHandler` aan; `PullAfasSamenstellingenResult` heeft nu `SyncAllSummary`.
- [x] `AfasPullCommand` toont matched/no_match totalen.

### Fase 3 — Integratie in `group:import-portal-csv`
- [x] `ImportPortalCsvHandler` roept na de variant-regeneratie `SyncAllGroupsHandler` aan; `PortalImportSummary` heeft optionele `sync`.
- [x] `ImportPortalCsvCommand` toont de sync-output.

### Fase 4 — Lint, live, commit
- [x] `make check` groen (169 tests / 404 assertions).
- [x] Live: `group:import-portal-csv` rapporteert "Auto-sync: 21 groepen verwerkt → 60 matched, 0 no_match"; `/groups/.../variants` toont groene matched-chips zonder handmatige sync.
- [x] **Commit + push** "slice 16: auto-sync na afas:pull en portal-CSV-import".

---

## Slice 17 — Accessoire verwijderen (CLI-only)

Eindbeeld: een accessoire is verwijderbaar via `accessoire:delete <itemcode>` op de CLI. De FK-cascade ruimt groepskoppelingen en bijbehorende varianten op; daarna regenereert de handler de variant-matrix voor elke betroffen groep zodat tellers kloppen.

De UI blijft read-only (zie CLAUDE.md). De accessoires-catalogus-pagina krijgt een inline-verwijzing naar de CLI-commando's in plaats van een delete-knop.

### Fase 1 — Domein + delete-handler
- [x] `AccessoireRepository::delete(string $itemcode): void` (throwt `AccessoireNotFoundException`).
- [x] InMemory + Sqlite + uitgebreide contracttest.
- [x] `DeleteAccessoireHandler` + `DeleteAccessoire` command + variant-regeneratie voor gekoppelde groepen.
- [x] Handler-test.

### Fase 2 — CLI
- [x] `DeleteAccessoireCommand` — `accessoire:delete <itemcode>` met success-output incl. lijst betroffen groepen.
- [x] `bin/samenstellingen` wiring.

### Fase 3 — UI-hint (geen mutatie)
- [x] AccessoiresList toont inline-tekst die naar `accessoire:create`/`accessoire:delete` verwijst.

### Fase 4 — Lint, live, commit
- [x] `make check` (175 tests / 414 assertions) + vitest (5 tests) groen.
- [x] Live: `accessoire:delete 91116` ruimt op + UI toont nu de inline-hint en geen 91116 meer.
- [x] **Commit + push** "slice 17: accessoire:delete via CLI + read-only-UI principe vastgelegd in CLAUDE.md/PLAN.md".

---

## Slice 18 — Name sanity check (audit op base- en variant-namen)

Eindbeeld: per gematchte AFAS-samenstelling (base + variant) bouwt de tool de **verwachte naam** volgens de canonieke template uit PLAN.md §9.1, vergelijkt strikt (byte-equal) met de werkelijke naam in `afas_samenstellingen.name`, en rapporteert drift. Read-only — geen rename-actie in deze slice (komt later in slice 13 / `afas:rename-drift` of vergelijkbaar).

Drift-soorten die we expliciet vangen:
- prefix-casing (`AED Pakket:` vs `AED pakket:`)
- modeltype-spelling (`Vol-automaat`, `Halfautomatisch`, `Semi-Automatique` met hoofdletter A)
- taal-suffix met haakjes (`(FR)` vs `FR`)
- ontbrekende, fout gespelde of omgekeerde safeset/stickerset-staart
- typo's (`safesett`, `incl.safeset` zonder spatie, dubbele spaties)
- variant met `safeset`-staart in plaats van `incl. {accessoire}`
- ontbrekend prefix bij oudere SKUs
- mismatched taal-spelling (`Kroatian`, `French`)

### Fase 1 — Domein: `VariantNamingPolicy`
- [x] `VariantNamingPolicy::expectedName(Group, GroupBase, ?Accessoire)`.
- [x] Vijf taal-templates volgens §9.1 (NL/FR/DE/DA/EN). Compound-codes (`NL/FR`) → eerste segment beslist.
- [x] `Group::modelName` nullable veld + migration 0013.
- [x] 11 unit-tests (base/variant × 5 talen + edge cases).

### Fase 2 — Application: `NameAuditHandler`
- [x] `NameAuditHandler` itereert over `matched`-varianten en vergelijkt strict.
- [x] `NameDriftRow` value-object.
- [x] 4 handler-tests (no-drift, drift-base, drift-variant, skip-zonder-model_name).

### Fase 3 — CLI: `audit:names`
- [x] `AuditNamesCommand` met `--limit=N`, exit 1 bij drift.
- [x] Live: 270 drift-rijen geconstateerd.

### Fase 4 — HTTP + UI
- [x] `GET /api/name-drift` + integratie-test.
- [x] AppBar krijgt `Name drift`-link.
- [x] `NameDrift.tsx` + Vitest.

### Fase 5 — Tests + lint + live + commit
- [x] `make check` (191 tests / 444 assertions) + vitest (6 tests) groen.
- [x] Live: 270 drift-rijen tonen prefix-casing (`AED Pakket:` ipv `AED pakket:`), FR-bases zonder `Pack DAE:`, verkorte accessoire-suffixen (`met witte binnenkast` ipv `incl. ARKY metalen binnenkast wit met alarm`).
- [x] **Commit + push** "slice 18: name sanity check (audit) — base + variant naam-templates per taal".

---

## Slice 19 — Suspicious-base audit (SKU-suffix matcht accessoire maar BOM mist die accessoire)

Eindbeeld: detecteer AFAS-samenstellingen waarvan de SKU eindigt op een geregistreerde accessoire-itemcode (`11683-60110`, `11650-60110`) maar wier BOM die accessoire **niet** bevat. Zulke samenstellingen zien er semantisch uit als varianten (base + accessoire) maar zijn in AFAS opgebouwd als base. Onze portal-CSV-import filtert op BOM-content, dus deze gevallen passeren als base — read-only audit signaleert ze.

Eerste hit (gedetecteerd in slice 18-context): `11683-60110` en `11650-60110` — beide Zoll AED Plus-bases met "+ ARKY Backpack" in de naam, maar zonder `60110` in de BOM.

### Fase 1 — Domain + Application
- [x] `SuspiciousBaseRow` value-object.
- [x] `SuspiciousBaseAuditHandler` parseert SKU op `…-NNNNN`, vergelijkt met accessoires-catalogus + BOM.
- [x] 4 handler-tests.

### Fase 2 — CLI
- [x] `AuditSuspiciousBasesCommand` — `audit:suspicious-bases`. Exit 1 bij hits.

### Fase 3 — HTTP + UI
- [x] `GET /api/suspicious-bases` + integratie-test.
- [x] AppBar krijgt `Suspicious`-link.
- [x] `SuspiciousBases.tsx` + Vitest.

### Fase 4 — Lint + live + commit
- [x] `make check` (196 / 455) + vitest (7) groen.
- [x] Live: 10 verdachte bases (5 Zoll AED Plus + 4 Mindray Beneheart + 1 Reanibex). UI toont alle.
- [x] **Commit + push** "slice 19: suspicious-base audit (SKU-suffix-vs-BOM consistentie)".

---

## Slice 21 — EN/UK-bases zonder stickerset accepteren

Eindbeeld: portal-CSV-import wijst pure EN/UK-bases niet meer af om de stickerset-eis. AFAS heeft simpelweg geen Engelse stickerset — Engelse bases hebben alleen `70112` (reanimatiekit) + article-code in hun BOM, en dat is daar de norm.

Compound talen (`NL/EN`, `NL/EN/FR`, `DE/EN/FR`) behouden de sticker-eis: data toont dat die bases gewoon `81111`/`81211` etc. in BOM hebben.

### Fase 1 — Filter aanpassing + tests
- [x] `ImportPortalCsvHandler::sellableCandidatesFor` krijgt extra `$language`-parameter.
- [x] Voor `language === 'EN'` of `'UK'`: sticker-eis (`81xxx`) vervalt; `70112` blijft verplicht.
- [x] Compound (`NL/EN` etc.): sticker-eis blijft.
- [x] 3 nieuwe tests: pure-EN accept, NL afwijzen zonder sticker, compound NL/EN sticker-eis behouden.

### Fase 2 — Lint + live
- [x] `make check` groen (205 tests / 480 assertions).
- [x] Live: 28 → 16 unresolved (14 EN-bases lossen op); 22 groepen / 339 matched / 260 no_match.

---

## Slice 22 — `group:add-base-from-afas` (handmatige base-koppeling)

Eindbeeld: workaround voor ambigue portal-CSV-import-gevallen. De user kan met één CLI-commando expliciet zeggen: "voor groep X, gebruik AFAS-samenstelling Y als base in taal Z" — naam + BOM komen uit de lokale snapshot.

Use case (bijv.): article 10650 levert 2 base-kandidaten (`11650` echt en `11650-60110` waar `60110` ontbreekt in BOM). De import skipt 'm. Met `group:add-base-from-afas 11683 11650 FR` zet de user de juiste base zelf vast.

```
group:add-base-from-afas <family-head> <afas-itemcode> <language>
```

### Fase 1 — Domain + handler
- [x] `AddBaseFromAfas` + `AddBaseFromAfasHandler` + `AfasSamenstellingNotInSnapshotException`.
- [x] `AfasSamenstellingenRepository::findByItemcode` (InMemory + Sqlite).
- [x] Handler-tests: success, onbekende groep, onbekende AFAS-SKU, duplicate base.

### Fase 2 — CLI
- [x] `AddBaseFromAfasCommand` — `group:add-base-from-afas <family-head> <afas-itemcode> <language>`.

### Fase 3 — Lint + live
- [x] `make check` groen (209 tests / 492 assertions).
- [x] Live: `group:add-base-from-afas 11683 11650 FR` voegt base #75 toe met AFAS-SKU `11650` + 4 BOM-items + regenereert variant-matrix.

---

## Slice 23 — Portal-CSV-import respecteert handmatig vastgezette base

Eindbeeld: als de portal-CSV-import voor een article meerdere base-kandidaten vindt **én** de user heeft al een base in onze tool waarvan `afas_itemcode` matched met één van die kandidaten, dan kiest de import die. Ambiguïteit-rapport voor die rij verdwijnt.

Reden: na slice 22 kan de user via `group:add-base-from-afas` expliciet een keuze maken voor ambigue gevallen. De prevalidate-stap zag die keuze niet en bleef "ambigu" rapporteren — misleidend.

### Fase 1 — Repository + handler
- [x] `GroupBaseRepository::findAllAfasItemcodes()` (InMemory + Sqlite + contracttest).
- [x] `ImportPortalCsvHandler` laadt de set + filtert na `sellableCandidatesFor`.
- [x] Test: ambigu lost op door pinned base (`pinnedBaseResolvesAmbiguity`).

### Fase 2 — Live
- [x] Live: filter werkt; voor 10650 blijven echter 2 gepinde SKU's (`11650` + `11650-60110` uit een eerdere import) → terecht nog ambigu. Vraagt aparte cleanup (slice 24 `group:remove-base`?).

---

## Slice 24 — `group:remove-base` (CLI om base handmatig te verwijderen)

Eindbeeld: één CLI-commando om een base uit een groep te verwijderen. Cascade ruimt base-items + bijbehorende varianten op. Variant-matrix wordt na verwijdering geregenereerd zodat tellers kloppen. Read-only UI blijft (CLAUDE.md).

Use case: opschoning na slice-19 `audit:suspicious-bases` aanwijst welke bases eigenlijk varianten zijn (zoals base #59 `11650-60110` "Zoll AED Plus Automatique FR+ ARKY Backpack" naast de echte base #75 `11650`). Onmisbaar voor:
- Slice 23's pinned-resolutie werkt pas als impasses worden opgeruimd.
- Onbedoelde duplicate bases in een groep+taal (12 paren bestaan al in de huidige DB).

Symmetrisch met `accessoire:delete` (slice 17) en `group:add-base-from-afas` (slice 22).

### Fase 1 — Repository + handler
- [x] `GroupBaseRepository::delete(int)` + `findFamilyHeadForBase(int)` op interface.
- [x] InMemory + Sqlite + contracttest.
- [x] `RemoveBase` + `RemoveBaseResult` + `RemoveBaseHandler` (throwt `BaseNotFoundException` voor onbekende id).
- [x] 2 handler-tests (success-path + onbekende id).

### Fase 2 — CLI
- [x] `RemoveBaseCommand` — `group:remove-base <base-id>` met foutmelding voor onbekende of niet-numerieke id.

### Fase 3 — Lint + live
- [x] `make check` groen (218 tests / 511 assertions).
- [x] Live: `group:remove-base 59` ruimt de oude `11650-60110`-base op; herimport rapporteert 0 ambigu (slice 23 pinning resolved 10650), unresolved 16 → 14, 333 matched / 266 no_match.

---

## Slice 25 — Accessoire-delta verplicht maken

Eindbeeld: elke accessoire heeft een canonieke prijs-toeslag (`delta_eur`) die de tool als ground truth gebruikt voor de price-audit (slice 27). Bij `accessoire:create` verplicht; bestaande 9 accessoires staan op `0` na migratie en worden via een nieuw `accessoire:set-delta`-commando bijgewerkt. UI toont de delta-kolom.

### Fase 1 — Schema + domain
- [x] Migration `0014_accessoire_delta.sql` (`delta_cents INTEGER NOT NULL DEFAULT 0`).
- [x] `Accessoire` value-object krijgt `int $deltaCents` met `>=0`-validatie.
- [x] `EuroParser` domain-service met 20 unit-tests (parses + format).
- [x] InMemory + Sqlite repo's; uitgebreide contracttest (persistence, default-0, `updateDelta`).

### Fase 2 — Application + CLI
- [x] `CreateAccessoire` + handler nemen `deltaCents` mee.
- [x] `accessoire:create` verplicht 3e arg `<delta-eur>`; parser via `EuroParser`.
- [x] `SetAccessoireDelta` + `SetAccessoireDeltaHandler` (3 handler-tests).
- [x] `accessoire:set-delta <itemcode> <eur>` CLI.
- [x] Bestaande `CreateAccessoireCommandTest` bijgewerkt voor nieuwe verplichte arg.

### Fase 3 — UI + HTTP
- [x] `GET /api/accessoires` levert `deltaCents` (int) + `deltaEur` (display "€ 79,50").
- [x] `AccessoiresList`-pagina: extra kolom "Toeslag" rechts-uitgelijnd.
- [x] Vitest update.
- [x] Helper-tekst in pagina wijst naar nieuwe CLI's.

### Fase 4 — Lint + live
- [x] `make check` + vitest groen (250 PHP + 7 vitest).
- [x] Migratie live gerund: alle 11 bestaande accessoires staan op 0. Invullen via `accessoire:set-delta` is volgende stap door user.

---

## Slice 26 — `afas_prijzen` snapshot + integratie in `afas:pull`

Eindbeeld: bij `afas:pull` halen we ook prijs-data uit `Get_Prijzen` op en bewaren alleen de actieve rijen (`Einddatum` leeg of ≥ vandaag) in een lokale `afas_prijzen`-tabel. UI-pagina `/prices/{itemcode}` toont de huidige prijzen per prijslijst/debiteur.

### Fase 1 — Schema + domain
- [x] Migration `0015_afas_prijzen.sql` (auto-id PK + indexen op itemcode + prijslijst_id).
- [x] `AfasPrijs` value-object met validaties.
- [x] `AfasPrijsRepository` interface (`replaceSnapshot`, `findByItemcode`, `countSnapshot`).
- [x] InMemory + Sqlite + contracttest (5 scenarios).

### Fase 2 — Fetcher + sync
- [x] `AfasPrijzenFetcher` + `HttpAfasPrijzenFetcher` (Get_Prijzen → cents, filter actief).
- [x] `PullAfasSamenstellingenHandler` haalt ook prijzen op.
- [x] `PullAfasSamenstellingenResult.prijzen`-veld.
- [x] `AfasPullCommand` toont aantal prijzen.

### Fase 3 — HTTP + UI
- [x] `GET /api/articles/{itemcode}/prices`.
- [x] `ArticlePricesTable` component; geïntegreerd in BasesTab van GroupDetail (BOM-items boven, actieve prijzen onder).
- [x] Vitest mock onderscheidt detail vs prices op URL.

### Fase 4 — Lint + live
- [x] `make check` (260 tests / 577 assertions) + vitest (7) groen.
- [x] Live: `afas:pull` levert 41.036 actieve prijzen over 17 prijslijsten en 10.886 unieke artikelen. UI toont per base z'n prijzen — voor 11161 (Lifepak CR2 NL): Basis €1.899, Dealers FR €1.819, Dealers Benelux €1.499.

---

## Slice 27 — `audit:prices` (toeslag-drift + missende prijslijst)

Eindbeeld: één audit-rapport dat per (groep, base, accessoire, prijslijst) detecteert óf de prijs-toeslag in AFAS afwijkt van `accessoires.delta_cents`, óf de variant in die prijslijst ontbreekt. Verwachte variant-SKU = `base.afasItemcode + '-' + accessoire.itemcode`.

### Fase 1 — Domain + handler
- [x] `PriceDriftRow` value-object + `AuditPrices` DTO.
- [x] `PriceAuditHandler` (skip klant-prijzen, skip hogere staffels, latest-per-prijslijst).
- [x] 5 handler-tests.

### Fase 2 — CLI
- [x] `audit:prices --limit=N` met exit 1 bij hits.

### Fase 3 — HTTP + UI
- [x] `GET /api/price-drift`.
- [x] AppBar krijgt "Price drift"-link.
- [x] `PriceDrift.tsx` met DataGrid + CSV-export + Vitest.
- [x] Bug-fix `EuroParser::formatCents` accepteert negatieve cents.
- [x] Bug-fix `SqliteGroupAccessoireRepository::findAllForGroup` selecteert nu `delta_cents` mee (stille bug — alle linked-accessoires kwamen met delta 0 uit deze repo).

### Fase 4 — Lint + live
- [x] `make check` (265 / 588) + vitest (8) groen.
- [x] Live: 980 rijen — 194 toeslag-drift + 786 missing. UI toont per rij verwachte vs werkelijke delta met status-chip.

---

## Slice 28 — Prijslijst-blacklist + prijslijst-naam-snapshot

Eindbeeld: één globale `prijslijst_blacklist`-tabel laat ons prijslijsten markeren die geheel buiten `audit:prices` blijven (zowel `missing` als `toeslag-drift`). Vóór de blacklist zelf eerst een mini-snapshot van `Get_Prijslijsten` (id → omschrijving) zodat namen overal in output beschikbaar zijn. CLI-only mutaties, read-only UI (CLAUDE.md regel). Zie PLAN.md §13.

### Sub-slice 28.0 — `afas_prijslijsten` snapshot

#### Fase 1 — Domain + repo
- [x] Migration `0016_afas_prijslijsten.sql`: `(id TEXT PRIMARY KEY, omschrijving TEXT NOT NULL, gesynchroniseerd_op TEXT)`.
- [x] Value-object `AfasPrijslijst(id, omschrijving)`.
- [x] `AfasPrijslijstRepository`-contract + `findAll()` + `findById(id): ?AfasPrijslijst` + `replaceSnapshot(list)` (idempotent reconciliation).
- [x] `InMemoryAfasPrijslijstRepository` + `SqliteAfasPrijslijstRepository` + gedeeld contract-test (278/616 groen).

#### Fase 2 — Fetcher + sync-handler
- [x] `AfasPrijslijstenFetcher`-contract.
- [x] `HttpAfasPrijslijstenFetcher`: `Get_Prijslijsten` → list<AfasPrijslijst>. Lege omschrijving → fallback naar ID.
- [x] Geïntegreerd in `PullAfasSamenstellingenHandler` + `PullAfasSamenstellingenResult` + `afas:pull` output. Container + bin wiring.

#### Fase 3 — Naam-weergave overal
- [x] `PriceAuditHandler` returnt `prijslijstOmschrijving` mee in `PriceDriftRow` (resolved via `AfasPrijslijstRepository`, fallback `null` als onbekend).
- [x] `audit:prices` CLI: kolom "Prijslijst" toont nu `id — omschrijving`.
- [x] HTTP `/api/price-drift` levert `prijslijstOmschrijving` mee.
- [x] `PriceDrift.tsx` + CSV-export tonen `id — omschrijving`; vitest aangepast (279/619 + 8 vitest groen).

### Sub-slice 28.1 — Blacklist tabel + CLI

#### Fase 1 — Domain + repo
- [x] Migration `0017_prijslijst_blacklist.sql`.
- [x] Value-object `PrijslijstBlacklistEntry(prijslijstId, reden, aangemaaktOp?)`.
- [x] `PrijslijstBlacklistRepository`-contract + InMemory + Sqlite + gedeeld contract-test.
- [x] Faalmodi (`PrijslijstAlreadyBlacklistedException` / `PrijslijstNotBlacklistedException`).

#### Fase 2 — CLI commando's
- [x] `pricelist:blacklist <id> "<reden>"`.
- [x] `pricelist:unblacklist <id>`.
- [x] `pricelist:list-blacklist` met join op `afas_prijslijsten` voor omschrijving.

### Sub-slice 28.2 — Audit-filter

- [x] `PriceAuditHandler` accepteert `PrijslijstBlacklistRepository`.
- [x] Skipt alle drift-rijen (beide statussen) waarvan `prijslijstId` op de blacklist staat.
- [x] Test `skipsBlacklistedPrijslijstForBothStatuses` dekt drift + missing.
- [x] Container + AppFactory + bin-wiring bijgewerkt (292/643 groen).

### Sub-slice 28.3 — UI

- [x] HTTP `GET /api/prijslijst-blacklist` → list met `prijslijstId, omschrijving, reden, aangemaaktOp`.
- [x] AppBar krijgt link "Prijslijst-BL".
- [x] `web/src/pages/PrijslijstBlacklist.tsx` met DataGrid + inline-tip naar CLI.
- [x] Vitest (9 passing).

### Fase 4 — Lint + live (over alle sub-slices)

- [x] `make check` (292/643 + 9 vitest) groen.
- [x] Live `afas:pull` syncde 29 prijslijsten.
- [x] `audit:prices` toont kolom `id — omschrijving` op de echte snapshot.
- [x] Live IOK (011), Opdrachtencentrale (024), Coop collectief (025), Farys (010) op blacklist. Audit: **980 → 917** rijen (63 missing-rijen op die lijsten weg; drift bleef 194 — er was geen drift op die kleine lijsten). UI op `/prijslijst-blacklist` toont de 4 entries met omschrijving+reden+datum. UI op `/price-drift` toont `194 toeslag-drift, 723 missing` met namen achter de prijslijst-ID.

---

## Slice 30 — `prices:fix-drift` (FbSalesPrice PUT)

Eindbeeld: CLI `prices:fix-drift [--apply] [--limit=N]` corrigeert variant-prijzen in AFAS naar `base + accessoires.delta_cents`. Default dry-run. PoC live geverifieerd op 10041-60212 in lijst 003 (€1520 → €1450). Zie PLAN.md §14.

### Fase 1 — Domain + AfasHttpClient
- [x] `AfasHttpClient::updateConnector(string $id, array $payload): void` (PUT).
- [x] Value-object `PriceFixPlan(variantItemcode, prijslijstId, staffelAantal, currentCents, targetCents, beginDate)`.
- [x] Contract `PriceFixWriter` met `apply(PriceFixPlan $plan): void` + `PriceFixFailedException`.
- [x] `InMemoryPriceFixWriter` (registreert plannen, simuleert fouten via constructor).
- [x] `HttpFbSalesPriceWriter` (bouwt FbSalesPrice-payload incl. staffel-velden `CrPr`+`Am` wanneer staffel > 0).

### Fase 2 — Handler + tests
- [x] `FixPriceDrift` DTO met `?limit` + `apply` flag.
- [x] `FixPriceDriftResult` met `plans`, `appliedCount`, `failures`.
- [x] `FixPriceDriftHandler`: drift-rijen via `PriceAuditHandler`, plan met `targetCents = baseCents + expectedDeltaCents`, schrijft via writer als `apply=true`.
- [x] Skipt rijen waar `targetCents === currentCents` (no-op).
- [x] Begindatum-lookup: **niet** uit `afas_prijzen`-snapshot (die heeft `date=vandaag`), maar via `BeginDateLookup`-contract → `HttpGetPrijzenBeginDateLookup` doet een Get_Prijzen-call per fix. AFAS wil de echte begindatum (PUT-PK).
- [x] 4 unit-tests (dry-run, apply, limit, fail-doesn't-block-next).

### Fase 3 — CLI
- [x] `prices:fix-drift [--apply] [--limit=N]`. Default dry-run met diff-table (Variant | Lijst | Aantal | Huidig | Gewenst | Δ).
- [x] Failures → `tmp/fix-drift-{datum}.csv`.
- [x] Exit-code: 0 bij geheel succes, 1 bij failures, 0 bij dry-run.

### Fase 4 — Lint + live
- [x] `make check` groen (304/679 + vitest 12).
- [x] Live `--limit=1 --apply`: 11114-60110 in 026 baseline-staffel €893,02 → €893,01. Geverifieerd via Get_Prijzen.
- [x] Cleanup: `tmp/poc-put-price.php` blijft staan als referentie-PoC (gitignored).

---

## Slice 31 — `prices:fix-missing` (FbSalesPrice POST)

Vervolg op slice 30. Patroon hergebruiken voor `missing`-rijen: variant ontbreekt in prijslijst waar base wel staat. Zie PLAN.md §15.

### Fase 0 — PoC POST
- [x] `tmp/poc-post-price.php` PoC: PUT faalt op nieuwe rij met "Prijs niet gevonden"; POST werkt (HTTP 201). POST is NIET idempotent — geeft "Prijs bestaat reeds" bij duplicate.
- [x] PUT ↔ update, POST ↔ insert. Beide payloads identiek.

### Fase 1 — Domain + writer-uitbreiding
- [x] `PriceFixWriter::insert(PriceFixPlan $plan): void` (apart van `apply`).
- [x] `HttpFbSalesPriceWriter::insert` doet POST via nieuwe `AfasHttpClient::insertConnector`.
- [x] `InMemoryPriceFixWriter` ondersteunt beide; tests updaten array `$inserted` separaat.

### Fase 2 — Handler + tests
- [x] `FixPriceMissingHandler`: missing-rijen via audit, begindatum van base (variant bestaat nog niet), skipt variants die niet in `afas_articles` zitten.
- [x] 3 unit-tests (plan-when-article-exists, skip-no-article, apply-inserts-all).

### Fase 3 — CLI
- [x] `prices:fix-missing [--apply] [--limit=N]` met dry-run-tabel, skip-rapport voor artikel-ontbreekt, failures naar `tmp/fix-missing-{datum}.csv`.

### Fase 4 — Lint + live
- [x] `make check` groen (307/687 + vitest 12).
- [x] Live `--limit=2 --apply`: plan 1 (basis €1029, al via PoC gezet) → 500 "Prijs bestaat reeds"; plan 2 (staffel 10 €999) → 201. AFAS toont nu beide prijzen.
- [x] **173 variants geskipt** wegens ontbrekend artikel — vraagt om slice 13 (`afas:create-missing`) voor volle dekking.

---

## Slice 32 — Staffelprijzen meenemen (switch `Get_Prijzen` → `easylinq_*`)

Eindbeeld: `afas_prijzen.staffel_aantal` bevat echte waarden uit AFAS. Onze huidige `Get_Prijzen`-bron levert geen staffels in deze AFAS-setup — we switchen naar `easylinq_prices_saleprice` + `easylinq_prices_saleprice_staffel`. Geen schema-wijziging. Zie PLAN.md §16.

### Sub-slice 32.0 — PoC + verkenning
- [x] `tmp/poc-easylinq-prijzen.php` script: count beide connectors + sample-vergelijking.
- [x] Mismatches geanalyseerd → easylinq is dag-voor-dag-view; filter op `date=today` lost dat op.
- [x] Bevindingen `tmp/easylinq-vs-getprijzen-{datum}.csv` opgeleverd.

### Sub-slice 32.1 — Fetcher omzetten
- [x] `HttpAfasPrijzenFetcher::fetchActive()` herschrijven naar twee easylinq-connectors.
- [x] Mapping: `item_id`/`pricelist_id`/`debtor_id`/`price`/`Hoeveelheid|quantity`/`date`.
- [x] Filter staffel-rijen client-side op `current=1` (server-side filter werkt niet op deze connector).
- [x] InMemory-fetcher ongewijzigd; live geverifieerd via `afas:pull`.

---

## Slice 34 — Duplicate-BOM audit

Eindbeeld: read-only audit-rapport van AFAS-samenstellingen met identieke BOM. Live: 1893 samenstellingen, 133 duplicaten. Hoofdpatroon: variant-rijen waar accessoire-itemcode in AFAS niet aan BOM is toegevoegd. Zie PLAN.md §17.

### Sub-slice 34.0 — Domain + handler
- [x] Value-object `DuplicateBomGroup(fingerprint, members)`.
- [x] `DuplicateBomAuditHandler` met `AfasSamenstelling::bomKey()` als fingerprint.
- [x] 5 unit-tests (empty, no-dups, identiek pair, order-invariant, lege BOM skipped).

### Sub-slice 34.1 — CLI + HTTP + UI
- [x] CLI `audit:duplicate-boms` met `--limit=N`, exit 1 bij hits.
- [x] HTTP `GET /api/duplicate-boms`.
- [x] AppBar-link "Duplicate BOM".
- [x] `DuplicateBoms.tsx` met uitklap + CSV-export + Vitest.
- [x] Live: 59 groepen met 192 samenstellingen op de echte snapshot. Hoofdpatroon bevestigd: variant heeft dezelfde BOM als pure base omdat accessoire-itemcode niet in BOM is opgenomen.

---

### Sub-slice 32.2 — Live verificatie van sync
- [x] `afas:pull` met 10264 staffel-rijen. Distributie 1, 5, 10, 12, 15, 20, 25, 48, 50, 100, 120, 200, 240.
- [x] `audit:prices` (na switch zonder per-staffel-logica): geen ongewenste baseline-jump.

### Sub-slice 32.3 — Audit-logica per-staffel
- [x] `indexLatestPerPrijslijstAndStaffel` met key `<lijst>|<staffel>`.
- [x] Drop `staffelAantal > 1`-skip.
- [x] `staffelAantal: ?int` in `PriceDriftRow`.
- [x] `inconsistent-staffel`-status (variant heeft staffel die base mist).
- [x] 3 nieuwe handler-tests (drift op staffel, missing op staffel, inconsistent).

### Sub-slice 32.4 — CLI + UI staffel-aware
- [x] `audit:prices` CLI-tabel met "Aantal"-kolom.
- [x] `/api/price-drift` levert `staffelAantal` mee.
- [x] `PriceDrift.tsx`: prijslijst-label met staffel-suffix, 3e tab "Inconsistent staffel", CSV-kolom.
- [x] Vitest aangepast.

### Sub-slice 32.5 — Fix-scripts staffel-aware (klaar tijdens slice 30/31)
- [x] Staffel-PUT live: 11114-60110 026 staffel 10 €860,41 → €860,40 via `prices:fix-drift --limit=1 --apply`. `CrPr=true` + `Am=10` onderscheidt staffel-rij van basis-rij.
- [x] Staffel-POST live (in slice 31): 10074-60112 026 staffel 10 €999 insert via `prices:fix-missing --limit=2 --apply`.
- [x] `HttpFbSalesPriceWriter::payload` voegt `CrPr=true` + `Am=N` toe bij `staffelAantal > 0` voor zowel PUT als POST.
- [x] `FixPriceDriftHandler` en `FixPriceMissingHandler` itereren al alle (lijst, staffel)-combinaties via audit-output.
- [x] CLI `prices:fix-drift` en `prices:fix-missing` tabellen hebben "Aantal"-kolom.

---

## Slice 37 — VariantNamingPolicy refactor + per-taal accessoire-labels

Eindbeeld: drie canonical templates (NL/FR/EN), per-accessoire en per-groep multi-taal labels, EN-bases krijgen suffix `UK`. Zie PLAN.md §18.

### Sub-slice 37.0 — Schema + VO + repositories
- [x] Migration `0019_naming_multi_taal.sql`: accessoires krijgt `naam_kort_nl/fr/en`; `groups.model_name` → `model_name_nl` + `model_name_fr/en`.
- [x] `Accessoire` VO + `naamKort(taal)` method.
- [x] `Group` VO + `modelNameForTaal(taal)` method.
- [x] Sqlite + InMemory repositories aangepast.
- [x] Bestaande `$group->modelName` refs → `$group->modelNameNl`.

### Sub-slice 37.1 — VariantNamingPolicy refactor
- [x] Drop oude 5 templates.
- [x] 2 templates (NL voor alles + FR voor pure FR-base) met EN→UK suffix + compound `/`→`-`.
- [x] Resolve per-taal model_name + naam_kort. Foutmelding noemt exacte CLI-suggestie.
- [x] 12 unit-tests (alle taal-codes × base/variant + foutpaden).

### Sub-slice 37.2 — CLI invoer + display
- [x] `accessoire:set-naam-kort <itemcode> <nl|fr|en> <naam>`.
- [x] `group:set-model-naam <family-head> <nl|fr|en> <naam>`.
- [x] `group:show` toont model_name_{nl,fr,en} + accessoires-kolommen `Kort NL/FR/EN`.
- [x] HTTP API geeft de nieuwe velden mee; `AccessoiresList`, `GroupDetail` UI updated.

### Sub-slice 37.3 — Live data invullen
- [x] 9 accessoires × 3 talen via `tmp/seed-naming.sh`.
- [x] 21 groepen × 3 talen.
- [x] `audit:names` toont 405 drift-rijen tov canonical (FR-template + per-taal-labels).

### Sub-slice 37.4 — (los) Fix-names CLI
- [x] `names:fix-drift [--apply] [--limit=N]` schrijft canonical naar AFAS via `FbComposition` PUT (`Ds`). Type_id=7 routing — `FbItemArticle` faalt met "Artikel niet gevonden". Live geverifieerd op 11112 → `Pack DAE: Heartsine Samaritan PAD 350P FR`.
- [x] FR-template haakjes verwijderd voor symmetrie met NL: `Pack DAE: <model> FR` (geen `(FR)`).

---

## Slice 38 — Variant-label per base (4G / USB / WiFi / 3G in canonical)

Eindbeeld: bases die binnen één groep een afwijkende hardware-variant zijn (4G-radio bij Mindray, WiFi/USB/3G bij LIFEPAK) krijgen een `variant_label` dat tussen `<model>` en `<taal-suffix>` in de canonical-naam komt. Zonder label blijft de naam-generatie 1-op-1 als nu. Zie PLAN.md §19.

### Sub-slice 38.0 — Schema + repository
- [x] Migratie `0020_group_base_variant_label.sql`: `ALTER TABLE group_bases ADD COLUMN variant_label TEXT NULL`.
- [x] `GroupBase` VO: optionele `?string $variantLabel`-parameter (default `null`).
- [x] `SqliteGroupBaseRepository`: SELECT/INSERT leest + schrijft `variant_label`.
- [x] `InMemoryGroupBaseRepository`: zelfde gedrag via `withId()`.
- [x] Contract-tests: round-trip null/value + 0-rijen-pad.

### Sub-slice 38.1 — Policy gebruikt label
- [x] `VariantNamingPolicy`: leest `$base->variantLabel`. Niet-leeg → `<label>` tussen `<model>` en `<suffix>`.
- [x] Data-provider-tests: NL/FR base met label, label + accessoire, label + compound taal, lege string == null.
- [x] Bestaande policy-tests blijven groen.

### Sub-slice 38.2 — CLI invoer + UI-chip
- [x] CLI `base:set-variant-label <afas_itemcode> <label>` — lege string clear. Repo-methode `setVariantLabelByAfasItemcode` updatet alle matchende bases.
- [x] HTTP-API: `ShowGroupController` exposeert `variantLabel` per base.
- [x] UI: outlined MUI-chip op `GroupDetail` naast taal-chip wanneer label gezet is. Vitest-regressie-test.

### Sub-slice 38.3 — Live backfill + verificatie
- [x] `tmp/seed-variant-labels.sh`: Mindray 21018-FR/UK/DE → `4G`; LIFEPAK 11141/42/44/56/64/66 → `WiFi`; 11153/54 → `3G`; 11161/62 → `USB`.
- [x] `audit:names` herdraait — canonical-namen tonen nu het label (4G/WiFi/3G/USB) tussen model en suffix.
- [x] Sanity-check Mindray-groep 21011: 4G-bases (DE/FR/UK) en niet-4G-bases (21011/21019/21021) produceren geen identieke namen meer.

---

## Slice 39 — Missende varianten in AFAS aanmaken (`variants:fix-missing`)

Eindbeeld: CLI `variants:fix-missing [--group=<family-head>] [--apply] [--limit=N]` POST't `no_match`-varianten als nieuwe FbComposition-records met canonical naam + BOM. Default dry-run. Failures → `tmp/fix-variants-{datum}.csv`. Zie PLAN.md §20.

### Sub-slice 39.0 — PoC: werkende FbComposition POST-payload (✅ voltooid)
- [x] `tmp/poc-fb-composition-post.php`: 9 payload-shapes (A-I) iteratief getest tegen live AFAS.
- [x] Minimaal vereiste velden bevestigd: `ItCd`, `Ds`, `VaCt='1'` (Explosie), `Grp`, `BiUn='STK'`, `BiSaItCd`, `VaRc='1'`, `CrId='50002'`, `CsGc`, `StPrice=0`, FF_PARENT/FF_SYNC/FF_TONEN (UUIDs).
- [x] Itemcode-strategie bevestigd: `<baseSku>-<accessoireItemcode>` werkt als explicit `@ItCd`.
- [x] BOM-lines: in dezelfde POST via `Objects.FbCompositionPart.Element[]` (niet `FbCompositionLine`!). Required regelvelden: `VaIt` (`"Art"`/`"Sam"` o.b.v. type_id≠7/=7), `ItCd`, `QuUn`, `Qu`, `PrSe` (positie × 10).
- [x] DELETE-syntax bevestigd: `/connectors/FbComposition/FbComposition/ItCd/<code>` (zonder `@`).
- [x] Live geverifieerd op 11111-60212 (NL) en 11114-60212 (DE) in groep 10013. Beide compleet met BOM + sync-flags + basis- + staffel-prijzen.
- [x] `tmp/poc-fb-composition-post-NOTES.md` met werkende JSON-snippet, FF-UUIDs, veld-mapping, DELETE-syntax en sources.

### Sub-slice 39.1 — Domain/Application ✅
- [x] `VariantFixMissingPlan` VO + 4 input-validatie-tests.
- [x] `VariantFixMissingWriter`-contract + `VariantFixMissingFailedException`.
- [x] `InMemoryVariantFixMissingWriter` met `failOnItemcode`-param.
- [x] `FixMissingVariantsHandler`: filtert tegen `afas_samenstellingen` (echte missing, niet onbetrouwbare no_match-status), `--group`-filter, `VariantNamingPolicy` voor canonical naam, referentie-lookup, `--limit`.
- [x] `MissingVariantRow` uitgebreid met `familyHead` voor de --group-filter.
- [x] 6 handler-tests: dry-run, apply, skip-already-in-afas, group-filter, limit, failure-non-blocking.

### Sub-slice 39.2 — Infrastructure (HTTP-writer) ✅
- [x] `FbCompositionVariantPayloadBuilder` (pure functie) bouwt de PoC-payload met FF-UUIDs + hardcoded constants (VaCt=1, BiUn=STK, VaRc=1, CrId=50002, StPrice=0).
- [x] `VariantWriteContextLookup`-interface + `InMemoryVariantWriteContextLookup` (test) + `HttpVariantWriteContextLookup` (lazy-pulls Get_Artikelen + easylinq_stock_item, cache per run).
- [x] `HttpFbCompositionVariantWriter` — thin wrapper rond builder + AfasHttpClient::insertConnector.
- [x] Mapping `VaIt='Art'/'Sam'` via `easylinq_stock_item.type_id` (= 7 → Sam, anders Art).
- [x] Contract-test op de payload-builder met 18 assertions over de body-structuur. Geen live AFAS.
- [x] PHPStan groen, `make check` 354/800.

### Sub-slice 39.3 — CLI + wiring ✅
- [x] `variants:fix-missing [--group=<family-head>] [--apply] [--limit=N]`. Default dry-run met tabel (Itemcode | Canonical naam | BOM-items | Family-head) en sectie "overgeslagen" met reden per gefilterde rij.
- [x] Failures → `tmp/fix-variants-{datum}.csv` (mirror `names:fix-drift`).
- [x] Output toont expliciete instructie "Draai nu `afas:pull && prices:fix-missing --apply`" voor de prijs-stap (vooruitlopend op 39.4's chained-flow).
- [x] Wire in `bin/samenstellingen` (handler + HTTP-writer + payload-builder + lookup + command).
- [x] Smoke: dry-run op groep 10013 toont 2 echte missing (11112-60212 FR + 11113-60212 UK).

### Sub-slice 39.4 — Chained prijs-integratie ✅
- [x] `VariantSnapshotRefresher`-interface + HTTP-impl (`PullAfasVariantSnapshotRefresher` roept de bestaande `afas:pull` aan — niet targeted, ~30s per run; acceptabel voor typische `--limit=N`-rollouts).
- [x] `FixPriceMissing` + handler krijgen `?onlyForVariantItemcodes`-filter (null = alle, lege array = geen).
- [x] `FixMissingVariantsWithPricesHandler` orchestreert POST → refresh → scoped prices-fix. Slaat refresh + prices over bij dry-run, `--skip-prices` of geen-applied.
- [x] CLI `--skip-prices`-flag + nieuwe sectie "Chained prijzen" met basis/staffel-tabel na variants.
- [x] 4 nieuwe handler-tests (dry-run skip, chained apply, skip-prices, geen-applied).

### Sub-slice 39.5 — Live verificatie ✅
- [x] `--apply --limit=1` op groep 10013 → 11113-60212 (UK) end-to-end: variant POST + snapshot refresh + 2 basis-prijzen ingevoegd.
- [x] Auto-sync matched-count +1 (441 → 442) bevestigt herkenning van nieuwe variant.
- [x] Bekende beperking gedocumenteerd: snapshot-refresh gebeurt vóór de prijs-insert; net-ingevoegde prijzen zijn pas lokaal zichtbaar na een tweede `afas:pull`. AFAS heeft ze wel. Optionele follow-up: post-price refresh toevoegen voor instant lokale UI-consistentie.

---

## Slice 41 — Base-deduplicatie op afas_itemcode i.p.v. naam

Eindbeeld: `groups:import-portal-csv` is idempotent **ook na naam-mutaties in AFAS** (door bv. `names:fix-drift`). Bases worden gededupliceerd op `(group_id, afas_itemcode)` wanneer SKU aanwezig is, met fallback naar `(group_id, name)` voor legacy bases zonder SKU. Zie PLAN.md §21.

### Sub-slice 41.0 — Diagnose + cleanup historische duplicates ✅
- [x] Backup gemaakt vóór alles: `tmp/samenstellingen.sqlite.backup-20260601-090027`.
- [x] Diagnose: 3 duplicates in herstelde DB-state — 11144 (id 7+76), 21019 (id 29+77), 21020 (id 34+82). Eerder bevonden duplicate 11112 was alleen aanwezig na de bug-import en is door restore verdwenen.
- [x] Per duplicate: behouden = rij waarvan naam matcht met huidige AFAS-naam (76 / 77 / 82).
- [x] `tmp/dedupe-bases-20260601.sql` transactioneel uitgevoerd, 3 rijen verwijderd.
- [x] Verifieer: 0 `(group_id, afas_itemcode)`-duplicates over. `group_bases` 78 → 75. BOM + varianten cascade-deleted; symmetrisch met behouden rijen dus geen data-verlies.

### Sub-slice 41.1 — Schema + repository ✅
- [x] Migratie `0021_group_bases_unique_on_itemcode.sql`: SQLite recreate-table dans met partial-UNIQUE op `(group_id, afas_itemcode) WHERE afas_itemcode IS NOT NULL`. Naam-UNIQUE gedropt, vervangen door non-unique index.
- [x] `GroupBaseRepository::findByAfasItemcodeInGroup()` op interface + InMemory + Sqlite + 2 contract-tests.
- [x] `BaseAlreadyExistsException::forItemcodeInGroup()` toegevoegd; Sqlite-handler onderscheidt nu SKU-violation van legacy naam-violation.
- [x] InMemory: SKU-eerst-check; bases zonder SKU mogen dezelfde naam delen.
- [x] Bestaande tests aangepast: `passesThroughDuplicateBase` + `failsForDuplicateBaseName` vervangen door `allowsTwoBasesWithSameNameWhenNoSku`-variants. `ImportSamenstellingenCsvHandler` (legacy) doet app-level naam-dedupe ipv repo-exception.

### Sub-slice 41.2 — Import-handler refactor ✅
- [x] `ImportPortalCsvHandler::importRow()`: `findByAfasItemcodeInGroup` is leidend; fallback save met try/catch op SKU-conflict, naam-pad alleen voor SKU-loze rijen.
- [x] Bij match: skip insert, **naam blijft behouden** zodat `names:fix-drift`-canonical niet wordt overschreven.
- [x] Nieuwe TDD-test `secondImportRemainsIdempotentAfterAfasNameChange` (import → naam-mutatie buiten tool om → herimport → 0 nieuwe bases, id behouden).
- [x] Bestaande `secondImportIsIdempotentAndPreservesUserDefinedConfig` blijft groen.

### Sub-slice 41.3 — Live verificatie ✅
- [x] Backup `tmp/samenstellingen.sqlite.backup-pre-41.3-20260601-093046` (75 bases).
- [x] `bin/samenstellingen group:import-portal-csv "AEDs op Reseller - Blad2.csv"` opnieuw gedraaid.
- [x] 75 → 78 bases (3 nieuwe uit de gefilterde CSV). **0 duplicates** op `(group, afas_itemcode)`. 11112 nog 1 rij (id=11, bewaarde naam, geen duplicate ondanks AFAS naam-mismatch).

---

## Slice 42 — Producttype + Subcategorie + Merknaam op nieuwe varianten

Eindbeeld: `variants:fix-missing` zet de webshop-relevante free-fields **Producttype** (`U5C3C…`), **Subcategorie** (`U79C8…`), **Merknaam** (`UE10D…`) direct goed op nieuwe FbComposition-records. Bron: de bestaande matched referentie-variant in dezelfde groep, via `PowerBI_Item`-GetConnector (die deze velden wel exposeert). Zie PLAN.md §22.

### Sub-slice 42.0 — Lookup uitbreiden ✅
- [x] `VariantWriteContextLookup::lookupReferenceFields()` retourneert `{grp, cbsCode, productType, subcategorie, merknaam}`.
- [x] `HttpVariantWriteContextLookup`: lazy-pull `PowerBI_Item` (eerste keer) + cache per CLI-run.
- [x] `InMemoryVariantWriteContextLookup`: fixture-shape uitgebreid; bestaande payload-builder-test up-to-date.

### Sub-slice 42.1 — Payload-builder uitbreiden ✅
- [x] `FbCompositionVariantPayloadBuilder` voegt 3 UUIDs toe (`U5C3C…`, `U79C8…`, `UE10D…`) wanneer lookup-data ze heeft. Lege string → veld weggelaten.
- [x] Unit-test uitgebreid met 3 nieuwe assertions (15 → 18). `make check` groen.

### Sub-slice 42.1bis — Enum-resolver ✅
- [x] Live-test toonde dat PowerBI_Item descriptions geeft (`"AED pakket"`, `"350P"`, `"Heartsine"`) maar UpdateConnector IDs eist (`"08"`, `"17"`, `"01"`).
- [x] `AfasHttpClient::getMetainfoUpdate()` toegevoegd.
- [x] `HttpVariantWriteContextLookup` pullt metainfo, bouwt description→id mapping per UUID, en resolved tijdens cache-build. Geen hardcoding.

### Sub-slice 42.2 — Live verificatie ✅
- [x] `variants:fix-missing --group=10142 --apply --limit=1` op Defibtech: `11142-FR-91116` aangemaakt + 3 prijzen, 0 gefaald.
- [x] PowerBI_Item-pull bevestigt: PT#01 = `AED pakket`, PT#02 = `AED pakket overig`, Merknaam = `Defibtech` — identiek aan referentie `11142-FR`.

---

## Slice 43 — Auto-sync family-head bij `afas:pull`

Eindbeeld: `afas:pull` detecteert wanneer Itemcode_Parent in AFAS verschoven is voor een groep en updatet `groups.family_head_itemcode` automatisch. Sanity rails: ≥3 bases nodig, nieuwe parent moet bestaan in AFAS, geen dubbel-claim. Log per shift. Zie PLAN.md §23.

### Sub-slice 43.0 — Detector (pure functie) ✅
- [x] `FamilyHeadShiftDetector::detect(list<Group>, array<int|string, iterable<GroupBase>>, list<AfasSamenstelling>): list<FamilyHeadShift>`.
- [x] 6 TDD-tests: stable, shift bij 3+ bases, geen shift bij <3, geen shift bij niet-bestaande parent, geen shift bij dubbel-claim, SKU-loze bases tellen niet.

### Sub-slice 43.1 — Repository ✅
- [x] `GroupRepository::updateFamilyHeadItemcode()` op interface + InMemory + Sqlite.
- [x] Contract-tests: shift werkt, GroupNotFoundException bij onbekende oude head, GroupAlreadyExistsException bij conflict.

### Sub-slice 43.2 — Integratie in pull ✅
- [x] `PullAfasSamenstellingenHandler` neemt `GroupRepository` + `GroupBaseRepository` + `FamilyHeadShiftDetector` op.
- [x] Na snapshot-replace, vóór `syncAllGroups`: detect + apply per shift; log naar stderr.
- [x] `PullAfasSamenstellingenResult` krijgt `int $familyHeadShiftsApplied`.
- [x] Wiring in `bin/samenstellingen` bijgewerkt.

### Sub-slice 43.3 — Live verificatie ✅
- [x] Geen handmatige AFAS-wijziging nodig: live pull detecteerde direct twee bestaande shifts: Mindray C2 semi `21013 → 21013-UK` (3 bases) en C2 vol `21014 → 21014-UK` (3 bases).
- [x] Heartsine-groepen (10013/10023/11131) en alle andere stable → 0 false positives.
- [x] DB verifieerde: `groups.family_head_itemcode` bijgewerkt; bases blijven gekoppeld via group_id (FK).
- [x] Note: de allereerste pull-run liet de update pas op tweede run committen (PDO connection state na meerdere replaceSnapshot-transacties). Idempotent — geen data-verlies. Mogelijke follow-up: shifts in eigen expliciete TX wrappen.

---

## Slice 20 — Reconciliation in portal-CSV-import (vervang wipe)

Eindbeeld: portal-CSV-import is idempotent. Bestaande groepen behouden hun `model_name` en `group_accessoires` over imports heen; alleen groepen die niet meer in de CSV staan worden opgeruimd. Geen `ToolDataWiper` meer in de import-flow.

Reden: de huidige wipe is overcompensatie uit slice 8 — toen was er nog geen handmatige seed-data op `groups`. Sindsdien zijn `model_name` en `group_accessoires` toegevoegd, en die verdwijnen sindsdien stilletjes bij elke import. Targeted reconciliation is wat we al doen voor `accessoires` en `bom_blacklist` (uit de wipe gehaald in slice 12) — dezelfde behandeling voor `groups`+`group_bases`.

### Fase 1 — Repository-uitbreidingen
- [x] `GroupRepository::delete(string $familyHeadItemcode)` + InMemory + Sqlite + contracttest.

### Fase 2 — Handler refactor
- [x] `ImportPortalCsvHandler` doet géén `wiper->wipe()` meer; `ToolDataWiper`-dep is uit de constructor verwijderd.
- [x] `reconcileOrphanGroups` deletet groepen die niet meer in CSV staan.
- [x] Groep-save is idempotent via `findByFamilyHeadItemcode` (skipt bestaande).
- [x] Base-save is idempotent via bestaande `BaseAlreadyExistsException`-catch.
- [x] `regenerateForGroup` blijft draaien — koppelingen overleven, matrix klopt.

### Fase 3 — Tests
- [x] Bestaande tests groen (8 → bag zonder wiper).
- [x] `secondImportIsIdempotentAndPreservesUserDefinedConfig` (model_name + group_accessoires overleven).
- [x] `removesGroupsThatNoLongerAppearInCsv` (cascade-delete).

### Fase 4 — Lint + live
- [x] `make check` groen (202 tests / 472 assertions).
- [x] Live: herimport — voor/na exact gelijk: 21 groepen, 154 koppelingen, 495 varianten (270/225), 21 model_names. Geen verlies.

---

## Slice 13 — `afas:create-missing` met per-taal naam-templating + dry-run default

Eindbeeld: `afas:create-missing [--apply] [--limit=N]` itereert over alle variant-rijen met `afas_status='no_match'` en construeert per rij een `FbComposition`-payload. Gebruikt `base.language_code` (uit slice 11) om de variant-naam te bouwen volgens AFAS-conventie per taal.

**Default = dry-run** (CLAUDE.md regel). `--apply` schrijft écht naar AFAS.

Per-taal naam-templates (heuristiek op base.name):
- NL: vervang ` incl.safeset en stickerset` door ` met {accessoire.label}` (of ` met  …` als de bron al een dubbele spatie heeft).
- FR: vervang ` avec safesett et signalétique` (of ` avec safeset et signalétique`) door `  avec {accessoire.label}` (dubbele spatie, conform AFAS).
- DE/EN/UK: append `+ {accessoire.label}` (uit AFAS-conventie van Philips/Cardiac-bases).
- Onbekend / NULL: fallback `"{base.name} + {accessoire.label}"`.

Bekende onzekerheden:
- Exacte FbComposition INSERT-payload-shape (BOM-lines onder `Objects`/`FbCompositionLines`?).
- Free field UUIDs voor `Itemcode_Parent`, `Sync_Reseller_NL`, `Tonen_Reseller_NL` hergebruiken uit afas-connector-tools.

### Fase 1 — Domein + writer-abstractie
- [ ] `NewAfasSamenstelling` value object: `itemcode`, `name`, `itemcodeParent`, `syncResellerNl`, `tonenResellerNl`, `bomItemcodes`.
- [ ] `AfasSamenstellingenWriter` interface met `create(NewAfasSamenstelling): void`.
- [ ] `AfasWriteFailedException`.

### Fase 2 — Naam-templating
- [ ] `VariantNamingPolicy` domain service: `name(GroupBase $base, Accessoire $accessoire): string`. Past per-taal regels toe op basis van `$base->languageCode`.

### Fase 3 — Writer-implementaties
- [ ] `LoggingDryRunWriter` (print payload).
- [ ] `HttpAfasSamenstellingenWriter` (echte call). Hergebruikt UUIDs uit afas-connector-tools (`U298663…` voor Itemcode_Parent etc.).
- [ ] `InMemoryAfasSamenstellingenWriter` (tests).

### Fase 4 — Application
- [ ] `CreateMissingSamenstellingen` + handler. Itereert missing variants, gebruikt `VariantNamingPolicy`, roept writer aan.
- [ ] `CreateMissingSummary` met counts en errors.

### Fase 5 — CLI
- [ ] `CreateMissingAfasCommand` — `afas:create-missing [--apply] [--limit=N]`.
- [ ] Default dry-run; `--apply` switcht naar HttpWriter + vraagt confirmation.

### Fase 6 — Handmatige verificatie + commit
- [ ] Dry-run check: payloads ogen goed (NL/FR namen kloppen met AFAS-conventie).
- [ ] `--apply --limit=1` op één veilige rij, controle in AFAS.
- [ ] Stapsgewijs opschalen.
- [ ] Na succesvolle apply: `afas:pull` + `group:sync-afas`; missing-count daalt.
- [ ] `make check` is groen.
- [ ] **Commit + push** "slice 12: afas:create-missing met per-taal naam-templating".

---

## Slice 14 — Web UI (read-only viewer)

Eindbeeld: een lokaal te starten browser-UI bovenop dezelfde SQLite-database. Twee pagina's via React Router — een groepen-lijst en een groep-detail met uitklapbare base-Accordion. Backend = Slim 4 PHP-API achter nginx + php-fpm (Docker compose voor de containers, Vite dev-server op host voor de frontend). Frontend = React + TypeScript + MUI v6.

**Beslissingen** (uit PLAN.md §10):
- MUI only (geen Tailwind).
- React Router voor `/` en `/groups/:familyHead`.
- nginx + php-fpm via `docker-compose.yml`.
- Geen issues-tab in eerste versie.

Strict read-only. Geen mutaties van AFAS/DB. Auth niet nodig (lokaal, single user).

### Fase 1 — Bootstrap-refactor + minimale PHP API
- [x] Composer-dep `slim/slim ^4` + `slim/psr7`.
- [x] Gedeelde bootstrap: `src/Bootstrap/Container.php` waar zowel CLI als HTTP de repos uit halen. Refactor `bin/samenstellingen`; CLI-gedrag onveranderd.
- [x] `src/Interface/Http/ListGroupsController` — `GET /api/groups` → `[{familyHead, name, baseCount, baseItemCount}]`.
- [x] `src/Interface/Http/ShowGroupController` — `GET /api/groups/{familyHead}` → `{familyHead, name, bases: [{id, name, languageCode, items: [{itemcode, label}]}]}`.
- [x] `public/index.php` front-controller die Slim opbouwt (relatieve DB-paden ankeren aan project-root).
- [x] Integratie-tests in `tests/Interface/Http/ApiTest.php` die Slim in-process booten via `TestDatabase::container()`.

### Fase 2 — Lokale serve-stack (nginx + php-fpm + docker-compose)
- [x] `docker/nginx.conf` — root `public/`, `/api/*` via `fastcgi_pass` naar fpm.
- [x] `docker/Dockerfile.fpm` — `php:8.5-fpm-alpine` + sqlite3-extensie + composer.
- [x] `docker-compose.yml` — services `web` (nginx) op :8080 + `fpm`, bind-mount op de repo.
- [x] `make ui-up` / `make ui-down` / `make ui-logs`-targets.

### Fase 3 — Vite + React + MUI skelet + groepen-lijst
- [x] `web/` directory met `package.json`, `vite.config.ts`, `tsconfig.json`.
- [x] Deps: React 18, React Router 6, MUI v6 + DataGrid, TanStack Query, Vitest + Testing Library.
- [x] Vite-proxy: `/api/*` → `http://localhost:8080`.
- [x] `web/src/main.tsx` — RouterProvider + QueryClientProvider + ThemeProvider.
- [x] `web/src/pages/GroupsList.tsx` — DataGrid met klik-naar-detail.
- [x] Vitest-test `GroupsList.test.tsx`.

### Fase 4 — Groep-detail-pagina
- [x] `web/src/pages/GroupDetail.tsx` met breadcrumb, MUI Accordion, BOM-tabel.
- [x] Loading skeleton + error-state (MUI Alert).
- [x] Vitest-test `GroupDetail.test.tsx`.

### Fase 5 — Afronding
- [x] 404-page voor onbekende routes (`web/src/pages/NotFound.tsx`).
- [x] `make ui` — combineert containers + Vite dev-server.
- [x] README-sectie met quickstart.
- [x] `make check` groen (151 PHP-tests / 347 assertions) en `npm --prefix web run test` groen (2 vitest tests).
- [x] **Commit + push** "slice 14: web UI read-only viewer".

---

## Beslissingen

- CLI-library: `symfony/console`.
- Itemcode-normalisatie: trim-only.
- Commando-namespaces: `group:` voor alles dat een groep raakt (`group:create`, `group:show`, `group:add-base`, `group:add-accessoire`) en `accessoire:` voor catalogusbeheer (`accessoire:create`).
- Alle CLI-commando's gaan via de application-laag (ook reads). Eén consistent patroon: CLI → handler → repo.
- Foreign keys in slice 3: surrogate `group_id` / `accessoire_id` (INTEGER) → `groups(id)` / `accessoires(id)`. Rename-robuust. De repository-API blijft op `string $groupName` / `string $itemcode` — de SQLite-impl vertaalt naar id.
- Bases zijn 1:N (taal+model-specifiek per groep). Accessoires zijn M:N: eigen catalogustabel + join-tabel, zodat dezelfde accessoire (bv. ARKY witte binnenkast `60112`) bij meerdere groepen kan horen zonder duplicatie.
- `GroupBase.itemcode` is de **AED-component itemcode** (50013 NL, 50001 FR), niet de AFAS samenstelling-SKU. De samenstelling-SKU is iets dat in AFAS bestaat en straks per variant-rij in `group_variants.afas_samenstelling_itemcode` wordt opgeslagen na een AFAS-sync. — **Vervangen in slice 5**: bases verliezen het itemcode-anker; alle items (incl. de AED) staan in `group_base_items`.
- Varianten = (base × {null ∪ accessoires}). Worden in `group_variants` opgeslagen zodat AFAS-metadata (SKU, status) er per rij aan kan worden gekoppeld. Auto-regenereren bij elke add-base / add-accessoire. Hard CASCADE.
- Slice 5 refactor: alle business-lookups via id (groep = `family_head_itemcode` UNIQUE; base = surrogate `id`; accessoire = `itemcode` UNIQUE). Geen lookup op naam, geen `language_code` op base. Demo-data uit slice 3-4 gaat verloren.
- Slice 3 modelleert bases/accessoires als losse entiteiten met eigen repositories, niet als aggregate-graph onder `Group`. Aggregate-refactor blijft optioneel voor wanneer variant-generatie zelf in scope komt.

---

## Buiten scope (toekomstige slices)

- Verwijderen of aanpassen van bases en accessoires (slice 3 ondersteunt alleen toevoegen).
- Listen van alle groepen (`group:list`).
- Groep importeren vanuit AFAS (`group:import`).
- AFAS-reads of -writes.
- `naming_template`-veld op `groups` en naam-normalisatie.
- Uniciteit van `family_head_itemcode` over groepen heen.
- Aggregate-refactor van `Group` met bases/accessoires als interne collecties.
- Variant-derivering (base × accessoire-matrix → SKU + BOM).
