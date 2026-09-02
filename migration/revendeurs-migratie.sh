#!/usr/bin/env bash
#
# Migratiescript revendeurs.defibrion.fr (Wholesale Suite → lefcreative-afas-b2b).
# De site stáát al op cp-01; dit script verhuist de AFAS-koppeling, niet de hosting.
# Wordt stap voor stap opgebouwd; elke stap is een aparte functie en wordt expliciet
# per naam aangeroepen — geen "alles in één keer".
#
#   ./migration/revendeurs-migratie.sh stap1
#
# Target-keuze (lokaal-eerst, zie MIGRATIE-REVENDEURS.md):
#   REVEND_TARGET=lokaal  (default) draait elke stap in de wpcli-container van
#                         de lokale Docker-kopie (~/projects/wordpress-migrater,
#                         .env-revendeurs, site op poort 8894)
#   REVEND_TARGET=cp01    draait exact dezelfde stap via ssh op cp-01
#
# Serverconfig komt uit de project-.env (repo-root):
#   REVEND_SERVER        ssh-host (defibrion-revendeurs-fr@cp-01; key nog regelen)
#   REVEND_WP_ROOT       WordPress-root op cp-01 (vermoedelijk
#                        /home/defibrion-revendeurs-fr/htdocs/revendeurs.defibrion.fr)
#   REVEND_MIGRATER_DIR  pad naar wordpress-migrater (default ~/projects/wordpress-migrater)
# Environment-variabelen met dezelfde naam gaan vóór de .env-waarden.
#
# Fase-overzicht: MIGRATIE-REVENDEURS.md · scriptvorm-blauwdruk: defibsolutions-migratie.sh
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# .env inlezen (KEY=VALUE-regels; quotes eromheen mogen; env-vars houden voorrang)
if [[ -f "$REPO_ROOT/.env" ]]; then
    while IFS='=' read -r k v; do
        [[ "$k" =~ ^[A-Z_]+$ ]] || continue
        v="${v%\"}"; v="${v#\"}"; v="${v%\'}"; v="${v#\'}"
        [[ -z "${!k:-}" && -n "$v" ]] && export "$k=$v"
    done < "$REPO_ROOT/.env"
fi

TARGET="${REVEND_TARGET:-lokaal}"
SERVER="${REVEND_SERVER:-INVULLEN-defibrion-revendeurs-fr@cp-01}"
WP_ROOT="${REVEND_WP_ROOT:-INVULLEN-/home/defibrion-revendeurs-fr/htdocs/revendeurs.defibrion.fr}"
MIGRATER_DIR="${REVEND_MIGRATER_DIR:-$HOME/projects/wordpress-migrater}"

# Oude plugins/themes op nieuwere PHP: "Deprecated:"-ruis wegfilteren.
# --line-buffered: anders zie je voortgang van lange stappen pas aan het eind.
_filter_ruis() { grep --line-buffered -vE '^(Deprecated|Notice):' || true; }

_lokaal_compose() {
    docker compose --progress quiet --project-directory "$MIGRATER_DIR" \
        --env-file "$MIGRATER_DIR/.env-revendeurs" "$@"
}

doel_naam() {
    [[ "$TARGET" == "lokaal" ]] && echo "lokale kopie (localhost:8894)" || echo "$SERVER"
}

# wp-cli op het gekozen target. Args gaan als één string door een shell
# (sh -c / ssh), zodat quoting op beide targets identiek uitpakt.
# wpr leest NIET van stdin (veilig in loops); wpr_stdin wél (voor eval-file -).
wpr() {
    wpr_stdin "$@" < /dev/null
}

# wpcli-image (Alpine) draait als uid 82, wp-content is van uid 33 (fpm/Debian):
# schrijvende wp-commando's als 33 draaien. HOME=/tmp voor de wp-cli-cache.
_LOKAAL_RUN_OPTS=(--rm -T --user 33:33 -e HOME=/tmp)

wpr_stdin() {
    if [[ "$TARGET" == "lokaal" ]]; then
        # memory_limit: 128M van de container is te krap zodra WP volledig
        # laadt (wp-staging-pro alleen al OOM't); syncs hebben ruim nodig.
        _lokaal_compose run "${_LOKAAL_RUN_OPTS[@]}" wpcli \
            sh -c "php -d memory_limit=1024M -d max_execution_time=0 /usr/local/bin/wp $*" 2>&1 | _filter_ruis
    else
        ssh "$SERVER" "cd '$WP_ROOT' && wp $*" 2>&1 | _filter_ruis
    fi
}

_lokaal_prep() {
    # Na een verse pull zijn schrijfmappen van de host-user (1000); uid 33
    # (php-fpm) moet erin kunnen schrijven. Kadence heeft geen et-cache-map
    # nodig (dat was Divi bij defibsolutions). Idempotent.
    _lokaal_compose run --rm -T --user 0 wpcli sh -c \
        'cd /var/www/html/wp-content && mkdir -p upgrade uploads/wc-logs \
         && chown -R 33:33 upgrade uploads/wc-logs' \
        >/dev/null 2>&1 || true
}

controleer_config() {
    if [[ "$TARGET" == "lokaal" ]]; then
        if [[ ! -f "$MIGRATER_DIR/.env-revendeurs" ]]; then
            echo "FOUT: $MIGRATER_DIR/.env-revendeurs ontbreekt (zet evt. REVEND_MIGRATER_DIR)." >&2
            exit 1
        fi
        if ! _lokaal_compose ps --status=running 2>/dev/null | grep -q 'revendeurs-db'; then
            echo "FOUT: lokale revendeurs-stack draait niet. Start met:" >&2
            echo "  cd $MIGRATER_DIR && docker compose --env-file .env-revendeurs up -d" >&2
            exit 1
        fi
        _lokaal_prep
    elif [[ "$TARGET" == "cp01" ]]; then
        if [[ "$SERVER" == INVULLEN-* || "$WP_ROOT" == INVULLEN-* ]]; then
            echo "FOUT: zet eerst REVEND_SERVER en REVEND_WP_ROOT (zie kop van dit script)." >&2
            exit 1
        fi
        # Vangrail (incident 2 sep: dev-wp-config wees naar de LIVE-database
        # doordat de migrater-configstap wegviel — de reeks liep toen op live).
        # Zet REVEND_DB_NAME om af te dwingen dat de site op de verwachte
        # database draait; zonder match stopt elke stap.
        if [[ -n "${REVEND_DB_NAME:-}" ]]; then
            local echte_db
            echte_db=$(ssh "$SERVER" "cd '$WP_ROOT' && wp config get DB_NAME" 2>/dev/null | tr -d '[:space:]')
            if [[ "$echte_db" != "$REVEND_DB_NAME" ]]; then
                echo "FOUT: wp-config op $SERVER gebruikt database '$echte_db', verwacht '$REVEND_DB_NAME' — gestopt." >&2
                exit 1
            fi
            echo "[db-vangrail: $echte_db ✓]"
        fi
    else
        echo "FOUT: onbekend REVEND_TARGET '$TARGET' (lokaal of cp01)." >&2
        exit 1
    fi
    echo "[target: $TARGET]"
}

# ---------------------------------------------------------------------------
# Stap 1 — Mail UIT.
# Voorkomt klant-mails tijdens inrichten en syncen. De lokale kopie heeft al
# een zz-disable-emails-local-mu-plugin van de migrater, maar deze stap moet
# straks óók op cp-01 werken — daarom de reguliere disable-emails-plugin.
# Weer aanzetten is de allerlaatste stap van de omschakeling.
# ---------------------------------------------------------------------------
stap1() {
    controleer_config
    wpr plugin install disable-emails --activate
    echo "--- controle:"
    wpr plugin list --status=active | grep disable-emails
    echo "OK — mail staat uit op $(doel_naam)"
}

# ---------------------------------------------------------------------------
# Stap 2 — Overbodige/gevaarlijke plugins UIT.
# - wp-staging(-pro): geheugenvreter (OOM-les defibsolutions), nutteloos op
#   de kopie én op cp-01 (CloudPanel doet backups).
# - litespeed-cache: cp-01 draait nginx en lokaal draait nginx — de LiteSpeed-
#   server ontbreekt op beide targets; page-cache-restanten maskeren wijzigingen.
# - jetpack: staat op live (wp-json toont jetpack/v4) maar niet op de kopie —
#   guard blijft staan zodat de stap op cp-01 hetzelfde doet.
# - wp-slimstat(-pro): staat al inactief; guard voor de zekerheid.
# Wholesale Suite gaat hier bewust NIET uit — dat is het omschakelmoment en
# krijgt een eigen stap ná plugin + settings (zie MIGRATIE-REVENDEURS.md).
# ---------------------------------------------------------------------------
stap2() {
    controleer_config
    local p
    for p in wp-staging-pro wp-staging litespeed-cache jetpack wp-slimstat-pro wp-slimstat; do
        if wpr plugin is-installed "$p" >/dev/null 2>&1; then
            if wpr plugin is-active "$p" >/dev/null 2>&1; then
                wpr plugin deactivate "$p"
            else
                echo "$p is al inactief op $(doel_naam) — overslaan"
            fi
        else
            echo "$p is niet geïnstalleerd op $(doel_naam) — overslaan"
        fi
    done
    # wp-staging's deactivatie-hook probeert zijn mu-plugin op te ruimen maar
    # mag dat op de kopie niet (bestand is van de host-user) — zelf opruimen,
    # anders blijft wp-staging-optimizer als must-use meeladen.
    if [[ "$TARGET" == "lokaal" ]]; then
        _lokaal_compose run --rm -T --user 0 wpcli \
            sh -c 'rm -f /var/www/html/wp-content/mu-plugins/wp-staging-optimizer.php' \
            >/dev/null 2>&1 || true
    else
        ssh "$SERVER" "rm -f '$WP_ROOT/wp-content/mu-plugins/wp-staging-optimizer.php'"
    fi
    echo "--- controle:"
    wpr plugin list | { grep -iE 'wp-staging|litespeed|jetpack|slimstat' || echo "(niets gevonden)"; }
    echo "OK — staging/cache/tracking-plugins staan uit op $(doel_naam)"
}

# ---------------------------------------------------------------------------
# Stap 3 — Klanten koppelen aan AFAS-verkooprelaties (usermeta afas_relatie_id,
# het veld waar lefcreative-afas-b2b op draait).
# Bron: work/revendeurs-klant-relatie-mapping.csv (wc_user_id;afas_relatie_id),
# gegenereerd door work/audit-klantmapping-revendeurs.py. De mapping komt uit
# twee sporen: users met rol `role_<debiteurnummer>` (Wholesale-Suite-rol per
# klant — nummer is geverifieerd het AFAS-debiteurnummer) en unieke
# e-mail-matches tegen Get_Verkooprelaties. REVIEW-gevallen staan bewust NIET
# in de CSV; die beslist Cas via work/revendeurs-klantmapping-review.csv.
# Default dry-run (toont ook het e-mailadres van de user ter verificatie);
# `stap3 apply` schrijft echt.
# ---------------------------------------------------------------------------
stap3() {
    controleer_config
    local mapping="$REPO_ROOT/work/revendeurs-klant-relatie-mapping.csv"
    local apply="${1:-}"
    [[ -f "$mapping" ]] || { echo "FOUT: $mapping ontbreekt (genereer met work/audit-klantmapping-revendeurs.py)" >&2; exit 1; }
    mkdir -p "$REPO_ROOT/tmp"

    python3 - "$mapping" "$apply" <<'PY' > "$REPO_ROOT/tmp/revendeurs-relatie-payload.php"
import csv, json, sys
mapping, apply = sys.argv[1], sys.argv[2] == "apply"
paren = {}
with open(mapping, encoding="utf-8-sig") as f:
    for r in csv.DictReader(f, delimiter=";"):
        uid, rel = r["wc_user_id"].strip(), r["afas_relatie_id"].strip()
        if uid.isdigit() and rel:
            paren[uid] = rel
print(f"// {len(paren)} koppelingen uit {mapping}", file=sys.stderr)
print("<?php")
print(f"$apply = {'true' if apply else 'false'};")
print(f"$map = json_decode('{json.dumps(paren)}', true);")
print("""
$gezet = $al = $onbekend = 0;
foreach ($map as $uid => $relatie) {
    $user = get_user_by('id', (int) $uid);
    if (!$user) { echo "ONBEKENDE USER  wc:$uid (relatie $relatie)\n"; $onbekend++; continue; }
    $huidig = (string) get_user_meta($user->ID, 'afas_relatie_id', true);
    if ($huidig === (string) $relatie) { $al++; continue; }
    if ($apply) { update_user_meta($user->ID, 'afas_relatie_id', (string) $relatie); }
    printf("%s  wc:%d %s: %s -> %s\n", $apply ? 'GEZET' : 'ZOU ZETTEN',
        $user->ID, $user->user_email, $huidig !== '' ? $huidig : '-', $relatie);
    $gezet++;
}
printf("--- %s: %d te zetten/gezet, %d stonden al goed, %d onbekende users\n",
    $apply ? 'APPLY' : 'DRY-RUN', $gezet, $al, $onbekend);
""")
PY

    wpr_stdin eval-file - < "$REPO_ROOT/tmp/revendeurs-relatie-payload.php"
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — niets geschreven. Draai '$0 stap3 apply' om echt te schrijven."
    fi
}

# ---------------------------------------------------------------------------
# Stap 4 — lefcreative-afas-b2b installeren + activeren + AFAS-settings.
# Bronnen (beide in work/, gitignored):
#   - work/lefcreative-afas-b2b-<versie>.zip  (nieuwste wint; nu 2.0.4)
#   - work/afas-settings-revendeurs.json      (gegenereerd door
#     work/maak-afas-settings-revendeurs.py uit de reseller-dump — bevat het
#     app-token, daarom bewust buiten git)
# --force + update_option maken her-runnen idempotent: de stap zet de shop
# altijd terug naar exact deze plugin-versie + settings-set.
# ---------------------------------------------------------------------------
stap4() {
    controleer_config
    local zip
    zip=$(ls -1 "$REPO_ROOT"/work/lefcreative-afas-b2b-*.zip 2>/dev/null | sort | tail -1)
    [[ -n "$zip" ]] || { echo "FOUT: geen work/lefcreative-afas-b2b-*.zip gevonden" >&2; exit 1; }
    local settings="$REPO_ROOT/work/afas-settings-revendeurs.json"
    [[ -f "$settings" ]] || { echo "FOUT: $settings ontbreekt (genereer met work/maak-afas-settings-revendeurs.py)" >&2; exit 1; }
    mkdir -p "$REPO_ROOT/tmp"

    echo "plugin: $(basename "$zip")"
    if [[ "$TARGET" == "lokaal" ]]; then
        # geen scp nodig: work/ read-only in de container mounten
        _lokaal_compose run "${_LOKAAL_RUN_OPTS[@]}" -v "$(dirname "$zip"):/revend-work:ro" wpcli \
            sh -c "php -d memory_limit=512M /usr/local/bin/wp plugin install '/revend-work/$(basename "$zip")' --force --activate" 2>&1 | _filter_ruis
    else
        echo "upload $(basename "$zip") ..."
        # naar de home van de site-user, niet /tmp: daar ligt al een zip van
        # een andere site-user (sticky bit -> Permission denied, gezien 2 sep)
        scp -q "$zip" "$SERVER:lefcreative-afas-b2b.zip"
        wpr plugin install "\$HOME/lefcreative-afas-b2b.zip" --force --activate
    fi

    python3 - "$settings" <<'PY' > "$REPO_ROOT/tmp/revendeurs-settings-payload.php"
import json, sys
d = json.load(open(sys.argv[1]))
veilig = json.dumps(d).replace("\\", "\\\\").replace("'", "\\'")
print("<?php")
print(f"$settings = json_decode('{veilig}', true);")
print("""
$n = 0;
foreach ($settings as $naam => $waarde) { update_option($naam, $waarde); $n++; }
printf("%d afas_*-opties geimporteerd\n", $n);
""")
PY
    wpr_stdin eval-file - < "$REPO_ROOT/tmp/revendeurs-settings-payload.php"

    if [[ "$TARGET" == "lokaal" ]]; then
        # Veiligheidsgordel: op de lokale kopie mag order-push naar AFAS
        # nooit aan staan, ook niet als de settings-bron hem (voor live) aanzet.
        wpr option update afas_sync_orders_enabled 0 >/dev/null
        echo "(lokaal: afas_sync_orders_enabled geforceerd op 0)"
        # Testklant voor checkout-tests: user 26 (Groupe France Protect,
        # relatie 13054, 170 orders). Wachtwoord komt niet mee uit een
        # live-dump, dus na elke verse pull opnieuw — alleen lokaal.
        if wpr user get 26 --field=ID >/dev/null 2>&1; then
            wpr user update 26 --user_pass=revend-test-2026 >/dev/null
            echo "(lokaal: testklant Groupe France Protect/26 wachtwoord gezet: revend-test-2026)"
        fi
    fi
    echo "--- controle:"
    wpr plugin list | grep -i lefcreative
    wpr option get afas_sync_artikelen_filterfieldids
    echo "OK — plugin actief + settings geimporteerd op $(doel_naam)"
}

# ---------------------------------------------------------------------------
# Stap 5 — Alle API-keys van de shop intrekken.
# Na de omschakeling mag niets van buitenaf meer de shop in schrijven — de
# nieuwe plugin praat zelf uitgaand met AFAS. WooCommerce REST-keys en
# application passwords gaan weg; wie later toegang nodig heeft maakt bewust
# een nieuwe key aan. LET OP: de tabel-prefix is hier ubMIcBt_ (géén wp_),
# dus de prefix wordt dynamisch opgevraagd.
# Default dry-run (toont wat er staat); `stap5 apply` verwijdert echt.
# ---------------------------------------------------------------------------
stap5() {
    controleer_config
    local apply="${1:-}"
    local prefix
    prefix=$(wpr db prefix | tr -d '[:space:]')
    [[ -n "$prefix" ]] || { echo "FOUT: kon tabel-prefix niet bepalen" >&2; exit 1; }
    echo "tabel-prefix: $prefix"

    echo "--- WooCommerce REST API-keys:"
    wpr db query "\"SELECT key_id, user_id, description, permissions, truncated_key, last_access FROM ${prefix}woocommerce_api_keys\""

    echo ""
    echo "--- Application passwords:"
    local userids
    # 'a:0:{}' = lege rij die WP na verwijderen laat staan — geen wachtwoord
    userids=$(wpr db query "\"SELECT user_id FROM ${prefix}usermeta WHERE meta_key='_application_passwords' AND meta_value NOT IN ('', 'a:0:{}')\"" --skip-column-names)
    for uid in $userids; do
        echo "user $uid:"
        wpr user application-password list "$uid" --fields=uuid,name,created,last_used
    done

    if [[ "$apply" != "apply" ]]; then
        echo ""
        echo "Dry-run — niets ingetrokken. Draai '$0 stap5 apply' om alle keys hierboven te verwijderen."
        return 0
    fi

    echo ""
    echo "--- intrekken:"
    wpr db query "\"DELETE FROM ${prefix}woocommerce_api_keys\""
    for uid in $userids; do
        wpr user application-password delete "$uid" --all
    done

    echo "--- controle:"
    wpr db query "\"SELECT COUNT(*) AS rest_keys FROM ${prefix}woocommerce_api_keys\""
    wpr db query "\"SELECT COUNT(*) AS app_passwords FROM ${prefix}usermeta WHERE meta_key='_application_passwords' AND meta_value NOT IN ('', 'a:0:{}')\""
    echo "OK — alle API-keys ingetrokken op $(doel_naam)"
}

# ---------------------------------------------------------------------------
# Stap 6 — Voorkoppeling: per gematcht WC-product de AFAS-itemcode in postmeta
# _afas_artikelnummer zetten. Zonder deze stap kan de artikelen-sync bestaande
# producten niet vinden en maakt hij duplicaten aan.
#
# BELANGRIJK (plugin 2.0.4, AfasArtikelLookup): de plugin "self-healt" — bij de
# eerste lees van een product zónder meta kopieert hij de SKU naar de meta.
# Voor de 325 producten met een BHV-SKU zou dat de verkeerde waarde vastzetten.
# Deze stap moet dus (a) zo vroeg mogelijk ná plugin-activatie draaien en
# (b) een afwijkende bestaande meta durven overschrijven (wordt gerapporteerd).
#
# Bron: work/revendeurs-koppelbaarheid.csv (audit, fase 1.3) — alleen rijen met
# een eenduidige afas_itemcode en status != draft. Parent-containers doen nog
# niet mee (besluit: plugin bouwt nieuwe containers, opruimstap volgt).
# Default dry-run; `stap6 apply` schrijft echt.
# ---------------------------------------------------------------------------
stap6() {
    controleer_config
    local audit="$REPO_ROOT/work/revendeurs-koppelbaarheid.csv"
    local apply="${1:-}"
    [[ -f "$audit" ]] || { echo "FOUT: $audit ontbreekt (genereer met work/audit-koppelbaarheid-revendeurs.py)" >&2; exit 1; }
    mkdir -p "$REPO_ROOT/tmp"

    python3 - "$audit" "$apply" <<'PY' > "$REPO_ROOT/tmp/revendeurs-voorkoppel-payload.php"
import csv, json, sys
audit, apply = sys.argv[1], sys.argv[2] == "apply"
paren = {}
with open(audit, encoding="utf-8-sig") as f:
    for r in csv.DictReader(f, delimiter=";"):
        # KALE-AED-VARIATIE: zwerf-variatie aan een kaal artikel (11661,
        # 20013-FR, 20014-FR) — niet voorkoppelen, stap11 ruimt op.
        if (r["afas_itemcode"] and r["status"] != "draft"
                and r["type"] != "variable" and r["oordeel"] != "KALE-AED-VARIATIE"):
            paren[r["wc_id"]] = r["afas_itemcode"]
# Uitzonderingen (besluiten Cas) overrulen de audit — bv. wc:991865 -> 10227,
# waar de shop-sku zelf onvindbaar is maar het product 1-op-1 bestaat.
import os
uitz = os.path.join(os.path.dirname(audit), "revendeurs-voorkoppel-uitzonderingen.csv")
if os.path.exists(uitz):
    for r in csv.DictReader(open(uitz, encoding="utf-8-sig"), delimiter=";"):
        if r.get("wc_id") and r.get("itemcode"):
            paren[r["wc_id"].strip()] = r["itemcode"].strip()
print(f"// {len(paren)} voorkoppelingen uit {audit}", file=sys.stderr)
print("<?php")
print(f"$apply = {'true' if apply else 'false'};")
print(f"$map = json_decode('{json.dumps(paren)}', true);")
print("""
$gezet = $al = $overschreven = $onbekend = 0;
foreach ($map as $pid => $code) {
    $pid = (int) $pid;
    $status = get_post_status($pid);
    if (!$status) { echo "ONBEKEND PRODUCT wc:$pid ($code)\n"; $onbekend++; continue; }
    if ($status === 'trash') { continue; } // opgeruimd (stap11/16): nooit meta terugzetten
    $huidig = (string) get_post_meta($pid, '_afas_artikelnummer', true);
    if ($huidig === (string) $code) { $al++; continue; }
    if ($apply) { update_post_meta($pid, '_afas_artikelnummer', (string) $code); }
    if ($huidig !== '') {
        printf("%s wc:%d: OVERSCHRIJF %s -> %s (was self-heal/legacy)\n",
            $apply ? 'GEZET' : 'ZOU ZETTEN', $pid, $huidig, $code);
        $overschreven++;
    } else {
        $gezet++;
    }
}
printf("--- %s: %d nieuw, %d overschreven (afwijkende meta), %d stonden al goed, %d onbekend\n",
    $apply ? 'APPLY' : 'DRY-RUN', $gezet, $overschreven, $al, $onbekend);

// Schoonmaakpass: dode koppelingen strippen. De plugin self-healt SKU->meta
// op eerste lees; producten buiten de mapping (GEEN-MATCH/GEBLOKKEERD-
// audit-gevallen) krijgen zo een artikelnummer dat niet (actief) in AFAS
// bestaat -> order-push zou stukgaan. Meta weg, SKU en product blijven.
global $wpdb;
$dood = $wpdb->get_results("SELECT p.ID, an.meta_value AS art
    FROM {$wpdb->posts} p
    JOIN {$wpdb->postmeta} an ON an.post_id = p.ID AND an.meta_key = '_afas_artikelnummer' AND an.meta_value <> ''
    LEFT JOIN {$wpdb->prefix}lef_afas_artikelen la ON la.artikelnummer = an.meta_value
    WHERE p.post_type IN ('product','product_variation')
      AND p.post_status IN ('publish','draft','private')
      AND la.artikelnummer IS NULL", ARRAY_A);
$gestript = 0;
foreach ($dood as $d) {
    if (isset($map[(string) $d['ID']])) { continue; } // hoort in de mapping: niet strippen
    printf("%s dode koppeling wc:%s (meta=%s was self-heal)\n",
        $apply ? 'GESTRIPT' : 'ZOU STRIPPEN', $d['ID'], $d['art']);
    if ($apply) { delete_post_meta((int) $d['ID'], '_afas_artikelnummer'); }
    $gestript++;
}
printf("--- %s schoonmaak: %d dode koppelingen\n", $apply ? 'APPLY' : 'DRY-RUN', $gestript);
""")
PY

    wpr_stdin eval-file - < "$REPO_ROOT/tmp/revendeurs-voorkoppel-payload.php"
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — niets geschreven. Draai '$0 stap6 apply' om echt te schrijven."
    fi
}

# ---------------------------------------------------------------------------
# Stap 7 — mu-plugins plaatsen uit migration/mu-plugins/ (gedeelde map met
# defibsolutions — daarom een EXPLICIETE selectie, geen *.php):
#   in:  checkout-coupon-points-right (Kadence-checkout-opmaak, van reseller),
#        wc-variation-threshold, variations-json-cache, checkout-ajax-fallback,
#        afas-preview-winkelmanager, shop-manager-login-as-klant,
#        order-email-afas-debiteur, order-email-unit-prices,
#        afas-tracktrace-style, wcpt-cli-cache-fix (shop draait wc-product-
#        table-pro)
#   uit: defibs-checkout-restyle, defibs-product-restyle (defibsolutions-
#        opmaak), points-pro-variable-price-fix (revendeurs heeft een ander
#        points-systeem)
# Taal: afas-tracktrace-style rendert op niet-nl-shops Engels; een Franse
# vertaalkaart is een vervolg-actie (zie runbook). Idempotent; een verse pull
# haalt ze weer weg, dus deze stap hoort in elke herhaal-reeks.
# ---------------------------------------------------------------------------
stap7() {
    controleer_config
    local bron="$REPO_ROOT/migration/mu-plugins"
    [[ -d "$bron" ]] || { echo "FOUT: $bron ontbreekt" >&2; exit 1; }
    local selectie=(
        checkout-coupon-points-right.php
        wc-variation-threshold.php
        variations-json-cache.php
        checkout-ajax-fallback.php
        afas-preview-winkelmanager.php
        shop-manager-login-as-klant.php
        order-email-afas-debiteur.php
        order-email-unit-prices.php
        afas-tracktrace-style.php
        wcpt-cli-cache-fix.php
    )
    local f
    for f in "${selectie[@]}"; do
        [[ -f "$bron/$f" ]] || { echo "FOUT: $bron/$f ontbreekt" >&2; exit 1; }
    done

    if [[ "$TARGET" == "lokaal" ]]; then
        local content_dir
        content_dir=$(grep '^CONTENT_DIR=' "$MIGRATER_DIR/.env-revendeurs" | cut -d= -f2)
        local doel="$MIGRATER_DIR/${content_dir#./}/mu-plugins"
        mkdir -p "$doel"
        for f in "${selectie[@]}"; do cp "$bron/$f" "$doel/"; done
        echo "--- controle:"
        ls "$doel"
    else
        ssh "$SERVER" "mkdir -p '$WP_ROOT/wp-content/mu-plugins'"
        for f in "${selectie[@]}"; do
            scp -q "$bron/$f" "$SERVER:$WP_ROOT/wp-content/mu-plugins/"
        done
        echo "--- controle:"
        ssh "$SERVER" "ls '$WP_ROOT/wp-content/mu-plugins'"
    fi
    echo "OK — ${#selectie[@]} mu-plugins geplaatst op $(doel_naam)"
}

# ---------------------------------------------------------------------------
# Stap 8 — Wholesale Suite UIT: het omschakelmoment. Vanaf hier komen
# groothandelsprijzen uit AFAS (lefcreative-plugin, klantprijzen per debiteur)
# in plaats van uit Wholesale-Suite-groepen/staffels. Alleen deactiveren; de
# Wholesale-Suite-data blijft in de database als inerte fallback (zelfde
# aanpak als B2BKing bij defibsolutions). Volgorde: premium eerst.
# Draai hierna de prijs-sync en controleer testklant-prijzen (stap 1.5/1.6
# van het runbook) — zonder gevulde Sync_Revendeurs_FR-vlaggen in AFAS toont
# de shop tijdelijk reguliere prijzen.
# ---------------------------------------------------------------------------
stap8() {
    controleer_config
    local p
    for p in woocommerce-wholesale-prices-premium woocommerce-wholesale-prices; do
        if wpr plugin is-installed "$p" >/dev/null 2>&1; then
            if wpr plugin is-active "$p" >/dev/null 2>&1; then
                wpr plugin deactivate "$p"
            else
                echo "$p is al inactief op $(doel_naam) — overslaan"
            fi
        else
            echo "$p is niet geïnstalleerd op $(doel_naam) — overslaan"
        fi
    done
    echo "--- controle:"
    wpr plugin list | { grep -i wholesale || echo "(geen wholesale-plugins gevonden)"; }
    echo "OK — Wholesale Suite staat uit op $(doel_naam); prijzen komen nu uit AFAS"
}

# ---------------------------------------------------------------------------
# Stap 9 — Syncs draaien: plugin-migraties, artikelen (AFAS → tabel),
# prijslijsten + prijzen + verkooprelaties, en 2× de WooCommerce-sync (run 2
# is het vangnet voor variaties wier container pas in run 1 ontstond).
# Zonder gevulde Sync_Revendeurs_FR-vlaggen in AFAS synct dit 0 artikelen —
# de vlaggen komen uit apply-revendeurs-vlaggen.php (afas-connector-tools)
# ná Randy's curatie.
# Opties (combineerbaar):
#   zonder-prijzen  slaat prijs-/relatie-import over (snelle herhaal-runs)
#   delta           wc-sync zonder force: alleen gewijzigde artikelen
# ---------------------------------------------------------------------------
stap9() {
    controleer_config
    local opties="${*:-}"
    mkdir -p "$REPO_ROOT/tmp"
    local payload="$REPO_ROOT/tmp/revendeurs-stap9-payload.php"
    # alleen-relaties: snelle her-run van de verkooprelaties-sync, bv. nadat
    # accounts (stap17) of relatie-vlaggen zijn bijgewerkt — de sync schrijft
    # dan ook factuuradressen naar alle gekoppelde WP-accounts.
    if [[ "$opties" == *alleen-relaties* ]]; then
        wpr_stdin eval-file - <<'PHP'
<?php
\Lefcreative\PluginBase\Core\Hooks::adminInit();
do_action('afas_sync_verkooprelaties', true);
global $wpdb;
printf("relaties: %d
", (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lef_afas_verkooprelaties"));
PHP
        echo "OK — alleen verkooprelaties gesynct op $(doel_naam)"
        return 0
    fi
    cat > "$payload" <<'PHP'
<?php
$zonderPrijzen = PRIJZEN_PLACEHOLDER;
$force = FORCE_PLACEHOLDER;   // false = delta-sync (alleen gewijzigde rijen)
$t0 = microtime(true);
$fase = function (string $m) use ($t0) {
    printf("[%5.1fs] %s\n", microtime(true) - $t0, $m);
    flush();
    if (function_exists('wp_cache_flush_runtime')) { wp_cache_flush_runtime(); }
};
global $wpdb;
$tabel = $wpdb->prefix . 'lef_afas_artikelen';

$fase('plugin-migraties');
\Lefcreative\PluginBase\Core\Hooks::adminInit();

$fase('artikelen-sync (AFAS -> tabel)');
do_action('afas_sync_artikelen', true);
printf("         tabel: %d artikelen\n", (int) $wpdb->get_var("SELECT COUNT(*) FROM `$tabel`"));

if ($zonderPrijzen) {
    $fase('prijslijsten + prijzen + verkooprelaties OVERGESLAGEN (zonder-prijzen)');
} else {
    $fase('prijslijsten + prijzen');
    do_action('afas_sync_prijslijsten', true);
    do_action('afas_sync_prijzen', true);
    printf("         prijzen: %d regels\n",
        (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lef_afas_prijzen"));
    // verkooprelaties: nodig voor prijslijst-/kortingsgroep-resolutie per klant
    $fase('verkooprelaties + kortingen + landen + adressen');
    do_action('afas_sync_verkooprelaties', true);
    do_action('afas_sync_kortingen', true);
    do_action('afas_sync_landen', true);
    // Get_Addresses is de zwaarste connector (geen filter -> alle adressen)
    // en time-out't af en toe na 300s (AFAS-kant, bevestigd door Cas: "gewoon
    // nog een keer runnen"). Tot 3 pogingen; de delta-cursor maakt elke
    // poging korter. Blijft het falen: waarschuwen en doorgaan — niet fataal
    // voor producten/prijzen.
    for ($poging = 1; $poging <= 3; $poging++) {
        try {
            do_action('afas_sync_addresses', true);
            break;
        } catch (\Throwable $e) {
            printf("         adressen-sync poging %d mislukt: %s\n", $poging,
                substr($e->getMessage(), 0, 120));
            if ($poging === 3) {
                printf("         -> opgegeven na 3 pogingen; later opnieuw (delta), producten/prijzen gaan door\n");
            }
        }
    }
    printf("         relaties: %d, kortingen: %d, adressen: %d\n",
        (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lef_afas_verkooprelaties"),
        (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lef_afas_kortingen"),
        (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lef_afas_addresses"));
}

$wpdb->query("DELETE FROM {$wpdb->prefix}lef_logs WHERE channel = 'woocommerce'");
$warnings = function () use ($wpdb): int {
    return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lef_logs
        WHERE channel = 'woocommerce' AND level = 'warning'");
};
// term-hertellingen uitstellen: anders hertelt elke productsave alle tellers
wp_defer_term_counting(true);
$fase($force ? 'wc-sync run 1 (force: alle productsaves)'
             : 'wc-sync run 1 (delta: alleen gewijzigde artikelen)');
do_action('afas_sync_woocommerce', $force, true);
if ($warnings() > 0) {
    $fase(sprintf('wc-sync run 2 (vangnet: %d warnings na run 1)', $warnings()));
    do_action('afas_sync_woocommerce', $force, true);
} else {
    $fase('run 2 overgeslagen: run 1 was schoon');
}
wp_defer_term_counting(false);

$fase('klaar — logsamenvatting:');
foreach ($wpdb->get_results("SELECT level, COUNT(*) n FROM {$wpdb->prefix}lef_logs
    WHERE channel = 'woocommerce' GROUP BY level", ARRAY_A) as $r) {
    printf("         %s: %d\n", $r['level'], (int) $r['n']);
}
PHP
    if [[ "$opties" == *zonder-prijzen* ]]; then
        sed -i 's/PRIJZEN_PLACEHOLDER/true/' "$payload"
    else
        sed -i 's/PRIJZEN_PLACEHOLDER/false/' "$payload"
    fi
    if [[ "$opties" == *delta* ]]; then
        sed -i 's/FORCE_PLACEHOLDER/false/' "$payload"
    else
        sed -i 's/FORCE_PLACEHOLDER/true/' "$payload"
    fi
    wpr_stdin eval-file - < "$payload"
    echo "OK — syncs gedraaid op $(doel_naam)"
}

# ---------------------------------------------------------------------------
# Stap 10 — Afgekeurde klant-accounts verwijderen (besluiten Cas 31 aug +
# 1 sep 2026):
#   wc:18  edwin@roelse.net           (stond ambigu in de mapping)
#   wc:197 saliha@defibrion.nl        (intern account)
#   wc:16  oreogans1337@gmail.com     (geen rol — spam/test)
#   wc:190 suambarawass@gmail.com     (geen rol — spam/test, dubbel)
#   wc:191 suambarawass@gmail.com     (geen rol — spam/test, dubbel)
# Allemaal geverifieerd: geen rol; de stap weigert accounts mét orders. Verwijderen wist ook hun usermeta
# (incl. evt. afas_relatie_id). Idempotent: al-verwijderde users worden
# overgeslagen. Default dry-run; `stap10 apply` verwijdert echt.
# ---------------------------------------------------------------------------
stap10() {
    controleer_config
    local apply="${1:-}"
    local uid
    for uid in 18 197 16 190 191; do
        if ! wpr user get "$uid" --field=user_email 2>/dev/null | grep -q '@'; then
            echo "wc:$uid bestaat niet (meer) op $(doel_naam) — overslaan"
            continue
        fi
        local email orders
        email=$(wpr user get "$uid" --field=user_email | tr -d '[:space:]')
        orders=$(wpr eval "\"echo count(wc_get_orders(['customer_id' => $uid, 'limit' => -1, 'return' => 'ids']));\"" | tr -d '[:space:]')
        if [[ "$orders" != "0" ]]; then
            echo "LET OP: wc:$uid ($email) heeft $orders orders — NIET verwijderd; eerst besluiten wat daarmee moet." >&2
            continue
        fi
        if [[ "$apply" == "apply" ]]; then
            wpr user delete "$uid" --yes
            echo "VERWIJDERD  wc:$uid $email (0 orders)"
        else
            echo "ZOU VERWIJDEREN  wc:$uid $email (0 orders)"
        fi
    done
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — niets verwijderd. Draai '$0 stap10 apply' om echt te verwijderen."
    fi
}

# ---------------------------------------------------------------------------
# Stap 11 — Handgemaakte containers OMZETTEN naar de plugin-conventie
# (besluit Cas 1 sep: omzetten i.p.v. weggooien+herbouwen, zodat post-ID's,
# content, afbeeldingen en menu-links blijven werken).
# Per oude container wordt de family-head bepaald uit zijn variaties
# (sku → AFAS-artikel → Itemcode_Parent, meerderheid wint; kale AED-artikelen
# via de pakket-map, bv. 20013-FR → 21013-UK). Per head overleeft ÉÉN
# kandidaat (meeste publish-variaties, daarna laagste ID): die krijgt
# _afas_artikelnummer = head + SKU = <head>-wpbase en behoudt al zijn
# variaties (voorgekoppeld in stap6 — de sync werkt ze in-place bij en vult
# ontbrekende aan). Overige kandidaten worden gestript en getrasht. Doordat
# elke head daarna een gekoppelde container heeft, hoeft de sync GEEN
# containers meer aan te maken — de blokkade-dans (sync→opruimen→sync)
# vervalt; stap9 volgt ná deze stap.
# Vereist work/cache/afas-artikelen-revendeurs.json (vers: audit --vers).
# Herkenning fresh-pull-proof: containers mét meta of -wpbase-SKU worden
# overgeslagen. Default dry-run; `stap11 apply` voert uit.
# ---------------------------------------------------------------------------
stap11() {
    controleer_config
    local apply="${1:-}"
    local cache="$REPO_ROOT/work/cache/afas-artikelen-revendeurs.json"
    [[ -f "$cache" ]] || { echo "FOUT: $cache ontbreekt (draai work/audit-koppelbaarheid-revendeurs.py --vers)" >&2; exit 1; }
    mkdir -p "$REPO_ROOT/tmp"
    local prefix
    prefix=$(wpr db prefix | tr -d '[:space:]')

    # dump: oude containers (geen meta, geen -wpbase-sku) + variaties met sku
    local dump="$REPO_ROOT/tmp/revendeurs-stap11-dump.tsv"
    wpr db query "\"SELECT par.ID, COALESCE(psku.meta_value,''), par.post_title, v.ID,
            COALESCE(vsku.meta_value,''), v.post_status
        FROM ${prefix}posts par
        JOIN ${prefix}term_relationships tr ON tr.object_id = par.ID
        JOIN ${prefix}term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_type'
        JOIN ${prefix}terms t ON t.term_id = tt.term_id AND t.name = 'variable'
        LEFT JOIN ${prefix}postmeta pan ON pan.post_id = par.ID AND pan.meta_key = '_afas_artikelnummer'
        LEFT JOIN ${prefix}postmeta psku ON psku.post_id = par.ID AND psku.meta_key = '_sku'
        LEFT JOIN ${prefix}posts v ON v.post_parent = par.ID AND v.post_type = 'product_variation'
             AND v.post_status IN ('publish','private')
        LEFT JOIN ${prefix}postmeta vsku ON vsku.post_id = v.ID AND vsku.meta_key = '_sku'
        WHERE par.post_status IN ('publish','private')
          AND (pan.meta_value IS NULL OR pan.meta_value = '')
          AND COALESCE(psku.meta_value,'') NOT LIKE '%-wpbase'\"" --skip-column-names > "$dump"

    python3 - "$cache" "$dump" "$apply" <<'PY' > "$REPO_ROOT/tmp/revendeurs-stap11-payload.php"
import json, sys
from collections import Counter
cache, dump, apply = sys.argv[1], sys.argv[2], sys.argv[3] == "apply"

# Kale AED-artikelen die als container-variatie misbruikt zijn -> de head van
# hun pakketfamilie (uitzoekwerk 31 aug, zie MIGRATIE-REVENDEURS.md).
KALE_PAKKET_MAP = {"20013-FR": "21013-UK", "20014-FR": "21014-UK"}

per_code, per_bhv = {}, {}
for a in json.loads(open(cache).read()):
    code = str(a.get("Itemcode") or "")
    per_code.setdefault(code, a)
    b = str(a.get("Artikelcode_BHV_Voordeelwinkel") or "").strip()
    if b:
        per_bhv.setdefault(b, {})[code] = a

def artikel_van_sku(sku: str):
    a = per_code.get(sku)
    if a is None:
        kandidaten = {c: k for c, k in per_bhv.get(sku, {}).items()
                      if k.get("Geblokkeerd") is not True}
        a = next(iter(kandidaten.values())) if len(kandidaten) == 1 else None
    if a is None or a.get("Geblokkeerd") is True:
        return None
    return a

def head_van_sku(sku: str) -> str:
    a = artikel_van_sku(sku)
    if a is None:
        return ""
    code = str(a.get("Itemcode"))
    return KALE_PAKKET_MAP.get(code) or str(a.get("Itemcode_Parent") or "").strip()

def is_vervallen(sku: str) -> bool:
    # variatie die niet in de container thuishoort: kale AED (Type_item geen
    # Samenstelling), of sku zonder actief AFAS-artikel (B-prefix/soft-delete,
    # bv. 52137*/52138*/21017 — de actieve opvolgers komen via de sync)
    a = artikel_van_sku(sku)
    if a is None:
        return sku != ""
    return str(a.get("Type_item") or "") != "Samenstelling"

containers = {}
for regel in open(dump, encoding="utf-8"):
    d = regel.rstrip("\n").split("\t")
    if len(d) < 6 or not d[0].isdigit():
        continue
    pid, psku, titel, vid, vsku, vstatus = d
    c = containers.setdefault(pid, {"titel": titel, "sku": psku, "heads": Counter(),
                                    "publish_vars": 0, "kale_vars": []})
    if vid.isdigit():
        if vstatus == "publish":
            c["publish_vars"] += 1
        vsku = vsku.strip()
        if is_vervallen(vsku):
            c["kale_vars"].append(int(vid))
        h = head_van_sku(vsku)
        if h:
            c["heads"][h] += 1

per_head = {}
for pid, c in containers.items():
    if not c["heads"]:
        print(f"// LET OP: container wc:{pid} '{c['titel']}' zonder herleidbare head — overgeslagen", file=sys.stderr)
        continue
    head = c["heads"].most_common(1)[0][0]
    per_head.setdefault(head, []).append((pid, c))

plan = []  # (pid, actie, head, titel, n_publish, kale_vars)
for head, kandidaten in per_head.items():
    kandidaten.sort(key=lambda x: (-x[1]["publish_vars"], int(x[0])))
    winnaar = kandidaten[0]
    plan.append((winnaar[0], "convert", head, winnaar[1]["titel"],
                 winnaar[1]["publish_vars"], winnaar[1]["kale_vars"]))
    for pid, c in kandidaten[1:]:
        plan.append((pid, "trash", head, c["titel"], c["publish_vars"], []))
plan.sort(key=lambda x: int(x[0]))

# plan bewaren voor stap14 (menu-herstel): pid -> head, ook voor de dubbelen
import os
plan_pad = os.path.join(os.path.dirname(dump), "revendeurs-container-omzetting.json")
json.dump({p: {"actie": a, "head": h, "titel": t} for p, a, h, t, _, _ in plan},
          open(plan_pad, "w"), ensure_ascii=False, indent=1)

print(f"// {len(containers)} oude containers -> {sum(1 for p in plan if p[1]=='convert')} omzetten, "
      f"{sum(1 for p in plan if p[1]=='trash')} weg; plan: {plan_pad}", file=sys.stderr)
print("<?php")
print(f"$apply = {'true' if apply else 'false'};")
print(f"$plan = json_decode('{json.dumps([{'pid': int(p), 'actie': a, 'head': h, 'kale': k} for p, a, h, _, _, k in plan])}', true);")
print(r"""
global $wpdb;
foreach ($plan as $p) {
    $pid = $p['pid']; $head = $p['head'];
    $kinderen = $wpdb->get_col($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'product_variation'", $pid));
    if ($p['actie'] === 'convert') {
        printf("%s container wc:%d '%s' -> head %s (%d variaties blijven)\n",
            $apply ? 'OMGEZET' : 'ZOU OMZETTEN', $pid, get_the_title($pid), $head, count($kinderen));
        foreach ($p['kale'] as $vid) {
            printf("%s   kale variatie wc:%d (sku=%s) uit deze container\n",
                $apply ? 'WEGGEGOOID' : 'ZOU WEGGOOIEN', $vid, get_post_meta($vid, '_sku', true));
            if ($apply) {
                update_post_meta($vid, '_sku', '');
                delete_post_meta($vid, '_afas_artikelnummer');
                wp_trash_post((int) $vid);
            }
        }
        if ($apply) {
            update_post_meta($pid, '_afas_artikelnummer', $head);
            update_post_meta($pid, '_sku', $head . '-wpbase');
        }
    } else {
        printf("%s container wc:%d '%s' (dubbel voor head %s, %d variaties)\n",
            $apply ? 'WEGGEGOOID' : 'ZOU WEGGOOIEN', $pid, get_the_title($pid), $head, count($kinderen));
        if ($apply) {
            foreach ($kinderen as $vid) {
                update_post_meta($vid, '_sku', '');
                delete_post_meta($vid, '_afas_artikelnummer');
                wp_trash_post((int) $vid);
            }
            update_post_meta($pid, '_sku', '');
            wp_trash_post($pid);
        }
    }
}
if (function_exists('wc_delete_product_transients')) { wc_delete_product_transients(); }
""")
PY

    wpr_stdin eval-file - < "$REPO_ROOT/tmp/revendeurs-stap11-payload.php"
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — niets gewijzigd. Draai '$0 stap11 apply' om uit te voeren."
    fi
}

# ---------------------------------------------------------------------------
# Stap 12 — Franse containernamen + variatie-assen, conform reseller-opzet.
# De plugin kent alleen het platte attribuut "Naam" en noemt containers naar
# de (Engelse) family-head. Hier krijgt elke container:
#   titel + slug      <- groups.model_name_fr (tool = bron van waarheid)
#   pa_langue         <- group_bases.language_code   ("FR/EN/NL" -> "Français · Anglais · Néerlandais")
#   pa_connectivite   <- group_bases.variant_label   (leeg -> "Aucune", WiFi -> "Wi-Fi", ...)
#   pa_capteur-rcp    <- variant_label met/zonder CPR-sensor -> Avec/Sans
#   pa_options        <- accessoires.naam_kort_fr    (geen accessoire -> "Défibrillateur")
# Een as wordt variatie-attribuut bij >1 waarde, anders vast. "Naam" verdwijnt.
# Defaults conform reseller-idee maar Frans: Français / Aucune / Défibrillateur.
# Oude (Franse) URL-slugs zijn met stap11 mee de prullenbak in; redirects van
# oude naar nieuwe slugs zijn een livegang-punt (zie runbook).
# Default dry-run; `stap12 apply` schrijft. Draai na stap9/stap11.
# ---------------------------------------------------------------------------
stap12() {
    controleer_config
    local apply="${1:-}"
    local snapshot="$REPO_ROOT/tmp/samenstellingen.sqlite"
    [[ -f "$snapshot" ]] || { echo "FOUT: $snapshot ontbreekt (tool-snapshot nodig)" >&2; exit 1; }
    mkdir -p "$REPO_ROOT/tmp"
    local prefix
    prefix=$(wpr db prefix | tr -d '[:space:]')

    # shop-dump: variable containers + hun variaties met artikelnummer
    local dump="$REPO_ROOT/tmp/revendeurs-varianten-dump.tsv"
    wpr db query "\"SELECT par.ID, COALESCE(pan.meta_value,''), v.ID, COALESCE(van.meta_value,'')
        FROM ${prefix}posts par
        JOIN ${prefix}term_relationships tr ON tr.object_id = par.ID
        JOIN ${prefix}term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_type'
        JOIN ${prefix}terms t ON t.term_id = tt.term_id AND t.name = 'variable'
        LEFT JOIN ${prefix}postmeta pan ON pan.post_id = par.ID AND pan.meta_key = '_afas_artikelnummer'
        JOIN ${prefix}posts v ON v.post_parent = par.ID AND v.post_type = 'product_variation'
             AND v.post_status IN ('publish','private')
        LEFT JOIN ${prefix}postmeta van ON van.post_id = v.ID AND van.meta_key = '_afas_artikelnummer'
        WHERE par.post_status IN ('publish','private')\"" --skip-column-names > "$dump"

    python3 - "$snapshot" "$dump" "$apply" <<'PY' > "$REPO_ROOT/tmp/revendeurs-assen-payload.php"
import json, sqlite3, sys
snapshot, dump, apply = sys.argv[1], sys.argv[2], sys.argv[3] == "apply"

TALEN = {"NL": "Néerlandais", "EN": "Anglais", "FR": "Français", "DE": "Allemand",
         "ES": "Espagnol", "IT": "Italien", "DK": "Danois", "NO": "Norvégien",
         "SE": "Suédois", "FI": "Finnois", "PL": "Polonais", "CZ": "Tchèque",
         "SK": "Slovaque", "SL": "Slovène", "HU": "Hongrois", "HR": "Croate",
         "EL": "Grec", "GA": "Irlandais", "PT": "Portugais", "LV": "Letton",
         "LT": "Lituanien", "RO": "Roumain", "TR": "Turc", "CH": "Édition Suisse"}
CONNECT = {"": "Aucune", "USB": "USB", "WiFi": "Wi-Fi", "SIGFOX": "Sigfox",
           "4G": "4G", "GPS+WiFi+SIGFOX": "GPS+Wi-Fi+SIGFOX"}
CPR = {"met CPR-sensor": "Avec", "zonder CPR-sensor": "Sans"}

con = sqlite3.connect(snapshot)
# accessoire-itemcode -> Franse korte naam (tool = bron; fallback = label)
opties_fr = {code: (fr or label) for code, fr, label in con.execute(
    "SELECT itemcode, naam_kort_fr, label FROM accessoires")}
# family-head -> Franse modelnaam (containertitel)
titel_fr = {head: naam for head, naam in con.execute(
    "SELECT family_head_itemcode, model_name_fr FROM groups WHERE model_name_fr IS NOT NULL")}
info = {}
for code, taal, label in con.execute(
        "SELECT afas_itemcode, COALESCE(language_code,''), COALESCE(variant_label,'')"
        " FROM group_bases WHERE afas_itemcode IS NOT NULL AND afas_itemcode <> ''"):
    info[code] = (taal, label, None, code)
for code, taal, label, acc, basecode in con.execute(
        "SELECT v.afas_samenstelling_itemcode, COALESCE(b.language_code,''),"
        "       COALESCE(b.variant_label,''), a.itemcode, COALESCE(b.afas_itemcode,'')"
        "  FROM group_variants v"
        "  JOIN group_bases b ON b.id = v.base_id"
        "  LEFT JOIN accessoires a ON a.id = v.accessoire_id"
        " WHERE v.afas_samenstelling_itemcode IS NOT NULL AND v.afas_samenstelling_itemcode <> ''"):
    info[code] = (taal, label, acc, basecode)
con.close()

def taal_naam(code):
    delen = [TALEN.get(d.strip().upper(), d.strip()) for d in code.split("/") if d.strip()]
    return " · ".join(delen) if delen else ""

containers, onbekend = {}, []
for regel in open(dump, encoding="utf-8"):
    d = regel.rstrip("\n").split("\t")
    if len(d) < 4 or not d[0].isdigit():
        continue
    par_id, par_code, var_id, var_code = d[0], d[1].strip(), d[2], d[3].strip()
    rij = info.get(var_code)
    if rij is None:
        onbekend.append((var_id, var_code))
        continue
    taal, label, acc, basecode = rij
    cpr = CPR.get(label, "")
    containers.setdefault(par_id, {"code": par_code, "titel_fr": titel_fr.get(par_code, ""),
                                   "varianten": {}})
    containers[par_id]["varianten"][var_id] = {
        "Langue": taal_naam(taal),
        "Connectivité": "Aucune" if cpr else CONNECT.get(label, label or "Aucune"),
        "Capteur RCP": cpr,
        "Options": opties_fr.get(acc, "Défibrillateur") if acc else "Défibrillateur",
    }
    if cpr and not basecode.endswith("F"):
        containers[par_id]["cpr_default"] = cpr

print(f"// {len(containers)} containers, {sum(len(c['varianten']) for c in containers.values())} variaties"
      f", {len(onbekend)} zonder tool-data", file=sys.stderr)
if onbekend[:5]:
    print(f"//   zonder tool-data (eerste 5): {onbekend[:5]}", file=sys.stderr)
print("<?php")
print(f"$apply = {'true' if apply else 'false'};")
print(f"$containers = json_decode('{json.dumps(containers, ensure_ascii=False)}', true);")
print(r"""
global $wpdb;
$AS_TAX = ['Langue' => 'pa_langue', 'Connectivité' => 'pa_connectivite',
    'Capteur RCP' => 'pa_capteur-rcp', 'Options' => 'pa_options'];
$gemaakt = $gezet = $overgeslagen = 0;

$slugVan = function (string $naam): string {
    return sanitize_title(str_replace(['·', '+'], [' ', ' '], $naam));
};

// Dropdown-volgorde: kaal toestel eerst, dan accessoires oplopend.
$VOLGORDE = [
    'pa_langue' => ['Français'],
    'pa_connectivite' => ['Aucune', 'USB', 'Wi-Fi', '4G', 'Sigfox', 'GPS+Wi-Fi+SIGFOX'],
    'pa_capteur-rcp' => ['Avec', 'Sans'],
    'pa_options' => ['Défibrillateur', 'Sac à Dos', 'Armoire Intérieure ARKY (Blanche)',
        'Armoire Intérieure ARKY (Verte)', 'Armoire Extérieure ARKY (Non Chauffée)',
        'Armoire Extérieure ARKY (Chauffée)', 'Armoire Extérieure ARKY Core Classic',
        'Armoire Extérieure ARKY Core Plus', 'Sacoche Defibtech', 'Sacoche Mindray'],
];
// Default-keuze per as: het kale Franstalige toestel zonder opties.
$DEFAULT_VOORKEUR = ['pa_langue' => 'Français', 'pa_connectivite' => 'Aucune',
    'pa_options' => 'Défibrillateur'];

foreach ($AS_TAX as $label => $tax) {
    if (taxonomy_exists($tax)) { continue; }
    if (!$apply) { echo "ZOU AANMAKEN attribuut: $label ($tax)\n"; continue; }
    $id = wc_create_attribute(['name' => $label, 'slug' => str_replace('pa_', '', $tax),
        'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false]);
    if (is_wp_error($id)) { echo "FOUT attribuut $label: " . $id->get_error_message() . "\n"; continue; }
    register_taxonomy($tax, 'product', ['hierarchical' => false, 'show_ui' => false, 'query_var' => true]);
    echo "attribuut aangemaakt: $label ($tax)\n";
}

// pa_langue als knoppen (zoals pa_taal op reseller)
if ($apply) {
    $wpdb->update($wpdb->prefix . 'woocommerce_attribute_taxonomies',
        ['attribute_type' => 'button'], ['attribute_name' => 'langue']);
    delete_transient('wc_attribute_taxonomies');
}

foreach ($containers as $parId => $data) {
    $parent = wc_get_product((int) $parId);
    if (!$parent || !$parent->is_type('variable')) { $overgeslagen++; continue; }

    $waarden = [];
    foreach ($data['varianten'] as $vid => $assen) {
        foreach ($assen as $label => $waarde) {
            if ($waarde !== '') { $waarden[$label][$waarde] = true; }
        }
    }
    if (empty($waarden)) { $overgeslagen++; continue; }

    // Alleen het platte "Naam" verdwijnt; overige bestaande attributen blijven
    // als vast attribuut staan.
    $attributes = [];
    foreach ($parent->get_attributes() as $sleutel => $bestaand) {
        if (strcasecmp($bestaand->get_name(), 'Naam') === 0) { continue; }
        if (isset($AS_TAX[$bestaand->get_name()]) || in_array($bestaand->get_name(), $AS_TAX, true)) { continue; }
        if ($bestaand->get_variation()) { $bestaand->set_variation(false); }
        $attributes[$sleutel] = $bestaand;
    }
    $positie = count($attributes);
    foreach ($AS_TAX as $label => $tax) {
        if (empty($waarden[$label])) { continue; }
        $namen = array_keys($waarden[$label]);
        $termIds = [];
        foreach ($namen as $naam) {
            $term = get_term_by('name', $naam, $tax) ?: get_term_by('slug', $slugVan($naam), $tax);
            if (!$term) {
                if (!$apply) { continue; }
                $res = wp_insert_term($naam, $tax, ['slug' => $slugVan($naam)]);
                if (is_wp_error($res)) { echo "FOUT term '$naam' in $tax: " . $res->get_error_message() . "\n"; continue; }
                $term = get_term($res['term_id'], $tax);
                $gemaakt++;
            }
            $termIds[] = (int) $term->term_id;
        }
        if (!$termIds) { continue; }
        if ($apply) {
            foreach ($termIds as $tid) {
                $naam = get_term($tid, $tax)->name ?? '';
                $pos = array_search($naam, $VOLGORDE[$tax] ?? [], true);
                if ($pos === false) {
                    $pos = 100 + (ord(substr($naam, 0, 1)) - 65);
                }
                // WooCommerce 3.6+ sorteert menu_order-attributen op termmeta
                // 'order'; de oude conventie 'order_pa_<tax>' blijft erbij voor
                // compatibiliteit (defibsolutions-script gebruikte alleen die,
                // waardoor Défibrillateur hier achteraan zakte — Cas 1 sep).
                update_term_meta($tid, 'order', (int) $pos);
                update_term_meta($tid, 'order_' . $tax, (int) $pos);
            }
        }
        $attr = new WC_Product_Attribute();
        $attr->set_id(wc_attribute_taxonomy_id_by_name($tax));
        $attr->set_name($tax);
        $attr->set_options($termIds);
        $attr->set_position($positie++);
        $attr->set_visible(true);
        $attr->set_variation(count($termIds) > 1);
        $attributes[$tax] = $attr;
        if ($apply) { wp_set_object_terms((int) $parId, $termIds, $tax); }
    }

    // rapporteer op basis van de VERZAMELDE waarden (in dry-run bestaan de
    // termen nog niet, dus $attributes onderrapporteert daar)
    $assenTekst = [];
    foreach ($AS_TAX as $label => $tax) {
        if (empty($waarden[$label])) { continue; }
        $n = count($waarden[$label]);
        $assenTekst[] = str_replace('pa_', '', $tax) . '=' . $n . ($n > 1 ? '' : ' (vast)');
    }
    printf("%s  container #%d [%s] '%s': %s\n", $apply ? 'GEZET' : 'ZOU ZETTEN',
        (int) $parId, $data['code'] ?: '-', $data['titel_fr'] ?: get_the_title((int) $parId),
        implode(', ', $assenTekst));

    if (!$apply) { continue; }

    // Franse titel + slug uit de tool (model_name_fr); plugin heeft
    // update_naam uit staan dus dit blijft staan.
    if ($data['titel_fr'] !== '' && $parent->get_name() !== $data['titel_fr']) {
        wp_update_post(['ID' => (int) $parId, 'post_title' => $data['titel_fr'],
                        'post_name' => sanitize_title($data['titel_fr'])]);
        printf("         titel: '%s'\n", $data['titel_fr']);
    }

    $parent->set_attributes($attributes);

    $defaults = [];
    foreach ($AS_TAX as $label => $tax) {
        if (!isset($attributes[$tax]) || !$attributes[$tax]->get_variation()) { continue; }
        $voorkeur = $tax === 'pa_capteur-rcp'
            ? ($data['cpr_default'] ?? 'Avec')
            : ($DEFAULT_VOORKEUR[$tax] ?? '');
        $namen = array_keys($waarden[$label]);
        sort($namen);
        $keuze = null;
        foreach ($namen as $n) { if ($n === $voorkeur) { $keuze = $n; break; } }
        if ($keuze === null && $voorkeur !== '') {
            foreach ($namen as $n) { if (str_starts_with($n, $voorkeur)) { $keuze = $n; break; } }
            if ($keuze === null) {
                foreach ($namen as $n) { if (str_contains($n, $voorkeur)) { $keuze = $n; break; } }
            }
        }
        if ($keuze === null) { $keuze = $namen[0] ?? null; }
        if ($keuze !== null) {
            $term = get_term_by('name', $keuze, $tax);
            if ($term) { $defaults[$tax] = $term->slug; }
        }
    }
    if ($defaults) {
        $parent->set_default_attributes($defaults);
        printf("         default: %s\n", implode(', ', array_map(
            fn($k, $v) => str_replace('pa_', '', $k) . '=' . $v,
            array_keys($defaults), $defaults)));
    }
    $parent->save();

    foreach ($data['varianten'] as $vid => $assen) {
        $variatie = wc_get_product((int) $vid);
        if (!$variatie) { continue; }
        $nieuw = [];
        foreach ($AS_TAX as $label => $tax) {
            if (!isset($attributes[$tax]) || !$attributes[$tax]->get_variation()) { continue; }
            $naam = $assen[$label] ?? '';
            if ($naam === '') { continue; }
            $term = get_term_by('name', $naam, $tax);
            if ($term) { $nieuw[$tax] = $term->slug; }
        }
        $variatie->set_attributes($nieuw);
        // "Locked" containers laten nieuwe variaties als private binnenkomen;
        // wij kennen de assen, dus publiceren — mits AFAS het artikel actief noemt.
        if ($variatie->get_status() === 'private' && $nieuw) {
            $actief = $wpdb->get_var($wpdb->prepare(
                "SELECT lef_is_active FROM {$wpdb->prefix}lef_afas_artikelen
                  WHERE artikelnummer = %s", (string) get_post_meta((int) $vid, '_afas_artikelnummer', true)));
            if ($actief === null || (int) $actief === 1) {
                $variatie->set_status('publish');
                printf("         variatie #%d gepubliceerd (was private, assen bekend)\n", (int) $vid);
            }
        }
        $variatie->save();
        $gezet++;
    }
}
printf("--- %s: %d containers, %d variaties bijgewerkt, %d termen aangemaakt, %d overgeslagen\n",
    $apply ? 'APPLY' : 'DRY-RUN', count($containers), $gezet, $gemaakt, $overgeslagen);
""")
PY

    wpr_stdin eval-file - < "$REPO_ROOT/tmp/revendeurs-assen-payload.php"
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — niets gewijzigd. Draai '$0 stap12 apply' om te schrijven."
    fi
}

# ---------------------------------------------------------------------------
# Stap 13 — BeRocket-filterbalk repareren (pad-cache).
# woocommerce-ajax-filters (BeRocket AAPF) cachet absolute bestandspaden in
# optie BeRocket_AAPF_getall_Template_Styles; na een verhuizing/pull wijzen
# die naar het oude serverpad en rendert elke filterwidget leeg
# (bapf_mt_none). Les uit de .nl-migratie, zie
# work/handoff-berocket-filter-pad-cache.md. Op revendeurs wijzen de paden
# zelfs nog naar de Plesk-server van vóór cp-01. De actie hieronder laat de
# plugin de optie herschrijven met paden van het huidige target; idempotent,
# hoort na elke pull. (De typo 'tempate' is van de plugin zelf.)
# ---------------------------------------------------------------------------
stap13() {
    controleer_config
    if ! wpr plugin is-active woocommerce-ajax-filters >/dev/null 2>&1; then
        echo "woocommerce-ajax-filters is niet actief op $(doel_naam) — overslaan"
        return 0
    fi
    wpr eval "\"do_action('bapf_include_all_tempate_styles');\"" >/dev/null
    echo "--- controle (paden moeten in de webroot van dit target liggen):"
    wpr eval "\"\\\$o = get_option('BeRocket_AAPF_getall_Template_Styles');
\\\$f = [];
if (is_array(\\\$o)) { array_walk_recursive(\\\$o, function(\\\$v, \\\$k) use (&\\\$f) { if (\\\$k === 'file') \\\$f[] = \\\$v; }); }
echo \\\$f ? implode(PHP_EOL, array_slice(array_unique(\\\$f), 0, 3)) . PHP_EOL : 'LEEG' . PHP_EOL;\""
    echo "OK — BeRocket-template-paden ververst op $(doel_naam)"
}

# ---------------------------------------------------------------------------
# Stap 14 — Menu-herstel. Nav-menu-items van type product die naar een
# getrashte dubbele container wijzen (stap11-plan) worden omgehangen naar de
# overlevende container van dezelfde head; daarna worden dubbelen binnen
# hetzelfde submenu verwijderd (eerste op menu_order wint — "Reanibex 100
# Auto" + "Auto (Connectivité)" worden er zo één). Items naar draft-producten
# (G5, Aivia — open audit-gevallen) worden alleen gerapporteerd.
# Vereist tmp/revendeurs-container-omzetting.json (bijproduct van stap11).
# Default dry-run; `stap14 apply` voert uit.
# ---------------------------------------------------------------------------
stap14() {
    controleer_config
    local apply="${1:-}"
    local plan="$REPO_ROOT/tmp/revendeurs-container-omzetting.json"
    [[ -f "$plan" ]] || { echo "FOUT: $plan ontbreekt (draai eerst stap11)" >&2; exit 1; }
    mkdir -p "$REPO_ROOT/tmp"

    python3 - "$plan" "$apply" <<'PY' > "$REPO_ROOT/tmp/revendeurs-stap14-payload.php"
import json, sys
plan, apply = json.load(open(sys.argv[1])), sys.argv[2] == "apply"
# alleen de getrashte dubbelen hebben een omhang-doel nodig
weg = {pid: info["head"] for pid, info in plan.items() if info["actie"] == "trash"}
print("<?php")
print(f"$apply = {'true' if apply else 'false'};")
print(f"$wegHeads = json_decode('{json.dumps(weg)}', true);")
print(r"""
global $wpdb;
$omgehangen = $verwijderd = 0;
foreach (wp_get_nav_menus() as $menu) {
    $items = wp_get_nav_menu_items($menu->term_id, ['post_status' => 'any']) ?: [];
    $gezien = []; // (parent|object_id) -> menu-item-ID die blijft
    usort($items, fn($a, $b) => $a->menu_order <=> $b->menu_order);
    foreach ($items as $i) {
        if ($i->object !== 'product') { continue; }
        $doelId = (int) $i->object_id;
        $status = get_post_status($doelId) ?: 'weg';
        if ($status === 'trash' && isset($wegHeads[(string) $doelId])) {
            $head = $wegHeads[(string) $doelId];
            $nieuw = $wpdb->get_var($wpdb->prepare(
                "SELECT p.ID FROM {$wpdb->posts} p
                  JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
                 WHERE pm.meta_key = '_afas_artikelnummer' AND pm.meta_value = %s
                   AND p.post_type = 'product' AND p.post_status = 'publish'", $head));
            if ($nieuw) {
                printf("%s menu '%s' item %d '%s': product %d -> %d (head %s)\n",
                    $apply ? 'OMGEHANGEN' : 'ZOU OMHANGEN', $menu->name, $i->ID, $i->title,
                    $doelId, (int) $nieuw, $head);
                if ($apply) { update_post_meta($i->ID, '_menu_item_object_id', (int) $nieuw); }
                $doelId = (int) $nieuw;
                $omgehangen++;
            } else {
                printf("LET OP menu '%s' item %d '%s': geen publish-product voor head %s\n",
                    $menu->name, $i->ID, $i->title, $head);
                continue;
            }
        } elseif ($status !== 'publish') {
            printf("INFO menu '%s' item %d '%s' wijst naar %s product %d — laten staan (assortiment-besluit)\n",
                $menu->name, $i->ID, $i->title, $status, $doelId);
            continue;
        }
        $sleutel = $i->menu_item_parent . '|' . $doelId;
        if (isset($gezien[$sleutel])) {
            printf("%s menu '%s' item %d '%s' (dubbel op product %d naast item %d)\n",
                $apply ? 'VERWIJDERD' : 'ZOU VERWIJDEREN', $menu->name, $i->ID, $i->title,
                $doelId, $gezien[$sleutel]);
            if ($apply) { wp_delete_post($i->ID, true); }
            $verwijderd++;
        } else {
            $gezien[$sleutel] = $i->ID;
        }
    }
}
printf("--- %s: %d omgehangen, %d dubbele items verwijderd\n",
    $apply ? 'APPLY' : 'DRY-RUN', $omgehangen, $verwijderd);
""")
PY

    wpr_stdin eval-file - < "$REPO_ROOT/tmp/revendeurs-stap14-payload.php"
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — niets gewijzigd. Draai '$0 stap14 apply' om uit te voeren."
    fi
}

# ---------------------------------------------------------------------------
# Stap 15 — Variatie-knoppen: niet-beschikbare opties grijs i.p.v. rood kruis
# (verzoek Cas 1 sep). woo-variation-swatches-instelling attribute_behavior:
# 'blur' (vervagen + rood kruis) -> 'blur-no-cross' (alleen vervagen).
# Alleen deze sleutel; de rest van de live-config blijft staan. Idempotent.
# ---------------------------------------------------------------------------
stap15() {
    controleer_config
    wpr_stdin eval-file - <<'PHP'
<?php
$o = get_option('woo_variation_swatches');
if (!is_array($o)) { echo "woo_variation_swatches ontbreekt - overslaan\n"; return; }
$was = $o['attribute_behavior'] ?? '(leeg)';
if ($was === 'blur-no-cross') {
    echo "attribute_behavior staat al op blur-no-cross\n";
} else {
    $o['attribute_behavior'] = 'blur-no-cross';
    update_option('woo_variation_swatches', $o);
    delete_transient('woo_variation_swatches_cache');
    printf("attribute_behavior: %s -> blur-no-cross\n", $was);
}
PHP
    echo "OK — niet-beschikbare variatie-opties vervagen zonder kruis op $(doel_naam)"

    # Checkout-veldinstellingen conform reseller/defibsolutions (verzoek Cas
    # 1 sep): bedrijfsnaam verbergen (komt uit de AFAS-relatie), telefoon en
    # adresregel 2 optioneel — zonder dit stonden company/phone op required
    # en liepen B2B-klanten vast op velden die AFAS al kent.
    wpr option update woocommerce_checkout_company_field hidden >/dev/null
    wpr option update woocommerce_checkout_phone_field optional >/dev/null
    wpr option update woocommerce_checkout_address_2_field optional >/dev/null
    echo "OK — checkout-velden: bedrijfsnaam hidden, telefoon/adres2 optional op $(doel_naam)"
}

# ---------------------------------------------------------------------------
# Stap 16 — Afgekeurde producten naar de prullenbak (besluiten Cas 1 sep:
# de 9 draft-restgevallen + wc:685 Laerdal-veer 141700 "eruit").
# SKU + meta worden gestript zodat
# ze nooit meer matchen; de sku-guard voorkomt dat een hergebruikt wc-id na
# een verse pull per ongeluk iets anders raakt. Idempotent.
# Default dry-run; `stap16 apply` voert uit.
# ---------------------------------------------------------------------------
stap16() {
    controleer_config
    local apply="${1:-}"
    wpr_stdin eval-file - <<PHP
<?php
\$apply = $([ "$apply" == "apply" ] && echo true || echo false);
\$besluit = [  // wc_id => verwachte sku (besluit Cas 1 sep 2026)
    95 => 'A200', 134 => '11403-000001', 357 => 'G5A-11C-FR', 358 => 'G5S-11C-FR',
    419 => '202-56052', 809 => '8008-0050-02', 863 => '03-DTR-G2006ZZ',
    864 => 'M3871A', 872 => '03-DTR-G2052ZZ',
    685 => '141700', // Laerdal compressieveer — besluit Cas 1 sep: eruit
    135 => '11403-000002', // draft-duplicaat van wc:991865 (10227); hield de BHV-sku bezet
];
foreach (\$besluit as \$pid => \$sku) {
    \$status = get_post_status(\$pid);
    if (\$status === false || \$status === 'trash') { printf("wc:%d al weg/prullenbak — overslaan\n", \$pid); continue; }
    \$echteSku = (string) get_post_meta(\$pid, '_sku', true);
    if (\$echteSku !== \$sku) { printf("LET OP wc:%d sku='%s' != verwacht '%s' — overslaan\n", \$pid, \$echteSku, \$sku); continue; }
    printf("%s wc:%d (%s) '%s'\n", \$apply ? 'PRULLENBAK' : 'ZOU PRULLENBAK', \$pid, \$sku, get_the_title(\$pid));
    if (\$apply) {
        update_post_meta(\$pid, '_sku', '');
        delete_post_meta(\$pid, '_afas_artikelnummer');
        wp_trash_post(\$pid);
    }
}
PHP
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — niets verwijderd. Draai '$0 stap16 apply' om uit te voeren."
    fi
}

# ---------------------------------------------------------------------------
# Stap 17 — Beheerders koppelen aan een AFAS-relatie (patroon defibsolutions-
# stap17, verzoek Cas 1 sep): zonder afas_relatie_id werkt de checkout-
# adres-dropdown (en klantprijs-weergave) niet voor admins. Relatie 23135
# (IDEALIS BRETAGNE, F — keuze Cas; huis-alternatief: 10003 Defibrion sarl) +
# afas_sync_paused=1 ("gegevens van dit account niet synchroniseren vanuit
# AFAS") zodat de relatie-sync het admin-account nooit overschrijft.
# Bestaande koppelingen blijven staan. Default dry-run; `stap17 apply` schrijft.
# ---------------------------------------------------------------------------
stap17() {
    controleer_config
    local apply="${1:-}"
    wpr_stdin eval-file - "$apply" <<'PHP'
<?php
$apply = ('apply' === ($args[0] ?? ''));
$relatie = '23135';
foreach (get_users(['role' => 'administrator']) as $u) {
    $huidig = (string) get_user_meta($u->ID, 'afas_relatie_id', true);
    $paused = (string) get_user_meta($u->ID, 'afas_sync_paused', true);
    $acties = [];
    if ($huidig === '') { $acties[] = "relatie -> $relatie"; }
    if ($paused !== '1') { $acties[] = 'sync_paused -> 1'; }
    if (!$acties) {
        printf("%-40s staat al goed (relatie %s)\n", $u->user_login, $huidig);
        continue;
    }
    if ($apply) {
        if ($huidig === '') { update_user_meta($u->ID, 'afas_relatie_id', $relatie); }
        if ($paused !== '1') { update_user_meta($u->ID, 'afas_sync_paused', '1'); }
    }
    printf("%-40s %s%s\n", $u->user_login, implode(', ', $acties), $apply ? '' : ' (dry-run)');
}
PHP
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — draai '$0 stap17 apply' om te schrijven."
    else
        echo "OK — beheerders gekoppeld op $(doel_naam)"
    fi
}

# ---------------------------------------------------------------------------
# bouwlocatie-afronding — na een migrater-deploy naar de dev-site
# (revendeursfr.defibrion.dev): de lokale kopie heeft de loginmuur uit
# (migrater deactiveert jonradio-private-site en plaatst zz-unlock-local) en
# die staat komt met de push mee. Hier: unlock-/lokale-mailblok-bestanden weg,
# jonradio-private-site aan, en controle dat een gast naar de login redirect.
# Draai met REVEND_TARGET=cp01 en REVEND_SERVER/WP_ROOT op de dev-site.
# ---------------------------------------------------------------------------
bouwlocatie-afronding() {
    controleer_config
    if [[ "$TARGET" != "cp01" ]]; then
        echo "bouwlocatie-afronding is bedoeld voor REVEND_TARGET=cp01 (dev-site)." >&2
        exit 1
    fi
    ssh "$SERVER" "rm -f '$WP_ROOT/wp-content/mu-plugins/zz-unlock-local.php' '$WP_ROOT/wp-content/mu-plugins/zz-disable-emails-local.php'"
    echo "lokale unlock-/mailblok-mu-plugins verwijderd"
    wpr plugin activate jonradio-private-site
    local url
    url=$(wpr option get siteurl | tr -d '[:space:]')
    echo "--- controle (gast hoort een redirect naar de login te krijgen):"
    curl -s -o /dev/null -w "gast %{http_code} -> %{redirect_url}\n" --max-time 20 "$url/" || true
    echo "OK — bouwlocatie afgeschermd op $(doel_naam)"
}

# ---------------------------------------------------------------------------
# backup — volledige backup van de cp01-site (bestanden + database) vóór de
# livegang-reeks. Alleen zinvol met REVEND_TARGET=cp01. Dump komt in de home
# van de site-user te staan met timestamp; blijft daar tot handmatige opruiming.
# ---------------------------------------------------------------------------
backup() {
    controleer_config
    if [[ "$TARGET" != "cp01" ]]; then
        echo "backup is bedoeld voor REVEND_TARGET=cp01 (lokale kopie is wegwerp)." >&2
        exit 1
    fi
    local stempel
    stempel=$(date +%Y%m%d-%H%M)
    echo "database-dump..."
    ssh "$SERVER" "cd '$WP_ROOT' && wp db export ~/backup-revendeurs-$stempel.sql --add-drop-table && gzip ~/backup-revendeurs-$stempel.sql"
    echo "bestanden (tar, exclusief cache)..."
    ssh "$SERVER" "tar --exclude='*/cache/*' -czf ~/backup-revendeurs-$stempel-files.tar.gz -C '$(dirname "$WP_ROOT")' '$(basename "$WP_ROOT")'"
    echo "--- controle:"
    ssh "$SERVER" "ls -lh ~/backup-revendeurs-$stempel*"
    echo "OK — backup staat in de home van de site-user op $SERVER"
}

# ---------------------------------------------------------------------------
# slotstap — allerlaatste livegang-acties, pas ná alle controles (fase 2):
# order-push naar AFAS aan + mail weer aan. Default dry-run; `slotstap apply`
# voert uit en herinnert aan de handmatige acties (Bron Order-code +
# administratie-keuze in AFAS — besluiten 3.1/3.2 van Cas).
# ---------------------------------------------------------------------------
slotstap() {
    controleer_config
    local apply="${1:-}"
    echo "huidige stand: afas_sync_orders_enabled=$(wpr option get afas_sync_orders_enabled 2>/dev/null | tr -d '[:space:]')"
    wpr plugin list --status=active --field=name 2>/dev/null | grep -q '^disable-emails$' \
        && echo "huidige stand: disable-emails ACTIEF (mail uit)" \
        || echo "huidige stand: disable-emails niet actief (mail aan)"
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — zou order-push aanzetten en disable-emails deactiveren."
        echo "Draai '$0 slotstap apply' als alle fase-2-controles groen zijn."
        return 0
    fi
    wpr option update afas_sync_orders_enabled 1 >/dev/null
    wpr plugin deactivate disable-emails 2>/dev/null || true
    echo "OK — order-push AAN en mail AAN op $(doel_naam)"
    echo "LET OP handmatig in AFAS (besluit Cas): eigen 'Bron Order'-code voor"
    echo "revendeurs zetten in afas_sync_orders_vrije_velden (nu 68 = reseller)"
    echo "en de administratie-keuze controleren (afas_sync_orders_administratie=1)."
}

# ---------------------------------------------------------------------------
# reeks — de volledige bewezen volgorde in één run (reproduceerbaarheids-
# check en straks de cp01-livegang). Vereist een verse of bestaande kopie;
# de verse pull zelf gaat via wordpress-migrater:
#   ./migrate.sh -c revendeurs --pull --local-refresh
# Volgorde (31 aug): basisstappen → stap9 vol (container-blokkade-warnings
# verwacht) → stap11 opruimen → stap9 herbouw (force!) → stap12 assen.
# ---------------------------------------------------------------------------
reeks() {
    local t0=$SECONDS
    local s
    # stap11 (omzetten) vóór stap9: elke head heeft dan al een gekoppelde
    # container, dus de sync hoeft er geen aan te maken — één sync volstaat.
    for s in "stap1" "stap2" "stap3 apply" "stap4" "stap5 apply" "stap6 apply" \
             "stap7" "stap8" "stap13" "stap10 apply" "stap17 apply" "stap11 apply" \
             "stap9" "stap12 apply" "stap14 apply" "stap15" "stap16 apply"; do
        echo ""
        echo "===== reeks: $s [$(( (SECONDS - t0) / 60 ))m] ====="
        # shellcheck disable=SC2086
        $s
    done
    echo ""
    echo "===== reeks compleet in $(( (SECONDS - t0) / 60 ))m ====="
}

usage() {
    cat <<EOF
Gebruik: $0 <stap> [apply|opties]

  REVEND_TARGET=lokaal (default) of cp01

Stappen:
  stap1         Mail uit (disable-emails installeren + activeren)
  stap2         wp-staging(-pro), litespeed-cache, jetpack, slimstat uit
  stap3 [apply] Klanten koppelen aan AFAS-relaties (usermeta afas_relatie_id)
  stap4         lefcreative-afas-b2b + settings (lokaal: order-push uit,
                testklant-wachtwoord)
  stap5 [apply] Alle WooCommerce REST-keys + application passwords intrekken
  stap6 [apply] Voorkoppeling _afas_artikelnummer op gematchte producten
  stap7         mu-plugins plaatsen (expliciete selectie van 9)
  stap8         Wholesale Suite uit (omschakelmoment: prijzen uit AFAS)
  stap9 [zonder-prijzen] [delta]
                Syncs draaien (vereist Sync_Revendeurs_FR-vlaggen in AFAS;
                vlaggen zetten: afas-connector-tools/bin/apply-revendeurs-vlaggen.php)
  stap10 [apply] Afgekeurde klant-accounts (wc:18, wc:197) verwijderen
  stap11 [apply] Handgemaakte containers omzetten naar plugin-conventie
                (1 per head; dubbelen weg) — vóór stap9 draaien
  stap12 [apply] Franse containernamen (tool: model_name_fr) + variatie-assen
                (pa_langue/pa_connectivite/pa_capteur-rcp/pa_options)

  stap13        BeRocket-filterbalk: pad-cache verversen (na elke pull)
  stap14 [apply] Menu-items omhangen naar overlevende containers + dubbelen weg
  stap15        Weergave: variatie-knoppen grijs i.p.v. kruis + checkout-velden
                (bedrijfsnaam hidden, telefoon/adres2 optional)
  stap16 [apply] Afgekeurde draft-producten prullenbak (besluit 1 sep)
  stap17 [apply] Beheerders koppelen aan relatie 23135 + sync-pauze
  backup        cp01: volledige backup (db + files) vóór de livegang-reeks
  bouwlocatie-afronding  dev-site afschermen (unlock weg, jonradio aan)
  slotstap [apply] Livegang-slot: order-push aan + mail aan (na controles)
  reeks         Alle stappen in de bewezen volgorde (repro-check / livegang)
EOF
    exit 1
}

[[ $# -ge 1 ]] || usage
case "$1" in
    stap1|stap2|stap3|stap4|stap5|stap6|stap7|stap8|stap9|stap10|stap11|stap12|stap13|stap14|stap15|stap16|stap17|backup|slotstap|bouwlocatie-afronding|reeks) "$@" ;;
    *) usage ;;
esac
