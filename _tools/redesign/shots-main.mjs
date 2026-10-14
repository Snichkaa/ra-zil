/**
 * Снимки главной страницы целиком на пяти ширинах.
 *
 * Снимок полной страницы, а не первого экрана: поломки вроде съехавшей
 * сетки или висящей в воздухе колонки видны ниже сгиба, и обрезанный
 * кадр их не показывает.
 *
 * Имена файлов латиницей: кириллица в именах приводит к тому,
 * что разные варианты пишутся в один файл.
 *
 * Запуск:
 *   node _tools/redesign/shots-main.mjs --out <каталог>
 *        [--widths 375,768,1024,1366,1920] [--url /] [--zoom 100]
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

const out = arg('out', null)
if (!out) {
  console.error('Нужен --out <каталог>')
  process.exit(2)
}
const widths = arg('widths', '375,768,1024,1366,1920').split(',').map(Number)
const url = arg('url', '/')
const zoom = Number(arg('zoom', '100'))

mkdirSync(out, { recursive: true })

const browser = await chromium.launch({ channel: 'chrome', headless: true })

for (const width of widths) {
  const ctx = await browser.newContext({ viewport: { width, height: 900 }, deviceScaleFactor: 1 })
  const page = await ctx.newPage()
  await page.goto('http://ra-zil.test' + url, { waitUntil: 'networkidle', timeout: 45000 })

  if (zoom !== 100) {
    await page.addStyleTag({ content: 'html { font-size: ' + zoom + '% !important; }' })
    await page.waitForTimeout(250)
  }

  // Ленивые картинки за кадром не загрузятся, и на полном снимке
  // окажутся пустые прямоугольники. Прокручиваем страницу до конца
  // и обратно, чтобы браузер их дотянул.
  await page.evaluate(async () => {
    const step = window.innerHeight
    for (let y = 0; y < document.documentElement.scrollHeight; y += step) {
      window.scrollTo(0, y)
      await new Promise((r) => setTimeout(r, 90))
    }
    window.scrollTo(0, 0)
  })
  await page.waitForTimeout(400)

  const name = 'home-' + width + 'w' + (zoom !== 100 ? '-zoom' + zoom : '') + '.png'
  await page.screenshot({ path: join(out, name), fullPage: true })

  const h = await page.evaluate(() => document.documentElement.scrollHeight)
  console.log(String(width).padStart(5) + 'px  высота страницы ' + h + 'px  -> ' + name)
  await ctx.close()
}

await browser.close()
console.log('--- Снимки в ' + out)
