import { chromium } from 'file:///C:/laragon/www/ra-zil/wp-content/themes/razil/node_modules/playwright/index.mjs';

const URL = 'http://ra-zil.test/';
const browser = await chromium.launch({ channel: 'chrome' });
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
await page.goto(URL, { waitUntil: 'networkidle' });

const data = await page.evaluate(() => {
  const out = [];
  const root = document.querySelector('.rz-first-aid');
  if (!root) return [{ error: 'no .rz-first-aid' }];
  for (const a of root.querySelectorAll('a[href^="tel:"]')) {
    const cs = getComputedStyle(a);
    const r = a.getBoundingClientRect();
    const after = getComputedStyle(a, '::after');
    // measure the ::after box via a temporary clone approach: use its computed inset
    out.push({
      text: a.textContent.trim(),
      href: a.getAttribute('href'),
      parentClass: a.parentElement.className || a.parentElement.tagName,
      closestP: (a.closest('p,li') || {}).className || '',
      rect: { w: +r.width.toFixed(2), h: +r.height.toFixed(2) },
      display: cs.display,
      position: cs.position,
      color: cs.color,
      fontSize: cs.fontSize,
      fontWeight: cs.fontWeight,
      lineHeight: cs.lineHeight,
      textDecorationLine: cs.textDecorationLine,
      textDecorationColor: cs.textDecorationColor,
      paddingBlock: cs.paddingTop + ' / ' + cs.paddingBottom,
      marginBlock: cs.marginTop + ' / ' + cs.marginBottom,
      minHeight: cs.minHeight,
      whiteSpace: cs.whiteSpace,
      tapVar: cs.getPropertyValue('--rz-tap').trim(),
      tapSideVar: cs.getPropertyValue('--rz-tap-side').trim(),
      afterContent: after.content,
      afterPosition: after.position,
      afterInsetBlock: after.insetBlockStart + ' / ' + after.insetBlockEnd,
      afterInsetInline: after.insetInlineStart + ' / ' + after.insetInlineEnd,
    });
  }
  return out;
});

console.log(JSON.stringify(data, null, 2));

// hit-area measurement via elementFromPoint probing around each link
const probe = await page.evaluate(() => {
  const res = [];
  const root = document.querySelector('.rz-first-aid');
  for (const a of root.querySelectorAll('a[href^="tel:"]')) {
    const r = a.getBoundingClientRect();
    const cx = r.left + r.width / 2;
    const cy = r.top + r.height / 2;
    const hits = (dx, dy) => {
      let n = 0;
      for (let i = 0; i < 60; i++) {
        const el = document.elementFromPoint(cx + dx * i, cy + dy * i);
        if (!el) break;
        if (el === a || a.contains(el) || el.parentElement === a) n = i; else break;
      }
      return n;
    };
    const up = hits(0, -1), down = hits(0, 1), left = hits(-1, 0), right = hits(1, 0);
    res.push({
      text: a.textContent.trim(),
      cssRect: { w: +r.width.toFixed(2), h: +r.height.toFixed(2) },
      hitBox: { w: left + right + 1, h: up + down + 1 },
      up, down, left, right,
    });
  }
  return res;
});
console.log('\n=== HIT AREA PROBE (elementFromPoint, px) ===');
console.log(JSON.stringify(probe, null, 2));

await browser.close();
