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
- [ ] **Commit + push** "slice 7: bulk-import bases en accessoires uit CSV".

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
