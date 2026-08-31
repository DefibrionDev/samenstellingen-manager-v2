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
        scp -q "$zip" "$SERVER:/tmp/lefcreative-afas-b2b.zip"
        wpr plugin install /tmp/lefcreative-afas-b2b.zip --force --activate
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
        # 20013-FR, 20014-FR) — niet voorkoppelen, stap10 ruimt op.
        if (r["afas_itemcode"] and r["status"] != "draft"
                and r["type"] != "variable" and r["oordeel"] != "KALE-AED-VARIATIE"):
            paren[r["wc_id"]] = r["afas_itemcode"]
print(f"// {len(paren)} voorkoppelingen uit {audit}", file=sys.stderr)
print("<?php")
print(f"$apply = {'true' if apply else 'false'};")
print(f"$map = json_decode('{json.dumps(paren)}', true);")
print("""
$gezet = $al = $overschreven = $onbekend = 0;
foreach ($map as $pid => $code) {
    $pid = (int) $pid;
    if (!get_post_status($pid)) { echo "ONBEKEND PRODUCT wc:$pid ($code)\n"; $onbekend++; continue; }
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
#   in:  wc-variation-threshold, variations-json-cache, checkout-ajax-fallback,
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
# Stap 10 — Afgekeurde klant-accounts verwijderen (besluit Cas 31 aug 2026):
#   wc:18  edwin@roelse.net    (Edwin Roelse — stond ambigu in de mapping)
#   wc:197 saliha@defibrion.nl (intern account)
# Beide geverifieerd: 0 orders, geen rol. Verwijderen wist ook hun usermeta
# (incl. evt. afas_relatie_id). Idempotent: al-verwijderde users worden
# overgeslagen. Default dry-run; `stap10 apply` verwijdert echt.
# ---------------------------------------------------------------------------
stap10() {
    controleer_config
    local apply="${1:-}"
    local uid
    for uid in 18 197; do
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

Volgende stappen (nog te bouwen, zie MIGRATIE-REVENDEURS.md):
  parent-containers opruimen (stap11) · variatie-assen · checkout-test
EOF
    exit 1
}

[[ $# -ge 1 ]] || usage
case "$1" in
    stap1|stap2|stap3|stap4|stap5|stap6|stap7|stap8|stap9|stap10) "$@" ;;
    *) usage ;;
esac
