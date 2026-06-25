#!/usr/bin/env python3
"""
One-off: zet de hoofdafbeelding op Prestan-accessoire/consumable-producten die
geen echte afbeelding hadden (placeholder of leeg). De images komen van
prestan.com (per product van z'n detail-pagina opgehaald). WooCommerce downloadt
de URL naar de media-library en zet 'm als featured image.

Raakt alleen `images` aan. Dry-run default; --apply om te schrijven.

GEBRUIK:
  python3 migration/arky-prestan-images.py            # dry-run
  python3 migration/arky-prestan-images.py --apply
"""
import sqlite3, json, urllib.request, urllib.error, base64, ssl, sys, os

APPLY = "--apply" in sys.argv
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
    return json.load(urllib.request.urlopen(r, timeout=120, context=ctx))


PP = "https://www.prestan.com/images/directory/products"
# wc-id -> (image-URL, alt-tekst). Per product van z'n prestan.com-detailpagina gehaald.
IMAGES = {
    6257: (f"{PP}/FaceShields_LungBags/PP-ALB-10_lrg.jpg", "PRESTAN Adult Manikin Face Shields & Lung Bags"),
    6260: (f"{PP}/Prof_Child/Images/PP-CLB-10_Child_Lung_Bags_10_Pack_lrg.jpg", "PRESTAN Child Manikin Face Shields & Lung Bags"),
    6263: (f"{PP}/Main_Product_Images/PP-IFS-50_lrg.jpg", "PRESTAN Infant Manikin Face Shields & Lung Bags"),
    3677: (f"{PP}/Main_Product_Images/PP-ULB-50_Ultralite_Manikins_Lung_Bags_lrg.jpg", "PRESTAN Ultralite Manikin Face Shields & Lung Bags"),
    3672: (f"{PP}/Series_2000_LungBags/Series2000_LungBag2_lrg.JPG", "PRESTAN Series 2000 Ventilation Lung Bags"),
    3685: (f"{PP}/PP-UTPAD-1-UltraTrainer-Pads-Set_lrg.png", "PRESTAN AED UltraTrainer Training Pads"),
    3701: (f"{PP}/Main_Product_Images/PP-ACASE2-1_lrg.jpg", "PRESTAN Training Pads Storage Case"),
}

print("MODE:", "APPLY" if APPLY else "DRY-RUN", "|", len(IMAGES), "producten")
for wid, (url, alt) in IMAGES.items():
    cur = api(f"products/{wid}?_fields=id,name,images")
    huidig = [i.get("src", "").split("/")[-1] for i in cur.get("images", [])]
    print(f"\n  wc {wid}  {cur.get('name')[:44]}")
    print(f"    nu:  {huidig or '(geen)'}")
    print(f"    ->   {url.split('/')[-1]}")
    if APPLY:
        try:
            api(f"products/{wid}", "PUT", {"images": [{"src": url, "alt": alt}]})
            print("    [APPLY] gezet (WC downloadt de afbeelding)")
        except urllib.error.HTTPError as e:
            print(f"    FAIL {e.code}: {e.read().decode()[:200]}")
        except Exception as e:
            print(f"    FAIL: {str(e)[:200]}")
if not APPLY:
    print("\nDRY-RUN — geen mutaties. Run met --apply.")
