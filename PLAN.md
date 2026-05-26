# Plan: samenstellingen-manager

## 1. Doel

Eén tool om de groei van Defibrion's samenstellingen in AFAS beheersbaar te houden. Per AED-familie (Reanibex 100 Semi-Auto, Reanibex 100 Auto, Defibtech Lifeline VIEW, …) ontstaan tientallen samenstellingen door taal × accessoire combinaties. De tool moet die matrix als één ding kunnen definiëren, controleren en muteren.

## 2. Conceptueel model

```
Groep (tool-concept)
 ├─ family-head itemcode  ← willekeurige sibling, ankert AFAS Itemcode_Parent
 ├─ Bases[]               ← één basissamenstelling per taal (AFAS composite, taal al ingebakken)
 │    ├─ itemcode, taal-label, naam
 │    └─ BOM-snapshot     ← gespiegeld vanuit AFAS, niet zelf samengesteld
 └─ Accessoires[]         ← taal-onafhankelijk
      └─ itemcode, label

Varianten (afgeleid, niet apart opgeslagen):
   ∀ base ∈ Bases, ∀ acc ∈ Accessoires:
     SKU      = "{base.itemcode}-{acc.itemcode}"     (bv. 52112-60112)
     BOM      = base.BOM ∪ {acc.itemcode}
     parent   = group.family-head
     naam     = template(base.naam, acc.label)
```

De **basissamenstelling** is de atomische taaleenheid. De tool kent geen "taal-slots" binnen een BOM — taalvariatie is volledig in de basis ingekapseld.

## 3. Datamodel (SQLite, lokaal)

```sql
groups            (id, name, family_head_itemcode, naming_template, notes, created_at, updated_at)
group_bases       (group_id, itemcode, language_code, name)             -- PK (group_id, itemcode)
group_accessoires (group_id, itemcode, label)                           -- PK (group_id, itemcode)

afas_snapshot     (itemcode, naam, type_id, itemcode_parent,
                   sync_reseller_nl, tonen_reseller_nl, fetched_at)
afas_bom_snapshot (itemcode, component_itemcode, position)              -- PK (itemcode, component_itemcode)
```

`afas_snapshot` is een gecachete spiegel van wat AFAS denkt — verfrist op aanvraag. Groepsdefinities zijn de bron van waarheid voor wat hoort te bestaan; AFAS-snapshot is wat is.

Bootstrap van een groep: gegeven een family-head-itemcode, haal alle samenstellingen op met dat Itemcode_Parent, splits basissen (geen koppelteken in SKU) van varianten (`base-acc` patroon), en stel een groepsdefinitie voor.

## 4. Functionaliteit

Concrete CLI-commando's worden later bepaald. De tool moet in elk geval het volgende kunnen:

- **Groepen beheren**: aanmaken, tonen, lijst, bases en accessoires toevoegen/verwijderen.
- **Handmatig base linken aan AFAS-samenstelling**: voor ambigue gevallen die portal-CSV-import niet kan resolven, een commando dat een specifieke AFAS-samenstelling (via z'n itemcode) als base aan een groep koppelt en de BOM uit de snapshot overneemt — workaround voor data-drift die AFAS-side opgelost moet worden maar nog niet kan (slice 22).
- **Importeren uit AFAS**: een groep bootstrappen vanuit een bestaand family-head-itemcode.
- **AFAS spiegelen**: lokale snapshot van samenstellingen + BOMs verversen.
- **Auditeren**: diff tussen groepsdefinitie en AFAS-werkelijkheid (zie §5).
- **Genereren**: ontbrekende varianten in AFAS aanmaken op basis van de matrix base × accessoire.
- **Bulk-muteren**: component vervangen over alle samenstellingen in een groep, varianten hernoemen via template.
- **Naam-normaliseren**: afwijkende namen terugschrijven naar het per-taal template (zie §6.1).

Conventies: writes naar AFAS zijn **dry-run by default** en vereisen een expliciete apply-flag. Output zowel mensleesbaar als machine-leesbaar (CSV/JSON), passend bij de `set-*` scripts in `afas-connector-tools`.

## 5. Audit-regels

Per groep checken:
1. **Volledigheid**: voor elke (base × accessoire) bestaat er een variant met SKU `{base}-{acc}` in AFAS.
2. **BOM-consistentie**: variant-BOM = base-BOM ∪ {acc}. Extra of ontbrekende componenten worden geflagd.
3. **Family-head**: alle bases en varianten hebben `Itemcode_Parent = group.family_head_itemcode`.
4. **Flags**: Sync/Tonen consistent binnen de groep (of conform per-base override).
5. **Naam**: volgt per-taal template (prefix · productnaam · taal-label · optionele radio-variant · inhoud-suffix). Audit moet ook typo's, casing en spacing-drift signaleren — zie §9.1. Afwijkingen worden corrigeerbaar via naam-normalisatie (zie §6.1).
6. **Wezen**: AFAS-samenstellingen met dit family-head die niet in de groepsdefinitie staan.

## 6. AFAS-integratie

Hergebruik patronen uit `afas-connector-tools`:
- `ClientFactory::fromEnv()`.
- Reads via `Get_Artikelen`, `easylinq_stock_item` (type_id=7), composite-routing.
- Writes via `FbComposition` (`VaCt`/`@VaCt`).
- Free fields: `U298663A9447D4B4D8A0BB3FBC14A2C0B` (Itemcode_Parent), `U4E3E32DEFB374A1BA9F8680B8C405907` (Sync), `UD77EC755E2F1404EB184A956685A7C0C` (Tonen).
- Server-side bool filters niet gebruiken — pull-and-filter.

Open: kan ik via één UpdateConnector ook BOM-lines van een samenstelling schrijven, of moet dat per regel? Eerste implementatie-stap = uitzoeken via een AFAS schema-inspect.

### 6.1 Naam-normalisatie naar AFAS

Eindstadium van de tool: gevonden naam-afwijkingen (typo's, ontbrekende prefix, casing, spacing, "Kroatian/Croatian", "FR/French" etc.) niet alleen rapporteren maar ook **terugschrijven** naar AFAS via `FbComposition` (veld `Naam`). Werkwijze:

1. Audit produceert een lijst kandidaten met huidige naam → voorgestelde naam (op basis van per-taal template).
2. Een dry-run toont de berekende diff.
3. Een expliciete apply-stap schrijft de gewijzigde namen naar AFAS, in batches met failure-CSV (patroon van `set-*` scripts in `afas-connector-tools`).
4. Per-taal template is bron van waarheid (vastgelegd in `groups`-tabel of een aparte `language_templates`-tabel met placeholders `{language_label}`, `{radio_variant?}`, `{accessoire_label?}`).

Veilig terugschrijven vereist menselijke goedkeuring van de definitieve template per taal — niet de tool die "raadt" wat correct is.

## 7. Fasering

| Fase | Inhoud |
|---|---|
| 0 | Project scaffolding (composer, env, SQLite, hergebruik AFAS-client) |
| 1 | AFAS spiegelen, groep importeren, groep tonen, auditeren (read-only) |
| 2 | Ontbrekende varianten in AFAS aanmaken |
| 3 | Bulk-component vervangen, namen normaliseren via template |
| 4 | Web-UI bovenop dezelfde DB (matrix-editor: talen × accessoires) |
| 5 | Prijslijsten & klantspecifieke prijzen (zie §8) |

Elke fase eindigt met een handvol echte groepen die "groen" zijn in de audit.

## 8. Toekomstig: prijslijsten & klantspecifieke prijzen

Naast structurele BOM-consistentie ook prijsbeheer voor de hele matrix van samenstellingen:

- **Standaard prijslijst** per groep / variant: catalogusprijs onderhouden, regelmatig auditen tegen AFAS.
- **Klantspecifieke prijzen**: per debiteur of debiteurgroep afwijkende prijzen kunnen vastleggen, voor elke variant in een groep tegelijk (matrix-bulk).
- **Audits**:
  - Ontbrekende prijzen voor bestaande varianten in een prijslijst.
  - Afwijkingen tussen tool-definitie en AFAS-prijs (verstoorde sync of handmatige aanpassing).
  - Onlogische prijsstapeling (bv. variant goedkoper dan zijn basis, of accessoire-toeslag inconsistent tussen talen).
- **Bulk-mutaties**: prijswijziging van één component of accessoire over alle relevante samenstellingen + alle prijslijsten in één actie doorzetten, met preview en expliciete apply-stap.
- **Bron-van-waarheid**: TBD — eigen DB met push naar AFAS, of AFAS leidend met de tool als checker. Hangt af van of AFAS's prijslijst-/klantcontract-structuur via UpdateConnector beschreven kan worden.
- **Datamodel-uitbreiding (schets)**:
  ```sql
  price_lists       (id, name, currency, valid_from, valid_to)
  list_prices       (price_list_id, itemcode, price)               -- variant-niveau
  customer_prices   (customer_code, itemcode, price, valid_from, valid_to)
  ```

## 9. Open vragen

1. ✅ **Naam-template** voor bases en varianten — *vastgelegd na inspectie van de AFAS-snapshot (1894 samenstellingen)*. Voor elke taal volgt de naam dit patroon:

   ```
   {Prefix}: {Model} {ModelType} {Lang} {Radio?} {Inhoud}
   ```

   Waarbij de slot-waardes per taal:

   | Taal | `{Prefix}` | `{ModelType: semi/full}` | `{Lang}` | `{Inhoud}` |
   |---|---|---|---|---|
   | **NL** | `AED pakket` | `semi-automaat` / `volautomaat` | `NL` | `incl. safeset en stickerset` |
   | **FR** | `Pack DAE` | `Semi-automatique` / `Entièrement automatique` | `FR` | `avec safeset et signalétique` |
   | **DE** | `AED package` | `Semi-Automatic` / `Fully-Automatic` | `DE` | `incl. safeset and stickerset` |
   | **DA** | `AED package` | `Semi-Automatic` / `Fully-Automatic` | `DA` | `incl. safeset and stickerset` |
   | **EN** | `AED package` | `Semi-Automatic` / `Fully-Automatic` | `EN` | `incl. safeset and stickerset` |

   De `{ModelType}`-keuze per taal volgt de **meest voorkomende AFAS-conventie** (gemeten 2026): NL `semi-automaat` 238×, FR `Semi-automatique` 27×, EN/DE/DA `Semi-Automatic` 444× / `Fully-Automatic` 399×. Afwijkende historische schrijfwijzen (`Vol-automaat`, `Halfautomatisch`, `Fully-automatic`, `Semi-Automatique` met hoofdletter A) zijn drift.

   `{Lang}` is **kaal**, dus géén haakjes — ook FR krijgt `FR`, niet `(FR)`. Eerste woord van de zin behoudt z'n eigen casing (NL `AED`, FR `Pack`, EN `AED`); het package-woord erna is lowercase (`pakket`, `DAE`, `package`).

   Voor **varianten met accessoire**: het `{Inhoud}`-staartstuk wordt vervangen door `incl. {accessoire-label}` (NL/DE/DA/EN) of `avec {accessoire-label}` (FR). Het safeset-stickerset-deel vervalt dus bij accessoire-varianten.

   **Voorbeeld base (NL)**: `AED pakket: Reanibex 100 semi-automaat NL incl. safeset en stickerset`
   **Voorbeeld variant (NL + ARKY witte binnenkast)**: `AED pakket: Reanibex 100 semi-automaat NL incl. ARKY metalen binnenkast wit met alarm`

   **Extra dimensie**: radio-varianten (`WIFI`, `SIGFOX`, `GPS + WIFI + SIGFOX`, `3G`, `USB`) zitten optioneel tussen `{Lang}` en `{Inhoud}`. Past binnen het model — elke (taal × radio) is een aparte base.

   **Open detail**: `{Model}` is per-groep gespecificeerd (`Reanibex 100`, `LIFEPAK CR2`, `Heartsine Samaritan PAD 350P` etc.). Sommige model-namen hebben al `AED` ingebakken (`AED Samaritan PAD 350P`) wat tot dubbele `AED` in de volle naam leidt. Voor de audit (slice 18) bepalen we `{Model}` per groep — initieel afgeleid uit de huidige base-namen door de bekende slots eraf te trimmen.

   **Drift die de audit moet vangen** (alle case-sensitive, geen normalisatie):
   - prefix-casing: `AED Pakket:` vs `AED pakket:`
   - modeltype-spelling: `Vol-automaat`, `Halfautomatisch`, `Semi-Automatique` (hoofdletter A in FR)
   - taal-suffix met haakjes: `(FR)`, `(NL)`
   - ontbrekende of omgekeerde safeset/stickerset-staart
   - typo's (`safesett`, `incl.safeset` zonder spatie, dubbele spaties)
   - mismatched accessoire-suffix (variant heeft `incl. safeset en stickerset` in plaats van `incl. {accessoire-label}`)
   - ontbrekend prefix (oudere SKUs zoals 52101–52108)
   - taal-spelling: `Kroatian` vs `Croatian`, `French` vs `FR`

2. **Prijs**: zit prijs op de samenstelling zelf of wordt die berekend uit componenten? Welke AFAS-velden / prijslijst-structuur is leidend (zie §8)?
3. **Sync/Tonen-strategie**: alle varianten altijd Sync+Tonen, of soms alleen de "vlaggenschip"-variant?
4. **SKU-collisions**: 8 accessoires × N talen × M groepen → groeit dit ooit boven het AFAS-itemcode-veld (lengte)?
5. **Andere merken**: gebruiken Defibtech / Prestan ook de `{base}-{acc}` SKU-conventie, of een andere?

---

## 10. Web UI — read-only viewer (concept)

Eindbeeld: een lokaal te starten browser-UI bovenop dezelfde SQLite-database, voor inspectie van de geïmporteerde groepen / bases / items.

**Vaste regel — geldt voor élke iteratie**: de UI is **strikt read-only**. Geen catalogus-CRUD, geen blacklist-CRUD, geen AFAS-mutaties, geen wipe, geen import-trigger, geen variant-generatie. Geen `POST`/`PUT`/`PATCH`/`DELETE`-endpoints — de API biedt alleen `GET`. Mutaties gebeuren via de CLI; daar zit het audit-spoor (git history + shell-history) en daar staat de dry-run-/confirmation-flow voor AFAS-writes (`--apply`). Wanneer een lees-pagina iets toont dat eruitziet alsof het beheerd moet worden, krijgt 'ie een inline-verwijzing naar het CLI-commando in plaats van een UI-actie. Zie CLAUDE.md voor de operationele regel.

### Pagina-flow
1. **Home / `/`** — lijst van groepen.
   - Kolommen: naam, family-head itemcode, aantal bases, totaal aantal base-items.
   - Zoek/filter-veld op naam.
   - Klik op rij → detail-pagina.
2. **Groep-detail / `/groups/{familyHead}`** — bovenaan groepsnaam + family-head; daaronder een Accordion-lijst van bases.
   - Per base zichtbaar: naam, taal-code, aantal items.
   - Open je een base, dan zie je de BOM-items als tabel: `itemcode | label`.
   - Optioneel later: een variant-matrix-tab per groep (base × accessoire).

### Beslissingen (bevestigd)
- **Componenten/styling**: **MUI only** (Material-UI). Geen Tailwind, geen Headless UI. MUI levert Accordion, Table en Layout-primitives out-of-the-box.
- **Routing**: **React Router** met `/` en `/groups/:familyHead`. Maakt bookmarken/sharen mogelijk.
- **Webserver**: **nginx + php-fpm**. Niet de ingebouwde `php -S`. Lokaal via een minimale `docker-compose.yml` (nginx + php-fpm-container met bind-mount op de repo) zodat geen system-config aangepast hoeft te worden. Native nginx blijft mogelijk via een voorbeeld-config.
- **Issues-tab**: **nog niet** in eerste versie. Misschien later als losse pagina.

### Architectuur

```
┌─────────────────────────────────────┐
│   React-app (Vite, in web/)         │
│   ↳ TypeScript                      │
│   ↳ MUI                             │
│   ↳ React Router (/, /groups/:id)   │
│   ↳ TanStack Query voor fetch+cache │
└────────────┬────────────────────────┘
             │ fetch JSON (proxy in dev,
             │ same-origin in prod-build)
┌────────────▼────────────────────────┐
│   nginx (port 8080)                 │
│   ↳ /            → public/index.html│
│   ↳ /assets/*    → public/assets/*  │
│   ↳ /api/*       → fastcgi → fpm    │
└────────────┬────────────────────────┘
             │ FastCGI
┌────────────▼────────────────────────┐
│   php-fpm                           │
│   ↳ public/index.php (front-ctrl)   │
│   ↳ src/Interface/Http/             │
│     ↳ ListGroupsController          │
│     ↳ ShowGroupController           │
│   ↳ Hergebruikt bestaande repos     │
└────────────┬────────────────────────┘
             │ PDO
┌────────────▼────────────────────────┐
│   tmp/samenstellingen.sqlite        │
│   (zelfde DB als de CLI)            │
└─────────────────────────────────────┘
```

- **Backend**: minimale PHP-router (Slim 4 — bewezen, kleine footprint) achter een `public/index.php` front-controller. Geen ORM — bestaande repositories volstaan. Nieuwe directory `src/Interface/Http/` met controllers parallel aan `src/Interface/Cli/`. Container/bootstrap uitfactoren naar een gedeelde `bootstrap.php` zodat zowel CLI als HTTP dezelfde repo-construeer-code gebruiken.
- **Frontend**: React + Vite + TypeScript. MUI v6 (incl. `@mui/icons-material`, `@mui/x-data-grid` voor de groepenlijst — DataGrid heeft search/sort gratis). TanStack Query voor caching + retries.
- **Build**: `npm run build` plaatst de bundle in `public/assets/` en een `public/index.html`. nginx serveert die statisch en routet `/api/*` naar php-fpm.
- **Dev-modus**: Vite dev-server op :5173 met proxy van `/api/*` naar nginx op :8080. Hot-reload voor TSX; PHP-changes worden direct opgepakt door fpm.
- **Containers**: `docker-compose.yml` met twee services (nginx + php-fpm:8.5-alpine), bind-mount op de repo en `tmp/samenstellingen.sqlite`. Vite blijft op de host (snel + native node).
- **Lokaal starten**: nieuwe `make ui`-target start `docker compose up -d` + `npm --prefix web run dev`. Stoppen met `make ui-stop`.

### Wat doelbewust *buiten* scope blijft (eerste versie)
- Authenticatie (lokale tool, single user).
- Inline bewerken van groepen/bases.
- Triggeren van AFAS-sync / import / wipe via de UI — blijft op de CLI om de blast-radius klein te houden en het audit-spoor in `git log` te behouden.
- Realtime updates / websockets — refresh-knop volstaat.
- Issues-tab / unresolved-rapport in UI (komt later eventueel).

### Fasering

- **Slice 14 (A1)** — bootstrap-refactor + minimal PHP API (`GET /api/groups`, `GET /api/groups/{familyHead}`). Slim 4 + PSR-7. Integratie-tests via PHPUnit (boot Slim in-process, call routes, assert JSON).
- **Slice 14 (A2)** — `docker-compose.yml` (nginx + php-fpm) + voorbeeld-`nginx.conf`. `make ui-up` / `make ui-down`. README-snippet.
- **Slice 14 (A3)** — Vite + React + MUI skelet in `web/`. Groepen-lijst-pagina (`/`) met DataGrid tegen `/api/groups`. Search/filter gratis via DataGrid.
- **Slice 14 (A4)** — Groep-detail-pagina (`/groups/:familyHead`) met breadcrumb, MUI Accordion per base, uitgeklapte BOM-tabel.
- **Slice 14 (A5)** — opruimen: error-boundaries, empty states, 404-page, loading-spinners. `make ui` als één-commando-start.

Iedere sub-slice gaat via z'n eigen TDD-cyclus (PHPUnit voor de backend; Vitest + React Testing Library voor de frontend).

---

## 11. Web UI — fase 2 (resterende read-only data)

Na slice 14 staan groepen-lijst, groep-detail (bases + BOM-tabel met echte AFAS-namen) en het basis-skelet er. Wat nog niet zichtbaar is in de UI maar wél in de DB staat:

| Data | Bron | Waar tonen |
|---|---|---|
| Accessoires-catalogus | `accessoires` | top-level pagina |
| Gekoppelde accessoires per groep | `group_accessoires` | tab in groep-detail |
| Gegenereerde varianten + AFAS-match | `group_variants` | tab in groep-detail |
| BOM-blacklist | `bom_blacklist` | top-level pagina |
| AFAS-samenstellingen snapshot + duplicates | `afas_samenstellingen` | (open punt — eigen UX) |
| Missing variants ("no_match") | berekend uit `group_variants` | (open punt — al CLI/CSV) |

### Navigatie

AppBar (MUI) krijgt drie links: **Groepen**, **Accessoires**, **Blacklist**. Groep-detail krijgt MUI `Tabs` met **Bases** (huidig), **Accessoires** en **Varianten**.

```
┌───────────────────────────────────────────────────────────────┐
│  Samenstellingen Manager   Groepen · Accessoires · Blacklist  │
├───────────────────────────────────────────────────────────────┤
│  Groepen / AED Samaritan PAD 350P                             │
│                                                                │
│  ┌──────────────────────────────────────────────────────────┐ │
│  │ AED Samaritan PAD 350P                                   │ │
│  │ family-head 10013 · 4 bases · 5 accessoires · 20 varianten│ │
│  └──────────────────────────────────────────────────────────┘ │
│  [ Bases ] [ Accessoires ] [ Varianten ]                      │
│  …                                                             │
└───────────────────────────────────────────────────────────────┘
```

### Pagina's

1. **Accessoires-catalogus** (`/accessoires`)
   - MUI DataGrid met `itemcode`, `label` (en eventueel "in N groepen gebruikt" als secundaire kolom).
   - Search/filter via DataGrid.
2. **BOM-blacklist** (`/blacklist`)
   - Tabel `itemcode` + `reason` (vaak één regel tekst).
3. **Groep-detail tabs**
   - **Bases** — huidige Accordion.
   - **Accessoires** — lijst van de aan de groep gekoppelde accessoires (subset van de catalogus).
   - **Varianten** — tabel per (base × accessoire) met kolommen: base-naam, taal, accessoire (of `—` voor base-only), AFAS-SKU, AFAS-status. Sorteren/filteren op status zodat je "no_match"-rijen snel boven krijgt.

### API-endpoints (Slim)

Voorgestelde nieuwe routes — controllers in `src/Interface/Http/`:

```
GET  /api/accessoires
     → [{itemcode, label, groupCount?}]
GET  /api/bom-blacklist
     → [{itemcode, reason}]
GET  /api/groups/{familyHead}/accessoires
     → [{itemcode, label}]
GET  /api/groups/{familyHead}/variants
     → [{
         baseId, baseName, languageCode,
         accessoireItemcode|null, accessoireLabel|null,
         afasSamenstellingItemcode|null, afasStatus|null
       }]
```

Alternatief: alles op `/api/groups/{familyHead}` plakken (één call, makkelijker voor UI, minder REST-y). Zie open punt 1.

### Buiten scope (eerste iteratie)
- Mutaties (toevoegen/verwijderen) — blijven op CLI.
- AFAS-samenstellingen-snapshot tonen (1894 items, eigen UX-puzzel).
- Missing-variants pagina (audit-output is al CLI/CSV-route).

### Open punten

1. **Endpoint-shape**: aparte sub-resources (`/api/groups/{id}/accessoires`, `/api/groups/{id}/variants`) of alles op `/api/groups/{id}` plakken? Sub-resources is REST-er en cachet beter (TanStack Query kan onafhankelijk staleness beheren), één-call is simpeler in de UI. Mijn voorkeur: **aparte sub-resources** — past bij hoe TanStack Query werkt en houdt show-controller klein.
2. **AFAS-snapshot in deze ronde meenemen?** Zou nuttig zijn voor "welke AFAS-samenstelling matcht met variant X", maar 1894 rijen heeft eigen UX-vereisten (search, paging). Mijn voorstel: **niet in deze slice**, eerst de drie hoofdviews afronden.
3. **Missing-variants UI?** Al beschikbaar als CSV via `audit:export-missing`. UI-versie zou dezelfde data zijn met een knop "exporteer als CSV". Mijn voorstel: **niet in deze slice**, hooguit een latere mini-slice die de variant-tab een filter "alleen no_match" geeft.

### Voorgestelde sub-slices (na akkoord)

- **Slice 15 (B1)** — AppBar-navigatie + accessoires-catalogus-pagina (`/api/accessoires` + `/accessoires`).
- **Slice 15 (B2)** — BOM-blacklist-pagina (`/api/bom-blacklist` + `/blacklist`).
- **Slice 15 (B3)** — groep-detail-tab "Accessoires" + endpoint.
- **Slice 15 (B4)** — groep-detail-tab "Varianten" + endpoint, met status-filter.
- **Slice 15 (B5)** — refactor: tabs-routing (`/groups/:familyHead/bases|accessoires|variants`) zodat tabs bookmarkable zijn, en een "summary line" met counts op de groep-detail-header.

Iedere sub-slice volgt het slice 14-patroon: PHP-test → controller → frontend type → vitest → live-verificatie.

---

## 12. Prijs-data + validatie (concept)

Eindbeeld: voor elke base + variant kennen we de actieve prijs per prijslijst en per debiteur, plus eventuele staffels. De accessoire-toeslag is een **delta** op de base-prijs. **De ground truth ligt in onze tool**, niet in AFAS: bij `accessoire:create` geeft de gebruiker expliciet op wat de delta in euro is. De audit controleert vervolgens of AFAS dezelfde delta hanteert over alle bases × prijslijsten. Read-only: prijs-mutaties blijven in AFAS — de tool detecteert alleen wat afwijkt.

### Bronnen uit AFAS (live geverifieerd via `/metainfo`)

| Connector | Inhoud |
|---|---|
| **`Get_Prijzen`** | Per (`Itemcode`, `Prijslijst`, `Debiteur`) één rij met `Verkoopprijs`, `Staffelprijs`, `Actieprijs`, `Begindatum`, `Einddatum`, `Valuta`, `Eenheid`. `Debiteur` leeg ⇒ prijslijst-prijs; gevuld ⇒ klant-specifieke override. Hoofdbron. |
| `Get_Prijslijsten` | Prijslijst-namen (al gebruikt in slice 15-context, 28 prijslijsten waaronder Basisprijslijst (incl/excl BTW), Dealers FR, ARKY Dealers, klant-specifieke zoals Farys/IOK/…) |
| `easylinq_debtor` | Debiteur-stamdata (`debtorId`, `name`, …) — nodig om klant-prijzen in de UI leesbaar te maken |

Snapshot is historisch: voor één itemcode kunnen 20+ rijen bestaan met overlappende `Begindatum..Einddatum`-periodes. De tool bewaart **alleen de actieve** rijen (`Einddatum` leeg of ≥ vandaag).

### Schema-wijzigingen

**Accessoires krijgen een delta-kolom** (canonieke toeslag t.o.v. base-prijs):

```sql
ALTER TABLE accessoires ADD COLUMN delta_cents INTEGER NOT NULL DEFAULT 0;
```

Bedrag opgeslagen in **cents** (integer) — geen float-rounding-problemen bij geld. Domain-property `Accessoire.deltaCents: int`. CLI's accepteren euro-input (`79`, `79.50`, `79,50`) en converteren naar cents. UI/API tonen euro-formaat.

Eén vast bedrag per accessoire (geldt over alle bases en alle prijslijsten). Bestaande 9 accessoires krijgen `0` als migratie-default; in te vullen via een nieuw `accessoire:set-delta <itemcode> <eur>` commando. Nieuwe accessoires aanmaken via `accessoire:create` vereist de delta als 3e argument.

Als later blijkt dat delta's structureel afwijken per prijslijst (dealers met andere marge), breiden we uit naar een base-delta + per-prijslijst override (optie C uit de spar-discussie). Voor MVP: één getal.

**Snapshot-tabel voor AFAS-prijzen:**

```sql
afas_prijzen (
  itemcode         TEXT NOT NULL,
  prijslijst_id    TEXT NOT NULL,
  debiteur_id      TEXT NULL,     -- leeg = prijslijst-prijs
  verkoopprijs     REAL NOT NULL,
  staffel_aantal   INT  NULL,     -- NULL = basisstaffel (vanaf 1)
  geldig_van       TEXT NOT NULL,
  geldig_tot       TEXT NULL,     -- NULL = open einde
  PRIMARY KEY (itemcode, prijslijst_id, debiteur_id, staffel_aantal, geldig_van)
);
```

Bij `afas:pull` wordt deze tabel net als `afas_samenstellingen` en `afas_articles` volledig vervangen (idempotent snapshot-pattern). `ToolDataWiper` raakt 'm niet aan, zelfde behandeling als de andere AFAS-tabellen.

### Prijs-lookup voor "wat kost X voor klant Y in een offerte vandaag?"

3-tier fallback (volgens user's bevestiging):

1. Klant-specifieke rij voor (itemcode, debiteur_id = Y), actief vandaag.
2. Anders: rij voor (itemcode, prijslijst_id = de prijslijst van Y), actief.
3. Anders: basisprijslijst.
4. Anders: geen prijs — audit-melding.

Met staffels: per stap kiezen we de juiste staffel-rij op basis van het bestelde aantal.

### Eén audit: `audit:prices`

Rapporteert per variant twee soorten drift in dezelfde tabel:

- **accessoire-toeslag-drift**: voor elke (base, accessoire, prijslijst) check of `AFAS-prijs(base+accessoire) - AFAS-prijs(base) === accessoires.delta_eur`. Wijkt af → drift. Voorbeeld: `accessoires.delta_eur` voor ARKY witte binnenkast (60112) = €295; AFAS toont voor Heartsine NL `prijs(11142-60112) - prijs(11142) = €301` → Heartsine NL staat in de drift-lijst voor 60112. Verwachte delta komt uit onze tool, werkelijke delta uit AFAS.
- **missende prijslijst**: een variant mist een prijs in een prijslijst waar z'n base wél in staat. Suggereert dat de variant na laatste prijsronde nog niet ge-update is in AFAS.

Output-kolommen: `groep | base | accessoire | prijslijst | verwachte_delta | werkelijke_delta | status (toeslag-drift / missing)`. CLI + UI-pagina + CSV-export, in dezelfde stijl als `audit:names` en `audit:suspicious-bases`.

### Slices (na akkoord op concrete TODO)

- **Slice 25** — `accessoires.delta_eur`-kolom (migration + domain + repo). `accessoire:create` krijgt verplichte 3e arg `<delta-eur>`. Nieuw `accessoire:set-delta <itemcode> <eur>` om bestaande in te vullen. Bestaande 9 accessoires staan op `0` na migratie. UI accessoires-pagina toont de delta-kolom.
- **Slice 26** — Snapshot `afas_prijzen` + integratie in `afas:pull` + UI-pagina (`/prices/{itemcode}` of als tab op groep-detail).
- **Slice 27** — `audit:prices` (accessoire-toeslag-drift + missende prijslijst in één), CLI + read-only UI + CSV. Gebruikt `accessoires.delta_eur` als ground truth.

### Open punten (niet blokkerend voor het concept, op te lossen tijdens slice 25)

1. **Relevante prijslijsten**: 13× `NIET GEBRUIKEN`-prijslijsten uitsluiten. Hardcoded of via een `is_actief`-vlag op `afas_prijslijsten`? Voorlopig: substring-filter op `NIET GEBRUIKEN`.
2. **Verkooprelatie ↔ prijslijst**: waar staat de koppeling "klant Y zit op prijslijst Z"? Te onderzoeken in `easylinq_debtor` of `easylink_verkooprelatie`.
3. **Historie**: alleen actieve rijen bewaren of ook oude voor latere "prijs-ontwikkeling"-views? Voorstel: alleen actief; historie pas later als business-case ontstaat.
4. **Staffels**: lijkt `Staffelprijs=1` = vanaf 1 stuk (basisstaffel) en hogere aantallen aparte rijen. Bevestigen tijdens slice 25-implementatie.
