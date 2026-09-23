/**
 * Прототип без правки рабочего дерева.
 *
 * Зачем. Предложение по дизайну надо показать, а не описать словами,
 * но показывать его правкой файлов темы нельзя: пока предложение
 * не принято, рабочее дерево должно оставаться чистым. Поэтому
 * таблица стилей подменяется на лету — перехватом запроса.
 *
 * Браузер запрашивает main.css, обработчик берёт настоящий ответ,
 * прогоняет его через заплатку и отдаёт изменённый. На диске
 * не меняется ничего; `git status` после прогона чист.
 *
 * Заплатка — файл одного из двух видов:
 *
 *   *.css  — дописывается в конец таблицы стилей;
 *   *.mjs  — модуль с `export default (css) => css`, то есть может
 *            не только дописать, но и вырезать или заменить правило.
 *
 * Запуск:
 *   node _tools/redesign/proto.mjs --patch <файл> --url /uslugi/krematsiya/
 *        --out <каталог> --name <имя> [--widths 1366,375] [--zoom 100]
 *        [--full]   снимок всей страницы, а не первого экрана
 *        [--sel .rz-sec]  снимок одного блока
 */

import { mkdirSync, readFileSync } from 'node:fs'
import { join, resolve, extname } from 'node:path'
import { pathToFileURL } from 'node:url'
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

const patchPath = arg('patch', null)
const url = arg('url', '/')
const out = arg('out', null)
const name = arg('name', 'proto')
const widths = arg('widths', '1366,375').split(',').map(Number)
const zoom = Number(arg('zoom', '100'))
const sel = arg('sel', '')
const full = process.argv.includes('--full')

if (!patchPath || !out) {
  console.error('Нужны --patch <файл> и --out <каталог>')
  process.exit(2)
}

// Заплатка читается один раз: она не должна зависеть от того,
// сколько раз браузер попросит таблицу стилей.
let применить
const ext = extname(patchPath).toLowerCase()
if (ext === '.css') {
  const add = readFileSync(resolve(patchPath), 'utf8')
  применить = (css) => css + '\n\n/* --- прототип ' + name + ' --- */\n' + add
} else {
  const mod = await import(pathToFileURL(resolve(patchPath)).href)
  применить = mod.default
  if (typeof применить !== 'function') {
    console.error('Модуль заплатки должен экспортировать функцию по умолчанию')
    process.exit(2)
  }
}

mkdirSync(out, { recursive: true })

const browser = await chromium.launch({ channel: 'chrome', headless: true })

for (const width of widths) {
  const ctx = await browser.newContext({ viewport: { width, height: 900 }, deviceScaleFactor: 1 })

  let подменено = 0
  await ctx.route('**/assets/css/main.css*', async (route) => {
    const resp = await route.fetch()
    const css = await resp.text()
    подменено++
    await route.fulfill({
      response: resp,
      body: применить(css),
      headers: { ...resp.headers(), 'content-type': 'text/css; charset=utf-8' },
    })
  })

  const page = await ctx.newPage()
  await page.goto('http://ra-zil.test' + url, { waitUntil: 'networkidle', timeout: 45000 })

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

  const file = join(out, name + '-' + width + (zoom !== 100 ? '-z' + zoom : '') + '.png')
  if (sel) {
    const el = await page.$(sel)
    if (!el) {
      console.log(String(width).padStart(5) + 'px  селектор не найден: ' + sel)
      await ctx.close()
      continue
    }
    await el.screenshot({ path: file })
  } else {
    await page.screenshot({ path: file, fullPage: full })
  }

  const h = await page.evaluate(() => document.documentElement.scrollHeight)
  console.log(
    String(width).padStart(5) + 'px  подмен стиля: ' + подменено +
    '  высота страницы ' + h + '  -> ' + file
  )
  await ctx.close()
}

await browser.close()
console.log('--- прототип ' + name + ' в ' + out + '; рабочее дерево не трогалось')
