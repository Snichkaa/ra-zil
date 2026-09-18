<?php
/**
 * Этап 3.1. Перестановка трёх list-item блока первой помощи.
 * Работа побайтовая: чанки вырезаются по границам строк и переставляются.
 * Текст внутри чанков не меняется ни на байт.
 */
require_once __DIR__ . '/../../wp-load.php';
require_once __DIR__ . '/edit-01-defs.php';

$file = rz_front_path();
$content = file_get_contents($file);

$baseline = file(__DIR__ . '/stage3-baseline.txt', FILE_IGNORE_NEW_LINES);
$baseMd5 = $baseline[0];
$baseLen = (int) $baseline[1];

echo "######## ОПОРНАЯ ТОЧКА ЭТАПА 3 ########\n";
printf("ожидаемый md5: %s\n", $baseMd5);
printf("фактический  : %s  -> %s\n", md5($content), md5($content) === $baseMd5 ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ');
printf("байт: %d (ожидалось %d)\n", strlen($content), $baseLen);
if (md5($content) !== $baseMd5) { echo "ПРЕРЫВАЮ: файл не в ожидаемом состоянии\n"; exit(1); }

$sizeBefore = strlen($content);

// ---------------------------------------------------------------- а) регион
echo "\n######## а) ГРАНИЦЫ РЕГИОНА core/list ########\n";

$openNeedle  = '<!-- wp:list {"ordered":true';
$closeNeedle = '<!-- /wp:list -->';

$nOpen = substr_count($content, $openNeedle);
printf("вхождений '%s' в файле: %d -> %s\n", $openNeedle, $nOpen, $nOpen === 1 ? 'OK' : 'ОШИБКА');
if ($nOpen !== 1) { echo "ПРЕРЫВАЮ\n"; exit(1); }

$regStart = strpos($content, $openNeedle);
$closePos = strpos($content, $closeNeedle, $regStart);
if ($closePos === false) { echo "ПРЕРЫВАЮ: закрывающий тег не найден\n"; exit(1); }
$regEnd = $closePos + strlen($closeNeedle); // экслюзивная граница

// Проверка на вложенные списки внутри региона.
$inner = substr($content, $regStart + strlen($openNeedle), $closePos - ($regStart + strlen($openNeedle)));
$nestedOpen = substr_count($inner, '<!-- wp:list {');
$nItemOpen  = substr_count($inner, '<!-- wp:list-item -->');
$nItemClose = substr_count($inner, '<!-- /wp:list-item -->');
printf("вложенных '<!-- wp:list {' внутри региона: %d -> %s\n", $nestedOpen, $nestedOpen === 0 ? 'OK (вложенности нет)' : 'ОШИБКА');
printf("'<!-- wp:list-item -->'  внутри региона: %d -> %s\n", $nItemOpen, $nItemOpen === 3 ? 'OK' : 'ОШИБКА');
printf("'<!-- /wp:list-item -->' внутри региона: %d -> %s\n", $nItemClose, $nItemClose === 3 ? 'OK' : 'ОШИБКА');
if ($nestedOpen !== 0 || $nItemOpen !== 3 || $nItemClose !== 3) { echo "ПРЕРЫВАЮ\n"; exit(1); }

$lineOfOpen  = substr_count(substr($content, 0, $regStart), "\n") + 1;
$lineOfClose = substr_count(substr($content, 0, $closePos), "\n") + 1;

printf("\nначало региона : байт %d (строка %d)\n", $regStart, $lineOfOpen);
printf("конец региона  : байт %d включительно, экслюзивно %d (строка %d)\n", $regEnd - 1, $regEnd, $lineOfClose);
printf("длина региона  : %d байт\n", $regEnd - $regStart);
echo "\n-- регион целиком --\n>>>" . substr($content, $regStart, $regEnd - $regStart) . "<<<\n";

// ---------------------------------------------------------------- б) чанки
echo "\n######## б) ЧАНКИ list-item ########\n";

$itemOpen  = '<!-- wp:list-item -->';
$itemClose = '<!-- /wp:list-item -->';

$chunks = [];
$scan = $regStart;
for ($i = 0; $i < 3; $i++) {
    $o = strpos($content, $itemOpen, $scan);
    if ($o === false || $o >= $regEnd) { echo "ПРЕРЫВАЮ: не найден открывающий тег пункта {$i}\n"; exit(1); }

    // начало строки, содержащей открывающий тег, вместе с ведущими табами
    $prevBreak = strrpos(substr($content, 0, $o), "\r\n");
    $cStart = ($prevBreak === false) ? 0 : $prevBreak + 2;
    $indent = substr($content, $cStart, $o - $cStart);
    $indentOk = ($indent === '' || trim($indent, "\t") === '');

    $c = strpos($content, $itemClose, $o);
    if ($c === false || $c >= $regEnd) { echo "ПРЕРЫВАЮ: не найден закрывающий тег пункта {$i}\n"; exit(1); }
    $afterClose = $c + strlen($itemClose);
    $tail = substr($content, $afterClose, 2);
    if ($tail !== "\r\n") {
        printf("ПРЕРЫВАЮ: после закрывающего тега пункта %d нет CRLF, там %s\n", $i, bin2hex($tail));
        exit(1);
    }
    $cEnd = $afterClose + 2; // экслюзивно, вместе с CRLF

    $chunks[] = [
        'start'   => $cStart,
        'end'     => $cEnd,
        'bytes'   => substr($content, $cStart, $cEnd - $cStart),
        'indent'  => $indent,
        'indentOk' => $indentOk,
    ];
    $scan = $cEnd;
}

foreach ($chunks as $i => $ch) {
    $b = $ch['bytes'];
    echo str_repeat('-', 74), "\n";
    printf("чанк[%d]: байты %d..%d, длина %d\n", $i, $ch['start'], $ch['end'] - 1, strlen($b));
    printf("  ведущий отступ: %d байт (%s) — только табы: %s\n",
        strlen($ch['indent']), bin2hex($ch['indent']), $ch['indentOk'] ? 'да' : 'НЕТ');
    printf("  первые 40 байт hex: %s\n", implode(' ', str_split(bin2hex(substr($b, 0, 40)), 2)));
    printf("  последние 20 байт hex: %s\n", implode(' ', str_split(bin2hex(substr($b, -20)), 2)));
    printf("  первые 40 байт как текст : >>>%s<<<\n", substr($b, 0, 40));
    printf("  последние 20 байт как текст: >>>%s<<<\n", substr($b, -20));
}
echo str_repeat('-', 74), "\n";

// ---------------------------------------------------------------- в) проверка покрытия
echo "\n######## в) ПРОВЕРКА ПЕРЕД ЗАПИСЬЮ ########\n";

$spanStart = $chunks[0]['start'];
$spanEnd   = $chunks[2]['end'];
$fileSpan  = substr($content, $spanStart, $spanEnd - $spanStart);
$concat    = $chunks[0]['bytes'] . $chunks[1]['bytes'] . $chunks[2]['bytes'];

printf("участок файла: байты %d..%d, длина %d\n", $spanStart, $spanEnd - 1, strlen($fileSpan));
printf("конкатенация : длина %d\n", strlen($concat));
printf("длины равны  : %s\n", strlen($fileSpan) === strlen($concat) ? 'да' : 'НЕТ');

// смежность чанков
$adj01 = ($chunks[0]['end'] === $chunks[1]['start']);
$adj12 = ($chunks[1]['end'] === $chunks[2]['start']);
printf("чанк[0].end == чанк[1].start : %s (%d / %d)\n", $adj01 ? 'да' : 'НЕТ', $chunks[0]['end'], $chunks[1]['start']);
printf("чанк[1].end == чанк[2].start : %s (%d / %d)\n", $adj12 ? 'да' : 'НЕТ', $chunks[1]['end'], $chunks[2]['start']);

$covers = ($fileSpan === $concat) && $adj01 && $adj12;
printf("\nчанки покрывают регион: %s\n", $covers ? 'YES' : 'NO');
if (!$covers) { echo "STOP: не пишу, покрытие не подтверждено\n"; exit(1); }

$indentAll = $chunks[0]['indentOk'] && $chunks[1]['indentOk'] && $chunks[2]['indentOk'];
printf("отступы у всех трёх чанков только из табов: %s\n", $indentAll ? 'YES' : 'NO');
if (!$indentAll) { echo "STOP: не пишу\n"; exit(1); }

// ---------------------------------------------------------------- г) запись
echo "\n######## г) ЗАПИСЬ В ПОРЯДКЕ чанк[2], чанк[0], чанк[1] ########\n";

$newSpan = $chunks[2]['bytes'] . $chunks[0]['bytes'] . $chunks[1]['bytes'];
printf("новый участок: длина %d (старый %d) -> равны: %s\n",
    strlen($newSpan), strlen($fileSpan), strlen($newSpan) === strlen($fileSpan) ? 'YES' : 'NO');

$result = substr($content, 0, $spanStart) . $newSpan . substr($content, $spanEnd);
printf("подготовлено: %d байт\n", strlen($result));

$written = file_put_contents($file, $result);
printf("file_put_contents вернул: %s\n", var_export($written, true));

clearstatcache(true, $file);
$reread = file_get_contents($file);

// ---------------------------------------------------------------- д) проверки
echo "\n######## д) ПРОВЕРКИ ПОСЛЕ ЗАПИСИ ########\n";

$sizeAfter = strlen($reread);
printf("размер до / размер после: %d / %d  -> равны: %s\n",
    $sizeBefore, $sizeAfter, $sizeBefore === $sizeAfter ? 'YES' : 'NO');
printf("md5 до / после: %s / %s\n", $baseMd5, md5($reread));
printf("прочитанное == подготовленному: %s\n", $reread === $result ? 'да' : 'НЕТ');

$cr = substr_count($reread, "\r");
$lf = substr_count($reread, "\n");
$crlf = substr_count($reread, "\r\n");
printf("\nCR: %d -> %s\n", $cr, $cr === 343 ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ');
printf("LF: %d -> %s\n", $lf, $lf === 343 ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ');
printf("одиночных CR: %d -> %s\n", $cr - $crlf, ($cr - $crlf) === 0 ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ');
printf("одиночных LF: %d -> %s\n", $lf - $crlf, ($lf - $crlf) === 0 ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ');
printf("первые 8 байт: %s\n", implode(' ', str_split(bin2hex(substr($reread, 0, 8)), 2)));
printf("последние 2 байта: %s\n", implode(' ', str_split(bin2hex(substr($reread, -2)), 2)));

$round = serialize_blocks(parse_blocks($reread));
printf("\nparse_blocks -> serialize_blocks identical: %s\n", $reread === $round ? 'YES' : 'NO');
if ($reread !== $round) {
    $n = min(strlen($reread), strlen($round));
    for ($i = 0; $i < $n; $i++) { if ($reread[$i] !== $round[$i]) { break; } }
    printf("первое расхождение на байте %d\n", $i);
}

// содержимое чанков не изменилось — только порядок
$multiset_before = [md5($chunks[0]['bytes']), md5($chunks[1]['bytes']), md5($chunks[2]['bytes'])];
sort($multiset_before);
$blocks = parse_blocks($reread);
$list = null;
foreach ($blocks[5]['innerBlocks'] as $b) { if ($b['blockName'] === 'core/list') { $list = $b; } }
printf("\nпунктов в списке после записи: %d\n", count($list['innerBlocks']));

echo "\n-- ПОРЯДОК ПУНКТОВ (первые 60 символов текста) --\n";
foreach ($list['innerBlocks'] as $i => $li) {
    $txt = html_entity_decode(strip_tags($li['innerHTML']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $txt = trim(preg_replace('/\s+/u', ' ', $txt));
    printf("  %d) %s\n", $i + 1, mb_substr($txt, 0, 60, 'UTF-8'));
}

echo "\n-- регион core/list после перестановки --\n";
$ns = strpos($reread, $openNeedle);
$nc = strpos($reread, $closeNeedle, $ns) + strlen($closeNeedle);
echo ">>>" . substr($reread, $ns, $nc - $ns) . "<<<\n";

$ok = $sizeBefore === $sizeAfter && $cr === 343 && $lf === 343
    && ($cr - $crlf) === 0 && ($lf - $crlf) === 0 && $reread === $round && $reread === $result;
echo "\nЭТАП 3.1: " . ($ok ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ') . "\n";
