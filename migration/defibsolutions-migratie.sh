#!/usr/bin/env bash
#
# Migratiescript DefibSolutions-webshop → nieuwe server (B2BKing → lefcreative-afas-b2b).
# Wordt stap voor stap opgebouwd; elke stap is een aparte functie en wordt expliciet
# per naam aangeroepen — geen "alles in één keer".
#
#   ./migration/defibsolutions-migratie.sh stap1
#
# Serverconfig komt uit de project-.env (repo-root), net als de AFAS-credentials:
#   DEFIBS_SERVER   ssh-host van de nieuwe server, bv. defibrion-defibsolutions@1.2.3.4
#   DEFIBS_WP_ROOT  pad naar de WordPress-root op die server
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

SERVER="${DEFIBS_SERVER:-INVULLEN-user@nieuwe-server}"
WP_ROOT="${DEFIBS_WP_ROOT:-INVULLEN-/pad/naar/wordpress}"

wpr() {
    # wp-cli op de nieuwe server, uitgevoerd vanuit de WP-root.
    # De server-PHP is nieuwer dan de oude plugins/het theme; de "Deprecated:"-
    # meldingen vervuilen elke stap-output en worden hier weggefilterd.
    ssh "$SERVER" "cd '$WP_ROOT' && wp $*" 2>&1 | { grep -vE '^(Deprecated|Notice):' || true; }
}

controleer_config() {
    if [[ "$SERVER" == INVULLEN-* || "$WP_ROOT" == INVULLEN-* ]]; then
        echo "FOUT: zet eerst DEFIBS_SERVER en DEFIBS_WP_ROOT (zie kop van dit script)." >&2
        exit 1
    fi
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
    echo "OK — mail staat uit op $SERVER"
}

# ---------------------------------------------------------------------------
# Stap 2 — Jetpack UIT.
# Jetpack hoort niet mee te draaien tijdens/na de migratie (externe koppelingen,
# mails, stats). Alleen deactiveren; verwijderen kan in de eindschoonmaak.
# ---------------------------------------------------------------------------
stap2() {
    controleer_config
    if ! wpr plugin is-installed jetpack; then
        echo "jetpack is niet geïnstalleerd op $SERVER — niets te doen"
        return 0
    fi
    wpr plugin deactivate jetpack
    echo "--- controle:"
    wpr plugin list | grep -i jetpack
    echo "OK — jetpack staat uit op $SERVER"
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

    ssh "$SERVER" "cd '$WP_ROOT' && wp eval-file -" < /tmp/afas-relatie-payload.php \
        2>&1 | { grep -vE '^(Deprecated|Notice):' || true; }
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
    echo "upload $(basename "$zip") ..."
    scp -q "$zip" "$SERVER:/tmp/lefcreative-afas-b2b.zip"
    wpr plugin install /tmp/lefcreative-afas-b2b.zip --force --activate

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
        ssh "$SERVER" "cd '$WP_ROOT' && wp eval-file -" < /tmp/afas-settings-payload.php \
            2>&1 | { grep -vE '^(Deprecated|Notice):' || true; }
    else
        echo "LET OP: $settings ontbreekt — plugin actief maar zonder settings-import."
    fi

    echo "--- controle:"
    wpr plugin list | grep -i lefcreative
    wpr option get afas_env_type
    echo "OK — plugin actief + settings geimporteerd op $SERVER"
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
    userids=$(wpr db query "\"SELECT user_id FROM wp_usermeta WHERE meta_key='_application_passwords'\"" --skip-column-names)
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
    wpr db query "\"SELECT COUNT(*) AS app_passwords FROM wp_usermeta WHERE meta_key='_application_passwords'\""
    echo "OK — alle API-keys ingetrokken op $SERVER"
}

# ---------------------------------------------------------------------------
usage() {
    echo "gebruik: $0 <stap>"
    echo "stappen:"
    echo "  stap1   Mail UIT: disable-emails installeren + activeren (nieuwe server)"
    echo "  stap2   Jetpack UIT: plugin deactiveren (nieuwe server)"
    echo "  stap3   Klanten koppelen aan AFAS-relaties uit work/klant-relatie-mapping.csv (dry-run; 'stap3 apply' schrijft)"
    echo "  stap4   lefcreative-afas-b2b installeren + activeren + afas-settings importeren (work/)"
    echo "  stap5   API-keys intrekken: WooCommerce REST-keys + application passwords (dry-run; 'stap5 apply' verwijdert)"
    exit 1
}

case "${1:-}" in
    stap1) stap1 ;;
    stap2) stap2 ;;
    stap3) stap3 "${2:-}" ;;
    stap4) stap4 ;;
    stap5) stap5 "${2:-}" ;;
    *) usage ;;
esac
