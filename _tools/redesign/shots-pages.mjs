/**
 * Снимки нескольких страниц целиком на нескольких ширинах.
 *
 * shots-main.mjs умеет только главную: имя файла в нём задано словом
 * home. Здесь страниц много, и каждая получает своё имя по ключу,
 * поэтому снимки не затирают друг друга.
 *
 * Имена файлов только латиницей: кириллица в именах приводит к тому,
 * что разные варианты пишутся в один файл.
 *
 * Снимок полной страницы, а не первого экрана: съехавшая сетка или
 * висящая в воздухе колонка видны ниже сгиба. Перед снимком страница
 * прокручивается до конца и обратно, иначе ленивые картинки остаются
 * пустыми прямоугольниками.
 *
 * Запуск:
 *   node _tools/redesign/shots-pages.mjs --out <каталог>
 *        [--widths 375,768,1024,1366,1920] [--zoom 100]
 *        [--only home,svc-krematsiya]
 */

import { mkdirSync } from 'node:fs'
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

// Набор по умолчанию — главная, каталог услуг и пять услуг: ровно те
// страницы, которых касаются правки услуг.
const PAGES = [
  ['home', '/'],
  ['uslugi', '/uslugi/'],
  ['svc-organizatsiya', '/uslugi/organizatsiya-pohoron/'],
  ['svc-krematsiya', '/uslugi/krematsiya/'],
  ['svc-transportirovka', '/uslugi/transportirovka/'],
  ['svc-blagoustroystvo', '/uslugi/blagoustroystvo/'],
  ['svc-yurpomoshch', '/uslugi/yuridicheskaya-pomoshch/'],
]

const out = arg('out', null)
if (!out) {
  console.error('Нужен --out <каталог>')
  process.exit(2)
}
const widths = arg('widths', '375,768,1024,1366,1920').split(',').map(Number)
const zoom = Number(arg('zoom', '100'))
const only = arg('only', '')
const pages = only ? PAGES.filter(([k]) => only.split(',').includes(k)) : PAGES

mkdirSync(out, { recursive: true })

const browser = await chromium.launch({ channel: 'chrome', headless: true })

for (const [key, url] of pages) {
  for (const width of widths) {
    const ctx = await browser.newContext({ viewport: { width, height: 900 }, deviceScaleFactor: 1 })
    const page = await ctx.newPage()
    const resp = await page.goto('http://ra-zil.test' + url, { waitUntil: 'networkidle', timeout: 45000 })

    if (zoom !== 100) {
      await page.addStyleTag({ content: 'html { font-size: ' + zoom + '% !important; }' })
      await page.waitForTimeout(250)
    }

    await page.evaluate(async () => {
      const step = window.innerHeight
      for (let y = 0; y < document.documentElement.scrollHeight; y += step) {
        window.scrollTo(0, y)
        await new Promise((r) => setTimeout(r, 90))
      }
      window.scrollTo(0, 0)
    })
    await page.waitForTimeout(400)

    const name = key + '-' + width + 'w' + (zoom !== 100 ? '-zoom' + zoom : '') + '.png'
    await page.screenshot({ path: join(out, name), fullPage: true })

    const h = await page.evaluate(() => document.documentElement.scrollHeight)
    console.log(
      key.padEnd(20) + String(width).padStart(5) + 'px  HTTP ' + (resp ? resp.status() : '?') +
      '  высота ' + h + 'px  -> ' + name
    )
    await ctx.close()
  }
}

await browser.close()
console.log('--- Снимки в ' + out)
