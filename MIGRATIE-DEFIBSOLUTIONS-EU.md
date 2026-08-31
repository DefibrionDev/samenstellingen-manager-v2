# DefibSolutions-EU-migratie runbook

`defibsolutions.eu`: B2BKing → `lefcreative-afas-b2b` + verhuizing naar cp-01
(nieuwe site) + opname als website in de samenstellingen-tool.
Blauwdruk: `MIGRATIE-DEFIBSOLUTIONS.md` (werkwijze + scriptvorm) en
`MIGRATIE-REVENDEURS.md` (runbook-vorm). Eigen scriptkopie:
`migration/defibsolutionseu-migratie.sh` (besluit Cas 27 aug — zelfde keuze
als bij .fr, geen shop-parameter in het NL-script).

## Waar staan we

- [x] Handoff gelezen, scope bevestigd (27 aug): zelfde plugin-overgang als
      NL · nieuwe cp-01-site · eigen scriptkopie · talen geïnspecteerd
- [x] Verkenning lokale kopie (read-only, 27 aug) — zie hieronder
- [x] AFAS-aanvraagtekst klaargezet → `work/afas-aanvraag-defibsolutions-eu.md`
- [ ] **Aanvraag versturen naar AFAS-beheer ← actie Cas (lange doorlooptijd)**
- [x] Akkoord op dit runbook (Cas, 27 aug)
- [x] Fase 0 — script-skelet + targetlaag (stap0 groen, 27 aug)
- [ ] **Fase 1 — lokale migratie (poort 8895) ← WE ZIJN HIER**
- [ ] Fase 2 — livegang op cp-01 (nieuwe site)

## Wat de verkenning opleverde (27 aug 2026, alles read-only)

- **Lokale kopie draait al**: `wordpress-migrater`, `.env-defibsolutionseu`,
  poort 8895. Containers hebben geen restart-policy — na een reboot start
  een `compose run` ze impliciet, maar de DB heeft dan ±30 s nodig
  ("Error establishing a database connection" = even wachten, niet stuk).
- **Taal: één taal, Engels.** `WPLANG` leeg (en_US); vertaling via de
  gratis **GTranslate**-widget (client-side machinevertaling, tien talen,
  géén aparte taal-content in de database). Relevante samenstellings-bases:
  de **Engelse** — consistent met de family-head-regel (Engelse base).
- **Thema: Divi 4.27.8** → de "Divi lokaal kaal"-stap (dynamic/critical CSS
  uit) uit het NL-script is ook hier nodig.
- **Omvang**: WooCommerce 11.0.1, 366 gepubliceerde producten, ±130 users.
- **B2BKing 5.6.10 + b2bking-wholesale 5.2.40 actief** → zelfde overgang
  als NL (deactiveren, data blijft inert staan).
- **Verder actief, let op**: wp-staging + wp-staging-pro (uit! OOM-les),
  wp-rocket (cache — lokaal uit), mainwp-child (remote beheer — uit op
  kopieën?), wp-mail-smtp + wp-mail-logging (mail-uit blijft stap 1),
  2× points & rewards (beslispunt B3), cartpops, product-table-pro,
  ajax-filters, megamenu(+pro), login-as-user, loginpress.
  Geen Jetpack of Mailchimp in de actieve lijst.
- **SKU's zijn een mix** (steekproef 20, waarvan 10 gecheckt tegen de
  snapshot): samenstellings-itemcodes (`52102-60122`, `11133-60112`, `10145`)
  matchen AFAS direct; fabrikantachtige codes (`A234407000`, `XELAED001B/C`,
  `100-1640U`, `03-DAC-101`, `07-10900`, `21015`) matchen géén itemcode —
  7 van 10 geen directe match. Anders dan NL (waar álles BHV-code was) is
  hier dus eerst een audit + matchveld-beslissing nodig (beslispunt B2).

## Beslispunten — open, voor Cas

- [ ] **B1 — AFAS vrije velden aanvragen.** Tekst ligt klaar in
      `work/afas-aanvraag-defibsolutions-eu.md`: `Sync_Defibsolutions_EU` /
      `Tonen_Defibsolutions_EU` op artikel én verkooprelatie, opgenomen in
      `Get_Artikelen` / `Get_Verkooprelaties`, UUID's terug. Vandaag
      versturen; oplevering blokkeert alleen de sync-stappen, niet fase 0/1.
      Open detail: FR kreeg ook een "Webshop klant"-veld op verkooprelatie —
      meteen mee-aanvragen?
- [ ] **B2 — SKU-/matchveld-strategie.** Audit-uitkomst (27 aug): de
      "fabrikantcodes" resolven vrijwel allemaal via
      `Artikelcode_BHV_Voordeelwinkel` — zelfde regime als NL/FR
      (itemcode eerst, dan BHV-veld, geblokkeerd telt nooit).
      **Voorstel: geen aparte strategie nodig**, plugin-matchveld = BHV
      zoals NL; alleen de audit-acties (zie 1.3) blijven over. Akkoord?
- [ ] **B3 — Points & rewards.** Er draaien er twéé
      (points-and-rewards-for-woocommerce + ultimate-woocommerce-points-
      and-rewards). Meenemen naar de nieuwe opzet of uitzetten?
      Interactie met AFAS-klantprijzen is onduidelijk.
- [ ] **B4 — Klant-relatie-mapping EU.** Zelfde orderhistorie-methode als
      NL (`work/klant-relatie-mapping.csv`)? Dan een EU-variant genereren
      (±130 users, kleiner karwei dan NL).
- [ ] **B5 — cp-01-sitenaam + dev-domein.** Voorstel conform NL-patroon:
      site-user `defibsolutionseu`, serveert `defibsolutionseu.defibrion.dev`
      tot livegang. Site aanmaken loopt via wordpress-migrater (zoals
      `HANDOFF-defibsolutions-cp01.md` daar).

## Fase 0 — script-skelet ✓ (27 aug)

1. [x] `migration/defibsolutionseu-migratie.sh`: kloon van het NL-script,
       prefix `DEFIBSEU_` (`DEFIBSEU_TARGET` lokaal|cp01, `DEFIBSEU_SERVER`,
       `DEFIBSEU_WP_ROOT`), env-file `.env-defibsolutionseu`, poort 8895.
       Stappen leeg behalve de targetlaag (`wpr()`, `_lokaal_prep`,
       `controleer_config`).
2. [x] Rooktest: `stap0` draait lokaal zonder ssh (WP-versie + blogname);
       `DEFIBSEU_TARGET=cp01` weigert netjes zolang server-config ontbreekt.

## Fase 1 — lokale migratie (`DEFIBSEU_TARGET=lokaal`, poort 8895)

Spiegel van NL-fase 1; per stap eerst dry-run, EU-verschillen expliciet:

1. [x] **1.1 Verse pull** — overgeslagen: de kopie is 27 aug gepulld
       (laatste order 09:28 GMT), `config-defibsolutionseu.ini` bestaat al.
       Bron blijkt **Satserver/DirectAdmin** (FTP + directe MySQL, geen
       shell) — relevant voor fase 2. Live draait achter
       `jonradio-private-site` (migrater zet die lokaal uit).
       Reproduceerbaarheids-check (1.6) pullt sowieso opnieuw.
2. [ ] **1.2 Stappen mail-uit t/m API-keys** (NL stap 1–5, EU-lijst):
       - [x] stap1 mail uit (disable-emails actief)
       - [x] stap2 plugins uit: b2bking(+wholesale), wp-staging(-pro),
             wp-rocket, mainwp-child + opruiming wp-staging-mu-plugin;
             idempotent herdraaid. Points & rewards bewust nog aan (B3).
       - [x] stap5 dry-run groen (2 REST-keys, 1 app-password)
       - [ ] `stap5 apply` — **actie Cas** (classifier blokkeert key-deletie
             vanuit de Claude-sessie): `./migration/defibsolutionseu-migratie.sh stap5 apply`
       - [ ] stap3 klantkoppeling — wacht op EU-mapping-CSV (B4)
       - [ ] stap4 plugin + settings — wacht op EU-`afas-settings`-dump
             (NL-settings bevatten NL-token/URL — niet hergebruiken)
3. [ ] **1.3 Koppelbaarheids-audit** — script + rapport klaar (27 aug),
       acties nog te kiezen:
       - [x] `work/audit-koppelbaarheid-defibsolutionseu.py` (bewerking van
             de revendeurs-audit; cache gedeeld met de verse pull van 27 aug)
       - [x] Rapport: `work/defibsolutionseu-koppelbaarheid.csv`, 1595 rijen
             (incl. variaties): **1329 OK · 126 GEBLOKKEERD (121 variaties,
             SKU's wijzen naar B-artikelen) · 67 GEEN-MATCH (simples:
             trainers/simulators) · 40 VORM-VERSCHILT (vooral Prestan
             simple↔variation, 25 draft) · 13 draft · 9 GEEN-SKU (o.a.
             "Offer"/"Credit"-hulpproducten)**. Vlag-voorstel:
             `work/defibsolutionseu-vlag-voorstel.csv`, 1350 itemcodes
             (incl. 6 family-heads).
       - [ ] Per probleemcategorie een actie kiezen (Cas/Kevin) —
             GEBLOKKEERD-mapping, GEEN-MATCH-assortiment, Prestan-structuur,
             GEEN-SKU-opschoning. Acties worden stappen in 1.4.
4. [ ] **1.4 Audit-acties + fase-2-handelingen als stappen** (checkout-
       pagina, mu-plugins incl. wc-variation-threshold, Divi-kaal,
       structuur-omzettingen, schrappingen — naar analogie NL stap 6–14,
       alleen wat de audit nodig maakt).
5. [ ] **1.5 Syncs + checkout proefdraaien** (vereist B1-oplevering:
       UUID's, `website:add`, `COLUMN_TO_UUID`-uitbreiding!) + prijsrapport-
       equivalent · **1.6 reproduceerbaarheids-check** (verse pull → alles
       groen zonder handwerk).

## Fase 2 — livegang op cp-01 (nieuwe site, buiten kantooruren)

1. [ ] Site-user aanmaken op cp-01 (via wordpress-migrater; CloudPanel-home
       `750`, niet `770` — sshd weigert anders stil de authorized_keys).
2. [ ] UniFi Threat Management: bron- en doelserver vooraf allowlisten
       (THREAT_BLOCKED-les van 24 aug).
3. [ ] Volledige backup + verse pull van live → alle script-stappen met
       `DEFIBSEU_TARGET=cp01` → controles (prijzen 0 onverklaard, testorder
       t/m AFAS, steekproeven) → mail aan, monitoren.
4. [ ] Na een week stabiel: B2BKing-plugins + overbodige data eruit.

## Afbakening (uit de handoff)

Niet aankomen: NL-kopie (8897), cp-01-site `defibsolutionsnl`, reseller
(8899), .fr-kopie — daar werken andere sessies. Geen databases droppen,
geen AFAS-mutaties zonder dry-run + akkoord.
