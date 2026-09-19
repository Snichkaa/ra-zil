/**
 * Сравнение двух снимков вычисленных стилей.
 *
 * Отвечает на один вопрос: изменился ли внешний вид и где именно.
 * Правка, которая обязана быть безразличной для вида — перестановка
 * правил, вынос цвета в токен, — должна давать ноль расхождений.
 * Правка, меняющая палитру, должна давать расхождения ТОЛЬКО
 * в цветовых свойствах, и это видно по сводке.
 *
 * Запуск:
 *   node _tools/redesign/diff-styles.mjs <до.json> <после.json>
 *        [--max 40]        сколько расхождений печатать подробно
 *        [--only-prop color,background-color]  только эти свойства
 *        [--ignore-prop @box]                  кроме этих
 *
 * Код возврата 1, если есть хоть одно расхождение.
 */

import { readFileSync } from 'node:fs'

function arg(name, fallback) {
  const i = process.argv.indexOf('--' + name)
  return i >= 0 && process.argv[i + 1] ? process.argv[i + 1] : fallback
}

const files = process.argv.slice(2).filter((a, i, all) => !a.startsWith('--') && !(i > 0 && all[i - 1].startsWith('--')))
if (files.length < 2) {
  console.error('Запуск: node diff-styles.mjs <до.json> <после.json>')
  process.exit(2)
}

const before = JSON.parse(readFileSync(files[0], 'utf8'))
const after = JSON.parse(readFileSync(files[1], 'utf8'))
const maxShow = Number(arg('max', 40))
const onlyProp = arg('only-prop', '') ? arg('only-prop', '').split(',') : null
const ignoreProp = arg('ignore-prop', '') ? arg('ignore-prop', '').split(',') : []

const byProp = new Map()
const diffs = []
let compared = 0

const slices = new Set([...Object.keys(before), ...Object.keys(after)])

for (const slice of [...slices].sort()) {
  const b = before[slice]
  const a = after[slice]

  if (!b || !a) {
    diffs.push({ slice, key: '—', prop: '@срез', b: b ? 'есть' : 'нет', a: a ? 'есть' : 'нет' })
    continue
  }
  if (b.error || a.error) {
    if (String(b.error) !== String(a.error)) {
      diffs.push({ slice, key: '—', prop: '@ошибка', b: String(b.error), a: String(a.error) })
    }
    continue
  }

  if (b.count !== a.count) {
    diffs.push({ slice, key: '—', prop: '@число элементов', b: String(b.count), a: String(a.count) })
  }

  const keys = new Set([...Object.keys(b.data), ...Object.keys(a.data)])
  for (const key of keys) {
    const rb = b.data[key]
    const ra = a.data[key]
    if (!rb || !ra) {
      diffs.push({ slice, key, prop: '@элемент', b: rb ? 'есть' : 'нет', a: ra ? 'есть' : 'нет' })
      continue
    }
    for (const prop of Object.keys(rb)) {
      if (ignoreProp.includes(prop)) continue
      if (onlyProp && !onlyProp.includes(prop)) continue
      compared++
      if (rb[prop] !== ra[prop]) {
        diffs.push({ slice, key, prop, b: rb[prop], a: ra[prop] })
        byProp.set(prop, (byProp.get(prop) || 0) + 1)
      }
    }
  }
}

console.log('--- Сравнено значений: ' + compared)
console.log('--- Расхождений: ' + diffs.length)

if (diffs.length) {
  console.log('\n--- По свойствам:')
  for (const [prop, n] of [...byProp.entries()].sort((x, y) => y[1] - x[1])) {
    console.log('    ' + String(n).padStart(6) + '  ' + prop)
  }

  console.log('\n--- Первые ' + Math.min(maxShow, diffs.length) + ':')
  for (const d of diffs.slice(0, maxShow)) {
    console.log('    [' + d.slice + '] ' + d.key)
    console.log('        ' + d.prop + ': ' + d.b + '  ->  ' + d.a)
  }
  process.exit(1)
}

console.log('--- СОВПАЛО: вычисленные стили не изменились')
process.exit(0)
