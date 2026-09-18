<?php
/**
 * Этап 5. Итоговая проверка. Только чтение, ничего не пишет.
 */
require_once __DIR__ . '/../../wp-load.php';
require_once __DIR__ . '/edit-01-defs.php';

$file = rz_front_path();
$after = file_get_contents($file);
$origBackup = dirname(__DIR__, 2) . '/_backup/t2/front-page.html.20260918-014234';
$orig = file_get_contents($origBackup);

$bs = chr(92);
$esc = $bs . 'u002d' . $bs . 'u002d';
$dash = chr(45) . chr(45);

/* ================================================================= 5.1 */
echo "################################################################\n";
echo "## 5.1. КРУГОВОРОТ ПО ВСЕМУ ФАЙЛУ\n";
echo "################################################################\n";
$round = serialize_blocks(parse_blocks($after));
printf("файл      : %d байт, md5 %s\n", strlen($after), md5($after));
printf("круговорот: %d байт, md5 %s\n", strlen($round), md5($round));
printf("identical : %s\n", $after === $round ? 'YES (побайтово)' : 'NO');
if ($after !== $round) {
    $n = min(strlen($after), strlen($round));
    for ($i = 0; $i < $n; $i++) { if ($after[$i] !== $round[$i]) { break; } }
    printf("первое расхождение на байте %d\n", $i);
    echo "файл      >>>" . substr($after, max(0, $i - 120), 240) . "<<<\n";
    echo "круговорот>>>" . substr($round, max(0, $i - 120), 240) . "<<<\n";
}

/* ================================================================= 5.2 */
echo "\n################################################################\n";
echo "## 5.2. ИТОГОВОЕ ДЕРЕВО ВЕРХНЕГО УРОВНЯ\n";
echo "################################################################\n";
$nowBlocks = parse_blocks($after);
$origBlocks = parse_blocks($orig);

printf("узлов сейчас: %d   узлов в разведке этапа 1: %d   -> %s\n\n",
    count($nowBlocks), count($origBlocks),
    count($nowBlocks) === count($origBlocks) ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ');

$describe = function ($blocks) {
    $out = [];
    foreach ($blocks as $i => $b) {
        $name = $b['blockName'] === null ? '(null)' : $b['blockName'];
        if ($name === '(null)' && trim($b['innerHTML']) === '') { $name = '(whitespace)'; }
        $cn = isset($b['attrs']['className']) ? $b['attrs']['className'] : '';
        $out[$i] = [$name, $cn];
    }
    return $out;
};
$dNow = $describe($nowBlocks);
$dOrig = $describe($origBlocks);

printf("%-4s %-22s %-42s %s\n", '#', 'blockName', 'className', 'сверка с этапом 1');
echo str_repeat('=', 100), "\n";
$treeOk = true;
foreach ($dNow as $i => $row) {
    $same = isset($dOrig[$i]) && $dOrig[$i][0] === $row[0] && $dOrig[$i][1] === $row[1];
    if (!$same) { $treeOk = false; }
    printf("%-4d %-22s %-42s %s\n", $i, $row[0], $row[1] === '' ? '-' : $row[1],
        $same ? 'совпадает' : 'ОТЛИЧАЕТСЯ (было: ' . ($dOrig[$i][0] ?? '?') . ' / ' . ($dOrig[$i][1] ?? '?') . ')');
}
echo str_repeat('=', 100), "\n";
printf("состав, порядок и className 19 узлов: %s\n", $treeOk ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ');

/* ================================================================= 5.3 */
echo "\n################################################################\n";
echo "## 5.3. ВСЕ className С ДВОЙНЫМ ДЕФИСОМ\n";
echo "################################################################\n";

// экранированные формы в JSON делимитеров
preg_match_all('/"className":"([^"]*)"/', $after, $mJson, PREG_OFFSET_CAPTURE);
$withEsc = [];
foreach ($mJson[1] as $hit) {
    if (strpos($hit[0], $esc) === false) { continue; }
    $line = substr_count(substr($after, 0, $hit[1]), "\n") + 1;
    $withEsc[] = ['json' => $hit[0], 'line' => $line, 'decoded' => str_replace($esc, $dash, $hit[0])];
}
// литеральные формы в class= у HTML
preg_match_all('/class="([^"]*)"/', $after, $mHtml, PREG_OFFSET_CAPTURE);
$withLit = [];
foreach ($mHtml[1] as $hit) {
    if (strpos($hit[0], $dash) === false) { continue; }
    $line = substr_count(substr($after, 0, $hit[1]), "\n") + 1;
    $withLit[] = ['html' => $hit[0], 'line' => $line];
}

printf("найдено в JSON делимитеров: %d\n", count($withEsc));
printf("найдено в атрибутах class : %d\n\n", count($withLit));

printf("%-6s %-52s %s\n", 'стр.', 'в JSON (экранированное)', 'в HTML-классе (литеральное)');
echo str_repeat('=', 118), "\n";
foreach ($withEsc as $k => $e) {
    $pair = '';
    foreach ($withLit as $l) {
        if ($l['line'] === $e['line'] + 1 || $l['line'] === $e['line']) { $pair = $l['html'] . '  [стр. ' . $l['line'] . ']'; break; }
    }
    printf("%-6d %-52s %s\n", $e['line'], $e['json'], $pair !== '' ? $pair : '(парная строка не найдена)');
}
echo str_repeat('=', 118), "\n";

echo "\n-- по каждому двойному дефису отдельно --\n";
foreach ([$dash . 'surface', $dash . 'warn'] as $suffix) {
    printf("  суффикс '%s': экранированных %d, литеральных %d\n", $suffix,
        substr_count($after, str_replace($dash, $esc, $suffix)),
        substr_count($after, $suffix));
}
printf("\nвсего экранированных последовательностей в файле: %d\n", substr_count($after, $esc));
printf("всего литеральных '--' вне делимитеров комментариев: считаем ниже\n");

$viol = 0; $checked = 0;
foreach (explode("\r\n", $after) as $ln => $line) {
    $t = ltrim($line, "\t ");
    if (strpos($t, '<!-- wp:') !== 0 && strpos($t, '<!-- /wp:') !== 0) { continue; }
    $checked++;
    $inner = substr($t, 4);
    $tail = strrpos($inner, '-->');
    if ($tail !== false) { $inner = substr($inner, 0, $tail); }
    $inner = rtrim($inner, '/');
    if (strpos($inner, $dash) !== false) { $viol++; printf("  НАРУШЕНИЕ строка %d: %s\n", $ln + 1, $line); }
}
printf("строк-делимитеров проверено: %d, нарушений: %d -> %s\n", $checked, $viol, $viol === 0 ? 'OK' : 'ОШИБКА');

/* ================================================================= 5.4 */
echo "\n################################################################\n";
echo "## 5.4. БАЙТОВАЯ ЦЕЛОСТНОСТЬ\n";
echo "################################################################\n";
$cr = substr_count($after, "\r");
$lf = substr_count($after, "\n");
$crlf = substr_count($after, "\r\n");
printf("размер : %d байт\n", strlen($after));
printf("md5    : %s\n", md5($after));
printf("строк  : %d\n", count(explode("\r\n", $after)));
printf("CR (0d): %d\n", $cr);
printf("LF (0a): %d\n", $lf);
printf("CRLF пар: %d\n", $crlf);
printf("одиночных CR: %d -> %s\n", $cr - $crlf, ($cr - $crlf) === 0 ? 'OK' : 'ОШИБКА');
printf("одиночных LF: %d -> %s\n", $lf - $crlf, ($lf - $crlf) === 0 ? 'OK' : 'ОШИБКА');
printf("первые 8 байт : %s\n", implode(' ', str_split(bin2hex(substr($after, 0, 8)), 2)));
printf("последние 2 байта: %s -> заканчивается CRLF: %s\n",
    implode(' ', str_split(bin2hex(substr($after, -2)), 2)),
    substr($after, -2) === "\r\n" ? 'да' : 'НЕТ');
printf("BOM: %s\n", substr($after, 0, 3) === "\xEF\xBB\xBF" ? 'ЕСТЬ - ПЛОХО' : 'нет');

echo "\n-- история размеров и md5 по этапам --\n";
printf("%-34s %-8s %s\n", 'этап', 'байт', 'md5');
$hist = [
    'исходное (до этапа 2)'  => [22888, 'baf9fcc444ce31c2431c3723f200a7ff'],
    '2.1 касса -> ККТ'       => [22995, '7a8044f9270a93ab6ba1c5ae4031371c'],
    '2.2 для захоронения'    => [23025, '3a989f206f354d84a55162290656ccd8'],
    '2.3 заголовок'          => [23082, '38a72d582a4249b6edf10d553c3d10a5'],
    '2-бис регистр'          => [23082, '9a1bfee7863b88de65233493ddc0cef5'],
    '3.1 перестановка'       => [23082, '528c6d90d9568c693af9c3e2583fd392'],
    '3.2 текст пункта'       => [23200, '2b81f2d56df54cce0a5ac29ffb281f91'],
    '3.3 класс на tel'       => [23325, 'cf67788a54a5fdf0f9b74368d1e065a8'],
    '4.1 обёртка callout'    => [23491, '86741a1827f5ad465e20b0f385b157bc'],
    '4.2 кнопка отзыва'      => [23746, '675debf02b66d0a80a0d3413dbfef66f'],
];
foreach ($hist as $stage => $pair) { printf("%-34s %-8d %s\n", $stage, $pair[0], $pair[1]); }
printf("\nтекущий файл совпадает с последней строкой: %s\n",
    md5($after) === '675debf02b66d0a80a0d3413dbfef66f' && strlen($after) === 23746 ? 'ДА' : 'НЕТ');

/* ================================================================= 5.6 */
echo "\n################################################################\n";
echo "## 5.6. СКВОЗНОЙ DIFF ЭТАПОВ 2-4 (LCS, с номерами строк)\n";
echo "################################################################\n";
printf("от: %s (%d байт)\n", basename($origBackup), strlen($orig));
printf("до: templates/front-page.html (%d байт)\n\n", strlen($after));

$a = explode("\r\n", $orig);
$b = explode("\r\n", $after);
$n = count($a); $m = count($b);
printf("строк было: %d, строк стало: %d (%+d)\n\n", $n, $m, $m - $n);

// LCS по строкам
$L = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
for ($i = $n - 1; $i >= 0; $i--) {
    for ($j = $m - 1; $j >= 0; $j--) {
        $L[$i][$j] = ($a[$i] === $b[$j]) ? $L[$i + 1][$j + 1] + 1 : max($L[$i + 1][$j], $L[$i][$j + 1]);
    }
}
$i = 0; $j = 0; $del = 0; $add = 0;
while ($i < $n && $j < $m) {
    if ($a[$i] === $b[$j]) { $i++; $j++; continue; }
    if ($L[$i + 1][$j] >= $L[$i][$j + 1]) {
        printf("-%4d              | %s\n", $i + 1, $a[$i]);
        $i++; $del++;
    } else {
        printf("       +%4d       | %s\n", $j + 1, $b[$j]);
        $j++; $add++;
    }
}
while ($i < $n) { printf("-%4d              | %s\n", $i + 1, $a[$i]); $i++; $del++; }
while ($j < $m) { printf("       +%4d       | %s\n", $j + 1, $b[$j]); $j++; $add++; }
printf("\nудалено строк: %d, добавлено строк: %d\n", $del, $add);

/* ================================================================= 5.7 */
echo "\n################################################################\n";
echo "## 5.7. ГОТОВЫЕ КУСКИ ДЛЯ СОСЕДА ПО СТИЛЯМ\n";
echo "################################################################\n";

echo "\n===== 1. ОБЁРТКА ПРЕДУПРЕЖДЕНИЯ =====\n";
echo "\n--- как в файле (templates/front-page.html) ---\n";
$grpStart = strpos($after, '<!-- wp:group {"className":"rz-callout');
$lineStart = strrpos(substr($after, 0, $grpStart), "\r\n") + 2;
$grpEnd = strpos($after, '<!-- /wp:group -->', $grpStart) + strlen('<!-- /wp:group -->');
echo substr($after, $lineStart, $grpEnd - $lineStart) . "\n";

echo "\n--- как в DOM (после рендера) ---\n";
$blocks5 = parse_blocks($after);
$callout = null;
foreach ($blocks5[5]['innerBlocks'] as $b) {
    if ($b['blockName'] === 'core/group' && isset($b['attrs']['className'])
        && strpos($b['attrs']['className'], 'rz-callout') !== false) { $callout = $b; }
}
echo trim(do_blocks(serialize_block($callout))) . "\n";
if (preg_match('/<div class="([^"]*)"/', do_blocks(serialize_block($callout)), $mc)) {
    echo "\nцепочка классов обёртки: " . $mc[1] . "\n";
}
echo "\nВНИМАНИЕ для соседа: margin-top: 1.5rem !important сейчас на самом абзаце\n";
echo ".rz-first-aid__warning, а не на обёртке. Пока у .rz-callout нет padding,\n";
echo "поле схлопывается наружу и работает зазором. Как только у .rz-callout--warn\n";
echo "появится padding или border, эти 1.5rem окажутся ВНУТРИ плашки.\n";
echo "Оформление плашки (padding, background, border, border-radius) сейчас тоже\n";
echo "на абзаце — main.css строка 2757.\n";

echo "\n===== 2. ОДНА tel:-ССЫЛКА С КЛАССОМ =====\n";
echo "\n--- как в файле ---\n";
if (preg_match('#<a class="rz-emergency-tel" href="tel:103">103</a>#', $after, $mt)) { echo $mt[0] . "\n"; }
echo "\n--- все пять, как в файле ---\n";
preg_match_all('#<a class="rz-emergency-tel" href="[^"]*">[^<]*</a>#', $after, $mall);
foreach ($mall[0] as $k => $x) { printf("  [%d] %s\n", $k + 1, $x); }
echo "\n--- строка целиком, для контекста ---\n";
foreach (explode("\r\n", $after) as $ln => $line) {
    if (strpos($line, 'rz-emergency-tel') !== false) { printf("%d| %s\n", $ln + 1, $line); }
}
echo "\nв DOM класс остаётся как есть, ядро к <a> внутри list-item ничего не добавляет.\n";
echo "Не задеты: <a href=\"tel:112\"> в абзаце rz-first-aid__note и кнопка героя.\n";

echo "\n===== 3. КНОПКА ОТЗЫВА =====\n";
echo "\n--- как в файле ---\n";
$btnStart = strpos($after, '<!-- wp:button {"className":"rz-btn-outline"} -->');
$btnLineStart = strrpos(substr($after, 0, $btnStart), "\r\n") + 2;
$btnEnd = strpos($after, '<!-- /wp:button -->', $btnStart) + strlen('<!-- /wp:button -->');
echo substr($after, $btnLineStart, $btnEnd - $btnLineStart) . "\n";

echo "\n--- как в DOM ---\n";
$btn = null;
foreach ($nowBlocks[13]['innerBlocks'][0]['innerBlocks'] as $b) {
    if ($b['blockName'] === 'core/button') { $btn = $b; }
}
echo trim(do_blocks(serialize_block($btn))) . "\n";
echo "\nработающие правила: .rz-btn-outline .wp-block-button__link (0,2,0) из main.css:2650\n";
echo "бьёт :root :where(.wp-element-button, .wp-block-button__link) (0,1,0) из global-styles.\n";
echo "has-surface-color не выставлен намеренно: .has-surface-color { color: ... !important }\n";
echo "перебил бы color: var(--rz-action) аутлайна.\n";

echo "\n################################################################\n";
echo "## СВОДНЫЙ ВЕРДИКТ\n";
echo "################################################################\n";
$final = [
    '5.1 круговорот побайтово'        => $after === $round,
    '5.2 дерево 19 узлов совпало'     => $treeOk && count($nowBlocks) === 19,
    '5.3 нарушений двойного дефиса 0' => $viol === 0,
    '5.4 одиночных CR/LF нет'         => ($cr - $crlf) === 0 && ($lf - $crlf) === 0,
    '5.4 BOM нет'                     => substr($after, 0, 3) !== "\xEF\xBB\xBF",
    '5.4 файл кончается CRLF'         => substr($after, -2) === "\r\n",
];
$allOk = true;
foreach ($final as $k => $v) { printf("  %-34s %s\n", $k, $v ? 'OK' : 'ОШИБКА'); if (!$v) { $allOk = false; } }
echo "\nЭТАП 5: " . ($allOk ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ') . "\n";
