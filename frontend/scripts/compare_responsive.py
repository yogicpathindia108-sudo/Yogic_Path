import subprocess
import time
import json
import urllib.request
import urllib.parse
import asyncio
import websockets
import base64
import sys
import os

DEVICES = {
    "desktop": {
        "width": 1440,
        "height": 900,
        "dpr": 1,
        "mobile": False,
        "ua": "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
    },
    "tablet": {
        "width": 768,
        "height": 1024,
        "dpr": 2,
        "mobile": True,
        "ua": "Mozilla/5.0 (iPad; CPU OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1"
    },
    "mobile": {
        "width": 390,
        "height": 844,
        "dpr": 3,
        "mobile": True,
        "ua": "Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1"
    }
}

async def capture_screenshot(url, output_path, device_mode="mobile", full_page=False, delay=2.5):
    dev = DEVICES[device_mode]
    port = 9222 + (os.getpid() % 1000)
    user_data_dir = f"/tmp/chrome_profile_resp_{os.getpid()}"
    
    chrome_proc = subprocess.Popen([
        "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome",
        "--headless",
        "--disable-gpu",
        f"--remote-debugging-port={port}",
        f"--user-data-dir={user_data_dir}",
        f"--window-size={dev['width']},{dev['height']}"
    ], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    
    # Wait for Chrome CDP port to open
    for _ in range(20):
        try:
            req = urllib.request.urlopen(f"http://127.0.0.1:{port}/json/version")
            break
        except Exception:
            time.sleep(0.3)
            
    try:
        put_req = urllib.request.Request(f"http://127.0.0.1:{port}/json/new?{urllib.parse.quote(url)}", method="PUT")
        req = urllib.request.urlopen(put_req)
        page_info = json.loads(req.read())
        page_ws = page_info["webSocketDebuggerUrl"]
        
        async with websockets.connect(page_ws, max_size=50*1024*1024) as ws:
            cmd_id = 0
            pending = {}
            
            async def reader():
                try:
                    while True:
                        raw = await ws.recv()
                        msg = json.loads(raw)
                        mid = msg.get("id")
                        if mid and mid in pending:
                            pending[mid].set_result(msg)
                except Exception:
                    pass
            
            reader_task = asyncio.create_task(reader())
            
            async def send_cmd(method, params=None):
                nonlocal cmd_id
                cmd_id += 1
                curr_id = cmd_id
                fut = asyncio.get_running_loop().create_future()
                pending[curr_id] = fut
                await ws.send(json.dumps({"id": curr_id, "method": method, "params": params or {}}))
                resp = await fut
                del pending[curr_id]
                return resp.get("result", {})
            
            await send_cmd("Page.enable")
            await send_cmd("Runtime.enable")
            
            # Set Emulation
            await send_cmd("Emulation.setUserAgentOverride", {"userAgent": dev["ua"]})
            await send_cmd("Emulation.setDeviceMetricsOverride", {
                "width": dev["width"],
                "height": dev["height"],
                "deviceScaleFactor": dev["dpr"],
                "mobile": dev["mobile"]
            })
            
            # Navigate to URL
            await send_cmd("Page.navigate", {"url": url})
            await asyncio.sleep(delay)
            
            # Check console errors and missing resources
            eval_res = await send_cmd("Runtime.evaluate", {
                "expression": """(() => {
                    const brokenImages = Array.from(document.images)
                        .filter(img => !img.complete || img.naturalWidth === 0)
                        .map(img => img.src || img.getAttribute('src'));
                    return {
                        title: document.title,
                        brokenImages: brokenImages.slice(0, 20),
                        totalImages: document.images.length,
                        bodyWidth: document.body.scrollWidth,
                        bodyHeight: document.body.scrollHeight,
                        windowInnerWidth: window.innerWidth
                    };
                })()""",
                "returnByValue": True
            })
            page_stats = eval_res.get("result", {}).get("value", {})
            
            if full_page:
                metrics = await send_cmd("Page.getLayoutMetrics")
                content_size = metrics.get("cssContentSize", metrics.get("contentSize", {}))
                h = int(content_size.get("height", dev["height"]))
                params = {
                    "format": "png",
                    "clip": {
                        "x": 0,
                        "y": 0,
                        "width": dev["width"],
                        "height": min(h, 8000),
                        "scale": 1
                    },
                    "captureBeyondViewport": True
                }
            else:
                params = {"format": "png"}
                
            res = await send_cmd("Page.captureScreenshot", params)
            img_data = base64.b64decode(res["data"])
            
            reader_task.cancel()
            with open(output_path, "wb") as f:
                f.write(img_data)
                
            return page_stats
    finally:
        chrome_proc.terminate()
        try:
            chrome_proc.wait(timeout=2)
        except Exception:
            chrome_proc.kill()
        subprocess.run(["rm", "-rf", user_data_dir], stderr=subprocess.DEVNULL)

if __name__ == "__main__":
    url = sys.argv[1]
    out = sys.argv[2]
    mode = sys.argv[3] if len(sys.argv) > 3 else "mobile"
    full = len(sys.argv) > 4 and sys.argv[4].lower() == "full"
    stats = asyncio.run(capture_screenshot(url, out, device_mode=mode, full_page=full))
    print(f"Stats for {mode} ({url}):", json.dumps(stats, indent=2))
