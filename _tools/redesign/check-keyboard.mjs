/**
 * Клавиатура и консоль.
 *
 * Проходит Tab-ом по началу страницы и смотрит, что получает фокус
 * и видна ли обводка. Обводка считается видимой, если у элемента
 * в состоянии :focus-visible ненулевая толщина контура — снимается
 * не из таблицы стилей, а с живого элемента после реального Tab.
 *
 * Запуск:
 *   node _tools/redesign/check-keyboard.mjs [--url /]
 *        [--widths 375,1366] [--steps 12]
 */

import { join } from 'node:path'
import { createRequire } from 'node:module'

const PW_DIR = process.env.RZ_PW_DIR
if (!PW_DIR) {
  console.error('Нужна переменная окружения RZ_PW_DIR')
  process.exit(2)
}
const { chromium } = createRequire(join(PW_DIR, 'noop.js'))('playwright-core')

function arg(name, fallback) {
  const i = process.argv.indexOf('--' + name)
  return i >= 0 && process.argv[i + 1] ? process.argv[i + 1] : fallback
}

const url = arg('url', '/')
const widths = arg('widths', '375,1366').split(',').map(Number)
const steps = Number(arg('steps', '12'))

const browser = await chromium.launch({ channel: 'chrome', headless: true })
let failed = 0

for (const width of widths) {
  const ctx = await browser.newContext({ viewport: { width, height: 900 } })
  const page = await ctx.newPage()
  const errs = []
  page.on('pageerror', (e) => errs.push('исключение: ' + String(e).slice(0, 140)))
  page.on('console', (m) => { if (m.type() === 'error') errs.push('консоль: ' + m.text().slice(0, 140)) })

  await page.goto('http://ra-zil.test' + url, { waitUntil: 'networkidle', timeout: 45000 })

  console.log('--- ' + width + 'px')
  let noOutline = 0
  for (let i = 0; i < steps; i++) {
    await page.keyboard.press('Tab')
    const r = await page.evaluate(() => {
      const el = document.activeElement
      if (!el || el === document.body) return null
      const cs = getComputedStyle(el)
      const rect = el.getBoundingClientRect()
      const w = parseFloat(cs.outlineWidth) || 0
      // Обводку может рисовать и тень, и рамка — учитываем всё,
      // чем на сайте показывают фокус.
      const shadow = cs.boxShadow && cs.boxShadow !== 'none'
      return {
        tag: el.tagName.toLowerCase(),
        cls: String(el.className).slice(0, 34),
        text: (el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 26),
        outline: w, style: cs.outlineStyle, color: cs.outlineColor, shadow,
        inView: rect.top >= -1 && rect.bottom <= window.innerHeight + 1,
        h: Math.round(rect.height),
      }
    })
    if (!r) { console.log('   ' + String(i + 1).padStart(2) + '. (фокус ушёл из страницы)'); break }
    const visible = (r.outline > 0 && r.style !== 'none') || r.shadow
    if (!visible) { noOutline++; failed++ }
    console.log(
      '   ' + String(i + 1).padStart(2) + '. ' + (r.tag + '.' + r.cls).padEnd(38) +
      ' h=' + String(r.h).padStart(3) +
      '  обводка ' + (visible ? r.outline + 'px ' + r.style : 'НЕТ') +
      '  «' + r.text + '»'
    )
  }
  if (errs.length) { failed += errs.length; console.log('   ОШИБКИ: ' + errs.join(' | ')) }
  else console.log('   ошибок в консоли нет')
  if (noOutline) console.log('   БЕЗ ВИДИМОЙ ОБВОДКИ: ' + noOutline)
  await ctx.close()
}

await browser.close()
console.log('\n--- замечаний: ' + failed)
process.exit(failed ? 1 : 0)
