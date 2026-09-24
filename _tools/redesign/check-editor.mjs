/**
 * Валидность блоков глазами редактора.
 *
 * Зачем отдельно от verify-blocks.php. Круговорот
 * serialize_blocks(parse_blocks($c)) проверяет, что разметку можно
 * разобрать и собрать обратно байт в байт. Это необходимое условие,
 * но не достаточное: редактор дополнительно прогоняет сохранённый
 * HTML каждого блока через его же save() и сравнивает. Разметка,
 * собранная руками и не совпавшая с тем, что сгенерировал бы save(),
 * круговорот проходит, а в редакторе показывает «Блок содержит
 * неожиданное содержимое». Владелец сайта после этого не может
 * править страницу, не потеряв разметку.
 *
 * Поэтому проверка идёт в настоящем редакторе блоков: страница
 * открывается в wp-admin, и невалидные блоки считаются рекурсивно
 * через wp.data.
 *
 * Авторизация — куками из _tools/t5/admin-cookie.php, без паролей.
 *
 * Запуск:
 *   node _tools/redesign/check-editor.mjs --cookies <файл.json>
 *        --ids 11,12,13,14,15
 */

import { readFileSync } from 'node:fs'
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

const cookiesFile = arg('cookies', null)
const ids = arg('ids', '11,12,13,14,15').split(',').map((s) => s.trim()).filter(Boolean)
if (!cookiesFile) {
  console.error('Нужен --cookies <файл.json>')
  process.exit(2)
}
const cookies = JSON.parse(readFileSync(cookiesFile, 'utf8'))

const browser = await chromium.launch({ channel: 'chrome', headless: true })
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } })
await ctx.addCookies(cookies)

let total = 0
let broken = 0

for (const id of ids) {
  const page = await ctx.newPage()
  const errors = []
  page.on('console', (m) => {
    if (m.type() === 'error') errors.push(m.text().slice(0, 140))
  })

  await page.goto('http://ra-zil.test/wp-admin/post.php?post=' + id + '&action=edit', {
    waitUntil: 'domcontentloaded',
    timeout: 60000,
  })

  // Редактор грузится асинхронно. Ждём не таймаутом, а появления
  // непустого списка блоков в хранилище: пустой список означает
  // «ещё не загрузилось», и посчитать по нему ноль невалидных
  // было бы ложным успехом.
  const ready = await page
    .waitForFunction(
      () => {
        const w = window
        if (!w.wp || !w.wp.data) return false
        const sel = w.wp.data.select('core/block-editor')
        if (!sel) return false
        const b = sel.getBlocks()
        return b && b.length > 0
      },
      null,
      { timeout: 60000 }
    )
    .then(() => true)
    .catch(() => false)

  if (!ready) {
    const title = await page.title()
    console.log(String(id).padStart(4) + '  РЕДАКТОР НЕ ЗАГРУЗИЛСЯ (заголовок окна: ' + title + ')')
    broken++
    await page.close()
    continue
  }

  // Небольшая пауза: валидация блока идёт при его регистрации в
  // хранилище, и сразу после появления списка часть блоков может
  // быть ещё не проверена.
  await page.waitForTimeout(2500)

  const r = await page.evaluate(() => {
    const sel = window.wp.data.select('core/block-editor')
    const out = { всего: 0, невалидных: 0, список: [], типы: {} }
    const walk = (blocks, путь) => {
      for (const b of blocks) {
        out.всего++
        out.типы[b.name] = (out.типы[b.name] || 0) + 1
        if (b.isValid === false) {
          out.невалидных++
          out.список.push(путь + '/' + b.name)
        }
        if (b.innerBlocks && b.innerBlocks.length) walk(b.innerBlocks, путь + '/' + b.name)
      }
    }
    walk(sel.getBlocks(), '')
    return out
  })

  total += r.невалидных
  console.log(
    String(id).padStart(4) + '  блоков ' + String(r.всего).padStart(3) +
    '  невалидных ' + r.невалидных +
    (r.невалидных ? '   << ' + r.список.join(', ') : '   ок') +
    (errors.length ? '   ошибок в консоли: ' + errors.length : '')
  )
  for (const e of errors.slice(0, 4)) console.log('        консоль: ' + e)

  await page.close()
}

await browser.close()
console.log('--- ИТОГО невалидных блоков: ' + total + (broken ? ', не открылось записей: ' + broken : ''))
process.exit(broken ? 2 : 0)
