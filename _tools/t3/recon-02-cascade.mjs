import { chromium } from 'file:///C:/laragon/www/ra-zil/wp-content/themes/razil/node_modules/playwright/index.mjs';
const browser = await chromium.launch({ channel: 'chrome' });
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
await page.goto('http://ra-zil.test/', { waitUntil: 'networkidle' });

// Inject the contract markup as the other agent will produce it, then read matched rules.
const out = await page.evaluate(() => {
  const warn = document.querySelector('.rz-first-aid__warning');
  const wrap = document.createElement('div');
  wrap.className = 'wp-block-group rz-callout rz-callout--warn is-layout-flow wp-block-group-is-layout-flow';
  warn.parentNode.insertBefore(wrap, warn);
  wrap.appendChild(warn);

  const collect = (el) => {
    const rules = [];
    for (const sheet of document.styleSheets) {
      let list;
      try { list = sheet.cssRules; } catch (e) { rules.push({ sheet: 'CORS-blocked: ' + sheet.href }); continue; }
      const walk = (rs, media) => {
        for (const r of rs) {
          if (r.type === CSSRule.MEDIA_RULE) { walk(r.cssRules, r.conditionText); continue; }
          if (r.type !== CSSRule.STYLE_RULE) continue;
          let m = false;
          try { m = el.matches(r.selectorText); } catch (e) { continue; }
          if (m) rules.push({
            media: media || '',
            selector: r.selectorText,
            css: r.style.cssText,
            href: sheet.href ? sheet.href.split('/').slice(-1)[0] : (sheet.ownerNode && sheet.ownerNode.id) || 'inline',
          });
        }
      };
      walk(list, '');
    }
    return rules;
  };

  const rulesWrap = collect(wrap);
  const rulesP = collect(warn);
  const csW = getComputedStyle(wrap), csP = getComputedStyle(warn);
  return {
    wrapRules: rulesWrap,
    pRules: rulesP.filter(r => /margin|padding|max-width/.test(r.css)),
    wrapComputed: { marginBlock: csW.marginTop + '/' + csW.marginBottom, padding: csW.padding, maxWidth: csW.maxWidth, width: csW.width },
    pComputed: { marginBlock: csP.marginTop + '/' + csP.marginBottom, maxWidth: csP.maxWidth, marginInline: csP.marginLeft + '/' + csP.marginRight },
  };
});
console.log(JSON.stringify(out, null, 2));
await browser.close();
