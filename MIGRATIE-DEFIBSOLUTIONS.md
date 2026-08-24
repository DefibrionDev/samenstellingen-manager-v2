# DefibSolutions-migratie runbook

`defibsolutions.nl/shop`: B2BKing → `lefcreative-afas-b2b`. Eerst volledig
lokaal migreren (Docker), pas als dat reproduceerbaar groen is dezelfde
stappen op de server. Blauwdruk: de ARKY-migratie (`MIGRATIE-uitgevoerd.md`).

## Waar staan we

- [x] Prijsonderzoek gedraaid → `work/prijsverschillen-defibsolutions/prijsverschillen-defibsolutions.xlsx`
- [x] Migratiescript stap 1–5 gebouwd → `migration/defibsolutions-migratie.sh`
- [x] Lokale Docker-setup klaargezet → `wordpress-migrater`, `.env-defibsolutions`, poort 8897
- [ ] **Fase 1 — lokale migratie ← WE ZIJN HIER**
- [ ] Fase 2 — livegang op cp-01

**Volgende actie: stap 1.1 (verse pull + kopie starten, ~30 min).**

## Spelregels

1. **Eén script, twee targets.** Elke stap in het migratiescript voert
   wp-cli-commando's uit via één helperfunctie, `wpr()` — de stappen weten
   zelf niet wáár ze draaien. `DEFIBS_TARGET=lokaal` (default) laat `wpr()`
   het commando in de lokale wpcli-container draaien; `DEFIBS_TARGET=cp01`
   laat exact hetzelfde commando via ssh op de server draaien.
2. **Geen handwerk, alleen stappen.** Elke fix (SKU's, content, plugins)
   wordt een genummerde, idempotente, dry-run-first stap in het script.
   Losse wp-cli-oneliners zijn verboden: die ben je kwijt bij de volgende
   verse pull, een stap niet.
3. **Reproduceerbaar groen.** De lokale kopie mag altijd weg. Verse pull +
   alle stappen moet zonder handwerk eindigen in een werkende shop. Pas dan
   mag `DEFIBS_TARGET=cp01`.

## Beslispunten — alle drie beslist (20 aug 2026), niets blokkeert meer

- [x] **Klantprijzen in de plugin: JA, wordt ondersteund.**
      `lefcreative-afas-b2b` kan AFAS-prijzen per debiteur aan (beslist
      20 aug 2026). Klantafspraken hoeven dus níet omgezet naar prijslijsten.
      In stap 1.6 alleen nog verifiëren dat een testklant met afspraak de
      juiste prijs ziet.
- [x] **Leidende bron per prijsverschil: AFAS.** Kevin en Roelof hebben AFAS
      goed gezet (beslist 20 aug 2026). Geen per-rij-beslissing meer nodig:
      de shop volgt AFAS via de prijs-sync. Het prijsrapport (`--vers`
      herdraaien, de data is van 9 juli) dient nog puur als eindcontrole in
      stap 1.6 — verschillen die dan opduiken zijn sync-fouten, geen
      beslispunten.
- [x] **SKU-strategie: optie B — BHV-codes houden** (beslist 20 aug 2026).
      De shop-SKU blijft de BHV-code; de plugin matcht die op het AFAS-veld
      `Artikelcode_BHV_Voordeelwinkel` en zet de gevonden AFAS-itemcode zelf
      in een metaveld op het product. Geen omhangen, geen plugin-aanpassing —
      wel borgen dat nieuwe artikelen het BHV-veld in AFAS gevuld krijgen.
      Blijft over als rechttrekwerk (audit in stap 1.4, uitvoeren in stap
      1.5): 64 SKU's die ook als BHV-code onvindbaar zijn + 4 producten
      zonder SKU — voorstel-lijst ligt klaar in `work/aed-sku-actielijst.csv`.

---

## Fase 1 — lokale migratie (`DEFIBS_TARGET=lokaal`)

### Stap 1.1 — [ ] Verse pull + kopie starten (~30 min, grotendeels wachten)

De pull van 9 juli is verouderd. In `~/projects/wordpress-migrater`:

```bash
cp config-defibsolutions.ini config.ini
./migrate.sh --pull --local-refresh
```

**Klaar als:** `http://localhost:8897` de shop toont met verse data.

### Stap 1.2 — [ ] Target-laag in het migratiescript (~1 uur)

`wpr()` splitsen per `DEFIBS_TARGET` (wpcli-container vs ssh); de
`scp`-upload in stap 4 wordt lokaal een volume-mount. Stappen 1–5 zelf
blijven ongewijzigd.

**Klaar als:** `./migration/defibsolutions-migratie.sh stap5` (dry-run)
lokaal draait zonder ssh.

### Stap 1.3 — [ ] Bestaande stappen 1–5 lokaal draaien (~1 uur)

Mail uit · Jetpack uit · klantkoppeling (dry-run → apply) · plugin +
settings · API-keys intrekken. Mail/keys zijn lokaal inhoudelijk niet
spannend, maar bewijzen dat elke stap op het lokale target werkt.

**Klaar als:** alle vijf stappen zonder fout doorlopen zijn en de plugin
actief is (`wp plugin list`).

### Stap 1.4 — [ ] Koppelbaarheids-audit: welke producten gaan stuk? (~1 dag — hier zit het meeste uitzoekwerk)

Read-only audit-script (zelfde patroon als de no-match-audit) dat per
gepubliceerd shop-product en per te syncen AFAS-artikel één rij met een
actie-kolom oplevert. **Referentieregel:** staat een artikel op reseller of
ARKY gepubliceerd, dan moet DefibSolutions dezelfde vorm krijgen
(variable/variation/simple, zelfde parent) — de audit toetst daaraan via de
snapshot (`tmp/samenstellingen.sqlite`). Te vangen gevallen:

1. **SKU niet koppelbaar**: shop-SKU matcht geen `Artikelcode_BHV_Voordeelwinkel`
   in AFAS (64 bekende gevallen + 4 producten zonder SKU —
   `work/aed-sku-actielijst.csv` is het startpunt)
2. **Structuur verschilt**: simple product in de shop dat in de nieuwe opzet
   variabel wordt — en andersom: shop-variaties die in AFAS losse artikelen zijn
3. **Eenzijdige producten**: shop-product zonder AFAS-artikel (assortiment
   gestopt?) en AFAS-artikel mét sync-vlag zonder shop-product (komt er als
   nieuw product bij — gewenst?)
4. **Botsende SKU's**: dubbele SKU's in de shop, variaties met een eigen SKU
   die met de parent-koppeling botst

**Klaar als:** het rapport per probleemgeval een gekozen actie heeft. De
acties zelf worden script-stappen in stap 1.5.

### Stap 1.5 — [ ] Fase-2-handelingen + audit-acties omzetten naar stappen (grootste blok, 1–2 dagen)

Nieuwe script-stappen, één voor één bouwen en lokaal draaien:

1. B2BKing deactiveren (stap 6)
2. ~~Vertalingen + pricing-JS~~ — vervalt: dat was bij ARKY een taal-fix
   (Engelse shop); DefibSolutions is nl_NL, plugin is al Nederlands
3. Checkout-pagina omzetten (stap 8)
4. mu-plugins plaatsen, o.a. `wc-variation-threshold` (stap 9)
5. Acties uit de koppelbaarheids-audit van stap 1.4 (stap 10+): BHV-veld in
   AFAS vullen, shop-SKU's corrigeren, simple→variabel-omzettingen, opruimen

**Klaar als:** elke handeling uit het oude fase-2-proza én elke audit-actie
een genummerde stap is die lokaal groen draait.

### Stap 1.6 — [ ] Syncs + checkout proefdraaien (~half dagdeel)

Product- en prijs-sync draaien, gemapte testklant inloggen, checkout
doorlopen (adres-selector, prijzen, verzendkosten). Prijsrapport tegen de
kopie draaien.

**Klaar als:** het prijsrapport 0 onverklaarde verschillen toont.

### Stap 1.7 — [ ] Reproduceerbaarheids-check (~1 uur doorlooptijd)

Kopie weggooien, verse pull, alle stappen achter elkaar.

**Klaar als:** de hele reeks zonder handmatig ingrijpen eindigt in een
werkende shop. Dan is het script klaar voor de server.

---

## Fase 2 — livegang (`DEFIBS_TARGET=cp01`, buiten kantooruren)

**Vooraf regelen:** UniFi Threat Management blokkeerde op 24 aug een zware
pull-sessie naar TransIP (THREAT_BLOCKED, IPS-modus). Vóór de livegang de
bron- (TransIP) en doelserver (cp-01) uitzonderen in UniFi, anders kan de
verse pull midden in het migratievenster stilvallen.

Zelfde stappenreeks als fase 1, tegen de nieuwe server. Volgorde:

1. [ ] Volledige backup (bestanden + database) + verse pull van live
2. [ ] Alle script-stappen draaien (mail uit t/m mu-plugins)
3. [ ] Controles: prijsrapport 0 onverklaard · testorder van echte
       klantaccount t/m AFAS-order · steekproef klantafspraak / lijst 027 / gast
4. [ ] Mail weer aan, monitoren
5. [ ] Na een week stabiel: B2BKing-plugins + overbodige data verwijderen

Let op: stap 5 van het script trekt óók de read_write-key van de oude
Improvit-koppeling in — na de omschakeling kan de oude middleware niets
meer de shop in schrijven. Dat is de bedoeling.

---

## Parallel regelen — lange doorlooptijd, nu al starten

- [ ] **AFAS-beheer vragen om vrije velden** `Sync_DefibSolutions` /
      `Tonen_DefibSolutions` op artikel én verkooprelatie, opgenomen in
      `get_artikelen` en `Get_Verkooprelaties`; daarna vlaggen zetten op de
      340 gepubliceerde producten (+ relaties).
- [ ] **Mapping klant ↔ AFAS-relatie afmaken**: `GEEN MATCH`-gevallen
      oplossen (o.a. hoofdaccount "Altijd een goed contract") en dubbele
      relaties saneren (bv. Ehabo 13804/31149). Tabblad `mapping` in het
      prijsrapport is de werklijst.

---

## Context (naslag — niet nodig om te starten)

**Wat DefibSolutions anders maakt dan ARKY:**

- B2BKing i.p.v. de wholesale-plugins: 10 klantgroepen met per-product
  groepsprijzen en staffels — feitelijk klantafspraken die in AFAS moeten landen.
- SKU's zijn BHV-artikelcodes, geen AFAS-itemcodes. Koppelen kan via het
  AFAS-veld `Artikelcode_BHV_Voordeelwinkel` (connector `get_artikelen`).
- Klantspecifieke AFAS-prijzen spelen een grote rol (±10.000 afspraken,
  924 staffelreeksen) — bij ARKY vrijwel afwezig.

**Servers:** TransIP (`defibsolutionsnl@defibs.ssh.transip.me`) = oude
live-hosting. `cp-01` (eigen Hetzner/CloudPanel, `DEFIBS_SERVER` in `.env`) =
nieuwe server, serveert `defibsolutionsnl.defibrion.dev`.

**Klaarstaand materiaal in `work/`** (gitignored): `klant-relatie-mapping.csv`
(orderhistorie-mapping, bron voor script-stap 3), `afas-settings.json`
(91 opties incl. token), `lefcreative-afas-b2b-1.3.14.zip`,
`aed-sku-actielijst.csv`, prijsrapport-xlsx. Generator:
`work/audit-prijzen-defibsolutions.py` (read-only, `--vers` voor herdraai).

**9 klanten met afspraak-zonder-groep**: AFAS kent afspraken die de shop
regulier bedient — per klant kiezen: afspraak in shop activeren of in AFAS
beëindigen (tabblad `afspraak-zonder-groep`).
