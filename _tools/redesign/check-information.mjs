/** Read-only UI checks. Never submits the contact/review forms. */
import { createRequire } from 'node:module';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import assert from 'node:assert/strict';
const require = createRequire(resolve('wp-content/themes/razil/package.json'));
const { chromium } = require('playwright');
require('postcss').parse(readFileSync('wp-content/themes/razil/assets/css/information.css', 'utf8'));
const base = 'http://ra-zil.test';
const out = '_check/pages-tour-2026-10-13';
mkdirSync(out, { recursive: true });
const mode = process.argv[2] || 'layout';
const allRoutes = ['o-kompanii', 'kontakty', 'otzyvy', 'rekvizity', 'politika', 'soglasie', 'polzovatelskoe-soglashenie', 'uslugi', '', 'spasibo'];
const selected = process.argv[3]?.split(',').map(s => s === 'home' ? '' : s);
const routes = selected ? allRoutes.filter(s => selected.includes(s)) : allRoutes;
const cases = mode === 'layout' ? [[320,'light',100],[375,'light',100],[768,'light',100],[1366,'light',100],[1366,'dark',100],[768,'light',200]] : [[1366,'light',100],[375,'light',100]];
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const reports = [];
try {
  if (mode === 'layout') {
    for (const slug of routes) for (const [width, colorScheme, zoom] of cases) {
      const page = await browser.newPage({ viewport: { width, height: 900 }, colorScheme, reducedMotion: 'reduce' });
      const errors = [], tourRequests = [];
      page.on('pageerror', e => errors.push(e.message));
      page.on('request', r => { if (r.url().includes('/virtual-tour/')) tourRequests.push(r.url()); });
      assert.equal((await page.goto(`${base}/${slug}`, { waitUntil: 'domcontentloaded' })).status(), 200);
      await page.evaluate(() => document.fonts.ready);
      await page.waitForTimeout(150);
      if (zoom !== 100) await page.evaluate(z => document.documentElement.style.fontSize = z + '%', zoom);
      const data = await page.evaluate(() => ({ overflow: document.documentElement.scrollWidth > innerWidth, h1: document.querySelectorAll('h1').length, duplicateIds: [...document.querySelectorAll('[id]')].map(n=>n.id).filter((id,i,all)=>all.indexOf(id)!==i), height: document.documentElement.scrollHeight }));
      const key = `${slug || 'home'}-${width}-${colorScheme}-${zoom}`;
      const result = { key, ...data, errors, tourRequests: tourRequests.length };
      reports.push(result);
      writeFileSync(`${out}/layout-results${selected ? '-'+process.argv[3] : ''}.json`, JSON.stringify(reports, null, 2));
      assert.equal(data.overflow, false, key);
      assert.equal(data.h1, 1, key);
      assert.deepEqual(data.duplicateIds, [], key);
      assert.deepEqual(errors, [], key);
      assert.equal(tourRequests.length, 0, key + ' loads tour before click');
      if (width === 1366 || width === 375) await page.screenshot({ path: `${out}/${key}.png`, fullPage: true });
      if (width === 375 && ['o-kompanii','kontakty','otzyvy','rekvizity'].includes(slug)) await page.screenshot({ path: `${out}/${slug}-mobile-top.png` });
      if (width === 1366) {
        await page.addScriptTag({ path: require.resolve('axe-core/axe.min.js') });
        const selector = await page.locator('main').count() ? 'main' : (['politika','soglasie','polzovatelskoe-soglashenie'].includes(slug) ? '.rz-document-layout' : '.wp-site-blocks > .wp-block-group');
        const violations = await page.evaluate(async selector => (await axe.run({ include: [[selector]] }, { runOnly: { type: 'tag', values: ['wcag2a','wcag2aa','wcag21aa'] } })).violations.map(v=>({ id:v.id, nodes:v.nodes.map(n=>({target:n.target,summary:n.failureSummary})) })), selector);
        result.violations = violations;
        writeFileSync(`${out}/layout-results${selected ? '-'+process.argv[3] : ''}.json`, JSON.stringify(reports, null, 2));
        if (violations.length) console.log(JSON.stringify({key, violations}));
        assert.deepEqual(violations, [], key);
      }
      console.log(key + ': OK');
      await page.close();
    }
  } else {
    for (const width of [1366, 375]) {
      const page = await browser.newPage({ viewport: { width, height: 900 }, reducedMotion: 'reduce' });
      const failures = [];
      page.on('response', r => { if (r.url().includes('/virtual-tour/') && r.status() >= 400) failures.push(r.status() + ' ' + r.url()); });
      await page.goto(`${base}/o-kompanii/#virtual-tour`, { waitUntil: 'networkidle' });
      assert.equal(await page.locator('.rz-tour iframe').count(), 0);
      const start = page.locator('[data-tour-start]');
      await start.focus(); await page.keyboard.press('Enter');
      const getTourFrame = () => page.frames().find(f=>f.url().includes('/virtual-tour/index.html'));
      await page.waitForFunction(()=>document.querySelector('.rz-tour iframe')?.contentWindow?.document.getElementById('krpanoSWFObject')?.get?.('scene.count') === 14);
      let frame = getTourFrame();
      assert.equal(await frame.evaluate(()=>document.getElementById('krpanoSWFObject').get('xml.scene')), 'pano159');
      await page.locator('[data-tour-scene]').selectOption('pano23');
      await page.waitForFunction(()=>document.querySelector('.rz-tour iframe')?.contentWindow?.document.getElementById('krpanoSWFObject')?.get?.('xml.scene') === 'pano23');
      frame = getTourFrame();
      await frame.waitForLoadState('networkidle');
      assert.equal(await frame.evaluate(()=>document.getElementById('krpanoSWFObject').get('autorotate.enabled')), false);
      await page.locator('.rz-tour').screenshot({ path:`${out}/tour-active-${width}.png` });
      if (width === 1366) {
        await page.locator('[data-tour-fullscreen]').click();
        await page.waitForFunction(()=>!!document.fullscreenElement);
        await page.locator('[data-tour-fullscreen]').click();
        await page.waitForFunction(()=>!document.fullscreenElement);
      }
      await page.locator('[data-tour-close]').click();
      assert.equal(await page.locator('.rz-tour iframe').count(), 0);
      assert.equal(await start.evaluate(n=>n===document.activeElement), true);
      assert.deepEqual(failures, []);
      await page.goto(`${base}/otzyvy/`, { waitUntil: 'networkidle' });
      await page.locator('.rz-review-compose summary').focus(); await page.keyboard.press('Enter');
      assert.equal(await page.locator('.rz-review-form').isVisible(), true);
      await page.locator('.rz-review-compose summary').click();
      assert.equal(await page.locator('.rz-review-form').isVisible(), false);
      const expand = page.locator('.rz-review-expand:visible').first();
      await expand.click();
      assert.equal(await expand.getAttribute('aria-expanded'), 'true');
      await expand.click();
      assert.equal(await expand.getAttribute('aria-expanded'), 'false');
      assert.equal(await page.locator('.rz-reviews-list__item:visible').count(), 6);
      await page.locator('[data-rz-more]').click();
      assert.equal(await page.locator('.rz-reviews-list__item:visible').count(), 12);
      await page.goto(`${base}/otzyvy/?rz=no_rating`, { waitUntil: 'domcontentloaded' });
      assert.equal(await page.locator('.rz-review-compose').evaluate(n=>n.open), true);
      await page.goto(`${base}/rekvizity/`, { waitUntil:'networkidle' });
      await page.locator('[data-copy-target="company-detail-4"]').click();
      assert.match(await page.locator('[data-copy-status]').innerText(), /скопировано/);
      await page.evaluate(()=>{ window.print = () => {window.rzPrintCalled = true;}; });
      await page.locator('[data-rz-print]').click();
      assert.equal(await page.evaluate(()=>window.rzPrintCalled), true);
      for (const slug of ['politika','soglasie','polzovatelskoe-soglashenie']) {
        await page.goto(`${base}/${slug}/`, { waitUntil:'domcontentloaded' });
        const details = page.locator('.rz-document-toc details');
        if (!await details.evaluate(n=>n.open)) await details.locator('summary').click();
        const anchor = details.locator('a').nth(2), href = await anchor.getAttribute('href');
        await anchor.click();
        const top = await page.locator(href).evaluate(n=>n.getBoundingClientRect().top);
        const header = await page.locator('.rz-header').evaluate(n=>n.getBoundingClientRect().bottom);
        assert.ok(top >= header-1, `${slug} ${width}: obscured by header`);
      }
      console.log(`UI ${width}: tour, fullscreen, form toggle, pagination, copy, print, outlines OK`);
      await page.close();
    }
    const nojs = await browser.newContext({ javaScriptEnabled: false });
    const page = await nojs.newPage();
    await page.goto(`${base}/o-kompanii/`);
    assert.match(await page.locator('[data-tour-start]').getAttribute('href'), /virtual-tour\/index.html/);
    await page.goto(`${base}/otzyvy/`); await page.locator('.rz-review-compose summary').click();
    assert.equal(await page.locator('.rz-review-form').isVisible(), true);
    assert.equal(await page.locator('.rz-reviews-list__item:visible').count(), 22);
    console.log('No JavaScript: tour link, form disclosure and all reviews available');
  }
} finally { await browser.close(); }
