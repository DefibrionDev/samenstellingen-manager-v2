#!/usr/bin/env python3
"""
One-off: ken het WC-native merk "Prestan" toe aan alle Prestan-producten op ARKY.

"Brand" = de WooCommerce Brands-taxonomie (/products/brands), NIET het pa_brand-
attribuut. Prestan-producten herkend op naam "PRESTAN" of SKU PP-/RPP-.
Alleen parents (simple + variable); variaties erven het merk van de parent.

Maakt het merk "Prestan" aan als 't nog niet bestaat, en zet het op elk product
(idempotent: PUT brands=[{id}] overschrijft niet-destructief). Dry-run default.

GEBRUIK (vanuit repo-root):
  python3 migration/arky-set-prestan-brand.py            # dry-run
  python3 migration/arky-set-prestan-brand.py --apply
"""
import sqlite3, json, urllib.request, urllib.error, base64, ssl, sys, os

APPLY = "--apply" in sys.argv
STORE = "partner.arkycase.eu"
REPO_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB_PATH = os.path.join(REPO_ROOT, "tmp", "samenstellingen.sqlite")
con = sqlite3.connect(DB_PATH)

row = con.execute(
    "SELECT consumer_key,consumer_secret,base_url FROM woocommerce_stores WHERE name=?", (STORE,)
).fetchone()
if row is None:
    sys.exit(f"Store '{STORE}' niet in snapshot.")
ck, cs, base = row
B = base.rstrip("/") + "/wp-json/wc/v3"
auth = base64.b64encode(f"{ck}:{cs}".encode()).decode()
ctx = ssl.create_default_context(); ctx.check_hostname = False; ctx.verify_mode = ssl.CERT_NONE


def api(path, method="GET", body=None):
    data = json.dumps(body).encode() if body is not None else None
    r = urllib.request.Request(f"{B}/{path}", data=data, method=method)
    r.add_header("Authorization", "Basic " + auth); r.add_header("User-Agent", "Mozilla/5.0")
    if body is not None:
        r.add_header("Content-Type", "application/json")
    return json.load(urllib.request.urlopen(r, timeout=60, context=ctx))


# Prestan-parents uit de snapshot.
targets = con.execute("""
  SELECT wp.wc_product_id, wp.type, wp.sku, wp.name
  FROM woocommerce_products wp JOIN woocommerce_stores ws ON ws.id=wp.store_id
  WHERE ws.name=? AND wp.type IN ('simple','variable')
    AND (LOWER(wp.name) LIKE '%prestan%' OR UPPER(wp.sku) LIKE 'PP-%' OR UPPER(wp.sku) LIKE 'RPP-%')
  ORDER BY wp.type, wp.wc_product_id""", (STORE,)).fetchall()

print("MODE:", "APPLY" if APPLY else "DRY-RUN")
print(f"Prestan-parents: {len(targets)}")

# Merk "PRESTAN" (all-caps) opzoeken / aanmaken / hernoemen.
BRAND_NAME = "PRESTAN"
brand_id = None
brand_name_now = None
for b in api("products/brands?per_page=100&search=Prestan"):
    if b.get("name", "").lower() == "prestan":
        brand_id = b["id"]; brand_name_now = b.get("name")
if brand_id is None:
    if APPLY:
        brand_id = api("products/brands", "POST", {"name": BRAND_NAME})["id"]
        print(f"  merk '{BRAND_NAME}' aangemaakt (id {brand_id})")
    else:
        print(f"  merk '{BRAND_NAME}' bestaat nog niet -> zou aangemaakt worden")
elif brand_name_now != BRAND_NAME:
    if APPLY:
        api(f"products/brands/{brand_id}", "PUT", {"name": BRAND_NAME})
        print(f"  merk hernoemd '{brand_name_now}' -> '{BRAND_NAME}' (id {brand_id})")
    else:
        print(f"  merk (id {brand_id}) heet nu '{brand_name_now}' -> zou hernoemd worden naar '{BRAND_NAME}'")
else:
    print(f"  merk '{BRAND_NAME}' bestaat (id {brand_id})")

ok = fail = skip = 0
for wcid, typ, sku, name in targets:
    if not APPLY:
        print(f"  ~ {typ:8} {wcid:<5} {sku or '-':<22} -> {BRAND_NAME}")
        continue
    try:
        res = api(f"products/{wcid}", "PUT", {"brands": [{"id": brand_id}]})
        names = [b.get("name") for b in res.get("brands", [])]
        if BRAND_NAME in names:
            ok += 1
        else:
            fail += 1; print(f"  FAIL {wcid} {sku}: brands={names}")
    except Exception as e:
        fail += 1; print(f"  FAIL {wcid} {sku}: {str(e)[:90]}")

if APPLY:
    print(f"\nGezet: ok={ok} fail={fail}")
else:
    print("\nDRY-RUN — geen mutaties. Run met --apply.")
