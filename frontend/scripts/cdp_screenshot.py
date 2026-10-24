import subprocess
import time
import json
import urllib.request
import asyncio
import websockets
import base64
import sys
import os

async def capture_page(url, output_path, full_page=True, delay=3.0):
    port = 9222
    user_data_dir = f"/tmp/chrome_profile_{os.getpid()}"
    chrome_proc = subprocess.Popen([
        "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome",
        "--headless",
        "--disable-gpu",
        "--blink-settings=lazyImageLoadingEnabled=false",
        f"--remote-debugging-port={port}",
        f"--user-data-dir={user_data_dir}",
        "--window-size=1440,900"
    ], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    
    time.sleep(1.2)
    try:
        # Get target pages
        put_req = urllib.request.Request(f"http://127.0.0.1:{port}/json/new?{urllib.parse.quote(url)}", method="PUT")
        req = urllib.request.urlopen(put_req)
        page_info = json.loads(req.read())
        page_ws = page_info["webSocketDebuggerUrl"]
        
        async with websockets.connect(page_ws, max_size=50*1024*1024) as ws:
            cmd_id_counter = 0
            pending_cmds = {}
            
            async def reader():
                try:
                    while True:
                        raw = await ws.recv()
                        msg = json.loads(raw)
                        msg_id = msg.get("id")
                        if msg_id and msg_id in pending_cmds:
                            pending_cmds[msg_id].set_result(msg)
                        method = msg.get("method")
                        if method == "Runtime.consoleAPICalled":
                            args = [str(a.get("value", a)) for a in msg.get("params", {}).get("args", [])]
                            print("[BROWSER CONSOLE]", *args)
                        elif method == "Runtime.exceptionThrown":
                            print("[BROWSER EXCEPTION]", msg.get("params", {}).get("exceptionDetails", {}).get("text"))
                except (asyncio.CancelledError, websockets.exceptions.ConnectionClosed):
                    pass
            
            reader_task = asyncio.create_task(reader())
            
            async def send_cmd(method, params=None):
                nonlocal cmd_id_counter
                cmd_id_counter += 1
                cmd_id = cmd_id_counter
                fut = asyncio.get_running_loop().create_future()
                pending_cmds[cmd_id] = fut
                msg = {"id": cmd_id, "method": method, "params": params or {}}
                await ws.send(json.dumps(msg))
                resp = await fut
                del pending_cmds[cmd_id]
                return resp.get("result", {})
            
            # Enable Page & Runtime & Console
            await send_cmd("Page.enable")
            await send_cmd("Runtime.enable")
            await send_cmd("Console.enable")
            
            # Wait a moment for initial load
            await asyncio.sleep(delay)
            
            if full_page:
                # Force all images to eager load and wait for decoding
                prepare_script = """
                (async () => {
                    // Turn all lazy images to eager
                    document.querySelectorAll('img').forEach(img => {
                        img.removeAttribute('loading');
                        img.loading = 'eager';
                    });
                    
                    // Scroll to bottom and back
                    await new Promise((resolve) => {
                        let totalHeight = 0;
                        let distance = 600;
                        let timer = setInterval(() => {
                            let scrollHeight = document.body.scrollHeight;
                            window.scrollBy(0, distance);
                            totalHeight += distance;
                            if (totalHeight >= scrollHeight) {
                                clearInterval(timer);
                                window.scrollTo(0, 0);
                                resolve();
                            }
                        }, 40);
                    });
                    
                    // Wait for all images to decode
                    await Promise.all(Array.from(document.images).map(async (img) => {
                        try {
                            if (!img.complete) {
                                await new Promise((res) => {
                                    img.onload = img.onerror = res;
                                    setTimeout(res, 2000);
                                });
                            }
                            await img.decode();
                        } catch (e) {}
                    }));
                })()
                """
                await send_cmd("Runtime.evaluate", {"expression": prepare_script, "awaitPromise": True})
                await asyncio.sleep(2.0)
            
            # If full page, get layout metrics to set emulation
            params = {"format": "png"}
            if full_page:
                metrics = await send_cmd("Page.getLayoutMetrics")
                content_size = metrics.get("cssContentSize", metrics.get("contentSize", {}))
                width = int(content_size.get("width", 1440))
                height = int(content_size.get("height", 900))
                
                # Clip to full content
                params["clip"] = {
                    "x": 0,
                    "y": 0,
                    "width": width,
                    "height": height,
                    "scale": 1
                }
                params["captureBeyondViewport"] = True
                
            res = await send_cmd("Page.captureScreenshot", params)
            img_data = base64.b64decode(res["data"])
            
            reader_task.cancel()
            with open(output_path, "wb") as f:
                f.write(img_data)
            print(f"Saved {len(img_data)} bytes to {output_path} ({width if full_page else 1440}x{height if full_page else 900})")

            
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
    full = len(sys.argv) <= 3 or sys.argv[3].lower() != "viewport"
    asyncio.run(capture_page(url, out, full_page=full))
