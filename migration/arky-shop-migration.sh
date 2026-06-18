#!/usr/bin/env bash
#
# ARKY-shop migratie — producten die na een migratie weg moeten, PERMANENT
# verwijderen (force=true; geen prullenbak — een getrasht product houdt z'n
# SKU gereserveerd en blokkeert de AFAS->Woo-sync).
# WC-id's gelden voor de huidige shop-instance (blijven bij een migratie-kopie
# behouden). Na de deletes herstelt de restore-stap de bekende-goede sku+meta
# per WC-id uit wc-sku-meta-map.csv.
#
# Creds via env:
#   export ARKY_CK=ck_xxx ; export ARKY_CS=cs_xxx
#   ./migration/arky-shop-migration.sh
#
# Mapping verversen (vanuit huidige shop-staat):
#   sqlite3 -header -csv tmp/samenstellingen.sqlite "SELECT wp.wc_product_id AS wc_id,
#     wp.type, COALESCE(wp.wc_parent_id,'') AS parent_id, COALESCE(wp.sku,'') AS sku,
#     COALESCE(wp.afas_itemcode,'') AS meta FROM woocommerce_products wp
#     JOIN woocommerce_stores ws ON ws.id=wp.store_id
#     WHERE ws.name='arkycase.defibrion.dev' AND wp.status<>'trash'
#     ORDER BY wp.type, wp.wc_product_id;" > migration/wc-sku-meta-map.csv
#
set -euo pipefail
B="${ARKY_STORE_URL:-https://arkycase.defibrion.dev}/wp-json/wc/v3"
CK="${ARKY_CK:?zet ARKY_CK}"; CS="${ARKY_CS:?zet ARKY_CS}"
UA='Mozilla/5.0'

# Permanent verwijderen (force=true) — simple-producten en variable-parents:
for id in 4726 4887 3080 2830 1895 1542 1287 1218 1300 1826 1407 3444; do
  curl -s -o /dev/null -w "delete product $id -> %{http_code}\n" \
    -u "$CK:$CS" -A "$UA" -X DELETE "$B/products/$id?force=true"
done

# Permanent verwijderen (force=true) — variations (parent/variation):
for pv in 2829/3005 2829/5626 2829/5627 2829/3007 \
          3762/3786 3762/3810 3874/3898 3949/3946 3957/3960 3949/3976 3949/3979 \
          3957/3983 3957/3986 3992/3995 3999/4002 4005/4008 4012/4015 4027/4033 \
          4027/4041 4064/4067; do
  curl -s -o /dev/null -w "delete variation $pv -> %{http_code}\n" \
    -u "$CK:$CS" -A "$UA" -X DELETE "$B/products/${pv%/*}/variations/${pv#*/}?force=true"
done

# ───────────────────────────────────────────────────────────────────────────
# Restore — zet sku + _afas_artikelnummer terug per WC-id (uit mapping-CSV)
# ───────────────────────────────────────────────────────────────────────────
# De AFAS->Woo-sync kan na een migratie/re-sync sku's en meta's anders zetten
# (collisions, attribuut-safeguard, -NN-suffixen). Deze stap herstelt de
# bekende-goede waarden van ELK ARKY-product (wc-sku-meta-map.csv: wc_id,type,
# parent_id,sku,meta). WC-id's blijven bij een migratie-kopie behouden.
MAP="${ARKY_MAP_CSV:-$(dirname "$0")/wc-sku-meta-map.csv}"
echo "== Restore sku+meta uit $MAP =="
python3 - "$MAP" "$B" "$CK" "$CS" "$UA" <<'PYEOF'
import sys, csv, json, urllib.request, base64, ssl
mapfile, base, ck, cs, ua = sys.argv[1:6]
ctx = ssl.create_default_context(); ctx.check_hostname = False; ctx.verify_mode = ssl.CERT_NONE
auth = base64.b64encode(f"{ck}:{cs}".encode()).decode()
ok = fail = skip = 0
with open(mapfile) as f:
    for row in csv.DictReader(f):
        wid, typ, par, sku, meta = row['wc_id'], row['type'], row['parent_id'], row['sku'], row['meta']
        body = {}
        if sku:
            body['sku'] = sku
        if meta:
            body['meta_data'] = [{'key': '_afas_artikelnummer', 'value': meta}]
        if not body:
            skip += 1; continue
        ep = f"products/{par}/variations/{wid}" if typ == 'variation' and par else f"products/{wid}"
        req = urllib.request.Request(f"{base}/{ep}", data=json.dumps(body).encode(), method='PUT')
        req.add_header('Authorization', 'Basic ' + auth)
        req.add_header('Content-Type', 'application/json')
        req.add_header('User-Agent', ua)
        try:
            urllib.request.urlopen(req, timeout=60, context=ctx); ok += 1
        except Exception as e:
            fail += 1; print(f"  FAIL {ep}: {str(e)[:90]}")
print(f"restore: ok={ok} fail={fail} skip(geen sku/meta)={skip}")
PYEOF
