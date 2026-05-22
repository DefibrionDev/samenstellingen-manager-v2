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
- [ ] **Commit + push** "slice 3: bases en accessoires beheren via CLI".

---

## Beslissingen

- CLI-library: `symfony/console`.
- Itemcode-normalisatie: trim-only.
- Commando-namespaces: `group:` voor alles dat een groep raakt (`group:create`, `group:show`, `group:add-base`, `group:add-accessoire`) en `accessoire:` voor catalogusbeheer (`accessoire:create`).
- Alle CLI-commando's gaan via de application-laag (ook reads). Eén consistent patroon: CLI → handler → repo.
- Foreign keys in slice 3: surrogate `group_id` / `accessoire_id` (INTEGER) → `groups(id)` / `accessoires(id)`. Rename-robuust. De repository-API blijft op `string $groupName` / `string $itemcode` — de SQLite-impl vertaalt naar id.
- Bases zijn 1:N (taal+model-specifiek per groep). Accessoires zijn M:N: eigen catalogustabel + join-tabel, zodat dezelfde accessoire (bv. ARKY witte binnenkast `60112`) bij meerdere groepen kan horen zonder duplicatie.
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
