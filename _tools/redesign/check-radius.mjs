/**
 * Опись скруглений: у чего на живых страницах скруглены углы.
 *
 * Чтением CSS этого не выяснить. Радиус приходит из нескольких правил
 * сразу, часть из них общие (.wp-block-image img ловит и панораму
 * первого экрана, и снимок в контенте), а часть отменяется дальше
 * по файлу. Поэтому опись снимается с готовой страницы.
 *
 * Отдельный случай — снимок внутри скруглённой карточки. Там у самого
 * снимка радиус нулевой, а выглядит он скруглённым, потому что карточка
 * обрезает его своим overflow: hidden. Это не дефект, а единственный
 * способ, которым угол снимка точно совпадает с углом карточки.
 * Инструмент такие случаи распознаёт и считает их отдельно, а не
 * записывает в нарушители.
 *
 * Запуск:
 *   node _tools/redesign/check-radius.mjs [--widths 1366]
 *        [--only home,kontakty] [--all]
 *
 * --all печатает вообще всё, у чего радиус ненулевой (карточки,
 * кнопки, поля). Без него печатаются только изображения, видео,
 * карты и заглушки — то, у чего по решению от 23 сентября углы
 * должны быть прямыми.
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
  ['uslugi', '/uslugi/'],
  ['svc-organizatsiya', '/uslugi/organizatsiya-pohoron/'],
  ['svc-krematsiya', '/uslugi/krematsiya/'],
  ['svc-transportirovka', '/uslugi/transportirovka/'],
  ['svc-blagoustroystvo', '/uslugi/blagoustroystvo/'],
  ['svc-yurpomoshch', '/uslugi/yuridicheskaya-pomoshch/'],
  ['o-kompanii', '/o-kompanii/'],
  ['kontakty', '/kontakty/'],
  ['otzyvy', '/otzyvy/'],
  ['vopros-otvet', '/vopros-otvet/'],
]

const widths = arg('widths', '1366').split(',').map(Number)
const only = arg('only', '')
const all = process.argv.includes('--all')
const pages = only ? PAGES.filter(([k]) => only.split(',').includes(k)) : PAGES

const browser = await chromium.launch({ channel: 'chrome', headless: true })
let bad = 0
const сводка = new Map()

for (const [key, url] of pages) {
  for (const width of widths) {
    const ctx = await browser.newContext({ viewport: { width, height: 900 }, deviceScaleFactor: 1 })
    const page = await ctx.newPage()
    await page.goto('http://ra-zil.test' + url, { waitUntil: 'networkidle', timeout: 45000 })

    const r = await page.evaluate((all) => {
      // Медиа — то, у чего углы обязаны быть прямыми: снимки, видео,
      // встроенные карты и заглушки будущих снимков.
      const медиа = (el) =>
        ['IMG', 'VIDEO', 'PICTURE', 'IFRAME', 'CANVAS'].includes(el.tagName) ||
        el.classList.contains('rz-photo-slot')

      const имя = (el) => el.tagName.toLowerCase() +
        (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).slice(0, 3).join('.') : '')

      // Родитель, который обрезает своим радиусом: тогда угол снимка
      // совпадает с углом карточки, и это правильно.
      const обрезан = (el) => {
        for (let n = el.parentElement; n; n = n.parentElement) {
          const cs = getComputedStyle(n)
          const ov = cs.overflow + cs.overflowX + cs.overflowY
          if ((ov.includes('hidden') || ov.includes('clip')) && parseFloat(cs.borderTopLeftRadius) > 0) {
            return имя(n) + ' r=' + cs.borderRadius
          }
        }
        return null
      }

      const нарушители = []
      const обрезанные = []
      const прочие = []

      for (const el of document.querySelectorAll('body *')) {
        const cs = getComputedStyle(el)
        const rr = el.getBoundingClientRect()
        if (rr.width === 0 || rr.height === 0) continue
        if (cs.display === 'none' || cs.visibility === 'hidden') continue
        const r0 = parseFloat(cs.borderTopLeftRadius) || 0
        const r1 = parseFloat(cs.borderTopRightRadius) || 0
        const r2 = parseFloat(cs.borderBottomRightRadius) || 0
        const r3 = parseFloat(cs.borderBottomLeftRadius) || 0
        const есть = r0 + r1 + r2 + r3 > 0
        const зап = { имя: имя(el), радиус: cs.borderRadius, размер: Math.round(rr.width) + 'x' + Math.round(rr.height) }
        if (медиа(el)) {
          if (есть) нарушители.push(зап)
          else {
            const c = обрезан(el)
            if (c) обрезанные.push({ ...зап, карточка: c })
          }
        } else if (есть && all) {
          прочие.push(зап)
        }
      }
      return { нарушители, обрезанные, прочие }
    }, all)

    if (r.нарушители.length) bad += r.нарушители.length
    console.log(
      key.padEnd(20) + String(width).padStart(5) + 'px  скруглённых медиа: ' + r.нарушители.length +
      '  обрезанных карточкой: ' + r.обрезанные.length +
      (r.нарушители.length ? '   << УПАЛ' : '   ок')
    )
    for (const n of r.нарушители) {
      console.log('        СКРУГЛЕНО: ' + n.имя + '  ' + n.радиус + '  ' + n.размер)
      сводка.set(n.имя, (сводка.get(n.имя) || 0) + 1)
    }
    for (const c of r.обрезанные) {
      console.log('        (норма) ' + c.имя + ' ' + c.размер + ' обрезан карточкой ' + c.карточка)
    }
    if (all) {
      for (const o of r.прочие) console.log('        прочее: ' + o.имя + '  ' + o.радиус + '  ' + o.размер)
    }
    await ctx.close()
  }
}

await browser.close()
if (сводка.size) {
  console.log('\n--- кто скруглён:')
  for (const [k, v] of [...сводка.entries()].sort((a, b) => b[1] - a[1])) console.log('    ' + k + ' — ' + v)
}
console.log('\n--- скруглённых медиа всего: ' + bad)
process.exit(bad ? 1 : 0)
