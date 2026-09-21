/**
 * Просвечивает ли содержимое страницы сквозь фон шапки.
 *
 * Контраст собственного текста шапки этого не ловит: он может быть
 * прекрасным, а под ним всё равно будут читаться буквы страницы.
 * Поэтому меряется другое — разброс яркости самой подложки.
 *
 * Содержимое шапки скрывается, страница прокручивается так, чтобы под
 * шапку попал самый контрастный кусок (крупный тёмный заголовок на
 * светлом фоне), и снимается полоса шапки. У непрозрачной подложки
 * разброс яркости близок к нулю. Чем он больше, тем отчётливее сквозь
 * шапку виден текст.
 *
 * Запуск:
 *   node _tools/redesign/check-header-bg.mjs [--widths 375,1366,1920]
 *        [--opacity 86]   подменить непрозрачность для сравнения
 */

import { join } from 'node:path'
import { createRequire } from 'node:module'
import { writeFileSync, mkdirSync } from 'node:fs'
import zlib from 'node:zlib'

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

const widths = arg('widths', '375,1366,1920').split(',').map(Number)
const opacity = arg('opacity', '')
const outDir = arg('out', '')
const AT = arg('at', 'heading')

/** Разбор PNG без внешних библиотек. */
function readPng(buf) {
  let pos = 8
  let idat = Buffer.alloc(0)
  let w = 0, h = 0, ch = 3
  while (pos < buf.length) {
    const len = buf.readUInt32BE(pos)
    const type = buf.toString('ascii', pos + 4, pos + 8)
    const data = buf.subarray(pos + 8, pos + 8 + len)
    if (type === 'IHDR') {
      w = data.readUInt32BE(0); h = data.readUInt32BE(4)
      ch = data[9] === 6 ? 4 : 3
    } else if (type === 'IDAT') {
      idat = Buffer.concat([idat, data])
    } else if (type === 'IEND') break
    pos += 12 + len
  }
  const raw = zlib.inflateSync(idat)
  const stride = w * ch
  const rows = []
  let prev = Buffer.alloc(stride)
  let i = 0
  for (let y = 0; y < h; y++) {
    const f = raw[i]; i++
    const line = Buffer.from(raw.subarray(i, i + stride)); i += stride
    if (f === 1) for (let x = ch; x < stride; x++) line[x] = (line[x] + line[x - ch]) & 255
    else if (f === 2) for (let x = 0; x < stride; x++) line[x] = (line[x] + prev[x]) & 255
    else if (f === 3) for (let x = 0; x < stride; x++) { const a = x >= ch ? line[x - ch] : 0; line[x] = (line[x] + ((a + prev[x]) >> 1)) & 255 }
    else if (f === 4) for (let x = 0; x < stride; x++) {
      const a = x >= ch ? line[x - ch] : 0, b = prev[x], cc = x >= ch ? prev[x - ch] : 0
      const pp = a + b - cc, pa = Math.abs(pp - a), pb = Math.abs(pp - b), pc = Math.abs(pp - cc)
      const pr = (pa <= pb && pa <= pc) ? a : (pb <= pc ? b : cc)
      line[x] = (line[x] + pr) & 255
    }
    rows.push(line); prev = line
  }
  return { w, h, ch, rows }
}

const srgb = (c) => { c /= 255; return c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4) }

const browser = await chromium.launch({ channel: 'chrome', headless: true })
if (outDir) mkdirSync(outDir, { recursive: true })
let worst = 0

for (const width of widths) {
  const ctx = await browser.newContext({ viewport: { width, height: 900 }, deviceScaleFactor: 1 })
  const page = await ctx.newPage()
  await page.goto('http://ra-zil.test/', { waitUntil: 'networkidle', timeout: 45000 })

  let css = '.rz-header__inner > * { visibility: hidden !important; }'
  if (opacity) {
    css += '@supports (backdrop-filter: blur(1px)) { .rz-header { background-color: ' +
      'color-mix(in srgb, var(--rz-surface) ' + opacity + '%, transparent) !important; } }'
  }
  await page.addStyleTag({ content: css })

  // Под шапку подводится самый контрастный кусок страницы —
  // крупный тёмный заголовок раздела на светлом фоне.
  //
  // После прокрутки ВНИЗ обязателен короткий ход ВВЕРХ. На узких
  // экранах скрипт прячет шапку при движении вниз, и без этого хода
  // замер попадал бы не на подложку шапки, а на голую страницу:
  // на 375 px он давал разброс 12.42:1, который не менялся
  // от непрозрачности — верный признак, что мерили не то.
  await page.evaluate((where) => {
    if (where === 'hero') {
      // Панорама офиса: самый пёстрый кусок страницы — светлое небо
      // рядом с тёмным кирпичом. Если сквозь шапку что-то видно,
      // видно будет здесь.
      const hero = document.querySelector('.rz-hero')
      const y = hero.getBoundingClientRect().top + window.scrollY
      window.scrollTo(0, y + Math.round(hero.getBoundingClientRect().height / 2))
      return
    }
    const h2 = document.querySelector('h2')
    const y = h2.getBoundingClientRect().top + window.scrollY
    window.scrollTo(0, y - 16)
  }, AT)
  await page.waitForTimeout(350)
  await page.evaluate(() => window.scrollBy(0, -8))
  await page.waitForTimeout(450)

  const hidden = await page.evaluate(() =>
    document.querySelector('.rz-header').classList.contains('rz-header--hidden'))
  if (hidden) {
    console.log(String(width).padStart(5) + 'px  шапка спрятана скриптом, замер невозможен')
    await ctx.close()
    continue
  }

  const hh = await page.evaluate(() => Math.round(document.querySelector('.rz-header').getBoundingClientRect().height))
  const shot = await page.screenshot({ clip: { x: 0, y: 0, width, height: Math.max(8, hh - 2) } })
  if (outDir) writeFileSync(join(outDir, 'hdrbg-' + width + (opacity ? '-op' + opacity : '') + '.png'), shot)

  const { w, h, ch, rows } = readPng(shot)
  let min = 1, max = 0
  for (let y = 2; y < h - 2; y += 2) {
    for (let x = 2; x < w - 2; x += 2) {
      const o = x * ch
      const L = 0.2126 * srgb(rows[y][o]) + 0.7152 * srgb(rows[y][o + 1]) + 0.0722 * srgb(rows[y][o + 2])
      if (L < min) min = L
      if (L > max) max = L
    }
  }
  // Отношение самой светлой точки подложки к самой тёмной — в той же
  // шкале, что и контраст текста, чтобы число было сопоставимо.
  const spread = (max + 0.05) / (min + 0.05)
  if (spread > worst) worst = spread
  console.log(
    String(width).padStart(5) + 'px  высота полосы ' + h +
    '  ['+AT+'] разброс яркости подложки ' + spread.toFixed(2) + ':1' +
    (spread < 1.15 ? '  ровная' : spread < 1.4 ? '  заметен' : '  СКВОЗИТ')
  )
  await ctx.close()
}

await browser.close()
console.log('\n--- худший разброс: ' + worst.toFixed(2) + ':1 (ровная подложка — меньше 1.15)')
process.exit(worst >= 1.15 ? 1 : 0)
