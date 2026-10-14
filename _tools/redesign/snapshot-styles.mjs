/**
 * Снимок вычисленных стилей всех элементов страницы.
 *
 * Нужен там, где правка обязана ничего не изменить: перестановка правил
 * в CSS, вынос цвета в токен, переименование секции. Счётчик правил и
 * взгляд на снимок экрана такого не доказывают — доказывает совпадение
 * вычисленных значений на каждом элементе.
 *
 * Цвета приводятся к единому виду. Chrome отдаёт то rgb(0-255), то
 * color(srgb 0-1) — для одного и того же цвета, в зависимости от того,
 * как он записан в таблице стилей. Без нормализации сравнение
 * показывало бы расхождение там, где цвет не менялся.
 *
 * Запуск:
 *   node _tools/redesign/snapshot-styles.mjs --out <файл.json>
 *                                            [--widths 375,1366]
 *                                            [--only slug,slug]
 *
 * Сравнение: node _tools/redesign/diff-styles.mjs <до.json> <после.json>
 */

import { mkdirSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { createRequire } from 'node:module'

const PW_DIR = process.env.RZ_PW_DIR
if (!PW_DIR) {
  console.error('Нужна переменная окружения RZ_PW_DIR')
  process.exit(2)
}
const { chromium } = createRequire(join(PW_DIR, 'noop.js'))('playwright-core')

const BASE = 'http://ra-zil.test'

const PAGES = [
  ['home', '/'],
  ['svc-index', '/uslugi/'],
  ['svc-blagoustroystvo', '/uslugi/blagoustroystvo/'],
  ['svc-organizatsiya', '/uslugi/organizatsiya-pohoron/'],
  ['svc-krematsiya', '/uslugi/krematsiya/'],
  ['svc-transportirovka', '/uslugi/transportirovka/'],
  ['svc-yurpomoshch', '/uslugi/yuridicheskaya-pomoshch/'],
  ['kontakty', '/kontakty/'],
  ['otzyvy', '/otzyvy/'],
  ['o-kompanii', '/o-kompanii/'],
  ['rekvizity', '/rekvizity/'],
  ['katalog', '/katalog/'],
  ['nashi-raboty', '/nashi-raboty/'],
  ['vopros-otvet', '/vopros-otvet/'],
  ['stati', '/stati/'],
  ['politika', '/politika/'],
  ['soglasie', '/soglasie/'],
  ['polzovatelskoe', '/polzovatelskoe-soglashenie/'],
  ['spasibo', '/spasibo/'],
]

function arg(name, fallback) {
  const i = process.argv.indexOf('--' + name)
  return i >= 0 && process.argv[i + 1] ? process.argv[i + 1] : fallback
}

const out = arg('out', null)
if (!out) {
  console.error('Нужен --out <файл.json>')
  process.exit(2)
}
const widths = arg('widths', '375,1366').split(',').map(Number)
const only = arg('only', '')
const pages = only ? PAGES.filter(([k]) => only.split(',').includes(k)) : PAGES

/** Снимается внутри страницы. */
const collect = () => {
  // Свойства, которыми описывается внешний вид. Полный список
  // getComputedStyle даёт ~340 свойств на элемент и десятки мегабайт
  // на страницу, а различают вид эти.
  const PROPS = [
    'display', 'position', 'visibility', 'opacity', 'z-index', 'overflow-x', 'overflow-y',
    'width', 'height', 'min-height', 'min-width', 'max-width', 'max-height',
    'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
    'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
    'color', 'background-color', 'background-image',
    'border-top-width', 'border-right-width', 'border-bottom-width', 'border-left-width',
    'border-top-color', 'border-right-color', 'border-bottom-color', 'border-left-color',
    'border-top-style', 'border-right-style', 'border-bottom-style', 'border-left-style',
    'border-top-left-radius', 'border-top-right-radius',
    'border-bottom-left-radius', 'border-bottom-right-radius',
    'font-family', 'font-size', 'font-weight', 'font-style', 'line-height',
    'letter-spacing', 'text-align', 'text-decoration-line', 'text-transform',
    'white-space', 'text-overflow',
    'flex-direction', 'flex-wrap', 'align-items', 'justify-content', 'gap',
    'grid-template-columns', 'grid-template-rows', 'order', 'flex-grow', 'flex-shrink', 'flex-basis',
    'box-shadow', 'outline-color', 'outline-width', 'outline-style',
    'transform', 'transition-property', 'transition-duration',
    'aspect-ratio', 'object-fit', 'object-position', 'list-style-type',
  ]

  // Цвет к единому виду: и rgb(0-255), и color(srgb 0-1) сводятся
  // к восьми шестнадцатеричным разрядам.
  const norm = (v) => {
    if (typeof v !== 'string') return v
    return v.replace(/(?:rgba?\(([^)]*)\)|color\(srgb ([^)]*)\))/g, (m, rgb, srgb) => {
      let parts
      if (rgb !== undefined) {
        parts = rgb.split(/[,\s/]+/).filter(Boolean).map(Number)
      } else {
        parts = srgb.split(/[\s/]+/).filter(Boolean).map(Number)
        parts = [parts[0] * 255, parts[1] * 255, parts[2] * 255, parts[3]]
      }
      const [r, g, b] = parts
      const a = parts[3] === undefined ? 1 : parts[3]
      const hx = (n) => Math.round(Math.max(0, Math.min(255, n))).toString(16).padStart(2, '0')
      return '#' + hx(r) + hx(g) + hx(b) + hx(a * 255)
    })
  }

  // Устойчивый ключ элемента: путь от корня по позиции среди братьев
  // плюс тег. По классам ключ строить нельзя — редизайн классы меняет,
  // и снимок перестал бы сопоставляться.
  const keyOf = (el) => {
    const parts = []
    let cur = el
    while (cur && cur !== document.documentElement) {
      const parent = cur.parentElement
      if (!parent) break
      const idx = [...parent.children].indexOf(cur)
      parts.unshift(cur.tagName.toLowerCase() + '[' + idx + ']')
      cur = parent
    }
    return parts.join('>')
  }

  const data = {}
  const all = document.querySelectorAll('html, body, body *')
  for (const el of all) {
    const cs = getComputedStyle(el)
    const rec = {}
    for (const p of PROPS) rec[p] = norm(cs.getPropertyValue(p))
    const r = el.getBoundingClientRect()
    rec['@box'] = [r.x, r.y, r.width, r.height].map((n) => Math.round(n * 100) / 100).join(',')
    data[keyOf(el)] = rec
  }
  return { count: all.length, scrollWidth: document.documentElement.scrollWidth, data }
}

const browser = await chromium.launch({ channel: 'chrome', headless: true })
const snapshot = {}

for (const [key, url] of pages) {
  for (const width of widths) {
    const ctx = await browser.newContext({ viewport: { width, height: 900 }, deviceScaleFactor: 1 })
    const tab = await ctx.newPage()
    try {
      await tab.goto(BASE + url, { waitUntil: 'networkidle', timeout: 45000 })
      const res = await tab.evaluate(collect)
      snapshot[key + '@' + width] = res
      console.log(key.padEnd(22) + String(width).padStart(5) + 'px  элементов ' + res.count)
    } catch (e) {
      snapshot[key + '@' + width] = { error: String(e).slice(0, 300) }
      console.log(key.padEnd(22) + String(width).padStart(5) + 'px  ОШИБКА ' + String(e).slice(0, 120))
    }
    await ctx.close()
  }
}

await browser.close()
mkdirSync(dirname(out), { recursive: true })
writeFileSync(out, JSON.stringify(snapshot), 'utf8')
console.log('\n--- Снимок стилей записан: ' + out)
