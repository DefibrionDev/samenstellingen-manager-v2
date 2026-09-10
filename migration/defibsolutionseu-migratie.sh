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
#   DEFIBSEU_SERVER        ssh-host op cp-01 (defibsolutionseu@cp-01, site sinds 9 sep)
#   DEFIBSEU_WP_ROOT       WordPress-root op cp-01
#   DEFIBSEU_DB_NAME       verwachte DB-naam op cp-01 (vangrail in controleer_config)
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
        # Vangrail (les revendeurs 2 sep, memory migrater-deploy-dbnaam-verifieren):
        # de site op cp-01 moet op de verwachte database draaien — een weggevallen
        # wp-config-stap liet elders een dev op de live-db draaien.
        if [[ -n "${DEFIBSEU_DB_NAME:-}" ]]; then
            local echte_db
            echte_db=$(ssh "$SERVER" "cd '$WP_ROOT' && wp config get DB_NAME" 2>/dev/null | tail -1 | tr -d '[:space:]')
            if [[ "$echte_db" != "$DEFIBSEU_DB_NAME" ]]; then
                echo "FOUT: wp-config op $SERVER gebruikt database '$echte_db', verwacht '$DEFIBSEU_DB_NAME' — gestopt." >&2
                exit 1
            fi
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
# Stap 4 — lefcreative-afas-b2b plugin installeren + activeren + AFAS-settings.
# Bronnen (beide in work/, gitignored):
#   - work/lefcreative-afas-b2b-2.1.1.zip     versie bewust GEPIND (besluit
#     Cas 9 sep: 2.1.1 = reseller-live; ingepakt vanaf cp-01). Upgraden is
#     een aparte beslissing, niet iets dat een glob stilletjes doet.
#   - work/afas-settings-defibsolutionseu.json  gegenereerd door
#     work/maak-afas-settings-defibsolutionseu.py (sjabloon = reseller-dump;
#     EU-vlaggen, BHV-matchveld, order-push uit, checkout-veld Engels)
# --force + update_option maken her-runnen idempotent: de stap zet de shop
# altijd terug naar exact deze plugin-versie + settings-set.
# ---------------------------------------------------------------------------
stap4() {
    controleer_config
    local zip="$REPO_ROOT/work/lefcreative-afas-b2b-2.1.1.zip"
    [[ -f "$zip" ]] || { echo "FOUT: $zip ontbreekt" >&2; exit 1; }
    if [[ "$TARGET" == "lokaal" ]]; then
        # geen scp nodig: work/ read-only in de container mounten
        _lokaal_compose run "${_LOKAAL_RUN_OPTS[@]}" -v "$(dirname "$zip"):/defibs-work:ro" wpcli \
            sh -c "php -d memory_limit=512M /usr/local/bin/wp plugin install '/defibs-work/$(basename "$zip")' --force --activate" 2>&1 | _filter_ruis
    else
        echo "upload $(basename "$zip") ..."
        scp -q "$zip" "$SERVER:/tmp/lefcreative-afas-b2b.zip"
        wpr plugin install /tmp/lefcreative-afas-b2b.zip --force --activate
    fi

    local settings="$REPO_ROOT/work/afas-settings-defibsolutionseu.json"
    [[ -f "$settings" ]] || { echo "FOUT: $settings ontbreekt (draai work/maak-afas-settings-defibsolutionseu.py)" >&2; exit 1; }
    python3 - "$settings" <<'PY' > /tmp/afas-settings-payload-eu.php
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
    wpr_stdin eval-file - < /tmp/afas-settings-payload-eu.php

    # Veiligheidsgordel: order-push naar AFAS staat overal geforceerd uit —
    # lokaal altijd, en op cp-01 zolang we niet live zijn (bij de livegang
    # zet Cas hem bewust handmatig aan, samen met de order-vrije-velden).
    wpr option update afas_sync_orders_enabled 0 >/dev/null
    echo "(afas_sync_orders_enabled geforceerd op 0 — bij livegang handmatig aan)"
    # Plugin-cron temmen: de sync-jobs draaien elk uur en raceten bij NL op
    # cp-01 dwars door het migratievenster heen (cron-delta maakte een
    # geschrapt artikel opnieuw aan). Intervallen op een week; bij de
    # livegang zet Cas ze handmatig terug (zelfde slotlijstje als orders aan).
    local i
    for i in artikelen prijslijsten verkooprelaties kortingen prijzen woocommerce addresses; do
        wpr option update "afas_sync_${i}_interval" 604800 >/dev/null
    done
    echo "(sync-cron-intervallen op 1 week — bij livegang handmatig terugzetten)"
    if [[ "$TARGET" == "lokaal" ]]; then
        # Testklant voor checkout-tests: user 114 (Cardiocare DK, relatie
        # 31239 — geverifieerde koppeling uit stap3). Het wachtwoord komt
        # niet mee uit de live-dump, dus na elke verse pull opnieuw zetten.
        # Alleen lokaal — nooit live-wachtwoorden muteren.
        if wpr user get 114 --field=ID >/dev/null 2>&1; then
            wpr user update 114 --user_pass=defibseu-test-2026 >/dev/null
            echo "(lokaal: testklant Cardiocare/114 wachtwoord gezet: defibseu-test-2026)"
        fi
    fi
    echo "OK — plugin + settings staan op $(doel_naam)"
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

# ---------------------------------------------------------------------------
# Stap 6 — Voorkoppeling: per WC-product (publish/private, incl. variaties) de
# AFAS-itemcode in postmeta _afas_artikelnummer zetten. Zonder deze stap kan de
# artikelen-sync bestaande producten niet vinden (hij matcht op deze meta, met
# alleen SKU==itemcode als fallback) en maakt hij duplicaten aan. De plugin
# self-healt bovendien de SKU naar deze meta bij de eerste frontend-lees —
# deze stap moet dus vóór frontend-verkeer draaien. (Port van FR-stap6.)
#
# Doel-itemcode per product, in volgorde:
#   1. OMZETTEN-rij in work/defibsolutionseu-omzet-aed.csv (regel Cas: kale
#      AED's worden samenstellingen; doel_base = samenstellings-itemcode);
#   2. SKU matcht precies één actief AFAS-artikel op Artikelcode_BHV_Voordeelwinkel;
#   3. SKU is zelf een actieve AFAS-itemcode.
# Geblokkeerde artikelen (soft-delete, B-prefix) doen nooit mee — de 126
# GEBLOKKEERD-gevallen uit de audit (Reanibex-variaties met oude SKU's)
# blijven dus ongekoppeld en worden in de structuur-opruiming afgehandeld.
# Bron-artikelen: work/cache/afas-artikelen-defibsolutionseu.json (--vers via
# de koppelbaarheids-audit). Default dry-run; `stap6 apply` schrijft echt.
# ---------------------------------------------------------------------------
stap6() {
    controleer_config
    local apply="${1:-}"
    local cache="$REPO_ROOT/work/cache/afas-artikelen-defibsolutionseu.json"
    local omzet="$REPO_ROOT/work/defibsolutionseu-omzet-aed.csv"
    [[ -f "$cache" ]] || { echo "FOUT: $cache ontbreekt (draai de koppelbaarheids-audit met --vers)" >&2; exit 1; }
    [[ -f "$omzet" ]] || { echo "FOUT: $omzet ontbreekt (draai work/maak-omzet-aed-defibsolutionseu.py)" >&2; exit 1; }

    mkdir -p "$REPO_ROOT/tmp"
    local shopdump="$REPO_ROOT/tmp/defibseu-shop-skus.tsv"
    wpr db query "\"SELECT p.ID, p.post_type, COALESCE(sku.meta_value,''), COALESCE(an.meta_value,'') FROM wp_posts p LEFT JOIN wp_postmeta sku ON sku.post_id=p.ID AND sku.meta_key='_sku' LEFT JOIN wp_postmeta an ON an.post_id=p.ID AND an.meta_key='_afas_artikelnummer' WHERE p.post_type IN ('product','product_variation') AND p.post_status IN ('publish','private')\"" --skip-column-names > "$shopdump"

    python3 - "$cache" "$omzet" "$shopdump" "$apply" <<'PY' > /tmp/afaseu-voorkoppel-payload.php
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
    if huidig.endswith("-wpbase") or sku.endswith("-wpbase"):
        continue  # al omgevormde container — nooit terugzetten
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

    wpr_stdin eval-file - < /tmp/afaseu-voorkoppel-payload.php
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — niets geschreven. Draai '$0 stap6 apply' om echt te schrijven."
    fi
}

# ---------------------------------------------------------------------------
# Stap 7 — mu-plugins plaatsen uit migration/mu-plugins/. EXPLICIETE EU-lijst:
# de 8 generieke (FR-set) + wcpt-cli-cache-fix (EU draait wc-product-table-pro,
# zonder deze fix crasht elke CLI-productsave — dus ook de sync), points-pro-
# variable-price-fix (EU's ultimate-woocommerce-points-and-rewards ís de
# Points & Rewards Pro-klasse; points blijven aan, besluit B3) en
# afas-prijzen-orderby (NL-live-pariteit). NIET: de Divi/NL-huisstijl-restyles,
# de NL-login-fix en de ARKY-Kadence-tweak — die worden actief opgeruimd.
# Idempotent. Een verse pull (rsync --delete) haalt alles weer weg — deze
# stap hoort in elke herhaal-reeks.
# ---------------------------------------------------------------------------
_EU_MU_PLUGINS=(
    wc-variation-threshold.php variations-json-cache.php
    checkout-ajax-fallback.php afas-tracktrace-style.php
    order-email-afas-debiteur.php order-email-unit-prices.php
    afas-preview-winkelmanager.php shop-manager-login-as-klant.php
    wcpt-cli-cache-fix.php points-pro-variable-price-fix.php
    afas-prijzen-orderby.php
)
_EU_MU_VERBODEN=(defibs-checkout-restyle.php defibs-product-restyle.php
    defibs-login-fix.php checkout-coupon-points-right.php)

stap7() {
    controleer_config
    local bron="$REPO_ROOT/migration/mu-plugins" p
    [[ -d "$bron" ]] || { echo "FOUT: $bron ontbreekt" >&2; exit 1; }
    for p in "${_EU_MU_PLUGINS[@]}"; do
        [[ -f "$bron/$p" ]] || { echo "FOUT: $bron/$p ontbreekt" >&2; exit 1; }
    done

    if [[ "$TARGET" == "lokaal" ]]; then
        local content_dir
        content_dir=$(grep '^CONTENT_DIR=' "$MIGRATER_DIR/.env-defibsolutionseu" | cut -d= -f2)
        local doel="$MIGRATER_DIR/${content_dir#./}/mu-plugins"
        mkdir -p "$doel"
        for p in "${_EU_MU_PLUGINS[@]}"; do cp "$bron/$p" "$doel/"; done
        for p in "${_EU_MU_VERBODEN[@]}"; do rm -f "$doel/$p"; done
        echo "--- controle:"
        ls "$doel"
    else
        ssh "$SERVER" "mkdir -p '$WP_ROOT/wp-content/mu-plugins'"
        for p in "${_EU_MU_PLUGINS[@]}"; do
            scp -q "$bron/$p" "$SERVER:$WP_ROOT/wp-content/mu-plugins/"
        done
        ssh "$SERVER" "cd '$WP_ROOT/wp-content/mu-plugins' && rm -f ${_EU_MU_VERBODEN[*]}"
        echo "--- controle:"
        ssh "$SERVER" "ls '$WP_ROOT/wp-content/mu-plugins'"
    fi
    echo "OK — ${#_EU_MU_PLUGINS[@]} mu-plugins geplaatst op $(doel_naam)"
}

# ---------------------------------------------------------------------------
# Stap 9 — Weergave-instellingen conform reseller (NL-stap9-patroon, zónder de
# Divi-kaal-workaround: die was een misdiagnose, zie stap15).
#   - woo-variation-swatches: exact de reseller-configuratie (knoppen aan,
#     squared); anders worden de lange samenstellingsnamen ronde knopjes.
#   - checkout-velden: bedrijfsnaam hidden, telefoon/adresregel 2 optional —
#     WC-default zet telefoon required en B2B-klanten liepen daarop vast.
# Idempotent.
# ---------------------------------------------------------------------------
stap9() {
    controleer_config
    wpr_stdin eval-file - <<'PHP'
<?php
$reseller_conform = [
    'clear_on_reselect' => 'no',
    'hide_out_of_stock_variation' => 'yes',
    'clickable_out_of_stock_variation' => 'no',
    'attribute_behavior' => 'blur-no-cross',
    'attribute_image_size' => 'variation_swatches_image_size',
];
update_option('woo_variation_swatches', $reseller_conform);
delete_transient('woo_variation_swatches_cache');
echo "woo_variation_swatches: reseller-conform gezet (knoppen aan, squared)\n";
PHP
    wpr option update woocommerce_checkout_company_field hidden >/dev/null
    wpr option update woocommerce_checkout_phone_field optional >/dev/null
    wpr option update woocommerce_checkout_address_2_field optional >/dev/null
    echo "checkout-velden: bedrijfsnaam hidden, telefoon/adres2 optional (conform reseller)"
    echo "OK — weergave-instellingen gezet op $(doel_naam)"
}

# ---------------------------------------------------------------------------
# Stap 12 — Variatie-assen op de containers, Engelstalig (port van FR-stap16 /
# NL-stap12, EU-shop is en_US). Elke container krijgt uit de tool-snapshot:
#   pa_language      <- group_bases.language_code ("NL/EN/FR" -> "Dutch · English · French"), knoppen
#   pa_connectivity  <- group_bases.variant_label (leeg -> "None", WiFi -> "Wi-Fi", SIGFOX -> "Sigfox", …)
#   pa_cpr-sensor    <- variant_label met/zonder CPR-sensor -> With/Without
#   pa_options       <- accessoires.naam_kort_en (geen accessoire -> "Defibrillator")
# Een as wordt variatie-attribuut bij >1 waarde, anders vast. De platte
# "Naam"-as van de plugin verdwijnt. Term-volgorde via termmeta `order`
# (WC 3.6+; alleen order_pa_<tax> sorteert niet meer). Containertitels
# blijven de shop-titels (FR-afwijking, EU idem: al Engels). Default-keuze:
# English · None · Defibrillator. Default dry-run; `stap12 apply` schrijft.
# Draai na stap11 (containers + variaties bestaan) en stap16.
# ---------------------------------------------------------------------------
stap12() {
    controleer_config
    local apply="${1:-}"
    local snapshot="$REPO_ROOT/tmp/samenstellingen.sqlite"
    [[ -f "$snapshot" ]] || { echo "FOUT: $snapshot ontbreekt (tool-snapshot nodig)" >&2; exit 1; }
    mkdir -p "$REPO_ROOT/tmp"

    local dump="$REPO_ROOT/tmp/defibseu-varianten-dump.tsv"
    wpr db query "\"SELECT par.ID, COALESCE(pan.meta_value,''), v.ID, COALESCE(van.meta_value,'')
        FROM wp_posts par
        JOIN wp_term_relationships tr ON tr.object_id = par.ID
        JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_type'
        JOIN wp_terms t ON t.term_id = tt.term_id AND t.slug = 'variable'
        LEFT JOIN wp_postmeta pan ON pan.post_id = par.ID AND pan.meta_key = '_afas_artikelnummer'
        JOIN wp_posts v ON v.post_parent = par.ID AND v.post_type = 'product_variation'
             AND v.post_status IN ('publish','private')
        LEFT JOIN wp_postmeta van ON van.post_id = v.ID AND van.meta_key = '_afas_artikelnummer'
        WHERE par.post_type = 'product' AND par.post_status IN ('publish','private')\"" --skip-column-names > "$dump"

    python3 - "$snapshot" "$dump" "$apply" <<'PY' > "$REPO_ROOT/tmp/afaseu-assen-payload.php"
import json, sqlite3, sys
snapshot, dump, apply = sys.argv[1], sys.argv[2], sys.argv[3] == "apply"

TALEN = {"NL": "Dutch", "EN": "English", "FR": "French", "DE": "German", "ES": "Spanish",
         "IT": "Italian", "DK": "Danish", "NO": "Norwegian", "SE": "Swedish", "FI": "Finnish",
         "PL": "Polish", "CZ": "Czech", "SK": "Slovak", "SL": "Slovenian", "HU": "Hungarian",
         "HR": "Croatian", "EL": "Greek", "GA": "Irish", "PT": "Portuguese", "LV": "Latvian",
         "LT": "Lithuanian", "RO": "Romanian", "TR": "Turkish", "CH": "Swiss Edition",
         "UK": "English", "KR": "Croatian", "GR": "Greek", "DA": "Danish", "WAL": "Welsh"}
CONNECT = {"": "None", "USB": "USB", "WiFi": "Wi-Fi", "SIGFOX": "Sigfox", "4G": "4G",
           "GPS+WiFi+SIGFOX": "GPS+Wi-Fi+Sigfox"}
CPR = {"met CPR-sensor": "With", "zonder CPR-sensor": "Without"}

con = sqlite3.connect(snapshot)
opties_en = {code: (en or label) for code, en, label in con.execute(
    "SELECT itemcode, naam_kort_en, label FROM accessoires")}
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
# fallback voor variaties die de tool (nog) niet gematcht heeft: het AFAS-
# artikelnummer volgt het patroon <base>-<accessoire> (11142-EN-60110); base
# en accessoire zijn wél bekend in de tool, dus de assen zijn afleidbaar
bases_by_code = {code: (taal, label) for code, (taal, label, acc, basecode) in info.items() if acc is None}
acc_codes = set(opties_en)
con.close()

def afgeleid(var_code):
    delen = var_code.split("-")
    for i in range(len(delen) - 1, 0, -1):
        base, acc = "-".join(delen[:i]), "-".join(delen[i:])
        if base in bases_by_code and acc in acc_codes:
            taal, label = bases_by_code[base]
            return (taal, label, acc, base)
    return None

def taal_naam(code):
    delen = [TALEN.get(d.strip().upper(), d.strip()) for d in code.split("/") if d.strip()]
    # dubbele (UK+EN) ontdubbelen met behoud van volgorde
    uniek = []
    for d in delen:
        if d not in uniek:
            uniek.append(d)
    return " · ".join(uniek) if uniek else ""

containers, onbekend = {}, []
for regel in open(dump, encoding="utf-8"):
    d = regel.rstrip("\n").split("\t")
    if len(d) < 4 or not d[0].isdigit():
        continue
    par_id, par_code, var_id, var_code = d[0], d[1].strip(), d[2], d[3].strip()
    rij = info.get(var_code) or afgeleid(var_code)
    if rij is None:
        onbekend.append((var_id, var_code))
        continue
    taal, label, acc, basecode = rij
    cpr = CPR.get(label, "")
    containers.setdefault(par_id, {"code": par_code, "varianten": {}})
    containers[par_id]["varianten"][var_id] = {
        "Language": taal_naam(taal),
        "Connectivity": "None" if cpr else CONNECT.get(label, label or "None"),
        "CPR sensor": cpr,
        "Options": opties_en.get(acc, "Defibrillator") if acc else "Defibrillator",
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
$AS_TAX = ['Language' => 'pa_language', 'Connectivity' => 'pa_connectivity',
    'CPR sensor' => 'pa_cpr-sensor', 'Options' => 'pa_options'];
$gemaakt = $gezet = $overgeslagen = 0;

$slugVan = function (string $naam): string {
    return sanitize_title(str_replace(['·', '+'], [' ', ' '], $naam));
};

// Dropdown-volgorde: kaal toestel eerst, dan accessoires oplopend.
$VOLGORDE = [
    'pa_language' => ['English'],
    'pa_connectivity' => ['None', 'USB', 'Wi-Fi', '4G', 'Sigfox', 'GPS+Wi-Fi+Sigfox'],
    'pa_cpr-sensor' => ['With', 'Without'],
    'pa_options' => ['Defibrillator', 'Backpack', 'ARKY Indoor Cabinet (White)',
        'ARKY Indoor Cabinet (Green)', 'ARKY Outdoor Cabinet (Unheated)',
        'ARKY Outdoor Cabinet (Heated)', 'ARKY Core Classic Outdoor Cabinet',
        'ARKY Core Plus Outdoor Cabinet', 'Defibtech Carry Bag', 'Mindray Carry Bag'],
];
$DEFAULT_VOORKEUR = ['pa_language' => 'English', 'pa_connectivity' => 'None',
    'pa_options' => 'Defibrillator'];

foreach ($AS_TAX as $label => $tax) {
    if (taxonomy_exists($tax)) { continue; }
    if (!$apply) { echo "ZOU AANMAKEN attribuut: $label ($tax)\n"; continue; }
    $id = wc_create_attribute(['name' => $label, 'slug' => str_replace('pa_', '', $tax),
        'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false]);
    if (is_wp_error($id)) { echo "FOUT attribuut $label: " . $id->get_error_message() . "\n"; continue; }
    register_taxonomy($tax, 'product', ['hierarchical' => false, 'show_ui' => false, 'query_var' => true]);
    echo "attribuut aangemaakt: $label ($tax)\n";
}

// pa_language als knoppen (zoals pa_taal op reseller)
if ($apply) {
    $wpdb->update($wpdb->prefix . 'woocommerce_attribute_taxonomies',
        ['attribute_type' => 'button'], ['attribute_name' => 'language']);
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

    $assenTekst = [];
    foreach ($AS_TAX as $label => $tax) {
        if (empty($waarden[$label])) { continue; }
        $n = count($waarden[$label]);
        $assenTekst[] = str_replace('pa_', '', $tax) . '=' . $n . ($n > 1 ? '' : ' (vast)');
    }
    printf("%s  container #%d [%s] '%s': %s\n", $apply ? 'GEZET' : 'ZOU ZETTEN',
        (int) $parId, $data['code'] ?: '-', get_the_title((int) $parId), implode(', ', $assenTekst));

    if (!$apply) { continue; }

    $parent->set_attributes($attributes);

    $defaults = [];
    foreach ($AS_TAX as $label => $tax) {
        if (!isset($attributes[$tax]) || !$attributes[$tax]->get_variation()) { continue; }
        $voorkeur = $tax === 'pa_cpr-sensor'
            ? ($data['cpr_default'] ?? 'With')
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

    wpr_stdin eval-file - < "$REPO_ROOT/tmp/afaseu-assen-payload.php"
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — niets gewijzigd. Draai '$0 stap12 apply' om te schrijven."
    fi
}

# ---------------------------------------------------------------------------
# Stap 14 — Containers erven taxonomie-termen van hun variaties (FR-stap14-
# patroon). De wc-sync maakt/vult familie-containers kaal ("Uncategorized",
# geen attributen); de variaties dragen de categorie-/filter-termen — maar
# variaties verschijnen niet in archieven/filters, dus de AED's zijn onvindbaar.
# Zet per variable container de UNIE van de termen van zijn variaties
# (product_cat/product_tag als termen; pa_* als échte WC-attributen, want
# filters en de attributes-lookup lezen uit de attribuut-config), en haalt
# "Uncategorized" weg zodra er echte categorieën zijn. Idempotent.
# Default dry-run; `stap14 apply` schrijft.
# ---------------------------------------------------------------------------
stap14() {
    controleer_config
    local apply="${1:-}"
    cat > /tmp/afaseu-containerterms-payload.php <<'PHP'
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
            $product->save();
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
        sed -i 's/APPLY_PLACEHOLDER/true/' /tmp/afaseu-containerterms-payload.php
    else
        sed -i 's/APPLY_PLACEHOLDER/false/' /tmp/afaseu-containerterms-payload.php
    fi
    wpr_stdin eval-file - < /tmp/afaseu-containerterms-payload.php
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — niets gewijzigd. Draai '$0 stap14 apply' om te schrijven."
    fi
}

# ---------------------------------------------------------------------------
# Stap 17 — Beheerders een AFAS-id + sync-pauze geven (FR-stap17-patroon, zie
# work/handoff-beheerders-afas-koppeling.md). Zonder afas_relatie_id ziet een
# admin een "kapotte" checkout (geen adres-dropdown, geen klantprijzen).
# Testrelatie: 31239 (Cardiocare Scandinavia — actieve EU-klant, gevlagd +
# gesynct; ook de testklant van stap4). afas_sync_paused=1 voorkomt dat de
# relatie-sync naam/e-mail van het admin-account overschrijft; het
# factuuradres komt wél mee (bewust). Alleen lege koppelingen vullen;
# idempotent. In de herhaal-reeks vóór stap11. Default dry-run; `stap17 apply`.
# NB: stap17 heette eerder "SKU-correcties"; die is nu stap18.
# ---------------------------------------------------------------------------
stap17() {
    controleer_config
    local apply="${1:-}"
    wpr_stdin eval-file - "$apply" <<'PHP'
<?php
$apply = ('apply' === ($args[0] ?? ''));
$relatie = '31239';
foreach (get_users(['role' => 'administrator']) as $u) {
    $huidig = (string) get_user_meta($u->ID, 'afas_relatie_id', true);
    $paused = (string) get_user_meta($u->ID, 'afas_sync_paused', true);
    $acties = [];
    if ($huidig === '') { $acties[] = "relatie -> $relatie"; }
    if ($paused !== '1') { $acties[] = 'sync_paused -> 1'; }
    if (!$acties) {
        printf("%-40s staat al goed (relatie %s)\n", $u->user_login, $huidig);
        continue;
    }
    if ($apply) {
        if ($huidig === '') { update_user_meta($u->ID, 'afas_relatie_id', $relatie); }
        if ($paused !== '1') { update_user_meta($u->ID, 'afas_sync_paused', '1'); }
    }
    printf("%-40s %s%s\n", $u->user_login, implode(', ', $acties), $apply ? '' : ' (dry-run)');
}
// factuur-usermeta direct vullen (NL-les: anders pas bij de volgende
// relaties-sync en lijkt de checkout stuk voor admins)
if ($apply && class_exists('\App\Jobs\SyncRelatiesJob')) {
    (new \App\Jobs\SyncRelatiesJob())->handleForDebtor($relatie);
    echo "factuurgegevens ververst voor relatie $relatie\n";
}
PHP
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — draai '$0 stap17 apply' om te schrijven."
    else
        echo "OK — beheerders gekoppeld op $(doel_naam)"
    fi
}

# ---------------------------------------------------------------------------
# Stap 10 — Assortiment-schrappingen (NL-stap10-patroon): de producten uit
# work/schraplijst-defibsolutionseu.csv gaan naar de prullenbak (besluit Cas
# 9 sep: G3-koffer met foute SKU 0009; de Primedic-AED's blijven bewust als
# reclame-producten; GEEN-MATCH-restanten volgen na review). SKU en koppeling
# worden gestript zodat niets ooit nog matcht. Daarna alle concept-producten
# naar de prullenbak (NL-besluit 25 aug, bewust zónder strip — herstelbaar).
# Default dry-run; `stap10 apply` voert uit.
# ---------------------------------------------------------------------------
stap10() {
    controleer_config
    local apply="${1:-}"
    local lijst="$REPO_ROOT/work/schraplijst-defibsolutionseu.csv"
    [[ -f "$lijst" ]] || { echo "FOUT: $lijst ontbreekt" >&2; exit 1; }

    python3 - "$lijst" "$apply" <<'PY' > /tmp/afaseu-schrap-payload.php
import csv, json, sys
lijst, apply = sys.argv[1], sys.argv[2] == "apply"
rijen = [(r["wc_id"].strip(), r["itemcode"].strip(), r["titel"].strip())
         for r in csv.DictReader(open(lijst, encoding="utf-8-sig"), delimiter=";")
         if r["wc_id"].strip().isdigit()]
print(f"// {len(rijen)} schrappingen uit {lijst}", file=sys.stderr)
print("<?php")
print(f"$apply = {'true' if apply else 'false'};")
print(f"$lijst = json_decode('{json.dumps(rijen)}', true);")
print("""
global $wpdb;
$weg = $al = 0;
foreach ($lijst as [$pid, $code, $titel]) {
    $post = get_post((int) $pid);
    // wc_id's van sync-aangemaakte posts verschillen per omgeving (lokaal vs
    // cp-01 vs live): zoek als vangnet ook op artikelcode, zodat dezelfde
    // CSV overal werkt. Nooit via het vangnet schrappen wat nog in
    // AFAS-beheer is (wp_lef_afas_artikelen) — les NL: achterhaalde rijen.
    if ((!$post || $post->post_status === 'trash') && $code !== '') {
        $inBeheer = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}lef_afas_artikelen WHERE artikelnummer = %s", $code));
        if ($inBeheer === 0) {
            $anders = $wpdb->get_col($wpdb->prepare(
                "SELECT pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key = '_afas_artikelnummer' AND pm.meta_value = %s
                   AND p.post_type IN ('product','product_variation') AND p.post_status <> 'trash'", $code));
            if ($anders) { $post = get_post((int) $anders[0]); $pid = (int) $anders[0]; }
        }
    }
    if (!$post || $post->post_status === 'trash') { $al++; continue; }
    printf("%s  #%d [%s] %s\\n", $apply ? 'PRULLENBAK' : 'ZOU TRASHEN', (int) $pid, $code, $titel);
    if ($apply) {
        delete_post_meta((int) $pid, '_afas_artikelnummer');
        update_post_meta((int) $pid, '_sku', '');
        // ook de lookup-tabel: daar draait WC's unieke-SKU-check op; een kale
        // meta-update ververst die niet en een achterblijvende rij blokkeert
        // toekomstige syncs
        $wpdb->update($wpdb->prefix . 'wc_product_meta_lookup', ['sku' => ''], ['product_id' => (int) $pid]);
        wp_trash_post((int) $pid);
        // WC trasht variaties van een parent mee, maar dan zonder strip:
        // kinderen expliciet ontdoen van sku + koppeling
        foreach (get_children(['post_parent' => (int) $pid, 'post_type' => 'product_variation', 'fields' => 'ids']) as $kind) {
            delete_post_meta((int) $kind, '_afas_artikelnummer');
            update_post_meta((int) $kind, '_sku', '');
            $wpdb->update($wpdb->prefix . 'wc_product_meta_lookup', ['sku' => ''], ['product_id' => (int) $kind]);
        }
    }
    $weg++;
}
printf("--- %s: %d geschrapt, %d stonden al in de prullenbak/bestaan niet\\n",
    $apply ? 'APPLY' : 'DRY-RUN', $weg, $al);

// Concepten: alle draft-producten naar de prullenbak (NL-besluit Cas 25 aug;
// EU-regel 9 sep "alles wat erbuiten valt verwijderen"). Bewust ZONDER
// SKU/meta-strip — drafts (o.a. Prestan) blijven zo herstelbaar; de sync
// negeert prullenbak-posts vanzelf.
$drafts = get_posts(['post_type' => ['product', 'product_variation'],
    'post_status' => 'draft', 'fields' => 'ids', 'numberposts' => -1,
    'suppress_filters' => true]);
foreach ($drafts as $did) {
    printf("%s  concept #%d — %s\\n", $apply ? 'PRULLENBAK' : 'ZOU TRASHEN',
        (int) $did, get_the_title($did));
    if ($apply) { wp_trash_post((int) $did); }
}
printf("--- %s: %d concepten\\n", $apply ? 'APPLY' : 'DRY-RUN', count($drafts));
""")
PY

    wpr_stdin eval-file - < /tmp/afaseu-schrap-payload.php
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — niets geschrapt. Draai '$0 stap10 apply' om uit te voeren."
    fi
}

# ---------------------------------------------------------------------------
# Stap 11 — Syncs draaien (nummer volgt NL; port van FR-stap10 op de 2.x-
# job-classes): plugin-migraties, artikelen (AFAS → tabel), prijslijsten +
# prijzen, landen/verkooprelaties/kortingen/adressen, en 2× de WooCommerce-
# sync (run 2 is het vangnet voor variaties wier container pas in run 1
# ontstond). Print per fase voortgang en eindigt met warning-telling + de
# relatie-kruischeck (NL-livegang-les 8). Vangrail NL-les 6 (stale
# wp_lef_migrations) zit erin. Vereist dat de Sync_/Tonen_Defibsolutions_EU-
# vlaggen in AFAS staan (gedaan 9 sep).
# Opties (combineerbaar):
#   zonder-prijzen   slaat de prijs-/relatie-import over (herhaal-runs)
#   alleen-relaties  alleen landen + verkooprelaties herdraaien
#   delta            wc-sync zonder force: alleen gewijzigde artikelen
# ---------------------------------------------------------------------------
stap11() {
    controleer_config
    local opties="${*:-}"
    local zonder_prijzen=""; [[ "$opties" == *zonder-prijzen* ]] && zonder_prijzen="zonder-prijzen"
    local alleen_relaties=""; [[ "$opties" == *alleen-relaties* ]] && alleen_relaties="1"
    cat > /tmp/afaseu-stap11-payload.php <<'PHP'
<?php
$zonderPrijzen = PRIJZEN_PLACEHOLDER;
$alleenRelaties = RELATIES_PLACEHOLDER; // alleen landen + verkooprelaties herdraaien
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
// Vangrail (NL-livegang 8 sep): stale wp_lef_migrations in een live-dump kan
// de adres-tabel als 'applied' aanmerken terwijl hij niet bestaat.
$adresTabel = $wpdb->prefix . 'lef_afas_addresses';
if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $adresTabel)) === null) {
    $wpdb->query("DELETE FROM {$wpdb->prefix}lef_migrations WHERE migration LIKE '%addresses%'");
    \Lefcreative\PluginBase\Core\Hooks::adminInit();
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $adresTabel)) === null) {
        fwrite(STDERR, "FOUT: adres-tabel ontbreekt na migratie-herdraai\n");
        exit(1);
    }
    echo "         stale migratie-boekhouding hersteld: adres-tabel aangemaakt\n";
}

$fase('artikelen-sync (AFAS -> tabel)');
do_action('afas_sync_artikelen', true);
printf("         tabel: %d artikelen\n", (int) $wpdb->get_var("SELECT COUNT(*) FROM `$tabel`"));

if ($alleenRelaties) {
    $fase('alleen-relaties: landen + verkooprelaties');
    do_action('afas_sync_landen', true);
    do_action('afas_sync_verkooprelaties', true);
    $fase('klaar (alleen-relaties)');
    exit(0);
}
if ($zonderPrijzen) {
    $fase('prijslijsten + prijzen + verkooprelaties OVERGESLAGEN (zonder-prijzen)');
} else {
    $fase('prijslijsten + prijzen');
    do_action('afas_sync_prijslijsten', true);
    do_action('afas_sync_prijzen', true);
    printf("         prijzen: %d regels\n",
        (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lef_afas_prijzen"));
    // VOLGORDE: landen vóór verkooprelaties — de relatie-sync vertaalt de
    // AFAS-landcode via lef_afas_landen; op een lege tabel blijft de code
    // rauw in billing_country staan (les revendeurs).
    $fase('landen + verkooprelaties + kortingen + adressen');
    do_action('afas_sync_landen', true);
    do_action('afas_sync_verkooprelaties', true);
    do_action('afas_sync_kortingen', true);
    // adressen: in 2.x is de oude hook een stille no-op — de job-class is het
    // betrouwbare pad, met hook-fallback en hard falen (NL-les 7)
    if (class_exists('\App\Jobs\SyncAddressesJob')) {
        (new \App\Jobs\SyncAddressesJob())->handle(true);
    } elseif (has_action('afas_sync_addresses')) {
        do_action('afas_sync_addresses', true);
    } else {
        fwrite(STDERR, "FOUT: geen adressen-syncpad (job noch hook) gevonden\n");
        exit(1);
    }
    printf("         relaties: %d, kortingen: %d, adressen: %d\n",
        (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lef_afas_verkooprelaties"),
        (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lef_afas_kortingen"),
        (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lef_afas_addresses"));
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
// Kruischeck (NL-livegang-les 8): gekoppelde klant zonder verkooprelatie-rij =
// relatie-vlag Sync_Defibsolutions_EU ontbreekt in AFAS -> geen klantprijzen.
$zonderRij = $wpdb->get_results("SELECT DISTINCT m.meta_value r, u.user_email e
    FROM {$wpdb->usermeta} m JOIN {$wpdb->users} u ON u.ID = m.user_id
    LEFT JOIN {$wpdb->prefix}lef_afas_verkooprelaties vr
      ON vr.afas_relatie_id = CONVERT(m.meta_value USING utf8mb4) COLLATE utf8mb4_unicode_ci
    WHERE m.meta_key = 'afas_relatie_id' AND m.meta_value <> '' AND vr.id IS NULL
    ORDER BY m.meta_value");
if ($zonderRij) {
    printf("         LET OP: %d gekoppelde relatie(s) zonder verkooprelatie-rij (vlag in AFAS ontbreekt?):\n", count($zonderRij));
    foreach ($zonderRij as $z) { printf("           %s  %s\n", $z->r, $z->e); }
} else {
    echo "         kruischeck: alle gekoppelde relaties hebben een verkooprelatie-rij\n";
}
PHP
    if [[ "$zonder_prijzen" == "zonder-prijzen" ]]; then
        sed -i 's/PRIJZEN_PLACEHOLDER/true/' /tmp/afaseu-stap11-payload.php
    else
        sed -i 's/PRIJZEN_PLACEHOLDER/false/' /tmp/afaseu-stap11-payload.php
    fi
    if [[ "$alleen_relaties" == "1" ]]; then
        sed -i 's/RELATIES_PLACEHOLDER/true/' /tmp/afaseu-stap11-payload.php
    else
        sed -i 's/RELATIES_PLACEHOLDER/false/' /tmp/afaseu-stap11-payload.php
    fi
    if [[ "$opties" == *delta* ]]; then
        sed -i 's/FORCE_PLACEHOLDER/false/' /tmp/afaseu-stap11-payload.php
    else
        sed -i 's/FORCE_PLACEHOLDER/true/' /tmp/afaseu-stap11-payload.php
    fi
    wpr_stdin eval-file - < /tmp/afaseu-stap11-payload.php
    echo "OK — syncs gedraaid op $(doel_naam)"
}

# ---------------------------------------------------------------------------
# Stap 8 — Structuur-opruiming (NL-stap8-/FR-stap11-patroon): losse simple
# products waarvan het gekoppelde AFAS-artikel een variatie hoort te zijn
# (artikel heeft artikelcode_parent in wp_lef_afas_artikelen) gaan naar de
# prullenbak, met SKU en _afas_artikelnummer gestript zodat ze nooit meer
# matchen (ook wc_product_meta_lookup — anders blokkeren achterblijvers
# toekomstige syncs). De artikelen-sync maakt/behoudt daarna de variatie onder
# de familie-container. Dekt duplicaat (variatie bestaat al) en conversie (nog
# niet). Ook: dubbele variaties met hetzelfde artikelnummer dedupliceren — bij
# EU relevant voor de Reanibex-containers waar oude variaties met geblokkeerde
# SKU's (audit: 126 GEBLOKKEERD) naast de door de sync aangemaakte staan.
# Default dry-run; `stap8 apply` voert uit. Draai hierna stap11 'zonder-prijzen delta'.
# ---------------------------------------------------------------------------
stap8() {
    controleer_config
    local apply="${1:-}"

    cat > /tmp/afaseu-structuur-payload.php <<'PHP'
<?php
$apply = APPLY_PLACEHOLDER;
global $wpdb;
// Volgorde-guard: zonder gevulde artikelen-tabel (stap11 eerst!) ziet deze
// stap niets en doet hij stilletjes te weinig.
$n_tabel = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}lef_afas_artikelen");
if ($n_tabel === 0) {
    echo "FOUT: wp_lef_afas_artikelen is leeg — draai eerst de artikelen-sync (stap11).\n";
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
    // deze regel is de bron voor redirect-/menu-herstel
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
            $wpdb->update($wpdb->prefix . 'wc_product_meta_lookup', ['sku' => ''], ['product_id' => $id]);
            wp_trash_post($id);
        }
        $d++;
    }
}
printf("--- %s: %d simples + %d dubbele variaties %s\n", $apply ? 'APPLY' : 'DRY-RUN', $n, $d,
    $apply ? 'naar prullenbak (SKU + koppeling gestript)' : 'zouden naar de prullenbak gaan');
PHP
    if [[ "$apply" == "apply" ]]; then
        sed -i 's/APPLY_PLACEHOLDER/true/' /tmp/afaseu-structuur-payload.php
    else
        sed -i 's/APPLY_PLACEHOLDER/false/' /tmp/afaseu-structuur-payload.php
    fi
    wpr_stdin eval-file - < /tmp/afaseu-structuur-payload.php
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — niets getrasht. Draai '$0 stap8 apply' om uit te voeren."
    fi
}

# ---------------------------------------------------------------------------
# Stap 16 — Containers + kale AED's OMVORMEN per family-head (revendeurs-stap11
# + FR-stap15 gecombineerd; les work/handoff-variabele-containers-omvormen.md:
# nooit content weggooien en kaal herbouwen). De plugin wil per head precies
# één container met _afas_artikelnummer = head en SKU <head>-wpbase; zolang
# oude posts head-codes dragen bouwt hij niets ("Aanmaken variabel product
# overgeslagen") en skipt hij alle variaties ("parent not found") — precies de
# 1865 warnings van de eerste EU-sync (9 sep).
# Kandidaten per head, uit drie bronnen:
#   a. handgemaakte variabele containers (head = meerderheid van de
#      Itemcode_Parent van hun variaties; Zoll Plus, HeartSine, Mindray C1,
#      Reanibex, Philips HS1/FRx);
#   b. OMZETTEN-rijen uit work/defibsolutionseu-omzet-aed.csv (kale AED-
#      simples; head = Itemcode_Parent van doel_base);
#   c. door de sync gebouwde kale containers (<head>-wpbase, geen content) —
#      tellen alleen mee als vangnet, verliezen altijd van a/b.
# Survivor = meeste publish-variaties, dan laagste ID: krijgt meta = head en
# SKU <head>-wpbase (een simple wordt variable). Verliezers: SKU + meta
# gestript, getrasht; hun variaties met een geldige head worden eerst
# omgehangen naar de survivor van díe head (Mindray C1 heeft beide heads
# door elkaar). Vervallen variaties in survivors (geen actief AFAS-artikel —
# de 126 B-code-Reanibex-SKU's — of geen Samenstelling) worden gestript +
# getrasht: de sync levert de actieve opvolgers. Containers zonder herleidbare
# head (Prestan WC-only-parents, trainers) blijven ongemoeid.
# Idempotent en fresh-pull-proof. Draai VÓÓR stap11 (herhaalreeks) resp. nu
# gevolgd door stap8 + stap11 'zonder-prijzen delta'. Default dry-run;
# `stap16 apply` schrijft.
# ---------------------------------------------------------------------------
stap16() {
    controleer_config
    local apply="${1:-}"
    local cache="$REPO_ROOT/work/cache/afas-artikelen-defibsolutionseu.json"
    local omzet="$REPO_ROOT/work/defibsolutionseu-omzet-aed.csv"
    [[ -f "$cache" ]] || { echo "FOUT: $cache ontbreekt" >&2; exit 1; }
    [[ -f "$omzet" ]] || { echo "FOUT: $omzet ontbreekt" >&2; exit 1; }
    mkdir -p "$REPO_ROOT/tmp"

    # dump: ALLE variabele containers + hun variaties (sku, status, meta)
    local dump="$REPO_ROOT/tmp/defibseu-stap16-dump.tsv"
    wpr db query "\"SELECT par.ID, COALESCE(psku.meta_value,''), COALESCE(pan.meta_value,''), par.post_title,
            COALESCE(v.ID,0), COALESCE(vsku.meta_value,''), COALESCE(van.meta_value,''), COALESCE(v.post_status,'')
        FROM wp_posts par
        JOIN wp_term_relationships tr ON tr.object_id = par.ID
        JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_type'
        JOIN wp_terms t ON t.term_id = tt.term_id AND t.slug = 'variable'
        LEFT JOIN wp_postmeta pan ON pan.post_id = par.ID AND pan.meta_key = '_afas_artikelnummer'
        LEFT JOIN wp_postmeta psku ON psku.post_id = par.ID AND psku.meta_key = '_sku'
        LEFT JOIN wp_posts v ON v.post_parent = par.ID AND v.post_type = 'product_variation'
             AND v.post_status IN ('publish','private')
        LEFT JOIN wp_postmeta vsku ON vsku.post_id = v.ID AND vsku.meta_key = '_sku'
        LEFT JOIN wp_postmeta van ON van.post_id = v.ID AND van.meta_key = '_afas_artikelnummer'
        WHERE par.post_type = 'product' AND par.post_status IN ('publish','private')\"" --skip-column-names > "$dump"

    python3 - "$cache" "$omzet" "$dump" "$apply" <<'PY' > "$REPO_ROOT/tmp/defibseu-stap16-payload.php"
import csv, json, sys
from collections import Counter, defaultdict
cache, omzet, dump, apply = sys.argv[1], sys.argv[2], sys.argv[3], sys.argv[4] == "apply"

per_code, per_bhv = {}, {}
for a in json.load(open(cache)):
    code = str(a.get("Itemcode") or "")
    per_code.setdefault(code, a)
    b = str(a.get("Artikelcode_BHV_Voordeelwinkel") or "").strip()
    if b:
        per_bhv.setdefault(b, {})[code] = a
# alleen family-heads van échte samenstellingen: Prestan-parents (WC-only) en
# trainer-families bestaan uit Type_item 'Artikel' en blijven ongemoeid
heads_bekend = {str(a.get("Itemcode_Parent") or "").strip() for a in per_code.values()
                if str(a.get("Type_item") or "") == "Samenstelling"} - {""}

def artikel_van(sku: str, meta: str):
    for kandidaat in (meta, sku):
        if not kandidaat:
            continue
        a = per_code.get(kandidaat)
        if a is None:
            actief = {c: k for c, k in per_bhv.get(kandidaat, {}).items() if k.get("Geblokkeerd") is not True}
            a = next(iter(actief.values())) if len(actief) == 1 else None
        if a is not None and a.get("Geblokkeerd") is not True:
            return a
    return None

def head_van(sku: str, meta: str) -> str:
    a = artikel_van(sku, meta)
    h = str(a.get("Itemcode_Parent") or "").strip() if a else ""
    return h if h in heads_bekend else ""

def is_vervallen(sku: str, meta: str) -> bool:
    # geen actief AFAS-artikel (B-prefix/soft-delete) of geen Samenstelling
    a = artikel_van(sku, meta)
    if a is None:
        return bool(sku or meta)
    return str(a.get("Type_item") or "") != "Samenstelling"

# a + c: containers
containers = {}
for regel in open(dump, encoding="utf-8"):
    d = regel.rstrip("\n").split("\t")
    if len(d) < 8 or not d[0].isdigit():
        continue
    pid, psku, pmeta, titel, vid, vsku, vmeta, vstatus = d
    c = containers.setdefault(pid, {"titel": titel, "sku": psku, "meta": pmeta, "heads": Counter(),
                                    "publish_vars": 0, "vars": [], "bron": "container"})
    if vid != "0":
        if vstatus == "publish":
            c["publish_vars"] += 1
        h = head_van(vsku.strip(), vmeta.strip())
        c["vars"].append({"id": int(vid), "sku": vsku.strip(), "meta": vmeta.strip(), "head": h,
                          "vervallen": is_vervallen(vsku.strip(), vmeta.strip())})
        if h:
            c["heads"][h] += 1

kandidaten = defaultdict(list)  # head -> [(pid, info)]
for pid, c in containers.items():
    head = c["heads"].most_common(1)[0][0] if c["heads"] else ""
    if not head:
        # plugin-gebouwde kale container zonder variaties: head uit sku/meta
        if c["sku"].endswith("-wpbase") and c["sku"][:-7] in heads_bekend:
            head = c["sku"][:-7]
        elif c["meta"] in heads_bekend:
            head = c["meta"]
    if not head:
        print(f"// ongemoeid: container wc:{pid} '{c['titel'][:45]}' — geen samenstellings-head (WC-only/trainer)", file=sys.stderr)
        continue
    c["head"] = head
    c["kaal_plugin"] = c["sku"] == head + "-wpbase" and c["publish_vars"] <= 12 and int(pid) > 107000
    kandidaten[head].append((int(pid), c))

# b: kale AED-simples uit de omzet-lijst
for r in csv.DictReader(open(omzet, encoding="utf-8-sig"), delimiter=";"):
    if r["status"].strip() != "OMZETTEN" or not r["doel_base"].strip():
        continue
    base = r["doel_base"].strip()
    head = str(per_code.get(base, {}).get("Itemcode_Parent") or "").strip() or base
    pid = int(r["wc_id"])
    if str(pid) in containers:
        continue  # al variable (bv. door de sync geadopteerd) -> zit in a/c
    kandidaten[head].append((pid, {"titel": r["shop_naam"].strip(), "sku": r["sku"], "meta": "",
                                    "publish_vars": 0, "vars": [], "bron": "simple", "head": head,
                                    "kaal_plugin": False}))

plan = []  # dicts: pid, actie(convert|trash), head, bron, omhangen[], vervallen[]
survivor_van = {}
for head, lijst in kandidaten.items():
    # rangorde: nooit een plugin-kale container laten winnen van content;
    # dan meeste publish-variaties, dan laagste id
    lijst.sort(key=lambda x: (x[1]["kaal_plugin"], -x[1]["publish_vars"], x[0]))
    survivor_van[head] = lijst[0][0]
for head, lijst in kandidaten.items():
    win_pid, win = lijst[0]
    al_goed = win["meta"] == head and win["sku"] == head + "-wpbase" and len(lijst) == 1 \
        and not any(v["vervallen"] or (v["head"] and v["head"] != head) for v in win["vars"])
    if al_goed:
        continue
    plan.append({"pid": win_pid, "actie": "convert", "head": head, "bron": win["bron"], "titel": win["titel"],
                 "vervallen": [v["id"] for v in win["vars"] if v["vervallen"]],
                 "omhangen": [(v["id"], survivor_van[v["head"]]) for v in win["vars"]
                              if v["head"] and v["head"] != head and v["head"] in survivor_van
                              and not v["vervallen"]]})
    for pid, c in lijst[1:]:
        plan.append({"pid": pid, "actie": "trash", "head": head, "bron": c["bron"], "titel": c["titel"],
                     "vervallen": [],
                     "omhangen": [(v["id"], survivor_van[v["head"]]) for v in c["vars"]
                                  if v["head"] and v["head"] in survivor_van and not v["vervallen"]]})
plan.sort(key=lambda x: x["pid"])
print(f"// {len(kandidaten)} heads: {sum(1 for p in plan if p['actie']=='convert')} omvormen, "
      f"{sum(1 for p in plan if p['actie']=='trash')} weg, "
      f"{sum(len(p['omhangen']) for p in plan)} variaties omhangen, "
      f"{sum(len(p['vervallen']) for p in plan)} vervallen variaties weg", file=sys.stderr)
print("<?php")
print(f"$apply = {'true' if apply else 'false'};")
print(f"$plan = json_decode('{json.dumps(plan, ensure_ascii=False).replace(chr(92), chr(92)*2).replace(chr(39), chr(92)+chr(39))}', true);")
print(r"""
global $wpdb;
$strip = function (int $id) use ($wpdb) {
    delete_post_meta($id, '_afas_artikelnummer');
    update_post_meta($id, '_sku', '');
    $wpdb->update($wpdb->prefix . 'wc_product_meta_lookup', ['sku' => ''], ['product_id' => $id]);
};
foreach ($plan as $p) {
    $pid = (int) $p['pid']; $head = $p['head'];
    if ($p['actie'] === 'convert') {
        printf("%s %s wc:%d '%s' -> head %s (%d variaties omhangen naar andere survivor, %d vervallen weg)\n",
            $apply ? 'OMGEVORMD' : 'ZOU OMVORMEN', $p['bron'], $pid, $p['titel'], $head,
            count($p['omhangen']), count($p['vervallen']));
        foreach ($p['vervallen'] as $vid) {
            printf("   %s vervallen variatie wc:%d (sku=%s)\n", $apply ? 'WEG' : 'zou weg', $vid, get_post_meta($vid, '_sku', true));
            if ($apply) { $strip((int) $vid); wp_trash_post((int) $vid); }
        }
        foreach ($p['omhangen'] as [$vid, $naar]) {
            printf("   %s variatie wc:%d -> container wc:%d\n", $apply ? 'OMGEHANGEN' : 'zou omhangen', $vid, $naar);
            if ($apply) { wp_update_post(['ID' => (int) $vid, 'post_parent' => (int) $naar]); }
        }
        if ($apply) {
            if ($p['bron'] === 'simple') { wp_set_object_terms($pid, 'variable', 'product_type'); }
            update_post_meta($pid, '_afas_artikelnummer', $head);
            update_post_meta($pid, '_sku', $head . '-wpbase');
            $wpdb->update($wpdb->prefix . 'wc_product_meta_lookup', ['sku' => $head . '-wpbase'], ['product_id' => $pid]);
        }
    } else {
        $kinderen = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'product_variation'", $pid));
        printf("%s %s wc:%d '%s' (dubbel voor head %s; %d variaties omhangen, %d mee weg)\n",
            $apply ? 'WEGGEGOOID' : 'ZOU WEGGOOIEN', $p['bron'], $pid, $p['titel'], $head,
            count($p['omhangen']), count($kinderen) - count($p['omhangen']));
        $omgehangen = [];
        foreach ($p['omhangen'] as [$vid, $naar]) {
            $omgehangen[] = (int) $vid;
            if ($apply) { wp_update_post(['ID' => (int) $vid, 'post_parent' => (int) $naar]); }
        }
        if ($apply) {
            foreach ($kinderen as $vid) {
                if (in_array((int) $vid, $omgehangen, true)) { continue; }
                $strip((int) $vid); wp_trash_post((int) $vid);
            }
            $strip($pid); wp_trash_post($pid);
        }
    }
}
if ($apply && function_exists('wc_delete_product_transients')) { wc_delete_product_transients(); }
printf("--- %s: %d acties\n", $apply ? 'APPLY' : 'DRY-RUN', count($plan));
""")
PY

    wpr_stdin eval-file - < "$REPO_ROOT/tmp/defibseu-stap16-payload.php"
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — niets gewijzigd. Draai '$0 stap16 apply' om om te vormen."
    fi
}

# ---------------------------------------------------------------------------
# Stap 18 — SKU-correcties uit work/sku-correcties-defibsolutionseu.csv
# (wc_id;oude_sku;nieuwe_sku;reden). Bevinding 10 sep: zes shop-SKU's dragen
# een punt die AFAS niet kent (60.123 vs itemcode/BHV 60123) — één teken
# verschil, dus GEEN-MATCH in de audit terwijl het artikel gewoon bestaat.
# Corrigeert de SKU (postmeta + wc_product_meta_lookup) alleen als de huidige
# SKU nog de oude is; ruimt eerst wees-rijen in de lookup-tabel op (verdwenen
# posts die een SKU bezet houden); daarna koppelt stap6 ze via itemcode/BHV.
# Idempotent.
# Default dry-run; `stap18 apply` schrijft.
# ---------------------------------------------------------------------------
stap18() {
    controleer_config
    local apply="${1:-}"
    local lijst="$REPO_ROOT/work/sku-correcties-defibsolutionseu.csv"
    [[ -f "$lijst" ]] || { echo "FOUT: $lijst ontbreekt" >&2; exit 1; }
    python3 - "$lijst" "$apply" <<'PY' > /tmp/afaseu-sku-payload.php
import csv, json, sys
lijst, apply = sys.argv[1], sys.argv[2] == "apply"
rijen = [(r["wc_id"].strip(), r["oude_sku"].strip(), r["nieuwe_sku"].strip())
         for r in csv.DictReader(open(lijst, encoding="utf-8-sig"), delimiter=";") if r["wc_id"].strip().isdigit()]
print("<?php")
print(f"$apply = {'true' if apply else 'false'};")
print(f"$lijst = json_decode('{json.dumps(rijen)}', true);")
print("""
global $wpdb;
// Wees-rijen in wc_product_meta_lookup (post bestaat niet meer) blokkeren WC's
// unieke-SKU-check (gezien 10 sep: sku 60213 vast op verdwenen post 103242).
$wezen = (int) $wpdb->query("DELETE l FROM {$wpdb->prefix}wc_product_meta_lookup l
    LEFT JOIN {$wpdb->posts} p ON p.ID = l.product_id WHERE p.ID IS NULL" . ($apply ? '' : ' AND 0'));
if ($apply) { printf("lookup-wezen opgeruimd: %d\\n", $wezen); }
$gezet = $al = $anders = 0;
foreach ($lijst as [$pid, $oud, $nieuw]) {
    $huidig = (string) get_post_meta((int) $pid, '_sku', true);
    if ($huidig === $nieuw) { $al++; continue; }
    if ($huidig !== $oud) { printf("OVERSLAAN  wc:%d heeft sku '%s' (verwacht '%s')\\n", (int) $pid, $huidig, $oud); $anders++; continue; }
    $bezet = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}wc_product_meta_lookup WHERE sku = %s AND product_id <> %d", $nieuw, (int) $pid));
    if ($bezet) { printf("OVERSLAAN  wc:%d: sku '%s' is al in gebruik\\n", (int) $pid, $nieuw); $anders++; continue; }
    printf("%s  wc:%d: %s -> %s\\n", $apply ? 'GEZET' : 'ZOU ZETTEN', (int) $pid, $oud, $nieuw);
    if ($apply) {
        update_post_meta((int) $pid, '_sku', $nieuw);
        $wpdb->update($wpdb->prefix . 'wc_product_meta_lookup', ['sku' => $nieuw], ['product_id' => (int) $pid]);
    }
    $gezet++;
}
printf("--- %s: %d gecorrigeerd, %d stonden al goed, %d overgeslagen\\n", $apply ? 'APPLY' : 'DRY-RUN', $gezet, $al, $anders);
""")
PY
    wpr_stdin eval-file - < /tmp/afaseu-sku-payload.php
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — niets gewijzigd. Draai '$0 stap18 apply' en daarna 'stap6 apply'."
    fi
}

# ---------------------------------------------------------------------------
# Stap 19 — Livegang-slot (NL-stap19-patroon, 8 sep): order-push aan met de
# EU-Bron-Order-code, order-vrije-velden, sync-intervallen naar productie
# (15 min, conform reseller-live), reseller-conforme push-/mapping-opties,
# mail aan. De Bron Order-code is per shop uniek in de AFAS-waardenlijst
# (reseller=68, ARKY=71, NL=72, revendeurs=73, EU=75 — besluit Cas 9 sep) en
# daarom een verplicht argument. Alleen op het live-target draaien.
# Gebruik: stap19 <bron-order-waarde> [apply]
# ---------------------------------------------------------------------------
stap19() {
    controleer_config
    local bron="${1:-}" apply="${2:-}"
    [[ "$bron" =~ ^[0-9]+$ ]] || { echo "FOUT: eerste argument moet de Bron Order-waarde zijn (getal), bv. stap19 75 apply" >&2; exit 1; }
    wpr_stdin eval-file - "$bron" "$apply" <<'PHP'
<?php
$bron  = (string) ($args[0] ?? '');
$apply = ('apply' === ($args[1] ?? ''));

$vrijeVelden = [
    ['referentie' => 'Status Verzending', 'veld' => 'SeSt', 'waarde' => '1'],
    ['referentie' => 'opmerking',         'veld' => 'Re',   'waarde' => '{customer_note}'],
    ['referentie' => 'Bron Order',        'veld' => 'U923B5458459E495CFD945A303684E740', 'waarde' => $bron],
    ['referentie' => 'Backorder',         'veld' => 'BkOr', 'waarde' => '1'],
];
$doel = [
    'afas_sync_orders_enabled'          => '1',
    'afas_sync_verkooporders_enabled'   => '1',
    'afas_sync_orders_administratie'    => '1',
    'afas_sync_orders_magazijn'         => '*****',
    'afas_sync_orders_rfcs_prefix'      => '{order_id}',
    'afas_sync_orders_vrije_velden'     => $vrijeVelden,
    'afas_sync_orders_complete_on_push' => '1',
    'afas_sync_orders_retry_delay'      => '5',
    'afas_sync_orders_rfcs_field'       => 'U6A53D0A280B94BE188C88373C2808436',
    // productie-intervallen (reseller-live): 15 min i.p.v. wekelijks (stap4)
    'afas_sync_addresses_interval'       => '900',
    'afas_sync_artikelen_interval'       => '900',
    'afas_sync_kortingen_interval'       => '900',
    'afas_sync_prijslijsten_interval'    => '900',
    'afas_sync_prijzen_interval'         => '900',
    'afas_sync_verkooprelaties_interval' => '900',
    'afas_sync_woocommerce_interval'     => '900',
    'afas_sync_pakbonnen_interval'       => '900',
    'afas_sync_woonplaatsen_enabled'     => '1',
    'afas_sync_woonplaatsen_interval'    => '86400',
];
foreach ($doel as $optie => $waarde) {
    $huidig = get_option($optie);
    $gelijk = is_array($waarde) ? ($huidig == $waarde) : ((string) $huidig === $waarde);
    $toon   = is_array($waarde) ? sprintf('[%d vrije velden, bron=%s]', count($waarde), $bron) : $waarde;
    if ($gelijk) {
        printf("%-38s staat al goed (%s)\n", $optie, $toon);
        continue;
    }
    if ($apply) { update_option($optie, $waarde); }
    printf("%-38s %s -> %s%s\n", $optie,
        is_array($huidig) ? '[array]' : var_export($huidig, true), $toon,
        $apply ? '' : ' (dry-run)');
}
PHP
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — draai '$0 stap19 $bron apply' om uit te voeren (zet ook mail aan)."
    else
        wpr plugin deactivate disable-emails
        echo "--- controle mail:"
        wpr plugin list --name=disable-emails --field=status || true
        echo "OK — livegang-slot uitgevoerd op $(doel_naam): push aan (bron $bron), intervallen 15 min, mail aan"
    fi
}

# ---------------------------------------------------------------------------
# stap15 — Divi-caches resetten na de URL-herschrijving van de verhuizing.
# (Nummer volgt het NL-script.) Divi's feature-cache (postmeta
# _et_builder_module_features_cache) keyt op md5 van de shortcode-attributen
# — inclusief URL's. Na de domein-rewrite missen álle lookups in de
# mee-gemigreerde cache, en een miss in een geladen cache betekent voor Divi
# "feature stond vorige keer uit": padding, box-shadow, borders,
# border-radius, knop- en hover-CSS verdwijnen uit elke pagina met
# URL-houdende module-attrs. De cache herstelt zichzelf nooit. Fix:
# cache-postmeta purgen + et-cache leeg; de eerste render bouwt alles
# correct opnieuw op. Idempotent — na élke pull + URL-rewrite draaien.
# Achtergrond: work/handoff-divi-feature-cache.md (les .nl-migratie 31 aug).
# ---------------------------------------------------------------------------
stap15() {
    controleer_config
    local apply="${1:-}"
    wpr_stdin eval-file - "$apply" <<'PHP'
<?php
$apply = ('apply' === ($args[0] ?? ''));
$keys = [
    '_et_builder_module_features_cache',
    '_et_dynamic_cached_shortcodes',
    '_et_dynamic_cached_attributes',
];
global $wpdb;
foreach ($keys as $k) {
    $n = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", $k
    ));
    if ($apply && $n > 0) { delete_post_meta_by_key($k); }
    printf("%s: %d rijen%s\n", $k, $n, ($apply && $n > 0) ? ' -> gepurged' : '');
}
// et-cache leegmaken zodat ook de gegenereerde CSS-bestanden vers zijn.
$dir = WP_CONTENT_DIR . '/et-cache';
$verwijderd = 0;
if (is_dir($dir)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $pad) {
        if (!$apply) { $verwijderd++; continue; }
        $ok = $pad->isDir() ? @rmdir($pad->getPathname()) : @unlink($pad->getPathname());
        if ($ok) { $verwijderd++; }
    }
}
printf("et-cache: %d items%s\n", $verwijderd, $apply ? ' verwijderd' : '');
// BeRocket AAPF cachet template-style-paden ABSOLUUT in een optie
// (BeRocket_AAPF_getall_Template_Styles). Na de verhuizing wijzen die naar
// het oude Satserver-pad -> file_exists faalt -> elke filter bailt met
// "Template not selected" en de filterbalk blijft leeg. De plugin heeft een
// eigen regeneratie-action die met lokale paden herschrijft.
// Achtergrond: work/handoff-berocket-filter-pad-cache.md (les .nl 31 aug).
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
    : 'paden al lokaal');
if (!$apply) { echo "Dry-run - niets gewijzigd.\n"; }
PHP
    if [[ "$apply" != "apply" ]]; then
        echo "Dry-run — draai '$0 stap15 apply' om te purgen."
    else
        echo "OK — Divi-caches gereset op $(doel_naam); eerste render bouwt ze opnieuw op"
    fi
}

usage() {
    echo "gebruik: $0 <stap>" >&2
    echo ""
    echo "  stap0   Rooktest: config-check + wp-versie op het target (read-only)"
    echo "  stap1   Mail UIT: disable-emails installeren + activeren"
    echo "  stap2   Overbodige plugins UIT: b2bking + wp-staging + wp-rocket + mainwp-child"
    echo "  stap3   Klanten koppelen aan AFAS-relaties uit work/defibsolutionseu-klant-relatie-mapping.csv (dry-run; 'stap3 apply' schrijft)"
    echo "  stap4   lefcreative-afas-b2b 2.1.1 installeren + activeren + EU-afas-settings importeren (work/)"
    echo "  stap5   API-keys intrekken: WooCommerce REST-keys + application passwords (dry-run; 'stap5 apply' verwijdert)"
    echo "  stap6   Voorkoppeling: _afas_artikelnummer per product via omzet-lijst/BHV/itemcode (dry-run; 'stap6 apply' schrijft)"
    echo "  stap7   mu-plugins plaatsen uit migration/mu-plugins/ (EU-lijst, idempotent)"
    echo "  stap8   Structuur-opruiming: gekoppelde simples die variatie horen te zijn + dubbele variaties -> prullenbak (dry-run; 'stap8 apply')"
    echo "  stap10  Assortiment-schrappingen uit work/schraplijst-defibsolutionseu.csv + concepten (dry-run; 'stap10 apply')"
    echo "  stap11  Syncs (opties: 'zonder-prijzen', 'alleen-relaties', 'delta')"
    echo "  stap9   Weergave: swatches reseller-conform + checkout-velden (idempotent)"
    echo "  stap12  Variatie-assen (pa_language/connectivity/cpr-sensor/options, EN) op de containers uit de tool (dry-run; 'stap12 apply')"
    echo "  stap14  Containers erven categorieën/tags/pa_*-termen van hun variaties (dry-run; 'stap14 apply')"
    echo "  stap17  Beheerders AFAS-relatie 31239 + sync-pauze (dry-run; 'stap17 apply')"
    echo "  stap19  Livegang-slot: order-push aan + vrije velden + intervallen 15 min + mail aan (dry-run; 'stap19 <bronwaarde> apply', EU=75)"
    echo "  stap18  SKU-correcties uit work/sku-correcties-defibsolutionseu.csv (punt-typo's; dry-run; 'stap18 apply', daarna stap6)"
    echo "  stap16  Containers + kale AED's omvormen per family-head (survivor/omhangen/vervallen; dry-run; 'stap16 apply')"
    echo "  stap15  Divi-caches resetten na URL-rewrite (dry-run; 'stap15 apply') — na élke verse pull"
    exit 1
}

case "${1:-}" in
    stap0) stap0 ;;
    stap1) stap1 ;;
    stap2) stap2 ;;
    stap3) stap3 "${2:-}" ;;
    stap4) stap4 ;;
    stap5) stap5 "${2:-}" ;;
    stap6) stap6 "${2:-}" ;;
    stap7) stap7 ;;
    stap8) stap8 "${2:-}" ;;
    stap10) stap10 "${2:-}" ;;
    stap11) shift; stap11 "$@" ;;
    stap16) stap16 "${2:-}" ;;
    stap9) stap9 ;;
    stap12) stap12 "${2:-}" ;;
    stap14) stap14 "${2:-}" ;;
    stap17) stap17 "${2:-}" ;;
    stap18) stap18 "${2:-}" ;;
    stap19) stap19 "${2:-}" "${3:-}" ;;
    stap15) stap15 "${2:-}" ;;
    *) usage ;;
esac
