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
- [ ] **Commit + push** "fase 0: scaffolding".

## Fase 1 — Domein: `Group` value object

- [ ] `Group` readonly class in `src/Domain/Group/Group.php` met `name` en `familyHeadItemcode`, beide niet-leeg (whitespace telt als leeg), getrimd. Validatie in de constructor met `InvalidArgumentException`.

## Fase 2 — Repository-contract + in-memory fake

- [ ] `GroupRepository` interface in `src/Domain/Group/` met `save(Group $g): void` en `findByName(string $name): ?Group`.
- [ ] `GroupAlreadyExistsException` in `src/Domain/Group/`, gegooid bij een duplicate naam.
- [ ] `InMemoryGroupRepository` in `src/Infrastructure/Persistence/InMemory/`, met round-trip-gedrag en duplicate-detectie.

## Fase 3 — SQLite-repository

- [ ] Migratiebestand `migrations/0001_create_groups.sql` met de `groups`-tabel uit PLAN.md §3 (`naming_template` weglaten voor nu).
- [ ] Kleine migrator in `src/Infrastructure/Persistence/Sqlite/Migrator.php` die SQL-bestanden in volgorde toepast.
- [ ] `SqliteGroupRepository implements GroupRepository`, geparametriseerd op een PDO-instance, met dezelfde gedragsgaranties als de in-memory variant.
- [ ] `tests/bootstrap.php` migreert de test-database één keer per phpunit-process; tests cleanen rijen tussen runs, geen re-migrate.
- [ ] Gedeelde abstracte testbasis zodat beide repository-implementaties dezelfde contract-tests draaien.

## Fase 4 — Application: define-group use case

- [ ] `DefineGroup` command-DTO in `src/Application/Group/` met `name` en `familyHeadItemcode`.
- [ ] `DefineGroupHandler` in `src/Application/Group/` met `GroupRepository` via de constructor; legt een `Group` vast en retourneert hem; laat `GroupAlreadyExistsException` doorgaan.

## Fase 5 — CLI: het define-group commando

- [ ] `symfony/console` toevoegen aan `composer.json`.
- [ ] `DefineGroupCommand` in `src/Interface/Cli/` (werknaam `group:define <name> <family-head-itemcode>`) die `DefineGroupHandler` aanroept; vangt `GroupAlreadyExistsException` op en rendert "Groep '<naam>' bestaat al" met niet-nul exit code.
- [ ] `bin/samenstellingen` als dunne bootstrap: één gedeelde PDO, repo, handler, command — geregistreerd in de Symfony Console application.
- [ ] **Handmatige verificatie**: `bin/samenstellingen group:define "Reanibex 100 Semi-Auto" 52112` tegen een echt lokaal SQLite-bestand, dan opnieuw uitvoeren voor de duplicate-melding. Inspecteer met `sqlite3`.
- [ ] `make check` is groen.
- [ ] **Commit + push** "slice 1: groep definiëren via CLI".

---

## Buiten scope voor deze slice (latere slices)

- Groepen tonen of lijst opvragen.
- Bases / accessoires aan een groep toevoegen.
- Groep importeren vanuit AFAS.
- Welke AFAS-reads of -writes dan ook.
- `naming_template`-veld op `groups` (nog geen normalisatie-werk).
- Uniciteit van `family_head_itemcode` over groepen heen — huidige regel is "naam is uniek"; opnieuw bekijken zodra een latere slice dit nodig heeft.

## Openstaande vragen voordat we Fase 1 starten

1. CLI-library: is `symfony/console` ok, of voorkeur voor iets lichters (bv. `league/climate`, eigen parser)?
2. Itemcode-normalisatie: laten zoals het is (alleen trim) of uppercase / non-alphanum strippen?
3. Commando-werknaam: `group:define` ok, of een andere namespace (`groups:define`, `define-group`, …)?
