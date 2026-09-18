<?php
/**
 * Пересдача одной проверки 4.1: текст абзаца против бэкапа.
 * В edit-12 регулярка была жадной (.* с /s) и захватывала весь хвост файла.
 * Здесь — нежадная, плюс контроль, что совпадение действительно одно.
 */
require_once __DIR__ . '/../../wp-load.php';
require_once __DIR__ . '/edit-01-defs.php';

$file = rz_front_path();
$baseline = file(__DIR__ . '/stage4-baseline.txt', FILE_IGNORE_NEW_LINES);
$backup = dirname(__DIR__, 2) . '/_backup/t2/' . $baseline[2];

$before = file_get_contents($backup);
$after  = file_get_contents($file);

$re = '#<p class="rz-first-aid__warning">.*?</p>#us';

$nB = preg_match_all($re, $before, $mB);
$nA = preg_match_all($re, $after, $mA);

echo "######## ТЕКСТ АБЗАЦА: БЭКАП ПРОТИВ ТЕКУЩЕГО ########\n";
printf("бэкап : %s\n", basename($backup));
printf("совпадений регулярки в бэкапе : %d -> %s\n", $nB, $nB === 1 ? 'OK (ровно 1)' : 'ОШИБКА');
printf("совпадений регулярки в текущем: %d -> %s\n", $nA, $nA === 1 ? 'OK (ровно 1)' : 'ОШИБКА');
if ($nB !== 1 || $nA !== 1) { echo "STOP\n"; exit(1); }

$pB = $mB[0][0];
$pA = $mA[0][0];

printf("\nбэкап : %d байт, md5 %s\n", strlen($pB), md5($pB));
printf("сейчас: %d байт, md5 %s\n", strlen($pA), md5($pA));
printf("\nТЕКСТ АБЗАЦА ПОБАЙТОВО РАВЕН ТЕКСТУ ИЗ БЭКАПА: %s\n", $pB === $pA ? 'YES' : 'NO');

if ($pB !== $pA) {
    $n = min(strlen($pB), strlen($pA));
    for ($i = 0; $i < $n; $i++) { if ($pB[$i] !== $pA[$i]) { break; } }
    printf("первое расхождение на байте %d\n", $i);
    echo "бэкап >>>" . substr($pB, max(0, $i - 60), 120) . "<<<\n";
    echo "сейчас>>>" . substr($pA, max(0, $i - 60), 120) . "<<<\n";
}

echo "\n-- абзац целиком, как он сейчас в файле --\n";
echo ">>>" . $pA . "<<<\n";
printf("\nhex первых 40 байт : %s\n", implode(' ', str_split(bin2hex(substr($pA, 0, 40)), 2)));
printf("hex последних 20 байт: %s\n", implode(' ', str_split(bin2hex(substr($pA, -20)), 2)));

echo "\n######## ПОВТОР ОСТАЛЬНЫХ ИТОГОВЫХ ПРОВЕРОК 4.1 ########\n";
$lines = explode("\r\n", $after);
$cr = substr_count($after, "\r");
$lf = substr_count($after, "\n");
$crlf = substr_count($after, "\r\n");
$round = serialize_blocks(parse_blocks($after));
$dash = chr(45) . chr(45);
$bs = chr(92);
$esc = $bs . 'u002d' . $bs . 'u002d';

$checks = [
    'строк = 348'                  => count($lines) === 348,
    'CR = 347'                     => $cr === 347,
    'LF = 347'                     => $lf === 347,
    'одиночных CR = 0'             => ($cr - $crlf) === 0,
    'одиночных LF = 0'             => ($lf - $crlf) === 0,
    'размер = 23491'               => strlen($after) === 23491,
    'BOM отсутствует'              => substr($after, 0, 3) !== "\xEF\xBB\xBF",
    'файл кончается на 0d 0a'      => substr($after, -2) === "\r\n",
    'круговорот побайтово'         => $after === $round,
    'текст предупреждения 1 раз'   => substr_count($after, 'Не пускайте в дом посторонних ритуальных агентов') === 1,
    'экранированная форма 1 раз'   => substr_count($after, 'rz-callout' . $esc . 'warn') === 1,
    'литеральная форма 1 раз'      => substr_count($after, 'rz-callout' . $dash . 'warn') === 1,
    'текст абзаца == бэкапу'       => $pB === $pA,
];
$allOk = true;
foreach ($checks as $name => $val) {
    printf("  %-30s %s\n", $name, $val ? 'OK' : 'ОШИБКА');
    if (!$val) { $allOk = false; }
}
echo "\nЭТАП 4.1: " . ($allOk ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ') . "\n";
