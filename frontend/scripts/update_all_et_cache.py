import os
import glob
import re
import urllib.request

LIVE_BASE = "https://yogicpathytt.com"
PUBLIC_DIR = "frontend/public"
CLONED_DIR = "frontend/cloned-pages"

def sync_et_cache():
    pages = glob.glob(os.path.join(CLONED_DIR, "*.html"))
    cache_files = set()
    for p in pages:
        with open(p, "r", encoding="utf-8") as f:
            content = f.read()
        found = re.findall(r'/wp-content/et-cache/([a-zA-Z0-9_\-\./]+\.css)', content)
        for cf in found:
            clean_cf = cf.split("?")[0]
            cache_files.add(clean_cf)

    print(f"Total et-cache files referenced: {len(cache_files)}")
    
    updated = 0
    failed = 0
    for cf in sorted(cache_files):
        rel_url = f"/wp-content/et-cache/{cf}"
        live_url = f"{LIVE_BASE}{rel_url}"
        local_path = os.path.join(PUBLIC_DIR, "wp-content/et-cache", cf)
        os.makedirs(os.path.dirname(local_path), exist_ok=True)
        
        try:
            req = urllib.request.Request(
                live_url,
                headers={"User-Agent": "Mozilla/5.0"}
            )
            with urllib.request.urlopen(req, timeout=10) as resp:
                data = resp.read().decode("utf-8", errors="ignore")
                
            # Clean URLs in downloaded CSS
            data = data.replace("//yoga.local/", "/")
            data = data.replace("https://yoga.local/", "/")
            data = data.replace("http://yoga.local/", "/")
            data = data.replace("https://yogicpathytt.com/", "/")
            data = data.replace("https:/wp-content", "/wp-content")
            data = data.replace("http:/wp-content", "/wp-content")
            
            with open(local_path, "w", encoding="utf-8") as out:
                out.write(data)
                
            updated += 1
            print(f"Updated et-cache: {cf}")
        except Exception as e:
            failed += 1
            print(f"Notice: {cf} could not be fetched from live ({e}), keeping local if present.")

    print(f"\nFinished sync: {updated} updated, {failed} skipped/failed.")

if __name__ == "__main__":
    sync_et_cache()
