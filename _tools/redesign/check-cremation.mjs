/** Visual checks for the cremation layout. Never submits a callback form. */
import { createRequire } from 'node:module';
import { mkdirSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import assert from 'node:assert/strict';
const require = createRequire(resolve('wp-content/themes/razil/package.json'));
const { chromium } = require('playwright');
const phase = process.argv[2] || 'after';
const dir = resolve('_check/cremation-2026-10-13', phase);
mkdirSync(dir, { recursive: true });
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const results = [];
try {
  const widths = phase === 'before' ? [1366, 375] : [320, 375, 768, 1024, 1366, 1920];
  for (const width of widths) {
    const page = await browser.newPage({ viewport: { width, height: 900 }, colorScheme: 'light', reducedMotion: 'reduce' });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto('http://ra-zil.test/uslugi/krematsiya/', { waitUntil: 'networkidle' });
    await page.evaluate(() => document.fonts.ready);
    if ([375, 1366].includes(width)) await page.screenshot({ path: `${dir}/${width}.png`, fullPage: true });
    const metrics = await page.evaluate(() => ({
      title: document.title,
      width: innerWidth,
      height: document.documentElement.scrollHeight,
      overflow: document.documentElement.scrollWidth > innerWidth,
      h1: document.querySelectorAll('h1').length,
      placeholders: document.querySelectorAll('.entry-content .rz-photo-slot').length,
      prices: [...document.querySelectorAll('.is-style-pricelist tbody td:last-child')].map(n => n.textContent.trim()),
      sections: [...document.querySelectorAll('.rz-svc-sec')].map(n => ({ class: n.className, height: Math.round(n.getBoundingClientRect().height) })),
      styles: [...document.querySelectorAll('link[rel=stylesheet]')].map(n => n.href).filter(h => h.includes('service-details')),
      overflowing: [...document.querySelectorAll('.entry-content *')].filter(n => n.getBoundingClientRect().right > innerWidth + 1).map(n => n.className).slice(0, 10),
    }));
    results.push({ ...metrics, errors });
    await page.close();
  }
  writeFileSync(`${dir}/metrics.json`, JSON.stringify(results, null, 2));
  console.log(JSON.stringify(results.map(({ width, height, overflow, overflowing, h1, placeholders, errors }) => ({ width, height, overflow, overflowing, h1, placeholders, errors })), null, 2));
  assert.ok(results.every(result => !result.overflow && !result.overflowing.length && !result.errors.length));
} finally { await browser.close(); }
