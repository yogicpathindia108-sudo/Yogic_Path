const fs = require('fs');
const path = require('path');

const CLONED_DIR = path.join(__dirname, '..', 'cloned-pages');

const HERO_PAGES = [
  'home.html',
  '200-hour-yoga-teacher-training-kerala.html',
  '200-hour-yoga-teacher-training-rishikesh.html',
  '300-hour-yoga-teacher-training-kerala.html',
  '300-hour-yoga-teacher-training-rishikesh.html'
];

const INJECTED_CSS = `
<style id="yp-custom-header-fixes">
/* =========================================================
   TALK TO US CTA BUTTON — SIGNATURE GOLD PILL
   ========================================================= */
.menu .et-menu > li.de-menu-cta {
    margin-left: 14px !important;
    display: inline-flex !important;
    align-items: center !important;
}

.menu .et-menu > li.de-menu-cta > a {
    background-color: #9a783c !important;
    color: #ffffff !important;
    padding: 9px 20px !important;
    border-radius: 20px !important;
    font-family: 'Cabin', sans-serif !important;
    font-weight: 600 !important;
    font-size: 14px !important;
    text-transform: none !important;
    letter-spacing: 0.5px !important;
    line-height: 1 !important;
    display: inline-block !important;
    box-shadow: 0 2px 8px rgba(154, 120, 60, 0.3) !important;
    transition: all 0.3s ease !important;
    text-decoration: none !important;
}

.menu .et-menu > li.de-menu-cta > a:hover {
    background-color: #423428 !important;
    color: #ffffff !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 4px 12px rgba(66, 52, 40, 0.4) !important;
}

.custom-header.et_pb_sticky .menu .et-menu > li.de-menu-cta > a,
.custom-header.yp-scrolled .menu .et-menu > li.de-menu-cta > a {
    background-color: #9a783c !important;
    color: #ffffff !important;
}

.custom-header.et_pb_sticky .menu .et-menu > li.de-menu-cta > a:hover,
.custom-header.yp-scrolled .menu .et-menu > li.de-menu-cta > a:hover {
    background-color: #423428 !important;
    color: #ffffff !important;
}

.menu .et-menu > li.de-menu-cta > a::before,
.menu .et-menu > li.de-menu-cta > a::after {
    display: none !important;
}

/* =========================================================
   NAVBAR CONSISTENCY ACROSS ALL PAGES
   ========================================================= */
.custom-header,
.et_pb_section_0_tb_header {
    transition: background-color 0.3s ease, box-shadow 0.3s ease !important;
}

/* Solid dark olive header on all inner pages */
body:not(.home):not(.yp-hero-page) .custom-header,
body:not(.home):not(.yp-hero-page) .et_pb_section_0_tb_header {
    background-color: #373a27 !important;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.25) !important;
}

/* Solid header when scrolled or sticky on ANY page */
.custom-header.et_pb_sticky,
.custom-header.yp-scrolled {
    background-color: #373a27 !important;
    box-shadow: 0 2px 14px rgba(0, 0, 0, 0.3) !important;
}

/* About Us page fix: prevent photo mosaic from being pulled under navbar */
body.page-id-987527147 .et_pb_section_0.et_pb_section {
    margin-top: 0px !important;
}

/* Active navigation link indicator (crisp gold underline + gold font) */
.menu .et-menu > li.current-menu-item:not(.de-menu-cta) > a,
.menu .et-menu > li.current-menu-ancestor:not(.de-menu-cta) > a {
    color: #b08643 !important;
}

.menu .et-menu > li.current-menu-item:not(.de-menu-cta) > a::before,
.menu .et-menu > li.current-menu-ancestor:not(.de-menu-cta) > a::before {
    content: "" !important;
    position: absolute !important;
    left: 12px !important;
    right: 12px !important;
    bottom: -2px !important;
    height: 2px !important;
    background: #b08643 !important;
    transform: scaleX(1) !important;
    transform-origin: left !important;
}

/* =========================================================
   FIX WHATSAPP DUPLICATION & OVERLAP BUG
   ========================================================= */
/* Hide duplicate Divi theme builder footer WhatsApp buttons */
.et_pb_image_1_tb_footer,
.et_pb_image_3_tb_footer {
    display: none !important;
}

/* Single dedicated, perfectly-styled floating WhatsApp button */
#yp-floating-whatsapp {
    position: fixed !important;
    bottom: 24px !important;
    right: 24px !important;
    z-index: 999999 !important;
    width: 60px !important;
    height: 60px !important;
    background-color: #25d366 !important;
    border-radius: 50% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    box-shadow: 0 4px 18px rgba(0, 0, 0, 0.3) !important;
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease !important;
    cursor: pointer !important;
    text-decoration: none !important;
}

#yp-floating-whatsapp:hover {
    transform: scale(1.1) !important;
    box-shadow: 0 8px 25px rgba(37, 211, 102, 0.5) !important;
}

#yp-floating-whatsapp img {
    width: 35px !important;
    height: 35px !important;
    display: block !important;
    object-fit: contain !important;
}

/* =========================================================
   TEACHER TRAINING DROPDOWN MENU — INTERACTIVE HOVER & SLIDE
   ========================================================= */
.et-menu li.mega-menu,
.et-menu li.menu-item-has-children {
    position: relative !important;
}

.et-menu li.menu-item-has-children > a {
    position: relative !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
}

.et-menu li.menu-item-has-children > a::after {
    content: "▾" !important;
    font-size: 13px !important;
    color: inherit !important;
    transition: transform 0.25s ease !important;
    display: inline-block !important;
}

.et-menu li.menu-item-has-children:hover > a::after {
    transform: rotate(180deg) !important;
}

/* Dropdown Sub-menu Container */
.et-menu li.menu-item-has-children > ul.sub-menu {
    display: block !important;
    visibility: hidden !important;
    opacity: 0 !important;
    position: absolute !important;
    top: calc(100% + 8px) !important;
    left: 0 !important;
    min-width: 320px !important;
    background-color: #373a27 !important;
    border-radius: 12px !important;
    padding: 10px 0 !important;
    box-shadow: 0 12px 35px rgba(0, 0, 0, 0.35) !important;
    border: 1px solid rgba(176, 134, 67, 0.25) !important;
    transition: opacity 0.25s ease, transform 0.25s ease, visibility 0.25s !important;
    transform: translateY(12px) !important;
    pointer-events: none !important;
    z-index: 99999 !important;
    list-style: none !important;
    margin: 0 !important;
}

/* Invisible bridge so mouse hover doesn't collapse */
.et-menu li.menu-item-has-children > ul.sub-menu::before {
    content: "" !important;
    position: absolute !important;
    top: -12px !important;
    left: 0 !important;
    right: 0 !important;
    height: 12px !important;
    background: transparent !important;
}

.et-menu li.menu-item-has-children:hover > ul.sub-menu,
.et-menu li.menu-item-has-children:focus-within > ul.sub-menu,
.et-menu li.menu-item-has-children.et-hover > ul.sub-menu {
    visibility: visible !important;
    opacity: 1 !important;
    transform: translateY(0) !important;
    pointer-events: auto !important;
}

.et-menu li.menu-item-has-children > ul.sub-menu li {
    display: block !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
    text-align: left !important;
}

.et-menu li.menu-item-has-children > ul.sub-menu li a {
    display: block !important;
    padding: 12px 22px !important;
    color: #ffffff !important;
    font-family: 'Cabin', sans-serif !important;
    font-size: 14px !important;
    font-weight: 500 !important;
    text-transform: none !important;
    line-height: 1.35 !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.07) !important;
    transition: background-color 0.2s ease, color 0.2s ease, padding-left 0.2s ease !important;
    text-decoration: none !important;
}

.et-menu li.menu-item-has-children > ul.sub-menu li:last-child a {
    border-bottom: none !important;
}

.et-menu li.menu-item-has-children > ul.sub-menu li a:hover {
    background-color: rgba(176, 134, 67, 0.25) !important;
    color: #b08643 !important;
    padding-left: 26px !important;
}

/* =========================================================
   BLOG POST & ARTICLE REPLICATION STYLES
   ========================================================= */
#main-content {
    background: #ffffff !important;
}

#main-content .container {
    width: 90% !important;
    max-width: 920px !important;
    margin: 0 auto !important;
    padding: 50px 0 80px 0 !important;
}

#left-area {
    width: 100% !important;
    float: none !important;
}

.et_pb_post .entry-title {
    font-family: 'Adamina', Georgia, serif !important;
    font-size: 40px !important;
    font-weight: 400 !important;
    color: #423428 !important;
    line-height: 1.25 !important;
    margin-bottom: 16px !important;
}

.et_pb_post .post-meta {
    font-family: 'Cabin', sans-serif !important;
    font-size: 14px !important;
    color: #777777 !important;
    margin-bottom: 28px !important;
    padding-bottom: 16px !important;
    border-bottom: 1px solid #ede8e1 !important;
}

.et_pb_post .post-meta a {
    color: #b08643 !important;
    text-decoration: none !important;
    font-weight: 500 !important;
}

.et_pb_post .post-meta a:hover {
    color: #423428 !important;
    text-decoration: underline !important;
}

.et_post_meta_wrapper img {
    width: 100% !important;
    max-width: 100% !important;
    height: auto !important;
    border-radius: 12px !important;
    margin: 20px 0 36px 0 !important;
    box-shadow: 0 8px 25px rgba(0,0,0,0.08) !important;
}

.entry-content {
    font-family: 'Cabin', sans-serif !important;
    font-size: 17px !important;
    line-height: 1.85 !important;
    color: #373a27 !important;
}

.entry-content p {
    margin-bottom: 24px !important;
}

.entry-content h2 {
    font-family: 'Adamina', Georgia, serif !important;
    font-size: 28px !important;
    color: #423428 !important;
    line-height: 1.3 !important;
    margin-top: 44px !important;
    margin-bottom: 16px !important;
    border-left: 4px solid #b08643 !important;
    padding-left: 14px !important;
}

.entry-content h3 {
    font-family: 'Adamina', Georgia, serif !important;
    font-size: 22px !important;
    color: #423428 !important;
    margin-top: 32px !important;
    margin-bottom: 12px !important;
}

.entry-content ul, .entry-content ol {
    margin: 18px 0 28px 24px !important;
    padding-left: 12px !important;
}

.entry-content li {
    margin-bottom: 10px !important;
    line-height: 1.7 !important;
}

.entry-content blockquote {
    border-left: 3px solid #b08643 !important;
    padding: 16px 24px !important;
    margin: 30px 0 !important;
    background: #fdfbf7 !important;
    border-radius: 0 8px 8px 0 !important;
    font-style: italic !important;
    color: #423428 !important;
}

/* Comment form styling */
#comment-wrap {
    margin-top: 60px !important;
    padding-top: 40px !important;
    border-top: 2px solid #ede8e1 !important;
}

#comment-wrap h1, #comment-wrap h2, #comment-wrap h3 {
    font-family: 'Adamina', Georgia, serif !important;
    color: #423428 !important;
}

#commentform input[type="text"],
#commentform input[type="email"],
#commentform textarea {
    width: 100% !important;
    padding: 12px 16px !important;
    border: 1px solid #d8cdba !important;
    border-radius: 8px !important;
    font-family: 'Cabin', sans-serif !important;
    margin-bottom: 16px !important;
    box-sizing: border-box !important;
}

#commentform input[type="submit"] {
    background-color: #423428 !important;
    color: #ffffff !important;
    border: none !important;
    padding: 12px 28px !important;
    border-radius: 8px !important;
    font-family: 'Cabin', sans-serif !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    transition: background-color 0.3s ease !important;
}

#commentform input[type="submit"]:hover {
    background-color: #b08643 !important;
}
</style>
`;

const SMART_HEADER_JS = `
<script id="yp-smart-header-script">
(function () {
    function updateHeader() {
        var headers = document.querySelectorAll('.custom-header');
        if (!headers.length) return;

        // Only pages with a dark full-bleed hero banner (like Home) start transparent at top
        var isHeroPage = document.body.classList.contains('home') || document.body.classList.contains('yp-hero-page');

        if (!isHeroPage) {
            headers.forEach(function (header) {
                header.style.setProperty('background-color', '#373a27', 'important');
                header.classList.add('yp-scrolled');
            });
            return;
        }

        var threshold = window.innerHeight * 0.05;
        var scrolled = window.scrollY >= threshold;

        headers.forEach(function (header) {
            if (scrolled) {
                header.style.setProperty('background-color', '#373a27', 'important');
                header.classList.add('yp-scrolled');
            } else {
                header.style.setProperty('background-color', 'transparent', 'important');
                header.classList.remove('yp-scrolled');
            }
        });
    }

    function init() {
        updateHeader();
        window.addEventListener('scroll', updateHeader, { passive: true });
        window.addEventListener('resize', updateHeader, { passive: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
`;

function applyFixes() {
  const files = fs.readdirSync(CLONED_DIR).filter(f => f.endsWith('.html'));
  console.log(`Processing ${files.length} HTML files in ${CLONED_DIR}...`);

  for (const f of files) {
    const filePath = path.join(CLONED_DIR, f);
    let html = fs.readFileSync(filePath, 'utf8');

    // Tag dark hero course pages with yp-hero-page class if applicable
    if (HERO_PAGES.includes(f) && f !== 'home.html') {
      if (!html.includes('yp-hero-page')) {
        html = html.replace(/(<body[^>]*class="[^"]*)/i, '$1 yp-hero-page');
      }
    }

    // Remove any previous injected fix blocks
    html = html.replace(/<style id="yp-custom-header-fixes">[\s\S]*?<\/style>/g, '');
    html = html.replace(/<script id="yp-smart-header-script">[\s\S]*?<\/script>/g, '');

    // Replace the old inline updateHeader implementation with our smart header implementation
    const oldHeaderRegex = /<script>\s*\(function\s*\(\)\s*\{\s*function updateHeader\(\)[\s\S]*?init\(\);\s*\}\s*\}\)\(\);\s*<\/script>/g;
    html = html.replace(oldHeaderRegex, '');

    // Convert deferred theme builder stylesheet to direct stylesheet link so header/footer styles always load
    html = html.replace(
      /<link rel=['"]preload['"] as=['"]style['"] id=['"]et-core-unified-tb-[^'"]+['"] href=['"]([^'"]+)['"][^>]*>/gi,
      '<link rel="stylesheet" href="$1" />'
    );

    // Inject our custom header & dropdown & WhatsApp fixes into <head>
    if (html.includes('</head>')) {
      html = html.replace('</head>', `${INJECTED_CSS}\n${SMART_HEADER_JS}\n</head>`);
    } else {
      html = INJECTED_CSS + '\n' + SMART_HEADER_JS + '\n' + html;
    }

    fs.writeFileSync(filePath, html, 'utf8');
    console.log(`✓ Updated ${f}`);
  }
}

applyFixes();

