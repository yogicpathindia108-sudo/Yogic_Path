import os
import re
import urllib.request
import urllib.parse

PAGES_DIR = "frontend/cloned-pages"
PUBLIC_DIR = "frontend/public"
LIVE_BASE = "https://yogicpathytt.com"

def extract_all_images():
    images = set()
    for fname in os.listdir(PAGES_DIR):
        if not fname.endswith(".html"):
            continue
        fpath = os.path.join(PAGES_DIR, fname)
        with open(fpath, "r", encoding="utf-8") as f:
            content = f.read()
            
        # Match all /wp-content/uploads/ or https://yogicpathytt.com/wp-content/uploads/
        matches = re.findall(r'(?:https://yogicpathytt\.com)?(/wp-content/uploads/[a-zA-Z0-9_\-\./%]+\.(?:jpg|jpeg|png|webp|svg|gif))', content)
        for m in matches:
            images.add(m.split("?")[0])

    print(f"Total unique images found across all HTML files: {len(images)}")
    
    missing = []
    for rel_path in sorted(images):
        local_path = os.path.join(PUBLIC_DIR, rel_path.lstrip("/"))
        if not os.path.exists(local_path):
            missing.append(rel_path)
            
    print(f"Total missing images: {len(missing)}")
    
    downloaded = 0
    failed = 0
    for rel_path in missing:
        local_path = os.path.join(PUBLIC_DIR, rel_path.lstrip("/"))
        live_url = f"{LIVE_BASE}{rel_path}"
        os.makedirs(os.path.dirname(local_path), exist_ok=True)
        
        try:
            req = urllib.request.Request(
                live_url,
                headers={"User-Agent": "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36"}
            )
            with urllib.request.urlopen(req, timeout=10) as resp, open(local_path, "wb") as out:
                out.write(resp.read())
            downloaded += 1
            if downloaded % 10 == 0 or downloaded == len(missing):
                print(f"Downloaded {downloaded}/{len(missing)}: {rel_path}")
        except Exception as e:
            failed += 1
            print(f"Failed to download {live_url}: {e}")
            
    print(f"\nDone! Downloaded: {downloaded}, Failed: {failed}")

if __name__ == "__main__":
    extract_all_images()
