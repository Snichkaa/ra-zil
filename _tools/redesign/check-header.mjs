/**
 * Проверка шапки: перенос, высота, компенсация, перекрытие заголовков.
 *
 * Именно этих проверок не было в прошлом прогоне, и именно поэтому
 * шапка в три строки высотой 280 px прошла аудит без замечаний.
 *
 * Про перенос отдельно. Считать переносом разные offsetTop у детей
 * нельзя: контейнер выравнивает их по центру, и у элементов разной
 * высоты верхняя кромка законно стоит на разной высоте. Поэтому
 * строки определяются по пересечению вертикальных диапазонов:
 * дети, чьи диапазоны перекрываются, стоят в одной строке.
 * offsetTop печатается рядом — его требует задание.
 *
 * Запуск:
 *   node _tools/redesign/check-header.mjs [--url /]
 *        [--widths 375,768,1024,1366,1920] [--zoom 100]
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
const widths = arg('widths', '375,768,1024,1366,1920').split(',').map(Number)
const zoom = Number(arg('zoom', '100'))

const probe = () => {
  const inner = document.querySelector('.rz-header__inner')
  const hdr = document.querySelector('.rz-header')
  const doc = document.documentElement

  const vis = [...inner.children].filter((k) => {
    const cs = getComputedStyle(k)
    return cs.display !== 'none' && cs.visibility !== 'hidden'
  })

  // Разбивка на строки по пересечению вертикальных диапазонов.
  const boxes = vis.map((k) => {
    const r = k.getBoundingClientRect()
    return { el: k, top: r.top, bottom: r.bottom, name: String(k.className).split(' ')[0] || k.tagName.toLowerCase() }
  }).sort((a, b) => a.top - b.top)

  const rows = []
  for (const bx of boxes) {
    const row = rows.find((rr) => bx.top < rr.bottom - 1 && bx.bottom > rr.top + 1)
    if (row) {
      row.top = Math.min(row.top, bx.top)
      row.bottom = Math.max(row.bottom, bx.bottom)
      row.items.push(bx.name)
    } else {
      rows.push({ top: bx.top, bottom: bx.bottom, items: [bx.name] })
    }
  }

  const headerH = hdr.getBoundingClientRect().height
  const padTop = parseFloat(getComputedStyle(document.body).paddingTop)

  // Перекрытие заголовков шапкой: прокручиваем к каждому H2
  // и смотрим, что лежит в точке его верхней кромки.
  const heads = []
  const y0 = window.scrollY
  for (const h of document.querySelectorAll('h1, h2')) {
    h.scrollIntoView({ block: 'start', behavior: 'instant' })
    const r = h.getBoundingClientRect()
    // Точка чуть внутри кромки: ровно на границе браузер может
    // вернуть соседа из-за округления.
    const x = Math.min(window.innerWidth - 2, Math.max(2, r.left + 6))
    const y = Math.min(window.innerHeight - 2, Math.max(2, r.top + 2))
    const at = document.elementFromPoint(x, y)
    const ok = !!at && (at === h || h.contains(at) || at.contains(h))
    heads.push({
      text: (h.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 42),
      top: Math.round(r.top),
      hit: at ? at.tagName.toLowerCase() + '.' + String(at.className).slice(0, 26) : 'null',
      ok,
      underHeader: !!at && !!at.closest && !!at.closest('.rz-header'),
    })
  }
  window.scrollTo(0, y0)

  return {
    headerH: Math.round(headerH * 10) / 10,
    varH: getComputedStyle(doc).getPropertyValue('--rz-header-h').trim(),
    padTop: Math.round(padTop * 10) / 10,
    scrollPad: getComputedStyle(doc).scrollPaddingTop,
    offsetTops: [...new Set(vis.map((k) => k.offsetTop))],
    rows: rows.length,
    rowItems: rows.map((rr) => rr.items.join('+')),
    children: vis.map((k) => String(k.className).split(' ')[0] + ':' + Math.round(k.getBoundingClientRect().width)),
    sw: doc.scrollWidth,
    cw: doc.clientWidth,
    heads,
  }
}

const browser = await chromium.launch({ channel: 'chrome', headless: true })
let failed = 0

for (const width of widths) {
  const ctx = await browser.newContext({ viewport: { width, height: 900 }, deviceScaleFactor: 1 })
  const page = await ctx.newPage()
  await page.goto('http://ra-zil.test' + url, { waitUntil: 'networkidle', timeout: 45000 })
  if (zoom !== 100) {
    await page.addStyleTag({ content: 'html { font-size: ' + zoom + '% !important; }' })
    await page.waitForTimeout(250)
  }
  const r = await page.evaluate(probe)

  const wrapped = r.rows > 1
  const padOk = Math.abs(r.headerH - r.padTop) < 1.5
  const hscroll = r.sw > r.cw + 1
  const covered = r.heads.filter((h) => !h.ok && h.underHeader)
  const bad = (zoom === 100 && width >= 1050 && wrapped) || !padOk || hscroll || covered.length
  if (bad) failed++

  console.log(
    String(width).padStart(5) + 'px z' + zoom + '  ' +
    'строк:' + r.rows + (wrapped ? ' (' + r.rowItems.join(' | ') + ')' : '') +
    '  высота:' + r.headerH + '  отступ:' + r.padTop + (padOk ? ' =' : ' РАСХОЖДЕНИЕ') +
    '  прокрутка:' + r.sw + '/' + r.cw + (hscroll ? ' ГОРИЗ!' : '') +
    '  заголовков под шапкой:' + covered.length +
    (bad ? '   << УПАЛ' : '   ок')
  )
  console.log('        offsetTop детей: ' + JSON.stringify(r.offsetTops) + '   ширины: ' + r.children.join('  '))
  if (covered.length) {
    for (const h of covered) console.log('        ПЕРЕКРЫТ: «' + h.text + '» top=' + h.top + ' в точке ' + h.hit)
  }
  await ctx.close()
}

await browser.close()
console.log('\n--- срезов с замечаниями: ' + failed + ' из ' + widths.length)
process.exit(failed ? 1 : 0)
