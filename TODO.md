# TODO — Slice 1: groep definiëren (naam + family-head itemcode)

Bron: `PLAN.md` §2 (conceptueel model) + §3 (datamodel) + §4 (functionaliteit: "Groepen beheren").

Eindbeeld: een CLI-commando neemt een groepnaam en een family-head itemcode en legt een nieuwe groep vast in de lokale SQLite-database. Nog geen AFAS-calls. Validatie, domeinmodel, repository en CLI-laag worden allemaal gedekt door tests.

Commando-namen worden later vastgesteld (PLAN.md §4) — onderstaand staan werknamen, te hernoemen later. Hetzelfde geldt voor de bin-scriptnaam; we gebruiken `bin/samenstellingen` als plaatsvervanger.

Per todo geldt TDD red-green-refactor; dat hoeft niet als aparte stap te staan.

---

## Fase 0 — Project scaffolding

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

## Fase 1 — Domein: `Group` value object

- [x] `Group` readonly class in `src/Domain/Group/Group.php` met `name` en `familyHeadItemcode`, beide niet-leeg (whitespace telt als leeg), getrimd. Validatie in de constructor met `InvalidArgumentException`.

## Fase 2 — Repository-contract + in-memory fake

- [x] `GroupRepository` interface in `src/Domain/Group/` met `save(Group $g): void` en `findByName(string $name): ?Group`.
- [x] `GroupAlreadyExistsException` in `src/Domain/Group/`, gegooid bij een duplicate naam.
- [x] `InMemoryGroupRepository` in `src/Infrastructure/Persistence/InMemory/`, met round-trip-gedrag en duplicate-detectie.

## Fase 3 — SQLite-repository

- [x] Migratiebestand `migrations/0001_create_groups.sql` met de `groups`-tabel uit PLAN.md §3 (`naming_template` weglaten voor nu).
- [x] Kleine migrator in `src/Infrastructure/Persistence/Sqlite/Migrator.php` die SQL-bestanden in volgorde toepast.
- [x] `SqliteGroupRepository implements GroupRepository`, geparametriseerd op een PDO-instance, met dezelfde gedragsgaranties als de in-memory variant.
- [x] `tests/bootstrap.php` migreert de test-database één keer per phpunit-process; tests cleanen rijen tussen runs, geen re-migrate.
- [x] Gedeelde abstracte testbasis zodat beide repository-implementaties dezelfde contract-tests draaien.

## Fase 4 — Application: create-group use case

- [x] `CreateGroup` command-DTO in `src/Application/Group/` met `name` en `familyHeadItemcode`.
- [x] `CreateGroupHandler` in `src/Application/Group/` met `GroupRepository` via de constructor; legt een `Group` vast en retourneert hem; laat `GroupAlreadyExistsException` doorgaan.

## Fase 5 — CLI: het create-group commando

- [x] `symfony/console` toevoegen aan `composer.json`.
- [x] `CreateGroupCommand` in `src/Interface/Cli/` (commando `group:create <name> <family-head-itemcode>`) die `CreateGroupHandler` aanroept; vangt `GroupAlreadyExistsException` op en rendert "Groep '<naam>' bestaat al" met niet-nul exit code.
- [x] `bin/samenstellingen` als dunne bootstrap: één gedeelde PDO, repo, handler, command — geregistreerd in de Symfony Console application.
- [x] **Handmatige verificatie**: `bin/samenstellingen group:create "Reanibex 100 Semi-Auto" 52112` tegen een echt lokaal SQLite-bestand, dan opnieuw uitvoeren voor de duplicate-melding. Inspecteer met `sqlite3`.
- [x] `make check` is groen.
- [ ] **Commit + push** "slice 1: groep definiëren via CLI".

---

## Buiten scope voor deze slice (latere slices)

- Groepen tonen of lijst opvragen.
- Bases / accessoires aan een groep toevoegen.
- Groep importeren vanuit AFAS.
- Welke AFAS-reads of -writes dan ook.
- `naming_template`-veld op `groups` (nog geen normalisatie-werk).
- Uniciteit van `family_head_itemcode` over groepen heen — huidige regel is "naam is uniek"; opnieuw bekijken zodra een latere slice dit nodig heeft.

## Beslissingen

- CLI-library: `symfony/console`.
- Itemcode-normalisatie: trim-only.
- Commando-naam: `group:create`.
