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
# Bron: work/klant-relatie-mapping-fr.csv (wc_user_id;afas_relatie_id) — nog
# op te bouwen zodra het bron-beslispunt (orderhistorie vs handmatig, 88
# klanten) beslist is. Users zonder mapping-rij blijven bewust ongekoppeld.
# Default dry-run (toont ook het e-mailadres van de user ter verificatie);
# `stap3 apply` schrijft echt.
# ---------------------------------------------------------------------------
stap3() {
    controleer_config
    local mapping="$REPO_ROOT/work/klant-relatie-mapping-fr.csv"
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
# Stap 5 — Alle API-keys van de shop inventariseren/intrekken.
# defibsolutions.fr heeft vier read_write REST-keys: Shopctrl, 2× Improvit en
# Dashboard. Na de migratie mag niets van buitenaf meer muteren — de nieuwe
# plugin praat zelf uitgaand met AFAS. LET OP: het lot van de Shopctrl-key is
# een open beslispunt (MIGRATIE-DEFIBSOLUTIONS-FR.md) — tot Cas beslist blijft
# deze stap dry-run-only en weigert hij apply.
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
        echo "Dry-run — niets ingetrokken. Apply is geblokkeerd tot het Shopctrl-beslispunt beslist is."
        return 0
    fi

    echo "FOUT: apply geblokkeerd — beslispunt Shopctrl staat nog open (zie MIGRATIE-DEFIBSOLUTIONS-FR.md)." >&2
    exit 1
}

hulp() {
    cat <<EOF
Gebruik: $0 <stap> [apply|opties]   (DEFIBSFR_TARGET=lokaal|cp01, default lokaal)

  stap1            Mail uit (disable-emails)
  stap2            Overbodige plugins uit (woocommerce-b2b, wp-staging, mainwp-child)
  stap3   [apply]  Klanten koppelen aan AFAS-relaties (mapping-CSV nog te maken)
  stap4            lefcreative-afas-b2b installeren + FR-settings + gordels
  stap5   [apply]  API-keys inventariseren (apply geblokkeerd: Shopctrl-beslispunt)

Zie MIGRATIE-DEFIBSOLUTIONS-FR.md voor het fase-overzicht.
EOF
}

case "${1:-}" in
    stap1) stap1 ;;
    stap2) stap2 ;;
    stap3) stap3 "${2:-}" ;;
    stap4) stap4 ;;
    stap5) stap5 "${2:-}" ;;
    *) hulp; [[ -n "${1:-}" ]] && exit 1 || exit 0 ;;
esac
