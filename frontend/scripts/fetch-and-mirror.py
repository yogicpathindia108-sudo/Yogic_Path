import os
import re
import urllib.request
import urllib.parse

BASE_URL = "https://yogicpathytt.com"
PAGES = {
    "": "home.html",
    "about-us/": "about-us.html",
    "teacher-training-programs/": "teacher-training-programs.html",
    "events/": "events.html",
    "contact-us/": "contact-us.html",
    "200-hour-yoga-teacher-training-rishikesh/": "200-hour-yoga-teacher-training-rishikesh.html",
    "200-hour-yoga-teacher-training-kerala/": "200-hour-yoga-teacher-training-kerala.html",
    "300-hour-yoga-teacher-training-rishikesh/": "300-hour-yoga-teacher-training-rishikesh.html",
    "300-hour-yoga-teacher-training-kerala/": "300-hour-yoga-teacher-training-kerala.html",
    "yogicpath-story/": "yogicpath-story.html",
    "yogic-path-course-registration/": "yogic-path-course-registration.html",
    "pricing-cancellation-policy/": "pricing-cancellation-policy.html",
    "terms-conditions/": "terms-conditions.html",
    "privacy-policy/": "privacy-policy.html",
    "200k/": "200k.html",
    "2026/08/23/why-kerala-for-yoga-teacher-traing/": "why-kerala-for-yoga-teacher-traing.html",
    "2026-08-23-why-kerala-for-yoga-teacher-traing/": "2026-08-23-why-kerala-for-yoga-teacher-traing.html",
}

ROOT_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), ".."))
CLONED_DIR = os.path.join(ROOT_DIR, "cloned-pages")
PUBLIC_DIR = os.path.join(ROOT_DIR, "public")

HEADERS = {
    "User-Agent": "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36"
}

LEAD_CAPTURE_JS = """
<script id="yp-backend-integration">
(function() {
  document.addEventListener('DOMContentLoaded', function() {
    // 1. Mobile burger menu toggle fallback
    var mobileBars = document.querySelectorAll('.mobile_menu_bar, .mobile_nav');
    mobileBars.forEach(function(bar) {
      bar.addEventListener('click', function() {
        var menu = document.querySelector('#mobile_menu, .et_mobile_menu');
        if (menu) {
          menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
        }
      });
    });

    // 2. FAQ Accordion toggle fallback
    document.querySelectorAll('.et_pb_toggle_title').forEach(function(title) {
      title.addEventListener('click', function() {
        var toggle = this.closest('.et_pb_toggle');
        if (!toggle) return;
        var parent = toggle.closest('.et_pb_accordion');
        var isOpen = toggle.classList.contains('et_pb_toggle_open');
        if (parent) {
          parent.querySelectorAll('.et_pb_toggle').forEach(function(item) {
            item.classList.remove('et_pb_toggle_open');
            item.classList.add('et_pb_toggle_close');
          });
        }
        if (!isOpen) {
          toggle.classList.remove('et_pb_toggle_close');
          toggle.classList.add('et_pb_toggle_open');
        } else {
          toggle.classList.remove('et_pb_toggle_open');
          toggle.classList.add('et_pb_toggle_close');
        }
      });
    });

    // 3. WPForms AJAX Interception (save lead to Python backend)
    document.querySelectorAll('.wpforms-form').forEach(function(f) {
      f.addEventListener('submit', function(e) {
        e.preventDefault();
        var inputs = {};
        f.querySelectorAll('input, select, textarea').forEach(function(inp) {
          if (inp.name) inputs[inp.name] = inp.value;
        });
        var email = inputs['wpforms[fields][1]'] || inputs['email'] || '';
        var name = inputs['wpforms[fields][0]'] || inputs['name'] || '';
        var msg = inputs['wpforms[fields][2]'] || inputs['message'] || '';
        var submitB = f.querySelector('button[type="submit"], input[type="submit"]');
        if (submitB) {
          submitB.disabled = true;
          submitB.innerText = 'Submitting...';
        }

        fetch('/api/leads/', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            name: name || 'Website Lead',
            email: email || 'lead@yogicpath.local',
            message: msg || JSON.stringify(inputs),
            source: window.location.pathname
          })
        }).then(function() {
          f.innerHTML = '<div class="wpforms-confirmation-container-full" style="padding:20px; background:#f4fbf5; border:1px solid #48c774; border-radius:8px; color:#236437; text-align:center;">Thank you! Your message has been received. Our team will contact you shortly.</div>';
        }).catch(function(err) {
          f.innerHTML = '<div class="wpforms-confirmation-container-full" style="padding:20px; background:#f4fbf5; border:1px solid #48c774; border-radius:8px; color:#236437; text-align:center;">Thank you! Your message has been received.</div>';
        });
      });
    });

    // 4. Registration Form Interception
    var regForm = document.getElementById('yogic-path-registration-form');
    if (regForm) {
      regForm.addEventListener('submit', function(e) {
        e.preventDefault();
        var course = (document.getElementById('yp-course') || {}).value || '';
        var location = (document.getElementById('yp-location') || {}).value || '';
        var name = (document.getElementById('yp-name') || {}).value || '';
        var email = (document.getElementById('yp-email') || {}).value || '';
        var phone = (document.getElementById('yp-phone') || {}).value || '';

        if (email) {
          fetch('/api/applications/', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              fullName: name || 'Applicant',
              email: email,
              phone: phone,
              courseSlug: course,
              locationSlug: location,
              paymentType: 'deposit',
              amount: 500
            })
          }).catch(function(err) { console.error(err); });
        }
      });
    }
  });
})();
</script>
"""

def download_asset_if_missing(url):
    clean_url = url.split("?")[0].split("#")[0]
    if clean_url.startswith("http://") or clean_url.startswith("https://"):
        parsed = urllib.parse.urlparse(clean_url)
        path = parsed.path
    elif clean_url.startswith("//"):
        path = "/" + clean_url.split("/", 3)[-1]
    else:
        path = clean_url

    if not path.startswith("/wp-content/") and not path.startswith("/wp-includes/"):
        return

    local_path = os.path.join(PUBLIC_DIR, path.lstrip("/"))
    if os.path.exists(local_path):
        return

    os.makedirs(os.path.dirname(local_path), exist_ok=True)
    full_remote_url = f"{BASE_URL}{path}"
    try:
        req = urllib.request.Request(full_remote_url, headers=HEADERS)
        with urllib.request.urlopen(req, timeout=15) as resp:
            data = resp.read()
            with open(local_path, "wb") as f:
                f.write(data)
            print(f"  Downloaded missing asset: {path} ({len(data)} bytes)")
    except Exception as e:
        print(f"  Warning: could not download {path}: {e}")

def mirror_pages():
    os.makedirs(CLONED_DIR, exist_ok=True)

    for slug, filename in PAGES.items():
        page_url = f"{BASE_URL}/{slug}"
        print(f"\nFetching {page_url} -> {filename}...")
        try:
            req = urllib.request.Request(page_url, headers=HEADERS)
            with urllib.request.urlopen(req, timeout=30) as resp:
                html = resp.read().decode("utf-8")
        except Exception as e:
            print(f"FAILED to fetch {page_url}: {e}")
            continue

        # 1. Convert deferred Theme Builder preload stylesheets to direct stylesheets
        html = re.sub(
            r'<link rel=[\'"]preload[\'"] as=[\'"]style[\'"] id=[\'"](et-core-unified-tb-[^\'"]+)[\'"] href=[\'"]([^\'"]+)[\'"][^>]*>',
            r'<link rel="stylesheet" id="\1" href="\2" />',
            html,
            flags=re.IGNORECASE
        )

        # 2. Find all assets (CSS, JS, images, fonts) and ensure they exist locally
        asset_matches = re.findall(r'(?:href|src|url)=[\'"]?([^\'"\s)]+)[\'"]?', html)
        for asset in asset_matches:
            if "wp-content" in asset or "wp-includes" in asset:
                download_asset_if_missing(asset)

        # 3. Replace remote absolute URLs for wp-content, wp-includes, and fonts with local paths
        html = re.sub(r'https?://yogicpathytt\.com/wp-content/', '/wp-content/', html)
        html = re.sub(r'https?://yogicpathytt\.com/wp-includes/', '/wp-includes/', html)
        html = re.sub(r'//yogicpathytt\.com/wp-content/', '/wp-content/', html)
        html = re.sub(r'//yogicpathytt\.com/wp-includes/', '/wp-includes/', html)
        html = re.sub(r'//yoga\.local/wp-content/', '/wp-content/', html)
        html = re.sub(r'//yoga\.local/wp-includes/', '/wp-includes/', html)
        html = re.sub(r'https?://yoga\.local/wp-content/', '/wp-content/', html)
        html = re.sub(r'https?://yoga\.local/wp-includes/', '/wp-includes/', html)

        # 4. Rewrite internal page links to stay on localhost
        for s_url, s_file in PAGES.items():
            remote_link = f"{BASE_URL}/{s_url}"
            local_target = f"/{s_url.rstrip('/')}" if s_url else "/"
            html = html.replace(remote_link, local_target)
            # also replace escaped URL in oEmbed
            escaped_remote = urllib.parse.quote(remote_link, safe="")
            html = html.replace(escaped_remote, urllib.parse.quote(local_target, safe=""))

        # 5. Inject lead capture / form handling script before </body>
        if "</body>" in html:
            html = html.replace("</body>", f"{LEAD_CAPTURE_JS}\n</body>")
        else:
            html = html + "\n" + LEAD_CAPTURE_JS

        dest = os.path.join(CLONED_DIR, filename)
        with open(dest, "w", encoding="utf-8") as f:
            f.write(html)
        print(f"✓ Saved {filename} ({len(html)} bytes)")

if __name__ == "__main__":
    mirror_pages()
    print("\nMirroring complete!")
