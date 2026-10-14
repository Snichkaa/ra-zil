/**
 * Проверка страниц в настоящем браузере.
 *
 * На каждой ширине из списка страница открывается, снимается экран и
 * собираются: ошибки консоли, необработанные исключения, неудачные
 * запросы, горизонтальная прокрутка, размеры кликабельных областей,
 * основной размер шрифта и число заголовков H1.
 *
 * Браузер берётся системный (канал chrome): загружать отдельный
 * комплект Playwright ради тех же страниц незачем.
 *
 * Запуск:
 *   node _tools/redesign/check-pages.mjs --out <каталог> [--tag имя]
 *                                        [--widths 375,768,1024,1366,1920]
 *                                        [--only slug,slug]
 *
 * Имена файлов снимков — только латиница: кириллица в именах приводит
 * к тому, что разные варианты пишутся в один файл.
 */

import { mkdirSync, writeFileSync } from 'node:fs'
import { join } from 'node:path'
import { createRequire } from 'node:module'

// Playwright ставится вне репозитория, чтобы не тащить его в историю.
// NODE_PATH модули ECMAScript не читают, поэтому пакет ищется через
// require с явной точкой отсчёта из переменной окружения.
const PW_DIR = process.env.RZ_PW_DIR
if (!PW_DIR) {
  console.error('Нужна переменная окружения RZ_PW_DIR — каталог с установленным playwright-core')
  process.exit(2)
}
const { chromium } = createRequire(join(PW_DIR, 'noop.js'))('playwright-core')

const BASE = 'http://ra-zil.test'

// Латинские ключи — это и есть имена файлов снимков.
const PAGES = [
  { key: 'home', url: '/', title: 'Главная' },
  { key: 'svc-index', url: '/uslugi/', title: 'Каталог услуг' },
  { key: 'svc-blagoustroystvo', url: '/uslugi/blagoustroystvo/', title: 'Благоустройство мест захоронения' },
  { key: 'svc-organizatsiya', url: '/uslugi/organizatsiya-pohoron/', title: 'Организация церемонии' },
  { key: 'svc-krematsiya', url: '/uslugi/krematsiya/', title: 'Кремация' },
  { key: 'svc-transportirovka', url: '/uslugi/transportirovka/', title: 'Перевозка' },
  { key: 'svc-yurpomoshch', url: '/uslugi/yuridicheskaya-pomoshch/', title: 'Помощь с документами' },
  { key: 'kontakty', url: '/kontakty/', title: 'Контакты' },
  { key: 'otzyvy', url: '/otzyvy/', title: 'Отзывы' },
  { key: 'o-kompanii', url: '/o-kompanii/', title: 'О компании' },
  { key: 'rekvizity', url: '/rekvizity/', title: 'Реквизиты' },
  { key: 'katalog', url: '/katalog/', title: 'Каталог' },
  { key: 'nashi-raboty', url: '/nashi-raboty/', title: 'Наши работы' },
  { key: 'vopros-otvet', url: '/vopros-otvet/', title: 'Вопрос-ответ' },
  { key: 'stati', url: '/stati/', title: 'Статьи' },
  { key: 'politika', url: '/politika/', title: 'Политика обработки данных' },
  { key: 'soglasie', url: '/soglasie/', title: 'Согласие на обработку' },
  { key: 'polzovatelskoe', url: '/polzovatelskoe-soglashenie/', title: 'Пользовательское соглашение' },
  { key: 'spasibo', url: '/spasibo/', title: 'Заявка принята' },
]

function arg(name, fallback) {
  const i = process.argv.indexOf('--' + name)
  return i >= 0 && process.argv[i + 1] ? process.argv[i + 1] : fallback
}

const outDir = arg('out', null)
if (!outDir) {
  console.error('Нужен --out <каталог>')
  process.exit(2)
}
const tag = arg('tag', '')
const widths = arg('widths', '375,768,1024,1366,1920').split(',').map(Number)
const only = arg('only', '')
const pages = only ? PAGES.filter((p) => only.split(',').includes(p.key)) : PAGES

mkdirSync(outDir, { recursive: true })

/** Измерения внутри страницы. Возвращает то, что нельзя увидеть снаружи. */
const probe = () => {
  const doc = document.documentElement
  const body = document.body

  const bodyFont = getComputedStyle(body).fontSize

  // Кликабельные области меньше 44 пикселей по высоте. Скрытые
  // элементы пропускаются: у них нулевая высота по определению,
  // и они засорили бы список.
  const small = []
  for (const el of document.querySelectorAll('a[href], button, summary, input, select, textarea, [role="button"]')) {
    const r = el.getBoundingClientRect()
    if (r.width === 0 && r.height === 0) continue
    const cs = getComputedStyle(el)
    if (cs.display === 'none' || cs.visibility === 'hidden') continue
    if (r.height < 44) {
      small.push({
        tag: el.tagName.toLowerCase(),
        cls: (el.getAttribute('class') || '').slice(0, 80),
        text: (el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 40),
        h: Math.round(r.height * 10) / 10,
        w: Math.round(r.width * 10) / 10,
      })
    }
  }

  // Элементы шире окна — источник горизонтальной прокрутки.
  const overflowing = []
  if (doc.scrollWidth > doc.clientWidth + 1) {
    for (const el of document.querySelectorAll('body *')) {
      const r = el.getBoundingClientRect()
      if (r.right > doc.clientWidth + 1 || r.left < -1) {
        overflowing.push({
          tag: el.tagName.toLowerCase(),
          cls: (el.getAttribute('class') || '').slice(0, 80),
          left: Math.round(r.left),
          right: Math.round(r.right),
        })
      }
      if (overflowing.length >= 15) break
    }
  }

  const heads = [...document.querySelectorAll('h1,h2,h3,h4,h5,h6')].map((h) => ({
    lvl: Number(h.tagName[1]),
    text: (h.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 70),
  }))

  const tels = [...document.querySelectorAll('a[href^="tel:"]')].map((a) => a.getAttribute('href'))

  const imgs = [...document.querySelectorAll('img')].map((i) => ({
    src: (i.getAttribute('src') || '').split('/').pop(),
    alt: i.getAttribute('alt'),
    w: i.getAttribute('width'),
    h: i.getAttribute('height'),
    loading: i.getAttribute('loading'),
    fetchpriority: i.getAttribute('fetchpriority'),
    natural: i.naturalWidth + 'x' + i.naturalHeight,
  }))

  return {
    scrollWidth: doc.scrollWidth,
    clientWidth: doc.clientWidth,
    hasHScroll: doc.scrollWidth > doc.clientWidth + 1,
    bodyFont,
    smallTargets: small,
    overflowing,
    h1count: heads.filter((h) => h.lvl === 1).length,
    heads,
    tels: [...new Set(tels)],
    imgs,
    title: document.title,
  }
}

const browser = await chromium.launch({ channel: 'chrome', headless: true })
const report = []

for (const page of pages) {
  for (const width of widths) {
    const ctx = await browser.newContext({
      viewport: { width, height: Math.round(width * 0.75) },
      deviceScaleFactor: 1,
    })
    const tab = await ctx.newPage()

    const consoleErrors = []
    const pageErrors = []
    const failed = []

    tab.on('console', (m) => {
      if (m.type() === 'error' || m.type() === 'warning') {
        consoleErrors.push(m.type() + ': ' + m.text().slice(0, 300))
      }
    })
    tab.on('pageerror', (e) => pageErrors.push(String(e).slice(0, 300)))
    tab.on('requestfailed', (r) => failed.push(r.url().slice(0, 200) + ' — ' + (r.failure()?.errorText || '')))

    let status = 0
    let probed = null
    let error = null

    try {
      const resp = await tab.goto(BASE + page.url, { waitUntil: 'networkidle', timeout: 45000 })
      status = resp ? resp.status() : 0
      probed = await tab.evaluate(probe)

      const name = [page.key, width + 'w', tag].filter(Boolean).join('-') + '.png'
      await tab.screenshot({ path: join(outDir, name), fullPage: true })
    } catch (e) {
      error = String(e).slice(0, 400)
    }

    report.push({ key: page.key, url: page.url, title: page.title, width, status, error, consoleErrors, pageErrors, failed, ...probed })

    const flag = error
      ? 'ОШИБКА'
      : status !== 200
        ? 'HTTP ' + status
        : probed.hasHScroll
          ? 'ГОРИЗ.ПРОКРУТКА'
          : consoleErrors.length || pageErrors.length
            ? 'КОНСОЛЬ ' + (consoleErrors.length + pageErrors.length)
            : 'ок'

    console.log(
      [
        page.key.padEnd(22),
        String(width).padStart(5) + 'px',
        flag.padEnd(16),
        'h1=' + (probed ? probed.h1count : '?'),
        'шрифт=' + (probed ? probed.bodyFont : '?'),
        'мелких целей=' + (probed ? probed.smallTargets.length : '?'),
      ].join('  ')
    )

    await ctx.close()
  }
}

await browser.close()

writeFileSync(join(outDir, 'report' + (tag ? '-' + tag : '') + '.json'), JSON.stringify(report, null, 2), 'utf8')
console.log('\n--- Снимки и report.json в ' + outDir)

const bad = report.filter((r) => r.error || r.status !== 200 || r.hasHScroll || r.pageErrors.length)
console.log('--- Проблемных срезов: ' + bad.length + ' из ' + report.length)
process.exit(bad.length ? 1 : 0)
