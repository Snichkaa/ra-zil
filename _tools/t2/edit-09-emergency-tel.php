<?php
/**
 * Этап 3.3. Класс rz-emergency-tel на пять tel:-ссылок внутри региона core/list.
 * Замены делаются в вырезанном регионе, чтобы кнопка героя и tel:112
 * физически не попали под замену.
 */
require_once __DIR__ . '/../../wp-load.php';
require_once __DIR__ . '/edit-01-defs.php';

$file = rz_front_path();
$content = file_get_contents($file);

$lenBefore = strlen($content);
$md5Before = md5($content);

$openNeedle  = '<!-- wp:list {"ordered":true';
$closeNeedle = '<!-- /wp:list -->';

$regStart = strpos($content, $openNeedle);
$closePos = strpos($content, $closeNeedle, $regStart);
$regEnd   = $closePos + strlen($closeNeedle);
$region   = substr($content, $regStart, $regEnd - $regStart);

echo "######## РЕГИОН, В КОТОРОМ РАБОТАЕМ ########\n";
printf("до : %d байт, md5 %s\n", $lenBefore, $md5Before);
printf("регион: байты %d..%d, длина %d\n", $regStart, $regEnd - 1, strlen($region));

$telTotalBefore = substr_count($content, 'href="tel:');
printf("\nвхождений 'href=\"tel:' в файле ДО: %d\n", $telTotalBefore);
printf("вхождений 'href=\"tel:' в регионе ДО: %d\n", substr_count($region, 'href="tel:'));
printf("вхождений 'rz-emergency-tel' в файле ДО: %d\n", substr_count($content, 'rz-emergency-tel'));

$targets = ['tel:+74212605290', 'tel:103', 'tel:03', 'tel:102', 'tel:02'];

echo "\n######## DRY-RUN: пять замен в регионе ########\n";
$probe = $region;
$allOk = true;
foreach ($targets as $t) {
    $old = '<a href="' . $t . '">';
    $new = '<a class="rz-emergency-tel" href="' . $t . '">';
    $n = substr_count($probe, $old);
    printf("%-22s вхождений: %d -> %s\n", $t, $n, $n === 1 ? 'OK (ровно 1)' : 'ОШИБКА');
    if ($n !== 1) { $allOk = false; continue; }
    printf("  было : %s\n", $old);
    printf("  стало: %s\n", $new);
    $c = 0;
    $probe = str_replace($old, $new, $probe, $c);
    if ($c !== 1) { $allOk = false; }
}
if (!$allOk) { echo "\nПРЕРЫВАЮ: не все счётчики равны 1, запись не выполнена\n"; exit(1); }
printf("\nmd5 файла не изменился: %s\n", md5_file($file) === $md5Before ? 'да' : 'НЕТ');

echo "\n-- регион после пробы --\n>>>" . $probe . "<<<\n";

echo "\n######## ЗАПИСЬ ########\n";
$newRegion = $region;
foreach ($targets as $t) {
    $old = '<a href="' . $t . '">';
    $new = '<a class="rz-emergency-tel" href="' . $t . '">';
    $c = 0;
    $newRegion = str_replace($old, $new, $newRegion, $c);
    printf("%-22s count: %d -> %s\n", $t, $c, $c === 1 ? 'OK' : 'ОШИБКА');
    if ($c !== 1) { echo "ПРЕРЫВАЮ\n"; exit(1); }
}

$result = substr($content, 0, $regStart) . $newRegion . substr($content, $regEnd);
$expectedLen = $lenBefore + (strlen($newRegion) - strlen($region));
printf("\nрегион: %d -> %d байт (%+d)\n", strlen($region), strlen($newRegion), strlen($newRegion) - strlen($region));
printf("подготовлено: %d байт (по арифметике %d)\n", strlen($result), $expectedLen);

$written = file_put_contents($file, $result);
printf("file_put_contents вернул: %s\n", var_export($written, true));

clearstatcache(true, $file);
$reread = file_get_contents($file);

echo "\n######## ПРОВЕРКИ ПОСЛЕ ЗАПИСИ ########\n";
printf("размер до / после: %d / %d (%+d)\n", $lenBefore, strlen($reread), strlen($reread) - $lenBefore);
printf("md5 до / после: %s / %s\n", $md5Before, md5($reread));
printf("прочитанное == подготовленному: %s\n", $reread === $result ? 'да' : 'НЕТ');
printf("длина совпала с арифметикой: %s\n", strlen($reread) === $expectedLen ? 'да' : 'НЕТ');

$nClass = substr_count($reread, 'rz-emergency-tel');
printf("\nвхождений 'rz-emergency-tel' в файле: %d -> %s (требуется 5)\n",
    $nClass, $nClass === 5 ? 'OK' : 'ОШИБКА');

$telTotalAfter = substr_count($reread, 'href="tel:');
printf("вхождений 'href=\"tel:' было / стало: %d / %d -> %s (числа обязаны совпадать)\n",
    $telTotalBefore, $telTotalAfter, $telTotalBefore === $telTotalAfter ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ');

$n112 = substr_count($reread, 'tel:112');
printf("вхождений 'tel:112': %d -> %s (требуется 1)\n", $n112, $n112 === 1 ? 'OK' : 'ОШИБКА');

echo "\n-- tel:112 без класса: строка целиком --\n";
$p112 = strpos($reread, 'tel:112');
$ls = strrpos(substr($reread, 0, $p112), "\r\n");
$ls = ($ls === false) ? 0 : $ls + 2;
$le = strpos($reread, "\r\n", $p112);
$line112 = substr($reread, $ls, $le - $ls);
$lineNo112 = substr_count(substr($reread, 0, $ls), "\n") + 1;
printf("%d| %s\n", $lineNo112, $line112);
printf("класса rz-emergency-tel в этой строке: %s -> %s\n",
    strpos($line112, 'rz-emergency-tel') === false ? 'нет' : 'ЕСТЬ',
    strpos($line112, 'rz-emergency-tel') === false ? 'OK' : 'ОШИБКА');
// сам якорь tel:112
if (preg_match('/<a\b[^>]*href="tel:112"[^>]*>/u', $reread, $m112)) {
    printf("якорь tel:112: %s\n", $m112[0]);
}

echo "\n-- кнопка героя: строка целиком --\n";
$pHero = strpos($reread, 'wp-block-button__link');
$hs = strrpos(substr($reread, 0, $pHero), "\r\n");
$hs = ($hs === false) ? 0 : $hs + 2;
$he = strpos($reread, "\r\n", $pHero);
$lineHero = substr($reread, $hs, $he - $hs);
$lineNoHero = substr_count(substr($reread, 0, $hs), "\n") + 1;
printf("%d| %s\n", $lineNoHero, $lineHero);
$heroClean = strpos($lineHero, 'rz-emergency-tel') === false;
printf("класса rz-emergency-tel в кнопке героя: %s -> %s\n",
    $heroClean ? 'нет' : 'ЕСТЬ', $heroClean ? 'OK' : 'ОШИБКА');

echo "\n-- все якоря tel: в файле после правки --\n";
preg_match_all('/<a\b[^>]*href="tel:[^"]*"[^>]*>/u', $reread, $mAll, PREG_OFFSET_CAPTURE);
foreach ($mAll[0] as $k => $hit) {
    $lineNo = substr_count(substr($reread, 0, $hit[1]), "\n") + 1;
    $hasCls = strpos($hit[0], 'rz-emergency-tel') !== false;
    printf("  [%d] строка %3d  %-12s %s\n", $k, $lineNo, $hasCls ? 'С КЛАССОМ' : 'без класса', $hit[0]);
}
printf("всего якорей tel: %d\n", count($mAll[0]));

echo "\n-- class стоит ПЕРЕД href во всех пяти --\n";
$orderOk = true;
foreach ($targets as $t) {
    $pat = '<a class="rz-emergency-tel" href="' . $t . '">';
    $n = substr_count($reread, $pat);
    printf("  %-22s точная форма найдена: %d -> %s\n", $t, $n, $n === 1 ? 'OK' : 'ОШИБКА');
    if ($n !== 1) { $orderOk = false; }
}

$cr = substr_count($reread, "\r");
$lf = substr_count($reread, "\n");
$crlf = substr_count($reread, "\r\n");
printf("\nCR: %d -> %s\n", $cr, $cr === 343 ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ');
printf("LF: %d -> %s\n", $lf, $lf === 343 ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ');
printf("одиночных CR: %d  одиночных LF: %d\n", $cr - $crlf, $lf - $crlf);
printf("первые 8 байт: %s   последние 2 байта: %s\n",
    implode(' ', str_split(bin2hex(substr($reread, 0, 8)), 2)),
    implode(' ', str_split(bin2hex(substr($reread, -2)), 2)));

$round = serialize_blocks(parse_blocks($reread));
printf("\nparse_blocks -> serialize_blocks identical: %s\n", $reread === $round ? 'YES' : 'NO');

// href у всех пяти не изменились
echo "\n-- номера телефонов не изменились --\n";
foreach (['tel:+74212605290', 'tel:103', 'tel:03', 'tel:102', 'tel:02', 'tel:112'] as $t) {
    printf("  href=\"%s\" : %d вхождений\n", $t, substr_count($reread, 'href="' . $t . '"'));
}

$ok = $nClass === 5 && $telTotalBefore === $telTotalAfter && $n112 === 1
    && $heroClean && $orderOk && $cr === 343 && $lf === 343
    && $reread === $round && $reread === $result;
echo "\nЭТАП 3.3: " . ($ok ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ') . "\n";
