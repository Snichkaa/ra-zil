// Измерение реальной ширины строк по метрикам woff2 темы.
// Читает hmtx/cmap/head/hhea/maxp прямо из файла шрифта — без браузера.
// Только чтение: ничего не пишет и не меняет.
const fs = require('fs');
const zlib = require('zlib');
const path = require('path');

const KNOWN_TAGS = [
  'cmap', 'head', 'hhea', 'hmtx', 'maxp', 'name', 'OS/2', 'post', 'cvt ', 'fpgm', 'glyf', 'loca',
  'prep', 'CFF ', 'VORG', 'EBDT', 'EBLC', 'gasp', 'hdmx', 'kern', 'LTSH', 'PCLT', 'VDMX', 'vhea',
  'vmtx', 'BASE', 'GDEF', 'GPOS', 'GSUB', 'EBSC', 'JSTF', 'MATH', 'CBDT', 'CBLC', 'COLR', 'CPAL',
  'SVG ', 'sbix', 'acnt', 'avar', 'bdat', 'bloc', 'bsln', 'cvar', 'fdsc', 'feat', 'fmtx', 'fvar',
  'gvar', 'hsty', 'just', 'lcar', 'mort', 'morx', 'opbd', 'prop', 'trak', 'Zapf', 'Silf', 'Glat',
  'Gloc', 'Feat', 'Sill',
];

function readBase128(buf, p) {
  let result = 0;
  for (let i = 0; i < 5; i++) {
    const b = buf[p++];
    if (i === 0 && b === 0x80) throw new Error('UIntBase128: leading zero');
    result = result * 128 + (b & 0x7f);
    if ((b & 0x80) === 0) return [result, p];
  }
  throw new Error('UIntBase128: too long');
}

function parseWoff2(file) {
  const buf = fs.readFileSync(file);
  if (buf.toString('latin1', 0, 4) !== 'wOF2') throw new Error('not woff2: ' + file);
  const numTables = buf.readUInt16BE(12);
  const totalCompressedSize = buf.readUInt32BE(20);

  let p = 48; // конец фиксированной части заголовка woff2
  const dir = [];
  for (let i = 0; i < numTables; i++) {
    const flags = buf[p++];
    const tagIndex = flags & 0x3f;
    const transformVersion = (flags >> 6) & 0x03;
    let tag;
    if (tagIndex === 63) { tag = buf.toString('latin1', p, p + 4); p += 4; }
    else { tag = KNOWN_TAGS[tagIndex]; }
    let origLength;
    const r1 = readBase128(buf, p); origLength = r1[0]; p = r1[1];
    const isGlyfLoca = (tag === 'glyf' || tag === 'loca');
    const hasTransformLength = isGlyfLoca ? (transformVersion === 0) : (transformVersion !== 0);
    let transformLength = null;
    if (hasTransformLength) { const r2 = readBase128(buf, p); transformLength = r2[0]; p = r2[1]; }
    dir.push({ tag, transformVersion, origLength, transformLength });
  }

  const compressed = buf.subarray(p, p + totalCompressedSize);
  const data = zlib.brotliDecompressSync(compressed);

  let off = 0;
  const tables = {};
  for (const t of dir) {
    const len = t.transformLength === null ? t.origLength : t.transformLength;
    tables[t.tag] = { buf: data.subarray(off, off + len), meta: t };
    off += len;
  }
  return { tables, dir, decompressedLength: data.length };
}

function parseCmapFormat4(b, off) {
  const segCountX2 = b.readUInt16BE(off + 6);
  const segCount = segCountX2 / 2;
  const endO = off + 14;
  const startO = endO + segCountX2 + 2;
  const deltaO = startO + segCountX2;
  const rangeO = deltaO + segCountX2;
  const map = new Map();
  for (let s = 0; s < segCount; s++) {
    const end = b.readUInt16BE(endO + s * 2);
    const start = b.readUInt16BE(startO + s * 2);
    const delta = b.readInt16BE(deltaO + s * 2);
    const rangeOffset = b.readUInt16BE(rangeO + s * 2);
    if (start === 0xffff) continue;
    for (let c = start; c <= end && c !== 0x10000; c++) {
      let gid;
      if (rangeOffset === 0) {
        gid = (c + delta) & 0xffff;
      } else {
        const gi = rangeO + s * 2 + rangeOffset + (c - start) * 2;
        if (gi + 1 >= b.length) continue;
        gid = b.readUInt16BE(gi);
        if (gid !== 0) gid = (gid + delta) & 0xffff;
      }
      if (gid !== 0) map.set(c, gid);
    }
  }
  return map;
}

function parseCmapFormat12(b, off) {
  const nGroups = b.readUInt32BE(off + 12);
  const map = new Map();
  for (let g = 0; g < nGroups; g++) {
    const o = off + 16 + g * 12;
    const start = b.readUInt32BE(o);
    const end = b.readUInt32BE(o + 4);
    const startGid = b.readUInt32BE(o + 8);
    for (let c = start; c <= end; c++) map.set(c, startGid + (c - start));
  }
  return map;
}

function buildFont(file) {
  const parsed = parseWoff2(file);
  const tables = parsed.tables;
  for (const t of ['head', 'hhea', 'hmtx', 'maxp', 'cmap']) {
    if (!tables[t]) throw new Error('missing table ' + t + ' in ' + file);
  }
  const unitsPerEm = tables.head.buf.readUInt16BE(18);
  const numGlyphs = tables.maxp.buf.readUInt16BE(4);
  const numberOfHMetrics = tables.hhea.buf.readUInt16BE(34);

  // hmtx может быть с трансформацией woff2 (версия 1): впереди байт флагов,
  // массив advanceWidth идёт подряд, без lsb.
  const hm = tables.hmtx;
  const advances = new Array(numberOfHMetrics);
  let hmtxMode;
  if (hm.meta.transformVersion !== 0) {
    hmtxMode = 'transformed v' + hm.meta.transformVersion;
    for (let i = 0; i < numberOfHMetrics; i++) advances[i] = hm.buf.readUInt16BE(1 + i * 2);
  } else {
    hmtxMode = 'untransformed';
    for (let i = 0; i < numberOfHMetrics; i++) advances[i] = hm.buf.readUInt16BE(i * 4);
  }

  // cmap: берём лучшую unicode-подтаблицу
  const cb = tables.cmap.buf;
  const nSub = cb.readUInt16BE(2);
  let best = null;
  for (let i = 0; i < nSub; i++) {
    const platform = cb.readUInt16BE(4 + i * 8);
    const encoding = cb.readUInt16BE(6 + i * 8);
    const offset = cb.readUInt32BE(8 + i * 8);
    const format = cb.readUInt16BE(offset);
    if (format !== 4 && format !== 12) continue;
    const score = (format === 12 ? 3 : 0) + (platform === 3 && (encoding === 1 || encoding === 10) ? 2 : 0);
    if (!best || score > best.score) best = { platform, encoding, offset, format, score };
  }
  if (!best) throw new Error('no usable cmap subtable in ' + file);
  const map = best.format === 12 ? parseCmapFormat12(cb, best.offset) : parseCmapFormat4(cb, best.offset);

  return {
    file: path.basename(file),
    unitsPerEm, numGlyphs, numberOfHMetrics, hmtxMode,
    cmapFormat: best.format, cmapEntries: map.size,
    decompressedLength: parsed.decompressedLength,
    tableTags: parsed.dir.map(d => d.tag + (d.transformVersion ? '(tv' + d.transformVersion + ')' : '')).join(' '),
    hasKern: !!tables['kern'],
    hasGPOS: !!tables['GPOS'],
    advanceEm: function (ch) {
      const gid = map.get(ch.codePointAt(0));
      if (gid === undefined) return null;
      const a = advances[Math.min(gid, numberOfHMetrics - 1)];
      return a / unitsPerEm;
    },
  };
}

// Жадная разбивка на строки. Точки переноса: после пробела, дефиса и тире.
function layout(runs, sizePx, availPx) {
  const chars = [];
  for (const r of runs) for (const ch of r.text) chars.push({ ch: ch, font: r.font });

  const tokens = [];
  let cur = [];
  for (let i = 0; i < chars.length; i++) {
    cur.push(chars[i]);
    const c = chars[i].ch;
    const breakAfter = (c === ' ' || c === '-' || c === '—' || c === '–');
    const nextIsSpace = i + 1 < chars.length && chars[i + 1].ch === ' ';
    if (breakAfter && !nextIsSpace) { tokens.push(cur); cur = []; }
  }
  if (cur.length) tokens.push(cur);

  function tokW(t, trimTrailingSpace) {
    let w = 0;
    for (let i = 0; i < t.length; i++) {
      if (trimTrailingSpace && i === t.length - 1 && t[i].ch === ' ') continue;
      const em = t[i].font.advanceEm(t[i].ch);
      if (em !== null) w += em * sizePx;
    }
    return w;
  }

  const lines = [];
  let line = [];
  for (const t of tokens) {
    const trial = line.concat(t);
    if (line.length && tokW(trial, true) > availPx + 0.01) { lines.push(line); line = t; }
    else { line = trial; }
  }
  if (line.length) lines.push(line);

  const missing = [];
  for (const c of chars) if (c.font.advanceEm(c.ch) === null) missing.push(c.ch);

  return {
    lines: lines.length,
    lineTexts: lines.map(function (l) { return l.map(function (c) { return c.ch; }).join(''); }),
    lineWidths: lines.map(function (l) { return Math.round(tokW(l, true) * 10) / 10; }),
    totalWidth: Math.round(tokW(chars, true) * 10) / 10,
    missing: missing,
  };
}

module.exports = { buildFont: buildFont, layout: layout };

if (require.main === module) {
  const FDIR = 'wp-content/themes/razil/assets/fonts/';
  const serif = buildFont(FDIR + 'pt-serif-400.woff2');
  const sans400 = buildFont(FDIR + 'pt-sans-400.woff2');
  const sans700 = buildFont(FDIR + 'pt-sans-700.woff2');

  console.log('######## РАЗОБРАННЫЕ ШРИФТЫ ########');
  [serif, sans400, sans700].forEach(function (f) {
    console.log(f.file + ': unitsPerEm=' + f.unitsPerEm + ' numGlyphs=' + f.numGlyphs
      + ' hMetrics=' + f.numberOfHMetrics + ' hmtx=' + f.hmtxMode
      + ' cmap=fmt' + f.cmapFormat + '/' + f.cmapEntries + ' симв.'
      + ' kern=' + f.hasKern + ' GPOS=' + f.hasGPOS);
    console.log('  таблицы: ' + f.tableTags);
  });

  console.log('\n######## КОНТРОЛЬ: ширины отдельных глифов (em) ########');
  [['PT Serif 400', serif], ['PT Sans 400', sans400], ['PT Sans 700', sans700]].forEach(function (pair) {
    const name = pair[0], f = pair[1];
    const probe = ['о', 'ш', 'В', ' ', 'М', 'и', 'л', '-', '—'];
    const s = probe.map(function (c) {
      const a = f.advanceEm(c);
      return "'" + c + "'=" + (a === null ? 'НЕТ' : a.toFixed(4));
    }).join('  ');
    console.log(name + ': ' + s);
    const alpha = 'абвгдеёжзийклмнопрстуфхцчшщъыьэюя';
    let sum = 0, n = 0;
    for (const c of alpha) { const a = f.advanceEm(c); if (a !== null) { sum += a; n++; } }
    console.log('  средняя ширина строчной кириллицы: ' + (sum / n).toFixed(4) + ' em (по ' + n + ' буквам)');
  });
}
