# ARKY-migratie runbook

Stappenplan voor de migratie van de ARKY-shop (`arkycase.defibrion.dev`, CloudPanel).
Draai de stappen in volgorde. Commando's met `wp` draaien op de server; commando's met
`php bin/samenstellingen` of het migratiescript draaien lokaal vanuit deze repo.

Voorwaarde: er staat een verse kopie van de productie-shop op de dev-omgeving.

---

## Stap 1 — Mail UIT (WordPress, server)

Voorkomt dat klanten welkomst-/account-mails krijgen tijdens het syncen/omzetten.

```bash
wp plugin activate disable-emails
```

## Stap 2 — Wholesale-plugins UIT (server)

Eerst de niet-premium, dan de premium:

```bash
wp plugin deactivate woocommerce-wholesale-prices
wp plugin deactivate woocommerce-wholesale-prices-premium
```

## Stap 3 — Migratiescript draaien (lokaal)

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

## Stap 4 — Klanten omzetten: afas_id → afas_klant (server)

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
' --skip-themes --skip-plugins
```

Verifiëren:
```bash
wp user list --role=afas_klant --format=count --skip-themes --skip-plugins
```

## Stap 5 — AFAS→Woo sync draaien (plugin, server)

Herbouwt de variations (o.a. de families waarvan we de legacy-containers verwijderden)
onder de juiste parent, en zet status/naam/prijs vanuit AFAS. Draai via de plugin-pagina
of de scheduler. Controleer daarna de sync-logs op resterende warnings.

## Stap 6 — Mail weer AAN (WordPress, server)

Vergeet dit niet, anders verstuurt de shop geen enkele mail meer.

```bash
wp plugin deactivate disable-emails
```

## Stap 7 — Snapshot verversen ter controle (lokaal, laatste stap)

Haal de eindstaat op zodat de lokale snapshot klopt en je kunt verifiëren. Vereist de
REST-keys in de DB (appendix A).

```bash
php bin/samenstellingen wc:pull --store=arkycase.defibrion.dev
```

---

# Appendix A — ARKY REST-keys (verse kopie)

```
ck_4db513b210aa32ce1a4a100ecc8aa1e4a033acc0
cs_9d66418dd5d45b3d3991e3a57d5a8e47d59f3dce
```

In lokale DB zetten (voor `wc:pull`):
```bash
sqlite3 tmp/samenstellingen.sqlite "UPDATE woocommerce_stores SET consumer_key='ck_...', consumer_secret='cs_...' WHERE name='arkycase.defibrion.dev';"
```
Het migratiescript leest de keys uit env (`ARKY_CK` / `ARKY_CS`).

# Appendix B — AFAS-plugin settings export/import

Alleen nodig wanneer de doel-shop de plugin-config mist. Alle config zit in
`wp_options` met prefix `afas_` (connectors, credentials, sync-intervallen, mappings,
filters, order-config), inclusief de dynamische sleutels `afas_connector_{slug}`,
`afas_mapping_{slug}`, `afas_custom_fields_{slug}`. De opgehaalde AFAS-*data*
(`tNEXYW_lef_afas_*`-tabellen) gaat NIET mee — die herbouwt zich via de syncs.

Exporteren (bron-shop):
```bash
mkdir -p /home/defibrion-arkycase/afas-settings; wp option list --search='afas_*' --field=option_name --skip-themes --skip-plugins | while read -r opt; do wp option get "$opt" --format=json --skip-themes --skip-plugins > "/home/defibrion-arkycase/afas-settings/$opt.json"; done
```

Importeren (doel-shop):
```bash
for f in /home/defibrion-arkycase/afas-settings/*.json; do opt="$(basename "$f" .json)"; wp option update "$opt" "$(cat "$f")" --format=json --skip-themes --skip-plugins; done
wp plugin activate lefcreative-afas-b2b
```

Let op: `afas_app_token.json` bevat de AFAS-token — map veilig bewaren en opruimen na import.
Open na import één keer de schedule-pagina (of de-/heractiveer de plugin) zodat de cron-jobs
opnieuw ingepland worden.
