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

- [x] Fase 0 — runbook akkoord (Cas, 27 aug); vrije-veld-aanvraag ligt bij Cas
- [ ] **Fase 1 — lokale migratie ← WE ZIJN HIER** (1.1 + 1.2 klaar)
- [ ] Fase 2 — livegang op cp-01

**Volgende actie: stap 1.3 (basisstappen porten); Cas: AFAS-aanvraag
versturen + Shopctrl-beslispunt.**

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

- [ ] **Shopctrl**: wat doet die koppeling voor FR (orders? voorraad?), en mag
      de key weg bij de keys-intrekstap of moet hij blijven/vervangen worden?
- [ ] **SKU-strategie FR**: zijn de shop-SKU's AFAS-itemcodes, BHV-codes of
      eigen codes? (NL had BHV-codes via `Artikelcode_BHV_Voordeelwinkel`.)
      → uitzoeken in fase 1-audit; beslissing zoals NL-optie A/B.
- [ ] **Klant↔relatie-mapping**: bron voor de 88 klanten — orderhistorie zoals
      NL, of handmatig (klein genoeg)?
- [ ] **Points/rewards-plugins**: uit tijdens migratie (zoals B2BKing-aanpak)
      of blijven ze actief? Reseller draait ze ook — cross-check gewenst.
- [ ] **Prijzen FR**: welke AFAS-prijslijst(en) bedienen Franse klanten;
      apart prijsvergelijkingsrapport (zoals `audit-prijzen-defibsolutions.py`
      voor NL) nodig?
- [ ] **Franse vertaling plugin**: .po/.mo maken (Loco Translate draait al op
      de shop) — wie levert de vertaalstrings, of machinaal + review?

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

### Stap 1.3 — [ ] Basisstappen 1–5 porten en lokaal draaien (~half dagdeel)

Mail uit · overbodige plugins uit (woocommerce-b2b i.p.v. B2BKing; lot van
points/rewards volgt uit het beslispunt; wp-staging altijd uit) · klant-
koppeling (dry-run → apply; bron per beslispunt) · plugin + FR-settings-set
(`work/afas-settings-fr.json` — nog te maken: NL-dump als basis, shop-
specifieke opties nalopen) · API-keys inventariseren (intrekken pas ná het
Shopctrl-besluit).

**Klaar als:** stappen 1–5 zonder fout doorlopen, plugin actief.

### Stap 1.4 — [ ] Koppelbaarheids-audit (~1 dag — het meeste uitzoekwerk)

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

Analoog NL-stappen 6–14, geport waar van toepassing: voorkoppeling
`_afas_artikelnummer` · mu-plugins (welke van de 8 voor Woodmart relevant
zijn nalopen) · structuur-opruiming · schrappingen (lijst uit audit, akkoord
Kevin/Cas) · syncs · variatie-assen · **nieuw: Franse vertaling** (.po/.mo
plaatsen, patroon van de ARKY-taalstap). Geen Divi-stap; wel checken of
Woodmart lokaal iets vergelijkbaars nodig heeft.

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

- [ ] **Aanvraag vrije velden versturen** (Cas). Kant-en-klare tekst:

  > Voor de koppeling van defibsolutions.fr hebben we twee nieuwe vrije
  > velden nodig, exact naar het voorbeeld van Sync_Defibsolutions_NL /
  > Tonen_Defibsolutions_NL:
  >
  > 1. **Sync_Defibsolutions_FR** (ja/nee) — op **artikel** én op
  >    **verkooprelatie**
  > 2. **Tonen_Defibsolutions_FR** (ja/nee) — op **artikel** én op
  >    **verkooprelatie**
  >
  > Beide velden graag opnemen in de GetConnectors **Get_Artikelen** en
  > **Get_Verkooprelaties**. Mogen we daarna de veld-UUID's ontvangen?
  > (Die hebben we nodig voor de UpdateConnector-writes.)

- [ ] **Zodra de UUIDs binnen zijn** (één todo, twee kanten — de tweede
      vergeten kostte bij NL een dag debug):
      1. `website:add` met het Sync/Tonen-paar;
      2. `COLUMN_TO_UUID` uitbreiden in
         `src/Infrastructure/Publications/HttpAfasFreeFieldStateReader.php`.
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
