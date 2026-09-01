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
- [x] Runbook + migratiescript in git ✓ (staat allang op de remote; vinkje
      was administratieve drift).

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
- [x] Rapport draaien en samen doorlopen ✓ afgerond via de koppel-/schrap-
      rondes van 25-27 aug: 28-lijst volledig besloten, restcheck 27 aug
      vond geen rijen zonder actie meer.
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
- [x] 10148F + 10149F samenstellingen ✓ 26-27 aug: slice G5F — pakketten
      11148F/11149F + 14 varianten + prijzen in AFAS, CPR-feedback-as in
      de shop; alleen de reseller-kant staat nog geparkeerd (TODO.md).
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

- [x] Product- en prijs-sync draaien; sync-resultaat steekproefsgewijs checken
      ✓ 25 aug: prijzen-sync 13.681 debiteurprijzen; flag-gap gedicht (46
      doelsamenstellingen + heads, gevonden via 105672/52111-case — les: de
      voorkoppel-dóelen moeten geflagd, niet de oude shop-match); stap8
      trashte 19 kale-AED-simples; eindstand 464 artikelen in beheer,
      0 warnings, 293 simple / 20 variable — Reanibex e.a. nu als variaties
      onder hun containers, conform reseller.
- [x] Gemapte testklant: inloggen + checkout doorlopen (adres-selector,
      klantafspraak-prijs, verzendkosten)
      ✓ 26 aug: runtime-prijscheck 4/4 pass als AEDcompany (FRx-klantprijzen
      1000/1025/1350, staffel 60213 = 311,48); testwachtwoord nu vast in
      stap4 (lokaal). Checkout gerestyled naar reseller-layout in eigen
      huisstijl-groen #7CC68D (mu-plugin defibs-checkout-restyle, stap7):
      bredere row, coupon + punten naar rechterkolom, factuuradres als
      kop + kaderblok, leeg factuurgegevens-blok auto-verborgen, paarse
      betaal-driehoek weg; checkout-velden conform reseller in stap9
      (bedrijfsnaam hidden, telefoon/adres2 optional). Points-performance-
      fix van reseller overgenomen. Laatste assortiment-besluiten Kevin
      verwerkt: 10224 gekoppeld (zijn correctie op 93093), 30140 + 70202
      alsnog geschrapt — 28 ongekoppelde producten: 12 gekoppeld,
      16 geschrapt, 0 open. wp-staging (pro+free) uit in stap2.
- [ ] Prijsrapport herdraaien (`--vers`): 0 onverklaarde verschillen

**Drager-aanpak (25 aug, na regressie-melding Cas):** trash-en-vervang brak
menu's/content van hoofdproducten. Fix: per familie is één bestaand product de
"drager" die aan de familie-head wordt voorgekoppeld — de plugin bouwt hem
in-place om tot variable container (zelfde ID/slug/content/menu's); alleen
niet-dragers worden variaties (stap8 logt hun slugs). 9 dragers gekozen o.b.v.
menu's/basisvariant, akkoord Cas. Herbouw van verse pull t/m werkende shop
liep zonder handwerk: 293 simple / 20 variable, dragers behielden hun pagina,
run 2 automatisch overgeslagen (stap11-optimalisatie). Settings-bron is nu een
volledige export van Cas' definitieve wp-admin-configuratie (148 opties incl.
mapping + custom fields); stap4 forceert lokaal altijd orders-push uit.

**Publicatie-state hoort in de tool (25 aug):** DefibSolutions NL is als derde
website geregistreerd (`website:add`, id=3, met de twee vrije-veld-UUID's) en
kreeg alle 106 base-publicaties van Reseller NL. Daarmee is de tool de bron
voor "wat staat op welke shop" en zet `publications:sync` de AFAS-vlaggen op
bases én accessoire-varianten (additief, zet nooit iets uit). Het ad-hoc
`fix-defibsolutions-vinkjes.php` blijft alleen voor losse niet-managed
artikelen (accessoires, trainers). Effect: 855 itemcodes geflagd, waarvan 641
nieuw voor de shop — DefibSolutions krijgt reseller's volledige AED-breedte
inclusief taalvarianten (wens Cas + Kevin: beide shops identiek).

**Volgorde die daarbij hoort** (nieuwe variaties onder een al omgebouwde
container komen door het locked-mechanisme als *private* binnen):
`publications:sync --apply` → `stap11` → `stap8 apply` → `stap11 delta` →
**`stap12 apply` als laatste** (zet assen én publiceert die private variaties).

## Fase G — Reproduceerbaarheids-check (stap 1.7)

- [x] Lokale kopie weggooien → verse pull → alle stappen achter elkaar.
      ✓ 26 aug, in 33 minuten en ZONDER handmatig ingrijpen (runner:
      `tmp/faseG-runner.sh`, log `tmp/faseG-1146.log`):
      pull 4,5 min · inrichting stap1-7/9/10 80 sec · syncs 26 min
      (waarvan 21 min prijzen/adressen-import, wc-sync 169 sec) ·
      stap8 ruimde 5 dubbele variaties op · delta-sync 0 sec · stap12 45 sec.
      Eindmeting: 284 simple / 27 variable / 855 variaties · **0 warnings** ·
      1 container zonder default (Zoll Trainer, geen samenstelling) · site up.
      Script is hiermee vrijgegeven voor `DEFIBS_TARGET=cp01`.
- [x] Kale verhuizing naar cp-01 ✓ 27 aug via wordpress-migrater (staging-URL
      defibsolutionsnl.defibrion.dev, achter Cloudflare Access); site-user-SSH
      werkt (home-permissies 770→750 waren de blokkade).
- [x] cp01-pad voor het eerst aangeraakt ✓ 27 aug: DEFIBS_TARGET=cp01 stap5
      read-only dry-run foutloos (4 REST-keys + 3 app-passwords geïnventariseerd,
      incl. de in te trekken Improvit read_write-key). Vervolg —
      `DEFIBS_TARGET=cp01 ./migration/defibsolutions-migratie.sh stap5`
      (read-only dry-run) en kijken wat er gebeurt. Pas daarna Fase H plannen.

## Fase H-vooraf — Kevins staging-feedback (mail 30 aug, "Testen eerste versie")

Bron: kevin@defibsolutions.nl 30 aug; Roelof (31 aug) pakt de
prijsgerelateerde punten op (K6/K11) en beantwoordde K12 deels
(11148F/11149F zijn gekoppeld). Eén voor één langslopen met Cas.

- [x] K1 Homepage: uitgelichte producten wijken af ✓ 31 aug: module verwijst
      naar niet-bestaande categorie 315 (op live óók) → Divi-fallback toont
      nieuwste producten; op staging zijn dat de 70 sync-imports. Besluit
      Cas: gedrag is identiek aan live, eventueel aanpassen ná migratie.
      Antwoord in work/antwoorden-kevin-staging-feedback.md.
- [x] K2+K3+K4 Homepage-afwijkingen ✓ 31 aug (visuele check Cas akkoord):
      Divi's feature-cache
      (postmeta `_et_builder_module_features_cache`) keyt op md5 van
      shortcode-attrs incl. URL's; na de URL-rewrite miste élke lookup en
      behandelt Divi elke design-feature als "uit" (padding/box-shadow/
      radius/knopkleur/borders/hover weg → ingeklapte kaarten, vierkante
      hoeken, rode fallback-knoppen, "missende" merken-/services-foto's).
      Cache herstelt zichzelf nooit (callbacks draaien niet meer → 15ms-
      drempel nooit gehaald). Fix: nieuwe stap15 (cache-purge + et-cache
      leeg), apply gedraaid op cp-01 én lokaal; render-verificatie: blok
      33.380 bytes met kleur/radius/padding conform live, op PHP 8.5.
      PHP-versie was NIET de oorzaak (8.3-test gaf zelfde truncatie).
      LET OP livegang: hoofdsite (aparte install `www/`) blijft bestaan —
      DNS-cutover moet alleen /shop naar cp-01 routeren (nog uitwerken).
- [x] K5 "Defibrillator" als eerste optie ✓ 31 aug (check Cas akkoord):
      stap12 schreef volgorde naar legacy-termmeta `order_pa_<as>`, Woo >=3.6
      leest `order` — key gefixt in stap12, apply herdraaid op cp-01 + lokaal.
      Geverifieerd op alle 26 variabele producten: Defibrillator overal eerst
      (en Nederlands/Geen/Met eerst op de andere assen).
- [ ] K6 AED Plus prijzen kloppen niet: shop toont €979 basisprijslijst;
      losse semi-auto hoort €1599, met EHBO-rugzak €1004 — BIJ ROELOF;
      wacht op artikelnummer van Kevin.
- [x] K7 Defibtech semi-auto kale NL ✓ 31 aug (check Cas: prijs + in mand):
      er misten géén pakketten in AFAS. Twee lagen: (a) de kale-NL-variatie
      kwam ná de generale-stap12 als private binnen (locked container) —
      stap12-herdraai publiceerde hem; (b) Kevin/Cas testten met een
      admin-account zónder afas_relatie_id — de plugin filtert dan alle
      runtime-geprijsde variaties weg en alleen de paar met vaste Woo-prijs
      blijven over ("alleen met kast"). Zelfde gedrag als reseller (363/864
      variaties daar zonder vaste prijs, werkt al maanden). Cas heeft zijn
      account inmiddels zelf gekoppeld. Prijsloze variaties zijn dus
      model-conform; ongekoppelde accounts zien bewust een uitgedund beeld.
- [x] K8 filterblok ✓ 31 aug (check Cas: "werkt") — eerst verwijderd
      (Kevins voorstel), door Cas teruggedraaid: filters moeten wérken
      zoals live. Echte oorzaak
      gevonden: BeRocket cachet template-style-paden ABSOLUUT in optie
      `BeRocket_AAPF_getall_Template_Styles`; na de verhuizing wezen die
      naar het TransIP-pad → file_exists faalt → elke filter bailt met
      "Template not selected" → lege filterbalk. Fix in stap15:
      `do_action('bapf_include_all_tempate_styles')` regenereert de paden;
      apply gedraaid op cp-01 + lokaal, render nu gelijk aan live (Merk/
      Garantie/Accessoires voor/Soort pop gevuld). Wacht op check Cas.
      NB: rollback van de eerdere verwijdering is teruggeplaatst in de
      template; verwijder-code uit stap16 gehaald.
- [x] K9+K10 ✓ 31 aug (check Cas: "klopt werkt") via nieuwe stap16 (apply
      op cp-01 + lokaal): categorieën Reanibex 100 (Wifi)/(Sigfox) en
      AED Bundels + 3 kast-subcategorieën verwijderd incl. 4 menu-items
      (bevatten alleen variaties → toonden leeg). stap16 zit in runner +
      livegang-volgorde.
- [ ] K11 HS1-prijs: shop toont €825 i.p.v. €775 (ook op reseller) —
      BIJ ROELOF (AFAS heeft overal 775; Roelof vraagt Randy/Kevin wat
      leidend is).
- [ ] K12 G5-prijzen lopen uiteen; welke producten zijn gelinkt — Roelof
      bevestigde 11148F/11149F gekoppeld (onze nieuwe basispakketten
      1540/1780); check of daarmee alles verklaard is.

- [x] Trainer-warnings ("parent not found" 10691/10698) ✓ 31 aug: head 10699
      (CZ, geschrapt) verhangen naar 10698 (EN, conventie "head = Engelse
      base") via afas-connector-tools/bin/verhang-trainer-head.php (3 AFAS-
      mutaties). Delta-sync + stap12 op beide kopieën: trainer staat als
      nette taal-container ("Zoll AED Plus Trainer", NL/EN, default NL),
      0 warnings. stap12 uitgebreid met losse-taalcontainers-blok + titel.
      Lokaal artefact 108359 (claim op 10698) eenmalig gestript — livegang
      is veilig: stap8 stript vóór trashen.
- [x] Beheerders zien uitgedund assortiment (K7-nasleep) ✓ 31 aug: nieuwe
      stap17 — alle administrator-accounts krijgen afas_relatie_id 35801 +
      afas_sync_paused=1 (bestaande koppelingen ongemoeid), besluit Cas.
      Apply op cp-01 + lokaal; zit in runner + usage.
- [x] G5F-prijsaanvulling → NAAR ROELOF (besluit Cas 31 aug): 11148F/11149F
      missen prijslijst 029 (1540/1780) en basisprijs ***** (zusters: 2049);
      klanten op lijst 029 zien de CPR-keuze anders niet. Staat in het
      antwoorden-document onder punt 12; geen actie meer aan onze kant.

## Fase H — Livegang: GEPLAND DINSDAG 8 SEPT 2026 (buiten kantooruren)

Besluit Cas 1 sept; antwoordmail naar Kevin/Roelof is verstuurd. Volgorde
in het venster: verse pull live → cp-01 (wordpress-migrater) → runner
stap1-17 (tmp/generale-cp01-runner.sh + stap1/2) → controles (testorder,
steekproeven) → slotlijstje handmatig (order-push aan, order-vrije-velden,
cron-intervallen terug, mail aan) → DNS/URL-omzetting. Checklist runbook
fase 2 (backup → alle stappen → controles → mail aan → week monitoren →
B2BKing opruimen).

- [x] Login-pagina-fix ✓ 31 aug (check Cas: "geverifieerd, werkt"; bestond
      op live óók): mu-plugin defibs-login-fix.php verbergt de altijd-zichtbare
      "Caps lock staat aan."-melding (plugin-markup brak de core-selector
      .wp-pwd; regel bewust zonder !important zodat echte caps-detectie
      blijft werken) en maakt "Onthoud mij" klikbaar (submit-paragraaf
      overlapte de checkbox; forgetmenot kreeg z-index). Via stap7 op
      cp-01 + lokaal; browser-geverifieerd (klik vinkt aan, toggle intact).
- [x] Prijzen-sync-bug ✓ 1 sept (melding Cas: HS1 toonde 825 i.p.v. 775):
      de plugin-import upsert alle AFAS-prijshistorie over de unieke sleutel
      zonder geldigheids-check; de connector levert actueel-eerst → de
      verlopen generatie wint. Elke ooit-gewijzigde prijs stond verkeerd
      (ook op reseller-productie!). Fix: mu-plugin afas-prijzen-orderby.php
      (pre_http_request → orderbyfieldids=Begindatum,… op Get_Prijzen);
      herimport cp-01: HS1 overal 775, AED Plus 979, G5 1540/1780 — de
      prijspunten 6/11/12 uit Kevins mail zijn hiermee opgelost, alleen
      G5F-basisprijs rest voor Roelof. Bugrapport voor lefcreative:
      work/bugrapport-lefcreative-prijzen-historie.md. OPEN: zelfde fix op
      reseller-productie uitrollen (waar draait die? — Cas).
- [ ] NÁ livegang (herinnering voor Cas, besluit 31 aug): Cloudflare
      oppakken — de definitieve shop-URL wordt **shop.defibsolutions.nl**
      (subdomein; hoofdsite blijft op het domein-root). Pas relevant als
      alles op de bouwlocatie akkoord is; bewust nog nergens in code of
      configs verwerkt.

---

## Later — verzameld 26 aug (niet vergeten)

- [x] Klanten zonder AFAS-koppeling langslopen (users zonder `afas_relatie_id`)
      ✓ 27 aug: lijst in work/klanten-zonder-afas-id.csv — 8 interne accounts
      verwijderd via stap14 (reassign naar info@defibsolutions.nl), 85
      slapende 0-order-klanten bewust ongekoppeld, 6 admins legitiem.
      Restpunt: 3 klanten mét orders zonder koppeling (Boomgaard, VDP
      Medical, PSD Opleidingen) — koppel-voorstel volgt.
- [x] Kevins 87-lijst verwerkt (27 aug): 71 aan op DefibSolutions, 17 uit op
      reseller (16 van Kevin + Zoll Trainer CZ, ook uit op DefibSolutions —
      product netjes private). 70 nieuwe producten via stap13 aangekleed
      vanaf reseller; alleen Rotaid Outdoor transformer (95.3010) zonder
      afbeelding want reseller heeft er ook geen. Restpunt hier: foto.
- [ ] Nieuwe producten opmaken (via de sync binnengekomen producten missen
      Divi-opmaak/afbeeldingen/teksten)
- [x] Product-AED-layout frontend: attributen-picker zoals reseller
      ✓ 27 aug: swatches-config exact reseller-conform in stap9 (knoppen,
      squared), mu-plugin defibs-product-restyle; bijvangst: stap12
      default-kiezer-fix (Mindray Frans-eerst) + 6 container-titels zonder
      taal-/CPR-aanduiding via titel-map in stap12.
- [x] Restcheck ongekoppelde producten ✓ 27 aug: alleen Offer/Credit (bewust
      lokaal) + 3 dode pagina's geschrapt; stap10-lookup-bug gepatcht en
      68 oude SKU-claims opgeruimd.

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
