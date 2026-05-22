# Samenstellingen Manager — Working Agreement

PHP 8.5 application for managing Defibrion's AED-pakket samenstellingen in AFAS, built with clean architecture, dependency injection, and strict TDD.

## Workflow (must follow in order)

1. **Ideas → `PLAN.md`.** Every idea, requirement, or design decision lands in `PLAN.md` first. Nothing implementation-related happens until the user reviews and approves the plan.
2. **Plan → `TODO.md`.** After the user approves `PLAN.md`, translate it into checkable todos grouped under logical phases (Markdown checkboxes: `- [ ] ...`). **Stop and wait for explicit approval** of the TODO before writing any code — don't roll straight from writing the TODO into implementing it, even when the previous slices were green-lit quickly. The user reads the TODO carefully and may want to adjust scope before code lands.
3. **One todo at a time, TDD red-green-refactor.** Pick the first unchecked todo, then:
   - **Red:** write a failing test that expresses the behavior.
   - **Green:** write the minimum code to make it pass.
   - **Refactor:** clean up while tests stay green.
4. **Verify by actually running.** Run the full test suite *and* exercise the feature manually (CLI, audit against a real AFAS snapshot, etc.) before claiming success. Type checks and green tests alone are not proof the feature works.
5. **Mark and move on.** Tick the todo in `TODO.md`, then pick the next one.
6. **Per-phase commit + push.** When all todos in a phase are checked, `git commit` with a clear message and `git push`. No commits mid-phase unless the user asks.

## Code rules

- **PHP 8.5+** features encouraged where they make code clearer (readonly properties, enums, asymmetric visibility, property hooks, pipe operator, etc.).
- **Strict types** at the top of every PHP file: `declare(strict_types=1);`.
- **Dependency injection everywhere.** No `new` of collaborators inside business logic, no service locators reaching into the container at runtime. Wire dependencies through constructors; configure them in a single container/bootstrap.
- **Depend on contracts (interfaces we own)** at collaboration points, not on concrete third-party classes. Especially for the AFAS client — wrap it behind an interface so audits and generators can run against an in-memory fake in tests. Prefer a well-tested PHP library accessed through a contract over reinventing it ourselves.
- **Layered code organisation**: `domain → application → infrastructure → interface (CLI)`. The folders are a convention for finding things; the rule that actually matters is the contract rule above.
- **Small classes, single responsibility.** Prefer composition over inheritance.

## Testing rules

- **PHPUnit** as the test runner (latest stable compatible with PHP 8.5).
- **TDD only** — production code is written to satisfy a failing test. No "I'll add tests later."
- **Mock by implementing interfaces.** When a collaborator needs to be faked, create a real class (e.g. `InMemoryAfasClient implements AfasClient`, `InMemoryGroupRepository implements GroupRepository`). Do **not** use mocking frameworks to override methods on concrete classes, and do **not** redefine/override functions.
- **Test pyramid**: many fast unit tests on the domain (group/base/accessoire model, variant derivation, template rendering, audit rules), fewer integration tests at boundaries (SQLite, AFAS client), a thin end-to-end layer.
- **Tests live next to their layer** in a parallel `tests/` tree mirroring `src/`.
- **Never hit real AFAS from tests.** All AFAS interactions go through the in-memory fake. The few real-AFAS smoke tests live in a separate suite and run only on demand.
- **Do not write tests for migrations themselves.** The schema is verified implicitly by the repository + snapshot integration tests that depend on it — if those pass, the migrations produced a usable schema.
- **Migrate the test database once per phpunit process** (via `tests/bootstrap.php`), then `TRUNCATE` / `DELETE FROM` between tests for speed. Never call the migrations runner from `setUp()`.
- **`make check` must be green when you finish a phase, even for errors that pre-existed your changes.** If `composer stan`, `make lint`, etc. flag something while you're working on Phase X, fix the underlying cause as part of Phase X — don't shrug it off as "not from my changes". Exception: if the fix would balloon the scope, surface it to the user and propose a dedicated cleanup phase instead of pretending it isn't there.

## Project conventions

- Source in `src/`, tests in `tests/`, executable scripts in `bin/`.
- PSR-4 autoloading via Composer.
- `composer test`, `composer lint`, `composer stan` (or similar) wired up early.
- **Use `make <target>` over raw commands.** The `Makefile` consolidates every routine recipe (`make test`, `make check`, `make audit`, `make sync`, …) so changes to flags happen in one place. `make help` lists everything.
- No code without a corresponding plan entry and todo.
- **Scratch files go in `tmp/` at the project root, not `/tmp`.** Logs from sync jobs, ad-hoc fixtures, audit-diff dumps, etc. `tmp/` is `.gitignore`d.
- **Reuse patterns from `afas-connector-tools`** for AFAS reads/writes — `ClientFactory::fromEnv()`, composite routing on `easylinq_stock_item.type_id === '7'`, free-field IDs for `Itemcode_Parent` / `Sync_Reseller_NL` / `Tonen_Reseller_NL`, and the dry-run + failure-CSV pattern of the `set-*` scripts. Don't reinvent.
- **AFAS writes are dry-run by default.** Every command that mutates AFAS (variant generation, BOM updates, name normalisation, price writes) must require an explicit `--apply` flag. Default output is a diff plus the proposed change set. This mirrors the convention in `afas-connector-tools/bin/`.
- **Server-side AFAS bool filters are unreliable.** Always pull-and-filter in PHP (see CLAUDE.md of `afas-connector-tools`).
- **Per-language naming templates are the source of truth, not the current AFAS data.** AFAS contains drift (typos, casing, missing prefixes — see `PLAN.md` §9.1). The tool normalises *toward* the templates, never the reverse. Templates only change with explicit user approval.
- **Never drop or recreate a database without explicit user consent — including the local SQLite snapshot.** The snapshot can take minutes to rebuild from AFAS and may hold the state used by a pending bulk operation. Read-only inspection is fine without asking.
- **Family-head is a family-tag, not a parent.** `Itemcode_Parent` in AFAS points to an arbitrary sibling that anchors the family. All bases and variants of a group share the same family-head itemcode. Audits depend on this invariant.

## Files Claude should keep up to date

- `PLAN.md` — living design document.
- `TODO.md` — phased checklist driven by `PLAN.md`.
- `CLAUDE.md` — this file. Update only when the user changes the working agreement.
