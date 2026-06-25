#!/usr/bin/env python3
"""
One-off: zet de DEFAULT-variatie (default_attributes) voor de variabele
Prestan-producten op ARKY, zodat er bij het openen meteen een variatie
voorgeselecteerd staat. Voorkeur: Skin Tone = Light, Pack = Single.

Per parent wordt de BEST passende BESTAANDE variatie gekozen (zodat de default
altijd een geldige combinatie is) op basis van een voorkeur-score; de default-
attributen worden gelijkgezet aan die variatie. Raakt niets anders aan.

Dry-run default; --apply om te schrijven.

GEBRUIK (vanuit repo-root):
  python3 migration/arky-prestan-default-variations.py            # dry-run
  python3 migration/arky-prestan-default-variations.py --apply
  python3 migration/arky-prestan-default-variations.py 3949       # alleen die wc-id
"""
import sqlite3, json, urllib.request, urllib.error, base64, ssl, sys, os

APPLY = "--apply" in sys.argv
ONLY = [int(a) for a in sys.argv[1:] if a.isdigit()]
STORE = "partner.arkycase.eu"
REPO_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
con = sqlite3.connect(os.path.join(REPO_ROOT, "tmp", "samenstellingen.sqlite"))
ck, cs, base = con.execute(
    "SELECT consumer_key,consumer_secret,base_url FROM woocommerce_stores WHERE name=?", (STORE,)).fetchone()
B = base.rstrip("/") + "/wp-json/wc/v3"
auth = base64.b64encode(f"{ck}:{cs}".encode()).decode()
ctx = ssl.create_default_context(); ctx.check_hostname = False; ctx.verify_mode = ssl.CERT_NONE


def api(path, method="GET", body=None):
    data = json.dumps(body).encode() if body is not None else None
    r = urllib.request.Request(f"{B}/{path}", data=data, method=method)
    r.add_header("Authorization", "Basic " + auth); r.add_header("User-Agent", "Mozilla/5.0")
    if body is not None:
        r.add_header("Content-Type", "application/json")
    return json.load(urllib.request.urlopen(r, timeout=90, context=ctx))


def all_variations(pid):
    out = []; page = 1
    while True:
        chunk = api(f"products/{pid}/variations?per_page=100&page={page}&status=any&_fields=id,sku,attributes")
        out.extend(chunk)
        if len(chunk) < 100:
            break
        page += 1
    return out


def score(vals):
    """Hogere score = betere default. Voorkeur: Light skin, Single pack."""
    s = 0
    if vals.get("Skin Tone") == "Light":
        s += 1000
    if vals.get("Pack") == "Single":
        s += 100
    if vals.get("Gender") == "Male":
        s += 10
    if vals.get("Type") == "Face Shield":
        s += 10
    lang = vals.get("Language", "")
    if lang == "English/Dutch":
        s += 10
    elif lang.startswith("English"):
        s += 5
    return s


# variabele Prestan-parents
parents = con.execute("""
  SELECT wp.wc_product_id, wp.name FROM woocommerce_products wp
  JOIN woocommerce_stores ws ON ws.id=wp.store_id
  WHERE ws.name=? AND wp.type='variable'
    AND (LOWER(wp.name) LIKE '%prestan%' OR UPPER(wp.sku) LIKE 'PP-%' OR UPPER(wp.sku) LIKE 'RPP-%')
  ORDER BY wp.wc_product_id""", (STORE,)).fetchall()
if ONLY:
    parents = [p for p in parents if p[0] in ONLY]

print("MODE:", "APPLY" if APPLY else "DRY-RUN", "|", len(parents), "parents")
for pid, name in parents:
    vs = all_variations(pid)
    # alleen variaties met variatie-attributen (de variatie-assen)
    cand = []
    for v in vs:
        attrs = v.get("attributes", [])
        if not attrs:
            continue
        vals = {a.get("name"): a.get("option") for a in attrs}
        cand.append((score(vals), v))
    if not cand:
        print(f"  wc {pid:<5} {name[:40]:40} -> GEEN variatie-attributen (overslaan)")
        continue
    cand.sort(key=lambda x: x[0], reverse=True)
    best = cand[0][1]
    default_attributes = [{"id": a["id"], "option": a["option"]} for a in best["attributes"]]
    label = " | ".join(f"{a['name']}={a['option']}" for a in best["attributes"])
    print(f"  wc {pid:<5} {name[:38]:38} -> default: {best.get('sku')}  [{label}]")
    if APPLY:
        api(f"products/{pid}", "PUT", {"default_attributes": default_attributes})

if not APPLY:
    print("\nDRY-RUN — geen mutaties. Run met --apply.")
else:
    print("\nKlaar.")
