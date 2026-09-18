<?php
require_once __DIR__ . '/../../wp-load.php';
require_once __DIR__ . '/edit-01-defs.php';

$file   = rz_front_path();
$backup = dirname(__DIR__, 2) . '/_backup/t2/front-page.html.20260918-014234';

$after  = file_get_contents($file);
$before = file_get_contents($backup);

echo "######## РАЗМЕР И MD5 ДО/ПОСЛЕ ########\n";
printf("%-8s %-10s %-34s %s\n", '', 'байт', 'md5', 'источник');
printf("%-8s %-10d %-34s %s\n", 'ДО',    strlen($before), md5($before), basename($backup));
printf("%-8s %-10d %-34s %s\n", 'ПОСЛЕ', strlen($after),  md5($after),  'templates/front-page.html');
printf("прирост: %+d байт (2.1 +107, 2.2 +30, 2.3 +57 = +194)\n",
    strlen($after) - strlen($before));

echo "\n######## БАЙТОВЫЕ ПРОВЕРКИ (бинарный режим) ########\n";
$cr = substr_count($after, "\r");
$lf = substr_count($after, "\n");
$crlf = substr_count($after, "\r\n");
printf("CR   (0d) : %d   -> %s (эталон 343)\n", $cr, $cr === 343 ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ');
printf("LF   (0a) : %d   -> %s (эталон 343)\n", $lf, $lf === 343 ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ');
printf("CRLF пар  : %d   -> %s (все переводы строк парные)\n", $crlf,
    ($crlf === $cr && $crlf === $lf) ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ');
printf("одиночных LF без CR: %d\n", $lf - $crlf);
printf("одиночных CR без LF: %d\n", $cr - $crlf);

$first8before = substr($before, 0, 8);
$first8after  = substr($after, 0, 8);
printf("\nпервые 8 байт ДО   : %s\n", implode(' ', str_split(bin2hex($first8before), 2)));
printf("первые 8 байт ПОСЛЕ: %s\n", implode(' ', str_split(bin2hex($first8after), 2)));
printf("первые 8 байт не изменились: %s\n", $first8before === $first8after ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ');

$bom = "\xEF\xBB\xBF";
printf("BOM в начале: %s\n", substr($after, 0, 3) === $bom ? 'ПОЯВИЛСЯ — ПЛОХО' : 'НЕТ (правильно)');

$last2 = substr($after, -2);
printf("последние 2 байта: %s -> файл заканчивается на 0d 0a: %s\n",
    implode(' ', str_split(bin2hex($last2), 2)),
    $last2 === "\r\n" ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ');

echo "\n######## КРУГОВОРОТ БЛОКОВ (только проверка, в файл не пишется) ########\n";
$round = serialize_blocks(parse_blocks($after));
printf("файл      : %d байт\n", strlen($after));
printf("круговорот: %d байт\n", strlen($round));
printf("identical : %s\n", $after === $round ? 'YES (побайтово)' : 'NO');
if ($after !== $round) {
    $n = min(strlen($after), strlen($round));
    for ($i = 0; $i < $n; $i++) { if ($after[$i] !== $round[$i]) { break; } }
    printf("первое расхождение на байте %d\n", $i);
    echo "файл  >>>" . substr($after, max(0, $i - 60), 140) . "<<<\n";
    echo "кругов>>>" . substr($round, max(0, $i - 60), 140) . "<<<\n";
}

echo "\n######## ЧТО ИЗМЕНИЛОСЬ: построчный diff ДО -> ПОСЛЕ ########\n";
$lb = explode("\r\n", $before);
$la = explode("\r\n", $after);
printf("строк ДО: %d, строк ПОСЛЕ: %d\n", count($lb), count($la));
$changed = 0;
foreach ($lb as $i => $l) {
    $r = isset($la[$i]) ? $la[$i] : '(нет строки)';
    if ($l !== $r) {
        $changed++;
        echo str_repeat('-', 74), "\n";
        printf("строка %d\n", $i + 1);
        echo "-  " . $l . "\n";
        echo "+  " . $r . "\n";
    }
}
printf("\nизменённых строк: %d (ожидалось 3)\n", $changed);
echo "ВЕРДИКТ: " . ($changed === 3 ? 'СОВПАЛО — затронуты ровно три строки' : 'РАСХОЖДЕНИЕ') . "\n";

echo "\n######## md5 main.css (для контроля правок соседнего агента) ########\n";
$css = WP_CONTENT_DIR . '/themes/razil/assets/css/main.css';
printf("main.css: %d байт, md5 %s\n", filesize($css), md5_file($css));
