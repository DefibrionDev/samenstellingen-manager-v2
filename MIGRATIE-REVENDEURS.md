# Revendeurs-verhuizing runbook

`revendeurs.defibrion.fr` ("Defibrion France B2B"): Wholesale Suite →
`lefcreative-afas-b2b` + opname als website #4 in de samenstellingen-tool.
Blauwdruk: ARKY-migratie (`MIGRATIE-uitgevoerd.md`), werkwijze en scriptvorm:
DefibSolutions (`MIGRATIE-DEFIBSOLUTIONS.md`, `migration/defibsolutions-migratie.sh`).

## Waar staan we + openstaande todos

**Klaar (lokaal groen, 27 aug):** stap 1–8 van `migration/revendeurs-migratie.sh`
— mail uit · plugins uit · klantkoppeling (175) · plugin 2.0.4 + settings ·
API-keys weg · voorkoppeling (790) · 9 mu-plugins · Wholesale Suite uit.
Checkout bleek al shortcode-conform. Audits + curatie-lijst gegenereerd.

**Wachtend op Randy (via Cas, uitgezet 27 aug):**
- [ ] Curatie-CSV invullen (`work/randy-curatie-revendeurs-fr.csv`, 195 bases)
- [ ] 15 klantmapping-reviewgevallen (`work/revendeurs-klantmapping-review.csv`)
      — wc:18 (Edwin Roelse) en wc:197 (intern) mogen weg, besluit Cas 31 aug:
      stap10 verwijdert ze (lokaal gedaan; op cp-01 in de livegang-reeks)
- [ ] 20 GEBLOKKEERD + 8 GEEN-MATCH uit de audit (verwachting: rolt
      grotendeels vanzelf uit de curatie-lijst)

**Klaargezet in de tussentijd (27 aug, wachten alleen op vlaggen):**
- [x] Vlaggen-apply-script:
      `afas-connector-tools/bin/apply-revendeurs-vlaggen.php` (vinkjes-
      patroon: plan-CSV, dry-run default, `--apply`, resultaten-CSV,
      composite-routing, skipt geblokkeerd/onbekend, idempotente delta).
      Dry-run tegen het vlag-voorstel: 793 te zetten, 0 skips. Input-CSV is
      vervangbaar door Randy's gefilterde lijst zonder script-wijziging.
- [x] stap9 syncs gebouwd + machinerie getest (zonder-prijzen delta: plugin-
      migraties ✓, artikelen-sync 0 — verwacht zonder vlaggen, wc-sync
      schoon). Opties: `zonder-prijzen`, `delta`.
- [x] Franse vertaalkaart (B4-vervolg): `afas-tracktrace-style` 2.2 → 2.3
      kent nu nl/fr/en (fr valt per string terug op en) — eigen teksten,
      plugin-gettext-strings én statuslabels; `order-email-afas-debiteur`
      toont "Numéro de client". Geverifieerd op de kopie (locale fr_FR →
      "Suivre l'expédition" / "Expédiée"). Gedeelde mu-plugin: reseller/
      ARKY/defibsolutions krijgen 2.3 bij hun eerstvolgende stap7-run,
      nl/en-gedrag ongewijzigd.

**Daarna, in volgorde (Claude):**
- [ ] Randy's mapping-besluiten als akkoord-rijen in de generator + stap3-delta
- [ ] Randy's curatie → gefilterde vlaggen-CSV → apply-revendeurs-vlaggen
      `--apply` (na akkoord Cas)
- [x] Fase 0 tool-kant: website-rij #4 + `COLUMN_TO_UUID` (31 aug, TDD,
      `make check` groen)
- [x] AFAS-family-fixes uitgezocht (31 aug): **géén AFAS-mutaties nodig** —
      AFAS/tool zijn overal consistent. De drie CHECKs waren shop-fouten:
      C1A-containers mengen semi/vol-variaties (sync + stap10 lossen op);
      ZOLL 11661 en Mindray C2a 20013-FR/20014-FR zijn kale AED-artikelen
      (Type_item Artikel) die als pakket-variatie misbruikt zijn — de echte
      pakketfamilies (11656 zit al in de semi-container; 21013-FR/21014-FR)
      bestaan compleet. Audit kent nu oordeel KALE-AED-VARIATIE (3×, regel:
      variatie → artikel dat geen Samenstelling is); die drie zijn uit
      voorkoppeling (stap6: 787) en vlag-voorstel (790) gehaald.
- [ ] stap9 volledig draaien (mét prijzen) + resultaat controleren
- [ ] Opruimstap 17 oude `*_parent`-containers ná geslaagde sync (stap10)
- [ ] Checkout-test met testklant (user 26)
- [ ] Reproduceerbaarheids-check: kopie weg → verse pull → alle stappen in
      één reeks zonder handwerk — dán pas is cp01 aan de beurt

**Livegang (fase 2, buiten kantooruren):** zie onderaan; Bron Order-code +
order-push aan + administratie zijn dáár bewuste acties (besluiten staan vast).

## Wat de verkenning opleverde (27 aug 2026, alles read-only)

- **De site staat al op cp-01** (origin achter Cloudflare = 138.199.223.146,
  reverse DNS `cp-01`, CloudPanel-user `defibrion-revendeurs-fr`). Dit is dus
  géén server-verhuizing maar een **plugin-/koppeling-verhuizing**: van
  Wholesale Suite (wwp/wholesale-namespaces in wp-json) naar de AFAS-plugin.
- **Stack lijkt op ARKY, niet op DefibSolutions**: Kadence + WooCommerce +
  Wholesale Suite + LiteSpeed + Jetpack + LoginPress + wp-all-import +
  woo-variation-swatches. Geen B2BKing.
- **SKU's ogen als AFAS-itemcodes** (11403, 60708, SAV-C0846, 60.112) met
  custom `*_parent`-SKU's op variabele producten (RBX100_WIFI_SEMI_parent) —
  zelfde vorm als reseller/ARKY. Geen BHV-veld-omweg nodig; audit moet dit
  bevestigen.
- **De tool is FR-klaar op naamgevingsniveau**: `VariantNamingPolicy` kent
  `fr` (prefix `Pack DAE:`, connector `avon → avec`); `websites`-tabel bevat
  alleen Reseller NL / ARKY / DefibSolutions NL → revendeurs wordt rij #4 en
  heeft een **eigen Sync/Tonen-vrije-veld-paar in AFAS nodig**.
- **Nog geen toegang**: ssh-key voor `defibrion-revendeurs-fr@cp-01` ontbreekt;
  er is nog geen `config-revendeurs.ini` in `wordpress-migrater`.

## Beslispunten — beslist door Cas (27 aug 2026)

- [x] **B1 — Vrije velden: aangemaakt én in beide GetConnectors** (geverifieerd
      via metainfo, 27 aug). Kolomnamen: `Sync_Revendeurs_FR` /
      `Tonen_Revendeurs_FR`. Veld-UUID's voor schrijven + tool-kant:

      | Veld | Entiteit | UUID |
      |---|---|---|
      | Sync Revendeurs FR | artikel (FbItemArticle) | `UBC3EEC609E9F46F89979374EBC300451` |
      | Tonen Revendeurs FR | artikel (FbItemArticle) | `U846C067CF358432E992BD5A8CE6F7141` |
      | Sync Revendeurs FR | verkooprelatie (KnSalesRelationOrg) | `U5CC926688DA8498AB9232C1F89708BFA` |
      | Tonen Revendeurs FR | verkooprelatie (KnSalesRelationOrg) | `UCBB8F9DFEF764A4FA691BA4D9D676CF8` |
      | Webshop klant (Revendeurs FR) | verkooprelatie | `U6C06597D3C5247249E439171926B297C` |
      | Klantrol (Revendeurs FR) | verkooprelatie | `UC3093761287245AAA1E1F5DE5F7EDF92` |

      De `websites`-rij #4 en `COLUMN_TO_UUID` gebruiken de twee
      **artikel**-UUID's (zelfde patroon als Reseller NL/ARKY/DefibSolutions).
- [x] **B2 — Prijsmodel: volledig.** AFAS-klantprijzen via de plugin vervangen
      de Wholesale-Suite-groepen en -staffels helemaal.
- [x] **B3 — Assortiment: eigen curatie.** Samen bekijken en waar nodig met
      Randy afstemmen; wordt een aparte curatie-werklijst vóór de
      publicatie-stap.
- [x] **B4 — Taal: Franse content staat er als het goed is al.** Geen
      vertaal-bouwstap; wel in stap 1.5 verifiëren dat plugin-frontend-teksten
      (prijzen, checkout, account) in het Frans staan — ARKY had daar een
      vertaal-/pricing-JS-stap voor nodig.
- [x] **Lokale poort: 8894** (Cas zet de kopie klaar in `wordpress-migrater`).

## Vooraf regelen (lange doorlooptijd of externe actie — parallel starten)

- [x] Sync/Tonen-kolommen in `Get_Artikelen` én `Get_Verkooprelaties`
      (gedaan door Cas, 27 aug; geverifieerd via metainfo).
- [x] Ssh-toegang `defibrion-revendeurs-fr@cp-01` werkt (geverifieerd 27 aug);
      `REVEND_SERVER`/`REVEND_WP_ROOT` staan in de project-`.env`.
- [ ] `afas-settings.json`-equivalent + `lefcreative-afas-b2b`-zip voor deze
      shop (nieuwste versie bij LEF checken; 1.3.14 ligt in `work/`).
- [ ] Curatie-gesprek assortiment (Cas + evt. Randy) — zie B3.
- [ ] Afstemmen met de andere sessie over de tool-aanpassingen (zie fase 0).
- [ ] Review-lijst klant-mapping: 17 gevallen bij Randy uitgezet (mail Cas,
      27 aug) — antwoorden verwerken als akkoord-rijen in de generator.
- [x] **Livegang-beslispunten — beslist door Cas (27 aug):**
      1. "Bron Order"-code: regelt Cas tijdens de livegang (tot dan 68).
      2. Order-push start óók op live UIT (generator zet
         `afas_sync_orders_enabled=0`); aanzetten = bewuste livegang-actie
         samen met 1 en de administratie-keuze.
      3. App-token: reseller-token wordt hergebruikt.
      4. Welcome-mails: geen risico — alle klanten bestaan al als user.

## Fase 0 — tool-kant (samenstellingen-manager)

1. [x] Website-rij #4 toegevoegd (31 aug, `website:add "Revendeurs FR"` met
       de artikel-UUID's uit B1).
2. [x] `COLUMN_TO_UUID` uitgebreid in
       `src/Infrastructure/Publications/HttpAfasFreeFieldStateReader.php`,
       TDD (regressietest naast de DefibSolutions-les); `make check` groen.
3. [ ] Publicatie-vlaggen zetten volgens B3 (dry-run → `--apply`) — wacht op
       Randy's curatie; gaat via `apply-revendeurs-vlaggen.php`.

## Fase 1 — lokale migratie (poort 8894, `REVEND_TARGET=lokaal`)

Zelfde spelregels als DefibSolutions: één script
(`migration/revendeurs-migratie.sh`), elke stap genummerd, idempotent,
dry-run default, `wpr()`-targetlaag lokaal/cp01. Reproduceerbaar groen vóór
er iets op de server gebeurt — ook al ís de server dit keer al cp-01:
stappen draaien eerst op de kopie.

1. [x] **1.1 Pull-config + verse kopie** — klaargezet door Cas (27 aug):
       `config-revendeurs.ini`, compose-project `revendeurs`,
       localhost:8894 toont de shop (HTTP 200 geverifieerd).
2. [ ] **1.2 Script-skelet + basisstappen** — skelet staat
       (`migration/revendeurs-migratie.sh`, `wpr()`-targetlaag lokaal/cp01):
       - [x] stap1 mail uit — lokaal groen (27 aug)
       - [x] stap2 wp-staging(-pro)/litespeed/jetpack/slimstat uit +
             wp-staging-optimizer-mu-plugin opruimen — lokaal groen, idempotent
       - [x] stap3 klant-koppeling `afas_relatie_id` — lokaal groen
             (dry-run + apply + idempotente herdraai, 27 aug). Mapping-bron
             bleek grotendeels in de shop te zitten: 71 users hebben een
             Wholesale-Suite-rol `role_<AFAS-debiteurnummer>` (steekproef
             5/5 klopte op naam+e-mail); de rest is e-mail-gematcht tegen
             `Get_Verkooprelaties`. Generator:
             `work/audit-klantmapping-revendeurs.py` (`--vers` voor verse
             AFAS-data) → 175/197 klant-users automatisch gekoppeld.
             Alleen dubbel bevestigde matches gaan automatisch (rol+e-mail,
             of unieke e-mail); rol-met-afwijkende-e-mail is op verzoek van
             Cas review; een `role_<nr>` die AFAS niet kent telt gewoon als
             wholesale-klant (e-mail-matching); e-mails op eigen/developer-
             domeinen (defibrion.nl, defibsolutions.nl, improvit.nl) worden
             nooit gekoppeld. **17 review-gevallen wachten op Cas** in
             `work/revendeurs-klantmapping-review.csv` (7 ambigu, 8 geen
             match waarvan 6 met exacte naam-hit, 2 rol-met-afwijkende-
             e-mail); besluiten worden akkoord-rijen die de generator
             meeneemt.
       - [x] stap4 plugin + settings — lokaal groen (27 aug):
             lefcreative-afas-b2b **2.0.4** + 120 opties uit
             `work/afas-settings-revendeurs.json`, gegenereerd door
             `work/maak-afas-settings-revendeurs.py` uit de reseller-dump
             (sjabloon = reseller, niet defibsolutions). Afwijkingen:
             `Sync_/Tonen_Revendeurs_FR`, delta-cursors weg, SKU-bron =
             `Itemcode`, checkout-veld in het Frans. Lokaal geforceerd:
             order-push uit; testklant = user 26 (Groupe France Protect,
             relatie 13054, 170 orders), wachtwoord `revend-test-2026`.
       - [x] stap5 API-keys intrekken — lokaal groen (dry-run + apply):
             4 REST-keys weg, waaronder 2 actieve Improvit-koppelingen
             (er draait dus een Improvit-middleware op deze shop — intrekken
             op live = het bewuste omschakelmoment, zelfde als
             defibsolutions). Tabel-prefix is hier `ubMIcBt_`, de stap
             vraagt hem dynamisch op.
       Shop is bevestigd fr_FR (wp-cli praat Frans); Wholesale Suite uit
       wordt een eigen omschakel-stap ná plugin + settings.
3. [x] **1.3 Koppelbaarheids-audit** — gedraaid 27 aug
       (`work/audit-koppelbaarheid-revendeurs.py`, `--vers` voor verse
       AFAS-data; snapshot-referentie van 24/25 juni). Uitkomst over 851
       producten: **790 OK · 20 geblokkeerd · 13 vorm-verschil · 8 geen
       match · 3 draft · 17 parent-containers · 0 dubbele SKU's**.
       - SKU's zijn hybride: 501 itemcodes + 325 BHV-codes, maar het
         BHV-veld bevat bij 500/501 itemcode-gevallen dezelfde code →
         SKU-bron blijft `Artikelcode_BHV_Voordeelwinkel` (stap4-settings
         gecorrigeerd en opnieuw geïmporteerd).
       - Alle 13 vorm-verschillen zijn **Prestan-drafts** (op reseller/ARKY
         variaties onder WC-only parents) — geen actie zolang ze draft
         blijven.
       - 20 GEBLOKKEERD (sku matcht alleen B-prefix/soft-delete artikelen,
         o.a. Mindray C1A, Reanibex-varianten, Aivia 200) + 8 GEEN-MATCH
         (fabrikant-partnummers, o.a. Powerheart G5 FR-versies, trainers)
         = actielijst voor Cas/Kevin: vervangend artikel kiezen, BHV-veld
         vullen, of product uit assortiment.
       - `work/revendeurs-vlag-voorstel.csv`: **793 itemcodes** (incl. 3
         family-heads) als voorstel voor `Sync_/Tonen_Revendeurs_FR` —
         input voor het curatie-gesprek met Randy (B3).
4. [ ] **1.4 Audit-acties + B2/B4-uitkomsten als script-stappen**:
       - [x] stap6 voorkoppeling `_afas_artikelnummer` — lokaal groen
             (790 gezet, dry-run + apply + idempotente herdraai, 27 aug).
             **Plugin-les (2.0.4, `AfasArtikelLookup`): de plugin
             "self-healt"** — eerste lees van een product zonder meta
             kopieert de SKU erin; bij BHV-SKU's is dat de verkeerde
             waarde. Stap6 dus altijd direct ná stap4 draaien (vóór
             frontend-verkeer) en hij overschrijft afwijkende meta
             (gerapporteerd als OVERSCHRIJF).
       - [ ] parent-containers: plugin bouwt eigen containers per
             family-head (`<head>-wpbase`); opruimstap voor de 17 oude
             `*_parent`-containers ná eerste geslaagde sync. 5 CHECKs
             (ZOLL AED 3 head-tag 11661, Mindray C2a heads ontbreken,
             C1A-tags inconsistent) = AFAS-fixes via tool/vinkjes-scripts.
       - [x] stap7 mu-plugins — lokaal groen (27 aug): expliciete selectie
             van 9 uit de gedeelde `migration/mu-plugins/` (defibs-restyles
             en points-pro-fix bewust niet). **Taal-actie:**
             `afas-tracktrace-style` rendert op fr_FR Engels (nl→NL,
             anders EN; ARKY lost dat met GTranslate op, revendeurs heeft
             geen GTranslate) → Franse vertaalkaart toevoegen of GTranslate
             — besluit B4-vervolg. `order-email-afas-debiteur` toont dan
             "Customer number".
       - [x] stap8 Wholesale Suite uit — lokaal groen (27 aug): prices +
             premium gedeactiveerd, data blijft als inerte fallback; shop
             rendert (home + productpagina 200). Prijzen komen nu uit de
             AFAS-plugin — tot de vlaggen in AFAS staan toont de shop
             reguliere prijzen.
       - [x] checkout-pagina gecontroleerd (27 aug): is al de klassieke
             `[woocommerce_checkout]`-shortcode ("Validation de la
             commande") — geen omzet-stap nodig.
       - [ ] 20 GEBLOKKEERD + 8 GEEN-MATCH afhandelen — **met Randy** (niet
             Kevin; .fr-assortiment): verwachting Cas is dat veel gevallen
             vanzelf uit de curatie-lijst rollen (product gaat eruit, of
             krijgt het vervangende artikel uit de lijst) ·
             Franse vertaalkaart voor `afas-tracktrace-style` (B4-vervolg).
5. [ ] **1.5 Syncs + checkout proefdraaien, dan reproduceerbaarheids-check**:
       product- en prijs-sync, testklant, checkout (geen echte PSP);
       daarna kopie weg, verse pull, alle stappen in één reeks zonder
       handwerk. Prijsrapport-equivalent: 0 onverklaarde verschillen.

## Fase 2 — omschakeling op cp-01 (`REVEND_TARGET=cp01`, buiten kantooruren)

1. [ ] Volledige backup (bestanden + database) van de site op cp-01.
2. [ ] UniFi: cp-01 allowlisten vóór bulk-ssh (THREAT_BLOCKED-les van 24 aug).
3. [ ] Alle script-stappen draaien; controles: prijzen 0 onverklaard,
       testorder t/m AFAS-order, steekproef klantafspraak + gast.
4. [ ] Mail weer aan, monitoren; na een week stabiel Wholesale-Suite-plugins
       en restdata opruimen.

## Wat dit runbook níet doet

- Geen werk aan defibsolutions (poort 8897) of aan tool-code zonder
  afstemming met de andere sessie.
- Geen AFAS-mutaties zonder dry-run + akkoord; geen database-drops zonder
  expliciete toestemming.
