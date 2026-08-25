#!/usr/bin/env bash
#
# Migratiescript DefibSolutions-webshop → nieuwe server (B2BKing → lefcreative-afas-b2b).
# Wordt stap voor stap opgebouwd; elke stap is een aparte functie en wordt expliciet
# per naam aangeroepen — geen "alles in één keer".
#
#   ./migration/defibsolutions-migratie.sh stap1
#
# Target-keuze (lokaal-eerst, zie MIGRATIE-DEFIBSOLUTIONS.md "Spelregels"):
#   DEFIBS_TARGET=lokaal  (default) draait elke stap in de wpcli-container van
#                         de lokale Docker-kopie (~/projects/wordpress-migrater,
#                         .env-defibsolutions, site op poort 8897)
#   DEFIBS_TARGET=cp01    draait exact dezelfde stap via ssh op de nieuwe server
#
# Serverconfig komt uit de project-.env (repo-root), net als de AFAS-credentials:
#   DEFIBS_SERVER        ssh-host van de nieuwe server (alleen nodig bij cp01)
#   DEFIBS_WP_ROOT       pad naar de WordPress-root op die server (idem)
#   DEFIBS_MIGRATER_DIR  pad naar wordpress-migrater (default ~/projects/wordpress-migrater)
# Environment-variabelen met dezelfde naam gaan vóór de .env-waarden.
#
# Achtergrond/fase-overzicht: MIGRATIE-DEFIBSOLUTIONS.md · blauwdruk: MIGRATIE-uitgevoerd.md
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

TARGET="${DEFIBS_TARGET:-lokaal}"
SERVER="${DEFIBS_SERVER:-INVULLEN-user@nieuwe-server}"
WP_ROOT="${DEFIBS_WP_ROOT:-INVULLEN-/pad/naar/wordpress}"
MIGRATER_DIR="${DEFIBS_MIGRATER_DIR:-$HOME/projects/wordpress-migrater}"

# De PHP op beide targets is nieuwer dan de oude plugins/het theme; de
# "Deprecated:"-meldingen vervuilen elke stap-output en worden weggefilterd.
# --line-buffered: zonder dit houdt grep de output vast tot het eind en zie je
# de fase-voortgang van stap11 pas als alles klaar is.
_filter_ruis() { grep --line-buffered -vE '^(Deprecated|Notice):' || true; }

_lokaal_compose() {
    # --progress quiet: compose-statusregels ("Container ... Running") gaan
    # anders door de 2>&1-merge heen en vervuilen gevangen stap-output.
    docker compose --progress quiet --project-directory "$MIGRATER_DIR" \
        --env-file "$MIGRATER_DIR/.env-defibsolutions" "$@"
}

doel_naam() {
    [[ "$TARGET" == "lokaal" ]] && echo "lokale kopie (localhost:8897)" || echo "$SERVER"
}

# wp-cli op het gekozen target. Args gaan als één string door een shell
# (sh -c / ssh), zodat quoting op beide targets identiek uitpakt.
# wpr leest NIET van stdin (veilig in loops); wpr_stdin wél (voor eval-file -).
wpr() {
    wpr_stdin "$@" < /dev/null
}

# De wpcli-image (Alpine) draait default als uid 82, maar de fpm-container
# (Debian) beheert wp-content als uid 33 — schrijvende wp-commando's moeten
# dus als 33 draaien. HOME=/tmp voor de wp-cli-cache (uid 33 heeft geen home).
_LOKAAL_RUN_OPTS=(--rm -T --user 33:33 -e HOME=/tmp)

wpr_stdin() {
    if [[ "$TARGET" == "lokaal" ]]; then
        # memory_limit: de 128M van de wpcli-container is te krap zodra WP
        # volledig laadt; de syncs (stap11) hebben ruim geheugen nodig.
        _lokaal_compose run "${_LOKAAL_RUN_OPTS[@]}" wpcli \
            sh -c "php -d memory_limit=1024M -d max_execution_time=0 /usr/local/bin/wp $*" 2>&1 | _filter_ruis
    else
        ssh "$SERVER" "cd '$WP_ROOT' && wp $*" 2>&1 | _filter_ruis
    fi
}

_lokaal_prep() {
    # Na een verse pull zijn schrijfmappen eigendom van de host-user (1000);
    # uid 33 (php-fpm) moet erin kunnen schrijven: upgrade/ (plugin-installs),
    # et-cache/ (Divi Dynamic CSS — zonder deze map geen thema-styling!) en
    # uploads/wc-logs. Idempotent, dus veilig per run.
    _lokaal_compose run --rm -T --user 0 wpcli sh -c \
        'cd /var/www/html/wp-content && mkdir -p upgrade et-cache uploads/wc-logs \
         && chown -R 33:33 upgrade et-cache uploads/wc-logs' \
        >/dev/null 2>&1 || true
}

controleer_config() {
    if [[ "$TARGET" == "lokaal" ]]; then
        if [[ ! -f "$MIGRATER_DIR/.env-defibsolutions" ]]; then
            echo "FOUT: $MIGRATER_DIR/.env-defibsolutions ontbreekt (zet evt. DEFIBS_MIGRATER_DIR)." >&2
            exit 1
        fi
        if ! _lokaal_compose ps --status=running 2>/dev/null | grep -q 'defibsolutions-db'; then
            echo "FOUT: lokale defibsolutions-stack draait niet. Start met:" >&2
            echo "  cd $MIGRATER_DIR && docker compose --env-file .env-defibsolutions up -d" >&2
            exit 1
        fi
        _lokaal_prep
    elif [[ "$TARGET" == "cp01" ]]; then
        if [[ "$SERVER" == INVULLEN-* || "$WP_ROOT" == INVULLEN-* ]]; then
            echo "FOUT: zet eerst DEFIBS_SERVER en DEFIBS_WP_ROOT (zie kop van dit script)." >&2
            exit 1
        fi
    else
        echo "FOUT: onbekend DEFIBS_TARGET '$TARGET' (lokaal of cp01)." >&2
        exit 1
    fi
    echo "[target: $TARGET]"
}

# ---------------------------------------------------------------------------
# Stap 1 — Mail UIT op de nieuwe server.
# Voorkomt dat klanten welkomst-/account-/order-mails krijgen tijdens het
# inrichten en syncen. Weer aanzetten is de allerlaatste stap van de migratie.
# ---------------------------------------------------------------------------
stap1() {
    controleer_config
    wpr plugin install disable-emails --activate
    echo "--- controle:"
    wpr plugin list --status=active | grep disable-emails
    echo "OK — mail staat uit op $(doel_naam)"
}

# ---------------------------------------------------------------------------
# Stap 2 — Overbodige plugins UIT: Jetpack + B2BKing.
# Jetpack hoort niet mee te draaien tijdens/na de migratie (externe koppelingen,
# mails, stats). B2BKing wordt vervangen door lefcreative-afas-b2b; de
# B2BKing-data blijft in de database staan als inerte fallback (zelfde aanpak
# als de wholesale-plugins bij ARKY). Alleen deactiveren; verwijderen kan in
# de eindschoonmaak.
# ---------------------------------------------------------------------------
stap2() {
    controleer_config
    local p
    for p in jetpack b2bking-wholesale-for-woocommerce b2bking; do
        if wpr plugin is-installed "$p" >/dev/null 2>&1; then
            wpr plugin deactivate "$p"
        else
            echo "$p is niet geïnstalleerd op $(doel_naam) — overslaan"
        fi
    done
    echo "--- controle:"
    wpr plugin list | { grep -iE 'jetpack|b2bking' || echo "(geen jetpack/b2bking gevonden)"; }
    echo "OK — jetpack + b2bking staan uit op $(doel_naam)"
}

# ---------------------------------------------------------------------------
# Stap 3 — Klanten koppelen aan AFAS-verkooprelaties (usermeta afas_relatie_id,
# het veld waar lefcreative-afas-b2b op draait).
# Bron: work/klant-relatie-mapping.csv (wc_user_id;afas_relatie_id) — statische
# uitdraai op basis van de ORDERHISTORIE van de huidige koppeling (per klant de
# relatie waar de meeste AFAS-orders op geboekt zijn). Users zonder orderbewijs
# staan niet in de CSV en blijven bewust ongekoppeld.
# Default dry-run (toont ook het e-mailadres van de user op de server ter
# verificatie); `stap3 apply` schrijft echt.
# ---------------------------------------------------------------------------
stap3() {
    controleer_config
    local mapping="$REPO_ROOT/work/klant-relatie-mapping.csv"
    local apply="${1:-}"
    [[ -f "$mapping" ]] || { echo "FOUT: $mapping ontbreekt (statische order-historie-mapping)" >&2; exit 1; }

    python3 - "$mapping" "$apply" <<'PY' > /tmp/afas-relatie-payload.php
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

    wpr_stdin eval-file - < /tmp/afas-relatie-payload.php
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — niets geschreven. Draai '$0 stap3 apply' om echt te schrijven."
    fi
}

# ---------------------------------------------------------------------------
# Stap 4 — lefcreative-afas-b2b plugin installeren + activeren + AFAS-settings.
# Bronnen (beide in work/, gitignored):
#   - work/lefcreative-afas-b2b-<versie>.zip  (identieke 1.3.14 van ARKY/reseller)
#   - work/afas-settings.json                 (dump van alle 91 afas_*-opties,
#     incl. app-token — daarom bewust buiten git)
# --force + update_option maken her-runnen idempotent: de stap zet de shop
# altijd terug naar exact deze plugin-versie + settings-set.
# ---------------------------------------------------------------------------
stap4() {
    controleer_config
    local zip
    zip=$(ls -1 "$REPO_ROOT"/work/lefcreative-afas-b2b-*.zip 2>/dev/null | sort | tail -1)
    [[ -n "$zip" ]] || { echo "FOUT: geen work/lefcreative-afas-b2b-*.zip gevonden" >&2; exit 1; }
    if [[ "$TARGET" == "lokaal" ]]; then
        # geen scp nodig: work/ read-only in de container mounten
        _lokaal_compose run "${_LOKAAL_RUN_OPTS[@]}" -v "$(dirname "$zip"):/defibs-work:ro" wpcli \
            sh -c "php -d memory_limit=512M /usr/local/bin/wp plugin install '/defibs-work/$(basename "$zip")' --force --activate" 2>&1 | _filter_ruis
    else
        echo "upload $(basename "$zip") ..."
        scp -q "$zip" "$SERVER:/tmp/lefcreative-afas-b2b.zip"
        wpr plugin install /tmp/lefcreative-afas-b2b.zip --force --activate
    fi

    local settings="$REPO_ROOT/work/afas-settings.json"
    if [[ -f "$settings" ]]; then
        python3 - "$settings" <<'PY' > /tmp/afas-settings-payload.php
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
        wpr_stdin eval-file - < /tmp/afas-settings-payload.php
    else
        echo "LET OP: $settings ontbreekt — plugin actief maar zonder settings-import."
    fi

    if [[ "$TARGET" == "lokaal" ]]; then
        # Veiligheidsgordel: op de lokale kopie mag order-push naar AFAS
        # nooit aan staan, ook niet als de settings-bron hem (voor live) aanzet.
        wpr option update afas_sync_orders_enabled 0 >/dev/null
        echo "(lokaal: afas_sync_orders_enabled geforceerd op 0)"
    fi
    echo "--- controle:"
    wpr plugin list | grep -i lefcreative
    wpr option get afas_env_type
    echo "OK — plugin actief + settings geimporteerd op $(doel_naam)"
}

# ---------------------------------------------------------------------------
# Stap 5 — Alle API-keys van de shop intrekken.
# De oude Improvit/EasyLinQ-koppeling schrijft via een WooCommerce REST-key
# ("improvit defibrion", read_write) orders/prijzen de shop in; daarnaast
# bestaan er application passwords (o.a. "improvit - AFAS"). Na de migratie
# mag niets van buitenaf meer muteren — de nieuwe plugin praat zelf uitgaand
# met AFAS en heeft geen inkomende REST-key nodig. Alles gaat weg; wie later
# weer toegang nodig heeft maakt bewust een nieuwe key aan.
# Default dry-run (toont wat er staat); `stap5 apply` verwijdert echt.
# Tabelprefix is wp_ (geverifieerd via `wp db prefix`).
# ---------------------------------------------------------------------------
stap5() {
    controleer_config
    local apply="${1:-}"

    echo "--- WooCommerce REST API-keys:"
    wpr db query "\"SELECT key_id, user_id, description, permissions, truncated_key, last_access FROM wp_woocommerce_api_keys\""

    echo ""
    echo "--- Application passwords:"
    local userids
    # 'a:0:{}' = lege rij die WP na verwijderen laat staan — geen wachtwoord
    userids=$(wpr db query "\"SELECT user_id FROM wp_usermeta WHERE meta_key='_application_passwords' AND meta_value NOT IN ('', 'a:0:{}')\"" --skip-column-names)
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
    wpr db query "\"DELETE FROM wp_woocommerce_api_keys\""
    for uid in $userids; do
        wpr user application-password delete "$uid" --all
    done

    echo "--- controle:"
    wpr db query "\"SELECT COUNT(*) AS rest_keys FROM wp_woocommerce_api_keys\""
    wpr db query "\"SELECT COUNT(*) AS app_passwords FROM wp_usermeta WHERE meta_key='_application_passwords' AND meta_value NOT IN ('', 'a:0:{}')\""
    echo "OK — alle API-keys ingetrokken op $(doel_naam)"
}

# ---------------------------------------------------------------------------
# Stap 6 — Voorkoppeling: per WC-product (publish/private, incl. variaties) de
# AFAS-itemcode in postmeta _afas_artikelnummer zetten. Zonder deze stap kan de
# artikelen-sync bestaande producten niet vinden (hij matcht op deze meta, met
# alleen SKU==itemcode als fallback) en maakt hij duplicaten aan.
#
# Doel-itemcode per product, in volgorde:
#   1. akkoord-rij in work/voorkoppel-actielijst.csv (uitzonderingen én de
#      kale-AED→samenstelling-omzettingen);
#   2. SKU matcht precies één actief AFAS-artikel op Artikelcode_BHV_Voordeelwinkel;
#   3. SKU is zelf een actieve AFAS-itemcode.
# Geblokkeerde artikelen (soft-delete, B-prefix) doen nooit mee.
# Bron-artikelen: work/cache/afas-artikelen.json (vers te halen met de audit).
# Default dry-run; `stap6 apply` schrijft echt.
# ---------------------------------------------------------------------------
stap6() {
    controleer_config
    local apply="${1:-}"
    local cache="$REPO_ROOT/work/cache/afas-artikelen.json"
    local actielijst="$REPO_ROOT/work/voorkoppel-actielijst.csv"
    [[ -f "$cache" ]] || { echo "FOUT: $cache ontbreekt (draai de koppelbaarheids-audit met --vers)" >&2; exit 1; }
    [[ -f "$actielijst" ]] || { echo "FOUT: $actielijst ontbreekt" >&2; exit 1; }

    mkdir -p "$REPO_ROOT/tmp"
    local shopdump="$REPO_ROOT/tmp/defibs-shop-skus.tsv"
    wpr db query "\"SELECT p.ID, p.post_type, COALESCE(sku.meta_value,''), COALESCE(an.meta_value,'') FROM wp_posts p LEFT JOIN wp_postmeta sku ON sku.post_id=p.ID AND sku.meta_key='_sku' LEFT JOIN wp_postmeta an ON an.post_id=p.ID AND an.meta_key='_afas_artikelnummer' WHERE p.post_type IN ('product','product_variation') AND p.post_status IN ('publish','private')\"" --skip-column-names > "$shopdump"

    python3 - "$cache" "$actielijst" "$shopdump" "$apply" <<'PY' > /tmp/afas-voorkoppel-payload.php
import csv, json, sys
from collections import defaultdict

cache, actielijst, shopdump, apply = sys.argv[1:5]
d = json.load(open(cache))
print(f"// AFAS-cache van {d.get('datum','?')}", file=sys.stderr)

per_itemcode, per_bhv = {}, defaultdict(list)
for r in d["data"]:
    c = (r.get("Itemcode") or "").strip()
    if not c or c in per_itemcode:
        continue
    per_itemcode[c] = r
    if str(r.get("Geblokkeerd", "")).lower() in ("true", "1"):
        continue
    b = (r.get("Artikelcode_BHV_Voordeelwinkel") or "").strip()
    if b:
        per_bhv[b].append(c)

akkoord = {}
for r in csv.DictReader(open(actielijst, encoding="utf-8-sig"), delimiter=";"):
    if r["status"].strip() == "akkoord" and r["itemcode"].strip():
        akkoord[r["wc_id"].strip()] = r["itemcode"].strip()

def actief(c):
    r = per_itemcode.get(c)
    return r is not None and str(r.get("Geblokkeerd", "")).lower() not in ("true", "1")

paren = {}
for line in open(shopdump, encoding="utf-8"):
    delen = line.rstrip("\n").split("\t")
    if len(delen) < 4 or not delen[0].isdigit():
        continue
    wc_id, _ptype, sku, huidig = delen[0], delen[1], delen[2].strip(), delen[3].strip()
    doel = None
    if wc_id in akkoord:
        doel = akkoord[wc_id]
    elif sku and len(per_bhv.get(sku, [])) == 1:
        doel = per_bhv[sku][0]
    elif sku and actief(sku):
        doel = sku
    if doel:
        paren[wc_id] = doel

print(f"// {len(paren)} voorkoppelingen bepaald ({len(akkoord)} uit actielijst)", file=sys.stderr)
print("<?php")
print(f"$apply = {'true' if apply == 'apply' else 'false'};")
print(f"$map = json_decode('{json.dumps(paren)}', true);")
print("""
$gezet = $al = $anders = 0;
foreach ($map as $pid => $code) {
    $huidig = (string) get_post_meta((int) $pid, '_afas_artikelnummer', true);
    if ($huidig === $code) { $al++; continue; }
    if ($apply) { update_post_meta((int) $pid, '_afas_artikelnummer', $code); }
    printf("%s  wc:%d: %s -> %s\\n", $apply ? 'GEZET' : 'ZOU ZETTEN',
        (int) $pid, $huidig !== '' ? $huidig : '-', $code);
    $huidig !== '' ? $anders++ : $gezet++;
}
printf("--- %s: %d nieuw, %d overschreven, %d stonden al goed\\n",
    $apply ? 'APPLY' : 'DRY-RUN', $gezet, $anders, $al);
""")
PY

    wpr_stdin eval-file - < /tmp/afas-voorkoppel-payload.php
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — niets geschreven. Draai '$0 stap6 apply' om echt te schrijven."
    fi
}

# ---------------------------------------------------------------------------
# Stap 7 — mu-plugins plaatsen uit migration/mu-plugins/ (keuze Cas 24 aug:
# alle 8 — variation-threshold, variations-json-cache, checkout-ajax-fallback,
# de drie mail/AFAS-presentatie-plugins en de twee winkelmanager-plugins;
# Points-Pro-plugins van reseller bewust NIET). Idempotent: kopieert altijd
# de repo-versie eroverheen. Let op: een verse pull (rsync --delete) haalt
# ze weer weg — deze stap hoort dus in elke herhaal-reeks.
# ---------------------------------------------------------------------------
stap7() {
    controleer_config
    local bron="$REPO_ROOT/migration/mu-plugins"
    [[ -d "$bron" ]] || { echo "FOUT: $bron ontbreekt" >&2; exit 1; }

    if [[ "$TARGET" == "lokaal" ]]; then
        local content_dir
        content_dir=$(grep '^CONTENT_DIR=' "$MIGRATER_DIR/.env-defibsolutions" | cut -d= -f2)
        local doel="$MIGRATER_DIR/${content_dir#./}/mu-plugins"
        mkdir -p "$doel"
        cp "$bron"/*.php "$doel/"
        echo "--- controle:"
        ls "$doel"
    else
        ssh "$SERVER" "mkdir -p '$WP_ROOT/wp-content/mu-plugins'"
        scp -q "$bron"/*.php "$SERVER:$WP_ROOT/wp-content/mu-plugins/"
        echo "--- controle:"
        ssh "$SERVER" "ls '$WP_ROOT/wp-content/mu-plugins'"
    fi
    echo "OK — $(ls "$bron"/*.php | wc -l) mu-plugins geplaatst op $(doel_naam)"
}

# ---------------------------------------------------------------------------
# Stap 8 — Structuur-opruiming (akkoord Cas 24 aug): losse simple products
# waarvan het gekoppelde AFAS-artikel een variatie hoort te zijn (artikel
# heeft artikelcode_parent in wp_lef_afas_artikelen) gaan naar de prullenbak,
# met SKU en _afas_artikelnummer gestript zodat ze nooit meer matchen. De
# artikelen-sync maakt/behoudt daarna de variatie onder de familie-container.
# Dekt beide gevallen: duplicaat (variatie bestaat al) en conversie (nog niet).
# Default dry-run; `stap8 apply` voert uit. Draai hierna de WC-sync opnieuw.
# ---------------------------------------------------------------------------
stap8() {
    controleer_config
    local apply="${1:-}"

    cat > /tmp/afas-structuur-payload.php <<'PHP'
<?php
$apply = APPLY_PLACEHOLDER;
global $wpdb;
// Volgorde-guard: zonder gevulde artikelen-tabel (stap11/artikelen-sync
// eerst!) ziet deze stap niets en doet hij stilletjes te weinig.
$n_tabel = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lef_afas_artikelen");
if ($n_tabel === 0) {
    echo "FOUT: wp_lef_afas_artikelen is leeg — draai eerst de artikelen-sync (stap11).\n";
    exit(1);
}
$rows = $wpdb->get_results("
    SELECT p.ID, p.post_title, an.meta_value AS artikelnummer,
           COALESCE(sku.meta_value, '') AS sku,
           COALESCE(a.artikelcode_parent, '') AS artikelcode_parent
      FROM {$wpdb->posts} p
      JOIN {$wpdb->postmeta} an ON an.post_id = p.ID AND an.meta_key = '_afas_artikelnummer'
 LEFT JOIN {$wpdb->prefix}lef_afas_artikelen a
           ON a.artikelnummer COLLATE utf8mb4_unicode_520_ci = an.meta_value
 LEFT JOIN {$wpdb->postmeta} sku ON sku.post_id = p.ID AND sku.meta_key = '_sku'
     WHERE p.post_type = 'product'
       AND p.post_status IN ('publish', 'private')
     ORDER BY p.ID", ARRAY_A);

$n = 0;
foreach ($rows as $r) {
    $product = wc_get_product((int) $r['ID']);
    if ($product && $product->is_type('variable')) { continue; }
    $variatieId = 0;
    $ids = get_posts(['post_type' => 'product_variation', 'post_status' => ['publish', 'private'],
        'meta_key' => '_afas_artikelnummer', 'meta_value' => $r['artikelnummer'],
        'fields' => 'ids', 'numberposts' => 1, 'suppress_filters' => true]);
    if (!empty($ids)) { $variatieId = (int) $ids[0]; }
    // trash alleen: duplicaat (variatie bestaat al) of conversie (artikel
    // hoort volgens de sync-tabel een variatie te zijn); anders overslaan
    if ($variatieId === 0 && $r['artikelcode_parent'] === '') { continue; }
    $soort = $variatieId ? "DUPLICAAT (variatie #$variatieId bestaat al)" : 'CONVERSIE (sync maakt variatie)';
    // slug meeloggen: getrashte pagina's kunnen in menu's/links hangen —
    // de CSV-regel hieronder is de bron voor redirect-/menu-herstel
    printf("%s  #%d sku=%s art=%s parent=%s slug=%s — %s [%s]\n",
        $apply ? 'PRULLENBAK' : 'ZOU TRASHEN', (int) $r['ID'], $r['sku'] ?: '-',
        $r['artikelnummer'], $r['artikelcode_parent'],
        get_post_field('post_name', (int) $r['ID']), $r['post_title'], $soort);
    if ($apply) {
        delete_post_meta((int) $r['ID'], '_afas_artikelnummer');
        if ($r['sku'] !== '') { update_post_meta((int) $r['ID'], '_sku', ''); }
        wp_trash_post((int) $r['ID']);
    }
    $n++;
}
// Dubbele variaties: twee product_variations met hetzelfde _afas_artikelnummer
// (oude WPML-suffix-variatie naast de nette). De variatie waarvan de SKU
// gelijk is aan het artikelnummer blijft; de andere(n) gaan naar de prullenbak.
$dubbel = $wpdb->get_results("
    SELECT an.meta_value AS artikelnummer, GROUP_CONCAT(p.ID ORDER BY p.ID) AS ids
      FROM {$wpdb->posts} p
      JOIN {$wpdb->postmeta} an ON an.post_id = p.ID AND an.meta_key = '_afas_artikelnummer'
     WHERE p.post_type = 'product_variation' AND p.post_status IN ('publish', 'private')
  GROUP BY an.meta_value HAVING COUNT(*) > 1", ARRAY_A);

$d = 0;
foreach ($dubbel as $grp) {
    $ids = array_map('intval', explode(',', $grp['ids']));
    $art = (string) $grp['artikelnummer'];
    $houd = 0;
    foreach ($ids as $id) {
        if ((string) get_post_meta($id, '_sku', true) === $art) { $houd = $id; break; }
    }
    if ($houd === 0) {
        printf("OVERSLAAN  dubbele variaties voor %s (%s): geen met SKU==artikelnummer, handmatig kiezen\n",
            $art, implode(', ', $ids));
        continue;
    }
    foreach ($ids as $id) {
        if ($id === $houd) { continue; }
        printf("%s  variatie #%d sku=%s art=%s — dubbel, #%d blijft\n",
            $apply ? 'PRULLENBAK' : 'ZOU TRASHEN', $id,
            (string) get_post_meta($id, '_sku', true) ?: '-', $art, $houd);
        if ($apply) {
            delete_post_meta($id, '_afas_artikelnummer');
            update_post_meta($id, '_sku', '');
            wp_trash_post($id);
        }
        $d++;
    }
}
printf("--- %s: %d simples + %d dubbele variaties %s\n", $apply ? 'APPLY' : 'DRY-RUN', $n, $d,
    $apply ? 'naar prullenbak (SKU + koppeling gestript)' : 'zouden naar de prullenbak gaan');
PHP
    if [[ "$apply" == "apply" ]]; then
        sed -i 's/APPLY_PLACEHOLDER/true/' /tmp/afas-structuur-payload.php
    else
        sed -i 's/APPLY_PLACEHOLDER/false/' /tmp/afas-structuur-payload.php
    fi

    wpr_stdin eval-file - < /tmp/afas-structuur-payload.php
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — niets getrasht. Draai '$0 stap8 apply' om uit te voeren."
    fi
}

# ---------------------------------------------------------------------------
# Stap 9 — Swatches-instelling conform reseller. Met default_to_button/-image
# bouwt woo-variation-swatches élk custom attribuut (zoals "Naam" op de
# plugin-variaties) om tot ronde knopjes waar de lange samenstellingsnamen
# overheen elkaar vallen. Reseller toont daar een dropdown; dit spiegelt dat.
# Bestaande pa_-attributen (met eigen type radio/select) blijven ongemoeid.
# ---------------------------------------------------------------------------
stap9() {
    controleer_config
    wpr_stdin eval-file - <<'PHP'
<?php
$opt = get_option('woo_variation_swatches', []);
if (!is_array($opt)) { $opt = []; }
$voor = json_encode(['button' => $opt['default_to_button'] ?? null, 'image' => $opt['default_to_image'] ?? null]);
$opt['default_to_button'] = 'no';
$opt['default_to_image']  = 'no';
update_option('woo_variation_swatches', $opt);
delete_transient('woo_variation_swatches_cache');
printf("woo_variation_swatches: default_to_button/image -> no (was %s)\n", $voor);
PHP
    if [[ "$TARGET" == "lokaal" ]]; then
        # Divi's Dynamic/Critical CSS weigert lokaal te renderen: de resource-
        # administratie uit de live-dump wijst naar live-paden, waardoor de
        # thema-CSS ontbreekt en de site kaal oogt. Statische fallback aan.
        # Alleen lokaal — op de server kloppen de paden en mag het aan blijven.
        wpr_stdin eval-file - <<'PHP'
<?php
$o = get_option('et_divi');
if (is_array($o)) {
    $o['divi_dynamic_css'] = 'off';
    $o['divi_critical_css'] = 'off';
    update_option('et_divi', $o);
}
wp_cache_flush();
echo "Divi dynamic/critical CSS uit (lokale workaround)\n";
PHP
    fi
    echo "OK — weergave-instellingen gezet op $(doel_naam)"
}

# ---------------------------------------------------------------------------
# Stap 10 — Assortiment-schrappingen (besluit Kevin, mail 25 aug): de producten
# uit work/schraplijst-defibsolutions.csv gaan naar de prullenbak (11 zonder
# omzet in 12 mnd + 10189FR die NL niet verkoopt). SKU en koppeling worden
# gestript zodat niets ooit nog matcht. Default dry-run; `stap10 apply` voert uit.
# ---------------------------------------------------------------------------
stap10() {
    controleer_config
    local apply="${1:-}"
    local lijst="$REPO_ROOT/work/schraplijst-defibsolutions.csv"
    [[ -f "$lijst" ]] || { echo "FOUT: $lijst ontbreekt" >&2; exit 1; }

    python3 - "$lijst" "$apply" <<'PY' > /tmp/afas-schrap-payload.php
import csv, json, sys
lijst, apply = sys.argv[1], sys.argv[2] == "apply"
rijen = [(r["wc_id"].strip(), r["itemcode"].strip(), r["titel"].strip())
         for r in csv.DictReader(open(lijst, encoding="utf-8-sig"), delimiter=";")
         if r["wc_id"].strip().isdigit()]
print(f"// {len(rijen)} schrappingen uit {lijst}", file=sys.stderr)
print("<?php")
print(f"$apply = {'true' if apply else 'false'};")
print(f"$lijst = json_decode('{json.dumps(rijen)}', true);")
print("""
$weg = $al = 0;
foreach ($lijst as [$pid, $code, $titel]) {
    $post = get_post((int) $pid);
    if (!$post || $post->post_status === 'trash') { $al++; continue; }
    printf("%s  #%d [%s] %s\\n", $apply ? 'PRULLENBAK' : 'ZOU TRASHEN', (int) $pid, $code, $titel);
    if ($apply) {
        delete_post_meta((int) $pid, '_afas_artikelnummer');
        update_post_meta((int) $pid, '_sku', '');
        wp_trash_post((int) $pid);
    }
    $weg++;
}
printf("--- %s: %d geschrapt, %d stonden al in de prullenbak/bestaan niet\\n",
    $apply ? 'APPLY' : 'DRY-RUN', $weg, $al);

// Concepten (besluit Cas 25 aug): alle draft-producten naar de prullenbak.
// Bewust ZONDER SKU/meta-strip — drafts (o.a. Prestan) blijven zo herstelbaar;
// de sync negeert prullenbak-posts vanzelf.
$drafts = get_posts(['post_type' => ['product', 'product_variation'],
    'post_status' => 'draft', 'fields' => 'ids', 'numberposts' => -1,
    'suppress_filters' => true]);
foreach ($drafts as $did) {
    printf("%s  concept #%d — %s\\n", $apply ? 'PRULLENBAK' : 'ZOU TRASHEN',
        (int) $did, get_the_title($did));
    if ($apply) { wp_trash_post((int) $did); }
}
printf("--- %s: %d concepten\\n", $apply ? 'APPLY' : 'DRY-RUN', count($drafts));
""")
PY

    wpr_stdin eval-file - < /tmp/afas-schrap-payload.php
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — niets geschrapt. Draai '$0 stap10 apply' om uit te voeren."
    fi
}

# ---------------------------------------------------------------------------
# Stap 11 — Syncs draaien: plugin-migraties, artikelen (AFAS → tabel),
# prijslijsten + prijzen, en 2× de WooCommerce-sync (run 2 is het vangnet
# voor variaties wier container pas in run 1 ontstond). Print per fase
# voortgang en eindigt met de warning-telling.
# Volledige herbouw-volgorde: stap1..7, 9, 10 → stap11 → stap8 apply → stap11.
# ---------------------------------------------------------------------------
stap11() {
    controleer_config
    # opties (combineerbaar):
    #   zonder-prijzen  slaat de prijs-/relatie-import over (herhaal-runs)
    #   delta           wc-sync zonder force: alleen gewijzigde artikelen
    #                   (seconden i.p.v. ~10 min; gebruik na kleine correcties)
    local opties="${*:-}"
    local zonder_prijzen=""; [[ "$opties" == *zonder-prijzen* ]] && zonder_prijzen="zonder-prijzen"
    cat > /tmp/afas-stap11-payload.php <<'PHP'
<?php
$zonderPrijzen = PRIJZEN_PLACEHOLDER;
$force = FORCE_PLACEHOLDER;   // false = delta-sync (alleen gewijzigde rijen)
$t0 = microtime(true);
$fase = function (string $m) use ($t0) {
    printf("[%5.1fs] %s\n", microtime(true) - $t0, $m);
    flush(); // live voortgang in logs/terminal i.p.v. alles aan het einde
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
    do_action('afas_sync_addresses', true);
    printf("         relaties: %d, kortingen: %d\n",
        (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lef_afas_verkooprelaties"),
        (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lef_afas_kortingen"));
}

$wpdb->query("DELETE FROM {$wpdb->prefix}lef_logs WHERE channel = 'woocommerce'");
$warnings = function () use ($wpdb): int {
    return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lef_logs
        WHERE channel = 'woocommerce' AND level = 'warning'");
};
// term-hertellingen uitstellen: anders hertelt elke productsave alle
// categorie-/attribuuttellers (honderden keren); nu één keer aan het eind
wp_defer_term_counting(true);
$fase($force ? 'wc-sync run 1 (force: alle productsaves, paar minuten)'
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
    if [[ "$zonder_prijzen" == "zonder-prijzen" ]]; then
        sed -i 's/PRIJZEN_PLACEHOLDER/true/' /tmp/afas-stap11-payload.php
    else
        sed -i 's/PRIJZEN_PLACEHOLDER/false/' /tmp/afas-stap11-payload.php
    fi
    if [[ "$opties" == *delta* ]]; then
        sed -i 's/FORCE_PLACEHOLDER/false/' /tmp/afas-stap11-payload.php
    else
        sed -i 's/FORCE_PLACEHOLDER/true/' /tmp/afas-stap11-payload.php
    fi
    wpr_stdin eval-file - < /tmp/afas-stap11-payload.php
    echo "OK — syncs gedraaid op $(doel_naam)"
}

# ---------------------------------------------------------------------------
# Stap 12 — Variatie-assen op de AED-containers, conform reseller.
# De plugin kent alleen het platte attribuut "Naam"; reseller toont drie
# globale attributen: pa_taal, pa_connectiviteit, pa_opties. Die worden hier
# afgeleid uit de tool-data (samenstellingen-manager-snapshot):
#   taal           <- group_bases.language_code  ("NL/EN/FR" -> "Nederlands · Engels · Frans")
#   connectiviteit <- group_bases.variant_label  (leeg -> "Geen", WiFi -> "Wi-Fi", ...)
#   opties         <- accessoire van de variant   (geen accessoire -> "Defibrillator")
# Een as wordt variatie-attribuut bij >1 waarde, anders een vast attribuut.
# Het "Naam"-attribuut wordt verwijderd (besluit Cas 25 aug).
# Default dry-run; `stap12 apply` schrijft. Draai na stap11.
# ---------------------------------------------------------------------------
stap12() {
    controleer_config
    local apply="${1:-}"
    local snapshot="$REPO_ROOT/tmp/samenstellingen.sqlite"
    [[ -f "$snapshot" ]] || { echo "FOUT: $snapshot ontbreekt (tool-snapshot nodig)" >&2; exit 1; }

    # shop-dump: variable containers + hun variaties met artikelnummer
    local dump="$REPO_ROOT/tmp/defibs-varianten-dump.tsv"
    wpr db query "\"SELECT par.ID, COALESCE(pan.meta_value,''), v.ID, COALESCE(van.meta_value,'')
        FROM wp_posts par
        JOIN wp_term_relationships tr ON tr.object_id = par.ID
        JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_type'
        JOIN wp_terms t ON t.term_id = tt.term_id AND t.name = 'variable'
        LEFT JOIN wp_postmeta pan ON pan.post_id = par.ID AND pan.meta_key = '_afas_artikelnummer'
        JOIN wp_posts v ON v.post_parent = par.ID AND v.post_type = 'product_variation'
             AND v.post_status IN ('publish','private')
        LEFT JOIN wp_postmeta van ON van.post_id = v.ID AND van.meta_key = '_afas_artikelnummer'
        WHERE par.post_status IN ('publish','private')\"" --skip-column-names > "$dump"

    python3 - "$snapshot" "$dump" "$apply" <<'PY' > /tmp/afas-assen-payload.php
import json, sqlite3, sys
snapshot, dump, apply = sys.argv[1], sys.argv[2], sys.argv[3] == "apply"

TALEN = {"NL": "Nederlands", "EN": "Engels", "FR": "Frans", "DE": "Duits", "ES": "Spaans",
         "IT": "Italiaans", "DK": "Deens", "NO": "Noors", "SE": "Zweeds", "FI": "Finnish",
         "PL": "Pools", "CZ": "Tsjechisch", "SK": "Slowaaks", "SL": "Sloveens",
         "HU": "Hongaars", "HR": "Kroatisch", "EL": "Grieks", "GA": "Iers",
         "PT": "Portugees", "LV": "Lets", "LT": "Litouws", "RO": "Roemeens",
         "TR": "Turks", "CH": "Zwitserse Editie"}
CONNECT = {"": "Geen", "USB": "USB", "WiFi": "Wi-Fi", "SIGFOX": "Sigfox", "4G": "4G",
           "GPS+WiFi+SIGFOX": "GPS+Wi-Fi+SIGFOX"}
# accessoire-itemcode -> reseller-term (naamdrift op reseller zelf bij 60212/60213:
# tool-naam is hier canoniek)
OPTIES = {"60110": "EHBO Rugzak", "60112": "ARKY Binnenkast Wit",
          "60122": "ARKY Binnenkast Groen", "60212": "ARKY Buitenkast Onverwarmd",
          "60213": "ARKY Buitenkast Verwarmd", "60222": "ARKY CORE Classic",
          "60223": "ARKY CORE Plus", "91116": "Defibtech draagtas",
          "10432": "Mindray Draagtas"}

con = sqlite3.connect(snapshot)
# itemcode -> (language_code, variant_label, accessoire_itemcode|None)
info = {}
for code, taal, label in con.execute(
        "SELECT afas_itemcode, COALESCE(language_code,''), COALESCE(variant_label,'')"
        " FROM group_bases WHERE afas_itemcode IS NOT NULL AND afas_itemcode <> ''"):
    info[code] = (taal, label, None)
for code, taal, label, acc in con.execute(
        "SELECT v.afas_samenstelling_itemcode, COALESCE(b.language_code,''),"
        "       COALESCE(b.variant_label,''), a.itemcode"
        "  FROM group_variants v"
        "  JOIN group_bases b ON b.id = v.base_id"
        "  LEFT JOIN accessoires a ON a.id = v.accessoire_id"
        " WHERE v.afas_samenstelling_itemcode IS NOT NULL AND v.afas_samenstelling_itemcode <> ''"):
    info[code] = (taal, label, acc)
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
    taal, label, acc = rij
    containers.setdefault(par_id, {"code": par_code, "varianten": {}})
    containers[par_id]["varianten"][var_id] = {
        "Taal": taal_naam(taal),
        "Connectiviteit": CONNECT.get(label, label or "Geen"),
        "Opties": OPTIES.get(acc, "Defibrillator") if acc else "Defibrillator",
    }

print(f"// {len(containers)} containers, {sum(len(c['varianten']) for c in containers.values())} variaties"
      f", {len(onbekend)} zonder tool-data", file=sys.stderr)
print("<?php")
print(f"$apply = {'true' if apply else 'false'};")
print(f"$containers = json_decode('{json.dumps(containers, ensure_ascii=False)}', true);")
print(r"""
global $wpdb;
$AS_TAX = ['Taal' => 'pa_taal', 'Connectiviteit' => 'pa_connectiviteit', 'Opties' => 'pa_opties'];
$gemaakt = $gezet = $overgeslagen = 0;

// Slug conform reseller: "Nederlands · Engels · Frans" -> nederlands-engels-frans
// (sanitize_title maakt van de middenstip anders %c2%b7).
$slugVan = function (string $naam): string {
    return sanitize_title(str_replace(['·', '+'], [' ', ' '], $naam));
};

foreach ($AS_TAX as $label => $tax) {
    if (taxonomy_exists($tax)) { continue; }
    if (!$apply) { echo "ZOU AANMAKEN attribuut: $label ($tax)\n"; continue; }
    $id = wc_create_attribute(['name' => $label, 'slug' => str_replace('pa_', '', $tax),
        'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false]);
    if (is_wp_error($id)) { echo "FOUT attribuut $label: " . $id->get_error_message() . "\n"; continue; }
    register_taxonomy($tax, 'product', ['hierarchical' => false, 'show_ui' => false, 'query_var' => true]);
    echo "attribuut aangemaakt: $label ($tax)\n";
}

foreach ($containers as $parId => $data) {
    $parent = wc_get_product((int) $parId);
    if (!$parent || !$parent->is_type('variable')) { $overgeslagen++; continue; }

    // waarden per as verzamelen
    $waarden = [];
    foreach ($data['varianten'] as $vid => $assen) {
        foreach ($assen as $label => $waarde) {
            if ($waarde !== '') { $waarden[$label][$waarde] = true; }
        }
    }
    if (empty($waarden)) { $overgeslagen++; continue; }

    // Bestaande attributen behouden (pa_merk, pa_display, ... blijven staan als
    // informatie/filter). Alleen het platte "Naam" verdwijnt; andere attributen
    // die nu variatie-as zijn worden vast, anders zijn variaties incompleet.
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
            if ($term && $term->slug !== $slugVan($naam) && $apply) {
                // slug herstellen (eerdere runs maakten %c2%b7-slugs)
                wp_update_term($term->term_id, $tax, ['slug' => $slugVan($naam)]);
                $term = get_term($term->term_id, $tax);
            }
            if (!$term) {
                if (!$apply) { continue; }  // dry-run schrijft nooit
                $res = wp_insert_term($naam, $tax, ['slug' => $slugVan($naam)]);
                if (is_wp_error($res)) { echo "FOUT term '$naam' in $tax: " . $res->get_error_message() . "\n"; continue; }
                $term = get_term($res['term_id'], $tax);
                $gemaakt++;
            }
            $termIds[] = (int) $term->term_id;
        }
        if (!$termIds) { continue; }
        $attr = new WC_Product_Attribute();
        $attr->set_id(wc_attribute_taxonomy_id_by_name($tax));
        $attr->set_name($tax);
        $attr->set_options($termIds);
        $attr->set_position($positie++);
        $attr->set_visible(true);
        // as met >1 waarde = variatie-as; met 1 waarde = vast attribuut
        $attr->set_variation(count($termIds) > 1);
        $attributes[$tax] = $attr;
        if ($apply) { wp_set_object_terms((int) $parId, $termIds, $tax); }
    }

    $assenTekst = [];
    foreach ($AS_TAX as $tax) {
        if (!isset($attributes[$tax])) { continue; }
        $assenTekst[] = str_replace('pa_', '', $tax) . '=' . count($attributes[$tax]->get_options())
            . ($attributes[$tax]->get_variation() ? '' : ' (vast)');
    }
    $behouden = array_diff(array_keys($attributes), array_values($AS_TAX));
    printf("%s  container #%d [%s]: %s | behouden: %s\n", $apply ? 'GEZET' : 'ZOU ZETTEN',
        (int) $parId, $data['code'] ?: '-', implode(', ', $assenTekst),
        implode(', ', $behouden) ?: 'geen');

    if (!$apply) { continue; }

    $parent->set_attributes($attributes);   // "Naam" verdwijnt hiermee
    $parent->save();

    foreach ($data['varianten'] as $vid => $assen) {
        $variatie = wc_get_product((int) $vid);
        if (!$variatie) { continue; }
        // bestaande variatie-waarden van andere assen laten vallen (die assen
        // zijn nu vast), onze drie assen zetten waar ze variatie zijn
        $nieuw = [];
        foreach ($AS_TAX as $label => $tax) {
            if (!isset($attributes[$tax]) || !$attributes[$tax]->get_variation()) { continue; }
            $naam = $assen[$label] ?? '';
            if ($naam === '') { continue; }
            $term = get_term_by('name', $naam, $tax);
            if ($term) { $nieuw[$tax] = $term->slug; }
        }
        $variatie->set_attributes($nieuw);
        // Zodra een container "locked" is (eigen assen i.p.v. "Naam") laat de
        // plugin nieuwe variaties bewust als private binnenkomen, wachtend op
        // handmatige toewijzing. Wij kennen de assen wél, dus publiceren we ze
        // alsnog — mits AFAS het artikel actief noemt.
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

    wpr_stdin eval-file - < /tmp/afas-assen-payload.php
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — niets gewijzigd. Draai '$0 stap12 apply' om te schrijven."
    fi
}

# ---------------------------------------------------------------------------
usage() {
    echo "gebruik: [DEFIBS_TARGET=lokaal|cp01] $0 <stap>   (default: lokaal)"
    echo "stappen:"
    echo "  stap1   Mail UIT: disable-emails installeren + activeren (nieuwe server)"
    echo "  stap2   Overbodige plugins UIT: Jetpack + B2BKing deactiveren"
    echo "  stap3   Klanten koppelen aan AFAS-relaties uit work/klant-relatie-mapping.csv (dry-run; 'stap3 apply' schrijft)"
    echo "  stap4   lefcreative-afas-b2b installeren + activeren + afas-settings importeren (work/)"
    echo "  stap5   API-keys intrekken: WooCommerce REST-keys + application passwords (dry-run; 'stap5 apply' verwijdert)"
    echo "  stap6   Voorkoppeling: _afas_artikelnummer per product zetten via BHV-match + actielijst (dry-run; 'stap6 apply' schrijft)"
    echo "  stap7   mu-plugins plaatsen uit migration/mu-plugins/ (idempotent)"
    echo "  stap8   Structuur-opruiming: gekoppelde simples die variatie horen te zijn -> prullenbak (dry-run; 'stap8 apply')"
    echo "  stap9   Swatches-instelling conform reseller (custom attributen als dropdown)"
    echo "  stap10  Assortiment-schrappingen uit work/schraplijst-defibsolutions.csv (dry-run; 'stap10 apply')"
    echo "  stap12  Variatie-assen (pa_taal/pa_connectiviteit/pa_opties) op AED-containers, Naam eruit (dry-run; 'stap12 apply')"
    echo "  stap11  Syncs (opties: 'zonder-prijzen', 'delta' = alleen gewijzigde artikelen, seconden i.p.v. minuten)"
    echo ""
    echo "volledige herbouw: stap1..7, 9, 10 apply -> stap11 -> stap8 apply -> stap11"
    exit 1
}

case "${1:-}" in
    stap1) stap1 ;;
    stap2) stap2 ;;
    stap3) stap3 "${2:-}" ;;
    stap4) stap4 ;;
    stap5) stap5 "${2:-}" ;;
    stap6) stap6 "${2:-}" ;;
    stap7) stap7 ;;
    stap8) stap8 "${2:-}" ;;
    stap9) stap9 ;;
    stap10) stap10 "${2:-}" ;;
    stap11) stap11 "${2:-}" ;;
    stap12) stap12 "${2:-}" ;;
    *) usage ;;
esac
