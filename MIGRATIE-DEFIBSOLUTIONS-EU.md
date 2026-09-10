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
- **Thema: Divi 4.27.8** → Divi-feature-cache is vergiftigd na élke
  URL-rewrite (zie `work/handoff-divi-feature-cache.md`, les .nl 31 aug).
  Overgenomen als `stap15` (zelfde nummer als NL); 31 aug lokaal gedraaid:
  52+52+51 cache-rijen + 220 et-cache-items gepurged, homepage rendert
  daarna met volledige design-CSS. **Na elke verse pull opnieuw draaien**
  (hoort dus ook in 1.6-reproduceerbaarheid en fase 2). De oude
  "Divi lokaal kaal"-workaround (NL stap9) is een misdiagnose en vervalt.
- **BeRocket (woocommerce-ajax-filters)**: pad-cache-fix zit óók in stap15
  (`work/handoff-berocket-filter-pad-cache.md`); 31 aug lokaal gedraaid —
  paden wezen naar het oude Satserver-pad, nu geregenereerd. Losse
  bevinding (beslislijst #13): de categorie-layout verwijst naar
  filtergroepen 8699/8648 die niet in de database bestaan (echte groepen:
  91229/92532) → filterbalk rendert leeg; onduidelijk of live dit ook
  heeft (zit achter jonradio-login). **Cas 9 sep: live is óók leeg** (menu toont
  product-tables; via breadcrumb naar een categorie = lege filter). Geen
  migratieschade; reparatie is optioneel (layout → 91229/92532, ~30 min).
  **Besluit Cas 10 sep: niet repareren.**
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

- [x] **B1 — AFAS vrije velden: aangemaakt én in beide GetConnectors**
      (Cas 31 aug; geverifieerd via metainfo). Veld-UUID's uit de
      UpdateConnector-metainfo:

      | Veld | Entiteit | UUID |
      |---|---|---|
      | Sync Defibsolutions EU | artikel (FbItemArticle) | `UB5C2D2F9C5074DC8A58313AAC05CDB57` |
      | Tonen Defibsolutions EU | artikel (FbItemArticle) | `U06EEE4646B0F433EBCBC74D27D5F73C4` |
      | Sync Defibsolutions EU | verkooprelatie (KnSalesRelationOrg) | `UDAAB6826209A40A4A0E1A6D320108CD1` |
      | Tonen Defibsolutions EU | verkooprelatie (KnSalesRelationOrg) | `U183237250C6A45769621C1E546B03C8A` |

      Verwerkt in de tool (27 aug): `website:add "DefibSolutions EU"`
      (website #5) én `COLUMN_TO_UUID` uitgebreid in
      `HttpAfasFreeFieldStateReader` — mét regressietest, `make check` groen.
      Geen "Webshop klant"-veld aangemaakt; pas aanvragen als de plugin-kant
      erom vraagt.
- [ ] **B2 — SKU-/matchveld-strategie.** Audit-uitkomst (27 aug): de
      "fabrikantcodes" resolven vrijwel allemaal via
      `Artikelcode_BHV_Voordeelwinkel` — zelfde regime als NL/FR
      (itemcode eerst, dan BHV-veld, geblokkeerd telt nooit).
      **Voorstel: geen aparte strategie nodig**, plugin-matchveld = BHV
      zoals NL; alleen de audit-acties (zie 1.3) blijven over. Akkoord?
- [x] **B3 — Points & rewards: BEHOUDEN** (besluit Cas 27 aug). Beide
      plugins blijven actief en gaan mee naar de nieuwe opzet; interactie
      met AFAS-klantprijzen controleren in de checkout-test (1.5).
- [x] **B4 — Klant-relatie-mapping EU: JA, orderhistorie-methode** (besluit
      Cas 27 aug). Uitgevoerd met e-mailverificatie erbovenop, want de kale
      WC-nummers in het AFAS-Nummer-veld komen uit meerdere shops (botsingen
      aangetoond). Generator: `work/mine-order-koppeling-defibsolutionseu.py`.
      Resultaat: 38 klanten met orders → 26 geverifieerd gekoppeld (stap3
      apply gedraaid), 5 ONGEVERIFIEERD in
      `work/defibsolutionseu-klantmapping-review.csv`, 7 zonder AFAS-
      orderbewijs (blijven bewust ongekoppeld). **Review 10 sep (Cas):**
      +2 gekoppeld op domein-match (wc:142 → 31242 HLR-Hjälpen Stockholm,
      wc:166 → 32640 LifeAid DK; `HANDMATIG_OK` in de generator, stap3 +
      relatie-vlaggen gedraaid) → **28 gekoppeld**. Bewust niet: wc:4
      (info@defibsolutions.eu, eigen account), wc:130 (support-Zendesk),
      wc:161 (mail@defibsolutions.no, geen relatie).
- [x] **B5 — cp-01-site aangemaakt door Cas (9 sep):** site-user
      `defibsolutionseu`, dev-domein `defibsolutionseu.defibrion.dev`
      (138.199.223.146), DB `defibrion-defibsolutionseu`. Credentials staan
      alléén in `wordpress-migrater/config-defibsolutionseu.ini`
      ([destination]/[database_destination], gitignored) en de project-`.env`
      (`DEFIBSEU_SERVER/WP_ROOT/DB_NAME`). SSH: Cas' sleutel in de
      authorized_keys gezet, home van 770 → 750 (sshd-valkuil uit de handoff).
- [x] **Livegang-keuzes Cas (9 sep):** Bron Order 75 + administratie 1 +
      magazijn `*****` kloppen; DNS/Cloudflare regelt Cas tijdens het venster;
      **maintenance aan tijdens de migratie** (0 orderverlies); GEEN-MATCH-
      besluiten (sheet) afwachten, niet blokkerend. Open: login-plugin
      (jonradio) behouden op de nieuwe site? — vragen bij het slot.
- [x] **B6 — Publieke URL op livegang-dag: `shop.defibsolutions.eu`**
      (besluit Cas 9 sep; NL-les 1: vooraf uitwerken als eigen fase-2-stap —
      DNS grijs → vhost-alias → cert met SAN → wp-config → search-replace
      mét protocol → stap15).
- [x] **B7 — Bron Order-code EU = 75** (besluit Cas 9 sep; reseller 68,
      ARKY 71, NL 72, revendeurs 73). Administratie 1 / magazijn `*****`
      zoals overal (nog te bevestigen bij livegang-slot). Landt in het
      stap19-equivalent (livegang-slot) en de settings-generator.
- [x] **B8 — Plugin naar 2.1.1** (besluit Cas 9 sep, i.p.v. 2.0.7): stap4
      pint 2.1.1 zodra de zip in `work/` ligt; sync-stap bouwen op de
      job-classes (2.x), niet op de oude do_action-hooks.

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
2. [x] **1.2 Stappen mail-uit t/m API-keys** (NL stap 1–5, EU-lijst):
       - [x] stap1 mail uit (disable-emails actief)
       - [x] stap2 plugins uit: b2bking(+wholesale), wp-staging(-pro),
             wp-rocket, mainwp-child + opruiming wp-staging-mu-plugin;
             idempotent herdraaid. Points & rewards bewust nog aan (B3).
       - [x] stap5 dry-run groen (2 REST-keys, 1 app-password)
       - [x] `stap5 apply`: 2 REST-keys + 1 app-password ingetrokken,
             controle 0/0 (31 aug)
       - [x] stap3 klantkoppeling: 26 geverifieerde koppelingen gezet
             (apply + idempotentie-check groen); 5 review-gevallen open (B4)
       - [x] stap4 plugin + settings — **9 sep herzien naar 2.1.1** (besluit
             Cas; zip read-only ingepakt vanaf reseller-live op cp-01 →
             `work/lefcreative-afas-b2b-2.1.1.zip`). Settings: **161 opties**
             uit `work/afas-settings-defibsolutionseu.json`, generator nu op
             de verse reseller-live-export (`afas-settings-reseller-live-
             2.1.1.json`, 170 opties; NL-les 9 — diff toonde buiten de 6
             bewuste EU-afwijkingen 0 verschillen, wél 41 nieuwe 2.x-opties).
             Geverifieerd: plugin 2.1.1 actief, EU-vlagvelden, BHV-matchveld,
             order-push 0, cron-intervallen 1 week, testklant Cardiocare/114.
             Les 6 (stale `wp_lef_migrations`): n.v.t. — er zijn nog géén
             `wp_lef_*`-tabellen; die maakt de MigrationRunner aan in de
             sync-stap (daar de NL-vangrail overnemen). Reseller-app-token
             zit in de settings: hergebruik op live = livegang-besluit.
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
       - [x] Richting per categorie (Cas 9 sep, in chat): "alleen
             samenstellingen publiceren; per samenstelling 1 AED als basis
             van het variabele product; alles wat erbuiten valt verwijderen
             — kijk hoe NL/FR het deden". Blauwdruk = FR: omzet-lijst
             (`work/maak-omzet-aed-defibsolutionseu.py` →
             `work/defibsolutionseu-omzet-aed.csv`: 19 OMZETTEN, 3
             SAMENSTELLING-ONTBREEKT waarvan Primedic 11182/11183 echt,
             123 KANDIDAAT = Reanibex-variaties met geblokkeerde SKU's →
             structuur-opruiming, geen kale AED's) + FR-stap15 "omvormen"
             (oud product wordt zelf de container) + NL-stap8 opruiming.
             Concrete stappen: zie 1.4.
       - [x] 0009 (Cas: "corrigeer") — G3 is uitgefaseerd, geen AFAS-code
             (alleen G5-koffers 10120/10128), wc:462 heeft 0 orders →
             wordt schrappen in 1.4, niet gevlagd.
       - [x] Primedic HeartSave Y semi/vol (wc:103292/103293, kale AED's
             11182/11183 zonder samenstelling) → **BLIJVEN** als
             "reclame"-producten (Cas 9 sep, herzien: geïnteresseerden worden
             naar een ander product geleid). Van de schraplijst gehaald.
             **Hoge uitzondering op de kale-AED-regel** (Cas 9 sep): blijven
             kaal, géén samenstelling/variabel product. Daarom als los
             artikel EU-gevlagd (vinkjes +2 → 415; generator kent
             `HANDMATIG_AAN`); stap6 had ze al gekoppeld → de delta-sync
             neemt prijs/beschikbaarheid over.
       - [ ] **Kale-AED-dubbels** (vraag Cas 31 aug, blinde vlek van de
             reseller/ARKY-referentieregel omdat het taal-varianten zijn):
             `10651`/`10652` (Zoll Plus FR/DE) zijn kale AED's terwijl de
             shop de pakket-families `11651*`/`11652*` al als variaties
             verkoopt → kaal schrappen ná migratie + uit vlag-voorstel.
             **Regel Cas 31 aug: kale AED's publiceren we nooit** — opties
             zijn alleen verwijderen óf taalvariant toevoegen aan een
             bestaande samenstellingsgroep. `10187`/`10158`/`10189FR`
             (kaal, AFAS kent 11187*/11158/11165) → per stuk: verwijderen
             of samenstelling op EU publiceren. `0009` = foute SKU op
             wc:462 (G3 Carry Case) → SKU corrigeren, niet vlaggen.
             Overige EU-only codes (draagtassen, batterijen, pads,
             trainers, rugzak) zijn losse accessoires — geen dubbels.
4. [ ] **1.4 Audit-acties + fase-2-handelingen als stappen** (9 sep gestart,
       blauwdruk FR; NL-nummering):
       - [x] stap6 voorkoppeling (`_afas_artikelnummer`; bronnen: omzet-
             lijst OMZETTEN → BHV-uniek → itemcode; geblokkeerd nooit) —
             dry-run 1357 koppelingen (19 via omzet-lijst), apply gedraaid.
       - [x] stap7 mu-plugins, expliciete EU-lijst (11): FR-set 8 +
             `wcpt-cli-cache-fix` (wc-product-table-pro!) + `points-pro-
             variable-price-fix` (ultimate-points = Pro-klasse, B3) +
             `afas-prijzen-orderby`; Divi/NL-restyles, NL-login-fix en
             ARKY-tweak worden actief geweerd.
       - [x] stap11 syncs (port FR-stap10 op 2.x job-classes, NL-les-6-
             vangrail + les-8-kruischeck). **Run 1 (9 sep, 18,5 min):** 1373
             artikelen, 69.344 prijsregels, 26 relaties, 39 kortingen, 53.651
             adressen, kruischeck schoon — maar 1865 warnings/run: de plugin
             kon per family-head geen container bouwen omdat de bestaande
             EU-containers/variaties de head-codes al droegen ("Aanmaken
             variabel product overgeslagen" → "parent not found" + SKU-
             botsingen). Precies het revendeurs-scenario → stap16.
       - [x] stap16 **containers + kale AED's omvormen per family-head**
             (revendeurs-stap11 + FR-stap15 gecombineerd, zie
             `work/handoff-variabele-containers-omvormen.md`): kandidaten uit
             handgemaakte containers, OMZETTEN-simples en plugin-gebouwde
             kale containers; survivor = meeste publish-variaties, dan laagste
             id (kale plugin-containers winnen nooit); verliezers gestript +
             getrasht, variaties met geldige head omgehangen (Mindray C1 had
             beide heads door elkaar), vervallen variaties (B-codes) weg;
             alleen heads van échte samenstellings-families (Prestan-parents
             en de Zoll-trainer blijven ongemoeid). **Apply 9 sep: 32
             acties** — 21 heads omgevormd (Zoll Plus semi/vol, Zoll 3
             semi/vol, HeartSine 350/360/500P, Mindray C1 semi/vol + C2
             semi/vol, Reanibex semi/vol, Philips HS1/FRx, Lifepak CR2 WiFi
             semi/vol, Defibtech semi/vol, Cardiac Science G5 semi/vol), 11
             dubbelen weg (o.a. CR2 USB semi/vol → variatie van de WiFi-
             container, FR-besluit "twee bases onder één head"), 36 Mindray-
             variaties omgehangen, 120 vervallen Reanibex-variaties weg.
             Lessen onderweg: (a) de omzet-lijst-generator moet toestellen
             herkennen aan een "automaat"-woord of AED-zonder-accessoirestam
             (NL+EN stammen: draagtas, wandbeugel, padz, …) — "elke BOM-
             component is een toestel" trok accessoires mee en zette via
             stap6 8× een verkeerd artikelnummer (hersteld door stap6
             opnieuw); (b) de artikel-cache moet vers zijn — de 27-aug-cache
             miste de CR2-heads die FR op 3 sep zette (vangrail: elke head
             in het plan moet actief zijn en kinderen hebben).
       - [x] stap8 structuur-opruiming (port FR-stap11/NL-stap8): dry-run na
             stap16 toonde nog 9 Zoll-Plus-trainer-taalsimples (AFAS-parent
             10699; reseller/ARKY voeren die als variabel product) → apply.
             NB: AFAS-familie van de trainer is inconsistent (10698 én 10699
             als parent) — `audit:variant-parent`-werk voor de tool, niet
             blokkerend.
       - [x] stap11 run 2+3 (`zonder-prijzen delta`, na stap16/8/10):
             **van 1865 naar 2 warnings**, kruischeck schoon. Productstand
             kopie: 323 simple · 26 variable · 1090 variaties (publish).
             Rest-warning = Zoll-Plus-trainer-familie: AFAS-heads
             inconsistent (10698 EN = head van zichzelf + 10699 CZ; de 11
             andere talen hebben parent 10699) → plugin wil container
             "10699" bouwen, botst met de CZ-variatie onder de bestaande
             trainer-container (103065 = 10698-wpbase). Gevolg op de kopie:
             de 9 (+1 PT) trainer-taalsimples zijn door stap8 getrasht maar
             komen pas als variatie terug als de familie consistent is.
             **Fix uitgevoerd (akkoord Cas 9 sep):** Itemcode_Parent → 10698
             op de 11 trainer-artikelen (`afas-connector-tools/bin/fix-
             defibsolutionseu-trainer-parent.php`, 11 ok / 0 fail). Run 4
             delta-sync: **0 warnings**, run 2 overgeslagen (run 1 schoon);
             trainer-container 103065 heeft nu 12 taal-variaties.
             Productstand kopie: 323 simple · 26 variable · 1100 variaties.
       - [x] stap10 schrap-stap (NL-patroon, CSV `work/schraplijst-
             defibsolutionseu.csv`): wc:462 (G3/0009) + alle concepten (13 +
             25 Prestan-drafts, zonder strip) — apply 9 sep. **10 sep:**
             GEEN-SKU geschrapt (besluit Cas: Offer, Credit, Primedic
             Savepads Trainings-elektroden 50-pack, Lifepak 500/1000 wall
             bracket — 4 publish; de 5 drafts waren al weg). GEEN-MATCH (59
             publish-producten zonder AFAS-artikel, 12 met verkoophistorie)
             → Google Sheet voor Martina/Rogier (per rij: BHV-code vullen of
             schrappen): https://docs.google.com/spreadsheets/d/1oF0RFz0yaEheUZHLoRGByCrgYOMnj0YEaxWNmctI6Rs
             (45 rijen na SKU-correcties; 26 met voorstel uit titel-match —
             `work/audit-titelmatch-defibsolutionseu.py`: 20 sterk, 12
             mogelijk, 6 zwak, 7 geen, idee Cas 10 sep); bron `work/
             defibsolutionseu-geen-match-sheet.csv`; mail-handoff `work/
             handoff-mail-sheet-geen-match-eu.md`.
             Vraag Cas 10 sep ("is 60.123 geen BHV-code?"): 6 gepunte SKU's
             (60.123, 60.213, 70.182, 10.315, 10.317, 10.321) zijn AFAS-codes
             mét punt-typo aan de shopkant (BHV-veld = code zonder punt) →
             **stap17 SKU-correcties** (CSV `work/sku-correcties-
             defibsolutionseu.csv`, ruimt ook wees-rijen in de lookup-tabel
             op) + stap6 → gekoppeld; uit de sheet gehaald (45 rijen).
             Besluiten landen daarna in de schraplijst resp. als BHV-actie
             in AFAS (Kevin).
       - [x] **17 Reanibex-100-SIGFOX-bases geregistreerd** (akkoord Cas 9 sep
             "voeg toe, check goed in AFAS"): AFAS-precheck groen (actief,
             Samenstelling, BOM met Sigfox-toestel 5013x–5015x, 7 varianten);
             `group:add-base-from-afas` in groepen 52120/52119 + label
             `SIGFOX` + `base:publish` EU (51/51 ok); taalcodes daarna
             gecorrigeerd naar de toestel-talen (CZ/SK/EN, HU/EN/DE, HR/EN,
             EL/EN/DE, LV/EN, LT/EN, DK/EN, SE/EN/NO, SL/EN). publications:
             sync convergeert (1776 codes), audit:no-match 0, duplicate-boms
             0. **AFAS-bevindingen (writes, akkoord nodig):** (a) Itemcode_
             Parent leeg op de 17 bases én hun 119 varianten →
             `base:fix-parent` + `variant:fix-parent`; (b) 15 bases missen
             stickerset 81611 (alleen DK heeft 81411) → `stickers:restore`;
             (c) CBS-code leeg — systemisch (804 samenstellingen), niet
             EU-specifiek. Commando-lijst: `work/registreer-reanibex-sig-
             defibsolutionseu.sh`.
             **Uitgevoerd (besluit Cas 9 sep: "de tool lost het op, tool-
             breed"):** `base:fix-parent --apply` 17/17, `variant:fix-parent
             --apply` 143/143 (136 SIG + 7 Defibtech), `stickers:restore
             --apply` 850 BOM-regels ingevoegd + 528 "komt al voor" (de
             eerste run was door een reboot gekilld ná de AFAS-inserts;
             benigne, `tmp/restore-stickers-2026-09-09-141215.csv`). `afas:pull`
             gedraaid (10 sep) → `audit:variant-parent` OK, `audit:stickers`
             OK ("geen sticker-drift"), `stickers:restore` dry-run "geen
             ontbrekende stickersets", publications:sync convergeert (1776).
             GEEN-MATCH-besluiten via Google Sheet (Martina/Rogier, cc
             Roelof): `work/handoff-mail-sheet-geen-match-eu.md`.
       - [x] stap9 weergave (swatches reseller-conform + checkout-velden
             bedrijfsnaam hidden/telefoon optional) — gedraaid 9 sep.
       - [x] stap17 beheerders → AFAS-relatie 31239 (Cardiocare) + sync-pauze,
             factuurgegevens direct ververst — 5 admin-accounts, gedraaid.
       - [x] stap14 containers erven termen (FR-stap14-port) — gebouwd;
             eerste dry-run 0 (EU-containers dragen hun categorieën al).
       - [x] stap12 variatie-assen **Engels** (port FR-stap16): pa_language
             (knoppen) / pa_connectivity / pa_cpr-sensor / pa_options uit de
             tool (accessoires.naam_kort_en); dry-run 11 containers/921
             variaties; de 10 pas omgevormde containers hadden nog 0
             variaties (delta-sync neemt alleen gewijzigde artikelen mee) →
             force-run stap11 zonder-prijzen: nog steeds 0. **Echte oorzaak:**
             de 10 families (Defibtech semi/vol, Zoll 3 semi/vol, CR2 semi/vol,
             Mindray C2 semi/vol, G5 semi/vol) waren nooit EU-gevlagd — het
             vlag-voorstel kwam uit shop-producten en daar stonden alleen de
             kale AED's. Fix (9 sep, regel Cas "1 AED per samenstelling",
             Engelse shop): per groep de Engelstalige base(s) `base:publish`
             op EU — 11142-EN, 11186EN, 11187EN, 21013-UK, 21014-UK, 11148 +
             11148F, 11149 + 11149F (NL/EN, CPR-sensor als as), 11164 + 11144
             (NL/EN WiFi) — publications:sync, heads via vinkjes (`HEADS_AAN`
             in de generator), force-sync, stap14 + stap12. **Open: Defibtech
             volautomaat (head 11141) heeft geen Engelse base** (alleen NL
             11141 en FR 11143-FR) → **besluit Cas 10 sep: optie (a), NL-base
             11141 gepubliceerd op EU** (publications:sync toegepast). CR2 USB
             (11161 NL / 11165 FR; 11162 NL / 11162-FR) bewust weggelaten —
             de WiFi-bases (NL/EN) dekken die containers.
             Resultaat force-sync (run 7): 1475 artikelen in de tabel, 0
             warnings, **20 containers met variaties** (Defibtech vol wacht op
             base-keuze). stap12 met fallback: variaties die de tool niet
             matcht (467 × "base_niet_gematcht" in `audit:no-match`, tool-
             curatie) krijgen hun assen uit het artikelnummer-patroon
             `<base>-<accessoire>` → 1188 van 1208 variaties gedekt; de 20
             zonder data zijn Prestan/trainer (WC-only, bewust ongemoeid).
       - [ ] stap19 livegang-slot — ingevoegd en lokaal dry-run getest
             (`stap19 75`): push aan, 4 vrije velden met bron 75, intervallen
             900, mail aan; alleen op cp01 toepassen.
       - [x] stap18 SKU-correcties (CSV, ruimt lookup-wezen op) — zie 1.3.
       - [x] **runner** `migration/defibsolutionseu-runner.sh`: hele reeks in
             bewezen volgorde (stap16 vóór de eerste sync), pipefail, log in
             tmp/, slotregel "KLAAR —", pre-flight docker/snapshot/cache.
5. [x] **1.5 Syncs + checkout proefdraaien** (9 sep): syncs 0 warnings
       (run 7: 1475 artikelen, 69.344 prijsregels, 28 relaties, 53.651
       adressen, kruischeck schoon). Klantprijzen: variatie 52120 anoniem
       €1189 (basis) vs ingelogd Cardiocare €699 (prijslijst 026) — via
       plugin-logica én echte wc-ajax-response. Checkout (let op: de EU-
       checkout staat op slug `/cart/`, de winkelwagen op
       `/shopping-cart/`): plugin-checkout rendert met AFAS-adresformulier,
       place-order, COD (enige gateway, geen PSP), bedrijfsnaam hidden,
       telefoon optional. Product-stand: 26 variabele containers waarvan 20
       met tool-assen (1188 variaties), 4 Prestan + trainer WC-only.
       Prijsrapport-equivalent: nog niet gedraaid (klantafspraken op EU zijn
       beperkt; prijslijst-check volstond).
   [ ] **1.6 reproduceerbaarheids-check** — gestart 9 sep: `migrate.sh --pull
       --local-refresh --config defibsolutionseu` (verse pull van Satserver,
       lokale DB gewist en herladen) gevolgd door `defibsolutionseu-runner.sh`
       (19 stappen in bewezen volgorde); log `tmp/defibseu-repro-*.log`.
       Klaar als: slotregel "KLAAR — volledige reeks groen", 0 warnings,
       zelfde productstand als 1.5.
       **Run 1 (9 sep 17:44) MISLUKT in de pull:** de FTP-mirror lukte (11
       min), maar de directe MySQL-dump van Satserver leverde een lege gzip
       (20 bytes) — poort 3306 op 87.236.98.21 is vanaf hier dicht/timeout
       (op 27 aug werkte het). De migrater merkte het niet (pipeline zonder
       pipefail → exit 0) en wiste de lokale DB; de runner strandde op stap1
       ("site not installed"). Fix in `wordpress-migrater/migrate.sh`:
       pipefail + minimale dump-grootte (weigert < 1 KB). Oorzaak-onderzoek
       poort 3306: UniFi-IPS-blokkade na bulk-FTP? (memory) of Satserver-
       whitelist. **Lokale kopie is nu leeg tot een geslaagde dump.**

### AFAS-vlaggen gezet (9 sep, akkoord Cas "mag meteen") ✓

Route zoals FR: samenstellingen via de tool, losse artikelen via vinkjes.
- [x] **120 bases `base:publish`'d** op website 5 (base zelf óf een
      variant in de shop; dekt 924 van de 1350 voorstel-codes) →
      `publications:sync --apply`: **960 toegepast, 0 gefaald**; verificatie-
      dry-run convergeert ("alle 1640 itemcodes staan al goed").
- [x] **413 losse artikelen** aan via
      `afas-connector-tools/bin/fix-defibsolutionseu-vinkjes.php`
      (input-generator `work/maak-vinkjes-input-defibsolutionseu.py`;
      413 ok / 0 fail). Bewust uitgesloten (13): kale AED's 10187, 10189FR,
      10211, 10272, 10299, 10591, 10651, 10652, 10653, 11182, 11183, 70112
      + foute-SKU 0009. Onder de 413 zitten 138 AFAS-samenstellingen die
      nog niet in de tool staan (52170–52173 e.d.) — registreren in 1.4
      (`group:add-base-from-afas`), `audit:online-not-assigned` toont ze
      tot die tijd.
- [x] **26 verkooprelaties** aan via
      `apply-defibsolutionseu-relatie-vlaggen.php` (26 ok / 0 fail).

## Livegang-lessen NL (8 sept) — verwerken vóór fase 2

Bron: `work/handoff-livegang-lessen-defibsolutions.md`. Status EU-checks
(8 sept): geen stale `wp_lef_migrations` in de dump (1.3.14 maakt die
tabellen nog niet — na de 2.0.7-upgrade opnieuw checken); wél 115
`wpstg0_*`-staging-tabellen → mee in de eindschoonmaak.

1. [ ] Publieke URL EU vooraf vastleggen (beslislijst #14) en als eigen
       fase-2-stap uitwerken: DNS grijs → vhost-alias → cert met SAN →
       wp-config WP_HOME/WP_SITEURL (hardcoded door migrater!) →
       search-replace **mét protocol** (`https://oud` → `https://nieuw`,
       anders herschrijf je bestandspaden → jedi-apprentice-guard → 500)
       → stap15 apply.
2. [ ] Plugin naar 2.0.7 (beslislijst #12): stap4-zip omzetten én de
       sync-stap met directe job-classes bouwen (`SyncAddressesJob` etc.)
       — `do_action` op oude hooks is in 2.0.7 een stille no-op.
3. [ ] Settings-diff tegen reseller-live draaien vlak vóór de
       stap19-bouw (NL-les 9: complete_on_push, rfcs_field, mappings) —
       de reseller-dump-template loopt achter.
4. [ ] Kruischeck in de sync-stap overnemen (NL-stap11): gekoppelde
       relaties zonder verkooprelatie-rij printen — vlag op de
       VERKOOPRELATIE is een aparte as (NL-les 8, kostte 21 klanten
       klantprijzen).
5. [ ] Runner-wrapper met `set -o pipefail` + slotregel-check; pre-flight
       `docker ps` op alles waar stappen van lezen; ordervenster/
       maintenance-vraag vóór het venster stellen; Bron Order-code
       (beslislijst #15) weken vooraf opvragen.

## Fase 2 — livegang op cp-01 (nieuwe site, buiten kantooruren)

### Draaiboek venster (concept, 9 sep)

1. **Vooraf (dagen ervoor):** proefverhuizing `cd ~/projects/wordpress-migrater
   && ./migrate.sh --config defibsolutionseu` (pull + upload naar cp-01 +
   rewrite naar de dev-URL), daarna `DEFIBSEU_TARGET=cp01 runner` → dev-site
   bekijken. Bron Order 75 in AFAS-lijst (Kevin). DNS-plan: A-record
   `shop.defibsolutions.eu` → 138.199.223.146, alleen IPv4 (Cas).
2. **Maintenance aan op live** (Satserver, via FTP: `.maintenance` met
   `$upgrading = time()+86400`) — verifieer 503 met cache-bust `?x=…`.
   Let op: de rsync/lftp kopieert `.maintenance` mee → direct na de
   migratie op cp-01 verwijderen.
3. **Volle migratie** `./migrate.sh --config defibsolutionseu` (~10 min
   incrementeel) → `wp config get DB_NAME` op cp-01 = `defibrion-
   defibsolutionseu` (vangrail zit in controleer_config).
4. **Runner op cp01**: `DEFIBSEU_TARGET=cp01 ./migration/defibsolutionseu-
   runner.sh` (stap1–15; ~40 min, syncs ~20). Check slotregel "KLAAR —",
   0 warnings, kruischeck schoon.
5. **URL-switch naar shop.defibsolutions.eu** (NL-les 1/2, in deze volgorde):
   a. [x] DNS A-record `shop.defibsolutions.eu` → 138.199.223.146, Cloudflare
      **grijs** (Cas, 10 sep). **Na cert-uitgifte: op oranje zetten** (Cas
      herinneren).
   b. [x] nginx-vhost op cp-01: `server_name defibsolutionseu.defibrion.dev
      shop.defibsolutions.eu;` (10 sep, root; backup in /root/nginx-backups),
      nginx herladen.
   c. [x] cert: `clpctl lets-encrypt:install:certificate
      --domainName=defibsolutionseu.defibrion.dev
      --subjectAlternativeName=shop.defibsolutions.eu` — eerste poging
      NXDOMAIN (de EU-dev-naam had, anders dan NL/FR, geen DNS-record);
      na Cas' A-record (grijs) uitgegeven 10 sep 09:26: Let's Encrypt, SAN
      voor beide namen, geldig t/m 9 dec 2026, nginx herladen en via SNI
      geverifieerd. **→ Cas: beide records op oranje.**
   d. wp-config: `WP_HOME`/`WP_SITEURL` (door de migrater HARDCODED gezet) →
      `https://shop.defibsolutions.eu`;
   e. `wp search-replace 'https://defibsolutionseu.defibrion.dev'
      'https://shop.defibsolutions.eu' --all-tables --precise` — **mét
      protocol**, anders herschrijf je de webroot-paden (`/htdocs/
      defibsolutionseu.defibrion.dev`) en gaat de site plat;
   f. `DEFIBSEU_TARGET=cp01 stap15 apply` (Divi + BeRocket caches);
   g. Cloudflare weer oranje; renders checken (home, categorie, product,
      /cart/ = checkout).
6. **Testorder** als echte klant (COD) → controleer order in WC; push staat
   nog uit.
7. **Slot**: `DEFIBSEU_TARGET=cp01 stap19 75` (dry-run) → `stap19 75 apply`
   (push aan, vrije velden, intervallen 900, mail aan). Vlak ervoor
   settings-diff tegen reseller-live herhalen (2.1.1-export van 9 sep als
   basis). Testorder opnieuw → push naar AFAS bewezen → testorders
   annuleren via normaal proces.
8. **Redirects** (Cloudflare Single Redirects, vers aanmaken): `https://www.
   defibsolutions.eu/shop*` → `https://shop.defibsolutions.eu${1}` 301 +
   query string; aparte rule voor het kale domein. Verifieer met curl:
   kaal, www, diep pad, query.
9. **Nazorg**: push-monitoring eerste dagen; na een week B2BKing-plugins,
   `wpstg0_*` (115 tabellen) en mainwp-child opruimen; jonradio-login-
   plugin: besluit Cas (nu nog actief op live).

Voorbereid 9 sep: `DEFIBSEU_TARGET=cp01 stap0` draait tegen de nieuwe site
(WP 7.1, verse CloudPanel-install, DB-naam geverifieerd); DB_NAME-vangrail in
`controleer_config` geport van FR en getest (stopt bij afwijkende naam).
Migrater-config `config-defibsolutionseu.ini` heeft [destination] +
[database_destination] gevuld.

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
