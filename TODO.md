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

## Slice 3 — bases en accessoires toevoegen aan een groep

Eindbeeld: na `group:create` kun je met `group:add-base` en `group:add-accessoire` componenten aan een groep koppelen. `group:show` toont vervolgens ook deze koppelingen.

Design-keuzes (zie ook §Beslissingen):
- `GroupBase` value object met `itemcode`, `languageCode`, `name`.
- `GroupAccessoire` value object met `itemcode`, `label`.
- Foreign key per row: surrogate `group_id` (INTEGER) → `groups(id)`. De repository-API neemt nog steeds een `string $groupName` aan; de SQLite-implementatie vertaalt die intern naar `group_id`. Zo blijft de id een infrastructure-detail.
- Bases en accessoires krijgen ieder hun eigen repository (geen aggregate-graph in deze slice).

### Fase 1 — Migraties
- [ ] `migrations/0002_create_group_bases.sql`: tabel `group_bases (group_id, itemcode, language_code, name)`, PK `(group_id, itemcode)`, FK `group_id → groups(id) ON DELETE CASCADE`.
- [ ] `migrations/0003_create_group_accessoires.sql`: tabel `group_accessoires (group_id, itemcode, label)`, PK `(group_id, itemcode)`, FK `group_id → groups(id) ON DELETE CASCADE`.

### Fase 2 — Domein
- [ ] `GroupBase` readonly value object in `src/Domain/Group/` met `itemcode`, `languageCode`, `name` — alle drie niet-leeg, getrimd, anders `InvalidArgumentException`.
- [ ] `GroupAccessoire` readonly value object in `src/Domain/Group/` met `itemcode` en `label` — beide niet-leeg, getrimd.
- [ ] `BaseAlreadyExistsException` en `AccessoireAlreadyExistsException` in `src/Domain/Group/`. (`GroupNotFoundException` bestaat al uit slice 2.)

### Fase 3 — Repository-contracten + in-memory fakes
- [ ] `GroupBaseRepository` interface met `saveForGroup(string $groupName, GroupBase $base): void` (throws bij duplicate of onbekende groep) en `findAllForGroup(string $groupName): array<GroupBase>`.
- [ ] `GroupAccessoireRepository` interface met dezelfde structuur voor accessoires.
- [ ] `InMemoryGroupBaseRepository` en `InMemoryGroupAccessoireRepository` — checken groep-bestaan via een meegegeven `GroupRepository`.
- [ ] Gedeelde abstracte contract-testbases per repo-type, draaien tegen in-memory én SQLite.

### Fase 4 — SQLite-implementaties
- [ ] `SqliteGroupBaseRepository` en `SqliteGroupAccessoireRepository`: FK-violations gemapt naar `GroupNotFoundException`, UNIQUE-violations naar de duplicate-excepties.
- [ ] `TestDatabase::truncate()` schrapt nu ook `group_bases` en `group_accessoires`.

### Fase 5 — Application use cases
- [ ] `AddBaseToGroup` command-DTO + `AddBaseToGroupHandler` (`groupName`, `itemcode`, `languageCode`, `name`).
- [ ] `AddAccessoireToGroup` command-DTO + `AddAccessoireToGroupHandler` (`groupName`, `itemcode`, `label`).
- [ ] Handlers laten domein-excepties doorgaan voor CLI-mapping.

### Fase 6 — CLI commando's
- [ ] `AddBaseCommand` (commando `group:add-base <group> <itemcode> <language-code> <name>`) mapt excepties op exit codes met duidelijke meldingen.
- [ ] `AddAccessoireCommand` (commando `group:add-accessoire <group> <itemcode> <label>`).
- [ ] `bin/samenstellingen` registreert beide.

### Fase 7 — Show-output uitbreiden
- [ ] `ShowGroupCommand` toont nu ook een tabel met bases (itemcode, taal, naam) en een tabel met accessoires (itemcode, label); lege groepen krijgen `(geen bases)` / `(geen accessoires)`.

### Fase 8 — Handmatige verificatie + commit
- [ ] `bin/samenstellingen group:add-base "Reanibex 100 Semi-Auto" 50013 NL "Reanibex 100 Semi-Automatic AED Nederlands"` slaagt; duplicate → exit 1; onbekende groep → exit 1.
- [ ] `bin/samenstellingen group:add-accessoire "Reanibex 100 Semi-Auto" 60112 "ARKY witte binnenkast"` slaagt.
- [ ] `bin/samenstellingen group:show "Reanibex 100 Semi-Auto"` toont de groep met bases en accessoires.
- [ ] `make check` is groen.
- [ ] **Commit + push** "slice 3: bases en accessoires beheren via CLI".

---

## Beslissingen

- CLI-library: `symfony/console`.
- Itemcode-normalisatie: trim-only.
- Commando-namespace: `group:` (`group:create`, `group:show`, `group:add-base`, `group:add-accessoire`).
- Alle CLI-commando's gaan via de application-laag (ook reads). Eén consistent patroon: CLI → handler → repo.
- Foreign keys in slice 3: surrogate `group_id` (INTEGER) → `groups(id)`. Rename-robuust. De repository-API blijft op `string $groupName` — de SQLite-impl vertaalt naar id.
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
