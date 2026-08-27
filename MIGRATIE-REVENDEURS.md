# Revendeurs-verhuizing runbook

`revendeurs.defibrion.fr` ("Defibrion France B2B"): Wholesale Suite →
`lefcreative-afas-b2b` + opname als website #4 in de samenstellingen-tool.
Blauwdruk: ARKY-migratie (`MIGRATIE-uitgevoerd.md`), werkwijze en scriptvorm:
DefibSolutions (`MIGRATIE-DEFIBSOLUTIONS.md`, `migration/defibsolutions-migratie.sh`).

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
- [ ] Ssh-key voor `defibrion-revendeurs-fr` op cp-01 (Cas, via CloudPanel/root)
      — nodig voor verse pulls én later `REVEND_TARGET=cp01`.
- [ ] `afas-settings.json`-equivalent + `lefcreative-afas-b2b`-zip voor deze
      shop (nieuwste versie bij LEF checken; 1.3.14 ligt in `work/`).
- [ ] Curatie-gesprek assortiment (Cas + evt. Randy) — zie B3.
- [ ] Afstemmen met de andere sessie over de tool-aanpassingen (zie fase 0).

## Fase 0 — tool-kant (samenstellingen-manager; AFGESTEMD met de andere sessie)

Pas uitvoeren na akkoord én afstemming — dit raakt de tool waar de
DefibSolutions-sessie in werkt:

1. [ ] Website-rij #4 + UUID-paar in de `websites`-tabel (via bestaand
       CLI-commando, geen hand-SQL).
2. [ ] `COLUMN_TO_UUID` uitbreiden in
       `src/Infrastructure/Publications/HttpAfasFreeFieldStateReader.php`
       (de vergeten-mapping-les: kostte vorige keer een dag debug).
3. [ ] Publicatie-vlaggen zetten volgens B3 (dry-run → `--apply`).

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
       - [ ] stap3 klant-koppeling `afas_relatie_id` — **mapping-bron nog
             onbekend**: deze shop had geen middleware-koppeling, dus geen
             orderhistorie-mapping zoals bij defibsolutions; waarschijnlijk
             e-mail-matching tegen `Get_Verkooprelaties` (uitzoeken)
       - [ ] stap4 plugin + settings — zip (1.3.14) ligt klaar; een
             `work/afas-settings-revendeurs.json` moet nog samengesteld
             (basis: defibsolutions-settings, dan `Sync_/Tonen_Revendeurs_FR`
             als filter-/actief-velden, SKU-bron = itemcode i.p.v. BHV-veld)
       - [ ] stap5 API-keys intrekken + testklant-login
       Shop is bevestigd fr_FR (wp-cli praat Frans); Wholesale Suite uit
       wordt een eigen omschakel-stap ná plugin + settings.
3. [ ] **1.3 Koppelbaarheids-audit** (read-only, grootste uitzoekwerk):
       per shop-product en per te syncen AFAS-artikel één rij met actie.
       Referentieregel: vorm (variable/variation/simple, zelfde parent) moet
       gelijk zijn aan reseller/ARKY per itemcode; family-heads mee-vlaggen.
       Vangt: niet-koppelbare SKU's, structuurverschillen, eenzijdige
       producten, botsende SKU's, B-prefix-codes (nooit koppelen).
4. [ ] **1.4 Audit-acties + B2/B4-uitkomsten als script-stappen**: SKU-fixes,
       simple↔variabel-omzettingen, checkout-pagina, mu-plugins
       (`wc-variation-threshold`), evt. vertaalstap.
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
