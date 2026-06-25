#!/usr/bin/env python3
"""
One-off: vul titel / slug / short_description / description voor de variabele
Prestan-producten op ARKY, in het 2829-format (intro + Key Features + Specs),
op basis van Prestan's eigen productinfo (prestan.com).

Raakt alleen name/slug/description/short_description aan — NIET attributen,
brand, variaties of prijs. Dry-run default; --apply om te schrijven.

GEBRUIK (vanuit repo-root):
  python3 migration/arky-prestan-content.py            # dry-run
  python3 migration/arky-prestan-content.py --apply
  python3 migration/arky-prestan-content.py 3949       # alleen die wc-id
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


def ul(items):
    return "<ul>\n" + "\n".join(f"<li>{i}</li>" for i in items) + "\n</ul>"


def specs(skin="light or dark", configs=None, warranty="Three-year limited warranty", extra=None):
    rows = []
    if skin:
        rows.append(f"Skin tones: {skin}")
    if configs:
        rows.append(f"Configurations: {configs}")
    rows += (extra or [])
    rows += [warranty, "Made in the USA"]
    return "<h3>Specifications</h3>\n" + ul(rows)


PROF_FEATURES = [
    "Realistic appearance and tactile feel",
    "Visual chest rise indicator",
    "CPR Rate Monitor with real-time LED feedback (100–120 compressions per minute)",
    "Audible clicker confirms correct compression depth (2.0–2.4″ / 5–6 cm)",
    "Lightweight design with quick set-up",
    "Compliant with the latest AHA Integrated Feedback directive",
]
S2000_FEATURES = [
    "Connects via Bluetooth to the PRESTAN CPR Feedback App (iOS &amp; Android)",
    "Advanced metrics: rate, depth, recoil, ventilation, hands-off time &amp; chest-compression fraction",
    "Built-in LED CPR Rate Monitor for stand-alone feedback (100–120 cpm)",
    "Tracks up to six students simultaneously and downloads training reports",
    "Visual chest rise and realistic look &amp; feel",
    "Compliant with the latest AHA Integrated Feedback directive",
]

PRODUCTS = {
    # ── Professional manikins ────────────────────────────────────────────────
    3949: dict(
        name="PRESTAN Professional Adult CPR Manikin",
        slug="prestan-professional-adult-cpr-manikin",
        short="<p>Realistic adult CPR training manikin with visual chest rise and the PRESTAN CPR Rate Monitor for instant feedback. AHA-compliant and made in the USA.</p>"
              + ul(["Realistic appearance and tactile feel", "Visual chest rise indicator",
                    "CPR Rate Monitor — LED feedback at 100–120 cpm", "Clicker confirms correct compression depth (2.0–2.4″)",
                    "Light or dark skin tone — single, 4-pack or diversity kit"]),
        desc="<p>The PRESTAN Professional Adult Manikin is realistic to the eye and to the touch, unlike any other CPR training manikin on the market. Its lightweight design and fast set-up suit busy training programs, while the built-in CPR Rate Monitor gives instructors and students instant, objective feedback on compression rate.</p>"
             + "<h3>Key Features</h3>\n" + ul(PROF_FEATURES)
             + "\n" + specs(configs="single, 4-pack, or 4-pack diversity kit"),
    ),
    3992: dict(
        name="PRESTAN Professional Child CPR Manikin",
        slug="prestan-professional-child-cpr-manikin",
        short="<p>Pediatric CPR training manikin with childlike proportions, visual chest rise and the PRESTAN CPR Rate Monitor. AHA-compliant, made in the USA.</p>"
              + ul(["Sized and proportioned for paediatric training", "Visual chest rise indicator",
                    "CPR Rate Monitor — LED feedback at 100–120 cpm", "Clicker confirms correct compression depth (2.0–2.4″)",
                    "Light or dark skin tone — single, 4-pack or diversity kit"]),
        desc="<p>The PRESTAN Professional Child Manikin is designed with childlike proportions and facial features rather than scaled-down adult characteristics, so students train on a realistic paediatric patient. It offers the same trusted feedback as the adult model, including visual chest rise and the CPR Rate Monitor.</p>"
             + "<h3>Key Features</h3>\n" + ul(["Smaller, thinner frame proportioned for paediatric training"] + PROF_FEATURES[1:])
             + "\n" + specs(configs="single, 4-pack, or 4-pack diversity kit"),
    ),
    4005: dict(
        name="PRESTAN Professional Infant CPR Manikin",
        slug="prestan-professional-infant-cpr-manikin",
        short="<p>Life-like infant CPR training manikin with realistic weight, visual chest rise and the PRESTAN CPR Rate Monitor. AHA-compliant, made in the USA.</p>"
              + ul(["Realistic infant weight and head movement for airway training", "Visual chest rise indicator",
                    "CPR Rate Monitor — feedback at 100–120 cpm", "Clicker confirms correct compression depth (2.0–2.4″)",
                    "Light or dark skin tone — single, 4-pack or diversity kit"]),
        desc="<p>The PRESTAN Professional Infant Manikin features a life-like design with realistic weight and sculpted body contours for authentic infant CPR training. Realistic head movement supports airway-positioning practice, and the CPR Rate Monitor delivers instant feedback on compression rate and depth.</p>"
             + "<h3>Key Features</h3>\n" + ul(["Realistic infant weight with head/face tilt for airway training",
                    "Visual chest rise indicator", "CPR Rate Monitor with rate &amp; depth feedback",
                    "Clicker confirms correct compression depth (2.0–2.4″)",
                    "Compliant with the latest AHA Integrated Feedback directive"])
             + "\n" + specs(configs="single, 4-pack, or 4-pack diversity kit"),
    ),
    4064: dict(
        name="PRESTAN Professional Manikin Family Pack",
        slug="prestan-professional-manikin-family-pack",
        short="<p>Complete PRESTAN Professional manikin collection for adult, child and infant CPR training — all with the CPR Rate Monitor. Made in the USA.</p>"
              + ul(["Adult, child and infant manikins in one pack", "Visual chest rise and CPR Rate Monitor on every manikin",
                    "Light or dark skin tone", "Ideal for full-curriculum CPR courses"]),
        desc="<p>The PRESTAN Professional Manikin Family Pack brings together a full range of manikins for comprehensive CPR training across every age group, all with visual chest rise and the PRESTAN CPR Rate Monitor.</p>"
             + "<h3>What's Included</h3>\n" + ul(["2 × Professional Adult manikin", "1 × Professional Child manikin", "2 × Professional Infant manikin"])
             + "\n" + specs(configs="3-pack or 5-pack collection"),
    ),
    3963: dict(
        name="PRESTAN PRO+ Adult CPR Manikin",
        slug="prestan-pro-plus-adult-cpr-manikin",
        short="<p>Premium PRESTAN adult manikin with functional oral and nasal airways for advanced airway training, plus the CPR Rate Monitor. AHA-compliant, made in the USA.</p>"
              + ul(["Functional oral &amp; nasal airways (NARCAN®, OPA, NPA compatible)", "Enhanced facial realism",
                    "CPR Rate Monitor with visual &amp; audio feedback", "Improved lung-bag and face-shield design",
                    "Light or dark skin tone"]),
        desc="<p>The PRESTAN PRO+ Adult Manikin builds on PRESTAN's trusted Professional design with an enhanced head featuring functional oral and nasal airways — ideal for training with NARCAN®, OPA and NPA devices. It retains the visual chest rise and CPR Rate Monitor of the Professional line.</p>"
             + "<h3>Key Features</h3>\n" + ul(["Functional oral &amp; nasal airways (NARCAN®, OPA &amp; NPA compatible)",
                    "Enhanced facial realism with upgraded face shield covering both airways",
                    "CPR Rate Monitor — LED feedback (100–120 cpm) with depth clicker (2.0–2.4″)",
                    "Improved lung-bag design for fast installation",
                    "Upgradeable: PRO+ head available for existing Professional manikins"])
             + "\n" + specs(configs="single or 4-pack"),
    ),
    # ── Series 2000 ───────────────────────────────────────────────────────────
    3957: dict(
        name="PRESTAN Professional Adult Series 2000 CPR Manikin",
        slug="prestan-professional-adult-series-2000-cpr-manikin",
        short="<p>Advanced adult CPR manikin with Bluetooth feedback to the PRESTAN CPR Feedback App — rate, depth, recoil, ventilation and more. Made in the USA.</p>"
              + ul(["Bluetooth feedback to the PRESTAN CPR Feedback App (iOS &amp; Android)",
                    "Rate, depth, recoil, ventilation, hands-off time &amp; CCF", "Built-in LED CPR Rate Monitor",
                    "Tracks up to six students at once", "Light or dark skin tone — single or 4-pack"]),
        desc="<p>The PRESTAN Series 2000 is a high-quality, realistic CPR training manikin enhanced with advanced feedback. It connects via Bluetooth to the redesigned PRESTAN CPR Feedback App, combining the trusted LED Rate Monitor with detailed, app-based performance metrics.</p>"
             + "<h3>Key Features</h3>\n" + ul(S2000_FEATURES)
             + "\n" + specs(configs="single or 4-pack"),
    ),
    3999: dict(
        name="PRESTAN Professional Child Series 2000 CPR Manikin",
        slug="prestan-professional-child-series-2000-cpr-manikin",
        short="<p>Advanced paediatric CPR manikin with Bluetooth feedback to the PRESTAN CPR Feedback App. Childlike proportions, made in the USA.</p>"
              + ul(["Childlike proportions for paediatric training", "Bluetooth feedback to the PRESTAN CPR Feedback App",
                    "Rate, depth, recoil &amp; ventilation metrics", "Built-in LED CPR Rate Monitor", "Single or 4-pack"]),
        desc="<p>The PRESTAN Professional Child Series 2000 Manikin pairs realistic paediatric proportions with advanced Bluetooth feedback, connecting to the PRESTAN CPR Feedback App for detailed compression and ventilation metrics alongside the built-in LED Rate Monitor.</p>"
             + "<h3>Key Features</h3>\n" + ul(["Childlike proportions for realistic paediatric training"] + S2000_FEATURES[:-1])
             + "\n" + specs(configs="single or 4-pack"),
    ),
    4012: dict(
        name="PRESTAN Professional Infant Series 2000 CPR Manikin",
        slug="prestan-professional-infant-series-2000-cpr-manikin",
        short="<p>Advanced infant CPR manikin with Bluetooth feedback to the PRESTAN CPR Feedback App. Realistic weight, made in the USA.</p>"
              + ul(["Realistic infant weight and feel", "Bluetooth feedback to the PRESTAN CPR Feedback App",
                    "Rate, depth, recoil &amp; ventilation metrics", "Built-in LED CPR Rate Monitor", "Single or 4-pack"]),
        desc="<p>The PRESTAN Professional Infant Series 2000 Manikin combines life-like infant proportions and weight with advanced Bluetooth feedback to the PRESTAN CPR Feedback App, giving instructors detailed insight into every rescue alongside the built-in LED Rate Monitor.</p>"
             + "<h3>Key Features</h3>\n" + ul(["Life-like infant weight and head movement for airway training"] + S2000_FEATURES[:-1])
             + "\n" + specs(configs="single or 4-pack"),
    ),
    # ── Ultralite ─────────────────────────────────────────────────────────────
    4027: dict(
        name="PRESTAN Ultralite CPR Manikin",
        slug="prestan-ultralite-cpr-manikin",
        short="<p>PRESTAN's most portable CPR manikin — lightweight, stackable and quick to set up, with optional CPR feedback. Made in the USA.</p>"
              + ul(["Ultra-portable, lightweight and stackable", "Visual chest rise", "Optional CPR Feedback Piston with LED rate monitor",
                    "Carry-bag and wheeled roller-bag options", "Light or dark skin tone — single, 4-pack or 12-pack"]),
        desc="<p>The PRESTAN Ultralite Manikin is our most portable manikin: easy to transport and ship, and simple to set up, use and clean. It combines genuine portability with realistic training features and an affordable price, making it ideal for mobile and high-volume training.</p>"
             + "<h3>Key Features</h3>\n" + ul(["Lightweight, stackable design (4-pack ≈ 6 kg, 12-pack ≈ 18 kg)",
                    "Portable carry options including shoulder-strap and wheeled roller bag", "Visual chest rise for realistic feedback",
                    "Optional CPR Feedback Piston with LED rate monitor and audible depth cues",
                    "Compliant with current AHA guidelines"])
             + "\n" + specs(configs="single, 4-pack or 12-pack; diversity kits available", extra=["Feedback: with or without CPR Feedback Piston"]),
    ),
    # ── AED Trainers ──────────────────────────────────────────────────────────
    3762: dict(
        name="PRESTAN Professional AED Trainer PLUS",
        slug="prestan-professional-aed-trainer-plus",
        short="<p>Realistic AED trainer matching real AED size and weight, with voice prompts, a child mode and the patented PRESTAN Pad Sensing System.</p>"
              + ul(["Clear voice prompts with optional CPR guidance", "Child button for paediatric scenarios",
                    "Optional remote control for instructor use", "Patented Pad Sensing System with replaceable pads",
                    "Toggle 15:2 / 30:2 ratios", "Available per language and as single or 4-pack"]),
        desc="<p>The PRESTAN Professional AED Trainer PLUS is a realistic AED training device designed to closely match actual AED brand trainers in size and weight. An easy-to-replace module keeps it current with CPR guidelines, and the patented PRESTAN Pad Sensing System delivers an authentic training experience.</p>"
             + "<h3>Key Features</h3>\n" + ul(["Clear voice prompts with optional CPR pacing guidance",
                    "Customizable child button for paediatric training", "Optional remote control (one instructor controls multiple units)",
                    "Easy-to-replace module with current CPR guidelines", "Toggle between 15:2 and 30:2 compression–ventilation ratios",
                    "Compatible with the universal AED Trainer replacement pads"])
             + "\n" + specs(skin=None, configs="single or 4-pack", extra=["Choose your language version at order"]),
    ),
    3683: dict(
        name="PRESTAN AED Trainer PLUS — Remote Control",
        slug="prestan-aed-trainer-plus-remote-control",
        short="<p>Instructor remote control for the PRESTAN Professional AED Trainer PLUS — start, pause and switch scenarios across one or more units.</p>"
              + ul(["Controls one or multiple AED Trainer PLUS units", "Hands-free scenario control for instructors", "Available single or 4-pack"]),
        desc="<p>The remote control lets instructors operate one or more PRESTAN Professional AED Trainer PLUS units from a distance — starting, pausing and switching scenarios without interrupting the class.</p>"
             + "\n" + specs(skin=None, configs="single or 4-pack", extra=["For use with the PRESTAN Professional AED Trainer PLUS"]),
    ),
    3874: dict(
        name="PRESTAN AED UltraTrainer",
        slug="prestan-aed-ultratrainer",
        short="<p>Compact, lightweight AED trainer with full scenario customization and the patented PRESTAN Pad Sensing System. Made in the USA.</p>"
              + ul(["Compact and lightweight for classroom use", "Customizable training scenarios",
                    "Patented Pad Sensing System with improved sensor", "Easier pad replacement (bidirectional tabs)",
                    "Available per language and as single or 4-pack"]),
        desc="<p>The PRESTAN AED UltraTrainer is a compact, lightweight AED trainer with full customization for classroom training. Replaceable modules keep it aligned with current CPR guidelines in multiple languages, and the patented PRESTAN Pad Sensing System ensures reliable pad placement.</p>"
             + "<h3>Key Features</h3>\n" + ul(["Compact, lightweight design", "Customizable for various classroom scenarios",
                    "Patented PRESTAN Pad Sensing System with improved sensor technology",
                    "Easier pad replacement with bidirectional tab insertion", "Toggle between 15:2 and 30:2 ratios",
                    "Inclusive pad-placement imagery"])
             + "\n" + specs(skin=None, configs="single or 4-pack", extra=["Choose your language version at order"]),
    ),
    3685: dict(
        name="PRESTAN AED UltraTrainer Training Pads",
        slug="prestan-aed-ultratrainer-training-pads",
        short="<p>Replacement training pads for the PRESTAN AED UltraTrainer, compatible with the patented Pad Sensing System.</p>"
              + ul(["For the PRESTAN AED UltraTrainer", "Patented Pad Sensing System compatible", "Available single set or 4-pack"]),
        desc="<p>Replacement training pads for the PRESTAN AED UltraTrainer. Compatible with the patented PRESTAN Pad Sensing System for reliable pad-placement detection during training.</p>"
             + "\n" + specs(skin=None, configs="single set or 4-pack", extra=["For use with the PRESTAN AED UltraTrainer"]),
    ),
}

# ── Consumables: lung bags & face shields (50-pack) ─────────────────────────
CONSUMABLES = {
    6257: ("PRESTAN Adult Manikin Lung Bags & Face Shields", "prestan-adult-manikin-lung-bags-face-shields", "Professional Adult"),
    6260: ("PRESTAN Child Manikin Lung Bags & Face Shields", "prestan-child-manikin-lung-bags-face-shields", "Professional Child"),
    6263: ("PRESTAN Infant Manikin Lung Bags & Face Shields", "prestan-infant-manikin-lung-bags-face-shields", "Professional Infant"),
    3677: ("PRESTAN Ultralite Lung Bags & Face Shields", "prestan-ultralite-lung-bags-face-shields", "Ultralite"),
    3678: ("PRESTAN PRO+ Lung Bags & Face Shields", "prestan-pro-plus-lung-bags-face-shields", "PRO+"),
}
for wid, (nm, sl, line) in CONSUMABLES.items():
    PRODUCTS[wid] = dict(
        name=nm, slug=sl,
        short=f"<p>Disposable replacement lung bags and face shields for PRESTAN {line} manikins. Hygienic, single-use protection for every student. 50-pack.</p>"
              + ul(["For PRESTAN " + line + " manikins", "Choose face shields or lung bags", "Single-use, hygienic", "50-pack"]),
        desc=f"<p>Genuine PRESTAN disposable lung bags and face shields for the {line} manikin range. Replace after each student for hygienic, realistic CPR training. Supplied as a 50-pack; choose face shields or lung bags.</p>"
             + "\n" + specs(skin=None, configs="50-pack", warranty="Genuine PRESTAN consumable"),
    )

# ── Toepassen ───────────────────────────────────────────────────────────────
targets = {w: c for w, c in PRODUCTS.items() if not ONLY or w in ONLY}
print("MODE:", "APPLY" if APPLY else "DRY-RUN", "|", len(targets), "producten")
for wid, c in targets.items():
    cur = api(f"products/{wid}?_fields=id,name,slug")
    print(f"\n=== wc {wid} ===")
    print(f"  naam:  {cur.get('name')!r}\n      -> {c['name']!r}")
    print(f"  slug:  {cur.get('slug')!r}\n      -> {c['slug']!r}")
    print(f"  short: {len(c['short'])} tekens | desc: {len(c['desc'])} tekens")
    if APPLY:
        api(f"products/{wid}", "PUT", {
            "name": c["name"], "slug": c["slug"],
            "short_description": c["short"], "description": c["desc"],
        })
        print("  [APPLY] gezet")
if not APPLY:
    print("\nDRY-RUN — geen mutaties. Run met --apply.")
