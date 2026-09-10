#!/usr/bin/env bash
#
# Runner defibsolutions.eu: de volledige herbouw-reeks na een verse pull, in de
# bewezen volgorde, met logging. Elke stap is idempotent; de reeks eindigt in
# een werkende shop zonder handwerk (spelregel "reproduceerbaar groen").
#
#   ./migration/defibsolutionseu-runner.sh            # lokaal (default)
#   DEFIBSEU_TARGET=cp01 ./migration/defibsolutionseu-runner.sh
#
# NL-livegang-les 4: `{ …; } | tee log` slikte exitcodes — daarom pipefail en
# een expliciete slotregel "KLAAR — …"; controleer die, niet alleen de exitcode.
# Les 5: pre-flight op alles waar stappen van lezen (docker-stack, snapshot).
#
# Volgorde en waarom:
#   1  mail uit                          — vóór alles wat mails kan triggeren
#   2  plugins uit                       — b2bking/wp-staging/wp-rocket/mainwp
#   3  klantkoppeling (apply)            — usermeta afas_relatie_id
#   4  plugin 2.1.1 + EU-settings        — order-push geforceerd uit
#   5  API-keys intrekken (apply)
#   7  mu-plugins                        — o.a. wcpt-cli-cache-fix vóór CLI-productsaves
#   9  weergave (swatches, checkout-velden)
#   15 Divi-/BeRocket-caches (apply)     — na de URL-rewrite van de pull
#   18 SKU-correcties (apply)            — vóór de voorkoppeling
#   6  voorkoppeling (apply)             — vóór frontend-verkeer en vóór de sync
#   16 containers/kale AED's omvormen    — VÓÓR de eerste sync: dan bouwt de
#                                          plugin nooit kale containers
#   17 beheerders AFAS-id (apply)        — vóór de relatie-sync
#   10 schrappingen (apply)
#   11 syncs, volledig (force)           — artikelen, prijzen, relaties, adressen, wc ×2
#   8  structuur-opruiming (apply)       — simples die variatie horen te zijn
#   11 syncs, zonder-prijzen delta       — na de opruiming
#   14 containers erven termen (apply)
#   12 variatie-assen (apply)
#   15 Divi-caches nogmaals (apply)      — na alle productwijzigingen
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SCRIPT="$REPO_ROOT/migration/defibsolutionseu-migratie.sh"
mkdir -p "$REPO_ROOT/tmp"
LOG="$REPO_ROOT/tmp/defibseu-runner-$(date +%Y%m%d-%H%M%S).log"
TARGET="${DEFIBSEU_TARGET:-lokaal}"

REEKS=(
    "stap1" "stap2" "stap3 apply" "stap4" "stap5 apply" "stap7" "stap9"
    "stap15 apply" "stap18 apply" "stap6 apply" "stap16 apply" "stap17 apply"
    "stap10 apply" "stap11" "stap8 apply" "stap11 zonder-prijzen delta"
    "stap14 apply" "stap12 apply" "stap15 apply"
)

# pre-flight
if [[ "$TARGET" == "lokaal" ]]; then
    docker ps --format '{{.Names}}' | grep -q 'defibsolutionseu-db-1' \
        || { echo "FOUT: EU-docker-stack draait niet (cd ~/projects/wordpress-migrater && docker compose --env-file .env-defibsolutionseu up -d)" >&2; exit 1; }
fi
[[ -f "$REPO_ROOT/tmp/samenstellingen.sqlite" ]] || { echo "FOUT: tool-snapshot ontbreekt (afas:pull)" >&2; exit 1; }
[[ -f "$REPO_ROOT/work/cache/afas-artikelen-defibsolutionseu.json" ]] || { echo "FOUT: AFAS-artikelcache ontbreekt (audit --vers)" >&2; exit 1; }

echo "runner defibsolutions.eu — target=$TARGET — log: $LOG"
t0=$(date +%s)
for stap in "${REEKS[@]}"; do
    echo ""
    echo "################ $stap ($(date +%H:%M:%S))"
    # shellcheck disable=SC2086
    "$SCRIPT" $stap
done 2>&1 | tee "$LOG"
rc=${PIPESTATUS[0]}
echo ""
if [[ $rc -eq 0 ]]; then
    echo "KLAAR — volledige reeks groen op $TARGET in $(( ($(date +%s) - t0) / 60 )) min; log: $LOG" | tee -a "$LOG"
else
    echo "GESTOPT — exit $rc; zie $LOG" | tee -a "$LOG"
fi
exit "$rc"
