/**
 * Разметка блоков глазами редактора: что он сохранил бы сам.
 *
 * Зачем. Круговорот serialize_blocks(parse_blocks($c)) проверяет
 * только разбор и сборку комментариев-разделителей: содержимое блока
 * он переносит как есть, и разметка, собранная руками и не совпавшая
 * с выводом save() этого блока, проходит его без единого замечания.
 * В редакторе такая разметка даёт «Блок содержит неожиданное
 * содержимое», и владелец сайта не может править страницу, не потеряв
 * её вёрстку. Проверка П-5 нашла ровно это: по два невалидных блока
 * на каждой из пяти страниц услуг.
 *
 * Поэтому кандидат прогоняется через НАСТОЯЩИЙ редактор:
 *
 *   wp.blocks.parse(html)  ->  пометить всё валидным  ->  serialize()
 *
 * Пометка нужна: getBlockContent отдаёт originalContent у блока,
 * признанного невалидным, и без неё сборка вернула бы вход дословно,
 * то есть проверка проверяла бы сама себя.
 *
 * На выходе — разметка, которую редактор считает своей. Если она
 * совпала с входом побайтово, блоки валидны по построению.
 *
 * Запуск:
 *   node _tools/redesign/normalize-blocks.mjs --cookies <файл.json>
 *        --in <кандидат.html> [--out <нормализованный.html>]
 *
 * Код возврата 1, если вход и нормализованный вид разошлись.
 */

import { readFileSync, writeFileSync } from 'node:fs'
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
const inFile = arg('in', null)
const outFile = arg('out', null)

if (!cookiesFile || !inFile) {
  console.error('Нужны --cookies <файл.json> и --in <кандидат.html>')
  process.exit(2)
}

const input = readFileSync(inFile, 'utf8')
const cookies = JSON.parse(readFileSync(cookiesFile, 'utf8'))

const browser = await chromium.launch({ channel: 'chrome', headless: true })
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } })
await ctx.addCookies(cookies)
const page = await ctx.newPage()

// Пустая новая запись нужного типа: реестр блоков и все стили блоков
// темы регистрируются именно для этого экрана.
await page.goto('http://ra-zil.test/wp-admin/post-new.php?post_type=services', {
  waitUntil: 'domcontentloaded',
  timeout: 60000,
})
await page.waitForFunction(() => window.wp && window.wp.blocks && window.wp.blocks.parse, null, { timeout: 60000 })
await page.waitForTimeout(1500)

const r = await page.evaluate((html) => {
  const parsed = window.wp.blocks.parse(html)

  const плохие = []
  const обойти = (blocks, путь) => {
    for (const b of blocks) {
      if (b.isValid === false) плохие.push(путь + '/' + b.name)
      if (b.innerBlocks && b.innerBlocks.length) обойти(b.innerBlocks, путь + '/' + b.name)
    }
  }
  обойти(parsed, '')

  // Копия дерева, где каждый блок объявлен валидным: только так
  // serialize пойдёт через save(), а не вернёт исходный текст.
  const валиден = (b) => ({
    ...b,
    isValid: true,
    innerBlocks: (b.innerBlocks || []).map(валиден),
  })

  return {
    невалидные: плохие,
    всего: (function счёт(bs) {
      let n = 0
      for (const b of bs) n += 1 + счёт(b.innerBlocks || [])
      return n
    })(parsed),
    нормализовано: window.wp.blocks.serialize(parsed.map(валиден)),
  }
}, input)

await browser.close()

const same = r.нормализовано === input

console.log('блоков: ' + r.всего + ', невалидных при разборе: ' + r.невалидные.length)
for (const b of r.невалидные) console.log('    НЕВАЛИДЕН: ' + b)

if (outFile) {
  writeFileSync(outFile, r.нормализовано, 'utf8')
  console.log('нормализованный вид -> ' + outFile)
}

if (same) {
  console.log('--- СОВПАЛО: редактор сохранил бы ровно это')
  process.exit(0)
}

// Показываем первое расхождение с окрестностью: длинный дифф здесь
// бесполезен, а первая точка расхождения почти всегда объясняет всё.
const a = input
const b = r.нормализовано
let at = Math.min(a.length, b.length)
for (let i = 0; i < at; i++) {
  if (a[i] !== b[i]) {
    at = i
    break
  }
}
const окно = (s) => JSON.stringify(s.slice(Math.max(0, at - 70), at + 90))
console.log('--- РАСХОЖДЕНИЕ на символе ' + at + ' (вход ' + a.length + ', редактор ' + b.length + ')')
console.log('    вход:     ' + окно(a))
console.log('    редактор: ' + окно(b))
process.exit(1)
