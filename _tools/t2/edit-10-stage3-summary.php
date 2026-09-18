<?php
/**
 * Этап 3.4. Итог: блок [5] до и после, размеры, md5, построчный diff.
 */
require_once __DIR__ . '/../../wp-load.php';
require_once __DIR__ . '/edit-01-defs.php';

$file = rz_front_path();
$baseline = file(__DIR__ . '/stage3-baseline.txt', FILE_IGNORE_NEW_LINES);
$backup = dirname(__DIR__, 2) . '/_backup/t2/' . $baseline[2];

$before = file_get_contents($backup);
$after  = file_get_contents($file);

echo "################################################################\n";
echo "## БЛОК [5] rz-first-aid — ДО ЭТАПА 3 (из бэкапа " . basename($backup) . ")\n";
echo "################################################################\n";
$bBlocks = parse_blocks($before);
echo $bBlocks[5]['innerHTML'];
echo "\n--- и то же самое в собранном виде (serialize_block) ---\n";
echo serialize_block($bBlocks[5]);

echo "\n\n################################################################\n";
echo "## БЛОК [5] rz-first-aid — ПОСЛЕ ЭТАПА 3\n";
echo "################################################################\n";
$aBlocks = parse_blocks($after);
echo $aBlocks[5]['innerHTML'];
echo "\n--- и то же самое в собранном виде (serialize_block) ---\n";
echo serialize_block($aBlocks[5]);

echo "\n\n################################################################\n";
echo "## РАЗМЕР И MD5\n";
echo "################################################################\n";
printf("%-10s %-8s %-34s %s\n", '', 'байт', 'md5', 'источник');
printf("%-10s %-8d %-34s %s\n", 'ДО',    strlen($before), md5($before), basename($backup));
printf("%-10s %-8d %-34s %s\n", 'ПОСЛЕ', strlen($after),  md5($after),  'templates/front-page.html');
printf("прирост: %+d байт\n", strlen($after) - strlen($before));
echo "\nпо шагам этапа 3:\n";
printf("  3.1 перестановка : 23082 -> 23082  (%+d, перестановка байт в байт)\n", 0);
printf("  3.2 текст пункта : 23082 -> 23200  (%+d)\n", 118);
printf("  3.3 класс на tel : 23200 -> 23325  (%+d)\n", 125);
printf("  итого            : %+d\n", 243);

echo "\n################################################################\n";
echo "## ПОСТРОЧНЫЙ DIFF\n";
echo "################################################################\n";
$lb = explode("\r\n", $before);
$la = explode("\r\n", $after);
printf("строк до: %d, строк после: %d -> %s\n", count($lb), count($la),
    count($lb) === count($la) ? 'число строк не изменилось' : 'ЧИСЛО СТРОК ИЗМЕНИЛОСЬ');

$changed = [];
foreach ($lb as $i => $l) {
    $r = isset($la[$i]) ? $la[$i] : '(нет строки)';
    if ($l !== $r) {
        $changed[] = $i + 1;
        echo str_repeat('-', 74), "\n";
        printf("строка %d\n", $i + 1);
        echo "-  " . $l . "\n";
        echo "+  " . $r . "\n";
    }
}
echo str_repeat('-', 74), "\n";
printf("\nизменённых строк: %d\n", count($changed));
printf("номера строк: %s\n", implode(', ', $changed));

echo "\n################################################################\n";
echo "## КОНТРОЛЬНЫЕ ПРОВЕРКИ ПО ИТОГУ ЭТАПА\n";
echo "################################################################\n";

$cr = substr_count($after, "\r");
$lf = substr_count($after, "\n");
$crlf = substr_count($after, "\r\n");
printf("CR: %d  LF: %d  CRLF пар: %d  одиночных CR: %d  одиночных LF: %d\n", $cr, $lf, $crlf, $cr - $crlf, $lf - $crlf);
printf("первые 8 байт: %s (BOM: %s)\n",
    implode(' ', str_split(bin2hex(substr($after, 0, 8)), 2)),
    substr($after, 0, 3) === "\xEF\xBB\xBF" ? 'ЕСТЬ — ПЛОХО' : 'нет');
printf("последние 2 байта: %s\n", implode(' ', str_split(bin2hex(substr($after, -2)), 2)));

$round = serialize_blocks(parse_blocks($after));
printf("parse_blocks -> serialize_blocks identical: %s\n", $after === $round ? 'YES' : 'NO');

echo "\n-- итоговый порядок пунктов, текст целиком --\n";
$list = null;
foreach ($aBlocks[5]['innerBlocks'] as $b) { if ($b['blockName'] === 'core/list') { $list = $b; } }
foreach ($list['innerBlocks'] as $i => $li) {
    $txt = html_entity_decode(strip_tags($li['innerHTML']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $txt = trim(preg_replace('/\s+/u', ' ', $txt));
    printf("%d) %s\n", $i + 1, $txt);
}

echo "\n-- абзацы ниже списка: порядок и содержимое --\n";
foreach ($aBlocks[5]['innerBlocks'] as $i => $b) {
    $cn = isset($b['attrs']['className']) ? $b['attrs']['className'] : '';
    printf("[5.%d] %-16s %s\n", $i, $b['blockName'], $cn);
}

echo "\n-- запрещённая старая редакция --\n";
foreach (['Мы организуем перевозку в морг', 'перевозку в морг'] as $n) {
    printf("  «%s»: %d вхождений -> %s\n", $n, substr_count($after, $n),
        substr_count($after, $n) === 0 ? 'OK (нигде нет)' : 'ОШИБКА');
}

echo "\n-- регистр --\n";
$caps = preg_match_all('/\b(Вы|Вам|Вами|Вас|Ваш[а-яё]*)\b/u', $after, $mc);
printf("  прописных форм Вы/Вам/Вас/Ваш в файле: %d -> %s\n", $caps, $caps === 0 ? 'OK (строчный регистр)' : 'ЕСТЬ');
$low = preg_match_all('/\b(вы|вам|вами|вас|ваш[а-яё]*)\b/u', $after, $ml);
printf("  строчных форм: %d (%s)\n", $low, implode(', ', array_unique($ml[1])));
