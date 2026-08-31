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
# Stap 2 — Overbodige plugins UIT: Jetpack + B2BKing + Mailchimp + WP Staging.
# Jetpack hoort niet mee te draaien tijdens/na de migratie (externe koppelingen,
# mails, stats). B2BKing wordt vervangen door lefcreative-afas-b2b; de
# B2BKing-data blijft in de database staan als inerte fallback (zelfde aanpak
# als de wholesale-plugins bij ARKY). Alleen deactiveren; verwijderen kan in
# de eindschoonmaak.
# ---------------------------------------------------------------------------
stap2() {
    controleer_config
    local p
    # mailchimp-for-woocommerce: zet bij ELKE productsave jobs in
    # wp_mailchimp_jobs (lokaal opgelopen tot 245k rijen) waardoor de
    # artikelen-sync tot stilstand komt — en de kopie zou naar het echte
    # Mailchimp-account pushen. Uit tijdens de migratie; na de livegang
    # bewust weer aanzetten als marketing hem nodig heeft.
    # wp-staging-pro: staging/backup-tool van de oude hosting; nutteloos op de
    # kopie en op cp-01 (Hetzner/CloudPanel doet backups) en zo'n geheugenvreter
    # dat wp-cli zonder verhoogde memory_limit al bij het booten OOM't.
    for p in jetpack b2bking-wholesale-for-woocommerce b2bking mailchimp-for-woocommerce wp-staging-pro wp-staging; do
        if wpr plugin is-installed "$p" >/dev/null 2>&1; then
            wpr plugin deactivate "$p"
        else
            echo "$p is niet geïnstalleerd op $(doel_naam) — overslaan"
        fi
    done
    echo "--- controle:"
    wpr plugin list | { grep -iE 'jetpack|b2bking|mailchimp|wp-staging' || echo "(niets gevonden)"; }
    # opgelopen job-wachtrij legen: die maakt elke latere save traag
    wpr db query "\"DELETE FROM wp_mailchimp_jobs\"" >/dev/null 2>&1 || true
    echo "OK — jetpack + b2bking + mailchimp + wp-staging staan uit op $(doel_naam)"
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

    # Veiligheidsgordel: order-push naar AFAS staat overal geforceerd uit —
    # lokaal altijd, en op cp-01 zolang we niet live zijn (bij de livegang
    # zet Cas hem bewust handmatig aan, samen met de order-vrije-velden).
    wpr option update afas_sync_orders_enabled 0 >/dev/null
    echo "(afas_sync_orders_enabled geforceerd op 0 — bij livegang handmatig aan)"
    # Plugin-cron temmen: de sync-jobs draaien elk uur en raceten op cp-01
    # dwars door het migratievenster heen (bewezen 27 aug: cron-delta maakte
    # een geschrapt artikel opnieuw aan). Intervallen op een week; bij de
    # livegang zet Cas ze handmatig terug (zelfde slotlijstje als orders aan).
    local i
    for i in artikelen prijslijsten verkooprelaties kortingen prijzen woocommerce addresses; do
        wpr option update "afas_sync_${i}_interval" 604800 >/dev/null
    done
    echo "(sync-cron-intervallen op 1 week — bij livegang handmatig terugzetten)"
    if [[ "$TARGET" == "lokaal" ]]; then
        # Testklant voor checkout-tests: user 187 (AEDcompany, relatie 31148).
        # Het wachtwoord komt niet mee uit de live-dump, dus na elke verse
        # pull opnieuw zetten. Alleen lokaal — nooit live-wachtwoorden muteren.
        if wpr user get 187 --field=ID >/dev/null 2>&1; then
            wpr user update 187 --user_pass=defibs-test-2026 >/dev/null
            echo "(lokaal: testklant AEDcompany/187 wachtwoord gezet: defibs-test-2026)"
        fi
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
// Exact de reseller-configuratie (daar staan shape/default-keys niet gezet,
// dus gelden de plugin-defaults: alle select-assen als vierkante knoppen).
// Eerdere fix zette default_to_button=no ("rondjes weg") maar dat schakelde
// de knop-weergave uit; de rondjes kwamen van shape_style=rounded.
$reseller_conform = [
    'clear_on_reselect' => 'no',
    'hide_out_of_stock_variation' => 'yes',
    'clickable_out_of_stock_variation' => 'no',
    'attribute_behavior' => 'blur-no-cross',
    'attribute_image_size' => 'variation_swatches_image_size',
];
update_option('woo_variation_swatches', $reseller_conform);
delete_transient('woo_variation_swatches_cache');
echo "woo_variation_swatches: exact reseller-conform gezet (knoppen aan, squared)\n";
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
    # Checkout-veldinstellingen conform reseller (WooCommerce-customizer):
    # bedrijfsnaam verbergen, telefoon en adresregel 2 optioneel. Zonder deze
    # opties is telefoon verplicht (WC-default) en stond bedrijfsnaam op
    # required — B2B-klanten liepen daarop vast.
    wpr option update woocommerce_checkout_company_field hidden >/dev/null
    wpr option update woocommerce_checkout_phone_field optional >/dev/null
    wpr option update woocommerce_checkout_address_2_field optional >/dev/null
    echo "checkout-velden: bedrijfsnaam hidden, telefoon/adres2 optional (conform reseller)"
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
    // wc_id's van sync-aangemaakte posts verschillen per omgeving (lokaal vs
    // cp-01 vs live): zoek als vangnet ook op artikelcode, zodat dezelfde
    // CSV overal werkt en een cron-race-artefact (bv. 30140 op cp-01) ook
    // zonder het juiste id wordt opgeruimd.
    if ((!$post || $post->post_status === 'trash') && $code !== '') {
        global $wpdb;
        // Nooit via het vangnet schrappen wat nog in AFAS-beheer is: een
        // artikel in wp_lef_afas_artikelen is (weer) legitiem — een oude
        // schraplijst-rij mag dat niet alsnog omleggen (les: 70202/602505
        // sneuvelden op cp-01 door achterhaalde rijen).
        $inBeheer = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}lef_afas_artikelen WHERE artikelnummer = %s", $code));
        if ($inBeheer === 0) {
            $anders = $wpdb->get_col($wpdb->prepare(
                "SELECT pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key = '_afas_artikelnummer' AND pm.meta_value = %s
                   AND p.post_type IN ('product','product_variation') AND p.post_status <> 'trash'", $code));
            if ($anders) { $post = get_post((int) $anders[0]); $pid = (int) $anders[0]; }
        }
    }
    if (!$post || $post->post_status === 'trash') { $al++; continue; }
    printf("%s  #%d [%s] %s\\n", $apply ? 'PRULLENBAK' : 'ZOU TRASHEN', (int) $pid, $code, $titel);
    if ($apply) {
        delete_post_meta((int) $pid, '_afas_artikelnummer');
        update_post_meta((int) $pid, '_sku', '');
        // ook de lookup-tabel: daar draait WC's unieke-SKU-check op; een kale
        // meta-update ververst die niet en een achterblijvende rij blokkeert
        // toekomstige syncs (les van de 11151-botsing)
        global $wpdb;
        $wpdb->update($wpdb->prefix . 'wc_product_meta_lookup', ['sku' => ''], ['product_id' => (int) $pid]);
        wp_trash_post((int) $pid);
        // WC trasht variaties van een parent mee, maar dan zonder strip:
        // kinderen expliciet ontdoen van sku + koppeling
        foreach (get_children(['post_parent' => (int) $pid, 'post_type' => 'product_variation', 'fields' => 'ids']) as $kind) {
            delete_post_meta((int) $kind, '_afas_artikelnummer');
            update_post_meta((int) $kind, '_sku', '');
            $wpdb->update($wpdb->prefix . 'wc_product_meta_lookup', ['sku' => ''], ['product_id' => (int) $kind]);
        }
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
# variant_label kan ook een CPR-sensor-uitvoering zijn (G5, slice G5F): dan is
# het geen connectiviteit maar een eigen as. Container-default = de niet-F-base.
CPR = {"met CPR-sensor": "Met", "zonder CPR-sensor": "Zonder"}
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
    containers.setdefault(par_id, {"code": par_code, "varianten": {}})
    containers[par_id]["varianten"][var_id] = {
        "Taal": taal_naam(taal),
        "Connectiviteit": "Geen" if cpr else CONNECT.get(label, label or "Geen"),
        "CPR-feedback": cpr,
        "Opties": OPTIES.get(acc, "Defibrillator") if acc else "Defibrillator",
    }
    # default-uitvoering: de al bestaande (niet-F) base van deze container
    if cpr and not basecode.endswith("F"):
        containers[par_id]["cpr_default"] = cpr

print(f"// {len(containers)} containers, {sum(len(c['varianten']) for c in containers.values())} variaties"
      f", {len(onbekend)} zonder tool-data", file=sys.stderr)
print("<?php")
print(f"$apply = {'true' if apply else 'false'};")
print(f"$containers = json_decode('{json.dumps(containers, ensure_ascii=False)}', true);")
print(r"""
global $wpdb;
$AS_TAX = ['Taal' => 'pa_taal', 'Connectiviteit' => 'pa_connectiviteit',
    'CPR-feedback' => 'pa_cpr-feedback', 'Opties' => 'pa_opties'];
$gemaakt = $gezet = $overgeslagen = 0;

// Slug conform reseller: "Nederlands · Engels · Frans" -> nederlands-engels-frans
// (sanitize_title maakt van de middenstip anders %c2%b7).
$slugVan = function (string $naam): string {
    return sanitize_title(str_replace(['·', '+'], [' ', ' '], $naam));
};

// Canonieke optie-volgorde (reseller heeft geen expliciete volgorde: daar
// staat Defibrillator 10e door aanmaakvolgorde). Hier wél gecureerd, zodat de
// dropdown logisch loopt: kaal toestel eerst, dan accessoires oplopend.
$VOLGORDE = [
    'pa_taal' => ['Nederlands'],
    'pa_connectiviteit' => ['Geen', 'USB', 'Wi-Fi', '4G', 'Sigfox', 'GPS+Wi-Fi+SIGFOX'],
    'pa_cpr-feedback' => ['Met', 'Zonder'],
    'pa_opties' => ['Defibrillator', 'EHBO Rugzak', 'ARKY Binnenkast Wit',
        'ARKY Binnenkast Groen', 'ARKY Binnenkast Groen ILCOR',
        'ARKY Buitenkast Onverwarmd', 'ARKY Buitenkast Verwarmd',
        'ARKY Buitenkast Verwarmd Groen', 'ARKY Buitenkast Groen',
        'ARKY CORE Classic', 'ARKY CORE Plus', 'Defibtech draagtas',
        'Mindray Draagtas', 'Plexiglas Wandbeugel'],
];
// Default-keuze per as, conform reseller: het kale NL-toestel zonder opties.
$DEFAULT_VOORKEUR = ['pa_taal' => 'Nederlands', 'pa_connectiviteit' => 'Geen',
    'pa_opties' => 'Defibrillator'];
// Container-titels zonder taal-/CPR-aanduiding (akkoord Cas 27 aug): taal en
// CPR-uitvoering zijn keuze-assen en horen niet in de titel. Map op de
// artikelcode van de container; slugs blijven ongemoeid (geen kapotte links).
$TITELS = [
    '11043'    => 'Defibtech View AED',
    '11149'    => 'Cardiac Science Powerheart G5 volautomaat',
    '11148'    => 'Cardiac Science Powerheart G5 halfautomaat',
    '21018-UK' => 'Mindray C1A V2 Semi Automaat',
    '21019-UK' => 'Mindray Beneheart C1A V2 Volautomaat',
    '11139'    => 'Defibtech Lifeline View AED Volautomaat',
    '10698'    => 'Zoll AED Plus Trainer',
];

foreach ($AS_TAX as $label => $tax) {
    if (taxonomy_exists($tax)) { continue; }
    if (!$apply) { echo "ZOU AANMAKEN attribuut: $label ($tax)\n"; continue; }
    $id = wc_create_attribute(['name' => $label, 'slug' => str_replace('pa_', '', $tax),
        'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false]);
    if (is_wp_error($id)) { echo "FOUT attribuut $label: " . $id->get_error_message() . "\n"; continue; }
    register_taxonomy($tax, 'product', ['hierarchical' => false, 'show_ui' => false, 'query_var' => true]);
    echo "attribuut aangemaakt: $label ($tax)\n";
}

// pa_taal toont op reseller knoppen i.p.v. een radiolijst
if ($apply) {
    $wpdb->update($wpdb->prefix . 'woocommerce_attribute_taxonomies',
        ['attribute_type' => 'button'], ['attribute_name' => 'taal']);
    delete_transient('wc_attribute_taxonomies');
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
        // volgorde-meta: WooCommerce >= 3.6 sorteert menu_order-attributen op
        // termmeta 'order' (wc_terms_clauses), NIET meer op het oude
        // 'order_<taxonomie>' — die legacy-key werd hier eerst geschreven en
        // stil genegeerd (Kevins K5: kasten vóór "Defibrillator").
        if ($apply) {
            foreach ($termIds as $tid) {
                $naam = get_term($tid, $tax)->name ?? '';
                $pos = array_search($naam, $VOLGORDE[$tax] ?? [], true);
                if ($pos === false) {
                    // niet in de lijst: achteraan, alfabetisch stabiel
                    $pos = 100 + (ord(substr($naam, 0, 1)) - 65);
                }
                update_term_meta($tid, 'order', (int) $pos);
            }
        }
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

    // titel-normalisatie (alleen containers uit de $TITELS-map)
    $gewensteTitel = $TITELS[$data['code']] ?? null;
    if ($gewensteTitel !== null && $parent->get_name() !== $gewensteTitel) {
        wp_update_post(['ID' => (int) $parId, 'post_title' => $gewensteTitel]);
        printf("         titel: '%s'\n", $gewensteTitel);
    }

    $parent->set_attributes($attributes);   // "Naam" verdwijnt hiermee

    // Default-variatie kiezen (conform reseller: kaal NL-toestel). Per as de
    // voorkeurswaarde, anders de eerste die de voorkeur bevat (bv.
    // "Nederlands · Engels · Frans" als los "Nederlands" niet bestaat).
    $defaults = [];
    foreach ($AS_TAX as $label => $tax) {
        if (!isset($attributes[$tax]) || !$attributes[$tax]->get_variation()) { continue; }
        // CPR-as: default per container = de uitvoering van de al bestaande
        // (niet-F) base; overige assen hebben een globale voorkeur.
        $voorkeur = $tax === 'pa_cpr-feedback'
            ? ($data['cpr_default'] ?? 'Met')
            : ($DEFAULT_VOORKEUR[$tax] ?? '');
        $namen = array_keys($waarden[$label]);
        sort($namen);
        $keuze = null;
        foreach ($namen as $n) { if ($n === $voorkeur) { $keuze = $n; break; } }
        if ($keuze === null && $voorkeur !== '') {
            // eerst namen die met de voorkeur BEGINNEN ("Nederlands · Engels ·
            // Frans"), pas daarna namen die hem alleen bevatten — anders wint
            // alfabetisch bv. "Frans · Engels · Nederlands"
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
    # Losse taalcontainers buiten de samenstellingen-scope (plugin-gemaakte
    # taal-families zonder samenstelling). Zelfde behandeling als de AED-
    # containers: Naam-as eruit, pa_taal-as erin, titel zonder taal-suffix,
    # default Nederlands. Nu alleen de Zoll AED Plus Trainer (head 10698,
    # verhangen 31 aug — zie afas-connector-tools/bin/verhang-trainer-head.php).
    wpr_stdin eval-file - "$apply" <<'PHP'
<?php
$apply = ('apply' === ($args[0] ?? ''));
$LOSSE_TAAL = [
    // opmaak_van: het oude eigen (door stap8 getrashte) product — live-ID,
    // stabiel over pulls. Content/afbeelding/slug worden daarvan overgenomen
    // zolang de nieuwe container nog geen hoofdafbeelding heeft.
    '10698' => ['titel' => 'Zoll AED Plus Trainer', 'opmaak_van' => 99946, 'varianten' => [
        '10691' => 'Nederlands', '10698' => 'Engels',
    ]],
];
foreach ($LOSSE_TAAL as $headCode => $cfg) {
    $ids = get_posts(['post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 1,
        'fields' => 'ids', 'meta_key' => '_afas_artikelnummer', 'meta_value' => $headCode]);
    if (!$ids) { echo "losse taalcontainer $headCode: niet gevonden, overslaan\n"; continue; }
    $parId = (int) $ids[0];
    $parent = wc_get_product($parId);
    $termIds = [];
    $variaties = [];
    foreach ($cfg['varianten'] as $code => $taalNaam) {
        $term = get_term_by('name', $taalNaam, 'pa_taal');
        if (!$term) { echo "  taalterm '$taalNaam' ontbreekt, overslaan\n"; continue 2; }
        $termIds[$code] = (int) $term->term_id;
        $v = get_posts(['post_type' => 'product_variation', 'post_status' => ['publish', 'private'],
            'numberposts' => 1, 'fields' => 'ids', 'post_parent' => $parId,
            'meta_key' => '_afas_artikelnummer', 'meta_value' => $code]);
        if ($v) { $variaties[$code] = (int) $v[0]; }
    }
    printf("losse taalcontainer #%d [%s]: %d taalvariaties%s\n", $parId, $headCode,
        count($variaties), $apply ? '' : ' (dry-run)');
    if (!$apply) { continue; }
    // parent: pa_taal als variatie-as, Naam-as weg, titel + default
    $attr = new WC_Product_Attribute();
    $attr->set_id(wc_attribute_taxonomy_id_by_name('pa_taal'));
    $attr->set_name('pa_taal');
    $attr->set_options(array_values($termIds));
    $attr->set_position(0);
    $attr->set_visible(true);
    $attr->set_variation(true);
    $parent->set_attributes(['pa_taal' => $attr]);
    $parent->set_default_attributes(['pa_taal' => 'nederlands']);
    if ($parent->get_name() !== $cfg['titel']) { $parent->set_name($cfg['titel']); }
    $parent->save();
    wp_set_object_terms($parId, array_values($termIds), 'pa_taal');
    foreach ($cfg['varianten'] as $code => $taalNaam) {
        if (!isset($variaties[$code])) { continue; }
        $vid = $variaties[$code];
        $slug = get_term($termIds[$code], 'pa_taal')->slug;
        update_post_meta($vid, 'attribute_pa_taal', $slug);
        delete_post_meta($vid, 'attribute_naam');
        wp_update_post(['ID' => $vid, 'post_title' => $cfg['titel'] . ' - ' . $taalNaam]);
    }
    echo "  gezet: pa_taal-as, titel '{$cfg['titel']}', default Nederlands, Naam-as weg\n";
    // Opmaak van de oude eigen tegenhanger (staat in de prullenbak, media
    // hangt daar nog aan als losse attachments — hergebruik per ID).
    if (!empty($cfg['opmaak_van']) && !get_post_meta($parId, '_thumbnail_id', true)) {
        $bron = get_post((int) $cfg['opmaak_van']);
        if (!$bron || $bron->post_type !== 'product') {
            echo "  opmaak-bron #{$cfg['opmaak_van']} niet bruikbaar (weg of omgebouwd) - overgeslagen\n";
        } else {
            wp_update_post(['ID' => $parId,
                'post_content' => $bron->post_content,
                'post_excerpt' => $bron->post_excerpt,
            ]);
            foreach (['_thumbnail_id', '_product_image_gallery'] as $mk) {
                $mv = get_post_meta($bron->ID, $mk, true);
                if ($mv !== '') { update_post_meta($parId, $mk, $mv); }
            }
            $slug = preg_replace('/__trashed(-\d+)?$/', '', $bron->post_name);
            if ($slug !== '' && get_post_field('post_name', $parId) !== $slug) {
                wp_update_post(['ID' => $parId, 'post_name' => $slug]);
            }
            printf("  opmaak overgenomen van #%d (tekst %d tekens, thumb %s, slug '%s')\n",
                $bron->ID, strlen($bron->post_content),
                get_post_meta($bron->ID, '_thumbnail_id', true) ?: '-',
                get_post_field('post_name', $parId));
        }
    }
}
PHP
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — niets gewijzigd. Draai '$0 stap12 apply' om te schrijven."
    fi
}

# ---------------------------------------------------------------------------
# Stap 13 — Opmaak-overname van reseller voor kale producten (besluit Cas
# 26 aug). Selectie: publish-producten mét AFAS-koppeling maar zónder
# hoofdafbeelding (de door de sync nieuw aangemaakte "-wpbase"-producten).
# Bron: de lokale reseller-kopie (DB + uploads). Per product wordt van de
# reseller-tegenhanger (product met zelfde _afas_artikelnummer) overgenomen:
# titel, slug, beschrijving, korte beschrijving, categorieën (incl.
# hiërarchie), tags, hoofdafbeelding + galerij (bestanden + alt-teksten).
# Titels/slugs alleen hier — de wc-sync heeft update-naam uit staan, dus de
# plugin draait dit nooit terug. Default dry-run; `stap13 apply` voert uit.
# ---------------------------------------------------------------------------
stap13() {
    controleer_config
    local apply="${1:-}"
    local reseller_content="$MIGRATER_DIR/temp-reseller.defibrion.nl/wordpress/wp-content"
    [[ -d "$reseller_content/uploads" ]] || { echo "FOUT: reseller-kopie ontbreekt ($reseller_content)" >&2; exit 1; }
    local staging="$REPO_ROOT/tmp/opmaak-reseller"
    rm -rf "$staging"; mkdir -p "$staging/afbeeldingen"

    # 1. doelproducten van het target (werkt ook op cp01 via wpr)
    wpr db query "\"SELECT p.ID, an.meta_value, p.post_title
        FROM wp_posts p
        JOIN wp_postmeta an ON an.post_id=p.ID AND an.meta_key='_afas_artikelnummer' AND an.meta_value<>''
        WHERE p.post_type='product' AND p.post_status='publish'
          AND NOT EXISTS (SELECT 1 FROM wp_postmeta t WHERE t.post_id=p.ID
              AND t.meta_key='_thumbnail_id' AND t.meta_value<>'')\"" \
        --skip-column-names > "$staging/doelen.tsv"

    # 2. bron-data uit de reseller-kopie verzamelen (host-side; python praat
    #    rechtstreeks met de reseller-DB-container)
    python3 - "$staging" "$reseller_content" <<'PY' || exit 1
import json, os, shutil, subprocess, sys
staging, content = sys.argv[1], sys.argv[2]

def rq(sql):
    r = subprocess.run(["docker", "exec", "reseller-db-1", "mariadb", "-uwordpress",
                        "-pwordpress", "wordpress", "-B", "-N", "-e", sql],
                       capture_output=True, text=True)
    if r.returncode != 0:
        sys.exit(f"FOUT reseller-DB: {r.stderr.strip()[:200]}")
    # split alleen op \n: batch-mode escapet \n/\t in waarden, maar \r niet —
    # splitlines() zou een kolom met \r\n-content als extra rijen zien
    return [regel.split("\t") for regel in r.stdout.split("\n") if regel]

doelen = []
for regel in open(f"{staging}/doelen.tsv", encoding="utf-8"):
    d = regel.rstrip("\n").split("\t")
    if len(d) >= 2 and d[0].isdigit():
        doelen.append({"wc_id": int(d[0]), "art": d[1].strip(), "titel_nu": d[2] if len(d) > 2 else ""})
if not doelen:
    print("Geen kale producten gevonden — niets te doen.")
    json.dump([], open(f"{staging}/plan.json", "w")); sys.exit(0)

def esc(s): return s.replace("\\", "\\\\").replace("'", "\\'")

plan = []
for doel in doelen:
    art = doel["art"]
    # tekstvelden als HEX: de mariadb-client escapet newlines in -B-output
    # niet betrouwbaar, en post_content bevat die volop
    rows = rq(f"""SELECT p.ID, HEX(p.post_title), p.post_name, HEX(p.post_content), HEX(p.post_excerpt)
        FROM wp_postmeta an JOIN wp_posts p ON p.ID=an.post_id
        WHERE an.meta_key='_afas_artikelnummer' AND an.meta_value='{esc(art)}'
          AND p.post_type='product' AND p.post_status IN ('publish','private') LIMIT 1""")
    if not rows or len(rows[0]) < 5:
        plan.append({**doel, "status": "geen-reseller-match"}); continue
    onthex = lambda h: bytes.fromhex(h).decode("utf-8", "replace") if h and h != "NULL" else ""
    rid, slug = rows[0][0], rows[0][2]
    titel, inhoud, excerpt = onthex(rows[0][1]), onthex(rows[0][3]), onthex(rows[0][4])

    # categorieën met hiërarchie (pad van root naar blad) + tags
    cats = rq(f"""SELECT t.term_id, t.name, t.slug, tt.parent FROM wp_term_relationships tr
        JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id=tr.term_taxonomy_id AND tt.taxonomy='product_cat'
        JOIN wp_terms t ON t.term_id=tt.term_id WHERE tr.object_id={rid}""")
    boom = {}
    for tid, naam, tslug, parent in rq("""SELECT t.term_id, t.name, t.slug, tt.parent
        FROM wp_term_taxonomy tt JOIN wp_terms t ON t.term_id=tt.term_id WHERE tt.taxonomy='product_cat'"""):
        boom[tid] = (naam, tslug, parent)
    def pad(tid):
        keten = []
        while tid in boom and tid != "0":
            naam, tslug, parent = boom[tid]
            keten.insert(0, {"naam": naam, "slug": tslug})
            tid = parent
        return keten
    cat_paden = [pad(c[0]) for c in cats]
    tags = [r[0] for r in rq(f"""SELECT t.name FROM wp_term_relationships tr
        JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id=tr.term_taxonomy_id AND tt.taxonomy='product_tag'
        JOIN wp_terms t ON t.term_id=tt.term_id WHERE tr.object_id={rid}""")]

    # afbeeldingen: thumbnail + galerij, met bestandslocatie en alt-tekst
    def attachment(aid):
        r = rq(f"""SELECT pm.meta_value,
                HEX(COALESCE((SELECT meta_value FROM wp_postmeta WHERE post_id={aid}
                          AND meta_key='_wp_attachment_image_alt'), ''))
            FROM wp_postmeta pm WHERE pm.post_id={aid} AND pm.meta_key='_wp_attached_file'""")
        if not r or not r[0][0]:
            return (None, "")
        alt = bytes.fromhex(r[0][1]).decode("utf-8", "replace") if len(r[0]) > 1 and r[0][1] not in ("", "NULL") else ""
        return (r[0][0], alt)
    afb = []
    thumb = rq(f"SELECT meta_value FROM wp_postmeta WHERE post_id={rid} AND meta_key='_thumbnail_id'")
    galerij = rq(f"SELECT meta_value FROM wp_postmeta WHERE post_id={rid} AND meta_key='_product_image_gallery'")
    ids = ([thumb[0][0]] if thumb and thumb[0][0] else []) + \
          ([x for x in galerij[0][0].split(',') if x.strip()] if galerij and galerij[0][0] else [])
    for i, aid in enumerate(ids):
        pad_rel, alt = attachment(aid)
        if not pad_rel: continue
        bron = os.path.join(content, "uploads", pad_rel)
        if not os.path.isfile(bron): continue
        naam = f"{doel['wc_id']}-{i}-{os.path.basename(pad_rel)}"
        shutil.copy2(bron, os.path.join(staging, "afbeeldingen", naam))
        afb.append({"bestand": naam, "alt": alt, "hoofd": i == 0})

    plan.append({**doel, "status": "ok", "titel": titel, "slug": slug,
                 "inhoud": inhoud, "excerpt": excerpt, "categorien": cat_paden,
                 "tags": tags, "afbeeldingen": afb})

json.dump(plan, open(f"{staging}/plan.json", "w"), ensure_ascii=False)
for p in plan:
    if p["status"] != "ok":
        print(f"SKIP  #{p['wc_id']} [{p['art']}] — {p['status']}")
    else:
        print(f"PLAN  #{p['wc_id']} [{p['art']}] '{p['titel_nu'][:38]}'")
        print(f"      titel -> '{p['titel'][:55]}'  slug -> {p['slug']}")
        print(f"      tekst {len(p['inhoud'])} tekens, excerpt {len(p['excerpt'])}, "
              f"categorieen {len(p['categorien'])}, tags {len(p['tags'])}, afbeeldingen {len(p['afbeeldingen'])}")
PY

    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — niets gewijzigd. Draai '$0 stap13 apply' om over te nemen."
        return
    fi
    [[ -s "$staging/plan.json" ]] || { echo "geen plan"; return; }

    # 3. tekst/slug/titel/categorieën/tags schrijven via één PHP-payload
    python3 - "$staging" <<'PY' > /tmp/afas-opmaak-payload.php
import json, sys
plan = [p for p in json.load(open(f"{sys.argv[1]}/plan.json")) if p.get("status") == "ok"]
veilig = json.dumps(plan, ensure_ascii=False).replace("\\", "\\\\").replace("'", "\\'")
print("<?php")
print(f"$plan = json_decode('{veilig}', true);")
print(r"""
foreach ($plan as $p) {
    $id = (int) $p['wc_id'];
    wp_update_post(['ID' => $id, 'post_title' => $p['titel'], 'post_name' => $p['slug'],
        'post_content' => $p['inhoud'], 'post_excerpt' => $p['excerpt']]);
    // categorie-paden: per niveau opzoeken (slug, dan naam) of aanmaken
    $catIds = [];
    foreach ($p['categorien'] as $keten) {
        $parent = 0;
        foreach ($keten as $niveau) {
            $term = get_term_by('slug', $niveau['slug'], 'product_cat')
                 ?: get_term_by('name', $niveau['naam'], 'product_cat');
            if (!$term) {
                $res = wp_insert_term($niveau['naam'], 'product_cat',
                    ['slug' => $niveau['slug'], 'parent' => $parent]);
                if (is_wp_error($res)) { break; }
                $term = get_term($res['term_id'], 'product_cat');
            }
            $parent = (int) $term->term_id;
        }
        if ($parent) { $catIds[] = $parent; }
    }
    if ($catIds) { wp_set_object_terms($id, array_unique($catIds), 'product_cat'); }
    if (!empty($p['tags'])) { wp_set_object_terms($id, $p['tags'], 'product_tag'); }
    printf("OPGEMAAKT #%d [%s] -> '%s' (%d cats, %d tags)\n",
        $id, $p['art'], $p['titel'], count($catIds), count($p['tags']));
}
""")
PY
    wpr_stdin eval-file - < /tmp/afas-opmaak-payload.php

    # 4. afbeeldingen: wp media import per bestand (maakt attachment + metadata),
    #    eerste = hoofdafbeelding, rest = galerij; alt-tekst erna.
    python3 - "$staging" <<'PY' > "$staging/afbeeldingen.tsv"
import json, sys
for p in json.load(open(f"{sys.argv[1]}/plan.json")):
    if p.get("status") != "ok": continue
    for a in p["afbeeldingen"]:
        print(f"{p['wc_id']}\t{a['bestand']}\t{1 if a['hoofd'] else 0}\t{a['alt']}")
PY
    if [[ "$TARGET" == "lokaal" ]]; then
        local mnt=(-v "$staging/afbeeldingen:/defibs-opmaak:ro")
        local pfx=/defibs-opmaak
        # uploads-jaarmap is na een verse pull host-eigendom; uid 33 (php-fpm)
        # moet er attachments in kunnen schrijven (zelfde klasse als _lokaal_prep)
        _lokaal_compose run --rm -T --user 0 wpcli sh -c             "cd /var/www/html/wp-content/uploads && mkdir -p $(date +%Y) && chown -R 33:33 $(date +%Y)"             >/dev/null 2>&1 || true
    else
        ssh "$SERVER" "mkdir -p /tmp/defibs-opmaak"
        scp -q "$staging/afbeeldingen/"* "$SERVER:/tmp/defibs-opmaak/" 2>/dev/null || true
        local mnt=() pfx=/tmp/defibs-opmaak
    fi
    local vorige="" galerij_ids=""
    while IFS=$'\t' read -r wc_id bestand hoofd alt; do
        [[ -n "$wc_id" ]] || continue
        if [[ "$wc_id" != "$vorige" && -n "$vorige" && -n "$galerij_ids" ]]; then
            wpr post meta update "$vorige" _product_image_gallery "${galerij_ids#,}" >/dev/null
            galerij_ids=""
        fi
        vorige="$wc_id"
        local uit aid
        if [[ "$TARGET" == "lokaal" ]]; then
            uit=$(_lokaal_compose run "${_LOKAAL_RUN_OPTS[@]}" "${mnt[@]}" wpcli sh -c \
                "php -d memory_limit=1024M /usr/local/bin/wp media import '$pfx/$bestand' --post_id=$wc_id $( [[ $hoofd == 1 ]] && echo --featured_image || true ) --porcelain" </dev/null 2>/dev/null | tail -1 || true)
        else
            uit=$(ssh "$SERVER" "cd '$WP_ROOT' && wp media import '$pfx/$bestand' --post_id=$wc_id $( [[ $hoofd == 1 ]] && echo --featured_image || true ) --porcelain" </dev/null 2>/dev/null | tail -1 || true)
        fi
        aid=$(echo "$uit" | grep -oE '[0-9]+$' || true)
        if [[ -n "$aid" ]]; then
            [[ -n "$alt" ]] && wpr post meta update "$aid" _wp_attachment_image_alt "\"$alt\"" >/dev/null
            [[ "$hoofd" != 1 ]] && galerij_ids="$galerij_ids,$aid"
            echo "AFBEELDING #$wc_id <- $bestand (attachment $aid$( [[ $hoofd == 1 ]] && echo ', hoofdafbeelding' ))"
        else
            echo "FOUT afbeelding $bestand voor #$wc_id"
        fi
    done < "$staging/afbeeldingen.tsv"
    if [[ -n "$vorige" && -n "$galerij_ids" ]]; then
        wpr post meta update "$vorige" _product_image_gallery "${galerij_ids#,}" >/dev/null
    fi
    echo "OK — opmaak overgenomen op $(doel_naam)"
}

# ---------------------------------------------------------------------------
# Stap 14 — Oude/interne accounts verwijderen (besluit Cas 27 aug). Content
# (orders, posts, media) wordt via --reassign overgedragen aan het hoofd-
# account (info@defibsolutions.nl), zodat er niets cascade-verdwijnt.
# O.a. het administrator-account van plugin-leverancier WPSwings gaat eruit.
# Idempotent: al-verwijderde accounts worden gemeld en overgeslagen.
# Default dry-run; `stap14 apply` verwijdert echt.
# ---------------------------------------------------------------------------
stap14() {
    controleer_config
    local apply="${1:-}"
    local doel_email="info@defibsolutions.nl"
    local weg=(
        "support@wpswings.com"
        "roelsethijs@gmail.com"
        "lola@defibsolutions.nl"
        "nordics@defibsolutions.eu"
        "katja@defibsolutions.nl"
        "ines@defibsolutions.nl"
        "rutger@improvit.nl"
        "info@bhvvoordeelwinkel.nl"
    )
    local doel_id
    doel_id=$(wpr user get "$doel_email" --field=ID 2>/dev/null | tr -d '[:space:]')
    [[ "$doel_id" =~ ^[0-9]+$ ]] || { echo "FOUT: doelaccount $doel_email niet gevonden" >&2; exit 1; }
    echo "reassign-doel: $doel_email (user $doel_id)"

    # Degraderen i.p.v. verwijderen: klant-accounts die onterecht (ook)
    # administrator zijn — beheerrechten eraf, klant + AFAS-koppeling blijven.
    # biuro@premiosafe.pl (user "martijn"): hergebruikt oud-medewerker-account,
    # admin-rol bleef hangen (besluit Cas 27 aug).
    local degradeer=(
        "biuro@premiosafe.pl"
    )
    local email uid login
    for email in "${degradeer[@]}"; do
        uid=$(wpr user get "$email" --field=ID 2>/dev/null | tr -d '[:space:]')
        if [[ ! "$uid" =~ ^[0-9]+$ ]]; then
            echo "SKIP   $email — bestaat niet"
            continue
        fi
        local rollen
        rollen=$(wpr user get "$uid" --field=roles 2>/dev/null | tr -d '[:space:]')
        if [[ "$rollen" != *administrator* ]]; then
            echo "OK     $email (user $uid) is al geen beheerder ($rollen)"
            continue
        fi
        if [[ "$apply" != "apply" ]]; then
            echo "ZOU DEGRADEREN   $email (user $uid, nu: $rollen) -> customer + afas_klant"
        else
            wpr user set-role "$uid" customer >/dev/null
            wpr user add-role "$uid" afas_klant >/dev/null
            echo "GEDEGRADEERD  $email (user $uid) -> customer + afas_klant"
        fi
    done

    for email in "${weg[@]}"; do
        uid=$(wpr user get "$email" --field=ID 2>/dev/null | tr -d '[:space:]')
        if [[ ! "$uid" =~ ^[0-9]+$ ]]; then
            echo "SKIP   $email — bestaat niet (al verwijderd?)"
            continue
        fi
        login=$(wpr user get "$uid" --field=user_login 2>/dev/null | tr -d '[:space:]')
        if [[ "$apply" != "apply" ]]; then
            echo "ZOU VERWIJDEREN  $email (user $uid, login $login) -> content naar $doel_id"
        else
            wpr user delete "$uid" --reassign="$doel_id" --yes
            echo "VERWIJDERD  $email (user $uid, login $login) -> content naar $doel_id"
        fi
    done
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — niets verwijderd. Draai '$0 stap14 apply' om uit te voeren."
    else
        echo "OK — accounts opgeruimd op $(doel_naam)"
    fi
}
# ---------------------------------------------------------------------------
# stap15 — Divi-caches resetten na de URL-herschrijving van de verhuizing.
# Divi's feature-cache (postmeta _et_builder_module_features_cache) keyt op
# md5 van de shortcode-attributen — inclusief URL's. Na de domein-rewrite
# missen álle lookups in de mee-gemigreerde cache, en een miss in een geladen
# cache betekent voor Divi "feature stond vorige keer uit"
# (class-et-builder-post-feature-base.php::get): padding, box-shadow, borders,
# border-radius, knop- en hover-CSS verdwijnen uit elke pagina met URL-houdende
# module-attrs (homepage-kaarten, merken-/servicesbalk — Kevins K2/K3/K4).
# De cache herstelt zichzelf nooit: de callbacks draaien niet meer, dus de
# 15ms-drempel voor her-save wordt nooit gehaald. Fix: cache-postmeta purgen
# + et-cache leeg; de eerste render bouwt alles correct opnieuw op (geldt op
# elke PHP-versie). Idempotent.
stap15() {
    controleer_config
    local apply="${1:-}"
    wpr_stdin eval-file - "$apply" <<'PHP'
<?php
$apply = ('apply' === ($args[0] ?? ''));
$keys = [
    '_et_builder_module_features_cache',
    '_et_dynamic_cached_shortcodes',
    '_et_dynamic_cached_attributes',
];
global $wpdb;
foreach ($keys as $k) {
    $n = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", $k
    ));
    if ($apply && $n > 0) { delete_post_meta_by_key($k); }
    printf("%s: %d rijen%s\n", $k, $n, ($apply && $n > 0) ? ' -> gepurged' : '');
}
// et-cache leegmaken zodat ook de gegenereerde CSS-bestanden vers zijn.
$dir = WP_CONTENT_DIR . '/et-cache';
$verwijderd = 0;
if (is_dir($dir)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $pad) {
        if (!$apply) { $verwijderd++; continue; }
        $ok = $pad->isDir() ? @rmdir($pad->getPathname()) : @unlink($pad->getPathname());
        if ($ok) { $verwijderd++; }
    }
}
printf("et-cache: %d items%s\n", $verwijderd, $apply ? ' verwijderd' : '');
// BeRocket AAPF cachet template-style-paden ABSOLUUT in een optie
// (BeRocket_AAPF_getall_Template_Styles). Na de verhuizing wijzen die naar
// het oude TransIP-pad -> file_exists faalt -> elke filter bailt met
// "Template not selected" en de filterbalk blijft leeg (Kevins K8).
// De plugin heeft een eigen regeneratie-action die met lokale paden
// herschrijft.
$styles = (array) get_option('BeRocket_AAPF_getall_Template_Styles');
$eerste = reset($styles);
$oudPad = is_array($eerste) && !str_starts_with($eerste['file'] ?? '', WP_PLUGIN_DIR);
if ($apply && $oudPad) {
    do_action('bapf_include_all_tempate_styles');
    $styles = (array) get_option('BeRocket_AAPF_getall_Template_Styles');
    $eerste = reset($styles);
}
printf("berocket template-styles: %s\n", $oudPad
    ? ($apply ? 'pad-cache geregenereerd -> ' . ($eerste['file'] ?? '?') : 'VEROUDERD PAD: ' . ($eerste['file'] ?? '?'))
    : 'paden al lokaal');
if (!$apply) { echo "Dry-run - niets gewijzigd.\n"; }
PHP
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — draai '$0 stap15 apply' om te purgen."
    else
        echo "OK — Divi-caches gereset op $(doel_naam); eerste render bouwt ze opnieuw op"
    fi
}
# ---------------------------------------------------------------------------
# stap16 — Kevins staging-opruiming (mail 30 aug, punten K9/K10):
#   K9  productcategorieën "Reanibex 100 (Wifi)"/"(Sigfox)" verwijderen —
#       bevatten alleen variaties (tonen leeg); is nu een keuze-as op de
#       Reanibex-productpagina.
#   K10 "AED Bundels" + kast-subcategorieën verwijderen — idem, bundels
#       zitten als opties op de productpagina's.
#   Bijbehorende menu-items gaan mee. K8 (filterblok) zat hier eerst óók in
#   maar is teruggedraaid: Cas wil de filters wérkend (zoals live), niet weg.
#   Idempotent.
stap16() {
    controleer_config
    local apply="${1:-}"
    wpr_stdin eval-file - "$apply" <<'PHP'
<?php
$apply = ('apply' === ($args[0] ?? ''));

// K9 + K10: categorieën op exacte naam + bijbehorende menu-items.
$namen = [
    'AED Bundels', 'AED Bundel met witte binnenkast',
    'AED Bundel met groene binnenkast', 'AED Bundel met arky buitenkast',
    'Reanibex 100 (Wifi)', 'Reanibex 100 (Sigfox)',
];
global $wpdb;
foreach ($namen as $naam) {
    $term = get_term_by('name', $naam, 'product_cat');
    if (!$term) { printf("cat '%s': al weg\n", $naam); continue; }
    $menuItems = $wpdb->get_col($wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
         JOIN {$wpdb->postmeta} o ON o.post_id = p.ID AND o.meta_key = '_menu_item_object' AND o.meta_value = 'product_cat'
         JOIN {$wpdb->postmeta} i ON i.post_id = p.ID AND i.meta_key = '_menu_item_object_id' AND i.meta_value = %s
         WHERE p.post_type = 'nav_menu_item'", (string) $term->term_id
    ));
    if ($apply) {
        foreach ($menuItems as $mid) { wp_delete_post((int) $mid, true); }
        wp_delete_term($term->term_id, 'product_cat');
    }
    printf("cat '%s' (term %d, %d menu-item%s)%s\n", $naam, $term->term_id,
        count($menuItems), count($menuItems) === 1 ? '' : 's',
        $apply ? ' -> verwijderd' : ' -> te verwijderen');
}
if (!$apply) { echo "Dry-run - niets gewijzigd.\n"; }
PHP
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — draai '$0 stap16 apply' om uit te voeren."
    else
        echo "OK — staging-opruiming K9/K10 uitgevoerd op $(doel_naam)"
    fi
}
# ---------------------------------------------------------------------------
# stap17 — Beheerders als klant laten meekijken (besluit Cas 31 aug).
# Zonder afas_relatie_id filtert de plugin alle runtime-geprijsde variaties
# weg en zien admins een uitgedund assortiment (Kevins K7-verwarring).
# Daarom krijgt elk administrator-account de relatie van een bestaande
# klant (35801) + afas_sync_paused=1 zodat de AFAS-sync hun accountgegevens
# nooit overschrijft — zelfde inrichting als het account van Cas.
# Bestaande koppelingen blijven ongemoeid. Idempotent.
stap17() {
    controleer_config
    local apply="${1:-}"
    wpr_stdin eval-file - "$apply" <<'PHP'
<?php
$apply = ('apply' === ($args[0] ?? ''));
$relatie = '35801';
foreach (get_users(['role' => 'administrator']) as $u) {
    $huidig = (string) get_user_meta($u->ID, 'afas_relatie_id', true);
    $paused = (string) get_user_meta($u->ID, 'afas_sync_paused', true);
    $acties = [];
    if ($huidig === '') { $acties[] = "relatie -> $relatie"; }
    if ($paused !== '1') { $acties[] = 'sync_paused -> 1'; }
    if (!$acties) {
        printf("%-20s staat al goed (relatie %s)\n", $u->user_login, $huidig);
        continue;
    }
    if ($apply) {
        if ($huidig === '') { update_user_meta($u->ID, 'afas_relatie_id', $relatie); }
        if ($paused !== '1') { update_user_meta($u->ID, 'afas_sync_paused', '1'); }
    }
    printf("%-20s %s%s\n", $u->user_login, implode(', ', $acties), $apply ? '' : ' (dry-run)');
}
PHP
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — draai '$0 stap17 apply' om te schrijven."
    else
        echo "OK — beheerders gekoppeld op $(doel_naam)"
    fi
}
# ---------------------------------------------------------------------------
usage() {
    echo "gebruik: [DEFIBS_TARGET=lokaal|cp01] $0 <stap>   (default: lokaal)"
    echo "stappen:"
    echo "  stap1   Mail UIT: disable-emails installeren + activeren (nieuwe server)"
    echo "  stap2   Overbodige plugins UIT: Jetpack + B2BKing + Mailchimp (incl. job-wachtrij legen)"
    echo "  stap3   Klanten koppelen aan AFAS-relaties uit work/klant-relatie-mapping.csv (dry-run; 'stap3 apply' schrijft)"
    echo "  stap4   lefcreative-afas-b2b installeren + activeren + afas-settings importeren (work/)"
    echo "  stap5   API-keys intrekken: WooCommerce REST-keys + application passwords (dry-run; 'stap5 apply' verwijdert)"
    echo "  stap6   Voorkoppeling: _afas_artikelnummer per product zetten via BHV-match + actielijst (dry-run; 'stap6 apply' schrijft)"
    echo "  stap7   mu-plugins plaatsen uit migration/mu-plugins/ (idempotent)"
    echo "  stap8   Structuur-opruiming: gekoppelde simples die variatie horen te zijn -> prullenbak (dry-run; 'stap8 apply')"
    echo "  stap9   Swatches-instelling conform reseller (custom attributen als dropdown)"
    echo "  stap10  Assortiment-schrappingen uit work/schraplijst-defibsolutions.csv (dry-run; 'stap10 apply')"
    echo "  stap12  Variatie-assen (pa_taal/pa_connectiviteit/pa_opties) op AED-containers, Naam eruit (dry-run; 'stap12 apply')"
    echo "  stap13  Opmaak-overname van reseller voor kale producten (dry-run; apply)"
    echo "  stap14  Oude/interne accounts verwijderen met reassign (dry-run; apply)"
    echo "  stap11  Syncs (opties: 'zonder-prijzen', 'delta' = alleen gewijzigde artikelen, seconden i.p.v. minuten)"
    echo "  stap15  Divi-caches resetten na URL-rewrite (dry-run; 'stap15 apply')"
    echo "  stap16  Kevins staging-opruiming K9/K10: lege categorieën + menu-items weg (dry-run; 'stap16 apply')"
    echo "  stap17  Beheerders koppelen aan klantrelatie 35801 + sync-pauze (dry-run; 'stap17 apply')"
    echo ""
    echo "volledige herbouw (na verse pull), in deze volgorde:"
    echo "  stap1, stap2, stap3 apply, stap4, stap5 apply, stap6 apply, stap7,"
    echo "  stap15 apply, stap9, stap10 apply, stap11, stap8 apply,"
    echo "  stap11 'zonder-prijzen delta',"
    echo "  stap12 apply      <- stap12 ALTIJD als laatste (assen + defaults +"
    echo "                       publiceert variaties die als private binnenkwamen)"
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
    stap13) stap13 "${2:-}" ;;
    stap14) stap14 "${2:-}" ;;
    stap15) stap15 "${2:-}" ;;
    stap16) stap16 "${2:-}" ;;
    stap17) stap17 "${2:-}" ;;
    *) usage ;;
esac
