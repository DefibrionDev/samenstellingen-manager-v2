# TODO — DefibSolutions-migratie

Driven door `MIGRATIE-DEFIBSOLUTIONS.md`. Per fase: alle todos af → commit + push.
Dit is migratie-tooling (bash + python), geen app-code: verificatie = dry-run +
draaien tegen de lokale kopie, niet PHPUnit.

**Volgende actie: eerste todo van Fase A.**

---

## Fase A — Lokale kopie draait (runbook stap 1.1)

- [x] Verse pull + lokale refresh: in `~/projects/wordpress-migrater` →
      `cp config-defibsolutions.ini config.ini && ./migrate.sh --pull --local-refresh`.
      Af als `http://localhost:8897` de shop met verse data toont.
      ✓ 20 aug: pull ok, site up (login-redirect = B2BKing gasten-block),
      nieuwste order 20 aug 14:41.
- [ ] Runbook + migratiescript in git: `MIGRATIE-DEFIBSOLUTIONS.md`,
      `migration/defibsolutions-migratie.sh` en de `.gitignore`-wijziging
      committen (eerste commit van deze cyclus).

## Fase B — Target-laag in het script (stap 1.2)

- [x] `DEFIBS_TARGET=lokaal|cp01` in `defibsolutions-migratie.sh`: `wpr()`
      kiest wpcli-container of ssh; de `scp`-upload in stap 4 wordt lokaal
      een volume-mount. Bestaande stappen ongewijzigd.
      ✓ 20 aug: + compose `--progress quiet` (statusregels vervuilden output)
      en `php -d memory_limit=512M` (wpcli-container OOM'de bij volle WP-load).
- [x] Verificatie: `./migration/defibsolutions-migratie.sh stap5` (dry-run)
      draait lokaal zonder ssh. ✓ 20 aug. De cp01-tegencontrole is op verzoek
      verplaatst naar Fase G: eerst alles lokaal goed, server als allerlaatste.

## Fase C — Bestaande stappen 1–5 lokaal draaien (stap 1.3)

- [x] stap1 (mail uit) + stap2 (Jetpack uit) lokaal groen
      ✓ 20 aug. Fix onderweg: wpcli draait nu als uid 33 + self-healing
      `upgrade/`-dir (`_lokaal_prep`), anders geen schrijfrechten na verse pull.
- [x] stap3 klantkoppeling: dry-run beoordelen → apply → steekproef usermeta
      ✓ 20 aug: 94 gezet, herdraai "94 stonden al goed", usermeta klopt.
- [x] stap4 plugin 1.3.14 + 91 settings lokaal geïmporteerd, plugin actief
      ✓ 20 aug. LET OP: `afas_env_type=production` — de lokale kopie praat
      met écht AFAS zodra syncs/checkout draaien (relevant voor Fase F).
- [x] stap5 API-keys: dry-run beoordelen → apply → beide tellingen op 0
      ✓ 20 aug: 4 REST-keys (incl. Improvit read_write) + 3 app-passwords weg.
      Controle-query aangescherpt: telt geen lege `a:0:{}`-restrijen meer mee.

## Fase D — Koppelbaarheids-audit (stap 1.4, meeste uitzoekwerk)

- [x] Audit-script (read-only, `work/audit-koppelbaarheid-defibsolutions.py`):
      per shop-product één rij met actie-kolom, plus familie-analyse.
      ✓ 20 aug: 549 WC-posts → 447 ok · 63 niet koppelbaar · 1 meerduidig ·
      38 structuurverschillen · 26 familie-issues · 0 dubbele SKU's.
      Rapport: `work/koppelbaarheid-defibsolutions/…xlsx`.
      Plugin-analyse leverde ook op: (a) koppeling loopt via postmeta
      `_afas_artikelnummer` → bestaande producten moeten vóór de eerste sync
      voorgekoppeld worden, anders duplicaten; (b) kinderen zonder mee-
      gevlagde parent worden door de sync geskipt → parents verplicht flaggen;
      (c) `afas_sku_source_field` moet van `artikelnummer` naar
      `Artikelcode_BHV_Voordeelwinkel` (optie B), anders overschrijft de sync
      alle BHV-SKU's met itemcodes.
      Referentieregel toegevoegd (20 aug): vorm moet gelijk zijn aan
      reseller/ARKY. Resultaat: 9 afwijkingen, allemaal Prestan → zelfde
      -VAR-herstructurering als ARKY nodig (WC-only parents, geen AFAS-flags);
      23 omzettingen zijn conform referentie en dus akkoord-per-definitie.
- [ ] Rapport draaien en samen doorlopen: per probleemgeval een actie kiezen
      (BHV-veld vullen / SKU corrigeren / omzetten / opruimen / negeren).
      Af als geen rij meer zonder gekozen actie.
      Stand 20 aug in `work/voorkoppel-actielijst.csv`: 10 akkoord (kale
      AED's → samenstellingen + parents → familie-heads), 24 voorstel
      (o.a. 14 uit aed-sku-actielijst, kastvarianten, Offer/Credit lokaal),
      41 open. Geblokkeerd-regel (B-prefix = soft-delete) toegevoegd aan de
      audit: 8 producten hingen aan geblokkeerde artikelen, waarvan 4
      Defibtech-Lifeline-kastvarianten zonder actieve vervanger
      (assortiment gestopt?) — beslissing nodig.

## Fase E — Fase-2-handelingen + audit-acties als script-stappen (stap 1.5)

- [x] `work/afas-settings.json` bijwerken + stap4 herdraaien. ✓ 24 aug, met
      drie wijzigingen: `afas_sku_source_field` → `Artikelcode_BHV_Voordeelwinkel`;
      `afas_custom_fields_artikelen` (BHV-veld → extra_data, local_key
      `bhv_code`) — zat niet in de dump en zonder dit veld valt de SKU-bron
      terug op artikelnummer; `afas_mapping_artikelen` →
      `{"artikelcode_parent": "Itemcode_Parent"}` — de mapping-defaults kennen
      ons veld `Itemcode_Parent` niet, zonder dit geen families.
- [x] nieuwe stap: voorkoppeling (`stap6`) — per WC-product (publish/private,
      incl. variaties) `_afas_artikelnummer` zetten via actielijst-akkoord →
      BHV-match → itemcode-match; geblokkeerde artikelen uitgesloten.
      ✓ 24 aug lokaal: dry-run 456 → apply 456 → herdraai "456 stonden al
      goed" (idempotent). Akkoord-omzettingen (kale AED's → samenstellingen,
      parents → familie-heads) steekproefsgewijs gecontroleerd.
- [x] Prestan-herstructurering: UIT DE KRITIEKE LIJN (24 aug). Prestan staat
      nergens publish behalve op ARKY (DefibSolutions: alleen drafts;
      reseller: private/draft) — niets dat kapot kan bij de migratie. Wordt
      pas relevant als Prestan op DefibSolutions live moet; dan conform
      ARKY-model (-VAR-containers, `migration/arky-prestan-*.py`), aanvullend
      op de AFAS-flags.
- [x] B2BKing deactiveren: op verzoek (24 aug) samengevoegd met stap2
      (Jetpack uit → "overbodige plugins uit"). ✓ lokaal gedraaid: jetpack +
      b2bking + b2bking-wholesale inactive. Controle dat prijzen via de
      plugin komen volgt in Fase F (sync-proefdraai).
- [x] stap7 vervalt: vertalingen + pricing-JS waren bij ARKY een taal-fix
      (Engelse shop, plugin-frontend deels NL). DefibSolutions is nl_NL
      (geverifieerd 24 aug) — plugin-teksten en staffel-tabel zijn al
      Nederlands, niets te doen.
- [x] stap8 vervalt: DefibSolutions-checkout is al de klassieke
      `[woocommerce_checkout]`-shortcode (in een Divi-wrapper), identiek aan
      reseller (geverifieerd 24 aug op beide verse kopieën). Plugin-hooks
      (adres-selector, custom fields) draaien binnen die shortcode.
      Visuele verificatie → Fase F checkout-doorloop.
- [x] mu-plugins plaatsen = `stap7` (keuze Cas 24 aug: categorie 1+2+3, alle
      8; Points-Pro-plugins niet). Bestanden gevendored in
      `migration/mu-plugins/`, ✓ lokaal geplaatst. NB: een verse pull haalt
      ze weer weg — stap7 hoort in elke herhaal-reeks.
- [x] stap8: structuur-opruiming (akkoord Cas 24 aug) — gekoppelde simples
      die variatie horen te zijn + dubbele variaties (WPML-suffix-SKU's) naar
      de prullenbak met SKU/koppeling gestript; de sync bouwt de vervangers.
      ✓ lokaal: 9 simples + 4 dubbele variaties getrasht.
- [x] Alle voorstel-rijen actielijst akkoord (24 aug) + 2 case-mismatch-
      voorkoppelingen (100087→10219, 99886→10788) + 4 familie-heads geflagd
      in AFAS (10699, 11043, 11133, 21018-UK via fix-defibsolutions-vinkjes).
      Resultaat na 3 sync-rondes: 122 → 19 → 9 → **0 warnings**;
      "3 aangemaakt, 387 bijgewerkt"; types 339/8/166 → 325 simple /
      12 variable / 179 variaties — kastvarianten terug als variaties onder
      de nieuwe containers, met nette SKU's.
- [x] stap10: assortiment-schrappingen (besluit Kevin 25 aug; blijvers van de
      nul-omzetters zijn 10562 + 30211). `work/schraplijst-defibsolutions.csv`
      → 12 producten getrasht (11 nul-omzet + 10189FR). ✓ lokaal apply.
- [x] flag-run 28 blijvers: 26 mét omzet + 10562 + 30211 →
      Sync/Tonen_Defibsolutions_NL aan via fix-defibsolutions-vinkjes
      (dry-run → apply, 28 ok / 0 fail, 25 aug).
- [ ] GEPARKEERD (25 aug, voor later): 10148F + 10149F samenstellingen
      aanmaken via de samenstellingen-manager, daarna flaggen — óók reseller
      NL. Tot die tijd blijven de twee kale G5's ongekoppeld in de shop staan.
- [ ] Kevins einddoel (mail 25 aug): beide shops uiteindelijk exact hetzelfde
      assortiment. De 28 ook op reseller = "lijst voor later"; de 721
      alleen-reseller-artikelen staan in
      `work/koppelbaarheid-defibsolutions/assortiment-verschillen-voor-kevin.xlsx`
      (gedeeld met Kevin) — verdere assortiment-gelijktrekking is een
      eigen traject ná deze migratie.

**Plugin-versie (24 aug):** reseller-live draait `lefcreative-afas-b2b`
**2.0.4**; onze analyse was op 1.3.14 maar de kernmechanica is ongewijzigd
(zelfde `_afas_artikelnummer`-meta, sku-bron, custom-fields/mapping-opties,
parent-guards). 2.0.4 uit de verse reseller-pull gezipt naar
`work/lefcreative-afas-b2b-2.0.4.zip`; stap4 pakt automatisch de nieuwste zip.
Na de verse defibsolutions-pull (wipe) stap 1–6 opnieuw draaien met 2.0.4.

## Fase F — Proefdraaien op de lokale kopie (stap 1.6)

- [ ] Product- en prijs-sync draaien; sync-resultaat steekproefsgewijs checken
- [ ] Gemapte testklant: inloggen + checkout doorlopen (adres-selector,
      klantafspraak-prijs, verzendkosten)
- [ ] Prijsrapport herdraaien (`--vers`): 0 onverklaarde verschillen

## Fase G — Reproduceerbaarheids-check (stap 1.7)

- [ ] Lokale kopie weggooien → verse pull → alle stappen achter elkaar.
      Af als de reeks zonder handmatig ingrijpen eindigt in een werkende shop.
      Dan is het script vrijgegeven voor `DEFIBS_TARGET=cp01`.
- [ ] Allerlaatste: cp01-pad voor het eerst aanraken —
      `DEFIBS_TARGET=cp01 ./migration/defibsolutions-migratie.sh stap5`
      (read-only dry-run) en kijken wat er gebeurt. Pas daarna Fase H plannen.

## Fase H — Livegang (runbook fase 2, buiten kantooruren)

Pas plannen na Fase G; aparte go/no-go met Cas. Checklist staat in het
runbook (backup → alle stappen → controles → mail aan → week monitoren →
B2BKing opruimen).

---

## Parallel (geen fase — lange doorlooptijd, kan nu al)

- [x] AFAS-beheer: vrije velden `Sync_Defibsolutions_NL`/`Tonen_Defibsolutions_NL`
      bestaan al in `get_artikelen` (geverifieerd 20 aug, vers) en er zijn al
      386 artikelen aangevinkt — vrijwel exact de "op beide shops"-regel
      (382/384). Vlag-correcties uitgevoerd 24 aug via
      `afas-connector-tools/bin/fix-defibsolutions-vinkjes.php` (dry-run →
      apply → herdraai "staat al goed"): 60123 + 60717 aan, 10533
      (geblokkeerd) + 10219-O uit. 10219 en 10788 blijven geflagd en komen
      bij de eerste sync als nieuw product binnen (akkoord).
- [ ] Mapping klant ↔ relatie afmaken: `GEEN MATCH`-gevallen + dubbelingen
      (bv. Ehabo 13804/31149), tabblad `mapping` van het prijsrapport
