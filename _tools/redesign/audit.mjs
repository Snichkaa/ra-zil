/**
 * Итоговая проверка сайта: адаптив, доступность, изображения.
 *
 * Обходит каждую страницу на каждой ширине настоящим браузером
 * и собирает то, что нельзя проверить чтением файлов:
 *
 *   - горизонтальная прокрутка и кто её создаёт;
 *   - размер основного текста;
 *   - число H1 и пропуски уровней заголовков;
 *   - ссылки tel: и их номера;
 *   - изображения: заданы ли размеры, ленивая загрузка, приоритет;
 *   - декоративные SVG без aria-hidden;
 *   - зоны нажатия меньше 44 px, с ПРОБОЙ ПОПАДАНИЯ, а не по рамке:
 *     у части ссылок зона расширена невидимым ::after, и рамка
 *     показывала бы промах там, где его нет;
 *   - перекрывает ли висящая кнопка звонка подвал и последний вопрос;
 *   - контраст текста к фактическому фону под ним.
 *
 * Запуск:
 *   node _tools/redesign/audit.mjs --out <файл.json>
 *        [--widths 375,768,1024,1366,1920] [--zoom 200] [--only slug,...]
 *        [--dark]
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

// Телефоны агентства. Любой другой номер в ссылке tel: — ошибка.
const PHONES = new Set([
  'tel:+74212605290', 'tel:+74212214664', 'tel:+79621514257',
  'tel:112', 'tel:103', 'tel:102', 'tel:03', 'tel:02',
])

function arg(name, fallback) {
  const i = process.argv.indexOf('--' + name)
  return i >= 0 && process.argv[i + 1] ? process.argv[i + 1] : fallback
}

const out = arg('out', null)
if (!out) {
  console.error('Нужен --out <файл.json>')
  process.exit(2)
}
const widths = arg('widths', '375,768,1024,1366,1920').split(',').map(Number)
const zoom = Number(arg('zoom', '100'))
const only = arg('only', '')
// Тёмная тема на сайте включается медиавыражением, поэтому
// эмулируется схемой цвета контекста, а не классом на теле.
const dark = process.argv.includes('--dark')
const pages = only ? PAGES.filter(([k]) => only.split(',').includes(k)) : PAGES

/** Всё, что собирается внутри страницы. */
const probe = (allowedPhones) => {
  const doc = document.documentElement

  // ---- цвет и контраст
  const parse = (v) => {
    const m = String(v).match(/[\d.]+/g)
    if (!m) return null
    const [r, g, b] = m.map(Number)
    const a = m.length > 3 ? Number(m[3]) : 1
    return { r, g, b, a }
  }
  const lin = (c) => { c /= 255; return c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4) }
  const lum = (c) => 0.2126 * lin(c.r) + 0.7152 * lin(c.g) + 0.0722 * lin(c.b)
  const ratio = (a, b) => { const l1 = lum(a), l2 = lum(b); const hi = Math.max(l1, l2), lo = Math.min(l1, l2); return (hi + 0.05) / (lo + 0.05) }
  // Фон под элементом: первый непрозрачный фон вверх по дереву.
  const bgOf = (el) => {
    let cur = el
    while (cur) {
      const c = parse(getComputedStyle(cur).backgroundColor)
      if (c && c.a >= 0.95) return c
      cur = cur.parentElement
    }
    return { r: 255, g: 255, b: 255, a: 1 }
  }

  const contrast = []
  const textSel = 'p, li, h1, h2, h3, h4, h5, h6, a, span, strong, em, td, th, summary, figcaption, label, button'
  for (const el of document.querySelectorAll(textSel)) {
    // Только элементы с собственным текстом, видимые и непустые.
    const own = [...el.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim().length > 1)
    if (!own) continue
    const cs = getComputedStyle(el)
    if (cs.display === 'none' || cs.visibility === 'hidden' || Number(cs.opacity) < 0.1) continue
    // Текст только для скринридеров спрятан обрезкой, а не display:
    // он остаётся в дереве доступности, но на экране его нет,
    // и требования к контрасту к нему не применяются.
    if (/inset\(50%/.test(cs.clipPath) || el.closest('.screen-reader-text')) continue
    const r = el.getBoundingClientRect()
    if (r.width < 1 || r.height < 1) continue
    const fg = parse(cs.color)
    if (!fg || fg.a < 0.95) continue
    const bg = bgOf(el)
    const size = parseFloat(cs.fontSize)
    const bold = Number(cs.fontWeight) >= 700
    const large = size >= 24 || (size >= 18.66 && bold)
    const cr = ratio(fg, bg)
    const need = large ? 3 : 4.5
    if (cr < need) {
      contrast.push({
        tag: el.tagName.toLowerCase(),
        cls: String(el.className).slice(0, 50),
        text: (el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 45),
        size: Math.round(size * 10) / 10, bold, cr: Math.round(cr * 100) / 100, need,
      })
    }
  }

  // ---- размеры текста
  //
  // §17.4 задаёт разные полы для разных ролей: основной текст 16-18,
  // подпись и метаданные 14-15, метка над заголовком 12-13. Поэтому
  // один общий минимум ничего не значит — считаем два.
  //
  // Меткой считается короткий текст в верхнем регистре с разрядкой
  // либо элемент с классом метки; подписью — всё, что помечено ролью
  // подписи. Остальное — основной текст.
  const LABEL = /rz-eyebrow|rz-photo-slot__num|wp-block-table/
  const CAPTION = /caption|__date|__source|__counter|__note|__hint|breadcrumb|footer|label/i
  let minBody = 999, minBodyWhat = ''
  let minMeta = 999, minMetaWhat = ''
  for (const el of document.querySelectorAll('p, li, td, th, summary, figcaption, label, a, span')) {
    const own = [...el.childNodes].some((n) => n.nodeType === 3 && n.textContent.trim().length > 1)
    if (!own) continue
    const cs = getComputedStyle(el)
    if (cs.display === 'none' || cs.visibility === 'hidden') continue
    if (/inset\(50%/.test(cs.clipPath) || el.closest('.screen-reader-text')) continue
    const size = parseFloat(cs.fontSize)
    const cls = String(el.className)
    const isLabel = LABEL.test(cls) || cs.textTransform === 'uppercase' ||
      el.tagName === 'TH' || el.closest('.rz-eyebrow, .rz-photo-slot')
    const isMeta = !isLabel && (CAPTION.test(cls) || !!el.closest('figcaption, .rz-breadcrumbs, .rz-footer, .rz-review__meta, .rz-reviews__nav'))
    if (isLabel) continue
    if (isMeta) {
      if (size < minMeta) { minMeta = size; minMetaWhat = el.tagName.toLowerCase() + '.' + cls.slice(0, 40) }
    } else if (size < minBody) {
      minBody = size; minBodyWhat = el.tagName.toLowerCase() + '.' + cls.slice(0, 40)
    }
  }

  // ---- горизонтальная прокрутка
  const overflowing = []
  const hasH = doc.scrollWidth > doc.clientWidth + 1
  if (hasH) {
    // Элемент внутри горизонтально прокручиваемого предка за край
    // документа не выходит: его обрезает этот предок. Так устроена
    // карусель отзывов — соседние карточки лежат правее нарочно.
    const inScroller = (el) => {
      let cur = el.parentElement
      while (cur && cur !== document.body) {
        const ov = getComputedStyle(cur).overflowX
        if (ov === 'auto' || ov === 'scroll' || ov === 'hidden') return true
        cur = cur.parentElement
      }
      return false
    }
    for (const el of document.querySelectorAll('body *')) {
      const r = el.getBoundingClientRect()
      if (r.width === 0 || r.height === 0) continue
      if (inScroller(el)) continue
      if (r.right > doc.clientWidth + 1 || r.left < -1) {
        overflowing.push({ tag: el.tagName.toLowerCase(), cls: String(el.className).slice(0, 60), left: Math.round(r.left), right: Math.round(r.right) })
        if (overflowing.length >= 12) break
      }
    }
  }

  // ---- заголовки
  const heads = [...document.querySelectorAll('h1,h2,h3,h4,h5,h6')]
    .filter((h) => getComputedStyle(h).display !== 'none')
    .map((h) => Number(h.tagName[1]))
  let skip = null
  for (let i = 1; i < heads.length; i++) {
    if (heads[i] - heads[i - 1] > 1) { skip = 'h' + heads[i - 1] + ' -> h' + heads[i]; break }
  }

  // ---- телефоны
  const tels = [...document.querySelectorAll('a[href^="tel:"]')].map((a) => a.getAttribute('href'))
  const badTels = [...new Set(tels)].filter((t) => !allowedPhones.includes(t))

  // ---- изображения
  const imgs = []
  for (const i of document.querySelectorAll('img')) {
    const cs = getComputedStyle(i)
    const sized = (i.hasAttribute('width') && i.hasAttribute('height')) || cs.aspectRatio !== 'auto'
    imgs.push({
      file: (i.currentSrc || i.src || '').split('/').pop().slice(0, 44),
      sized, loading: i.getAttribute('loading') || '(нет)',
      fetchpriority: i.getAttribute('fetchpriority') || '(нет)',
      alt: i.getAttribute('alt'),
      top: Math.round(i.getBoundingClientRect().top + window.scrollY),
    })
  }

  // ---- декоративные SVG
  const svgBad = []
  for (const s of document.querySelectorAll('svg')) {
    if (s.getAttribute('aria-hidden') !== 'true' && !s.querySelector('title') && s.getAttribute('role') !== 'img') {
      svgBad.push(String(s.getAttribute('class') || s.parentElement?.className || '').slice(0, 50))
    }
  }

  // ---- зоны нажатия: пробой попадания, а не по рамке
  //
  // Меряется не рамка элемента, а то, куда фактически попадёт палец:
  // у части ссылок зона расширена невидимым ::after с отрицательным
  // inset, и рамка показывала бы промах там, где его нет.
  //
  // Каждый элемент перед пробой подводится к середине окна.
  // elementFromPoint работает в координатах окна, и без этого всё,
  // что ниже первого экрана, не проверялось бы вовсе — именно так
  // выглядел первый прогон: «probed: false» у двух третей ссылок.
  const small = []
  const cands = [...document.querySelectorAll('a[href], button, summary, input:not([type="hidden"]), select, textarea, [role="button"]')]
  const y0 = window.scrollY
  for (const el of cands) {
    const cs = getComputedStyle(el)
    if (cs.display === 'none' || cs.visibility === 'hidden') continue
    // Поле, спрятанное обрезкой, целью нажатия не является: нажимают
    // по его подписи. Так сделаны звёзды оценки — сам radio остаётся
    // в дереве доступности ради клавиатуры и скринридера,
    // а кликают по label.
    // Обрезка бывает и на обёртке, а не на самом поле: так спрятана
    // ловушка для ботов .rz-hp. Человек её не видит и не нажимает.
    if (/inset\(50%/.test(cs.clipPath) || el.closest('.rz-hp')) continue
    let r = el.getBoundingClientRect()
    if (r.width === 0 && r.height === 0) continue
    if (r.height >= 44) continue

    el.scrollIntoView({ block: 'center', behavior: 'instant' })
    r = el.getBoundingClientRect()
    const cx = (r.left + r.right) / 2, cy = (r.top + r.bottom) / 2
    if (cy < 1 || cy > window.innerHeight - 1) continue

    // У флажка и переключателя настоящая цель — подпись: нажатие по ней
    // переключает поле. Если подпись достаточно велика, мелкий квадрат
    // самого поля цель не ограничивает. Проверено нажатием: подпись
    // согласия 389x53 px переключает флажок.
    const labelled = el.tagName === 'INPUT' && /^(checkbox|radio)$/.test(el.type || '')
    if (labelled) {
      const lab = (el.id && document.querySelector('label[for="' + CSS.escape(el.id) + '"]')) || el.closest('label')
      if (lab && lab.getBoundingClientRect().height >= 44) continue
    }

    const hit = (x, y) => { const e = document.elementFromPoint(x, y); return !!e && (e === el || el.contains(e)) }
    const up = hit(cx, cy - 21), down = hit(cx, cy + 21)
    if (!up || !down) {
      small.push({
        tag: el.tagName.toLowerCase(), cls: String(el.className).slice(0, 44),
        text: (el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 34),
        h: Math.round(r.height), up, down,
        tap: cs.getPropertyValue('--rz-tap').trim() || '(не задана)',
      })
    }
  }
  window.scrollTo(0, y0)

  return {
    scrollWidth: doc.scrollWidth, clientWidth: doc.clientWidth, hasHScroll: hasH, overflowing,
    bodyFont: getComputedStyle(document.body).fontSize, minBody, minBodyWhat, minMeta, minMetaWhat,
    h1: heads.filter((h) => h === 1).length, headSkip: skip, heads: heads.length,
    tels: [...new Set(tels)], badTels,
    imgs, svgBad, smallTargets: small, contrast,
    docHeight: doc.scrollHeight,
  }
}

const browser = await chromium.launch({ channel: 'chrome', headless: true })
const report = []

for (const [key, url] of pages) {
  for (const width of widths) {
    const ctx = await browser.newContext({
      viewport: { width, height: 900 },
      deviceScaleFactor: 1,
      colorScheme: dark ? 'dark' : 'light',
    })
    const tab = await ctx.newPage()
    const consoleErrors = []
    const pageErrors = []
    tab.on('console', (m) => { if (m.type() === 'error') consoleErrors.push(m.text().slice(0, 200)) })
    tab.on('pageerror', (e) => pageErrors.push(String(e).slice(0, 200)))

    let rec = { key, url, width, zoom }
    try {
      const resp = await tab.goto(BASE + url, { waitUntil: 'networkidle', timeout: 45000 })
      rec.status = resp ? resp.status() : 0
      if (zoom !== 100) {
        // Увеличение текста браузером: тема задаёт html { font-size: 100% }
        // и меряет всё в rem, поэтому кратное увеличение корня — это
        // ровно то же, что делает настройка масштаба текста.
        await tab.addStyleTag({ content: 'html { font-size: ' + zoom + '% !important; }' })
        await tab.waitForTimeout(250)
      }
      Object.assign(rec, await tab.evaluate(probe, [...PHONES]))

      // Висящая кнопка звонка: не должна накрывать подвал и последний вопрос.
      await tab.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight))
      await tab.waitForTimeout(450)
      rec.sticky = await tab.evaluate(() => {
        const btn = document.querySelector('.rz-sticky-call')
        if (!btn || !btn.classList.contains('is-visible')) return { visible: false }
        const b = btn.getBoundingClientRect()
        const covered = []
        const check = (sel, name) => {
          for (const el of document.querySelectorAll(sel)) {
            const r = el.getBoundingClientRect()
            if (r.bottom < 0 || r.top > window.innerHeight) continue
            const ix = Math.max(0, Math.min(b.right, r.right) - Math.max(b.left, r.left))
            const iy = Math.max(0, Math.min(b.bottom, r.bottom) - Math.max(b.top, r.top))
            if (ix > 2 && iy > 2) covered.push(name + ': ' + (el.textContent || '').trim().slice(0, 40))
          }
        }
        check('.rz-footer a, .rz-footer p', 'подвал')
        check('.entry-content .wp-block-details:last-of-type summary', 'последний вопрос')
        check('.rz-cta a', 'призыв')
        return { visible: true, box: [Math.round(b.left), Math.round(b.top), Math.round(b.width), Math.round(b.height)], covered: [...new Set(covered)].slice(0, 6) }
      })
    } catch (e) {
      rec.error = String(e).slice(0, 300)
    }
    rec.consoleErrors = consoleErrors
    rec.pageErrors = pageErrors
    report.push(rec)

    const flags = []
    if (rec.error) flags.push('ОШИБКА')
    if (rec.status !== 200) flags.push('HTTP ' + rec.status)
    if (rec.hasHScroll) flags.push('ГОРИЗ.ПРОКРУТКА')
    if (rec.h1 !== 1) flags.push('H1=' + rec.h1)
    if (rec.headSkip) flags.push('ПРОПУСК ' + rec.headSkip)
    if (rec.minBody < 16) flags.push('ОСН.ТЕКСТ ' + rec.minBody)
    if (rec.minMeta < 14) flags.push('ПОДПИСЬ ' + rec.minMeta)
    if (rec.badTels && rec.badTels.length) flags.push('ЧУЖОЙ ТЕЛЕФОН')
    if (rec.svgBad && rec.svgBad.length) flags.push('SVG БЕЗ aria-hidden:' + rec.svgBad.length)
    if (rec.contrast && rec.contrast.length) flags.push('КОНТРАСТ:' + rec.contrast.length)
    // Зона, которой намеренно назначена своя высота через --rz-tap,
    // это задокументированное исключение (ссылки подвала, крошки,
    // номера служб на узком экране), а не недосмотр. В отказ идут
    // только цели, которым зону не задавали вовсе.
    const zonesNoTap = (rec.smallTargets || []).filter((t) => t.tap === '(не задана)')
    rec.zonesNoTap = zonesNoTap.length
    rec.zonesWithTap = (rec.smallTargets || []).length - zonesNoTap.length
    if (zonesNoTap.length) flags.push('ЗОНЫ БЕЗ ЦЕЛИ:' + zonesNoTap.length)
    if (rec.sticky && rec.sticky.covered && rec.sticky.covered.length) flags.push('КНОПКА НАКРЫВАЕТ:' + rec.sticky.covered.length)
    if (rec.pageErrors.length) flags.push('JS:' + rec.pageErrors.length)

    console.log(key.padEnd(22) + String(width).padStart(5) + 'px  ' + (flags.length ? flags.join(' | ') : 'ок'))
    await ctx.close()
  }
}

await browser.close()
mkdirSync(dirname(out), { recursive: true })
writeFileSync(out, JSON.stringify(report, null, 1), 'utf8')
console.log('\n--- Отчёт: ' + out)

const bad = report.filter((r) => r.error || r.status !== 200 || r.hasHScroll || r.h1 !== 1 || r.headSkip ||
  r.minBody < 16 || r.minMeta < 14 || (r.badTels || []).length || (r.svgBad || []).length || (r.contrast || []).length ||
  (r.zonesNoTap || 0) || (r.sticky?.covered || []).length || (r.pageErrors || []).length)
console.log('--- Срезов с замечаниями: ' + bad.length + ' из ' + report.length)
process.exit(bad.length ? 1 : 0)
