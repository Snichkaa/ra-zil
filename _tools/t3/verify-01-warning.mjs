import { chromium } from 'file:///C:/laragon/www/ra-zil/wp-content/themes/razil/node_modules/playwright/index.mjs';

const browser = await chromium.launch({ channel: 'chrome' });

for (const scheme of ['light', 'dark']) {
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 }, colorScheme: scheme });
  const page = await ctx.newPage();
  await page.goto('http://ra-zil.test/', { waitUntil: 'networkidle' });

  const res = await page.evaluate(() => {
    const el = document.querySelector('.rz-first-aid__warning');
    if (!el) return { error: 'not found' };
    const cs = getComputedStyle(el);
    const rv = (n) => getComputedStyle(document.documentElement).getPropertyValue(n).trim();

    // Какие правила реально задают color и border-top
    const winners = { color: [], borderTop: [], background: [] };
    for (const sheet of document.styleSheets) {
      let rules; try { rules = sheet.cssRules; } catch { continue; }
      const walk = (rs) => {
        for (const r of rs) {
          if (r.type === CSSRule.MEDIA_RULE) { walk(r.cssRules); continue; }
          if (r.type !== CSSRule.STYLE_RULE) continue;
          let m = false; try { m = el.matches(r.selectorText); } catch { continue; }
          if (!m) continue;
          const src = sheet.href ? sheet.href.split('/').pop().split('?')[0] : (sheet.ownerNode?.id || 'inline');
          if (r.style.color) winners.color.push(`${src} :: ${r.selectorText} { color: ${r.style.color} }`);
          if (/border/.test(r.style.cssText)) winners.borderTop.push(`${src} :: ${r.selectorText} { ${r.style.cssText.match(/border[^;]*;/g)?.join(' ')} }`);
          if (/background/.test(r.style.cssText)) winners.background.push(`${src} :: ${r.selectorText} { ${r.style.cssText.match(/background[^;]*;/g)?.join(' ')} }`);
        }
      };
      walk(rules);
    }

    return {
      backgroundColor: cs.backgroundColor,
      color: cs.color,
      border: cs.border,
      borderTop: cs.borderTop,
      borderTopColor: cs.borderTopColor,
      borderTopWidth: cs.borderTopWidth,
      borderRadius: cs.borderRadius,
      padding: cs.padding,
      marginTop: cs.marginTop,
      marginBottom: cs.marginBottom,
      overflow: cs.overflow,
      tokens: {
        '--rz-warn-bg': rv('--rz-warn-bg'),
        '--rz-warn-border': rv('--rz-warn-border'),
        '--rz-warn-text': rv('--rz-warn-text'),
        '--rz-ink': rv('--rz-ink'),
        '--rz-line': rv('--rz-line'),
        '--rz-radius-card': rv('--rz-radius-card'),
      },
      matchedRules: winners,
    };
  });

  console.log(`\n######## colorScheme = ${scheme} ########`);
  console.log(JSON.stringify(res, null, 2));

  const box = page.locator('.rz-first-aid__warning');
  await box.scrollIntoViewIfNeeded();
  await page.waitForTimeout(250);
  await box.screenshot({ path: `_tools/t3/shot-warning-${scheme}.png` });
  await page.locator('.rz-first-aid').screenshot({ path: `_tools/t3/shot-section-${scheme}.png` });
  await ctx.close();
}

await browser.close();
