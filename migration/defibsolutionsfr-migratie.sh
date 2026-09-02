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
# ook PHP-runtime-Warnings (wpforms-bug) en de gelogde varianten met
# timestamp eruit — die vervuilen gevangen output zoals `wpr db prefix`
_filter_ruis() { grep --line-buffered -vE '^(Deprecated|Notice|Warning):|^\[[0-9]{2}-[A-Z][a-z]{2}-[0-9]{4} [^]]*\] PHP ' || true; }

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

# ---------------------------------------------------------------------------
# Stap 9 — BeRocket-filterbalk repareren na pull/URL-rewrite. De plugin
# woocommerce-ajax-filters (BeRocket AAPF) cachet ABSOLUTE template-style-
# paden in optie BeRocket_AAPF_getall_Template_Styles; na een verhuizing
# wijzen die naar het oude serverpad -> file_exists faalt -> elke filter
# bailt met "Template not selected" en de balk rendert leeg (bapf_mt_none).
# Les uit de NL-migratie (work/handoff-berocket-filter-pad-cache.md; NL
# stap15). Het Divi-deel van die stap vervalt: FR draait Woodmart.
# Idempotent; hoort in elke herhaal-reeks na een verse pull.
# Default dry-run (toont het huidige pad); `stap9 apply` regenereert.
# ---------------------------------------------------------------------------
stap9() {
    controleer_config
    local apply="${1:-}"
    wpr_stdin eval-file - "$apply" <<'PHP'
<?php
$apply = ('apply' === ($args[0] ?? ''));
// let op: de typo 'tempate' in de action-naam is van de plugin zelf
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
    : 'paden al lokaal: ' . ($eerste['file'] ?? '(leeg)'));
if (!$apply) { echo "Dry-run - niets gewijzigd.\n"; }
PHP
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — draai '$0 stap9 apply' om te regenereren."
    else
        echo "OK — BeRocket-pad-cache vers op $(doel_naam)"
    fi
}

# ---------------------------------------------------------------------------
# Stap 10 — Syncs draaien: plugin-migraties, artikelen (AFAS → tabel),
# prijslijsten + prijzen, verkooprelaties/kortingen/landen/adressen, en 2× de
# WooCommerce-sync (run 2 is het vangnet voor variaties wier container pas in
# run 1 ontstond). Print per fase voortgang en eindigt met de warning-telling.
# Vereist dat de Sync_/Tonen_Defibsolutions_FR-vlaggen in AFAS staan
# (publications:sync + vinkjes-scripts, gedaan 31 aug/1 sep).
# Opties (combineerbaar):
#   zonder-prijzen  slaat de prijs-/relatie-import over (herhaal-runs)
#   delta           wc-sync zonder force: alleen gewijzigde artikelen
# ---------------------------------------------------------------------------
stap10() {
    controleer_config
    local opties="${*:-}"
    local zonder_prijzen=""; [[ "$opties" == *zonder-prijzen* ]] && zonder_prijzen="zonder-prijzen"
    cat > /tmp/afasfr-stap10-payload.php <<'PHP'
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
        sed -i 's/PRIJZEN_PLACEHOLDER/true/' /tmp/afasfr-stap10-payload.php
    else
        sed -i 's/PRIJZEN_PLACEHOLDER/false/' /tmp/afasfr-stap10-payload.php
    fi
    if [[ "$opties" == *delta* ]]; then
        sed -i 's/FORCE_PLACEHOLDER/false/' /tmp/afasfr-stap10-payload.php
    else
        sed -i 's/FORCE_PLACEHOLDER/true/' /tmp/afasfr-stap10-payload.php
    fi
    wpr_stdin eval-file - < /tmp/afasfr-stap10-payload.php
    echo "OK — syncs gedraaid op $(doel_naam)"
}

# ---------------------------------------------------------------------------
# Stap 11 — Structuur-opruiming (NL-stap8-patroon): losse simple products
# waarvan het gekoppelde AFAS-artikel een variatie hoort te zijn (artikel
# heeft artikelcode_parent in wp_lef_afas_artikelen) gaan naar de prullenbak,
# met SKU en _afas_artikelnummer gestript zodat ze nooit meer matchen. De
# artikelen-sync maakt/behoudt daarna de variatie onder de familie-container.
# Dekt beide gevallen: duplicaat (variatie bestaat al) en conversie (nog niet).
# Ook: dubbele variaties met hetzelfde artikelnummer dedupliceren.
# Default dry-run; `stap11 apply` voert uit. Draai hierna stap10 delta.
# ---------------------------------------------------------------------------
stap11() {
    controleer_config
    local apply="${1:-}"

    cat > /tmp/afasfr-structuur-payload.php <<'PHP'
<?php
$apply = APPLY_PLACEHOLDER;
global $wpdb;
// Volgorde-guard: zonder gevulde artikelen-tabel (stap10 eerst!) ziet deze
// stap niets en doet hij stilletjes te weinig.
$n_tabel = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lef_afas_artikelen");
if ($n_tabel === 0) {
    echo "FOUT: wp_lef_afas_artikelen is leeg — draai eerst de artikelen-sync (stap10).\n";
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
        $wpdb->update($wpdb->prefix . 'wc_product_meta_lookup', ['sku' => ''], ['product_id' => (int) $r['ID']]);
        wp_trash_post((int) $r['ID']);
    }
    $n++;
}
// Dubbele variaties: twee product_variations met hetzelfde _afas_artikelnummer.
// De variatie waarvan de SKU gelijk is aan het artikelnummer blijft; de
// andere(n) gaan naar de prullenbak.
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
        sed -i 's/APPLY_PLACEHOLDER/true/' /tmp/afasfr-structuur-payload.php
    else
        sed -i 's/APPLY_PLACEHOLDER/false/' /tmp/afasfr-structuur-payload.php
    fi

    wpr_stdin eval-file - < /tmp/afasfr-structuur-payload.php
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — niets getrasht. Draai '$0 stap11 apply' om uit te voeren."
    fi
}

# ---------------------------------------------------------------------------
# Stap 12 — /boutique-restanten uit content-URL's strippen. De live shop stond
# in submap /boutique; de migrater herschrijft domein+submap, maar de
# escaped-slashes-variant (Woodmart cms_blocks/Elementor-JSON:
# https:\/\/…\/boutique\/…) valt buiten zijn passes, waardoor het domein wél
# en /boutique níet gestript werd. Deze stap vervangt alle drie de vormen
# (plain, \/-escaped, %2F-encoded) van "<home>/boutique" door "<home>" in
# posts, postmeta en options. Idempotent; hoort in elke herhaal-reeks ná de
# pull. Bij de livegang (boutique.defibsolutions.fr) geldt hetzelfde gat —
# zie runbook fase 2. Default dry-run; `stap12 apply` schrijft.
# ---------------------------------------------------------------------------
stap12() {
    controleer_config
    local apply="${1:-}"
    cat > /tmp/afasfr-boutiquepad-payload.php <<'PHP'
<?php
$apply = APPLY_PLACEHOLDER;
global $wpdb;
$home = untrailingslashit(home_url());               // bv. http://localhost:8896
$kaal = preg_replace('#^https?://#', '', $home);     // localhost:8896
// De bron-data mengt coderingen (WPBakery: url:http%3A%2F%2Fhost/boutique%2F…),
// dus elke combinatie van host-vorm × slash-vorm apart strippen. Vervanging =
// alleen de host-vorm; de scheider die op "boutique" volgt blijft staan en
// wordt vanzelf de nieuwe pad-scheider. "…/boutique" aan het einde (shop-root)
// wordt zo de homepage — klopt: de shop draait op de root.
$paren = [];
foreach ([$kaal, str_replace(':', '%3A', $kaal)] as $hostvorm) {
    foreach (['/', '\\/', '%2F'] as $slash) {
        $paren[$hostvorm . $slash . 'boutique'] = $hostvorm;
    }
}
$doelen = [
    ["{$wpdb->posts}", 'post_content', 'ID'],
    ["{$wpdb->postmeta}", 'meta_value', 'meta_id'],
    ["{$wpdb->options}", 'option_value', 'option_id'],
];
$totaal = 0;
foreach ($doelen as [$tabel, $kolom, $pk]) {
    foreach ($paren as $van => $naar) {
        // LIKE-specials in één pass escapen: literal '\' wordt '\\', en
        // '%'/'_' krijgen een '\' ervoor (esc_like + naïef verdubbelen
        // sloopt elkaars escaping — les van 2 sep)
        $patroon = addcslashes($van, '\\%_');
        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT $pk FROM $tabel WHERE $kolom LIKE %s",
            '%' . $patroon . '%'
        ));
        if (!$rows) { continue; }
        printf("%s%s.%s: %d rijen met '%s'\n", $apply ? 'FIX  ' : 'ZOU FIXEN  ',
            $tabel, $kolom, count($rows), $van);
        $totaal += count($rows);
        if ($apply) {
            foreach ($rows as $id) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE $tabel SET $kolom = REPLACE($kolom, %s, %s) WHERE $pk = %d",
                    $van, $naar, $id
                ));
            }
        }
    }
}
if ($apply && $totaal > 0) { wp_cache_flush(); }
printf("--- %s: %d rijen\n", $apply ? 'APPLY' : 'DRY-RUN', $totaal);
PHP
    if [[ "$apply" == "apply" ]]; then
        sed -i 's/APPLY_PLACEHOLDER/true/' /tmp/afasfr-boutiquepad-payload.php
    else
        sed -i 's/APPLY_PLACEHOLDER/false/' /tmp/afasfr-boutiquepad-payload.php
    fi
    wpr_stdin eval-file - < /tmp/afasfr-boutiquepad-payload.php
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — niets gewijzigd. Draai '$0 stap12 apply' om te fixen."
    else
        echo "OK — /boutique-restanten gestript op $(doel_naam)"
    fi
}

# ---------------------------------------------------------------------------
# Stap 13 — FontAwesome terugzetten. De migrater sluit node_modules/ uit bij
# elke pull (hardcoded rsync/lftp-exclude), maar WPBakery (js_composer)
# levert FontAwesome uitgerekend in assets/lib/vendor/node_modules/
# @fortawesome/ — zonder deze bestanden 404't vc_font_awesome_5 en vallen
# alle fa-iconen weg (o.a. het huisje voor "Home" in de menubalk, melding
# Cas 2 sep). Bron: de live-site zelf (publieke assets, exact de juiste
# versie). Idempotent; hoort in elke herhaal-reeks na een pull. Geldt ook
# voor de kale cp-01-verhuizing (zelfde exclude).
# ---------------------------------------------------------------------------
_FA_BRON="https://www.defibsolutions.fr/boutique/wp-content/plugins/js_composer/assets/lib/vendor/node_modules/@fortawesome/fontawesome-free"
_FA_REL="wp-content/plugins/js_composer/assets/lib/vendor/node_modules/@fortawesome/fontawesome-free"
_FA_BESTANDEN=(css/all.min.css css/v4-shims.min.css
    webfonts/fa-solid-900.woff2 webfonts/fa-solid-900.ttf
    webfonts/fa-regular-400.woff2 webfonts/fa-regular-400.ttf
    webfonts/fa-brands-400.woff2 webfonts/fa-brands-400.ttf)

stap13() {
    controleer_config
    local f code
    if [[ "$TARGET" == "lokaal" ]]; then
        local content_dir doel
        content_dir=$(grep '^CONTENT_DIR=' "$MIGRATER_DIR/.env-defibsolutionsfr" | cut -d= -f2)
        doel="$MIGRATER_DIR/${content_dir#./}/${_FA_REL#wp-content/}"
        mkdir -p "$doel/css" "$doel/webfonts"
        for f in "${_FA_BESTANDEN[@]}"; do
            code=$(curl -s -o "$doel/$f" -w '%{http_code}' "$_FA_BRON/$f")
            [[ "$code" == "200" ]] || { echo "FOUT: $f -> HTTP $code" >&2; exit 1; }
        done
    else
        ssh "$SERVER" "mkdir -p '$WP_ROOT/$_FA_REL/css' '$WP_ROOT/$_FA_REL/webfonts'"
        for f in "${_FA_BESTANDEN[@]}"; do
            ssh "$SERVER" "curl -sf -o '$WP_ROOT/$_FA_REL/$f' '$_FA_BRON/$f'" \
                || { echo "FOUT: $f niet opgehaald op $SERVER" >&2; exit 1; }
        done
    fi
    echo "--- controle:"
    if [[ "$TARGET" == "lokaal" ]]; then
        curl -s -o /dev/null -w "all.min.css lokaal: HTTP %{http_code}\n" \
            "http://localhost:8896/$_FA_REL/css/all.min.css"
    else
        ssh "$SERVER" "ls -la '$WP_ROOT/$_FA_REL/css/'"
    fi
    echo "OK — FontAwesome (${#_FA_BESTANDEN[@]} bestanden) teruggezet op $(doel_naam)"
}

# ---------------------------------------------------------------------------
# Stap 14 — Containers erven taxonomie-termen van hun variaties. De wc-sync
# maakt familie-containers kaal aan ("Uncategorized", geen attributen); de
# omgebouwde oude producten (nu variaties) dragen de categorie-/filter-termen
# (product_cat, pa_fabricant, pa_periode-de-garantie, …) — maar variaties
# verschijnen niet in archieven/filters, dus de AED's zijn onvindbaar
# (melding Cas 2 sep: Zoll-filterlink toont niets). Deze stap zet per
# variable container de UNIE van de termen van zijn variaties, en haalt
# "Uncategorized" weg zodra er echte categorieën zijn. Idempotent.
# Default dry-run; `stap14 apply` schrijft.
# ---------------------------------------------------------------------------
stap14() {
    controleer_config
    local apply="${1:-}"
    cat > /tmp/afasfr-containerterms-payload.php <<'PHP'
<?php
$apply = APPLY_PLACEHOLDER;
$skip = ['product_type', 'product_visibility', 'product_shipping_class'];
$taxen = array_diff(get_object_taxonomies('product'), $skip);
$containers = get_posts(['post_type' => 'product', 'post_status' => ['publish', 'private'],
    'numberposts' => -1, 'fields' => 'ids', 'suppress_filters' => true,
    'tax_query' => [['taxonomy' => 'product_type', 'field' => 'slug', 'terms' => 'variable']]]);
$totaal = 0;
foreach ($containers as $cid) {
    $kinderen = get_children(['post_parent' => $cid, 'post_type' => 'product_variation',
        'post_status' => ['publish', 'private'], 'fields' => 'ids']);
    if (!$kinderen) { continue; }
    $regels = [];
    // product_cat/product_tag: losse term-toewijzing volstaat voor archieven
    foreach (['product_cat', 'product_tag'] as $tax) {
        $unie = [];
        foreach ($kinderen as $kid) {
            foreach (wp_get_object_terms($kid, $tax, ['fields' => 'ids']) as $tid) {
                $unie[$tid] = true;
            }
        }
        $huidig = wp_get_object_terms($cid, $tax, ['fields' => 'ids']);
        $huidig = is_wp_error($huidig) ? [] : $huidig;
        $doel = array_keys($unie);
        if ($tax === 'product_cat' && $doel !== []) {
            // "Uncategorized" weghalen zodra er echte categorieën zijn
            $unc = (int) get_option('default_product_cat');
            $doel = array_values(array_diff(array_unique(array_merge($huidig, $doel)), [$unc]));
        } else {
            $doel = array_values(array_unique(array_merge($huidig, $doel)));
        }
        sort($doel); $h = $huidig; sort($h);
        if ($doel === [] || $doel === $h) { continue; }
        $regels[] = sprintf("%s: %d -> %d termen", $tax, count($h), count($doel));
        if ($apply) { wp_set_object_terms($cid, $doel, $tax); }
    }
    // pa_*: als échte WC-attributen op de container (filters/lookup-tabel
    // lezen uit de attribuut-config, niet uit losse termen); bestaande
    // attributen (o.a. de variatie-as van de plugin) blijven staan
    $product = wc_get_product($cid);
    $attrs = $product->get_attributes();
    $nieuw = 0;
    foreach ($taxen as $tax) {
        if (!str_starts_with($tax, 'pa_') || isset($attrs[$tax])) { continue; }
        $unie = [];
        foreach ($kinderen as $kid) {
            foreach (wp_get_object_terms($kid, $tax, ['fields' => 'ids']) as $tid) {
                $unie[$tid] = true;
            }
        }
        if ($unie === []) { continue; }
        $a = new WC_Product_Attribute();
        $a->set_id(wc_attribute_taxonomy_id_by_name($tax));
        $a->set_name($tax);
        $a->set_options(array_keys($unie));
        $a->set_visible(true);
        $a->set_variation(false);
        $attrs[$tax] = $a;
        $nieuw++;
    }
    if ($nieuw > 0) {
        $regels[] = sprintf("attributen: +%d (pa_*)", $nieuw);
        if ($apply) {
            $product->set_attributes($attrs);
            $product->save(); // triggert ook de attributes-lookup-tabel
        }
    }
    if ($regels) {
        printf("%s  #%d %s\n    %s\n", $apply ? 'GEZET' : 'ZOU ZETTEN', $cid,
            get_the_title($cid), implode(' · ', $regels));
        $totaal++;
    }
}
printf("--- %s: %d containers bijgewerkt\n", $apply ? 'APPLY' : 'DRY-RUN', $totaal);
PHP
    if [[ "$apply" == "apply" ]]; then
        sed -i 's/APPLY_PLACEHOLDER/true/' /tmp/afasfr-containerterms-payload.php
    else
        sed -i 's/APPLY_PLACEHOLDER/false/' /tmp/afasfr-containerterms-payload.php
    fi
    wpr_stdin eval-file - < /tmp/afasfr-containerterms-payload.php
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — niets gewijzigd. Draai '$0 stap14 apply' om te schrijven."
    fi
}

# ---------------------------------------------------------------------------
# Stap 15 — Oude AED-producten OMVORMEN tot familie-containers (les uit de
# revendeurs-migratie, work/handoff-variabele-containers-omvormen.md: nooit
# content weggooien en kaal herbouwen). Het oude product behoudt ID, titel,
# slug, beschrijving, foto's, categorieën en menu-links en wordt zelf het
# variable product met head-koppeling (SKU <head>-wpbase); de plugin-sync
# vult/actualiseert de variaties eronder.
# Dekt twee toestanden (idempotent):
#   - verse pull: oude post is simple product -> direct omvormen
#     (draai stap15 dan VÓÓR stap10, dan ontstaan er nooit kale containers);
#   - huidige kopie: oude post is al variatie onder een kale sync-container
#     -> terug-omvormen, sibling-variaties omhangen, kale container strippen
#     en trashen.
# Bron: work/defibsolutionsfr-omzet-aed.csv (OMZETTEN-rijen) + AFAS-cache
# (head = Itemcode_Parent van doel_base). Draai na apply altijd stap10
# zonder-prijzen + stap14. Default dry-run; `stap15 apply` schrijft.
# ---------------------------------------------------------------------------
stap15() {
    controleer_config
    local apply="${1:-}"
    local cache="$REPO_ROOT/work/cache/afas-artikelen-defibsolutionsfr.json"
    local omzet="$REPO_ROOT/work/defibsolutionsfr-omzet-aed.csv"
    [[ -f "$cache" ]] || { echo "FOUT: $cache ontbreekt" >&2; exit 1; }
    [[ -f "$omzet" ]] || { echo "FOUT: $omzet ontbreekt" >&2; exit 1; }

    python3 - "$cache" "$omzet" "$apply" <<'PY' > /tmp/afasfr-omvorm-payload.php
import csv, json, sys
cache, omzet, apply = sys.argv[1], sys.argv[2], sys.argv[3] == "apply"
per_code = {}
for a in json.load(open(cache)):
    per_code.setdefault(str(a.get("Itemcode") or ""), a)
plan = []
for r in csv.DictReader(open(omzet, encoding="utf-8-sig"), delimiter=";"):
    if r["status"].strip() != "OMZETTEN" or not r["doel_base"].strip():
        continue
    base = r["doel_base"].strip()
    head = str(per_code.get(base, {}).get("Itemcode_Parent") or "").strip() or base
    plan.append({"wc": int(r["wc_id"]), "base": base, "head": head,
                 "titel": r["shop_naam"].strip()})
print(f"// {len(plan)} omvormingen", file=sys.stderr)
print("<?php")
print(f"$apply = {'true' if apply else 'false'};")
print(f"$plan = json_decode('{json.dumps(plan)}', true);")
print(r"""
global $wpdb;
$n = 0;
foreach ($plan as $p) {
    $post = get_post($p['wc']);
    if (!$post) { printf("LET OP: wc:%d bestaat niet — overslaan\n", $p['wc']); continue; }
    $sku = (string) get_post_meta($post->ID, '_sku', true);
    $meta = (string) get_post_meta($post->ID, '_afas_artikelnummer', true);
    if ($post->post_type === 'product' && $meta === $p['head'] && str_ends_with($sku, '-wpbase')) {
        continue; // al omgevormd
    }
    $n++;
    if ($post->post_type === 'product_variation') {
        // huidige kopie: terug-omvormen + kale sync-container opruimen
        $oudeContainer = (int) $post->post_parent;
        $siblings = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d
              AND post_type = 'product_variation' AND ID <> %d", $oudeContainer, $post->ID));
        printf("%s  wc:%d '%s' wordt container voor head %s (%d sibling-variaties omgehangen, kale container #%d weg)\n",
            $apply ? 'OMGEVORMD' : 'ZOU OMVORMEN', $post->ID, $post->post_title,
            $p['head'], count($siblings), $oudeContainer);
        if ($apply) {
            // titel terug naar de originele productnaam (de sync had hem
            // hernoemd naar "container - variatie"); slug blijft origineel
            wp_update_post(['ID' => $post->ID, 'post_type' => 'product',
                'post_parent' => 0, 'post_title' => $p['titel']]);
            wp_set_object_terms($post->ID, 'variable', 'product_type');
            update_post_meta($post->ID, '_afas_artikelnummer', $p['head']);
            update_post_meta($post->ID, '_sku', $p['head'] . '-wpbase');
            $wpdb->update($wpdb->prefix . 'wc_product_meta_lookup',
                ['sku' => $p['head'] . '-wpbase'], ['product_id' => $post->ID]);
            foreach ($siblings as $vid) {
                wp_update_post(['ID' => (int) $vid, 'post_parent' => $post->ID]);
            }
            // kale plugin-container strippen (nooit meer laten matchen) + weg
            delete_post_meta($oudeContainer, '_afas_artikelnummer');
            update_post_meta($oudeContainer, '_sku', '');
            $wpdb->update($wpdb->prefix . 'wc_product_meta_lookup',
                ['sku' => ''], ['product_id' => $oudeContainer]);
            wp_trash_post($oudeContainer);
        }
    } else {
        // verse pull: simple product direct omvormen; sync bouwt variaties
        printf("%s  wc:%d '%s' (simple) wordt container voor head %s\n",
            $apply ? 'OMGEVORMD' : 'ZOU OMVORMEN', $post->ID, $post->post_title, $p['head']);
        if ($apply) {
            wp_set_object_terms($post->ID, 'variable', 'product_type');
            update_post_meta($post->ID, '_afas_artikelnummer', $p['head']);
            update_post_meta($post->ID, '_sku', $p['head'] . '-wpbase');
            $wpdb->update($wpdb->prefix . 'wc_product_meta_lookup',
                ['sku' => $p['head'] . '-wpbase'], ['product_id' => $post->ID]);
        }
    }
}
if ($apply && function_exists('wc_delete_product_transients')) { wc_delete_product_transients(); }
printf("--- %s: %d omvormingen\n", $apply ? 'APPLY' : 'DRY-RUN', $n);
""")
PY

    wpr_stdin eval-file - < /tmp/afasfr-omvorm-payload.php
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — niets gewijzigd. Draai '$0 stap15 apply', daarna stap10 zonder-prijzen + stap14 apply."
    fi
}

# ---------------------------------------------------------------------------
# Stap 16 — Franse variatie-assen, geport van revendeurs-stap12 (zie
# work/handoff-variabele-containers-omvormen.md). Elke container krijgt:
#   pa_langue         <- group_bases.language_code ("FR/EN/NL" -> "Français · Anglais · Néerlandais")
#   pa_connectivite   <- group_bases.variant_label (leeg -> "Aucune", WiFi -> "Wi-Fi", …)
#   pa_capteur-rcp    <- variant_label met/zonder CPR-sensor -> Avec/Sans
#   pa_options        <- accessoires.naam_kort_fr (geen accessoire -> "Défibrillateur")
# Een as wordt variatie-attribuut bij >1 waarde, anders vast. De platte
# "Naam"-as van de plugin verdwijnt. Term-volgorde via termmeta `order`
# (WC 3.6+). FR-afwijking t.o.v. revendeurs: titels blijven de originele
# shop-titels (stap15). Default dry-run; `stap16 apply` schrijft.
# Draai na stap15 + stap10.
# ---------------------------------------------------------------------------
stap16() {
    controleer_config
    local apply="${1:-}"
    local snapshot="$REPO_ROOT/tmp/samenstellingen.sqlite"
    [[ -f "$snapshot" ]] || { echo "FOUT: $snapshot ontbreekt (tool-snapshot nodig)" >&2; exit 1; }
    mkdir -p "$REPO_ROOT/tmp"
    local prefix
    prefix=$(wpr db prefix | tail -1 | tr -d '[:space:]')

    # shop-dump: variable containers + hun variaties met artikelnummer
    local dump="$REPO_ROOT/tmp/defibsfr-varianten-dump.tsv"
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

    python3 - "$snapshot" "$dump" "$apply" <<'PY' > "$REPO_ROOT/tmp/afasfr-assen-payload.php"
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

    // FR: containertitels bewust NIET hernoemen — de originele shop-titels
    // (hersteld in stap15) blijven leidend.

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

    wpr_stdin eval-file - < "$REPO_ROOT/tmp/afasfr-assen-payload.php"
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — niets gewijzigd. Draai '$0 stap16 apply' om te schrijven."
    fi
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
  stap9   [apply]  BeRocket-filterbalk: pad-cache regenereren na pull
  stap10  [zonder-prijzen|delta]  Syncs draaien (artikelen/prijzen/relaties + 2x wc-sync)
  stap11  [apply]  Structuur-opruiming: simples die variatie horen te zijn
  stap12  [apply]  /boutique-restanten strippen uit content-URLs
  stap13           FontAwesome terugzetten (node_modules-exclude-gat)
  stap14  [apply]  Containers erven taxonomie-termen van variaties
  stap15  [apply]  Oude AED-producten omvormen tot containers (content behouden)
  stap16  [apply]  Franse variatie-assen (langue/connectivite/capteur-rcp/options)

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
    stap9) stap9 "${2:-}" ;;
    stap10) shift; stap10 "$@" ;;
    stap11) stap11 "${2:-}" ;;
    stap12) stap12 "${2:-}" ;;
    stap13) stap13 ;;
    stap14) stap14 "${2:-}" ;;
    stap15) stap15 "${2:-}" ;;
    stap16) stap16 "${2:-}" ;;
    *) hulp; [[ -n "${1:-}" ]] && exit 1 || exit 0 ;;
esac
