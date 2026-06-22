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

# Permanent verwijderen (force=true) — simple-producten en variable-parents.
# (Een variable-parent neemt z'n child-variations mee.)
# Laatste blok = PRESTAN-artikelen die niet in Josca's publicatielijst staan.
for id in 4726 4887 3080 2830 1895 1542 1287 1218 1300 1826 1407 3444 \
          3679 3680 3681 3682 3687 3688 3689 3690 3691 3695 3696 3697 3698 \
          3758 3759 3760 3761 3826 3850 3922 3953 3966 3970 3973 3989 4021 \
          4024 4030 4037 4045 4052 4056 4060 4070 4073 4076 \
          3647 3648 3649 3650 3651 3652 3653 3654 3655 3656 3657 3658 3659 \
          3660 3661 3662 3663 3664 3665 3666 3692 3693 3694 3699 3700 3702 \
          3703 3704 3705 3706 3707 3708 3709 3710 3711 3712 3713 3714 3715 \
          3716 3717 3718 3719 3720 3721 3722 3723 3724 3725 3726 3727 3728 \
          3729 3730 3731 3732 3733 3734 3735 3736 3737 3738 3739 3740 3741 \
          3742 3743 3744 3748 3749 3750 3751 3752 3753 3754 3755 3756; do
  curl -s -o /dev/null -w "delete product $id -> %{http_code}\n" \
    -u "$CK:$CS" -A "$UA" -X DELETE "$B/products/$id?force=true"
done

# Permanent verwijderen (force=true) — variations (parent/variation):
for pv in 2829/3005 2829/5626 2829/5627 2829/3007 \
          3762/3786 3762/3810 3874/3898 3949/3946 3957/3960 3949/3976 3949/3979 \
          3957/3983 3957/3986 3992/3995 3999/4002 4005/4008 4012/4015 4027/4033 \
          4027/4041 4064/4067 \
          3762/3780 3874/3892 4018/4020 4049/4051; do
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
