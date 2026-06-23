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
# Blok "PRESTAN niet-gepubliceerd" = artikelen die niet in Josca's publicatielijst staan.
# Laatste blok (3786..4067) = niet-gekoppelde legacy/-VAR variable-containers: hun
# children horen bij een al-bestaande correcte container; de cascade ruimt ze op en
# de AFAS->Woo-sync herbouwt de variations onder de juiste parent.
for id in 4726 4887 3080 2830 1895 1542 1287 1218 1300 1826 1407 3444 \
          3679 3680 3681 3682 3687 3688 3689 3690 3691 3695 3696 3697 3698 \
          3758 3759 3760 3761 3826 3850 3922 3953 3966 3970 3973 3989 4021 \
          4024 4030 4037 4045 4052 4056 4060 4070 4073 4076 \
          3647 3648 3649 3650 3651 3652 3653 3654 3655 3656 3657 3658 3659 \
          3660 3661 3662 3663 3664 3665 3666 3692 3693 3694 3699 3700 3702 \
          3703 3704 3705 3706 3707 3708 3709 3710 3711 3712 3713 3714 3715 \
          3716 3717 3718 3719 3720 3721 3722 3723 3724 3725 3726 3727 3728 \
          3729 3730 3731 3732 3733 3734 3735 3736 3737 3738 3739 3740 3741 \
          3742 3743 3744 3748 3749 3750 3751 3752 3753 3754 3755 3756 \
          2054 2053 1997 2003 1996 2051 2052 \
          1288 3757 6151 \
          3786 3810 3898 3946 3960 3976 3979 3983 3986 3995 4002 4008 4015 4033 4041 4067; do
  curl -s -o /dev/null -w "delete product $id -> %{http_code}\n" \
    -u "$CK:$CS" -A "$UA" -X DELETE "$B/products/$id?force=true"
done

# Permanent verwijderen (force=true) — variations (parent/variation):
# 2829/3006 = stale oude 21015 (geblokkeerd, vervangen door 21011-DE; bezet SKU 21015).
# 4018/4019 + 4049/4051 = self-variations van PP-JTM/PP-IULM die simple-producten worden
#   (zie variable->simple conversie-blok hieronder).
for pv in 2829/3005 2829/5626 2829/5627 2829/3007 2829/3006 \
          3762/3780 3874/3892 4018/4020 4049/4051 4018/4019 4049/4050 \
          1600/5219 1600/5220 1600/5221 1600/5222 1600/5223 1600/5224 1600/5225 \
          2971/5404 2971/5405 2971/5406 2971/5407 2971/5408 2971/5409 2971/5411 \
          2971/5412 2971/5413 2971/5414 2971/5415 2971/5416 2971/5417 \
          2976/5444 2976/5445 2976/5446 2976/5447 2976/5448 2976/5449 2976/5451 \
          2976/5452 2976/5453 2976/5454 2976/5455 2976/5456 2976/5457 \
          2981/5484 2981/5485 2981/5486 2981/5487 2981/5488 2981/5489 2981/5491 \
          2981/5492 2981/5493 2981/5494 2981/5495 2981/5496 2981/5497 \
          1822/5298 1822/5299 1822/5300 1822/5301 1822/5302 1822/5303 1822/5304 \
          1950/4298 1950/4299 1950/4300 1950/4301 1950/4302 1950/4303 1950/4304 1950/4305 \
          1959/4411 1959/4412 1959/4413 1959/4414 1959/4415 1959/4416 1959/4417 \
          1969/4258 1969/4259 1969/4260 1969/4261 1969/4262 1969/4263 1969/4264 1969/4265 \
          1993/5526 1993/5527 1993/5528 1993/5529 1993/5530 1993/5531 1993/5532 1993/5541 \
          5159/5184 \
          2952/4607 2952/4608 2952/4609 2952/4610 2952/4611 2952/4612 2952/4613 \
          2952/4622 2952/4623 2952/4624 2952/4625 2952/4626 2952/4627 2952/4628 2952/4629 \
          2952/4646 2952/4647 2952/4648 2952/4649 2952/4650 2952/4651 2952/4652 2952/4653 \
          2952/4662 2952/4663 2952/4664 2952/4665 2952/4666 2952/4667 2952/4668 2952/4669 \
          2952/4710 2952/4711 2952/4712 2952/4713 2952/4714 2952/4715 2952/4716 2952/4717 \
          2952/4718 2952/4719 2952/4720 2952/4721 2952/4722 2952/4723 2952/4724 2952/4725 \
          2940/4479 2940/4480 2940/4481 2940/4482 2940/4483 2940/4484 2940/4485 \
          2940/4494 2940/4495 2940/4496 2940/4497 2940/4498 2940/4499 2940/4500 2940/4501 \
          2940/4582 2940/4583 2940/4584 2940/4585 2940/4586 2940/4587 2940/4588 2940/4589 \
          2940/4590 2940/4591 2940/4592 2940/4593 2940/4594 2940/4595 2940/4596 2940/4597; do
  curl -s -o /dev/null -w "delete variation $pv -> %{http_code}\n" \
    -u "$CK:$CS" -A "$UA" -X DELETE "$B/products/${pv%/*}/variations/${pv#*/}?force=true"
done

# Variable-parent -> simple converteren (single-producten zonder AFAS-kinderen die
# onterecht een variable-container met één self-variation kregen). De self-variation
# is hierboven al verwijderd, dus de bare SKU is vrij om op de simple te zetten.
# Format: "<wc_id> <bare_sku>"
while read -r id sku; do
  [ -z "$id" ] && continue
  curl -s -o /dev/null -w "convert $id -> simple ($sku) -> %{http_code}\n" \
    -u "$CK:$CS" -A "$UA" -X PUT "$B/products/$id" \
    -H 'Content-Type: application/json' -d "{\"type\":\"simple\",\"sku\":\"$sku\"}"
done <<'CONV'
4018 PP-JTM-100M-MS
4049 PP-IULM-100M-MS
CONV

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
    rows = list(csv.DictReader(f))
total = len(rows)
print(f"  {total} producten te herstellen", flush=True)
for i, row in enumerate(rows, 1):
    wid, typ, par, sku, meta = row['wc_id'], row['type'], row['parent_id'], row['sku'], row['meta']
    body = {}
    if sku:
        body['sku'] = sku
    if meta:
        body['meta_data'] = [{'key': '_afas_artikelnummer', 'value': meta}]
    if not body:
        skip += 1
    else:
        ep = f"products/{par}/variations/{wid}" if typ == 'variation' and par else f"products/{wid}"
        req = urllib.request.Request(f"{base}/{ep}", data=json.dumps(body).encode(), method='PUT')
        req.add_header('Authorization', 'Basic ' + auth)
        req.add_header('Content-Type', 'application/json')
        req.add_header('User-Agent', ua)
        try:
            urllib.request.urlopen(req, timeout=60, context=ctx); ok += 1
        except Exception as e:
            fail += 1; print(f"  FAIL {ep}: {str(e)[:90]}", flush=True)
    # voortgang elke 50 producten + op het einde
    if i % 50 == 0 or i == total:
        print(f"  [{i}/{total}] ok={ok} fail={fail} skip={skip}", flush=True)
print(f"restore: ok={ok} fail={fail} skip(geen sku/meta)={skip}")
PYEOF
