#!/usr/bin/env python3
"""
One-off: zet het merk voor ALLE ARKY-producten op TWEE plekken (bewust dubbel):
  1. de WC-native Brands-taxonomie (/products/brands) — het echte merk
  2. het globale pa_brand-attribuut (vast, zichtbaar) — "Brand"

Merk-bepaling (direct op naam-proxy, gebruikerskeuze — geen review-CSV):
  * managed AED-parents -> uit de groeps-data (groepsnaam-eerste-woord + overrides),
    betrouwbaar (Reanibex/Mindray/Philips/Zoll/Heartsine/CU Medical).
  * overige producten   -> alias-map op het eerste woord van de productnaam.
  * onbekend/ambigu (AED, CPR, Universal, …) -> GEEN merk (overgeslagen, gelogd).

Attributen worden GEMERGED: op AED-parents blijven de al-globale Language/
Connectivity/Options staan; het custom 'Brand' (en evt. oude pa_brand) wordt
vervangen door het globale pa_brand. Brands-termen + pa_brand-termen worden
aangemaakt waar nodig (case-insensitief hergebruik; 'Prestan' -> 'PRESTAN').

Dry-run default; --apply om te schrijven. Idempotent.

GEBRUIK (vanuit repo-root):
  python3 migration/arky-set-brands.py            # dry-run
  python3 migration/arky-set-brands.py --apply
"""
import sqlite3, json, urllib.request, urllib.error, base64, ssl, sys, os

APPLY = "--apply" in sys.argv
ONLY = [int(a) for a in sys.argv[1:] if a.isdigit()]  # optioneel: beperk tot deze wc-id's
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
    return json.load(urllib.request.urlopen(r, timeout=90, context=ctx))


# ── Merk-resolutie ──────────────────────────────────────────────────────────
# Canonieke merknaam per eerste-woord (lowercased, zonder ® ™ !).
ALIAS = {
    "laerdal": "Laerdal", "resusci": "Laerdal", "little": "Laerdal", "skillguide": "Laerdal",
    "zoll": "ZOLL", "stat-padz": "ZOLL",
    "prestan": "PRESTAN",
    "philips": "Philips",
    "ambu": "Ambu", "ambuman": "Ambu",
    "defibtech": "Defibtech",
    "physio-control": "Physio-Control", "physio": "Physio-Control", "lifepak": "Physio-Control",
    "heartsine": "Heartsine",
    "cardiac": "Cardiac Science",
    "arky": "ARKY",
    "mindray": "Mindray",
    "schiller": "Schiller",
    "rotaid": "Rotaid",
    "brayden": "Brayden",
    "primedic": "Primedic",
    "cu": "CU Medical", "cu-medical": "CU Medical",
    "evacusafe": "Evacusafe",
    "aivia": "Aivia",
    "nihon": "Nihon Kohden",
    "lifevac": "LifeVac",
    "reanibex": "Reanibex",
    "bexen": "Bexen",
}


# Handmatige merk-overrides per wc-id (uit AFAS-naam afgeleid; gaat vóór naam-proxy).
# Voor producten waar de WC-naam het merk niet toont maar de AFAS-naam wel.
MANUAL_BRAND = {
    191: "Ambu", 192: "Ambu", 1367: "Ambu", 1425: "Ambu",   # Ambu Choking/Baby
    1635: "Laerdal", 1824: "Laerdal",                         # Laerdal Mini-Anne / Manikin Filters
    2103: "Mindray",                                          # AFAS: Mindray Beneheart-trainer (WC zegt "Universal")
    6266: "PRESTAN",                                          # PPA-prefix = Prestan
}


def resolve_brand(text):
    if not text:
        return None
    first = text.strip().split()[0].lower().rstrip("®™!.,")
    return ALIAS.get(first)


def brand_in_name(name):
    """Fallback: scan de hele naam; geef het merk alleen terug als er PRECIES
    één bekend merk in voorkomt (anders te ambigu -> None)."""
    found = set()
    for w in name.replace("/", " ").split():
        c = ALIAS.get(w.lower().rstrip("®™!.,()"))
        if c:
            found.add(c)
    return next(iter(found)) if len(found) == 1 else None


# managed AED-parents: family-head -> wc-id, en groepsnaam voor merk.
AED_BRAND_OVERRIDE = {
    "11113": "Heartsine", "11123": "Heartsine", "11133": "Heartsine",
    "064.1309-SAM-UK": "CU Medical", "064.1338-SAM-DE": "CU Medical",
}
aed_brand_by_wcid = {}
for head, wcid in con.execute("""
  SELECT g.family_head_itemcode, wp.wc_product_id
  FROM woocommerce_products wp JOIN woocommerce_stores ws ON ws.id=wp.store_id
  JOIN groups g ON g.family_head_itemcode = wp.afas_itemcode
  WHERE ws.name=? AND wp.type='variable'""", (STORE,)):
    gname = con.execute("SELECT name FROM groups WHERE family_head_itemcode=?", (head,)).fetchone()
    raw = AED_BRAND_OVERRIDE.get(head, (gname[0].split()[0] if gname else ""))
    aed_brand_by_wcid[wcid] = resolve_brand(raw) or raw

# Alle parents.
parents = con.execute("""
  SELECT wp.wc_product_id, wp.type, wp.sku, wp.name
  FROM woocommerce_products wp JOIN woocommerce_stores ws ON ws.id=wp.store_id
  WHERE ws.name=? AND wp.type IN ('simple','variable')
  ORDER BY wp.type, wp.wc_product_id""", (STORE,)).fetchall()
if ONLY:
    parents = [p for p in parents if p[0] in ONLY]

plan = []          # (wcid, type, sku, name, brand, bron)
skipped = []       # (wcid, type, sku, name)
for wcid, typ, sku, name in parents:
    if wcid in MANUAL_BRAND:
        brand, bron = MANUAL_BRAND[wcid], "manual"
    elif wcid in aed_brand_by_wcid:
        brand, bron = aed_brand_by_wcid[wcid], "aed-head"
    else:
        brand = resolve_brand(name)
        bron = "naam-alias"
        if not brand:
            brand = brand_in_name(name)
            bron = "naam-scan"
    if brand:
        plan.append((wcid, typ, sku, name, brand, bron))
    else:
        skipped.append((wcid, typ, sku, name))

brands_used = sorted({p[4] for p in plan})
print("MODE:", "APPLY" if APPLY else "DRY-RUN")
print(f"Parents: {len(parents)} | met merk: {len(plan)} | overgeslagen (geen merk): {len(skipped)}")
print(f"Merken in gebruik ({len(brands_used)}): {', '.join(brands_used)}")

# ── Termen verzekeren in beide taxonomieën ──────────────────────────────────
PA_BRAND_ID = next((a["id"] for a in api("products/attributes?per_page=100") if a["slug"] == "pa_brand"), None)
if PA_BRAND_ID is None:
    sys.exit("pa_brand-attribuut niet gevonden op ARKY.")


def load_terms(path_prefix):
    out = {}  # lower-name -> {id, name}
    page = 1
    while True:
        chunk = api(f"{path_prefix}?per_page=100&page={page}&_fields=id,name,slug")
        for t in chunk:
            out[t["name"].lower()] = {"id": t["id"], "name": t["name"]}
        if len(chunk) < 100:
            break
        page += 1
    return out


brand_terms = load_terms("products/brands")              # Brands-taxonomie
pa_terms = load_terms(f"products/attributes/{PA_BRAND_ID}/terms")  # pa_brand


def ensure_term(store, path_prefix, name):
    """Zorg dat term `name` bestaat (case-insensitief). Hernoem naar canonical
    als de casing afwijkt. Return term-id (None in dry-run als nog niet bestaat)."""
    key = name.lower()
    if key in store:
        t = store[key]
        if t["name"] != name and APPLY:
            api(f"{path_prefix}/{t['id']}", "PUT", {"name": name})
            print(f"     ~ hernoemd term '{t['name']}' -> '{name}'")
            t["name"] = name
        return t["id"]
    if APPLY:
        created = api(path_prefix, "POST", {"name": name})
        store[key] = {"id": created["id"], "name": name}
        print(f"     + term '{name}' aangemaakt in {path_prefix}")
        return created["id"]
    print(f"     ~ zou term '{name}' aanmaken in {path_prefix}")
    return None


print("\nTermen verzekeren:")
brand_term_id = {}
for b in brands_used:
    bid = ensure_term(brand_terms, "products/brands", b)
    ensure_term(pa_terms, f"products/attributes/{PA_BRAND_ID}/terms", b)
    brand_term_id[b] = bid

# ── Toepassen ───────────────────────────────────────────────────────────────
def merged_attributes(current, brand):
    """Houd alle attributen behalve oud Brand/pa_brand; voeg globaal pa_brand toe."""
    out = []
    for a in current:
        is_brand = a.get("id") == PA_BRAND_ID or (not a.get("id") and a.get("name") == "Brand")
        if is_brand:
            continue
        spec = {"visible": a.get("visible", True), "variation": a.get("variation", False),
                "options": a.get("options", [])}
        if a.get("id"):
            spec["id"] = a["id"]
        else:
            spec["name"] = a["name"]
        out.append(spec)
    out.append({"id": PA_BRAND_ID, "visible": True, "variation": False, "options": [brand]})
    return out


if not APPLY:
    print("\nVoorbeeld-mapping (eerste 25):")
    for wcid, typ, sku, name, brand, bron in plan[:25]:
        print(f"  {typ:8} {wcid:<5} {sku or '-':<22} [{bron}] -> {brand}")
    if skipped:
        print(f"\nGEEN MERK ({len(skipped)}):")
        for wcid, typ, sku, name in skipped[:40]:
            print(f"  {typ:8} {wcid:<5} {sku or '-':<22} {name[:40]}")
    print("\nDRY-RUN — geen mutaties. Run met --apply.")
    sys.exit(0)

ok = fail = 0
for wcid, typ, sku, name, brand, bron in plan:
    try:
        cur = api(f"products/{wcid}?_fields=id,attributes")
        body = {
            "brands": [{"id": brand_term_id[brand]}],
            "attributes": merged_attributes(cur.get("attributes", []), brand),
        }
        api(f"products/{wcid}", "PUT", body)
        ok += 1
    except Exception as e:
        fail += 1; print(f"  FAIL {wcid} {sku}: {str(e)[:100]}")
print(f"\nGezet: ok={ok} fail={fail} | overgeslagen zonder merk: {len(skipped)}")
