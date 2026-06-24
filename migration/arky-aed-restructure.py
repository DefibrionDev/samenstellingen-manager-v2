#!/usr/bin/env python3
"""
ARKY AED-restructure — zet de variabele AED-parents op de ARKY-shop naar het
reseller-873-model (Engelstalig):

  * vast attribuut   : Brand
  * variatie-assen   : Language / Connectivity / Options  — maar een as wordt
                       alleen een variatie-attribuut als 'ie >1 waarde heeft;
                       bij precies 1 waarde (bv. enkel Connectivity "None") wordt
                       'ie een VAST attribuut (geen zinloze dropdown).
  * default-variatie : Language = Engelse variant, Connectivity = None, Options = Defibrillator

Elke WC-variatie wordt aan de tool-data gekoppeld via de _afas_artikelnummer-meta
(= AFAS-samenstelling-itemcode) en daaruit worden de attribuutwaarden afgeleid:
  Language     <- group_bases.language_code  (NL/EN/FR -> Dutch/English/French)
  Connectivity <- group_bases.variant_label  (leeg -> None, anders 4G/WiFi/SIGFOX/…)
  Options      <- accessoires.naam_kort_en    (kale base -> "Defibrillator")

VOORWAARDEN (anders mist 'ie data):
  * `afas:pull` is gedraaid -> group_variants matched + accessoires.naam_kort_en gevuld.
  * `wc:pull --store=arkycase.defibrion.dev` is gedraaid -> de snapshot kent de
    ARKY variable-parents (wc_product_id) en de variaties bestaan live op de shop.
  * REST-keys van ARKY staan in de snapshot (woocommerce_stores) — die leest dit script.

GEBRUIK (vanuit de repo-root):
  python3 migration/arky-aed-restructure.py --all                 # dry-run, alle 17 parents
  python3 migration/arky-aed-restructure.py --apply --all         # schrijf live
  python3 migration/arky-aed-restructure.py 52120 21019-UK        # dry-run, specifieke heads
  python3 migration/arky-aed-restructure.py --apply --no-variations --all   # alleen parents (attrs+default)

Default (zonder --all en zonder head-argumenten): de 2 test-AED's (52120 + 21019-UK).
Schrijven is dry-run by default; --apply is vereist om te muteren.
"""
import sqlite3, json, urllib.request, base64, ssl, sys, time, os

APPLY = "--apply" in sys.argv
NO_VARS = "--no-variations" in sys.argv   # alleen parent (attrs + default_attributes)
ALL = "--all" in sys.argv                 # alle ARKY variable-parents i.p.v. de 2 test-AED's
args = [a for a in sys.argv[1:] if not a.startswith("--")]

STORE = "arkycase.defibrion.dev"
REPO_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB_PATH = os.path.join(REPO_ROOT, "tmp", "samenstellingen.sqlite")

con = sqlite3.connect(DB_PATH)

# family-head -> ARKY WC variable-parent id (uit de snapshot)
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
    HEADS = ["52120", "21019-UK"]   # default: de 2 test-AED's

row = con.execute(
    "SELECT consumer_key,consumer_secret,base_url FROM woocommerce_stores WHERE name=?", (STORE,)
).fetchone()
if row is None:
    sys.exit(f"Store '{STORE}' niet gevonden in de snapshot — draai eerst wc:pull / registreer de store.")
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
        chunk = api(f"products/{pid}/variations?per_page=100&page={page}&status=any&_fields=id,sku,status,attributes,meta_data")
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


# tool: afas_itemcode -> (language_code, variant_label, accessoire_itemcode|None, naam_kort_en|None)
tool = {}
for ic, lc, vl, acc_ic, acc_en in con.execute("""
  SELECT gv.afas_samenstelling_itemcode, gb.language_code, gb.variant_label, a.itemcode, a.naam_kort_en
  FROM group_variants gv JOIN group_bases gb ON gb.id=gv.base_id
  LEFT JOIN accessoires a ON a.id=gv.accessoire_id
  WHERE gv.afas_samenstelling_itemcode IS NOT NULL AND gv.afas_status='matched'"""):
    tool[ic] = (lc, vl, acc_ic, acc_en)

# Brand: groepsnaam-eerste-woord klopt voor Mindray/Philips/Reanibex/Zoll, maar niet
# voor Heartsine ("AED Samaritan...") en CU Medical ("CU Medical..." -> "CU").
BRAND_OVERRIDE = {
    '11113': 'Heartsine', '11123': 'Heartsine', '11133': 'Heartsine',
    '064.1309-SAM-UK': 'CU Medical', '064.1338-SAM-DE': 'CU Medical',
}
brand_by_head = {}
for head in PARENTS:
    g = con.execute("SELECT name FROM groups WHERE family_head_itemcode=?", (head,)).fetchone()
    brand_by_head[head] = BRAND_OVERRIDE.get(head, g[0].split()[0] if g else "")


def options_label(acc_ic, acc_en):
    if acc_ic is None:
        return "Defibrillator"
    return (acc_en or "").strip() or None


print("MODE:", "APPLY" if APPLY else "DRY-RUN")
missing = [h for h in HEADS if h not in PARENTS]
if missing:
    print("  LET OP: geen ARKY variable-parent in snapshot voor:", missing)
for head in [h for h in HEADS if h in PARENTS]:
    pid = PARENTS[head]
    brand = brand_by_head.get(head, "")
    pa = api(f"products/{pid}?_fields=id,name,sku,type,attributes")
    vs = all_variations(pid)
    print(f"\n===== {pa.get('name')}  (head {head}, wc {pid}, type={pa.get('type')}, {len(vs)} variaties) =====")
    print("  Brand:", brand)
    print("  huidige attributen:", [(a['name'], 'var' if a.get('variation') else 'vast') for a in pa.get('attributes', [])])

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
        plan[v['id']] = (v.get('sku'), v.get('status'), L, C, O)
        langs.add(L); conns.add(C); opts.add(O)

    print(f"  -> Language : {sorted(langs)}")
    print(f"  -> Connectivity: {sorted(conns)}")
    print(f"  -> Options  : {sorted(opts)}")
    print(f"  -> in te vullen variaties: {len(plan)}; niet-mapbaar: {len(bad)}")
    if bad:
        print("     NIET-MAPBAAR:", bad[:30])
    for vid, (sku, st, L, C, O) in list(plan.items())[:8]:
        print(f"       {vid} {sku:18} {st:8} | {L} | {C} | {O}")
    if len(plan) > 8:
        print(f"       ... (+{len(plan)-8})")

    # default-variatie: Connectivity=None, Language=Engelse variant, Options=Defibrillator
    eng_cands = sorted([l for l in langs if l.split('/')[0] == 'English'], key=len)
    default_lang = eng_cands[0] if eng_cands else (sorted(langs)[0] if langs else None)
    default_conn = 'None' if 'None' in conns else (sorted(conns)[0] if conns else None)
    default_opt = 'Defibrillator' if 'Defibrillator' in opts else (sorted(opts)[0] if opts else None)
    print(f"  -> default: Language={default_lang} | Connectivity={default_conn} | Options={default_opt}")

    axes = [
        ("Language", sorted(langs), default_lang),
        ("Connectivity", sorted(conns), default_conn),
        ("Options", sorted(opts), default_opt),
    ]
    variation_axes = {name for name, values, _ in axes if len(values) > 1}
    fixed_axes = {name for name, values, _ in axes if len(values) == 1}
    print(f"  -> variatie-attributen: {sorted(variation_axes) or '-'}")
    print(f"  -> vaste attributen: {sorted(fixed_axes) or '-'}")

    if APPLY:
        attrs = [{"name": "Brand", "visible": True, "variation": False, "options": [brand]}]
        for name, values, _default in axes:
            if values:
                attrs.append({"name": name, "visible": True, "variation": name in variation_axes, "options": values})
        default_attributes = [
            {"name": name, "option": default}
            for name, _values, default in axes
            if name in variation_axes and default
        ]
        api(f"products/{pid}", "PUT", {"attributes": attrs, "default_attributes": default_attributes})
        print("  [APPLY] parent-attributen + defaults gezet")
        if NO_VARS:
            continue
        ok = fail = 0
        for vid, (sku, st, L, C, O) in plan.items():
            value_by_axis = {"Language": L, "Connectivity": C, "Options": O}
            body = {"attributes": [
                {"name": name, "option": value_by_axis[name]}
                for name in ("Language", "Connectivity", "Options")
                if name in variation_axes
            ]}
            try:
                api(f"products/{pid}/variations/{vid}", "PUT", body); ok += 1
            except Exception as e:
                fail += 1; print(f"     FAIL {vid} {sku}: {str(e)[:90]}")
        print(f"  [APPLY] variaties gezet: ok={ok} fail={fail}")
