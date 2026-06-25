#!/usr/bin/env python3
"""
ARKY AED — zet de variatie-assen Language / Connectivity / Options om van
PLATTE custom-attributen (per-product strings) naar GLOBALE attribuut-taxonomieën
(`pa_*`) met termen, zoals reseller. Brand blijft bewust custom (gebruikerskeuze).

Waarom: globale attributen zijn herbruikbaar, filterbaar (layered nav) en koppelen
variaties via term-id/slug i.p.v. losse strings. Het reseller-model.

Per AED-parent (family-head → ARKY variable-parent uit de snapshot):
  * Language     -> globaal pa_language (bestaat al, id via slug)
  * Connectivity -> globaal pa_connectivity (wordt aangemaakt als 't ontbreekt)
  * Options      -> globaal pa_options (bestaat al, id via slug)
  * Brand        -> blijft CUSTOM (ongewijzigd), vast attribuut

Waarden worden afgeleid uit tool-data via de _afas_artikelnummer-meta per variatie
(zelfde afleiding als arky-aed-restructure.py):
  Language     <- group_bases.language_code  (NL/EN/FR -> Dutch/English/French)
  Connectivity <- group_bases.variant_label  (leeg -> None)
  Options      <- accessoires.naam_kort_en    (kale base -> "Defibrillator")

Een as wordt alléén een variatie-attribuut bij >1 waarde; bij 1 waarde vast.
Ontbrekende termen worden aangemaakt (bestaande hergebruikt; stale termen blijven
staan — gebruikerskeuze). Termen worden op NAAM gekoppeld (zoals reseller toont).

VOORWAARDEN: `afas:pull` + `wc:pull --store=partner.arkycase.eu` gedraaid; ARKY
REST-keys in de snapshot.

GEBRUIK (vanuit repo-root):
  python3 migration/arky-aed-global-attributes.py            # dry-run, 2 test-AED's
  python3 migration/arky-aed-global-attributes.py --all      # dry-run, alle parents
  python3 migration/arky-aed-global-attributes.py --apply --all
  python3 migration/arky-aed-global-attributes.py 52120      # dry-run, specifieke head
"""
import sqlite3, json, urllib.request, urllib.error, base64, ssl, sys, time, os

APPLY = "--apply" in sys.argv
ALL = "--all" in sys.argv
args = [a for a in sys.argv[1:] if not a.startswith("--")]

STORE = "partner.arkycase.eu"
REPO_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB_PATH = os.path.join(REPO_ROOT, "tmp", "samenstellingen.sqlite")
con = sqlite3.connect(DB_PATH)

PARENTS = {}
for head, wcid in con.execute("""
  SELECT g.family_head_itemcode, wp.wc_product_id
  FROM woocommerce_products wp JOIN woocommerce_stores ws ON ws.id=wp.store_id
  JOIN groups g ON g.family_head_itemcode = wp.afas_itemcode
  WHERE ws.name=? AND wp.type='variable'""", (STORE,)):
    PARENTS[head] = wcid

if ALL:
    HEADS = sorted(PARENTS.keys())
elif args:
    HEADS = args
else:
    HEADS = ["52120", "21019-UK"]

row = con.execute(
    "SELECT consumer_key,consumer_secret,base_url FROM woocommerce_stores WHERE name=?", (STORE,)
).fetchone()
if row is None:
    sys.exit(f"Store '{STORE}' niet gevonden — draai eerst wc:pull.")
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
                time.sleep(2 ** attempt); continue
            raise
        except (urllib.error.URLError, TimeoutError) as e:
            last = e
            if attempt < 3:
                time.sleep(2 ** attempt); continue
            raise
    raise last


def all_variations(pid):
    out = []; page = 1
    while True:
        chunk = api(f"products/{pid}/variations?per_page=100&page={page}&status=any&_fields=id,sku,status,meta_data")
        out.extend(chunk)
        if len(chunk) < 100:
            break
        page += 1
    return out


LANG = {'NL': 'Dutch', 'EN': 'English', 'FR': 'French', 'DE': 'German', 'DK': 'Danish', 'CZ': 'Czech',
        'NO': 'Norwegian', 'SE': 'Swedish', 'EL': 'Greek', 'FI': 'Finnish', 'HR': 'Croatian',
        'HU': 'Hungarian', 'SK': 'Slovak', 'SL': 'Slovenian', 'ES': 'Spanish', 'PL': 'Polish',
        'LV': 'Latvian', 'RO': 'Romanian', 'IT': 'Italian', 'PT': 'Portuguese', 'LT': 'Lithuanian'}


def lang_str(code):
    toks = [t.strip().upper() for t in (code or '').split('/') if t.strip()]
    out = [LANG.get(t) for t in toks]
    return "/".join(out) if out and all(out) else None


def conn_str(label):
    return 'None' if (label or '').strip() == '' else label.strip()


# De tool leidt Options-labels af uit accessoires.naam_kort_en, maar in de shop
# zijn de pa_options-termen handmatig hernoemd naar nettere labels. Map de
# tool-naam naar de bestaande shop-term, zodat een her-run die termen HERGEBRUIKT
# i.p.v. dubbele aanmaakt. Sleutel = tool-naam (naam_kort_en), waarde = shop-term.
OPTION_TERM_OVERRIDE = {
    "ARKY Core Classic outdoor cabinet": "ARKY CORE Classic",
    "ARKY Core Plus outdoor cabinet": "ARKY CORE Plus",
    "ARKY outdoor cabinet (heated)": "ARKY Outdoor Cabinet Heated",
    "ARKY outdoor cabinet (unheated)": "ARKY Outdoor Cabinet Unheated",
    "Backpack": "First Aid Backpack",
    "green ARKY indoor cabinet": "ARKY Indoor Cabinet Green",
    "white ARKY indoor cabinet": "ARKY Indoor Cabinet White",
    # "Defibrillator" is in beide gelijk.
}


def options_label(acc_ic, acc_en):
    if acc_ic is None:
        return "Defibrillator"
    label = (acc_en or "").strip() or None
    return OPTION_TERM_OVERRIDE.get(label, label) if label else None


# tool: afas_itemcode -> (language_code, variant_label, accessoire_itemcode|None, naam_kort_en|None)
tool = {}
for ic, lc, vl, acc_ic, acc_en in con.execute("""
  SELECT gv.afas_samenstelling_itemcode, gb.language_code, gb.variant_label, a.itemcode, a.naam_kort_en
  FROM group_variants gv JOIN group_bases gb ON gb.id=gv.base_id
  LEFT JOIN accessoires a ON a.id=gv.accessoire_id
  WHERE gv.afas_samenstelling_itemcode IS NOT NULL AND gv.afas_status='matched'"""):
    tool[ic] = (lc, vl, acc_ic, acc_en)

BRAND_OVERRIDE = {
    '11113': 'Heartsine', '11123': 'Heartsine', '11133': 'Heartsine',
    '064.1309-SAM-UK': 'CU Medical', '064.1338-SAM-DE': 'CU Medical',
}
brand_by_head = {}
for head in PARENTS:
    g = con.execute("SELECT name FROM groups WHERE family_head_itemcode=?", (head,)).fetchone()
    brand_by_head[head] = BRAND_OVERRIDE.get(head, g[0].split()[0] if g else "")


# ── Globale-attribuut-laag ──────────────────────────────────────────────────
# slug (zonder pa_) -> {id, name}. WC's registry-GET levert slug MÉT pa_-prefix.
attr_registry = {a["slug"]: a for a in api("products/attributes?per_page=100")}


def ensure_global_attribute(slug_with_pa, display_name):
    """Vind globaal attribuut op pa_-slug; maak 't aan als 't ontbreekt (alleen --apply)."""
    if slug_with_pa in attr_registry:
        return attr_registry[slug_with_pa]["id"]
    if not APPLY:
        return None  # dry-run: nog niet aangemaakt
    created = api("products/attributes", "POST",
                  {"name": display_name, "slug": slug_with_pa.removeprefix("pa_"), "type": "select"})
    attr_registry[slug_with_pa] = created
    return created["id"]


# id -> {term-name: term-slug}. Lazy geladen + uitgebreid bij aanmaak.
_term_cache = {}
# Dry-run: termen op een nog-niet-bestaand attribuut (attr_id None) dedupe-set.
_pending_terms = set()


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


def ensure_term(attr_id, name):
    """Zorg dat term `name` bestaat op het attribuut; return (created_bool)."""
    if attr_id is None:
        # attribuut bestaat nog niet (dry-run) -> term zou nieuw zijn; dedupe over parents.
        if name in _pending_terms:
            return False
        _pending_terms.add(name)
        return True
    cache = terms_for(attr_id)
    if name in cache:
        return False
    if APPLY:
        created = api(f"products/attributes/{attr_id}/terms", "POST", {"name": name})
        cache[name] = created["slug"]
    else:
        cache[name] = "(dry-run)"  # markeer als gezien zodat volgende parents niet dubbeltellen
    return True


PA_LANGUAGE = ensure_global_attribute("pa_language", "Language")
PA_CONNECTIVITY = ensure_global_attribute("pa_connectivity", "Connectivity")
PA_OPTIONS = ensure_global_attribute("pa_options", "Options")

AXIS_ATTR = {"Language": PA_LANGUAGE, "Connectivity": PA_CONNECTIVITY, "Options": PA_OPTIONS}

print("MODE:", "APPLY" if APPLY else "DRY-RUN")
print("Globale attributen: pa_language=%s  pa_connectivity=%s  pa_options=%s" % (PA_LANGUAGE, PA_CONNECTIVITY, PA_OPTIONS))
if PA_CONNECTIVITY is None:
    print("  -> pa_connectivity bestaat nog NIET; wordt bij --apply aangemaakt.")

missing = [h for h in HEADS if h not in PARENTS]
if missing:
    print("  LET OP: geen ARKY variable-parent in snapshot voor:", missing)

new_terms_total = 0
for head in [h for h in HEADS if h in PARENTS]:
    pid = PARENTS[head]
    brand = brand_by_head.get(head, "")
    pa = api(f"products/{pid}?_fields=id,name,type")
    vs = all_variations(pid)
    print(f"\n===== {pa.get('name')}  (head {head}, wc {pid}, {len(vs)} variaties) =====")

    def meta_ic(v):
        for m in v.get('meta_data', []):
            if m.get('key') == '_afas_artikelnummer':
                return str(m.get('value') or '')
        return ''

    plan = {}; langs = set(); conns = set(); opts = set(); bad = []
    for v in vs:
        ic = meta_ic(v)
        t = tool.get(ic)
        if not t:
            bad.append(f"{v.get('sku')}(meta={ic or '-'})"); continue
        lc, vl, acc_ic, acc_en = t
        L = lang_str(lc); C = conn_str(vl); O = options_label(acc_ic, acc_en)
        if not L or not O:
            bad.append(f"{v.get('sku')}(L={L},O={O})"); continue
        plan[v['id']] = (v.get('sku'), L, C, O)
        langs.add(L); conns.add(C); opts.add(O)

    axes = [("Language", sorted(langs)), ("Connectivity", sorted(conns)), ("Options", sorted(opts))]
    variation_axes = {name for name, values in axes if len(values) > 1}
    print(f"  Brand (custom, vast): {brand}")
    print(f"  Language ({'var' if 'Language' in variation_axes else 'vast'}): {sorted(langs)}")
    print(f"  Connectivity ({'var' if 'Connectivity' in variation_axes else 'vast'}): {sorted(conns)}")
    print(f"  Options ({'var' if 'Options' in variation_axes else 'vast'}): {sorted(opts)}")
    print(f"  mapbare variaties: {len(plan)}; niet-mapbaar: {len(bad)}")
    if bad:
        print("     NIET-MAPBAAR:", bad[:20])

    # Termen verzekeren + tellen welke nieuw zijn.
    for name, values in axes:
        attr_id = AXIS_ATTR[name]
        for val in values:
            if ensure_term(attr_id, val):
                new_terms_total += 1
                print(f"     {'+ TERM' if APPLY else '~ zou term aanmaken'}: pa_{name.lower()} '{val}'")

    # Default-variatie: Connectivity=None, Language=Engelse variant, Options=Defibrillator.
    eng = sorted([l for l in langs if l.split('/')[0] == 'English'], key=len)
    default = {
        "Language": (eng[0] if eng else (sorted(langs)[0] if langs else None)),
        "Connectivity": ('None' if 'None' in conns else (sorted(conns)[0] if conns else None)),
        "Options": ('Defibrillator' if 'Defibrillator' in opts else (sorted(opts)[0] if opts else None)),
    }

    if not APPLY:
        continue

    # Parent: Brand custom (ongewijzigd qua aard) + globale assen.
    attrs = [{"name": "Brand", "visible": True, "variation": False, "options": [brand]}]
    for name, values in axes:
        if not values:
            continue
        attrs.append({
            "id": AXIS_ATTR[name], "visible": True,
            "variation": name in variation_axes, "options": values,
        })
    default_attributes = [
        {"id": AXIS_ATTR[name], "option": default[name]}
        for name in ("Language", "Connectivity", "Options")
        if name in variation_axes and default[name]
    ]
    api(f"products/{pid}", "PUT", {"attributes": attrs, "default_attributes": default_attributes})
    print("  [APPLY] parent-attributen (globaal) + defaults gezet")

    ok = fail = 0
    for vid, (sku, L, C, O) in plan.items():
        value_by_axis = {"Language": L, "Connectivity": C, "Options": O}
        body = {"attributes": [
            {"id": AXIS_ATTR[name], "option": value_by_axis[name]}
            for name in ("Language", "Connectivity", "Options")
            if name in variation_axes
        ]}
        try:
            api(f"products/{pid}/variations/{vid}", "PUT", body); ok += 1
        except Exception as e:
            fail += 1; print(f"     FAIL {vid} {sku}: {str(e)[:90]}")
    print(f"  [APPLY] variaties herkoppeld aan globale termen: ok={ok} fail={fail}")

print(f"\n{'Aangemaakte' if APPLY else 'Te maken'} nieuwe termen: {new_terms_total}")
if not APPLY:
    print("DRY-RUN — geen mutaties. Run met --apply (en --all voor alle parents).")
