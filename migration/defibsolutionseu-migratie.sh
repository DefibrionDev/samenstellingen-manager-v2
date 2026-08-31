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

# ---------------------------------------------------------------------------
# Stap 1 — Mail UIT.
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
# Stap 2 — Overbodige plugins UIT (EU-lijst, anders dan NL):
#   b2bking(+wholesale)  wordt vervangen door lefcreative-afas-b2b; data blijft
#                        in de database staan als inerte fallback
#   wp-staging(-pro)     staging/backup van de oude hosting; nutteloos op de
#                        kopie/cp-01 en zo'n geheugenvreter dat wp-cli zonder
#                        verhoogde memory_limit al bij het booten OOM't
#   wp-rocket            page-cache met live-paden; op de kopie alleen maar
#                        stale-cache-verwarring — na livegang bewust opnieuw
#                        beoordelen
#   mainwp-child         remote beheer vanaf het oude MainWP-dashboard; dat
#                        mag de nieuwe omgeving niet kunnen muteren
# Geen jetpack/mailchimp op deze shop (wél guard, voor het geval een verse
# pull ze terugbrengt). Points & rewards (2 plugins) blijven bewust AAN tot
# beslispunt B3 (MIGRATIE-DEFIBSOLUTIONS-EU.md) is beslist.
# Alleen deactiveren; verwijderen kan in de eindschoonmaak.
# ---------------------------------------------------------------------------
stap2() {
    controleer_config
    local p
    for p in b2bking-wholesale-for-woocommerce b2bking wp-staging-pro wp-staging wp-rocket mainwp-child jetpack mailchimp-for-woocommerce; do
        if wpr plugin is-installed "$p" >/dev/null 2>&1; then
            wpr plugin deactivate "$p"
        else
            echo "$p is niet geïnstalleerd op $(doel_naam) — overslaan"
        fi
    done
    # wp-staging laat bij deactivatie zijn mu-plugin (wp-staging-optimizer.php)
    # achter als unlink faalt — lokaal is dat bestand van de host-user en mag
    # uid 33 het niet weg-unlinken. Als root opruimen; op cp01 is de site-user
    # eigenaar en volstaat een gewone rm.
    if [[ "$TARGET" == "lokaal" ]]; then
        _lokaal_compose run --rm -T --user 0 wpcli \
            rm -f /var/www/html/wp-content/mu-plugins/wp-staging-optimizer.php 2>/dev/null || true
    else
        ssh "$SERVER" "rm -f '$WP_ROOT/wp-content/mu-plugins/wp-staging-optimizer.php'"
    fi
    echo "--- controle:"
    wpr plugin list | { grep -iE 'b2bking|wp-staging|wp-rocket|mainwp|jetpack|mailchimp' || echo "(niets gevonden)"; }
    echo "OK — b2bking + wp-staging + wp-rocket + mainwp-child staan uit op $(doel_naam)"
}

# ---------------------------------------------------------------------------
# Stap 3 — Klanten koppelen aan AFAS-verkooprelaties (usermeta afas_relatie_id,
# het veld waar lefcreative-afas-b2b op draait).
# Bron: work/defibsolutionseu-klant-relatie-mapping.csv (wc_user_id;afas_relatie_id)
# — orderhistorie-methode zoals NL, mét e-mailverificatie omdat de kale
# WC-nummers in AFAS uit meerdere shops komen (zie
# work/mine-order-koppeling-defibsolutionseu.py). Alleen geverifieerde,
# eenduidige koppelingen staan in de CSV; users zonder orderbewijs blijven
# bewust ongekoppeld.
# Default dry-run (toont ook het e-mailadres van de user ter verificatie);
# `stap3 apply` schrijft echt.
# ---------------------------------------------------------------------------
stap3() {
    controleer_config
    local mapping="$REPO_ROOT/work/defibsolutionseu-klant-relatie-mapping.csv"
    local apply="${1:-}"
    [[ -f "$mapping" ]] || { echo "FOUT: $mapping ontbreekt (draai work/mine-order-koppeling-defibsolutionseu.py)" >&2; exit 1; }

    python3 - "$mapping" "$apply" <<'PY' > /tmp/afas-relatie-payload-eu.php
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

    wpr_stdin eval-file - < /tmp/afas-relatie-payload-eu.php
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — niets geschreven. Draai '$0 stap3 apply' om echt te schrijven."
    fi
}

# ---------------------------------------------------------------------------
# Stap 5 — Alle API-keys van de shop intrekken.
# Na de migratie mag niets van buitenaf meer muteren — de nieuwe plugin praat
# zelf uitgaand met AFAS en heeft geen inkomende REST-key nodig. Alles gaat
# weg; wie later weer toegang nodig heeft maakt bewust een nieuwe key aan.
# Default dry-run (toont wat er staat); `stap5 apply` verwijdert echt.
# Tabelprefix is wp_ (geverifieerd via wp_posts/wp_postmeta-queries).
# Nummering volgt het NL-script (stap 3/4 = klantkoppeling/plugin-install,
# wachten op EU-mapping-CSV en EU-afas-settings).
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

usage() {
    echo "gebruik: $0 <stap>" >&2
    echo ""
    echo "  stap0   Rooktest: config-check + wp-versie op het target (read-only)"
    echo "  stap1   Mail UIT: disable-emails installeren + activeren"
    echo "  stap2   Overbodige plugins UIT: b2bking + wp-staging + wp-rocket + mainwp-child"
    echo "  stap3   Klanten koppelen aan AFAS-relaties uit work/defibsolutionseu-klant-relatie-mapping.csv (dry-run; 'stap3 apply' schrijft)"
    echo "  stap5   API-keys intrekken: WooCommerce REST-keys + application passwords (dry-run; 'stap5 apply' verwijdert)"
    echo ""
    echo "Stap 4 (plugin + settings) volgt zodra de EU-afas-settings er zijn —"
    echo "zie MIGRATIE-DEFIBSOLUTIONS-EU.md."
    exit 1
}

case "${1:-}" in
    stap0) stap0 ;;
    stap1) stap1 ;;
    stap2) stap2 ;;
    stap3) stap3 "${2:-}" ;;
    stap5) stap5 "${2:-}" ;;
    *) usage ;;
esac
