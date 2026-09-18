<?php
/**
 * Этап 4.1. Обёртка абзаца предупреждения в core/group rz-callout rz-callout--warn.
 *
 * Экранированная последовательность собирается в коде из chr(92):
 * ни один символ её литеральной формы в тексте этого файла не встречается,
 * иначе транспортный слой записи декодировал бы её в настоящий дефис.
 */
require_once __DIR__ . '/../../wp-load.php';
require_once __DIR__ . '/edit-01-defs.php';

$file = rz_front_path();
$content = file_get_contents($file);

$baseline = file(__DIR__ . '/stage4-baseline.txt', FILE_IGNORE_NEW_LINES);
$baseMd5 = $baseline[0];
$baseLen = (int) $baseline[1];
$backup = dirname(__DIR__, 2) . '/_backup/t2/' . $baseline[2];

echo "######## ОПОРНАЯ ТОЧКА ########\n";
printf("ожидаемый md5: %s (%d байт)\n", $baseMd5, $baseLen);
printf("фактический  : %s (%d байт)\n", md5($content), strlen($content));
if (md5($content) !== $baseMd5) { echo "STOP: файл не в ожидаемом состоянии\n"; exit(1); }
echo "СОВПАЛО\n";

/* ---------------------------------------------- сборка экранирования */
echo "\n######## СБОРКА ЭКРАНИРОВАНИЯ ########\n";
$bs  = chr(92);
$esc = $bs . 'u002d' . $bs . 'u002d';
$cls = 'rz-callout rz-callout' . $esc . 'warn';

printf("chr(92) = %s (hex %s)\n", $bs, bin2hex($bs));
printf("собранная последовательность, hex: %s\n", implode(' ', str_split(bin2hex($esc), 2)));
printf("собранная последовательность, длина: %d байт\n", strlen($esc));
printf("className для JSON, hex начала: %s ...\n", implode(' ', str_split(bin2hex(substr($cls, 0, 24)), 2)));
printf("className для JSON как текст: %s\n", $cls);

$probeJson = '{"className":"' . $cls . '"}';
printf("\nпроверочный JSON: %s\n", $probeJson);
$decoded = json_decode($probeJson, true);
if (!is_array($decoded) || !isset($decoded['className'])) {
    echo "STOP: json_decode не разобрал строку\n"; exit(1);
}
printf("json_decode дал className: %s\n", var_export($decoded['className'], true));
$wantDecoded = 'rz-callout rz-callout' . chr(45) . chr(45) . 'warn';
$decodeOk = ($decoded['className'] === $wantDecoded);
printf("ожидалось                : %s\n", var_export($wantDecoded, true));
printf("СОВПАЛО: %s\n", $decodeOk ? 'YES' : 'NO');
if (!$decodeOk) { echo "STOP\n"; exit(1); }

// Контроль, что литеральной формы в тексте этого скрипта нет.
$self = file_get_contents(__FILE__);
printf("\nлитеральной последовательности в теле скрипта: %d вхождений (ожидается 0)\n",
    substr_count($self, $esc));

/* ---------------------------------------------- old_str из файла */
echo "\n######## а) OLD_STR: три строки абзаца [5.4] ########\n";

$marker = '<!-- wp:paragraph {"className":"rz-first-aid__warning"} -->';
$nMarker = substr_count($content, $marker);
printf("вхождений открывающего делимитера абзаца: %d -> %s\n", $nMarker, $nMarker === 1 ? 'OK' : 'ОШИБКА');
if ($nMarker !== 1) { echo "STOP\n"; exit(1); }

$mPos = strpos($content, $marker);
$prevBreak = strrpos(substr($content, 0, $mPos), "\r\n");
$oStart = ($prevBreak === false) ? 0 : $prevBreak + 2;
$indent = substr($content, $oStart, $mPos - $oStart);
printf("ведущий отступ абзаца: %d байт, hex %s -> только табы: %s\n",
    strlen($indent), bin2hex($indent), trim($indent, "\t") === '' ? 'да' : 'НЕТ');
if (trim($indent, "\t") !== '') { echo "STOP\n"; exit(1); }

$closer = '<!-- /wp:paragraph -->';
$cPos = strpos($content, $closer, $mPos);
if ($cPos === false) { echo "STOP: закрывающий делимитер не найден\n"; exit(1); }
$afterCloser = $cPos + strlen($closer);
if (substr($content, $afterCloser, 2) !== "\r\n") {
    printf("STOP: после закрывающего делимитера нет CRLF, там %s\n", bin2hex(substr($content, $afterCloser, 2)));
    exit(1);
}
$oEnd = $afterCloser + 2;
$old = substr($content, $oStart, $oEnd - $oStart);

printf("\nold_str: байты %d..%d, длина %d\n", $oStart, $oEnd - 1, strlen($old));
printf("строк в old_str: %d\n", substr_count($old, "\r\n"));
printf("первые 30 байт hex: %s\n", implode(' ', str_split(bin2hex(substr($old, 0, 30)), 2)));
printf("последние 30 байт hex: %s\n", implode(' ', str_split(bin2hex(substr($old, -30)), 2)));
echo "old_str целиком:\n>>>" . $old . "<<<\n";

/* ---------------------------------------------- new_str */
echo "\n######## а) NEW_STR: те же строки с лишним табом, в группе ########\n";

// каждой непустой строке old_str добавляем ровно один ведущий таб
$oldLines = explode("\r\n", $old);
$innerLines = [];
foreach ($oldLines as $i => $l) {
    if ($i === count($oldLines) - 1 && $l === '') { continue; } // хвостовой пустой элемент от финального CRLF
    $innerLines[] = "\t" . $l;
}
printf("строк перенесено внутрь группы: %d\n", count($innerLines));
foreach ($innerLines as $i => $l) {
    printf("  строка %d: отступ %d табов\n", $i + 1, strlen($l) - strlen(ltrim($l, "\t")));
}

$T = $indent; // отступ группы — ровно как был у абзаца
$new = $T . '<!-- wp:group {"className":"' . $cls . '"} -->' . "\r\n"
     . $T . '<div class="wp-block-group rz-callout rz-callout' . chr(45) . chr(45) . 'warn">' . "\r\n"
     . implode("\r\n", $innerLines) . "\r\n"
     . $T . '</div>' . "\r\n"
     . $T . '<!-- /wp:group -->' . "\r\n";

printf("\nnew_str: длина %d байт, строк %d\n", strlen($new), substr_count($new, "\r\n"));
printf("прирост строк: %+d (ожидалось +4)\n", substr_count($new, "\r\n") - substr_count($old, "\r\n"));
printf("прирост байт : %+d\n", strlen($new) - strlen($old));
echo "new_str целиком:\n>>>" . $new . "<<<\n";

// текст абзаца не изменился ни на байт
if (!preg_match('#<p class="rz-first-aid__warning">.*</p>#us', $old, $mOldP)) { echo "STOP: абзац не найден в old\n"; exit(1); }
if (!preg_match('#<p class="rz-first-aid__warning">.*</p>#us', $new, $mNewP)) { echo "STOP: абзац не найден в new\n"; exit(1); }
printf("\nтекст абзаца old == new побайтово: %s (%d байт, md5 %s)\n",
    $mOldP[0] === $mNewP[0] ? 'YES' : 'NO', strlen($mOldP[0]), md5($mOldP[0]));
if ($mOldP[0] !== $mNewP[0]) { echo "STOP\n"; exit(1); }

/* ---------------------------------------------- кандидат */
echo "\n######## а) КАНДИДАТ В ПАМЯТИ ########\n";
$cnt = 0;
$cand = str_replace($old, $new, $content, $cnt);
printf("str_replace count: %d -> %s\n", $cnt, $cnt === 1 ? 'OK' : 'ОШИБКА');
if ($cnt !== 1) { echo "STOP: не пишу\n"; exit(1); }
printf("кандидат: %d байт (было %d, %+d)\n", strlen($cand), strlen($content), strlen($cand) - strlen($content));

/* ---------------------------------------------- б) проверки на кандидате */
echo "\n######## б) ПРОВЕРКИ НА КАНДИДАТЕ, ДО ЗАПИСИ ########\n";

$round = serialize_blocks(parse_blocks($cand));
$roundOk = ($round === $cand);
printf("serialize_blocks(parse_blocks(cand)) === cand: %s\n", $roundOk ? 'YES' : 'NO');
if (!$roundOk) {
    $n = min(strlen($cand), strlen($round));
    for ($i = 0; $i < $n; $i++) { if ($cand[$i] !== $round[$i]) { break; } }
    printf("первое расхождение на байте %d\n", $i);
    echo "кандидат  >>>" . substr($cand, max(0, $i - 120), 240) . "<<<\n";
    echo "круговорот>>>" . substr($round, max(0, $i - 120), 240) . "<<<\n";
    echo "STOP: не пишу\n";
    exit(1);
}

echo "\n-- греп: литеральный '--' в строках делимитеров блоков --\n";
$dash = chr(45) . chr(45);
$viol = 0;
$checked = 0;
foreach (explode("\r\n", $cand) as $ln => $line) {
    $t = ltrim($line, "\t ");
    if (strpos($t, '<!--') !== 0) { continue; }
    if (strpos($t, '<!-- wp:') !== 0 && strpos($t, '<!-- /wp:') !== 0) { continue; }
    $checked++;
    // снимаем сами делимитеры комментария
    $inner = $t;
    $inner = substr($inner, 4);                       // убрали '<!--'
    $tail = strrpos($inner, '-->');
    if ($tail !== false) { $inner = substr($inner, 0, $tail); }
    $inner = rtrim($inner, '/');                      // самозакрывающийся '/-->'
    if (strpos($inner, $dash) !== false) {
        $viol++;
        printf("  НАРУШЕНИЕ строка %d: %s\n", $ln + 1, $line);
    }
}
printf("проверено строк-делимитеров: %d\n", $checked);
printf("нарушений: %d -> %s\n", $viol, $viol === 0 ? 'OK' : 'ОШИБКА');
if ($viol !== 0) { echo "STOP: не пишу\n"; exit(1); }

$nEsc = substr_count($cand, 'rz-callout' . $esc . 'warn');
$nLit = substr_count($cand, 'rz-callout' . $dash . 'warn');
printf("\nэкранированная форма 'rz-callout\\u002d\\u002dwarn' в кандидате: %d -> %s\n",
    $nEsc, $nEsc === 1 ? 'OK (требуется 1)' : 'ОШИБКА');
printf("литеральная форма 'rz-callout--warn' в кандидате: %d -> %s\n",
    $nLit, $nLit === 1 ? 'OK (требуется 1)' : 'ОШИБКА');
if ($nEsc !== 1 || $nLit !== 1) { echo "STOP: не пишу\n"; exit(1); }

echo "\n-- строка с литеральной формой целиком --\n";
$litOk = false;
foreach (explode("\r\n", $cand) as $ln => $line) {
    if (strpos($line, 'rz-callout' . $dash . 'warn') === false) { continue; }
    printf("%d| %s\n", $ln + 1, $line);
    $isDivClass = (bool) preg_match('/^\t*<div class="[^"]*rz-callout' . preg_quote($dash, '/') . 'warn[^"]*">$/', $line);
    printf("это атрибут class у <div> и больше ничего: %s\n", $isDivClass ? 'YES' : 'NO');
    $litOk = $isDivClass;
}
if (!$litOk) { echo "STOP: литеральная форма не в атрибуте class\n"; exit(1); }

/* ---------------------------------------------- в) запись */
echo "\n######## в) ЗАПИСЬ ########\n";
$written = file_put_contents($file, $cand);
printf("file_put_contents вернул: %s (кандидат %d байт)\n", var_export($written, true), strlen($cand));

/* ---------------------------------------------- г) чтение с диска */
echo "\n######## г) ЧТЕНИЕ С ДИСКА И СВЕРКА С КАНДИДАТОМ ########\n";
clearstatcache(true, $file);
$reread = file_get_contents($file);
printf("прочитанное с диска: %d байт, md5 %s\n", strlen($reread), md5($reread));
printf("кандидат в памяти  : %d байт, md5 %s\n", strlen($cand), md5($cand));
$transportOk = ($reread === $cand);
printf("ПОБАЙТОВО РАВНО КАНДИДАТУ: %s\n", $transportOk ? 'YES' : 'NO');
if (!$transportOk) {
    $n = min(strlen($reread), strlen($cand));
    for ($i = 0; $i < $n; $i++) { if ($reread[$i] !== $cand[$i]) { break; } }
    printf("первое расхождение на байте %d\n", $i);
    echo "диск   >>>" . substr($reread, max(0, $i - 120), 240) . "<<<\n";
    echo "память >>>" . substr($cand, max(0, $i - 120), 240) . "<<<\n";
}

/* ---------------------------------------------- проверки после записи */
echo "\n######## ПРОВЕРКИ ПОСЛЕ ЗАПИСИ ########\n";

$nWarn = substr_count($reread, 'Не пускайте в дом посторонних ритуальных агентов');
printf("«Не пускайте в дом посторонних ритуальных агентов»: %d -> %s\n", $nWarn, $nWarn === 1 ? 'OK' : 'ОШИБКА');

$before = file_get_contents($backup);
preg_match('#<p class="rz-first-aid__warning">.*</p>#us', $before, $mB);
preg_match('#<p class="rz-first-aid__warning">.*</p>#us', $reread, $mA);
printf("текст абзаца побайтово равен тексту из бэкапа: %s\n", $mB[0] === $mA[0] ? 'YES' : 'NO');
printf("  бэкап : %d байт, md5 %s\n", strlen($mB[0]), md5($mB[0]));
printf("  сейчас: %d байт, md5 %s\n", strlen($mA[0]), md5($mA[0]));

$lines = explode("\r\n", $reread);
$cr = substr_count($reread, "\r");
$lf = substr_count($reread, "\n");
$crlf = substr_count($reread, "\r\n");
printf("\nстрок: %d -> %s (ожидалось 348)\n", count($lines), count($lines) === 348 ? 'OK' : 'ОШИБКА');
printf("CR: %d -> %s (ожидалось 347)\n", $cr, $cr === 347 ? 'OK' : 'ОШИБКА');
printf("LF: %d -> %s (ожидалось 347)\n", $lf, $lf === 347 ? 'OK' : 'ОШИБКА');
printf("одиночных CR: %d   одиночных LF: %d\n", $cr - $crlf, $lf - $crlf);
printf("первые 8 байт: %s   последние 2 байта: %s   BOM: %s\n",
    implode(' ', str_split(bin2hex(substr($reread, 0, 8)), 2)),
    implode(' ', str_split(bin2hex(substr($reread, -2)), 2)),
    substr($reread, 0, 3) === "\xEF\xBB\xBF" ? 'ЕСТЬ' : 'нет');

$expected = $baseLen - strlen($old) + strlen($new);
printf("\nразмер до / после: %d / %d (%+d)\n", $baseLen, strlen($reread), strlen($reread) - $baseLen);
printf("по арифметике: %d - %d + %d = %d -> %s\n", $baseLen, strlen($old), strlen($new), $expected,
    strlen($reread) === $expected ? 'СОШЛОСЬ' : 'НЕ СОШЛОСЬ');
printf("md5 до / после: %s / %s\n", $baseMd5, md5($reread));

echo "\n-- ДЕРЕВО БЛОКА [5] --\n";
$blocks = parse_blocks($reread);
$walk = function ($bs, $path, $depth) use (&$walk) {
    foreach ($bs as $i => $b) {
        $p = $path === '' ? (string) $i : $path . '.' . $i;
        $name = $b['blockName'] === null ? '(null)' : $b['blockName'];
        if ($name === '(null)' && trim($b['innerHTML']) === '') { continue; }
        $cn = isset($b['attrs']['className']) ? $b['attrs']['className'] : '';
        printf("%s[%s] %-18s %s\n", str_repeat('  ', $depth), $p, $name, $cn !== '' ? "className='" . $cn . "'" : '');
        if (!empty($b['innerBlocks'])) { $walk($b['innerBlocks'], $p, $depth + 1); }
    }
};
$walk([5 => $blocks[5]], '', 0);

echo "\n-- className группы: две формы --\n";
$grp = null;
foreach ($blocks[5]['innerBlocks'] as $b) {
    if ($b['blockName'] === 'core/group' && isset($b['attrs']['className'])
        && strpos($b['attrs']['className'], 'rz-callout') !== false) { $grp = $b; }
}
if ($grp === null) { echo "STOP: группа не найдена в дереве\n"; exit(1); }
printf("как вернул parse_blocks    : %s\n", var_export($grp['attrs']['className'], true));
$ser = serialize_block($grp);
if (preg_match('/"className":"([^"]*)"/', $ser, $mS)) {
    printf("как вернул serialize_blocks: %s\n", var_export($mS[1], true));
}

echo "\n-- РЕНДЕР блока [5]: фактические классы обёртки и позиция абзаца --\n";
$renderedAll = do_blocks(serialize_block($blocks[5]));
if (preg_match('/<div class="([^"]*rz-callout[^"]*)"/', $renderedAll, $mR)) {
    echo "цепочка классов обёртки в DOM: " . $mR[1] . "\n";
    foreach (preg_split('/\s+/', trim($mR[1])) as $c) { echo "  - " . $c . "\n"; }
}
printf("\nдетей у группы rz-callout по дереву: %d\n", count($grp['innerBlocks']));
foreach ($grp['innerBlocks'] as $i => $b) {
    $cn = isset($b['attrs']['className']) ? $b['attrs']['className'] : '';
    $marks = [];
    if ($i === 0) { $marks[] = ':first-child'; }
    if ($i === count($grp['innerBlocks']) - 1) { $marks[] = ':last-child'; }
    printf("  %d) %s %s   %s\n", $i + 1, $b['blockName'], $cn, implode(' + ', $marks));
}
printf("абзац — единственный ребёнок и одновременно :first-child и :last-child: %s\n",
    count($grp['innerBlocks']) === 1 ? 'YES' : 'NO');

$ok = $roundOk && $transportOk && $viol === 0 && $nEsc === 1 && $nLit === 1
    && $nWarn === 1 && $mB[0] === $mA[0] && count($lines) === 348
    && $cr === 347 && $lf === 347 && ($cr - $crlf) === 0 && ($lf - $crlf) === 0
    && strlen($reread) === $expected && count($grp['innerBlocks']) === 1;
echo "\nЭТАП 4.1: " . ($ok ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ') . "\n";
