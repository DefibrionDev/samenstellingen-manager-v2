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

usage() {
    cat <<EOF
Gebruik: $0 <stap> [apply]

  REVEND_TARGET=lokaal (default) of cp01

Stappen:
  stap1         Mail uit (disable-emails installeren + activeren)
  stap2         wp-staging(-pro), litespeed-cache, jetpack, slimstat uit
  stap3 [apply] Klanten koppelen aan AFAS-relaties (usermeta afas_relatie_id)

Volgende stappen (nog te bouwen, zie MIGRATIE-REVENDEURS.md):
  plugin + settings · API-keys intrekken · voorkoppeling _afas_artikelnummer ·
  Wholesale Suite uit · syncs · checkout
EOF
    exit 1
}

[[ $# -ge 1 ]] || usage
case "$1" in
    stap1|stap2|stap3) "$@" ;;
    *) usage ;;
esac
