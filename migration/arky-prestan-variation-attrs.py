#!/usr/bin/env python3
"""
ARKY Prestan — vul de variatie-attributen van de variabele Prestan-producten,
afgeleid uit de spec-CSV `Prestan te publiceren - All SKUs.csv` (kolom Variant).

Assen (globale attribuut-taxonomieën):
  Gender      -> pa_gender        (Male / Female)
  Pack        -> pa_pack          (Single / 4-Pack / 3-Pack / 5-Pack / 12-Pack / 50-pack / Kit …)
  Skin Tone   -> pa_skin-tone     (Light / Dark / Diversity)
  Type        -> pa_type          (Face Shield / Lung Bag / …)
  Pads        -> pa_pads          (2 pads / 8 pads)
  Language    -> pa_language       (bestaat al, id via slug)

Een as wordt een VARIATIE-attribuut als 'ie >1 waarde heeft binnen de parent;
bij 1 waarde een VAST attribuut. Brand (pa_brand=PRESTAN) blijft staan (merge).

Koppeling: elke WC-variatie wordt via de `_afas_artikelnummer`-meta (= AFAS-
itemcode) aan een rij in tmp/prestan-variatie-assen.csv gekoppeld (kolom SKU =
AFAS-itemcode). NIET op de WC-SKU.

VOORWAARDEN: tmp/prestan-variatie-assen.csv bestaat (genereer met de axes-stap);
wc:pull is gedraaid; REST-keys in de snapshot.

GEBRUIK (vanuit repo-root):
  python3 migration/arky-prestan-variation-attrs.py            # dry-run, alle Prestan-parents
  python3 migration/arky-prestan-variation-attrs.py --apply
  python3 migration/arky-prestan-variation-attrs.py 3949       # dry-run, één wc-parent
"""
import sqlite3, json, urllib.request, urllib.error, base64, ssl, sys, os, csv

APPLY = "--apply" in sys.argv
ONLY = [int(a) for a in sys.argv[1:] if a.isdigit()]
STORE = "partner.arkycase.eu"
REPO_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB_PATH = os.path.join(REPO_ROOT, "tmp", "samenstellingen.sqlite")
AXES_CSV = os.path.join(REPO_ROOT, "tmp", "prestan-variatie-assen.csv")
con = sqlite3.connect(DB_PATH)

# As -> (globale pa-slug, display-naam, type)
AXIS_DEF = [
    ("Gender", "pa_gender", "Gender", "select"),
    ("Language", "pa_language", "Language", "button"),
    ("Pack", "pa_pack", "Pack", "select"),
    # slug 'type' is een WC-gereserveerde term -> pa_consumable-type (weergave blijft "Type")
    ("Type", "pa_consumable-type", "Type", "select"),
    ("Pads", "pa_pads", "Pads", "select"),
    ("Skin Tone", "pa_skin-tone", "Skin Tone", "select"),
]
AXIS_ORDER = [a[0] for a in AXIS_DEF]

# assen-CSV inlezen: itemcode -> {as: waarde}
axes_by_ic = {}
with open(AXES_CSV) as f:
    for row in csv.DictReader(f):
        ic = (row.get("SKU") or "").strip()
        if not ic:
            continue
        axes_by_ic[ic] = {a: (row.get(a) or "").strip() for a in AXIS_ORDER}

# Normalisatie-overrides: de Adult-bundel (6257) had in de CSV afwijkende Type-labels
# ("Face Shield only" / "Face-Shield/Lung Bag"); gelijktrekken met de rest.
TYPE_OVERRIDE = {"PP-AFS-50": "Face Shield", "PP-ALB-50": "Lung Bag"}
for ic, val in TYPE_OVERRIDE.items():
    if ic in axes_by_ic:
        axes_by_ic[ic]["Type"] = val

row = con.execute(
    "SELECT consumer_key,consumer_secret,base_url FROM woocommerce_stores WHERE name=?", (STORE,)
).fetchone()
ck, cs, base = row
B = base.rstrip("/") + "/wp-json/wc/v3"
auth = base64.b64encode(f"{ck}:{cs}".encode()).decode()
ctx = ssl.create_default_context(); ctx.check_hostname = False; ctx.verify_mode = ssl.CERT_NONE


def api(path, method="GET", body=None):
    data = json.dumps(body).encode() if body is not None else None
    last = None
    for attempt in range(4):
        r = urllib.request.Request(f"{B}/{path}", data=data, method=method)
        r.add_header("Authorization", "Basic " + auth); r.add_header("User-Agent", "Mozilla/5.0")
        if body is not None:
            r.add_header("Content-Type", "application/json")
        try:
            return json.load(urllib.request.urlopen(r, timeout=90, context=ctx))
        except urllib.error.HTTPError as e:
            last = e
            if e.code in (429, 500, 502, 503, 504) and attempt < 3:
                import time; time.sleep(2 ** attempt); continue
            raise
    raise last


def all_variations(pid):
    out = []; page = 1
    while True:
        chunk = api(f"products/{pid}/variations?per_page=100&page={page}&status=any&_fields=id,sku,meta_data")
        out.extend(chunk)
        if len(chunk) < 100:
            break
        page += 1
    return out


def meta_ic(v):
    for m in v.get("meta_data", []):
        if m.get("key") == "_afas_artikelnummer":
            return str(m.get("value") or "")
    return ""


# Globale-attribuut-laag
attr_registry = {a["slug"]: a for a in api("products/attributes?per_page=100")}
PA_ID = {}
for axis, slug, disp, typ in AXIS_DEF:
    if slug in attr_registry:
        PA_ID[axis] = attr_registry[slug]["id"]
    elif APPLY:
        created = api("products/attributes", "POST", {"name": disp, "slug": slug.removeprefix("pa_"), "type": typ})
        attr_registry[slug] = created; PA_ID[axis] = created["id"]
    else:
        PA_ID[axis] = None  # dry-run: nog aan te maken

_term_cache = {}
_pending = set()


def terms_for(attr_id):
    if attr_id not in _term_cache:
        out = {}; page = 1
        while True:
            chunk = api(f"products/attributes/{attr_id}/terms?per_page=100&page={page}&_fields=id,name,slug")
            for t in chunk:
                out[t["name"]] = t["slug"]
            if len(chunk) < 100:
                break
            page += 1
        _term_cache[attr_id] = out
    return _term_cache[attr_id]


def ensure_term(axis, name):
    attr_id = PA_ID[axis]
    if attr_id is None:
        if (axis, name) in _pending:
            return False
        _pending.add((axis, name)); return True
    cache = terms_for(attr_id)
    if name in cache:
        return False
    if APPLY:
        created = api(f"products/attributes/{attr_id}/terms", "POST", {"name": name})
        cache[name] = created["slug"]
    else:
        cache[name] = "(dry-run)"
    return True


# WC variabele Prestan-parents
parents = con.execute("""
  SELECT wp.wc_product_id, wp.name FROM woocommerce_products wp
  JOIN woocommerce_stores ws ON ws.id=wp.store_id
  WHERE ws.name=? AND wp.type='variable'
    AND (LOWER(wp.name) LIKE '%prestan%' OR UPPER(wp.sku) LIKE 'PP-%' OR UPPER(wp.sku) LIKE 'RPP-%')
  ORDER BY wp.wc_product_id""", (STORE,)).fetchall()
if ONLY:
    parents = [p for p in parents if p[0] in ONLY]

print("MODE:", "APPLY" if APPLY else "DRY-RUN")
print("Globale attributen:", {a: PA_ID[a] for a in AXIS_ORDER})

new_terms = 0
for pid, name in parents:
    pa = api(f"products/{pid}?_fields=id,name,attributes")
    vs = all_variations(pid)
    plan = {}; bad = []
    axis_vals = {a: [] for a in AXIS_ORDER}
    for v in vs:
        ic = meta_ic(v)
        ax = axes_by_ic.get(ic)
        if ax is None:
            bad.append(f"{v.get('sku')}(meta={ic or '-'})"); continue
        present = {a: ax[a] for a in AXIS_ORDER if ax[a]}
        plan[v["id"]] = present
        for a, val in present.items():
            axis_vals[a].append(val)
    used = [a for a in AXIS_ORDER if axis_vals[a]]
    var_axes = [a for a in used if len(set(axis_vals[a])) > 1]
    fix_axes = [a for a in used if len(set(axis_vals[a])) == 1]
    print(f"\n### wc {pid}  {pa.get('name')}  ({len(vs)} var, {len(plan)} mapbaar, niet-mapbaar={len(bad)})")
    print(f"   variatie-assen: {var_axes or '-'} | vaste assen: {fix_axes or '-'}")
    for a in used:
        print(f"     {a}: {sorted(set(axis_vals[a]))}")
    if bad:
        print(f"     NIET-MAPBAAR: {bad[:10]}")

    # termen verzekeren
    for a in used:
        for val in sorted(set(axis_vals[a])):
            if ensure_term(a, val):
                new_terms += 1
                print(f"     {'+ TERM' if APPLY else '~ zou term'}: {PA_ID[a] and 'pa' or 'pa'}_{a.lower().replace(' ','-')} '{val}'")

    if not APPLY:
        continue

    # parent attributen: Brand behouden + assen
    keep = []
    for a0 in pa.get("attributes", []):
        if a0.get("id") == attr_registry.get("pa_brand", {}).get("id") or (not a0.get("id") and a0.get("name") == "Brand"):
            spec = {"visible": True, "variation": False, "options": a0.get("options", [])}
            if a0.get("id"):
                spec["id"] = a0["id"]
            else:
                spec["name"] = a0["name"]
            keep.append(spec)
    attrs = list(keep)
    for a in used:
        attrs.append({"id": PA_ID[a], "visible": True, "variation": a in var_axes,
                      "options": sorted(set(axis_vals[a]))})
    api(f"products/{pid}", "PUT", {"attributes": attrs})
    print("   [APPLY] parent-attributen gezet")
    ok = fail = 0
    for vid, present in plan.items():
        body = {"attributes": [{"id": PA_ID[a], "option": present[a]} for a in var_axes if a in present]}
        try:
            api(f"products/{pid}/variations/{vid}", "PUT", body); ok += 1
        except Exception as e:
            fail += 1; print(f"     FAIL {vid}: {str(e)[:90]}")
    print(f"   [APPLY] variaties gezet: ok={ok} fail={fail}")

print(f"\n{'Aangemaakte' if APPLY else 'Te maken'} nieuwe termen: {new_terms}")
if not APPLY:
    print("DRY-RUN — geen mutaties. Run met --apply.")
