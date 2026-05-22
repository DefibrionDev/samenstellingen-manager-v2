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

1. ~~**Naam-template** voor varianten~~ — *grotendeels beantwoord door `reanibex-semi-with-safeset.csv`*: er zijn **per-taal templates**, allemaal volgens dezelfde structuur maar met taal-eigen woorden:
   - NL: `AED pakket: Reanibex 100 Semi-Automatic AED NL {(radio)} incl. safeset en stickerset`
   - FR: `Pack DAE: Reanibex 100 Semi-automatique (FR) {(radio)} avec safeset et signalétique`
   - DE/DA: `AED Package: Reanibex 100 Semi-Automatic AED {Language} {(radio)} incl. stickerset and safeset`
   - EN/overig: `AED Package: Reanibex 100 Semi-Automatic AED {Language} {(radio)} incl. safeset`

   Voor varianten met accessoire vervangt het `incl./avec ...` deel door `incl./avec {accessoire-label}`. Resterende open punten: officiële template per taal vastleggen (de data bevat inconsistenties — zie hieronder), en bevestigen of accessoire-variant óók het safeset-deel laat vallen of behoudt.

   Ontdekte extra dimensie: **radio-varianten** (`WIFI`, `SIGFOX`, `GPS + WIFI + SIGFOX`) zitten in zowel AED-component als naam. Past binnen huidig model — elke (taal × radio) is gewoon een aparte base.

   Gevonden inconsistenties in bestaande data (door audit af te vangen via template-match + normalisatie):
   - Typo `safesett` (52124).
   - Spacing `incl.safeset` zonder spatie; dubbele spaties in FR varianten.
   - Case-drift `Semi-Automatic` ↔ `Semi-automatic` binnen één taal.
   - Ontbrekend `AED Package:`-prefix bij oudere SKUs (52101, 52102, 52103, 52106, 52108).
   - Spelling `Kroatian` vs `Croatian`; `FR` vs `French`.
   - Afwijkende inhoud-suffix bij 52199/52198/52200 (`incl. electrodes, battery and safeset`).
   - 51013 vs 52112 beide gekoppeld aan NL met verschillende naam — mogelijk legacy duplicate.

2. **Prijs**: zit prijs op de samenstelling zelf of wordt die berekend uit componenten? Welke AFAS-velden / prijslijst-structuur is leidend (zie §8)?
3. **Sync/Tonen-strategie**: alle varianten altijd Sync+Tonen, of soms alleen de "vlaggenschip"-variant?
4. **SKU-collisions**: 8 accessoires × N talen × M groepen → groeit dit ooit boven het AFAS-itemcode-veld (lengte)?
5. **Andere merken**: gebruiken Defibtech / Prestan ook de `{base}-{acc}` SKU-conventie, of een andere?
