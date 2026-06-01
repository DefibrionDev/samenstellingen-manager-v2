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

## 13. Prijslijst-blacklist (slice 28 — concept)

### Probleem

Na live `audit:prices` (slice 27, 980 rijen) blijkt veel "missing"-ruis te komen van prijslijsten met een kleine doelgroep:

| ID | Omschrijving | # bases | Karakter |
|---|---|--:|---|
| 011 | IOK | 1 | Eén specifieke klant, niet elke AED hoort hierin |
| 024 | Opdrachtencentrale | 2 | Idem |
| 025 | Coop collectief | 3 | Idem |
| 010 | Farys | 3 | Idem |
| 026 | ARKY Dealers | 47 | Eigen catalogus, niet 1:1 met onze AED-lijn |

"Missing" op zo'n lijst betekent vaak niet "vergeten in AFAS", maar "die AED hoort daar bewust niet in".

### Oplossing — `prijslijst_blacklist`

Globale blacklist van prijslijst-IDs. Geblackliste lijsten worden volledig uit `audit:prices` weggelaten (zowel `missing` als `toeslag-drift`). Beredenering voor "ook drift skippen": als een lijst niet relevant is voor onze AED-lijn, is een afwijkende toeslag daar net zo goed ruis als een ontbrekende prijs.

Patroon kopieert `bom_blacklist` (slice 12.x): één tabel, CRUD via CLI, read-only UI-pagina.

### Schema

```sql
CREATE TABLE prijslijst_blacklist (
    prijslijst_id TEXT PRIMARY KEY,
    reden TEXT NOT NULL,
    aangemaakt_op TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

`prijslijst_id` matcht 1:1 op `afas_prijzen.prijslijst_id` (zelfde ruwe AFAS-ID, bv. `011`, `010`, `026`).

### CLI

- `pricelist:blacklist <id> "<reden>"` — voegt toe (faalt als al aanwezig).
- `pricelist:unblacklist <id>` — verwijdert.
- `pricelist:list-blacklist` — toont huidige lijst met reden + datum.

### UI

Read-only pagina `/prijslijst-blacklist` met tabel (ID, omschrijving uit ad-hoc lookup of leeg, reden, datum). Inline tip "Beheer via CLI: `pricelist:blacklist <id> '<reden>'`" — net als andere read-only pagina's (CLAUDE.md regel).

### Vraag: prijslijst-naam erbij?

In de DB hebben we alleen ID's. Voor de UI zou een naam fijn zijn. Twee opties:

- **A** (lazy): UI-pagina alleen ID + reden + datum. Naam ophalen kost een AFAS-call, slaan we niet op. Minst werk, mogelijk verwarrend ("welke lijst is `010` ook al weer?").
- **B** (snapshot): aparte snapshot-tabel `afas_prijslijsten (id, omschrijving)` synced tijdens `afas:pull` (29 rijen, statisch). Naam staat dan overal beschikbaar — ook voor `audit:prices`-output, `pricelist:list-blacklist`, UI.

Voorstel: **B**. Klein, statisch, en is sowieso nuttig voor leesbaarheid van alle prijs-gerelateerde output. Wordt sub-slice 28.0 vóór de blacklist zelf.

### Slices

- **Slice 28.0** — snapshot `afas_prijslijsten` (id, omschrijving) + sync in `afas:pull`. Toon naam in `audit:prices` output (CLI tabelkolom + UI grid-kolom).
- **Slice 28.1** — `prijslijst_blacklist` tabel + CLI commando's + repository.
- **Slice 28.2** — `PriceAuditHandler` skipt geblackliste lijsten (zowel `missing` als `toeslag-drift`). Test toevoegen.
- **Slice 28.3** — Read-only UI-pagina + nav-link "Prijslijst-blacklist". Vitest.

## 14. Prijs-drift fixen — `prices:fix-drift` (slice 30 — concept)

### Probleem

`audit:prices` toont 45 toeslag-drift-rijen waarvan de variant-prijs in AFAS niet `base + accessoires.delta_cents` is. Handmatig fixen in AFAS Profit is voor 45 rijen onpraktisch — maar fout-bulk-fixen risicovol omdat het klantprijzen raakt.

### Aanpak

CLI `prices:fix-drift [--apply] [--limit=N]`:
- **Default = dry-run** (CLAUDE.md regel). Print per drift-rij: huidige vs gewenste prijs, en payload-preview.
- `--apply` doet de PUT richting AFAS via `FbSalesPrice`.
- `--limit=N` beperkt tot eerste N rijen, voor stapsgewijs uitrollen.
- Idempotent: tweede run vindt minder drift; dezelfde rij twee keer fixen is een no-op (Pric wordt overschreven).

### PoC-bevindingen (verifieerd live op 27-05-2026)

- **Connector**: `FbSalesPrice` (PUT op `/connectors/FbSalesPrice`).
- **Payload**:
  ```
  {"FbSalesPrice":{"Element":{"Fields":{
    "VaIt":"7",         // 7 = Samenstelling (universeel voor onze varianten)
    "ItCd":"<variant>",
    "PrLi":"<prijslijst>",
    "BiUn":"STK",       // universeel
    "CuId":"EUR",       // universeel
    "DaBg":"<bestaande begindatum>",
    "Pric":<base+delta in euro>
  }}}}
  ```
- **Begindatum behouden** (niet opnieuw zetten op vandaag) — voorkomt prijshistorie-corruptie.
- Respons: HTTP 201, lege body bij succes.
- PoC-resultaat: 10041-60212 in lijst 003 succesvol van €1520 → €1450 gezet (audit-drift opgeheven).

### Architectuur

1. **AfasHttpClient** krijgt `updateConnector(string $id, array $payload): void` (PUT).
2. **Domain**:
   - `PriceFixPlan` — value-object met `variantItemcode`, `prijslijstId`, `currentCents`, `targetCents`, `beginDate`.
   - `PriceFixWriter`-contract — `apply(PriceFixPlan)`.
3. **Application**:
   - `FixPriceDriftHandler` — itereert `audit:prices` (drift only), bouwt `PriceFixPlan` per rij, schrijft via `PriceFixWriter`. Skipt blacklist-lijsten automatisch (al door audit-filter). Logt successen/fouten.
4. **Infrastructure**:
   - `HttpFbSalesPriceWriter implements PriceFixWriter` — bouwt FbSalesPrice-payload en doet PUT via AfasHttpClient.
   - `InMemoryPriceFixWriter` voor tests — registreert wat geschreven zou worden, geen netwerk.
5. **CLI** `prices:fix-drift`: dry-run-table by default, `--apply` doet de PUT, `--limit=N` beperkt.

### Test-strategie

- Unit-test `FixPriceDriftHandler` met `InMemoryPriceFixWriter` — assert dat de juiste plannen worden gegenereerd.
- Geen integratietest tegen echte AFAS (CLAUDE.md regel: tests raken nooit echte AFAS).
- Wel live verificatie na implementatie: `--limit=1` op een veilige rij en handmatig in Profit/Get_Prijzen verifiëren.

### Faalmodi

- **PUT faalt** (4xx/5xx): logregel in `tmp/fix-drift-{datum}.csv`, doorgaan met volgende rij. Niet abort.
- **Pric verschilt < 1 cent**: skippen (geen meaningful fix).
- **Variant heeft geen base-prijs in deze prijslijst**: kan niet voorkomen want audit produceert dan geen drift-rij, alleen missing.

## 15. Missing-prijs aanvullen — `prices:fix-missing` (slice 31 — concept)

Zelfde patroon als slice 30, maar dan voor `missing`-rijen: variant heeft geen prijs in een prijslijst waar de base wel in staat.

- **Connector**: zelfde `FbSalesPrice`, maar **POST** (insert) ipv PUT (update). AFAS sluit dit doorgaans niet uit — verifiëren met PoC voor implementatie.
- **Payload**: identiek aan drift-fix, behalve dat de rij nog niet bestaat.
- **Begindatum**: nemen we de begindatum van de *base*-prijs in dezelfde prijslijst (consistente boekhouding).
- **Architectuur**: hergebruik `PriceFixPlan` + `PriceFixWriter`, voeg `FixPriceMissingHandler` toe naast `FixPriceDriftHandler`. CLI `prices:fix-missing [--apply] [--limit=N]`.

PoC vereist een **handmatige eerste POST** om te bevestigen dat insert via FbSalesPrice werkt zoals verwacht — net als slice 30 zijn we doen het stap-voor-stap.

## 16. Staffelprijzen meenemen — switch naar easylinq (slice 32 — concept)

### Probleem

`Get_Prijzen` levert in onze AFAS-setup **geen staffelprijzen** — alle 41036 rijen in `afas_prijzen` hebben `staffel_aantal = NULL`, en een server-side filter op niet-lege `Staffelprijs` retourneert 0 rijen. Audit en fix-scripts werken nu alleen op de baseline-prijs en zouden hogere staffels stilletjes negeren.

AFAS heeft twee `easylinq_*`-connectors die wel staffels leveren:

- **`easylinq_prices_saleprice`** — basisstaffel (`Hoeveelheid` veld, hoofdletter H).
- **`easylinq_prices_saleprice_staffel`** — hogere staffels (`quantity` veld) met `current`-flag.

### Aanpak

Switch `HttpAfasPrijzenFetcher` van `Get_Prijzen` naar de twee easylinq-connectors, geünificeerd in één result-list. Geen schema-wijzigingen aan `afas_prijzen` — die heeft al `staffel_aantal` als kolom (was alleen leeg).

### Mapping naar `afas_prijzen`

| afas_prijzen kolom | saleprice veld | saleprice_staffel veld |
|---|---|---|
| `itemcode` | `item_id` | `item_id` |
| `prijslijst_id` | `pricelist_id` | `pricelist_id` |
| `debiteur_id` | `debtor_id` (leeg → null) | `debtor_id` |
| `verkoopprijs_cents` | `price` × 100 (string→int via EuroParser-stijl) | `price` × 100 |
| `staffel_aantal` | `Hoeveelheid` | `quantity` |
| `geldig_van` | `date` | `date` |
| `geldig_tot` | — (niet aanwezig) | — (niet aanwezig) |

`geldig_tot` blijft `NULL` voor easylinq-data. Filter op "actieve prijs" loopt via:
- `saleprice`: huidige fetcher houdt alle rijen (er is geen einddatum, blijkbaar pure "actief" semantiek).
- `saleprice_staffel`: alleen rijen met `current=1`.

### Audit-impact — staffels mee-auditen

Beslissing: niet alleen staffels syncen, ook auditen + fixen.

**Audit-regel** (plat model): voor elke (variant, prijslijst, staffel) geldt `variantPrijs == basePrijs(zelfde-prijslijst, zelfde-staffel) + accessoires.delta_cents`. Volume-korting in base → identieke volume-korting in variant. Accessoire-toeslag is een vaste cents per staffel.

**Statuscategorieën** in `PriceDriftRow`:
- `toeslag-drift` — beide hebben deze staffel, maar variant-base ≠ delta.
- `missing` — base heeft deze staffel, variant niet.
- `inconsistent-staffel` (nieuw) — variant heeft staffel die base niet heeft. Auto-fix is onveilig → wel rapporteren, niet auto-corrigeren.

**Indexering**: `PriceAuditHandler::indexLatestPerPrijslijst` wordt `indexLatestPerPrijslijstAndStaffel` — key wordt `<prijslijst>|<staffel>`. Drop de `staffelAantal > 1`-skip.

**`PriceDriftRow`** krijgt veld `staffelAantal: ?int` (null = baseline-rij in oude data; na switch altijd ≥ 0).

**CLI + UI**: extra kolom "Aantal" achter "Prijslijst". UI-grouping blijft `(variant, accessoire)`; uitklap-rijen tonen per (prijslijst, staffel).

### Fix-impact

`FbSalesPrice`-payload moet bij staffel > basisstaffel ook `CrPr=true` en `Am=<staffel>` meegeven. PoC nodig met één staffel-PUT en één staffel-POST voor we slice 30/31 op staffels loslaten.

### PoC

Vóór code:
1. Tel rijen in beide easylinq-connectors (background-call loopt al).
2. Vergelijk: levert `easylinq_prices_saleprice` minstens net zoveel baseline-rijen als de huidige `Get_Prijzen`-snapshot (41036) op (itemcode, prijslijst, debiteur) niveau?
3. Sample 5 random items en vergelijk prijs (cents) tussen oud en nieuw. Verschil van 0 = clean switch; verschil > 0 = data-issue in oude of nieuwe bron, eerst onderzoeken.

### Slices

- **Slice 32.0** — PoC + verkenningsscript. Vergelijk row-counts en sample-prijzen tussen `Get_Prijzen` en `easylinq_prices_saleprice`. Output → `tmp/`. Geen production-code-wijziging.
- **Slice 32.1** — `HttpAfasPrijzenFetcher` herschrijven naar twee easylinq-connectors. Filter staffel-rijen op `current=1`.
- **Slice 32.2** — Live `afas:pull` + count-verificatie van staffel-rijen.
- **Slice 32.3** — `PriceAuditHandler` per-staffel indexeren. Drop `staffel > 1`-skip. Voeg `staffelAantal` toe aan `PriceDriftRow`. Voeg `inconsistent-staffel` status toe voor variant-staffels zonder base-tegenhanger.
- **Slice 32.4** — UI/CLI uitbreiden met "Aantal"-kolom. Grouping in UI blijft `(variant, accessoire)`; uitklap toont (prijslijst, staffel).
- **Slice 32.5** — Slice 30/31 PoC voor staffel-PUT/POST (FbSalesPrice met `CrPr` + `Am`). Pas fix-handlers aan om staffels mee te nemen.

## 17. Duplicate-BOM audit (slice 34 — concept)

### Probleem

Live SQL-check op `afas_samenstellingen` toont **133 samenstellingen met BOM identiek aan minstens één andere**. Bij inspectie:

- `10042-60112` (variant Defibtech + witte binnenkast 60112) heeft BOM `[10142, 70112, 81111]`.
- `10042` (pure base zonder accessoire) heeft *dezelfde* BOM.

De variant *zou* `60112` in z'n BOM moeten hebben — dat doet hij niet. Dit zijn dus structurele AFAS-data-fouten. Patroon is consistent over alle gevonden gevallen: variant-rij neemt de base-BOM over zonder accessoire-itemcode mee te nemen.

Impact: portal-CSV-import-resolutie en variant-detectie zijn fragiel als BOM-data niet betrouwbaar is.

### Aanpak

Read-only audit volgens hetzelfde patroon als de andere audits (`audit:names`, `audit:suspicious-bases`):

- **Domain**: `DuplicateBomGroup` value-object met `fingerprint` (gesorteerde, kommagescheiden BOM-string) en `list<itemcode + name>`.
- **Application**: `DuplicateBomAuditHandler` — itereert alle `afas_samenstellingen` (geen filter — *alle* samenstellingen, niet alleen die in onze groepen), bouwt fingerprint, groepeert. Return alleen groepen met ≥ 2 leden.
- **CLI**: `audit:duplicate-boms` — toont per groep itemcodes + BOM-fingerprint. Exit-code 1 bij hits.
- **HTTP + UI**: `GET /api/duplicate-boms` + `/duplicate-boms` pagina met DataGrid (group-by-fingerprint, uitklap toont itemcodes en namen). CSV-export.

### Slices

- **Slice 34.0** — Domain + handler + unit-tests met `InMemoryAfasSamenstellingenRepository`.
- **Slice 34.1** — CLI + HTTP + UI + vitest.

## 18. VariantNamingPolicy refactor + per-taal accessoire-labels (slice 37 — concept)

### Probleem

`VariantNamingPolicy` (slice 7) genereert namen als `AED pakket: ... NL incl. EHBO-Rugzak`. Bij vergelijking met de werkelijke AFAS-data klopt dit niet:

- AFAS gebruikt **prefix `AED Pakket:`** (hoofdletter P), niet `AED pakket:`.
- AFAS gebruikt **joiner ` met `** voor varianten, niet `incl.`.
- AFAS gebruikt **`UK`** als taal-suffix voor `EN`-bases, niet `EN`.
- AFAS heeft **echte vertalingen** van accessoire-suffixes (Reanibex-groep heeft `Sac à dos` ipv `Rugtas`); onze labels zijn lange interne beschrijvingen die niet matchen.
- AFAS heeft compound-suffixes met `-` ipv `/` (`NL-FR`, `NL-EN-FR`).
- AFAS-data zelf bevat **inconsistenties** (`Heartsine` vs `HeartSine`, `Buitenkast` vs `buitenkast`, typos zoals `safesett`, `aavec`).

### Aanpak

AFAS-patronen zijn leidend voor de structuur, maar onze tool produceert één canonical vorm per (taal, accessoire). Bestaande AFAS-inconsistenties worden door de audit gerapporteerd en bij `prices:fix-names`-equivalent (volgende slice) gecorrigeerd.

#### Drie canonical templates

```
NL: AED Pakket: {ModelNL} {LangSuffix}                                ← base
    AED Pakket: {ModelNL} {LangSuffix} met {AccessoireNL}             ← variant

FR: Pack DAE: {ModelFR} ({LangSuffix})                                ← base
    Pack DAE: {ModelFR} ({LangSuffix}) avec {AccessoireFR}            ← variant

EN: AED Package: {ModelEN} ({LangSuffix})                             ← base
    AED Package: {ModelEN} ({LangSuffix}) with {AccessoireEN}         ← variant
```

Template-keuze loopt over het **eerste taal-token** van de base (compound `NL/EN/FR` → NL-template).

#### LangSuffix-mapping (AFAS-data-leidend)

| `language_code` | Suffix |
|---|---|
| `NL` | `NL` |
| `FR` | `FR` |
| `DE` | `DE` |
| `DK` | `DK` |
| `EN` | **`UK`** |
| `WAL` | `WAL` |
| `NL/FR` | `NL-FR` |
| `NL/EN/FR` | `NL-EN-FR` |

EN-bases krijgen suffix `UK` op basis van bestaande AFAS-werkelijkheid (11113, 11123 hebben `... PAD 350P UK` etc.).

#### Schema-uitbreiding

- `accessoires` tabel: nieuwe kolommen `naam_kort_nl`, `naam_kort_fr`, `naam_kort_en` (TEXT, nullable). Bestaande `label` blijft als interne beschrijving (lange vorm).
- `groups` tabel: nieuwe kolommen `model_name_fr`, `model_name_en` (TEXT, nullable). Bestaande `model_name` wordt `model_name_nl`. Bij compound talen wordt model-naam van de bijbehorende taal-token gebruikt.

#### CLI

- `accessoire:set-naam-kort <itemcode> <taal> <naam>` — vult/wijzigt `naam_kort_nl|fr|en`.
- `group:set-model-naam <family-head> <taal> <naam>` — idem voor groep.
- `audit:names` blijft, gebruikt nu de nieuwe per-taal-data en rapporteert drift naar canonical.

#### Defibtech-groep

User-beslissing: ook canonical NL/FR-template, geen Engelse uitzondering. Defibtech-bases worden dus gefilterd via name-drift en moeten omgezet worden naar `AED Pakket: Defibtech Lifeline AED semi-automaat NL met Rugtas` (Nederlands template, ook al gebruikt AFAS nu een ander format).

### Slices

- **Slice 37.0** — Migration: accessoires.naam_kort_nl/fr/en + groups.model_name_fr/en. Hernoem `model_name` → `model_name_nl`. VO + repository-updates + InMemory/Sqlite + tests.
- **Slice 37.1** — Refactor `VariantNamingPolicy`: drop oude template, implementeer 3 canonical templates met `LangSuffix`-mapping (EN→UK). Resolve modelnaam + accessoire-naam-kort per taal. Unit-tests met data-provider (alle 7 taal-codes × base/variant).
- **Slice 37.2** — CLI: `accessoire:set-naam-kort`, `group:set-model-naam`. Update bestaande CLI's die `model_name` referen.
- **Slice 37.3** — Live data invullen via CLI voor onze 9 accessoires (3 talen elk) + 22 groepen (3 talen elk). Daarna `audit:names` om huidige drift te zien.
- **Slice 37.4** — Eventueel `prices:fix-names`-equivalent (FbItemArticle PUT op `Ds_1043`/`Ds_1036`/`Ds_2057`) om namen in AFAS te corrigeren — apart, na confirmation.

---

## 19. Variant-label per base — 4G / USB / WiFi / 3G in canonical (slice 38 — concept)

### Probleem

Onze canonical-naam bestaat uit `<template> <model_name uit group> <taal-suffix>`. `model_name` zit op groep-niveau, niet op base-niveau. Maar binnen één groep kunnen bases voorkomen die fysiek anders zijn dan de rest van de groep — die verschillen zijn vandaag onzichtbaar:

- **Mindray Beneheart C1, groep `21011`:** `21018-DE/FR/UK` zijn de 4G-uitvoering, `21019` en `21021` zijn niet-4G. Allemaal dezelfde accessoire-matrix → terecht in dezelfde groep, maar canonical levert nu identieke namen op (`AED Pakket: Mindray Beneheart C1 semi-automaat DE`) voor de 4G- en de niet-4G-base.
- **LIFEPAK CR2:** USB / WiFi / 3G zitten ook in dezelfde groep. Zelfde mechanisme.

Effect bij `names:fix-drift --apply`: AFAS krijgt twee artikelen met identieke `Ds`. Naam-collisie + verlies van zinvolle hardware-aanduiding.

### Aanpak — optie 2 uit het overleg

Nieuwe optionele kolom `variant_label` op `group_bases`. Het template wordt:

```
<template> <model_name> <variant_label?> <taal-suffix>
```

`variant_label` is taal-neutraal (`4G`, `WiFi`, `USB`, `3G`) — bewust niet vertaald, want het zijn product-namen / radio-specs, niet copy.

Voorbeelden:
- `21018-DE` met `variant_label='4G'` → `AED Pakket: Mindray Beneheart C1 semi-automaat 4G DE`
- `21019` met `variant_label=NULL` (niet-4G) → `AED Pakket: Mindray Beneheart C1 semi-automaat FR` (huidig gedrag)
- LIFEPAK `11144` met `variant_label='WiFi'` → `AED Pakket: LIFEPAK CR2 AED volautomaat WiFi NL-UK`

### Schema

```sql
ALTER TABLE group_bases ADD COLUMN variant_label TEXT NULL;
```

`NULL` of `''` → no-op (huidig gedrag blijft 1-op-1 hetzelfde). Geen migratie-data nodig — backfill gebeurt later via CLI.

### Policy-aanpassing

`VariantNamingPolicy::expectedName(Group $group, GroupBase $base, ?Accessoire $accessoire)` — base krijgt nu een `?string $variantLabel`. Template:

```
NL-bucket:   AED Pakket: <model> [<label> ]<suffix>[ met <acc>]
FR-bucket:   Pack DAE: <model> [<label> ]<suffix>[ avec <acc>]
```

Conditioneel — alleen ingevoegd als `variantLabel` niet leeg is.

### CLI

- `base:set-variant-label <afas_itemcode> <label>` — set/clear (lege string = clear).
- Backfill via shell-script in `tmp/seed-variant-labels.sh`:
  - `21018-FR`, `21018-UK`, `21018-DE` → `4G`
  - LIFEPAK 4G/WiFi/USB-codes (lijstje afstemmen vóór backfill)

### UI

`GroupDetail` toont `variant_label` als chip naast de base-itemcode. Read-only zoals de rest van de UI.

### Audit-impact

`audit:names` houdt nu rekening met het label — voor `21018-DE` is het expected nu `AED Pakket: Mindray Beneheart C1 semi-automaat 4G DE`, drift verdwijnt voor de 4G-bases zodra het label gezet is.

### Test-strategie

- VariantNamingPolicy data-provider uitbreiden: base met label, base zonder label, label + accessoire.
- Repository: round-trip van `variant_label` (NULL + value) op SQLite en InMemory.
- Migratie wordt impliciet getest via repository-tests (zie CLAUDE.md).
- E2E: na `base:set-variant-label 21018-DE 4G`, `audit:names` rapporteert geen drift meer voor die rij.

### Slices

- **Slice 38.0** — Schema + repository: kolom toevoegen, `GroupBase`-VO uitbreiden met `?string $variantLabel`, InMemory + Sqlite + round-trip-test.
- **Slice 38.1** — `VariantNamingPolicy` gebruikt het label; data-provider-test uitbreiden (NL, FR, FR + accessoire, label + suffix).
- **Slice 38.2** — CLI `base:set-variant-label`. UI: chip op `GroupDetail`.
- **Slice 38.3** — Live backfill: `21018-FR/UK/DE` → `4G` + lijst LIFEPAK-bases met radio-variant. Daarna `audit:names` heruitvoeren en verifiëren dat de naam-collisies weg zijn. Pas dán evt. opnieuw `names:fix-drift --apply` voor de overgebleven drift.

---

## 20. Missende varianten in AFAS aanmaken — `variants:fix-missing` (slice 39 — concept)

### Probleem

`audit:export-missing` levert een CSV-actielijst voor het AFAS-team — handmatig invoeren in Profit. Dat schaalt slecht (honderden missende varianten) en is foutgevoelig. We willen dezelfde flow als `names:fix-drift` en `prices:fix-missing`: dry-run default, `--apply` om te schrijven, `--limit=N` voor stapsgewijze rollout, `--group=<family-head>` om scope te beperken.

### Aanpak — twee fasen

We doen dit niet in één slice omdat we cruciale onbekenden hebben rond de **AFAS POST-payload-shape voor nieuwe samenstellingen**. De `afas-connector-tools`-codebase doet alleen `PUT`s op bestaande composities; een werkende `POST` voor een nieuw FbComposition-record met `Ds` + BOM-lines is nog niet eerder gedaan. Eerst een experiment-fase, daarna de productie-slice.

#### Onbekenden die de PoC moet uitwijzen

1. **Payload-shape.** Welke combinatie van `@VaCt`, `@ItCd`, `Fields`, en de BOM-lines-array wordt door FbComposition geaccepteerd voor een create? `test-composition-variants.php` in afas-connector-tools probeerde 6 vormen — geen documentatie van wat wel werkte.
2. **Itemcode-strategie.** Accepteert AFAS onze `<baseSku>-<accessoireItemcode>`-conventie als nieuwe key, of moet AFAS hem zelf genereren (en wij hem daarna ophalen)?
3. **Verplichte velden.** Naast `Ds` waarschijnlijk: `Itemcode_Parent` (family-head), warehouse, vrije velden `Sync_Reseller_NL`/`Tonen_Reseller_NL`, type_id-coupling. Welk minimum is vereist? Welke kan AFAS afleiden van de parent?
4. **BOM-lines.** Wordt de BOM mee gepost in dezelfde call (`Lines`/`Objects`-array), of in een tweede call op een andere connector?

### Fase A — PoC (los, in `tmp/`)

Klein wegwerp-script: kies één missing variant (bv. uit een lage-risico groep zonder commerciële druk), probeer 3-5 payload-vormen via `AfasHttpClient`. Stop zodra AFAS 200 OK geeft + de itemcode in Profit terugvindbaar is.

Output: `tmp/poc-fb-composition-post-NOTES.md` met:
- werkende payload-shape (JSON-snippet)
- bevestigde itemcode-strategie
- minimaal vereiste velden
- BOM-line-aanpak

Geen contractuele tests, geen integratie in CLI. Puur experiment.

### Fase B — Productie-slice (na PoC)

Mirror van `prices:fix-missing` (slice 31):

- **Domain/Application:** `VariantFixMissingPlan` (`afasItemcode`, `canonicalName`, `bomItemcodes`, `familyHeadItemcode`, etc.). `VariantFixMissingWriter`-contract met `apply(VariantFixMissingPlan)`. `FixMissingVariantsHandler` met dry-run/apply-gedrag.
- **Infrastructure:** `HttpFbCompositionVariantWriter` implementeert de PoC-payload. `InMemoryVariantFixMissingWriter` voor tests (met `failOn*` optie).
- **CLI:** `variants:fix-missing [--group=<family-head>] [--apply] [--limit=N]`. Default dry-run. Failures → `tmp/fix-variants-{datum}.csv`. Scope-filter `--group` zodat 1 productlijn tegelijk kan rollen.
- **Audit-koppeling:** verbruikt `ListMissingVariantsHandler` (bestaand) + `VariantNamingPolicy` voor canonical naam. Skipt rijen zonder canonical (model_name/naam_kort ontbreekt) met duidelijke foutmelding.
- **UI:** `GroupDetail`-varianten-tab toont al canonical naam — geen extra UI nodig. Wel `MissingVariants`-pagina checken of die nog actueel is na fix-runs (queryCache invalidate).
- **Test-strategie:** InMemory-fake voor writer + audit-handler. Round-trip in-memory; live verificatie in laatste sub-slice op `--limit=1`.
- **Faalmodi:** AFAS rejected POST (logged + CSV), base bestaat niet meer in AFAS (skip + warning), canonical-naam mist (skip + warning, verwijst naar `set-model-naam` / `set-naam-kort`-CLI).

### PoC-resultaten (39.0 — voltooid 2026-05-29)

Live geverifieerd op twee echte missing varianten in groep 10013 (AED Samaritan PAD 350P):
- `11111-60212` (NL) — naam + 5/5 BOM-regels + 2 basis-prijzen
- `11114-60212` (DE) — naam + 5/5 BOM-regels + 3 basis-prijzen + **2 staffel-prijzen** (lijst 026 @10 en @25 stuks)

**Werkende POST-payload** (zie `tmp/poc-fb-composition-post-NOTES.md` voor de volledige JSON):

Required velden:
- `ItCd` + `@ItCd` (composite key)
- `Ds` (canonical naam uit `VariantNamingPolicy`)
- FF_PARENT (UUID `U298663…` = `Itemcode_Parent`), met family-head als value
- FF_SYNC + FF_TONEN (UUID's) op `true` zodat de variant in de webshop verschijnt
- `VaCt = "1"` (Type samenstelling = Explosie — onze AED-pakketten zijn altijd Explosie)
- `Grp` (Artikelgroep — uit referentie-variant in dezelfde groep)
- `BiUn = "STK"` (Basiseenheid — constant voor onze pakketten)
- `BiSaItCd` = ItCd (Itemcode verkoop = de variant zelf)
- `VaRc = "1"` (Tariefgroep BTW 21% NL)
- `CrId = "50002"` (Inkooprelatie Defibrion)
- `CsGc` (CBS-goederencode — uit referentie-AED-artikel zoals 10111/10114)
- `StPrice = 0` (Verrekenprijs — virtuele samenstelling)
- `Objects.FbCompositionPart.Element[]` — BOM-regels met `VaIt` (`"Art"` voor type_id≠7, `"Sam"` voor type_id=7), `ItCd`, `QuUn`, `Qu`, `PrSe` (= positie × 10)

**DELETE-syntax** (voor cleanup of revert):
```
DELETE /connectors/FbComposition/FbComposition/ItCd/<code>
```

**Prijzen + staffels worden NIET door deze POST gedekt.** Bestaande `prices:fix-missing` (slice 31, staffel-aware sinds 32.5) doet dat via FbSalesPrice — chained na de variant-POST. PoC bewees dat dit end-to-end klopt.

### Scope-update: chained variants + prices

Op verzoek van user: `variants:fix-missing` moet **gelijk ook de prijzen goed zetten**. Twee stappen in één CLI:

1. POST FbComposition (deze slice) — variant + naam + BOM + sync-flags.
2. Direct erna: gebruik de bestaande `FixPriceMissingHandler` om basis + staffels in alle relevante prijslijsten te insertten.

Dit voorkomt dat de gebruiker twee commando's moet draaien en een tussen-`afas:pull` (de prijs-handler werkt op snapshot-data — moet refresh tussendoor of in-memory door-handlen wat we net gepost hebben).

**Optie A — Echt chained, twee fasen in handler:**
- Stap 1: POST varianten + verzamel succesvol ingevoegde itemcodes.
- Stap 2: refresh `afas_articles` + `afas_samenstellingen` voor die itemcodes (bij voorkeur targeted, niet volle pull) zodat `FixPriceMissingHandler` ze ziet.
- Stap 3: invoke `FixPriceMissingHandler` voor diezelfde itemcodes.

**Optie B — Simpeler, gedocumenteerd 2-staps gebruik:**
- `variants:fix-missing --apply` doet alleen POST.
- CLI-output zegt expliciet: "Run nu `afas:pull && prices:fix-missing --apply` voor prijzen".

Voorkeur: **optie A** (chained), maar met `--skip-prices`-flag als ontsnapping voor edge cases. Optie B wordt opgenomen voor de eerste sub-slice (39.3) als veiligheidsventiel, optie A als 39.4-uitbreiding.

### Slices (geüpdatet)

- **Slice 39.0 (PoC)** — ✅ Voltooid 2026-05-29. Werkende payload + chained-prijs-flow bewezen op 11111-60212 en 11114-60212. `tmp/poc-fb-composition-post.php` + `tmp/poc-fb-composition-post-NOTES.md`.
- **Slice 39.1** — Domain/Application: `VariantFixMissingPlan`, `VariantFixMissingWriter`-contract, `InMemoryVariantFixMissingWriter`, `FixMissingVariants`-handler met `--group`-filter en `--limit`. TDD met dry-run/apply/limit/failure-paths.
- **Slice 39.2** — Infrastructure: `HttpFbCompositionVariantWriter` met de PoC-payload. Spiegelt `Grp`/`CsGc` per groep uit een referentie-variant (bestaande matched in dezelfde groep). Contract-test op de payload-builder, geen live AFAS in tests.
- **Slice 39.3** — CLI `variants:fix-missing [--group=<fh>] [--apply] [--limit=N] [--skip-prices]`. Default dry-run met tabel (Base | Accessoire | Suggested SKU | Canonical naam | BOM-count). Failures → `tmp/fix-variants-{datum}.csv`. Output toont "draai nu prices:fix-missing" als `--skip-prices` aanstaat.
- **Slice 39.4** — Chained-prijs-integratie: na succesvolle variant-POST, refresh targeted snapshot voor die itemcodes, invoke `FixPriceMissingHandler` voor diezelfde codes. Tests verifiëren dat staffels meekomen.
- **Slice 39.5** — Live verificatie op `--limit=1` voor één groep. Daarna `audit:export-missing` herdraait → rij weg + prijzen erin. Pas dan grotere `--limit=N`.

---

## 21. Base-deduplicatie op afas_itemcode i.p.v. naam (slice 41 — concept)

### Probleem

`groups:import-portal-csv` heeft op 2026-06-01 een duplicate-base aangemaakt voor `11112` in groep `10013`: oude DB-rij `id=11` heet `"AED Pakket: …"`, nieuwe rij `id=103` heet `"Pack DAE: …"` — dezelfde AFAS-SKU, verschillende namen.

Oorzaak: bases worden gededupliceerd op `(group_id, name)`:
- Schema: `migrations/0006_refactor_schema.sql:29` — `UNIQUE (group_id, name)`.
- `InMemoryGroupBaseRepository.php:33` — duplicate-check via `->name === $base->name`.
- `SqliteGroupBaseRepository.php:38-41` — UNIQUE-violation → `BaseAlreadyExistsException::forNameInGroup()`.
- `ImportPortalCsvHandler.php:342-351` (`findExistingBase`) — match op `$base->name === $name`.

Toen `slice 37.4 names:fix-drift` de canonical naam in AFAS naar `"Pack DAE: …"` schreef, kreeg het portal-CSV-extract daarna ook die nieuwe naam. De import zag dat als een onbekende base (naam matched niet) → insert. De `afas_itemcode`-kolom uit `migrations/0011_base_afas_itemcode.sql` heeft géén UNIQUE — dus de duplicate ging onopgemerkt door.

De bestaande test `secondImportIsIdempotentAndPreservesUserDefinedConfig` (slice 20) controleert idempotentie alleen bij identieke namen — naam-mutatie tussen runs is niet gedekt.

### Aanpak

**Itemcode is leidend wanneer aanwezig**, naam blijft fallback voor legacy bases zonder SKU.

- Match-volgorde: `(group_id, afas_itemcode)` met beide niet-null → fallback `(group_id, name)`.
- Schema: drop UNIQUE op naam, voeg partial-UNIQUE toe op `(group_id, afas_itemcode) WHERE afas_itemcode IS NOT NULL` — SQLite ondersteunt dat. Naam-UNIQUE vervangen door non-unique index voor performance.
- Bij match: skip insert (idempotent), behoud bestaande naam zodat een eerdere `names:fix-drift` niet door een herimport wordt overschreven.

### Migratie-strategie

Bestaande duplicates moeten vóór de UNIQUE-constraint worden opgeruimd, anders faalt de migratie. We hebben er nu drie: `11112` (door deze bug aangemaakt), `11144` en `21020` (historisch, namen wijken af op taal-suffix). Beslissing per duplicate is user-input: welke rij behouden, welke verwijderen?

### Test-strategie

Nieuwe test `secondImportRemainsIdempotentAfterAfasNameChange()`:
1. Eerste import: variant met SKU X en naam A landt in DB.
2. Handmatige update: zet de naam van die base op naam B (simuleert wat `names:fix-drift --apply` extern doet).
3. Tweede import met dezelfde CSV (naam-veld = B).
4. Verwachting: nul nieuwe bases, naam B is bewaard.

Bestaande tests blijven groen: bases zonder SKU vallen op het naam-pad terug.

### Slices

- **Slice 41.0** — Diagnose + cleanup van bestaande duplicates: lijst 3 huidige duplicates (11112 / 11144 / 21020), wachten op user-keuze per rij, daarna verwijderen. Bewaart de canonical-naam-variant waar van toepassing. Backup-script in `tmp/`.
- **Slice 41.1** — Schema-migratie + repository-update: drop naam-UNIQUE, voeg partial-UNIQUE op itemcode. Nieuwe methode `GroupBaseRepository::findByAfasItemcodeInGroup()`. Nieuwe exception-variant `BaseAlreadyExistsException::forItemcodeInGroup()`. Bestaande naam-pad blijft voor SKU-loze bases.
- **Slice 41.2** — Import-handler refactor: `findExistingBase()` matcht eerst op (groep, itemcode), valt terug op naam. Skipt zonder de naam te overschrijven. Nieuwe TDD-test voor naam-change-idempotentie.
- **Slice 41.3** — Live verificatie: portal-CSV opnieuw importeren tegen huidige `samenstellingen.sqlite`. Verwachting: nul nieuwe duplicates, ook na de eerder gerunde `names:fix-drift --apply`.

---

## 22. Producttype + Subcategorie + Merknaam op nieuwe varianten (slice 42 — concept)

### Probleem

`variants:fix-missing` zet de webshop-relevante free-fields **Producttype** (`U5C3C0BC348244F0F97425794CE3FB4A8`), **Subcategorie** (`U79C8521E4FDA2AC22FF895BD89B6D273`) en **Merknaam** (`UE10D6C68486BDE5DE3CCC19EBE1E787B`) niet op nieuwe FbComposition-records. Frontend-labels in de Reseller-CSV zijn respectievelijk "Product type (#01)", "Product type (#02)" en "Merknaam"; de UpdateConnector-labels heten "Producttype", "Subcategorie", "Merknaam".

Resultaat: nieuwe varianten worden niet correct gecategoriseerd in de webshop-stromen totdat ze handmatig nageregeld worden. Doel: bij de POST direct correct.

### Bron-data

`Get_Artikelen` (de basis-GetConnector die we al gebruiken) exposed deze velden NIET. `PowerBI_Item` doet dat wel:

| Itemcode | Type_item | PT#01 | PT#02 | Merknaam |
|---|---|---|---|---|
| 10111 (los AED-artikel) | 2 (Artikel) | `AED's` | `350P` | `Heartsine` |
| 11111 (samenstelling) | 7 (Samenstelling) | `AED pakket` | `350P` | `Heartsine` |
| 11111-60112 (matched variant) | 7 | `AED pakket` | `350P` | `Heartsine` |

PT#01 verschilt per Type_item — voor onze nieuwe samenstellingen moet het `AED pakket` zijn. PT#02 en Merknaam zijn identiek aan zowel het base-AED-artikel als de matched-variant.

### Aanpak — spiegelen vanuit `referenceVariantItemcode`

We hebben al een referentie-variant per nieuw plan (`VariantFixMissingPlan::referenceVariantItemcode`, gebruikt voor `Grp`/`CsGc`-spiegel via `lookupReferenceFields`). Diezelfde lookup uitbreiden met `productType` / `subcategorie` / `merknaam` levert automatisch `AED pakket` voor PT#01 (want de referentie is óók een matched samenstelling) en de juiste PT#02 + Merknaam per groep. Geen hardcoding, geen extra CLI, geen schema.

### Slices

- **Slice 42.0** — Lookup uitbreiden: `VariantWriteContextLookup::lookupReferenceFields()` returnt nu `{grp, cbsCode, productType, subcategorie, merknaam}`. `HttpVariantWriteContextLookup` pullt `PowerBI_Item` lazy (zelfde patroon als de Get_Artikelen-pull) en bouwt een cache. `InMemoryVariantWriteContextLookup` neemt de drie nieuwe velden in zijn fixture. Contract-update op tests.
- **Slice 42.1** — `FbCompositionVariantPayloadBuilder` voegt de drie UUIDs toe wanneer de lookup-data de velden bevat. Unit-test uitbreiden met de drie assertions.
- **Slice 42.2** — Live verificatie: POST een nieuwe variant via `variants:fix-missing --apply --limit=1`, pull `PowerBI_Item` voor de nieuwe SKU, verifieer dat de drie velden 1-op-1 matchen met de referentie. Vink slice 42 af.
