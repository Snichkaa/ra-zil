import { chromium } from 'file:///C:/laragon/www/ra-zil/wp-content/themes/razil/node_modules/playwright/index.mjs';

const browser = await chromium.launch({ channel: 'chrome' });
const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 }, colorScheme: 'light' });
const page = await ctx.newPage();
await page.goto('http://ra-zil.test/', { waitUntil: 'networkidle' });

const res = await page.evaluate(() => {
  const el = document.querySelector('.rz-first-aid__warning');
  const cs = getComputedStyle(el);
  const rootCs = getComputedStyle(document.documentElement);
  const rv = (n) => rootCs.getPropertyValue(n).trim();
  const tel = el.closest('.rz-first-aid').querySelector('a[href^="tel:"]');
  return {
    warning: {
      backgroundColor: cs.backgroundColor,
      borderColor: cs.borderColor,
      borderTopColor: cs.borderTopColor,
      borderRightColor: cs.borderRightColor,
      borderBottomColor: cs.borderBottomColor,
      borderLeftColor: cs.borderLeftColor,
      color: cs.color,
      border: cs.border,
      borderRadius: cs.borderRadius,
      padding: cs.padding,
    },
    pageBg: getComputedStyle(el.closest('.rz-first-aid')).backgroundColor,
    bodyBg: getComputedStyle(document.body).backgroundColor,
    telColor: tel ? getComputedStyle(tel).color : null,
    tokens: {
      '--rz-warn-bg': rv('--rz-warn-bg'),
      '--rz-warn-border': rv('--rz-warn-border'),
      '--rz-warn-text': rv('--rz-warn-text'),
      '--rz-bg': rv('--rz-bg'),
      '--rz-action': rv('--rz-action'),
    },
  };
});
console.log(JSON.stringify(res, null, 2));

const box = page.locator('.rz-first-aid__warning');
await box.scrollIntoViewIfNeeded();
await page.waitForTimeout(250);
await box.screenshot({ path: '_tools/t3/shot3-warning-light.png' });
await page.locator('.rz-first-aid').screenshot({ path: '_tools/t3/shot3-section-light.png' });
await ctx.close();
await browser.close();
