/**
 * Висящая кнопка звонка: где она есть и что она собой накрывает.
 *
 * Зачем отдельный инструмент. Такая проверка уже была внутри
 * audit.mjs, и она оказалась неустойчивой: там страница
 * прокручивается вниз сразу после загрузки, а ленивые блоки —
 * карта на странице контактов, картинки ниже сгиба — догружаются
 * уже после прокрутки. Высота страницы меняется под ногами, и пробой
 * попадает не в ту точку. В полном обходе это давало замечание
 * «кнопка накрывает копирайт» там, где прямой замер показывал зазор
 * в 12 px, а повтор того же среза отдельно проходил чисто.
 *
 * Здесь страница сначала прокручивается до конца шагами, чтобы
 * ленивое догрузилось, потом высота проверяется на устойчивость:
 * пока scrollHeight меняется, замер не начинается. Только после
 * этого — прокрутка в самый низ и пробой.
 *
 * Пробой не по рамкам, а попаданием: elementFromPoint в точках
 * самих целей. Пересечение рамок сказало бы «накрывает» и в том
 * случае, когда кнопка стоит в прозрачном углу цели и нажатию
 * не мешает.
 *
 * Запуск:
 *   node _tools/redesign/check-sticky.mjs [--widths 375,768,1366]
 *        [--zoom 100] [--only home,kontakty]
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

const PAGES = [
  ['home', '/'],
  ['svc-index', '/uslugi/'],
  ['svc-blagoustroystvo', '/uslugi/blagoustroystvo/'],
  ['svc-organizatsiya', '/uslugi/organizatsiya-pohoron/'],
  ['svc-krematsiya', '/uslugi/krematsiya/'],
  ['svc-transportirovka', '/uslugi/transportirovka/'],
  ['svc-yurpomoshch', '/uslugi/yuridicheskaya-pomoshch/'],
  ['o-kompanii', '/o-kompanii/'],
  ['kontakty', '/kontakty/'],
  ['otzyvy', '/otzyvy/'],
  ['rekvizity', '/rekvizity/'],
  ['katalog', '/katalog/'],
  ['nashi-raboty', '/nashi-raboty/'],
  ['vopros-otvet', '/vopros-otvet/'],
  ['stati', '/stati/'],
  ['politika', '/politika-konfidentsialnosti/'],
  ['soglasie', '/soglasie-na-obrabotku-dannyh/'],
  ['polzovatelskoe', '/polzovatelskoe-soglashenie/'],
  ['spasibo', '/spasibo/'],
]

const widths = arg('widths', '375,768,1366').split(',').map(Number)
const zoom = Number(arg('zoom', '100'))
const only = arg('only', '')
const pages = only ? PAGES.filter(([k]) => only.split(',').includes(k)) : PAGES

const browser = await chromium.launch({ channel: 'chrome', headless: true })
let failed = 0

for (const [key, url] of pages) {
  for (const width of widths) {
    const ctx = await browser.newContext({ viewport: { width, height: 900 }, deviceScaleFactor: 1 })
    const page = await ctx.newPage()
    await page.goto('http://ra-zil.test' + url, { waitUntil: 'networkidle', timeout: 45000 })
    if (zoom !== 100) {
      await page.addStyleTag({ content: 'html { font-size: ' + zoom + '% !important; }' })
      await page.waitForTimeout(250)
    }

    // Прокрутка по экрану до конца — чтобы ленивое догрузилось.
    await page.evaluate(async () => {
      const step = window.innerHeight
      for (let y = 0; y < document.documentElement.scrollHeight; y += step) {
        window.scrollTo(0, y)
        await new Promise((r) => setTimeout(r, 80))
      }
    })

    // Высота должна перестать меняться: пока растёт — ждём.
    const settled = await page.evaluate(async () => {
      let prev = -1
      for (let i = 0; i < 20; i++) {
        const h = document.documentElement.scrollHeight
        if (h === prev) return { ok: true, tries: i, h }
        prev = h
        await new Promise((r) => setTimeout(r, 150))
      }
      return { ok: false, tries: 20, h: document.documentElement.scrollHeight }
    })

    await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight))
    await page.waitForTimeout(400)

    const r = await page.evaluate(() => {
      const btn = document.querySelector('.rz-sticky-call')
      if (!btn) return { есть: false }
      const cs = getComputedStyle(btn)
      const видна = cs.display !== 'none' && cs.visibility !== 'hidden' && parseFloat(cs.opacity) > 0.01
      if (!видна) return { есть: true, видна: false, display: cs.display }

      const b = btn.getBoundingClientRect()
      const цели = []
      const add = (sel, имя) => {
        for (const el of document.querySelectorAll(sel)) {
          const q = el.getBoundingClientRect()
          if (q.bottom < 0 || q.top > window.innerHeight || q.width === 0) continue
          цели.push({ el, имя, текст: (el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 34) })
        }
      }
      add('.rz-footer a, .rz-footer p', 'подвал')
      add('.entry-content .wp-block-details summary', 'вопрос')
      add('.rz-cta a', 'призыв')

      // Пробой попаданием: пять точек по цели — четыре угла внутри
      // и центр. Промахом считается только то, что вместо цели
      // отдаёт кнопку.
      const накрыты = []
      for (const t of цели) {
        const q = t.el.getBoundingClientRect()
        const pts = [
          [q.left + 2, q.top + 2], [q.right - 2, q.top + 2],
          [q.left + 2, q.bottom - 2], [q.right - 2, q.bottom - 2],
          [(q.left + q.right) / 2, (q.top + q.bottom) / 2],
        ]
        let бито = 0
        for (const [x, y] of pts) {
          if (x < 0 || y < 0 || x > window.innerWidth || y > window.innerHeight) continue
          const at = document.elementFromPoint(Math.round(x), Math.round(y))
          if (at && (at === btn || btn.contains(at))) бито++
        }
        if (бито) накрыты.push(t.имя + ' «' + t.текст + '» точек под кнопкой: ' + бито + ' из 5')
      }

      const foot = document.querySelector('.rz-footer')
      const fcs = foot ? getComputedStyle(foot) : null
      const last = foot ? [...foot.querySelectorAll('p')].pop() : null
      return {
        есть: true, видна: true,
        кнопка: Math.round(b.top) + '..' + Math.round(b.bottom),
        запасПодвала: fcs ? fcs.paddingBottom : '—',
        низПоследнейСтроки: last ? Math.round(last.getBoundingClientRect().bottom) : null,
        зазор: last ? Math.round(b.top - last.getBoundingClientRect().bottom) : null,
        накрыты,
      }
    })

    const bad = (r.накрыты || []).length > 0 || !settled.ok
    if (bad) failed++
    console.log(
      key.padEnd(20) + String(width).padStart(5) + 'px z' + zoom +
      (r.есть === false ? '  кнопки нет в разметке страницы'
        : r.видна === false ? '  кнопки нет (display: ' + r.display + ')'
          : '  кнопка ' + r.кнопка + '  запас подвала ' + r.запасПодвала +
            '  зазор до последней строки ' + r.зазор) +
      (settled.ok ? '' : '  ВЫСОТА НЕ УСТОЯЛАСЬ') +
      (bad ? '   << УПАЛ' : '   ок')
    )
    for (const c of r.накрыты || []) console.log('        НАКРЫТО: ' + c)

    // Второй проход: низа страницы мало.
    //
    // Висящая кнопка стоит в углу окна, а не страницы, и накрывает
    // то, что оказалось под ней при любой прокрутке. Самое опасное
    // положение цели — у нижней кромки окна, там же, где кнопка.
    // Поэтому каждый вопрос аккордеона и каждая ссылка подвала
    // подводятся к нижней кромке и пробиваются там.
    if (r.видна) {
      const наход = await page.evaluate(async () => {
        const btn = document.querySelector('.rz-sticky-call')
        const цели = [
          ...[...document.querySelectorAll('.entry-content .wp-block-details summary')].map((el) => ({ el, вид: 'вопрос' })),
          ...[...document.querySelectorAll('.rz-footer a')].map((el) => ({ el, вид: 'подвал' })),
          ...[...document.querySelectorAll('.rz-cta a')].map((el) => ({ el, вид: 'призыв' })),
        ]
        const плохо = []
        for (const { el, вид } of цели) {
          el.scrollIntoView({ block: 'end', behavior: 'instant' })
          await new Promise((r) => setTimeout(r, 30))
          const q = el.getBoundingClientRect()
          if (q.width === 0) continue
          const pts = [
            [q.left + 2, q.top + 2], [q.right - 2, q.top + 2],
            [q.left + 2, q.bottom - 2], [q.right - 2, q.bottom - 2],
            [(q.left + q.right) / 2, (q.top + q.bottom) / 2],
          ]
          let бито = 0
          for (const [x, y] of pts) {
            if (x < 0 || y < 0 || x > window.innerWidth || y > window.innerHeight) continue
            const at = document.elementFromPoint(Math.round(x), Math.round(y))
            if (at && (at === btn || btn.contains(at))) бито++
          }
          if (бито) {
            плохо.push({ вид, текст: (el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 34), бито })
          }
        }
        return { целей: цели.length, плохо }
      })
      // §25 называет две цели: последний пункт FAQ и элементы подвала.
      // Их перекрытие — замечание. Ссылка призыва разбирается отдельно:
      // она ведёт на тот же номер, что и сама кнопка, поэтому промах
      // по её углу набирает ровно тот номер, за которым человек тянулся.
      // Печатается всё равно — чтобы про это нельзя было забыть.
      const строго = наход.плохо.filter((x) => x.вид !== 'призыв')
      const мягко = наход.плохо.filter((x) => x.вид === 'призыв')
      if (строго.length) {
        failed++
        console.log('        у нижней кромки окна накрыто ' + строго.length + ' из ' + наход.целей + ':')
        for (const c of строго.slice(0, 6)) console.log('          ' + c.вид + ' «' + c.текст + '» точек под кнопкой ' + c.бито + ' из 5')
      } else {
        console.log('        у нижней кромки окна: ' + наход.целей + ' целей, из целей §25 не накрыта ни одна')
      }
      for (const c of мягко) {
        console.log('        (не замечание) призыв «' + c.текст + '» — точек под кнопкой ' + c.бито + ' из 5, номер тот же самый')
      }
    }

    await ctx.close()
  }
}

await browser.close()
console.log('\n--- срезов с замечаниями: ' + failed + ' из ' + pages.length * widths.length)
process.exit(failed ? 1 : 0)
