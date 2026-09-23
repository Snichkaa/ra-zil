/**
 * Заглушки под фотографии: помещается ли подпись внутрь рамки.
 *
 * Зачем постоянный инструмент. Этот дефект прожил два прогона.
 * 19 сентября заглушки поставили, 21 сентября нашли, что при 200 %
 * текста подпись вылезает за нижнюю кромку рамки на странице
 * благоустройства, — и не починили, потому что шла работа
 * по главной. Проверка тогда существовала черновиком в каталоге
 * временных файлов и потерялась вместе с ним. Поэтому теперь она
 * лежит здесь.
 *
 * Что меряется:
 *
 *   1. Выход подписи за рамку. Считается по нижней кромке последнего
 *      потомка против внутренней кромки рамки (граница минус поле).
 *      Положительное число — подпись вылезла.
 *   2. Выход рамки за свою ячейку сетки. У заглушки задана пропорция,
 *      и если ей же задать высоту, ширину она посчитает из высоты
 *      по пропорции и может вылезти за колонку. Ловится сравнением
 *      ширины рамки с шириной её области в сетке.
 *   3. Пустота внутри рамки — справочно: рамка держит геометрию
 *      будущего снимка, поэтому какой-то запас в ней законен, но
 *      рост этого числа означает, что заглушка раздулась.
 *
 * Запуск:
 *   node _tools/redesign/check-slots.mjs [--widths 375,768,1024,1366,1920]
 *        [--zoom 100] [--only svc-blagoustroystvo]
 *
 * Возвращает ненулевой код, если хоть где-то подпись вылезла
 * за рамку или рамка вылезла за свою ячейку.
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
  ['kontakty', '/kontakty/'],
  ['o-kompanii', '/o-kompanii/'],
]

const widths = arg('widths', '375,768,1024,1366,1920').split(',').map(Number)
const zooms = arg('zoom', '100,200').split(',').map(Number)
const only = arg('only', '')
const pages = only ? PAGES.filter(([k]) => only.split(',').includes(k)) : PAGES

const browser = await chromium.launch({ channel: 'chrome', headless: true })
let failed = 0
let всего = 0

for (const [key, url] of pages) {
  for (const width of widths) {
    for (const zoom of zooms) {
      const ctx = await browser.newContext({ viewport: { width, height: 900 }, deviceScaleFactor: 1 })
      const page = await ctx.newPage()
      await page.goto('http://ra-zil.test' + url, { waitUntil: 'networkidle', timeout: 45000 })
      if (zoom !== 100) {
        await page.addStyleTag({ content: 'html { font-size: ' + zoom + '% !important; }' })
        await page.waitForTimeout(300)
      }

      const r = await page.evaluate(() => {
        const out = []
        for (const s of document.querySelectorAll('.rz-photo-slot')) {
          const cs = getComputedStyle(s)
          const box = s.getBoundingClientRect()
          if (box.width === 0) continue
          const pt = parseFloat(cs.paddingTop) || 0
          const pb = parseFloat(cs.paddingBottom) || 0

          const дети = [...s.children].map((c) => c.getBoundingClientRect())
          const верхСодержимого = Math.min(...дети.map((d) => d.top))
          const низСодержимого = Math.max(...дети.map((d) => d.bottom))

          // Выход подписи за внутреннюю кромку рамки.
          const выход = Math.round(Math.max(
            низСодержимого - (box.bottom - pb),
            (box.top + pt) - верхСодержимого
          ))

          // Ширина области сетки, если заглушка — элемент сетки.
          let ячейка = null
          const p = s.parentElement
          if (p && getComputedStyle(p).display.includes('grid')) {
            const пробел = parseFloat(getComputedStyle(p).columnGap) || 0
            const колонки = getComputedStyle(p).gridTemplateColumns.split(' ').map(parseFloat)
            // Сколько колонок занимает — по фактической ширине против дорожки.
            let занимает = 1
            let сумма = колонки[0] || box.width
            while (сумма < box.width - 1 && занимает < колонки.length) {
              занимает++
              сумма += пробел + (колонки[занимает - 1] || 0)
            }
            ячейка = Math.round(сумма)
          }

          out.push({
            номер: (s.getAttribute('data-slot') || '?'),
            рамка: Math.round(box.width) + 'x' + Math.round(box.height),
            выход,
            пустота: Math.round(box.height - pt - pb - (низСодержимого - верхСодержимого)),
            шире: ячейка !== null ? Math.round(box.width - ячейка) : null,
          })
        }
        return out
      })

      всего += r.length
      const плохие = r.filter((x) => x.выход > 1 || (x.шире !== null && x.шире > 1))
      if (плохие.length) failed += плохие.length
      if (r.length) {
        console.log(
          key.padEnd(20) + String(width).padStart(5) + 'px z' + zoom +
          '  заглушек ' + r.length +
          '  пустота ' + r.map((x) => x.пустота).join('/') +
          (плохие.length ? '   << УПАЛ' : '   ок')
        )
        for (const x of плохие) {
          console.log(
            '        Фото ' + x.номер + ' ' + x.рамка +
            (x.выход > 1 ? '  подпись за рамкой на ' + x.выход + 'px' : '') +
            (x.шире > 1 ? '  рамка шире ячейки на ' + x.шире + 'px' : '')
          )
        }
      }
      await ctx.close()
    }
  }
}

await browser.close()
console.log('\n--- заглушек проверено: ' + всего + ', с замечаниями: ' + failed)
process.exit(failed ? 1 : 0)
