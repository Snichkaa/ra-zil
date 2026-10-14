/** Responsive, keyboard, dialog and contrast checks. No form is submitted. */
import { createRequire } from 'node:module';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import assert from 'node:assert/strict';
const require = createRequire(resolve('wp-content/themes/razil/package.json'));
const { chromium } = require('playwright');
require('postcss').parse(readFileSync('wp-content/themes/razil/assets/css/service-details.css', 'utf8'));
const dir = resolve('_check/cremation-2026-10-13/ui');
mkdirSync(dir, { recursive: true });
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const reports = [];
try {
  const cases = [[1366, 'light', 100], [375, 'light', 100], [1366, 'dark', 100], [375, 'dark', 100], [768, 'light', 200]];
  for (const [width, colorScheme, zoom] of process.argv.includes('--zoom-only') ? cases.slice(-1) : cases) {
    const page = await browser.newPage({ viewport: { width, height: 900 }, colorScheme, reducedMotion: 'reduce' });
    await page.goto('http://ra-zil.test/uslugi/krematsiya/', { waitUntil: 'networkidle' });
    await page.evaluate(() => document.fonts.ready);
    if (zoom !== 100) await page.evaluate(zoom => { document.documentElement.style.fontSize = `${zoom}%`; }, zoom);
    const key = `${width}-${colorScheme}-${zoom}`;
    for (const section of ['hero', 'docs', 'price']) {
      if (width === 375 || zoom === 200) await page.locator(`.rz-svc-${section}`).screenshot({ path: `${dir}/${key}-${section}.png` });
    }
    if (colorScheme === 'dark' && width === 1366) await page.screenshot({ path: `${dir}/${key}.png`, fullPage: true });
    const sizing = await page.evaluate(() => ({ overflow: document.documentElement.scrollWidth > innerWidth, clipped: [...document.querySelectorAll('.entry-content *')].filter(n => n.getBoundingClientRect().right > innerWidth + 1).map(n => n.className) }));
    if (sizing.overflow || sizing.clipped.length) console.log(JSON.stringify({ key, ...sizing }));
    assert.equal(sizing.overflow, false, key);
    assert.deepEqual(sizing.clipped, [], key);

    // Jump links must target existing sections and clear the sticky header.
    for (const anchor of await page.locator('.rz-svc-nav a').all()) {
      const href = await anchor.getAttribute('href');
      await anchor.click();
      const offset = await page.locator(href).evaluate(n => n.getBoundingClientRect().top);
      const header = await page.locator('.rz-header').evaluate(n => n.getBoundingClientRect().bottom);
      assert.ok(offset >= header - 1, `${key}: anchor ${href} is covered by header`);
    }

    // Both page actions and the final CTA use the same existing callback dialog.
    assert.equal(await page.locator('#rz-cb-dialog').count(), 1);
    for (const button of await page.locator('.rz-svc-shell [data-rz-cb-open], .rz-callback [data-rz-cb-open]').all()) {
      await button.click();
      assert.equal(await page.locator('#rz-cb-dialog').evaluate(n => n.open), true);
      await page.keyboard.press('Escape');
      assert.equal(await page.locator('#rz-cb-dialog').evaluate(n => n.open), false);
      assert.equal(await button.evaluate(n => n === document.activeElement), true);
    }

    const questions = page.locator('.rz-svc-faq__list summary');
    await questions.nth(1).focus();
    await page.keyboard.press('Enter');
    await page.waitForFunction(() => document.querySelectorAll('.rz-svc-faq__list details[open]').length === 1);
    assert.equal(await page.locator('.rz-svc-faq__list details[open]').count(), 1);
    assert.equal(await questions.nth(1).evaluate(n => n.parentElement.open), true);

    await page.addScriptTag({ path: require.resolve('axe-core/axe.min.js') });
    const audit = await page.evaluate(async () => {
      const results = await axe.run({ include: [['.entry-content']] }, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa'] } });
      return results.violations.map(v => ({ id: v.id, nodes: v.nodes.map(n => ({ target: n.target, summary: n.failureSummary })) }));
    });
    reports.push({ key, ...sizing, violations: audit });
    console.log(JSON.stringify(reports.at(-1)));
    await page.close();
  }
  const control = await browser.newPage();
  const response = await control.goto('http://ra-zil.test/', { waitUntil: 'networkidle' });
  assert.equal(response.status(), 200);
  assert.equal(await control.locator('body.single-services').count(), 0);
  assert.equal(await control.locator('#razil-service-details-css').count(), 0);
  assert.equal(await control.locator('body.rz-svc-layout').count(), 0);
  await control.close();
  writeFileSync(`${dir}/checks.json`, JSON.stringify(reports, null, 2));
} finally { await browser.close(); }
