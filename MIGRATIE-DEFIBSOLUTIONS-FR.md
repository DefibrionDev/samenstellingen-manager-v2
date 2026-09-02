# DefibSolutions-FR-migratie runbook

`defibsolutions.fr`: woocommerce-b2b → `lefcreative-afas-b2b`. Eerst volledig
lokaal migreren (Docker, poort 8896), pas als dat reproduceerbaar groen is
dezelfde stappen op cp-01. Blauwdruk: de NL-migratie
(`MIGRATIE-DEFIBSOLUTIONS.md` + `migration/defibsolutions-migratie.sh`);
eigen scriptkopie `migration/defibsolutionsfr-migratie.sh` (besluit 27 aug).

## Scope-besluiten (Cas, 27 aug 2026)

- [x] **Zelfde overgang als NL**: woocommerce-b2b eruit, lefcreative-afas-b2b
      erin; klantkoppeling + prijzen via AFAS.
- [x] **cp-01-site bestaat nog niet** — aanmaken hoort bij fase 2 (naam-
      voorstel: `defibsolutionsfr`, serveert `defibsolutionsfr.defibrion.dev`).
- [x] **Eigen scriptkopie** `migration/defibsolutionsfr-migratie.sh` — het
      NL-script blijft onaangeraakt tijdens diens livegang.
- [x] **AFAS-vrije velden nog niet aangevraagd** — aanvraagtekst staat klaar
      in §"Parallel regelen" hieronder; Cas verstuurt.

## Waar staan we

- [x] Fase 0 — runbook akkoord (Cas, 27 aug); vrije velden live (31 aug)
- [ ] **Fase 1 — lokale migratie ← WE ZIJN HIER** (1.1 t/m 1.3 klaar)
- [ ] Fase 2 — livegang op cp-01

**Volgende actie: stap 1.4 (koppelbaarheids-audit, ~1 dag).**

## Spelregels (identiek aan NL)

1. **Eén script, twee targets.** `wpr()`-helper; `DEFIBSFR_TARGET=lokaal`
   (default, wpcli-container van `.env-defibsolutionsfr`, poort 8896) of
   `DEFIBSFR_TARGET=cp01` (ssh). Eigen env-namen (`DEFIBSFR_SERVER`,
   `DEFIBSFR_WP_ROOT`) zodat NL- en FR-runs elkaar nooit kruisen.
2. **Geen handwerk, alleen genummerde idempotente stappen**, dry-run default,
   expliciete `apply`.
3. **Reproduceerbaar groen**: verse pull + alle stappen = werkende shop,
   zonder handwerk. Pas dan `DEFIBSFR_TARGET=cp01`.
4. **Nooit twee script-aanroepen tegelijk** op dezelfde kopie (les uit NL:
   tweede `compose run` naast een lopende sync = stille half-werk-runs).

## Wat FR anders maakt dan NL (bevonden 27 aug op de lokale kopie)

| Onderwerp | NL | FR |
|---|---|---|
| B2B-plugin | B2BKing | `woocommerce-b2b` + 2 points/rewards-plugins |
| Thema | Divi | **Woodmart** (child) — Divi-CSS-stap vervalt |
| Taal | nl_NL | fr_FR — **Franse .po/.mo voor de plugin bestaat nog niet** (alleen en_US/en_GB in `migration/afas-translations/`) |
| Producten | ±340 published | **402 published, 16 drafts, slechts 1 variatie** — vrijwel alles simple; structuur-omzetting wordt het grote audit-blok |
| Klanten | duizenden | **88** (rol customer) |
| REST-keys | Improvit | **4× read_write: Shopctrl (vandaag actief!), 2× Improvit, Dashboard** |
| AFAS-vrije velden | bestonden al | **bestaan nog niet** (tool kent alleen ARKY, DefibSolutions NL, Reseller NL) |
| Betaling | — | Mollie (blijft) |

## Beslispunten — beantwoorden vóór fase 1 kan afronden

- [x] **Shopctrl: mag weg** (besluit Cas 31 aug). Stap 5 trekt alle keys in;
      lokaal met `apply` gedraaid: 0 REST-keys, 0 app-passwords over.
- [x] **SKU-strategie FR: optie B — SKU's laten zoals ze zijn** (akkoord
      Cas 31 aug). Plugin matcht op `Artikelcode_BHV_Voordeelwinkel`
      (staat al zo in `afas-settings-fr.json`). Data: 289/403 uniek BHV,
      30 itemcode, 83 no-match + 1 zonder SKU → FR-actielijst in stap 1.4.
- [x] **Klant↔relatie-mapping: orderhistorie + e-mail-fallback** (besluit
      Cas 31 aug). Gedaan via `work/mine-order-koppeling-defibsolutionsfr.py`:
      56/88 gekoppeld (35 orderhistorie e-mail-geverifieerd, 21 unieke
      e-mail-match) en met stap 3 lokaal gezet. Restant in
      `work/defibsolutionsfr-klantmapping-review.csv`: 15 EMAIL-AMBIGU
      (dubbele AFAS-relaties — saneren zoals Ehabo bij NL), 16 GEEN-MATCH,
      1 ONGEVERIFIEERD, 1 intern account.
- [ ] **Points/rewards-plugins**: uit tijdens migratie (zoals B2BKing-aanpak)
      of blijven ze actief? Reseller draait ze ook — cross-check gewenst.
- [ ] **Prijzen FR**: welke AFAS-prijslijst(en) bedienen Franse klanten;
      apart prijsvergelijkingsrapport (zoals `audit-prijzen-defibsolutions.py`
      voor NL) nodig?
- [x] **Franse vertaling plugin**: machinaal vertaald + geplaatst (stap 8,
      31 aug); native review vóór livegang is de enige restactie.

---

## Fase 1 — lokale migratie (`DEFIBSFR_TARGET=lokaal`)

### Stap 1.1 — [x] Verse pull + kopie starten

Kopie draait, dump is van 27 aug zelf (laatste order 12:57) — geen verse
pull nodig. Bevinding voor fase 2: de FR-hosting geeft **geen SSH** — pull
loopt via FTPS + directe MySQL (`config-defibsolutionsfr.ini`, host
87.236.98.101, WP onder `/boutique`).

### Stap 1.2 — [x] Scriptskelet + target-laag

`defibsolutionsfr-migratie.sh` staat: `DEFIBSFR_*`-variabelen,
`.env-defibsolutionsfr`, poort 8896. Geverifieerd: stap5 (keys-inventaris)
draait lokaal dry-run; cp01- en onbekend-target-guards falen netjes.
Stap5-`apply` weigert bewust tot het Shopctrl-beslispunt beslist is.

### Stap 1.3 — [x] Basisstappen 1–5 porten en lokaal draaien

Klaar 31 aug: **alle vijf stappen lokaal groen.**

- [x] Stap 1 — mail uit (disable-emails actief)
- [x] Stap 2 — plugins uit: woocommerce-b2b, wp-staging(-pro), mainwp-child
      (remote-beheerkanaal, extra t.o.v. NL); ruimt ook de achterblijvende
      wp-staging-optimizer-mu-plugin op. Points/rewards-plugins bewust nog
      actief (open beslispunt).
- [x] Stap 3 — klantkoppeling gezet (31 aug): 56 users gekoppeld via
      `work/defibsolutionsfr-klant-relatie-mapping.csv`; idempotent
      geverifieerd (herrun: 56 stonden al goed).
- [x] Stap 4 — lefcreative-afas-b2b **2.0.4** (actuele zip van 24 aug; het
      NL-runbook noemt nog 1.3.14) + `work/afas-settings-fr.json` (147
      opties, FR-afleiding van de NL-dump: Sync_/Tonen_Defibsolutions_FR,
      delta-cursors leeg, scheduling + orders geforceerd 0).
      `afas_sku_source_field` staat nog op de NL-waarde — SKU-beslispunt.
- [x] Stap 5 — alle API-keys ingetrokken (31 aug, incl. Shopctrl na besluit
      Cas): 0 REST-keys, 0 app-passwords over.

**Klaar als:** stappen 1–5 zonder fout doorlopen, plugin actief.

### Stap 1.4 — [ ] Koppelbaarheids-audit (~1 dag — het meeste uitzoekwerk)

Stand 31 aug — audit gedraaid (`work/audit-koppelbaarheid-defibsolutionsfr.py`,
verse AFAS-pull; output `work/defibsolutionsfr-koppelbaarheid.csv` +
`work/defibsolutionsfr-vlag-voorstel.csv`, 318 itemcodes):

- **OK 319 · GEEN-MATCH 79 · VORM-VERSCHILT 12 · GEBLOKKEERD 8 · GEEN-SKU 1**
- Alle 12 vorm-conflicten zijn Prestan-**drafts** (bekend WC-only-VAR-patroon);
  het gepubliceerde assortiment heeft géén vorm-conflicten.
- 1 wees-variatie (wc:6285, Peli 1450) onder een niet-variable parent.
- **BESLIST (Cas, 31 aug): FR verkoopt nooit kale AED's** — elk toestel
  wordt een samenstelling met variaties (zoals Reseller NL/DefibSolutions
  NL: simple → variable met alles eronder), of wordt geschrapt.
  Omzet-lijst: `work/defibsolutionsfr-omzet-aed.csv` (generator
  `work/maak-omzet-aed-defibsolutionsfr.py`): **16 OMZETTEN** (14 via
  BOM-route + 500P/FRx bevestigd doordat de doel-base Revendeurs-FR-gevlagd
  is — idee Cas 31 aug; promotie alleen bij exact gelijke cijfer-model-
  tokens zodat FR3 nooit op FRx promoveert), **8 KANDIDAAT** (echte
  business-keuzes: CR2 USB+WiFi ×4 — tool heeft alleen NL/FR-WiFi-bases;
  View-semi en Lifeline-vol — FR-uitvoering bestaat niet; FR3 en Zoll Pro —
  geen groep, niet op Revendeurs → schrappen of nieuw), **2
  SAMENSTELLING-ONTBREEKT** (Powerheart G5 semi/auto: alleen NL/EN-bases).
- Actielijst staat klaar: `work/defibsolutionsfr-sku-actielijst.csv`
  (88 rijen; 74 met naam-gebaseerd voorstel + score, 14 zonder kandidaat;
  generator `work/maak-sku-actielijst-defibsolutionsfr.py`). Barcode/
  Itemcode_2/B-strip leverden 0 automatische matches (getest 31 aug).
  Status-kolom in te vullen door Cas/Kevin: akkoord / schrappen / anders.

Zelfde opzet als NL-stap 1.4: per published product en per te syncen
AFAS-artikel één rij met actie-kolom. **Referentieregel:** staat een artikel
op reseller/ARKY/DefibSolutions-NL gepubliceerd, dan krijgt FR dezelfde vorm
(variable/variation/simple, zelfde parent) — toetsen via de snapshot
(`tmp/samenstellingen.sqlite`). Extra zwaar voor FR: vrijwel alle 402
producten zijn simple, dus veel simple→variatie-conversies. Voordeel: de
Franse taalvarianten (FR/EN/NL- en FR/EN/ES-bases bij Mindray, LIFEPAK CR2,
Reanibex) bestaan al in de tool. De audit beantwoordt ook het SKU-beslispunt.

**Klaar als:** het rapport per probleemgeval een gekozen actie heeft.

### Stap 1.5 — [ ] Audit-acties + inrichtingsstappen als scriptstappen (1–2 dagen)

Stand 31 aug:

- [x] Stap 6 — voorkoppeling `_afas_artikelnummer`: 321 producten gezet
      (319 eenduidig + omzet-lijst-doelen; idempotent geverifieerd). De 10
      Randy-twijfelgevallen bewust onaangeroerd.
- [x] Stap 7 — mu-plugins: expliciete FR-lijst van de oorspronkelijke 8;
      de NL-specifieke `defibs-*-restyle` (Divi/NL-huisstijl), Points-Pro-
      fix en wcpt-cli-cache-fix worden actief geweerd/opgeruimd.
- [x] AFAS-vlaggen samenstellingen (31 aug, akkoord Cas): 16 bases
      `base:publish`'d op website 6, `publications:sync --apply` →
      **129 toegepast, 0 gefaald**; verificatie-dry-run convergeert
      ("alle 1565 itemcodes staan al goed").
- [x] AFAS-vlaggen kale artikelen + relaties (1 sep, akkoord Cas):
      `fix-defibsolutionsfr-vinkjes.php` → **301 artikelen** aan (input
      `work/defibsolutionsfr-vinkjes-input.csv`; de kale AED's 10140/10559/
      10189FR bewust uitgesloten — Randy-gevallen, kale AED's nooit
      publiceren) en `apply-defibsolutionsfr-relatie-vlaggen.php` →
      **56 verkooprelaties** aan. Beide geconvergeerd (herrun: 0 te doen).
- [x] Stap 8 — Franse plugin-vertaling: 39 strings machinaal vertaald
      (`migration/afas-translations/lefcreative-afas-b2b-fr_FR.po/.mo`),
      geplaatst in `wp-content/languages/plugins/`, laadt geverifieerd.
      Review door native speaker welkom vóór livegang.
- [x] Stap 9 — BeRocket-pad-cache geregenereerd (handoff-les NL 31 aug;
      wees naar het oude hostpad). Zichtbare schade was er niet: de FR-shop
      rendert de filterbalk nergens (live én kopie 0 bapf-elementen;
      BeRocket dient hier alleen WPBakery-grids). Divi-feature-cache-
      handoff n.v.t.: FR draait Woodmart.
- [x] Stap 10 — syncs draaien (1 sep): 446 artikelen (incl. 16 family-heads
      — run 1 faalde op "parent not found", heads bijgevlagd conform
      cross-site-regel), 71.860 prijsregels, 56 relaties, 39 kortingen;
      wc-sync run schoon (0 warnings). Get_Prijzen kan time-outen op 300s
      (transient — retry hielp).
- [x] Stap 11 — structuur-opruiming geport; dry-run vindt 0 gevallen:
      plugin 2.0.4 bouwt gekoppelde simples **in-place** om tot variaties
      onder de containers (16 gedaan). Stap blijft als vangnet in de
      herhaal-reeks. Shop heeft nu de reseller-structuur: 402 producten +
      146 variaties. LET OP stap 1.6: oude product-URL's van de 16
      omgebouwde toestellen bestaan niet meer (variatie ≠ pagina) —
      redirects/menu's checken.
- [x] Stap 12 — /boutique-restanten gestript (2 sep, melding Cas): de
      WPBakery-menu-blokken (cms_blocks) bevatten gemengd gecodeerde URL's
      (`host%3A…/boutique%2F…`) die buiten álle migrater-passes vallen; 47
      rijen gefixt, homepage schoon, links landen (200), idempotent.
      **Zelfde gat geldt bij de fase-2-rewrite naar
      boutique.defibsolutions.fr — stap 12 hoort in de livegang-reeks.**
- [ ] Nog te doen: variatie-assen + container-opmaak/taal (containers
      heten nu "AED Package: … (EN)" — NL-stap12/13-equivalent) ·
      checkout-pagina · weergave-instellingen (Woodmart) · schrappingen
      (na Randy) · rest SKU-actielijst.

**Klaar als:** elke handeling een genummerde stap is die lokaal groen draait.

### Stap 1.6 — [ ] Syncs + checkout proefdraaien (~half dagdeel)

Product- en prijs-sync, testklant (lokaal wachtwoord zetten), checkout
(Mollie: nooit doorklikken naar de echte PSP), prijsrapport tegen de kopie.

**Klaar als:** prijsrapport 0 onverklaarde verschillen.

### Stap 1.7 — [ ] Reproduceerbaarheids-check (~1 uur doorlooptijd)

Kopie weg (na akkoord Cas), verse pull, alle stappen achter elkaar.

**Klaar als:** de reeks zonder handmatig ingrijpen eindigt in een werkende shop.

---

## Fase 2 — livegang (`DEFIBSFR_TARGET=cp01`, buiten kantooruren)

**Definitieve URL (besluit Cas 1 sep): `boutique.defibsolutions.fr`** — de
shop verhuist van submap `defibsolutions.fr/boutique` naar een subdomein.
Lokaal draait de kopie al zonder submap (root van poort 8896), dus de
URL-herschrijving bij livegang is root → root. De hoofdsite op
`defibsolutions.fr` (aparte installatie) blijft waar hij is; daar moet bij
livegang een verwijzing/redirect van `/boutique` naar het subdomein komen.

**Vooraf regelen:** cp-01-site `defibsolutionsfr` + site-user aanmaken
(let op: CloudPanel-homes `750`, group-writable `770` laat sshd de
authorized_keys stil weigeren). UniFi Threat Management: bron- én doelserver
allowlisten vóór de pull (les van 24 aug). De kale verhuizing live → cp-01
doet het **wordpress-migrater**-project (patroon:
`HANDOFF-defibsolutions-cp01.md` aldaar); de inrichting doet dit script.

1. [ ] Volledige backup + verse pull van live
2. [ ] Alle scriptstappen (mail uit t/m vertaling/mu-plugins)
3. [ ] Controles: prijsrapport 0 onverklaard · testorder echte klant t/m
       AFAS-order · steekproef klantprijzen / gast
4. [ ] Mail weer aan, monitoren
5. [ ] Na een week stabiel: woocommerce-b2b + overbodige data verwijderen

`afas_sync_orders_enabled` blijft overal geforceerd 0 tot de livegang
(zelfde gordel als NL-stap 4); Cas zet hem live bewust handmatig aan.

---

## Parallel regelen — NU starten (lange doorlooptijd AFAS-beheer)

- [x] **Vrije velden aangemaakt** (AFAS-beheer, 31 aug) — op artikel én
      verkooprelatie, opgenomen in beide GetConnectors. UUID's (via
      metainfo geverifieerd):
      - artikel (FbComposition): Sync `U5F08630D77AB483DA576C513B14FE7C6`,
        Tonen `UFB9CAA381B1A4AA280EB05E6E26DF2A6`
      - verkooprelatie (KnSalesRelationOrg): Sync
        `U475C47EC6EE8461299BA37053337DC4D`, Tonen
        `U1F55265FE06747FA9D05E4ECCF0161A7` (nodig bij het relatie-flaggen)
- [x] **Tool bijgewerkt** (31 aug): `website:add` gedaan (website 6,
      "DefibSolutions FR") én `COLUMN_TO_UUID` uitgebreid met test
      (de tweede kant vergeten kostte bij NL een dag debug).
- [ ] **Klant↔relatie-mapping FR opbouwen** (na het bron-beslispunt).

---

## Context (naslag)

**Servers:** oude live-hosting FR nog te achterhalen (TransIP-account?);
cp-01 = eigen Hetzner/CloudPanel (`ssh root@cp-01`).

**Lokale kopie:** `~/projects/wordpress-migrater`, `.env-defibsolutionsfr`,
poort 8896; reseller-referentie op 8899. wpcli-lessen (uid 33, memory_limit,
`</dev/null` in lussen, HEX() voor lange kolommen) staan in de handoff
(`work/handoff-defibsolutions-fr.md`) en gelden onverkort.

**Blijf af (andere sessies):** NL-kopie 8897, cp-01-site `defibsolutionsnl`,
reseller 8899, .eu-kopie 8895, `migration/revendeurs-migratie.sh`.
