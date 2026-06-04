# samenstellingen-manager

Beheertool voor Defibrion's AED-samenstellingen in AFAS. PHP 8.5 CLI + (sinds slice 14) een lokale read-only web-UI bovenop dezelfde SQLite-snapshot.

Zie `PLAN.md` + `TODO.md` voor de huidige cyclus (WooCommerce-koppeling). Eerdere cyclus over AFAS-management is gearchiveerd in `PLAN-AFAS.md` + `TODO-AFAS.md`.

## Quickstart

```sh
make install         # composer install
make web-install     # npm --prefix web install (eenmalig, voor de UI)
```

`.env` met AFAS-credentials is optioneel maar nodig voor `afas:pull`. Een minimale variant:

```env
SAMENSTELLINGEN_DB_PATH=./tmp/samenstellingen.sqlite
AFAS_BASE_URL=https://…
AFAS_TOKEN=…
```

## CLI

```sh
php bin/samenstellingen list
php bin/samenstellingen group:show 52112
php bin/samenstellingen group:import-portal-csv "AEDs op Reseller - Blad2.csv"
php bin/samenstellingen samenstelling:blacklist-bom 81311 'Waalse stickerset'
```

Alle commando's staan via `make help` en `php bin/samenstellingen list`.

## Tests + lint (CI-gate)

```sh
make check           # PHP CS Fixer + PHPStan + PHPUnit
make ui-test         # vitest voor de frontend
```

## Web UI (lokaal)

De UI is een React/MUI-app achter een nginx + php-fpm-stack in Docker. De Vite dev-server draait op de host met proxy naar nginx.

```sh
make ui              # docker compose up -d  +  vite dev-server op :5173
make ui-down         # stop de containers
```

Open <http://localhost:5173> tijdens dev (Vite hot-reload, proxy `/api/*` → nginx :8080).
Voor een gebouwde productie-versie:

```sh
make ui-build        # output naar public/index.html + public/assets/
make ui-up           # nginx serveert public/ op :8080
```

De UI is **strikt read-only**: mutaties van AFAS / DB blijven op de CLI.
