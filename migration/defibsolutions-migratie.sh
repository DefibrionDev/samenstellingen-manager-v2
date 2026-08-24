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
_filter_ruis() { grep -vE '^(Deprecated|Notice):' || true; }

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
        # volledig laadt (wp-mail-smtp's mail-catcher OOM't dan).
        _lokaal_compose run "${_LOKAAL_RUN_OPTS[@]}" wpcli \
            sh -c "php -d memory_limit=512M /usr/local/bin/wp $*" 2>&1 | _filter_ruis
    else
        ssh "$SERVER" "cd '$WP_ROOT' && wp $*" 2>&1 | _filter_ruis
    fi
}

_lokaal_prep() {
    # Na een verse pull is wp-content/upgrade eigendom van de host-user (1000);
    # uid 33 moet er plugins kunnen uitpakken. Idempotent, dus veilig per run.
    _lokaal_compose run --rm -T --user 0 wpcli sh -c \
        'mkdir -p /var/www/html/wp-content/upgrade && chown -R 33:33 /var/www/html/wp-content/upgrade' \
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
usage() {
    echo "gebruik: [DEFIBS_TARGET=lokaal|cp01] $0 <stap>   (default: lokaal)"
    echo "stappen:"
    echo "  stap1   Mail UIT: disable-emails installeren + activeren (nieuwe server)"
    echo "  stap2   Overbodige plugins UIT: Jetpack + B2BKing deactiveren"
    echo "  stap3   Klanten koppelen aan AFAS-relaties uit work/klant-relatie-mapping.csv (dry-run; 'stap3 apply' schrijft)"
    echo "  stap4   lefcreative-afas-b2b installeren + activeren + afas-settings importeren (work/)"
    echo "  stap5   API-keys intrekken: WooCommerce REST-keys + application passwords (dry-run; 'stap5 apply' verwijdert)"
    echo "  stap6   Voorkoppeling: _afas_artikelnummer per product zetten via BHV-match + actielijst (dry-run; 'stap6 apply' schrijft)"
    echo "  stap7   mu-plugins plaatsen uit migration/mu-plugins/ (8 stuks, idempotent)"
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
    *) usage ;;
esac
