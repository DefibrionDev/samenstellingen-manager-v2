# ARKY-migratie runbook

Stappenplan voor de migratie van de ARKY-shop (`partner.arkycase.eu`, CloudPanel).
Draai de stappen in volgorde. Commando's met `wp` draaien op de server; commando's met
`php bin/samenstellingen` of het migratiescript draaien lokaal vanuit deze repo.

Voorwaarde: er staat een verse kopie van de productie-shop op de dev-omgeving.

---

## Stap 1 — Mail UIT (WordPress, server)

Voorkomt dat klanten welkomst-/account-mails krijgen tijdens het syncen/omzetten.

```bash
wp plugin activate disable-emails
```

## Stap 2 — Storende plugins UIT (server)

Zet de plugins uit die tijdens de migratie/sync zouden interfereren: de wholesale-plugins
(eerst niet-premium, dan premium) en de automatische order-status-controller — die kan
anders ongewenst order-statussen wijzigen tijdens de AFAS→Woo-sync.

```bash
wp plugin deactivate woocommerce-wholesale-prices
wp plugin deactivate woocommerce-wholesale-prices-premium
wp plugin deactivate order-status-rules-for-woocommerce
```

## Stap 3 — AFAS-plugin aanzetten + settings restoren (server)

Importeer de plugin-config (connectors, credentials, mappings, filters) en activeer
de plugin, zodat de AFAS→Woo sync in stap 7 met de juiste instellingen draait. De
settings-map staat in appendix B; details daar.

```bash
for f in /home/defibrion-arkycase/afas-settings/*.json; do opt="$(basename "$f" .json)"; wp option update "$opt" "$(cat "$f")" --format=json; done
wp plugin activate lefcreative-afas-b2b
```

Controleren dat kerninstellingen staan:
```bash
wp option get afas_base_url
wp option get afas_connector_artikelen
```

## Stap 4 — Engelse UI-overrides plaatsen (server)

De plugin-front-end staat deels in het Nederlands; de Engelse vertalingen liggen klaar in
`migration/afas-translations/` (deze repo). Kopieer ze naar de ARKY-server (`<WP-root>` = de
webroot van de ARKY-site).

**1. PHP-strings (adres-dropdown, adresboek, checkout)** — `.mo`-bestanden, buiten de plugin →
update-veilig. Plaats het bestand dat bij de site-taal hoort (English (US) → `en_US`,
English (UK) → `en_GB`; bij twijfel beide):
```bash
cp migration/afas-translations/lefcreative-afas-b2b-en_US.mo <WP-root>/wp-content/languages/plugins/
cp migration/afas-translations/lefcreative-afas-b2b-en_GB.mo <WP-root>/wp-content/languages/plugins/
```

**2. Staffelprijzen-tabel (JS)** — `Aantal`/`Prijs p/stuk`/`stuks` zitten hardcoded in de plugin-JS,
niet via vertaalfuncties. **Tijdelijke** override tot dev vertaalbare strings
(`wp_set_script_translations`) levert; **opnieuw plaatsen na elke plugin-update**:
```bash
cp migration/afas-translations/afas-product-pricing.js \
   <WP-root>/wp-content/plugins/lefcreative-afas-b2b/plugin/assets/afas-product-pricing.js
```

Voorwaarden: site-taal = Engels (WP → Settings → General). Daarna cache legen:
`wp cache flush` (+ evt. page-cache-plugin) en hard-refresh.

## Stap 5 — Migratiescript draaien (lokaal)

Verwijdert de te-verwijderen producten, converteert de losse variable→simple gevallen,
en herstelt sku + AFAS-meta uit `migration/wc-sku-meta-map.csv`.

Het script is self-contained: het werkt op hardcoded WC-id's + het gecommitte bestand
`migration/wc-sku-meta-map.csv`. Het leest NIET uit de lokale snapshot, dus een `wc:pull`
vooraf is niet nodig. Regenereer de mapping NIET uit een verse kopie — de gecommitte
versie bevat onze cleanup (parent-links, `-wpbase`-sku's) die een verse kopie mist.

```bash
export ARKY_CK=ck_... ARKY_CS=cs_...     # keys van de verse kopie (appendix A)
bash migration/arky-shop-migration.sh
```

Duur (gemeten run 2026-06-23, ~325 deletes + 2036 restore-PUT's):
- fase 1 product/parent-deletes (~151 calls): ~2½ min
- fase 2 variation-deletes + conversies (~176 calls): ~2 min
- restore sku+meta (2036 PUT's): ~22 min  ← veruit de langste
- **totaal ~27 min.** De restore logt voortgang per 50 producten; klaar bij de
  eindregel `restore: ok=… fail=…`. Een hoog fail-aantal is grotendeels verwacht
  (verwijderde producten + cascade-children staan nog in de mapping → 404).

## Stap 6 — Klanten omzetten: afas_id → afas_klant (server)

ARKY-klanten dragen meta `afas_id` + rol `role_00X`; de plugin koppelt op
`afas_relatie_id` + rol `afas_klant`. Zet per klant met gevulde `afas_id`:
`afas_relatie_id = afas_id` en rol → `afas_klant`. Administrators worden overgeslagen.

```bash
wp eval '
$users = get_users(["meta_key"=>"afas_id","meta_compare"=>"EXISTS","fields"=>["ID"]]);
$done=0; $skip=0;
foreach($users as $u){
  $uid=$u->ID;
  $aid=get_user_meta($uid,"afas_id",true);
  if($aid==="" || $aid===null){ continue; }
  $user=new WP_User($uid);
  if(in_array("administrator",$user->roles,true)){ echo "SKIP $uid (admin)\n"; $skip++; continue; }
  update_user_meta($uid,"afas_relatie_id",$aid);
  $user->set_role("afas_klant");
  $done++;
}
echo "klaar: omgezet=$done overgeslagen=$skip\n";
'
```

Verifiëren:
```bash
wp user list --role=afas_klant --format=count
```

## Stap 7 — AFAS→Woo sync draaien (plugin, server)

Herbouwt de variations (o.a. de families waarvan we de legacy-containers verwijderden)
onder de juiste parent, en zet status/naam/prijs vanuit AFAS. Draai via de plugin-pagina
of de scheduler. Controleer daarna de sync-logs op resterende warnings.

## Stap 8 — Mail + order-status-controller weer AAN (WordPress, server)

Vergeet dit niet — anders verstuurt de shop geen enkele mail meer en blijft de
automatische order-status-controller uit. (De wholesale-plugins blijven bewust uit.)

```bash
wp plugin deactivate disable-emails
```

## Stap 9 — Snapshot verversen (lokaal)

Haal de eindstaat op zodat de lokale snapshot klopt en je kunt verifiëren. Vereist de
REST-keys in de DB (appendix A). Nodig vóór stap 10 (die leest de ARKY-parents uit de snapshot).

```bash
php bin/samenstellingen wc:pull --store=partner.arkycase.eu
```

## Stap 10 — AED-variaties herstructureren op ARKY (lokaal, laatste stap)

Zet alle variabele AED-parents op ARKY naar het juiste attribuut-model — vast `Brand` +
variatie-assen `Language` / `Connectivity` / `Options` (Engelstalig) — en vult elke variatie
uit de tool-data (gekoppeld via de `_afas_artikelnummer`-meta). Spiegelt het reseller-873-model.

Voorwaarden: `afas:pull` is gedraaid (matched variants + `naam_kort_en`) én stap 9 (`wc:pull`,
zodat de snapshot de ARKY variable-parents kent en de variaties live bestaan).

Eerst dry-run — controleer dat overal `niet-mapbaar = 0` staat:
```bash
python3 migration/arky-aed-restructure.py --all
```
Dan toepassen:
```bash
python3 migration/arky-aed-restructure.py --apply --all
```

Gedrag: dry-run is default, `--apply` muteert. Een as wordt alléén een variatie-attribuut bij
>1 waarde (bv. enkel Connectivity `None` → vast attribuut). Default-variatie = `English / None /
Defibrillator`. Brand-overrides voor Heartsine + CU Medical zitten in het script. De 9 groepen
zonder ARKY variable-parent (Cardiac Science, Defibtech, Lifepak) vallen buiten scope tot hun
WC-type is rechtgezet.

---

# Appendix A — ARKY REST-keys (verse kopie)

```
ck_4db513b210aa32ce1a4a100ecc8aa1e4a033acc0
cs_9d66418dd5d45b3d3991e3a57d5a8e47d59f3dce
```

In lokale DB zetten (voor `wc:pull`):
```bash
sqlite3 tmp/samenstellingen.sqlite "UPDATE woocommerce_stores SET consumer_key='ck_...', consumer_secret='cs_...' WHERE name='partner.arkycase.eu';"
```
Het migratiescript leest de keys uit env (`ARKY_CK` / `ARKY_CS`).

# Appendix B — AFAS-plugin settings (import = stap 3)

De plugin-config zit in `wp_options` met prefix `afas_` (connectors, credentials,
sync-intervallen, mappings, filters, order-config), inclusief de dynamische sleutels
`afas_connector_{slug}`, `afas_mapping_{slug}`, `afas_custom_fields_{slug}`. De opgehaalde
AFAS-*data* (`tNEXYW_lef_afas_*`-tabellen) gaat NIET mee — die herbouwt zich via de syncs.

De settings-map `/home/defibrion-arkycase/afas-settings/` bevat één JSON per optie.
Importeren + plugin activeren = stap 3. Een verse export (bron-shop) maak je zo:
```bash
mkdir -p /home/defibrion-arkycase/afas-settings; wp option list --search='afas_*' --field=option_name | while read -r opt; do wp option get "$opt" --format=json > "/home/defibrion-arkycase/afas-settings/$opt.json"; done
```

Let op: `afas_app_token.json` bevat de AFAS-token — map veilig bewaren en opruimen na import.
Open na de import (stap 3) één keer de schedule-pagina (of de-/heractiveer de plugin) zodat
de cron-jobs opnieuw ingepland worden.
