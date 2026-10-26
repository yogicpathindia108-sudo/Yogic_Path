import asyncio
import json
import subprocess
import time
import urllib.request
import websockets

PAGES = [
    "/",
    "/about-us/",
    "/yogicpath-story/",
    "/teacher-training-programs/",
    "/200-hour-yoga-teacher-training-rishikesh/",
    "/200-hour-yoga-teacher-training-kerala/",
    "/300-hour-yoga-teacher-training-rishikesh/",
    "/300-hour-yoga-teacher-training-kerala/",
    "/events/",
    "/contact-us/",
    "/yogic-path-course-registration/",
    "/pricing-cancellation-policy/",
    "/terms-conditions/",
    "/privacy-policy/",
    "/200k/",
    "/why-kerala-for-yoga-teacher-traing/"
]

async def check_all_pages():
    port = 9238
    user_data = "/tmp/chrome_test_overflow"
    proc = subprocess.Popen([
        "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome",
        "--headless",
        f"--remote-debugging-port={port}",
        f"--user-data-dir={user_data}",
        "--window-size=390,844"
    ], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    
    for _ in range(20):
        try:
            urllib.request.urlopen(f"http://127.0.0.1:{port}/json/version")
            break
        except Exception:
            time.sleep(0.3)
            
    try:
        req = urllib.request.Request(f"http://127.0.0.1:{port}/json/new?http://localhost:8080/", method="PUT")
        info = json.loads(urllib.request.urlopen(req).read())
        ws_url = info["webSocketDebuggerUrl"]
        
        async with websockets.connect(ws_url, max_size=50*1024*1024) as ws:
            mid = 0
            async def send(method, params=None):
                nonlocal mid
                mid += 1
                curr = mid
                await ws.send(json.dumps({"id": curr, "method": method, "params": params or {}}))
                while True:
                    r = json.loads(await ws.recv())
                    if r.get("id") == curr:
                        return r.get("result", {})
                        
            await send("Page.enable")
            await send("Runtime.enable")
            
            for mode, w, h in [("mobile", 390, 844), ("tablet", 768, 1024)]:
                await send("Emulation.setDeviceMetricsOverride", {"width": w, "height": h, "deviceScaleFactor": 2, "mobile": True})
                print(f"\n--- Checking {mode.upper()} ({w}px) ---")
                
                for path in PAGES:
                    url = f"http://localhost:8080{path}"
                    await send("Page.navigate", {"url": url})
                    await asyncio.sleep(1.5)
                    
                    js = """(() => {
                        const scrollW = document.documentElement.scrollWidth;
                        const innerW = window.innerWidth;
                        const overflows = [];
                        if (scrollW > innerW + 1) {
                            document.querySelectorAll('*').forEach(el => {
                                const r = el.getBoundingClientRect();
                                if (r.right > innerW + 2) {
                                    overflows.push({
                                        tag: el.tagName,
                                        cls: typeof el.className === 'string' ? el.className.slice(0, 80) : '',
                                        right: Math.round(r.right)
                                    });
                                }
                            });
                        }
                        return {
                            scrollW: scrollW,
                            innerW: innerW,
                            hasOverflow: scrollW > innerW + 1,
                            overflowCount: overflows.length,
                            firstOverflow: overflows[0] || null
                        };
                    })()"""
                    res = await send("Runtime.evaluate", {"expression": js, "returnByValue": True})
                    val = res.get("result", {}).get("value", {})
                    status = "OVERFLOW!" if val.get("hasOverflow") else "OK"
                    print(f"[{status:9}] {path:45} scrollW: {val.get('scrollW')} (window: {val.get('innerW')})")
                    if val.get("hasOverflow"):
                        print(f"          Culprit: {val.get('firstOverflow')}")
    finally:
        proc.terminate()
        proc.wait()

if __name__ == "__main__":
    asyncio.run(check_all_pages())
