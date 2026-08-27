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

  stap5   [apply]  API-keys inventariseren (apply geblokkeerd: Shopctrl-beslispunt)

Zie MIGRATIE-DEFIBSOLUTIONS-FR.md voor het fase-overzicht.
EOF
}

case "${1:-}" in
    stap5) stap5 "${2:-}" ;;
    *) hulp; [[ -n "${1:-}" ]] && exit 1 || exit 0 ;;
esac
