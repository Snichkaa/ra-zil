/** Browser checks for all service pages. Does not submit forms or follow tel links. */
import { createRequire } from 'node:module';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import assert from 'node:assert/strict';
const require = createRequire(resolve('wp-content/themes/razil/package.json'));
const { chromium } = require('playwright');
const services = [[11, 'organizatsiya-pohoron', 4], [12, 'krematsiya', 4], [13, 'transportirovka', 4], [14, 'blagoustroystvo', 7], [15, 'yuridicheskaya-pomoshch', 4]];
const mode = process.argv[2] || 'layout';
const filter = process.argv[3]?.split(',').map(Number);
const selected = filter ? services.filter(([id]) => filter.includes(id)) : services;
const dir = resolve('_check/services-2026-10-13', mode);
mkdirSync(dir, { recursive: true });
if (mode !== 'before') require('postcss').parse(readFileSync('wp-content/themes/razil/assets/css/service-details.css', 'utf8'));
const cases = mode === 'before' ? [[1366, 'light', 100]] : mode === 'preview' ? [[1366, 'light', 100], [375, 'light', 100], [768, 'light', 200]] : mode === 'ui' ? [[375, 'light', 100], [1366, 'dark', 100], [768, 'light', 200]] : [320, 375, 768, 1024, 1366, 1920].map(w => [w, 'light', 100]);
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const reports = [];
try {
  for (const [id, slug, photoCount] of selected) {
    for (const [width, colorScheme, zoom] of cases) {
      const page = await browser.newPage({ viewport: { width, height: 900 }, colorScheme, reducedMotion: 'reduce' });
      const errors = [];
      page.on('pageerror', e => errors.push(e.message));
      const response = await page.goto(`http://ra-zil.test/uslugi/${slug}/`, { waitUntil: 'networkidle', timeout: 60000 });
      assert.equal(response.status(), 200);
      await page.evaluate(() => document.fonts.ready);
      if (zoom !== 100) await page.evaluate(zoom => { document.documentElement.style.fontSize = `${zoom}%`; }, zoom);
      const key = `${id}-${width}-${colorScheme}-${zoom}`;
      if (width === 1366 || (mode === 'layout' && width === 375)) await page.screenshot({ path: `${dir}/${key}.png`, fullPage: true });
      if (mode === 'preview' && width === 375) {
        for (const section of ['hero', 'docs', 'price']) await page.locator(`.rz-svc-${section}`).screenshot({ path: `${dir}/${key}-${section}.png` });
      }
      const result = await page.evaluate(() => ({
        height: document.documentElement.scrollHeight,
        overflow: document.documentElement.scrollWidth > innerWidth,
        clipped: [...document.querySelectorAll('.entry-content *')].filter(n => n.getBoundingClientRect().width && (n.getBoundingClientRect().right > innerWidth + 1 || n.getBoundingClientRect().left < -1)).map(n => n.className).slice(0, 15),
        h1: document.querySelectorAll('h1').length,
        photos: [...document.querySelectorAll('.entry-content .rz-photo-slot')].map(n => n.dataset.photoNumber),
        prices: [...document.querySelectorAll('.is-style-pricelist tbody td:last-child, .rz-svc-pack__price')].map(n => n.textContent.trim()),
        stageColumns: [...document.querySelectorAll('.rz-svc-stages__grid')].map(n => getComputedStyle(n).gridTemplateColumns),
      }));
      const report = { id, width, colorScheme, zoom, ...result, errors };
      if (mode !== 'before') {
        assert.equal(result.h1, 1, key);
        assert.deepEqual(result.photos, Array.from({ length: photoCount }, (_, i) => String(i + 1)), key);
        assert.ok(result.prices.length && result.prices.every(p => p === '…' || p === 'от …'), key);
        if (result.overflow || result.clipped.length) console.log(JSON.stringify(report));
        assert.equal(result.overflow, false, key);
        assert.deepEqual(result.clipped, [], key);
        assert.deepEqual(errors, [], key);
      }
      if (mode === 'ui') {
        const anchorIds = await page.locator('.rz-svc-nav a').evaluateAll(nodes => nodes.map(n => n.getAttribute('href')));
        assert.equal(new Set(anchorIds).size, anchorIds.length);
        for (const href of anchorIds) {
          const anchor = page.locator(`.rz-svc-nav a[href="${href}"]`);
          await anchor.click();
          assert.equal(await page.locator(href).count(), 1);
          const top = await page.locator(href).evaluate(n => n.getBoundingClientRect().top);
          const header = await page.locator('.rz-header').evaluate(n => n.getBoundingClientRect().bottom);
          assert.ok(top >= header - 1, `${key} ${href}: sticky header overlap`);
        }
        assert.equal(await page.locator('#rz-cb-dialog').count(), 1);
        for (const button of await page.locator('.rz-svc-shell [data-rz-cb-open], .rz-callback [data-rz-cb-open]').all()) {
          await button.click();
          assert.equal(await page.locator('#rz-cb-dialog').evaluate(n => n.open), true);
          await page.keyboard.press('Escape');
          assert.equal(await page.locator('#rz-cb-dialog').evaluate(n => n.open), false);
          assert.equal(await button.evaluate(n => n === document.activeElement), true);
        }
        await page.locator('.rz-svc-faq__list summary').nth(1).focus();
        await page.keyboard.press('Enter');
        await page.waitForFunction(() => document.querySelectorAll('.rz-svc-faq__list details[open]').length === 1);
        assert.equal(await page.locator('.rz-svc-faq__list details').nth(1).evaluate(n => n.open), true);
        await page.addScriptTag({ path: require.resolve('axe-core/axe.min.js') });
        report.violations = await page.evaluate(async () => (await axe.run({ include: [['.entry-content']] }, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa'] } })).violations.map(v => ({ id: v.id, nodes: v.nodes.map(n => ({ target: n.target, summary: n.failureSummary })) })));
        if (report.violations.length) console.log(JSON.stringify(report.violations));
        assert.deepEqual(report.violations, [], key);
      }
      reports.push(report);
      writeFileSync(`${dir}/results${filter ? '-' + filter.join('-') : ''}.json`, JSON.stringify(reports, null, 2));
      console.log(`${key}: height ${result.height}, overflow ${result.overflow}, photos ${result.photos.length}, errors ${errors.length}`);
      await page.close();
    }
  }
} finally { await browser.close(); }
