#!/usr/bin/env bash
#
# Migratiescript defibsolutions.fr → cp-01 (woocommerce-b2b → lefcreative-afas-b2b).
# Eigen kopie van het NL-sjabloon (migration/defibsolutions-migratie.sh, besluit
# 27 aug 2026) — het NL-script blijft onaangeraakt tijdens diens livegang.
# Wordt stap voor stap opgebouwd; elke stap is een aparte functie en wordt
# expliciet per naam aangeroepen — geen "alles in één keer".
#
#   ./migration/defibsolutionsfr-migratie.sh stap5
#
# Target-keuze (lokaal-eerst, zie MIGRATIE-DEFIBSOLUTIONS-FR.md "Spelregels"):
#   DEFIBSFR_TARGET=lokaal  (default) draait elke stap in de wpcli-container van
#                           de lokale Docker-kopie (~/projects/wordpress-migrater,
#                           .env-defibsolutionsfr, site op poort 8896)
#   DEFIBSFR_TARGET=cp01    draait exact dezelfde stap via ssh op cp-01
#
# Serverconfig komt uit de project-.env (repo-root); eigen namen zodat NL- en
# FR-runs elkaar nooit kruisen:
#   DEFIBSFR_SERVER        ssh-host van de nieuwe server (alleen nodig bij cp01)
#   DEFIBSFR_WP_ROOT       pad naar de WordPress-root op die server (idem)
#   DEFIBSFR_MIGRATER_DIR  pad naar wordpress-migrater (default ~/projects/wordpress-migrater)
# Environment-variabelen met dezelfde naam gaan vóór de .env-waarden.
#
# Achtergrond/fase-overzicht: MIGRATIE-DEFIBSOLUTIONS-FR.md
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

TARGET="${DEFIBSFR_TARGET:-lokaal}"
SERVER="${DEFIBSFR_SERVER:-INVULLEN-user@cp-01}"
WP_ROOT="${DEFIBSFR_WP_ROOT:-INVULLEN-/pad/naar/wordpress}"
MIGRATER_DIR="${DEFIBSFR_MIGRATER_DIR:-$HOME/projects/wordpress-migrater}"

# De PHP op beide targets is nieuwer dan de oude plugins/het theme; de
# "Deprecated:"-meldingen vervuilen elke stap-output en worden weggefilterd.
# --line-buffered: zonder dit houdt grep de output vast tot het eind en zie je
# fase-voortgang van lange stappen pas als alles klaar is.
_filter_ruis() { grep --line-buffered -vE '^(Deprecated|Notice):' || true; }

_lokaal_compose() {
    # --progress quiet: compose-statusregels ("Container ... Running") gaan
    # anders door de 2>&1-merge heen en vervuilen gevangen stap-output.
    docker compose --progress quiet --project-directory "$MIGRATER_DIR" \
        --env-file "$MIGRATER_DIR/.env-defibsolutionsfr" "$@"
}

doel_naam() {
    [[ "$TARGET" == "lokaal" ]] && echo "lokale kopie (localhost:8896)" || echo "$SERVER"
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
        # volledig laadt; de syncs hebben ruim geheugen nodig.
        _lokaal_compose run "${_LOKAAL_RUN_OPTS[@]}" wpcli \
            sh -c "php -d memory_limit=1024M -d max_execution_time=0 /usr/local/bin/wp $*" 2>&1 | _filter_ruis
    else
        ssh "$SERVER" "cd '$WP_ROOT' && wp $*" 2>&1 | _filter_ruis
    fi
}

_lokaal_prep() {
    # Na een verse pull zijn schrijfmappen eigendom van de host-user (1000);
    # uid 33 (php-fpm) moet erin kunnen schrijven: upgrade/ (plugin-installs)
    # en uploads/wc-logs. (Geen et-cache: FR draait Woodmart, geen Divi.)
    # Idempotent, dus veilig per run.
    _lokaal_compose run --rm -T --user 0 wpcli sh -c \
        'cd /var/www/html/wp-content && mkdir -p upgrade uploads/wc-logs \
         && chown -R 33:33 upgrade uploads/wc-logs' \
        >/dev/null 2>&1 || true
}

controleer_config() {
    if [[ "$TARGET" == "lokaal" ]]; then
        if [[ ! -f "$MIGRATER_DIR/.env-defibsolutionsfr" ]]; then
            echo "FOUT: $MIGRATER_DIR/.env-defibsolutionsfr ontbreekt (zet evt. DEFIBSFR_MIGRATER_DIR)." >&2
            exit 1
        fi
        if ! _lokaal_compose ps --status=running 2>/dev/null | grep -q 'defibsolutionsfr-db'; then
            echo "FOUT: lokale defibsolutionsfr-stack draait niet. Start met:" >&2
            echo "  cd $MIGRATER_DIR && docker compose --env-file .env-defibsolutionsfr up -d" >&2
            exit 1
        fi
        _lokaal_prep
    elif [[ "$TARGET" == "cp01" ]]; then
        if [[ "$SERVER" == INVULLEN-* || "$WP_ROOT" == INVULLEN-* ]]; then
            echo "FOUT: zet eerst DEFIBSFR_SERVER en DEFIBSFR_WP_ROOT (zie kop van dit script)." >&2
            exit 1
        fi
    else
        echo "FOUT: onbekend DEFIBSFR_TARGET '$TARGET' (lokaal of cp01)." >&2
        exit 1
    fi
    echo "[target: $TARGET]"
}

# ---------------------------------------------------------------------------
# Stap 1 — Mail UIT.
# Voorkomt dat klanten welkomst-/account-/order-mails krijgen tijdens het
# inrichten en syncen. Weer aanzetten is de allerlaatste stap van de migratie.
# (De lokale kopie heeft ook al de mu-plugin zz-disable-emails-local; deze
# stap is de gordel die óók op cp-01 werkt.)
# ---------------------------------------------------------------------------
stap1() {
    controleer_config
    wpr plugin install disable-emails --activate
    echo "--- controle:"
    wpr plugin list --status=active | grep disable-emails
    echo "OK — mail staat uit op $(doel_naam)"
}

# ---------------------------------------------------------------------------
# Stap 2 — Overbodige plugins UIT: woocommerce-b2b + wp-staging + mainwp-child.
# woocommerce-b2b wordt vervangen door lefcreative-afas-b2b; de data blijft in
# de database staan als inerte fallback (zelfde aanpak als B2BKing bij NL).
# wp-staging(-pro): staging/backup-tool van de oude hosting; nutteloos op de
# kopie en op cp-01, en zo'n geheugenvreter dat wp-cli zonder verhoogde
# memory_limit al bij het booten OOM't (les van NL).
# mainwp-child: remote-beheerkanaal (MainWP-dashboard kan plugins/updates
# pushen) — uit op kopieën zodat niets van buitenaf de shop muteert; bij de
# livegang bewust weer aanzetten als beheer hem nodig heeft.
# jetpack/mailchimp staan in de lus voor sjabloon-pariteit met NL; op FR zijn
# ze niet geïnstalleerd en meldt de stap dat netjes.
# NIET in de lijst (open beslispunt): points-and-rewards-for-woocommerce en
# ultimate-woocommerce-points-and-rewards — zie MIGRATIE-DEFIBSOLUTIONS-FR.md.
# ---------------------------------------------------------------------------
stap2() {
    controleer_config
    local p
    for p in woocommerce-b2b wp-staging-pro wp-staging mainwp-child jetpack mailchimp-for-woocommerce; do
        if wpr plugin is-installed "$p" >/dev/null 2>&1; then
            wpr plugin deactivate "$p"
        else
            echo "$p is niet geïnstalleerd op $(doel_naam) — overslaan"
        fi
    done
    # wp-staging probeert bij deactivatie zijn mu-plugin te unlinken maar mist
    # daarvoor de rechten (mu-plugins/ is host-eigendom na een verse pull) —
    # zonder opruiming blijft wp-staging-optimizer.php als must-use laden.
    if [[ "$TARGET" == "lokaal" ]]; then
        _lokaal_compose run --rm -T --user 0 wpcli sh -c \
            'rm -f /var/www/html/wp-content/mu-plugins/wp-staging-optimizer.php' >/dev/null 2>&1 || true
    else
        ssh "$SERVER" "rm -f '$WP_ROOT/wp-content/mu-plugins/wp-staging-optimizer.php'"
    fi
    echo "--- controle:"
    wpr plugin list | { grep -iE 'woocommerce-b2b|wp-staging|mainwp|jetpack|mailchimp' || echo "(niets gevonden)"; }
    echo "OK — woocommerce-b2b + wp-staging + mainwp-child staan uit op $(doel_naam)"
}

# ---------------------------------------------------------------------------
# Stap 3 — Klanten koppelen aan AFAS-verkooprelaties (usermeta afas_relatie_id,
# het veld waar lefcreative-afas-b2b op draait).
# Bron: work/defibsolutionsfr-klant-relatie-mapping.csv (wc_user_id;
# afas_relatie_id), gegenereerd door work/mine-order-koppeling-defibsolutionsfr.py
# (orderhistorie met e-mailbewijs + e-mail-fallback, besluit Cas 31 aug).
# Users zonder mapping-rij blijven bewust ongekoppeld (review-CSV ernaast).
# Default dry-run (toont ook het e-mailadres van de user ter verificatie);
# `stap3 apply` schrijft echt.
# ---------------------------------------------------------------------------
stap3() {
    controleer_config
    local mapping="$REPO_ROOT/work/defibsolutionsfr-klant-relatie-mapping.csv"
    local apply="${1:-}"
    [[ -f "$mapping" ]] || { echo "FOUT: $mapping ontbreekt (bron-beslispunt nog open, zie runbook)" >&2; exit 1; }

    python3 - "$mapping" "$apply" <<'PY' > /tmp/afasfr-relatie-payload.php
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

    wpr_stdin eval-file - < /tmp/afasfr-relatie-payload.php
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — niets geschreven. Draai '$0 stap3 apply' om echt te schrijven."
    fi
}

# ---------------------------------------------------------------------------
# Stap 4 — lefcreative-afas-b2b plugin installeren + activeren + AFAS-settings.
# Bronnen (beide in work/, gitignored):
#   - work/lefcreative-afas-b2b-<versie>.zip  (identieke 1.3.14 van NL/ARKY)
#   - work/afas-settings-fr.json              (FR-afleiding van de NL-dump,
#     27 aug 2026: Sync_/Tonen_Defibsolutions_FR als filtervelden, delta-
#     cursors leeg, scheduling uit tot livegang; afas_sku_source_field staat
#     nog op de NL-waarde — open SKU-beslispunt)
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

    local settings="$REPO_ROOT/work/afas-settings-fr.json"
    if [[ -f "$settings" ]]; then
        python3 - "$settings" <<'PY' > /tmp/afasfr-settings-payload.php
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
        wpr_stdin eval-file - < /tmp/afasfr-settings-payload.php
    else
        echo "LET OP: $settings ontbreekt — plugin actief maar zonder settings-import."
    fi

    # Veiligheidsgordels: order-push naar AFAS én de interne scheduler staan
    # overal geforceerd uit — lokaal altijd, en op cp-01 zolang we niet live
    # zijn (bij de livegang zet Cas ze bewust handmatig aan).
    wpr option update afas_sync_orders_enabled 0 >/dev/null
    wpr option update afas_scheduling_enabled 0 >/dev/null
    echo "(afas_sync_orders_enabled + afas_scheduling_enabled geforceerd op 0 — bij livegang handmatig aan)"
    # Testklant voor checkout-tests volgt in stap 1.6 (runbook) zodra een
    # geschikte FR-klant gekozen is — nooit live-wachtwoorden muteren.
    echo "--- controle:"
    wpr plugin list | grep -i lefcreative
    wpr option get afas_env_type
    echo "OK — plugin actief + settings geimporteerd op $(doel_naam)"
}

# ---------------------------------------------------------------------------
# Stap 5 — Alle API-keys van de shop intrekken.
# defibsolutions.fr had vier read_write REST-keys: Shopctrl, 2× Improvit en
# Dashboard — besluit Cas 27 aug: álles mag weg, ook Shopctrl. Na de migratie
# mag niets van buitenaf meer muteren — de nieuwe plugin praat zelf uitgaand
# met AFAS en heeft geen inkomende REST-key nodig. Wie later weer toegang
# nodig heeft maakt bewust een nieuwe key aan.
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
# alleen SKU==itemcode als fallback) en maakt hij duplicaten aan. De plugin
# self-healt bovendien de SKU naar deze meta bij de eerste frontend-lees —
# deze stap moet dus vóór frontend-verkeer draaien.
#
# Doel-itemcode per product, in volgorde:
#   1. OMZETTEN-rij in work/defibsolutionsfr-omzet-aed.csv (besluit Cas 31 aug:
#      kale AED's worden samenstellingen; doel_base = samenstellings-itemcode);
#   2. SKU matcht precies één actief AFAS-artikel op Artikelcode_BHV_Voordeelwinkel;
#   3. SKU is zelf een actieve AFAS-itemcode.
# Geblokkeerde artikelen (soft-delete, B-prefix) doen nooit mee. De 10
# Randy-twijfelgevallen hebben geen OMZETTEN-status en blijven onaangeroerd.
# Bron-artikelen: work/cache/afas-artikelen-defibsolutionsfr.json (--vers via
# de koppelbaarheids-audit). Default dry-run; `stap6 apply` schrijft echt.
# ---------------------------------------------------------------------------
stap6() {
    controleer_config
    local apply="${1:-}"
    local cache="$REPO_ROOT/work/cache/afas-artikelen-defibsolutionsfr.json"
    local omzet="$REPO_ROOT/work/defibsolutionsfr-omzet-aed.csv"
    [[ -f "$cache" ]] || { echo "FOUT: $cache ontbreekt (draai de koppelbaarheids-audit met --vers)" >&2; exit 1; }
    [[ -f "$omzet" ]] || { echo "FOUT: $omzet ontbreekt" >&2; exit 1; }

    mkdir -p "$REPO_ROOT/tmp"
    local shopdump="$REPO_ROOT/tmp/defibsfr-shop-skus.tsv"
    wpr db query "\"SELECT p.ID, p.post_type, COALESCE(sku.meta_value,''), COALESCE(an.meta_value,'') FROM wp_posts p LEFT JOIN wp_postmeta sku ON sku.post_id=p.ID AND sku.meta_key='_sku' LEFT JOIN wp_postmeta an ON an.post_id=p.ID AND an.meta_key='_afas_artikelnummer' WHERE p.post_type IN ('product','product_variation') AND p.post_status IN ('publish','private')\"" --skip-column-names > "$shopdump"

    python3 - "$cache" "$omzet" "$shopdump" "$apply" <<'PY' > /tmp/afasfr-voorkoppel-payload.php
import csv, json, sys
from collections import defaultdict

cache, omzet, shopdump, apply = sys.argv[1:5]
d = json.load(open(cache))

per_itemcode, per_bhv = {}, defaultdict(list)
for r in d:
    c = (r.get("Itemcode") or "").strip()
    if not c or c in per_itemcode:
        continue
    per_itemcode[c] = r
    if r.get("Geblokkeerd") is True:
        continue
    b = (r.get("Artikelcode_BHV_Voordeelwinkel") or "").strip()
    if b:
        per_bhv[b].append(c)

def actief(c):
    r = per_itemcode.get(c)
    return r is not None and r.get("Geblokkeerd") is not True

akkoord = {}
for r in csv.DictReader(open(omzet, encoding="utf-8-sig"), delimiter=";"):
    if r["status"].strip() == "OMZETTEN" and r["doel_base"].strip():
        doel = r["doel_base"].strip()
        if not actief(doel):
            print(f"// LET OP: doel_base {doel} (wc:{r['wc_id']}) niet actief in AFAS — overgeslagen", file=sys.stderr)
            continue
        akkoord[r["wc_id"].strip()] = doel

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

print(f"// {len(paren)} voorkoppelingen bepaald ({len(akkoord)} uit omzet-lijst)", file=sys.stderr)
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

    wpr_stdin eval-file - < /tmp/afasfr-voorkoppel-payload.php
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — niets geschreven. Draai '$0 stap6 apply' om echt te schrijven."
    fi
}

# ---------------------------------------------------------------------------
# Stap 7 — mu-plugins plaatsen uit migration/mu-plugins/. EXPLICIETE FR-lijst
# (de oorspronkelijke 8 van het NL-besluit, 24 aug) — migration/mu-plugins/
# bevat inmiddels ook NL-specifieke restyles (defibs-*-restyle: Divi/NL-
# huisstijl) en de reseller-Points-Pro-fix; die horen NIET op de Woodmart-FR-
# shop en worden actief opgeruimd als ze er staan. Idempotent. Let op: een
# verse pull (rsync --delete) haalt alles weer weg — deze stap hoort in elke
# herhaal-reeks.
# ---------------------------------------------------------------------------
_FR_MU_PLUGINS=(
    wc-variation-threshold.php variations-json-cache.php
    checkout-ajax-fallback.php afas-tracktrace-style.php
    order-email-afas-debiteur.php order-email-unit-prices.php
    afas-preview-winkelmanager.php shop-manager-login-as-klant.php
)
_FR_MU_VERBODEN=(defibs-checkout-restyle.php defibs-product-restyle.php
    points-pro-variable-price-fix.php wcpt-cli-cache-fix.php)

stap7() {
    controleer_config
    local bron="$REPO_ROOT/migration/mu-plugins" p
    [[ -d "$bron" ]] || { echo "FOUT: $bron ontbreekt" >&2; exit 1; }
    for p in "${_FR_MU_PLUGINS[@]}"; do
        [[ -f "$bron/$p" ]] || { echo "FOUT: $bron/$p ontbreekt" >&2; exit 1; }
    done

    if [[ "$TARGET" == "lokaal" ]]; then
        local content_dir
        content_dir=$(grep '^CONTENT_DIR=' "$MIGRATER_DIR/.env-defibsolutionsfr" | cut -d= -f2)
        local doel="$MIGRATER_DIR/${content_dir#./}/mu-plugins"
        mkdir -p "$doel"
        for p in "${_FR_MU_PLUGINS[@]}"; do cp "$bron/$p" "$doel/"; done
        for p in "${_FR_MU_VERBODEN[@]}"; do rm -f "$doel/$p"; done
        echo "--- controle:"
        ls "$doel"
    else
        ssh "$SERVER" "mkdir -p '$WP_ROOT/wp-content/mu-plugins'"
        for p in "${_FR_MU_PLUGINS[@]}"; do
            scp -q "$bron/$p" "$SERVER:$WP_ROOT/wp-content/mu-plugins/"
        done
        ssh "$SERVER" "cd '$WP_ROOT/wp-content/mu-plugins' && rm -f ${_FR_MU_VERBODEN[*]}"
        echo "--- controle:"
        ssh "$SERVER" "ls '$WP_ROOT/wp-content/mu-plugins'"
    fi
    echo "OK — ${#_FR_MU_PLUGINS[@]} mu-plugins geplaatst op $(doel_naam)"
}

# ---------------------------------------------------------------------------
# Stap 8 — Franse vertaling van de plugin plaatsen. De plugin is NL-talig;
# fr_FR.po/.mo staan in migration/afas-translations/ (39 strings, machinaal
# vertaald 31 aug — review door native welkom). Doel: wp-content/languages/
# plugins/ (WP-conventie; overleeft plugin-updates, i.t.t. de plugin-map).
# Idempotent: kopieert altijd de repo-versie eroverheen.
# ---------------------------------------------------------------------------
stap8() {
    controleer_config
    local mo="$REPO_ROOT/migration/afas-translations/lefcreative-afas-b2b-fr_FR.mo"
    [[ -f "$mo" ]] || { echo "FOUT: $mo ontbreekt (msgfmt op de .po draaien)" >&2; exit 1; }

    if [[ "$TARGET" == "lokaal" ]]; then
        local content_dir
        content_dir=$(grep '^CONTENT_DIR=' "$MIGRATER_DIR/.env-defibsolutionsfr" | cut -d= -f2)
        local doel="$MIGRATER_DIR/${content_dir#./}/languages/plugins"
        mkdir -p "$doel"
        cp "$mo" "$doel/"
    else
        ssh "$SERVER" "mkdir -p '$WP_ROOT/wp-content/languages/plugins'"
        scp -q "$mo" "$SERVER:$WP_ROOT/wp-content/languages/plugins/"
    fi
    echo "--- controle (vertaling geladen?):"
    wpr_stdin eval-file - <<'PHP'
<?php
// forceer verse laad van het tekstdomein en toets twee strings
unload_textdomain('lefcreative-afas-b2b');
load_plugin_textdomain('lefcreative-afas-b2b', false, 'lefcreative-afas-b2b/languages');
load_textdomain('lefcreative-afas-b2b', WP_CONTENT_DIR . '/languages/plugins/lefcreative-afas-b2b-' . get_locale() . '.mo');
printf("locale=%s\n", get_locale());
foreach (['Adresboek', 'Afleveradres', 'Opslaan'] as $s) {
    printf("  %-14s -> %s\n", $s, __($s, 'lefcreative-afas-b2b'));
}
PHP
    echo "OK — Franse vertaling geplaatst op $(doel_naam)"
}

hulp() {
    cat <<EOF
Gebruik: $0 <stap> [apply|opties]   (DEFIBSFR_TARGET=lokaal|cp01, default lokaal)

  stap1            Mail uit (disable-emails)
  stap2            Overbodige plugins uit (woocommerce-b2b, wp-staging, mainwp-child)
  stap3   [apply]  Klanten koppelen aan AFAS-relaties (orderhistorie + e-mail-fallback)
  stap4            lefcreative-afas-b2b installeren + FR-settings + gordels
  stap5   [apply]  Alle API-keys intrekken (besluit 31 aug: incl. Shopctrl)
  stap6   [apply]  Voorkoppeling _afas_artikelnummer (incl. omzet-lijst)
  stap7            mu-plugins plaatsen
  stap8            Franse plugin-vertaling plaatsen (fr_FR.mo)

Zie MIGRATIE-DEFIBSOLUTIONS-FR.md voor het fase-overzicht.
EOF
}

case "${1:-}" in
    stap1) stap1 ;;
    stap2) stap2 ;;
    stap3) stap3 "${2:-}" ;;
    stap4) stap4 ;;
    stap5) stap5 "${2:-}" ;;
    stap6) stap6 "${2:-}" ;;
    stap7) stap7 ;;
    stap8) stap8 ;;
    *) hulp; [[ -n "${1:-}" ]] && exit 1 || exit 0 ;;
esac
