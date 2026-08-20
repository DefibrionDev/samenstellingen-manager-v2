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

- [ ] Audit-script (read-only, `work/`): per shop-product en per te syncen
      AFAS-artikel één rij met actie-kolom. Vangt: SKU niet koppelbaar op
      `Artikelcode_BHV_Voordeelwinkel` · structuurverschil simple↔variabel ·
      eenzijdige producten (alleen-shop / alleen-AFAS) · botsende SKU's.
- [ ] Rapport draaien en samen doorlopen: per probleemgeval een actie kiezen
      (BHV-veld vullen / SKU corrigeren / omzetten / opruimen / negeren).
      Af als geen rij meer zonder gekozen actie.

## Fase E — Fase-2-handelingen + audit-acties als script-stappen (stap 1.5)

- [ ] stap6: B2BKing deactiveren (+ controle dat prijzen via plugin komen)
- [ ] stap7: vertalingen + pricing-JS plaatsen (conform ARKY-runbook)
- [ ] stap8: checkout-pagina omzetten
- [ ] stap9: mu-plugins plaatsen (o.a. `wc-variation-threshold`)
- [ ] stap10+: de gekozen audit-acties uit Fase D, elk als eigen
      dry-run-first stap (aantal en inhoud volgt uit het rapport)

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

- [ ] AFAS-beheer: vrije velden `Sync_DefibSolutions`/`Tonen_DefibSolutions`
      aanvragen (artikel + verkooprelatie, in `get_artikelen` +
      `Get_Verkooprelaties`) en vlaggen op de 340 producten
- [ ] Mapping klant ↔ relatie afmaken: `GEEN MATCH`-gevallen + dubbelingen
      (bv. Ehabo 13804/31149), tabblad `mapping` van het prijsrapport
