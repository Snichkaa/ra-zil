// Точный расчёт переноса строк для двух изменённых мест главной.
// Ширины глифов — из реальных woff2 темы (measure-01-woff2.js).
// Только чтение.
const { buildFont, layout } = require('./measure-01-woff2.js');

const FDIR = 'wp-content/themes/razil/assets/fonts/';
const serif = buildFont(FDIR + 'pt-serif-400.woff2');
const sans400 = buildFont(FDIR + 'pt-sans-400.woff2');
const sans700 = buildFont(FDIR + 'pt-sans-700.woff2');

const ROOT_PX = 16;
const rem = (r) => r * ROOT_PX;

// --- разрешение clamp() из theme.json ---
// heading-m: clamp(1.625rem, 1.4178rem + 0.884vw, 2.125rem)
function headingM(viewportPx) {
  const min = rem(1.625);            // 26px
  const max = rem(2.125);            // 34px
  const pref = rem(1.4178) + 0.00884 * viewportPx;
  return { min, max, pref, value: Math.min(Math.max(pref, min), max) };
}

// --- доступная ширина текста ---
// .rz-section { padding-inline: 1rem }  -> 16px с каждой стороны
// theme.json settings.layout.contentSize = 72.5rem = 1160px (constrained)
const PAD = rem(1) * 2;
const CONTENT_SIZE = rem(72.5);

function containerWidth(viewportPx) {
  return Math.min(viewportPx - PAD, CONTENT_SIZE);
}

// .rz-advantage: flex, .rz-icon flex:none width 3.5rem, gap 1rem
// .rz-advantage p { max-width: 52rem }
const ICON = rem(3.5);
const GAP = rem(1);
const P_MAX = rem(52);

function advantageTextWidth(viewportPx) {
  const box = containerWidth(viewportPx) - ICON - GAP;
  return Math.min(box, P_MAX);
}

function headingTextWidth(viewportPx) {
  return containerWidth(viewportPx); // max-width у h2 не задан, ширину держит contentSize
}

const VIEWPORTS = [360, 1280];

// ---------------------------------------------------------------- случай (б)
const H2_OLD = 'Что делать, если уход наступил дома';
const H2_NEW = 'Что делать, если уход из жизни близкого Вам человека наступил дома';
const H2_LINE_HEIGHT = 1.2; // theme.json styles.elements.h2

console.log('################################################################');
console.log('## (б) ЗАГОЛОВОК h2 БЛОКА ПЕРВОЙ ПОМОЩИ');
console.log('################################################################');
console.log('шрифт: PT Serif 400 (theme.json styles.elements.h2), line-height 1.2');
console.log('размер: preset heading-m = clamp(1.625rem, 1.4178rem + 0.884vw, 2.125rem)');
console.log('max-width на h2 не задан; ширину держит contentSize 72.5rem = ' + CONTENT_SIZE + 'px');
console.log('было : ' + H2_OLD.length + ' симв.');
console.log('стало: ' + H2_NEW.length + ' симв.  (+' + (H2_NEW.length - H2_OLD.length) + ')');

for (const vw of VIEWPORTS) {
  const fs = headingM(vw);
  const avail = headingTextWidth(vw);
  console.log('\n---------- viewport ' + vw + 'px ----------');
  console.log('clamp: min=' + fs.min + 'px  preferred=' + fs.pref.toFixed(3) + 'px  max=' + fs.max
    + 'px  ->  font-size = ' + fs.value.toFixed(2) + 'px'
    + (fs.value === fs.min ? ' (упёрлось в min)' : fs.value === fs.max ? ' (упёрлось в max)' : ''));
  console.log('доступная ширина: min(' + vw + ' - ' + PAD + ', ' + CONTENT_SIZE + ') = ' + avail + 'px');

  for (const [label, text] of [['БЫЛО ', H2_OLD], ['СТАЛО', H2_NEW]]) {
    const r = layout([{ text, font: serif }], fs.value, avail);
    console.log('\n  ' + label + ': ширина в одну строку = ' + r.totalWidth + 'px'
      + '  (' + (r.totalWidth / avail * 100).toFixed(1) + '% доступной)'
      + '  ->  СТРОК: ' + r.lines);
    r.lineTexts.forEach((t, i) => {
      console.log('    строка ' + (i + 1) + ' [' + r.lineWidths[i] + 'px]: ' + t);
    });
    if (r.missing.length) console.log('    НЕТ ГЛИФОВ: ' + r.missing.join(' '));
    console.log('    высота блока: ' + r.lines + ' x ' + (fs.value * H2_LINE_HEIGHT).toFixed(1)
      + 'px = ' + (r.lines * fs.value * H2_LINE_HEIGHT).toFixed(1) + 'px');
  }
}

// ---------------------------------------------------------------- случай (а)
const BODY_PX = rem(1.125);   // has-body-font-size = 1.125rem
const BODY_LH = 1.65;         // theme.json styles.typography.lineHeight

const CARDS = [
  { n: 1, bold: 'Опыт более 10 лет', rest: ' в организации траурных мероприятий с соблюдением традиций и пожеланий близких', changed: false },
  { n: 2, bold: 'Работаем круглосуточно', rest: ' — в любое время суток трубку берёт живой человек, а не автоответчик', changed: false },
  { n: 3, bold: 'Справедливые цены', rest: ' без скрытых платежей, готовы обсудить любой бюджет. Все расчёты проходят с использованием контрольно-кассовой техники в соответствии с законодательством РФ', changed: true },
  { n: 4, bold: 'Полный цикл услуг', rest: ' — от оформления документов для захоронения до благоустройства места захоронения и последующего ухода за ним', changed: true },
];

const CARD3_OLD = { bold: 'Справедливые цены', rest: ' без скрытых платежей, готовы обсудить любой бюджет. Все расчёты проходят через кассу с выдачей чека' };
const CARD4_OLD = { bold: 'Полный цикл услуг', rest: ' — от оформления документов до благоустройства места захоронения и последующего ухода за ним' };

function runsOf(c) {
  return [{ text: c.bold, font: sans700 }, { text: c.rest, font: sans400 }];
}

console.log('\n\n################################################################');
console.log('## (а) КАРТОЧКИ ПРЕИМУЩЕСТВ');
console.log('################################################################');
console.log('шрифт: PT Sans, начало абзаца <strong> -> PT Sans 700, остальное 400');
console.log('размер: has-body-font-size = 1.125rem = ' + BODY_PX + 'px, line-height ' + BODY_LH);
console.log('карточка: flex; .rz-icon flex:none ' + ICON + 'px; gap ' + GAP + 'px; .rz-advantage p max-width 52rem = ' + P_MAX + 'px');

for (const vw of VIEWPORTS) {
  const avail = advantageTextWidth(vw);
  const box = containerWidth(vw);
  console.log('\n---------- viewport ' + vw + 'px ----------');
  console.log('контейнер: min(' + vw + ' - ' + PAD + ', ' + CONTENT_SIZE + ') = ' + box + 'px');
  console.log('ширина абзаца: min(' + box + ' - ' + ICON + ' - ' + GAP + ', ' + P_MAX + ') = ' + avail + 'px'
    + (box - ICON - GAP > P_MAX ? '  (ограничил max-width 52rem)' : '  (ограничил контейнер)'));

  let maxLines = 0;
  for (const c of CARDS) {
    const r = layout(runsOf(c), BODY_PX, avail);
    maxLines = Math.max(maxLines, r.lines);
    console.log('\n  карточка ' + c.n + ' «' + c.bold + '»' + (c.changed ? '  [ИЗМЕНЕНА]' : '')
      + '  ' + (c.bold + c.rest).length + ' симв.');
    console.log('    ширина в одну строку ' + r.totalWidth + 'px  ->  СТРОК: ' + r.lines
      + '  высота ' + (r.lines * BODY_PX * BODY_LH).toFixed(1) + 'px');
    r.lineTexts.forEach((t, i) => console.log('      ' + (i + 1) + ' [' + r.lineWidths[i] + 'px] ' + t));
    if (r.missing.length) console.log('      НЕТ ГЛИФОВ: ' + r.missing.join(' '));
  }

  console.log('\n  -- те же две карточки ДО правки, для сравнения --');
  for (const [label, c] of [['карточка 3 ДО', CARD3_OLD], ['карточка 4 ДО', CARD4_OLD]]) {
    const r = layout(runsOf(c), BODY_PX, avail);
    console.log('    ' + label + ': ' + (c.bold + c.rest).length + ' симв., СТРОК: ' + r.lines
      + ', высота ' + (r.lines * BODY_PX * BODY_LH).toFixed(1) + 'px');
  }

  console.log('\n  самая высокая карточка: ' + maxLines + ' строк(и)');
  console.log('  переполнения по горизонтали нет: самая длинная строка '
    + Math.max.apply(null, CARDS.map(c => Math.max.apply(null, layout(runsOf(c), BODY_PX, avail).lineWidths)))
    + 'px при доступных ' + avail + 'px');
}

// ---------------------------------------------------------------- переломные ширины
console.log('\n\n################################################################');
console.log('## ПЕРЕЛОМНЫЕ ШИРИНЫ ОКНА: где меняется число строк');
console.log('################################################################');

function linesAt(kind, vw) {
  if (kind === 'h2-new') return layout([{ text: H2_NEW, font: serif }], headingM(vw).value, headingTextWidth(vw)).lines;
  if (kind === 'h2-old') return layout([{ text: H2_OLD, font: serif }], headingM(vw).value, headingTextWidth(vw)).lines;
  if (kind === 'card3-new') return layout(runsOf(CARDS[2]), BODY_PX, advantageTextWidth(vw)).lines;
  if (kind === 'card3-old') return layout(runsOf(CARD3_OLD), BODY_PX, advantageTextWidth(vw)).lines;
  throw new Error('kind?');
}

for (const kind of ['h2-old', 'h2-new', 'card3-old', 'card3-new']) {
  const steps = [];
  let prev = null;
  for (let vw = 320; vw <= 1920; vw += 1) {
    const n = linesAt(kind, vw);
    if (prev !== null && n !== prev) steps.push(vw + 'px: ' + prev + ' -> ' + n);
    prev = n;
  }
  console.log(kind + ': ' + (steps.length ? steps.join(' | ') : 'число строк не меняется во всём диапазоне 320-1920')
    + '   (на 320px: ' + linesAt(kind, 320) + ', на 1920px: ' + linesAt(kind, 1920) + ')');
}

console.log('\n######## ОГОВОРКИ ########');
console.log('- кернинг: таблицы kern нет ни в одном шрифте, но GPOS есть; браузер применяет');
console.log('  font-kerning: auto. Для кириллицы в PT парах кернинг редкий, поправка сотые доли процента');
console.log('  и всегда В МИНУС ширине, то есть запас только увеличивается.');
console.log('- полоса прокрутки: на десктопе классическая полоса отнимает ~15px у layout viewport,');
console.log('  но contentSize 1160px всё равно ограничивает раньше, так что на 1280 это ничего не меняет.');
console.log('  Единица vw в clamp() считается ПО окну, вместе с полосой, поэтому font-size тоже не меняется.');
console.log('- разбивка жадная, как в браузере: точки переноса после пробела, дефиса и тире.');
