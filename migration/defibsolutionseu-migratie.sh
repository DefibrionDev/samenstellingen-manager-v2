#!/usr/bin/env bash
#
# Migratiescript defibsolutions.eu (B2BKing → lefcreative-afas-b2b + verhuizing
# naar een nieuwe cp-01-site). Eigen kopie naast het NL-script (besluit Cas
# 27 aug 2026) — zelfde vorm: elke stap is een aparte functie en wordt expliciet
# per naam aangeroepen — geen "alles in één keer".
#
#   ./migration/defibsolutionseu-migratie.sh stap0
#
# Target-keuze (lokaal-eerst, zie MIGRATIE-DEFIBSOLUTIONS-EU.md):
#   DEFIBSEU_TARGET=lokaal  (default) draait elke stap in de wpcli-container van
#                           de lokale Docker-kopie (~/projects/wordpress-migrater,
#                           .env-defibsolutionseu, site op poort 8895)
#   DEFIBSEU_TARGET=cp01    draait exact dezelfde stap via ssh op cp-01
#
# Serverconfig komt uit de project-.env (repo-root):
#   DEFIBSEU_SERVER        ssh-host op cp-01 (site-user bestaat nog niet; fase 2)
#   DEFIBSEU_WP_ROOT       WordPress-root op cp-01 (idem)
#   DEFIBSEU_MIGRATER_DIR  pad naar wordpress-migrater (default ~/projects/wordpress-migrater)
# Environment-variabelen met dezelfde naam gaan vóór de .env-waarden.
#
# Fase-overzicht: MIGRATIE-DEFIBSOLUTIONS-EU.md · scriptvorm-blauwdruk:
# defibsolutions-migratie.sh (NL) — stappen worden per audit-uitkomst
# overgenomen, niet blind gekopieerd.
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

TARGET="${DEFIBSEU_TARGET:-lokaal}"
SERVER="${DEFIBSEU_SERVER:-INVULLEN-user@cp-01}"
WP_ROOT="${DEFIBSEU_WP_ROOT:-INVULLEN-/pad/naar/wordpress}"
MIGRATER_DIR="${DEFIBSEU_MIGRATER_DIR:-$HOME/projects/wordpress-migrater}"

# De PHP op beide targets is nieuwer dan de oude plugins/het theme; de
# "Deprecated:"-meldingen vervuilen elke stap-output en worden weggefilterd.
# --line-buffered: zonder dit houdt grep de output vast tot het eind en zie je
# fase-voortgang van lange stappen pas als alles klaar is.
_filter_ruis() { grep --line-buffered -vE '^(Deprecated|Notice):' || true; }

_lokaal_compose() {
    # --progress quiet: compose-statusregels ("Container ... Running") gaan
    # anders door de 2>&1-merge heen en vervuilen gevangen stap-output.
    docker compose --progress quiet --project-directory "$MIGRATER_DIR" \
        --env-file "$MIGRATER_DIR/.env-defibsolutionseu" "$@"
}

doel_naam() {
    [[ "$TARGET" == "lokaal" ]] && echo "lokale kopie (localhost:8895)" || echo "$SERVER"
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
        # volledig laadt; syncs hebben ruim geheugen nodig.
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
        if [[ ! -f "$MIGRATER_DIR/.env-defibsolutionseu" ]]; then
            echo "FOUT: $MIGRATER_DIR/.env-defibsolutionseu ontbreekt (zet evt. DEFIBSEU_MIGRATER_DIR)." >&2
            exit 1
        fi
        if ! _lokaal_compose ps --status=running 2>/dev/null | grep -q 'defibsolutionseu-db'; then
            echo "FOUT: lokale defibsolutionseu-stack draait niet. Start met:" >&2
            echo "  cd $MIGRATER_DIR && docker compose --env-file .env-defibsolutionseu up -d" >&2
            exit 1
        fi
        _lokaal_prep
    elif [[ "$TARGET" == "cp01" ]]; then
        if [[ "$SERVER" == INVULLEN-* || "$WP_ROOT" == INVULLEN-* ]]; then
            echo "FOUT: zet eerst DEFIBSEU_SERVER en DEFIBSEU_WP_ROOT (zie kop van dit script)." >&2
            exit 1
        fi
    else
        echo "FOUT: onbekend DEFIBSEU_TARGET '$TARGET' (lokaal of cp01)." >&2
        exit 1
    fi
    echo "[target: $TARGET]"
}

# ---------------------------------------------------------------------------
# Stap 0 — Rooktest: config + target bereikbaar, geen mutaties.
# Bewijst dat de targetlaag werkt vóór er echte stappen bestaan.
# ---------------------------------------------------------------------------
stap0() {
    controleer_config
    echo "doel: $(doel_naam)"
    wpr core version
    wpr option get blogname
}

usage() {
    echo "gebruik: $0 <stap>" >&2
    echo ""
    echo "  stap0   Rooktest: config-check + wp-versie op het target (read-only)"
    echo ""
    echo "Volgende stappen komen per fase uit MIGRATIE-DEFIBSOLUTIONS-EU.md."
    exit 1
}

case "${1:-}" in
    stap0) stap0 ;;
    *) usage ;;
esac
