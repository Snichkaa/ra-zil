<?php
/**
 * Этап 3.2. Текст нового первого пункта.
 * Одна подстрочная замена, count обязан быть 1.
 */
require_once __DIR__ . '/../../wp-load.php';
require_once __DIR__ . '/edit-01-defs.php';

$file = rz_front_path();
$content = file_get_contents($file);

$old = 'Мы организуем перевозку в морг и возьмём на себя всё остальное.';
$new = 'Мы подробно проконсультируем вас о дальнейших действиях, организуем перевозку усопшего в морг и возьмём на себя всё остальное.';

$lenBefore = strlen($content);
$md5Before = md5($content);

echo "######## DRY-RUN ########\n";
printf("до : %d байт, md5 %s\n", $lenBefore, $md5Before);
printf("\nискомая: >>>%s<<<\n", $old);
printf("новая  : >>>%s<<<\n", $new);
printf("длина старой: %d симв. / %d байт\n", mb_strlen($old, 'UTF-8'), strlen($old));
printf("длина новой : %d симв. / %d байт\n", mb_strlen($new, 'UTF-8'), strlen($new));
printf("прирост: %+d симв. / %+d байт\n",
    mb_strlen($new, 'UTF-8') - mb_strlen($old, 'UTF-8'), strlen($new) - strlen($old));

$count = substr_count($content, $old);
printf("\nвхождений в файле: %d -> %s\n", $count, $count === 1 ? 'OK (ровно 1)' : 'ОШИБКА');
if ($count !== 1) { echo "ПРЕРЫВАЮ: запись не выполнена\n"; exit(1); }

echo "\n-- БЫЛО (контекст +-80 символов) --\n>>>" . rz_ctx($content, $old, 80) . "<<<\n";
$probeCount = 0;
$probe = str_replace($old, $new, $content, $probeCount);
echo "\n-- СТАЛО (контекст +-80 символов) --\n>>>" . rz_ctx($probe, $new, 80) . "<<<\n";
printf("\nstr_replace count на пробе: %d\n", $probeCount);
printf("md5 файла не изменился: %s\n", md5_file($file) === $md5Before ? 'да' : 'НЕТ');

echo "\n######## ЗАПИСЬ ########\n";
$replaceCount = 0;
$result = str_replace($old, $new, $content, $replaceCount);
printf("str_replace count: %d -> %s\n", $replaceCount, $replaceCount === 1 ? 'OK' : 'ОШИБКА');
if ($replaceCount !== 1) { echo "ПРЕРЫВАЮ\n"; exit(1); }

$expectedLen = $lenBefore - strlen($old) + strlen($new);
$written = file_put_contents($file, $result);
printf("file_put_contents вернул: %s (подготовлено %d, по арифметике %d)\n",
    var_export($written, true), strlen($result), $expectedLen);

clearstatcache(true, $file);
$reread = file_get_contents($file);

echo "\n######## ПРОВЕРКИ ПОСЛЕ ЗАПИСИ ########\n";
printf("размер до / после: %d / %d (прирост %+d, ожидалось %+d)\n",
    $lenBefore, strlen($reread), strlen($reread) - $lenBefore, strlen($new) - strlen($old));
printf("md5 до / после: %s / %s\n", $md5Before, md5($reread));
printf("прочитанное == подготовленному: %s\n", $reread === $result ? 'да' : 'НЕТ');
printf("длина совпала с арифметикой: %s\n", strlen($reread) === $expectedLen ? 'да' : 'НЕТ');

echo "\n-- требуемые grep-проверки --\n";
$g1 = substr_count($reread, 'перевозку в морг');
$g2 = substr_count($reread, 'перевозку усопшего в морг');
$g3 = substr_count($reread, 'Мы организуем перевозку');
printf("«перевозку в морг»          : %d -> %s (требуется 0)\n", $g1, $g1 === 0 ? 'OK' : 'ОШИБКА');
printf("«перевозку усопшего в морг» : %d -> %s (требуется 1)\n", $g2, $g2 === 1 ? 'OK' : 'ОШИБКА');
printf("«Мы организуем перевозку»   : %d -> %s (требуется 0)\n", $g3, $g3 === 0 ? 'OK' : 'ОШИБКА');

echo "\n-- второй и третий пункты не тронуты --\n";
$keep = [
    'Вызовите скорую помощь.' => 1,
    'Врач констатирует смерть и выдаст документ, без которого дальнейшие действия невозможны.' => 1,
    'Вызовите полицию.' => 1,
    'Сотрудник оформит протокол осмотра. Это обязательная процедура при смерти вне медицинского учреждения.' => 1,
    'Спросите фамилию агента, который к вам приедет.' => 1,
];
$keepOk = true;
foreach ($keep as $needle => $want) {
    $have = substr_count($reread, $needle);
    printf("  %-104s %d -> %s\n", '«' . mb_substr($needle, 0, 100, 'UTF-8') . '»', $have, $have === $want ? 'OK' : 'ОШИБКА');
    if ($have !== $want) { $keepOk = false; }
}

echo "\n-- построчный diff --\n";
$lb = explode("\r\n", $content);
$la = explode("\r\n", $reread);
printf("строк до: %d, строк после: %d\n", count($lb), count($la));
$changed = [];
foreach ($lb as $i => $l) {
    $r = isset($la[$i]) ? $la[$i] : '(нет строки)';
    if ($l !== $r) {
        $changed[] = $i + 1;
        printf("\nстрока %d\n-  %s\n+  %s\n", $i + 1, $l, $r);
    }
}
printf("\nизменённых строк: %d (номера: %s) -> %s\n",
    count($changed), implode(', ', $changed), count($changed) === 1 ? 'OK (ровно 1)' : 'ОШИБКА');

$cr = substr_count($reread, "\r");
$lf = substr_count($reread, "\n");
$crlf = substr_count($reread, "\r\n");
printf("\nCR: %d  LF: %d  одиночных CR: %d  одиночных LF: %d\n", $cr, $lf, $cr - $crlf, $lf - $crlf);
printf("первые 8 байт: %s   последние 2 байта: %s\n",
    implode(' ', str_split(bin2hex(substr($reread, 0, 8)), 2)),
    implode(' ', str_split(bin2hex(substr($reread, -2)), 2)));

$round = serialize_blocks(parse_blocks($reread));
printf("parse_blocks -> serialize_blocks identical: %s\n", $reread === $round ? 'YES' : 'NO');

echo "\n-- новый первый пункт целиком --\n";
$blocks = parse_blocks($reread);
foreach ($blocks[5]['innerBlocks'] as $b) {
    if ($b['blockName'] === 'core/list') {
        echo ">>>" . $b['innerBlocks'][0]['innerHTML'] . "<<<\n";
    }
}

$ok = $g1 === 0 && $g2 === 1 && $g3 === 0 && count($changed) === 1 && $keepOk
    && $cr === 343 && $lf === 343 && $reread === $round && $reread === $result;
echo "\nЭТАП 3.2: " . ($ok ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ') . "\n";
