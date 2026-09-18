<?php
/**
 * Этап 2-бис. Откат регистра в заголовке первой помощи.
 * Замена равной длины: «Вам» (d0 92 d0 b0 d0 bc) -> «вам» (d0 b2 d0 b0 d0 bc).
 */
require_once __DIR__ . '/../../wp-load.php';
require_once __DIR__ . '/edit-01-defs.php';

$file = rz_front_path();

$old = 'близкого Вам человека';
$new = 'близкого вам человека';

$content = file_get_contents($file);
$md5before = md5($content);
$lenbefore = strlen($content);

echo "######## DRY-RUN ########\n";
printf("файл: %s\n", $file);
printf("до : %d байт, md5 %s\n", $lenbefore, $md5before);
printf("\nискомая подстрока: >>>%s<<<\n", $old);
printf("новая  подстрока : >>>%s<<<\n", $new);
printf("hex старой: %s\n", implode(' ', str_split(bin2hex($old), 2)));
printf("hex новой : %s\n", implode(' ', str_split(bin2hex($new), 2)));
printf("длина старой: %d байт / %d симв.\n", strlen($old), mb_strlen($old, 'UTF-8'));
printf("длина новой : %d байт / %d симв.\n", strlen($new), mb_strlen($new, 'UTF-8'));
printf("замена равной длины: %s\n", strlen($old) === strlen($new) ? 'ДА' : 'НЕТ');

$count = substr_count($content, $old);
printf("\nвхождений в файле: %d -> %s\n", $count, $count === 1 ? 'OK (ровно 1)' : 'ОШИБКА');
if ($count !== 1) { echo "ПРЕРЫВАЮ: запись не выполнена\n"; exit(1); }

echo "\n-- БЫЛО (контекст +-80 символов) --\n>>>" . rz_ctx($content, $old, 80) . "<<<\n";
$probeCount = 0;
$probe = str_replace($old, $new, $content, $probeCount);
echo "\n-- СТАЛО (контекст +-80 символов) --\n>>>" . rz_ctx($probe, $new, 80) . "<<<\n";
printf("\nstr_replace count на пробе: %d\n", $probeCount);
printf("md5 файла не изменился: %s\n", md5_file($file) === $md5before ? 'да' : 'НЕТ');

echo "\n######## ЗАПИСЬ ########\n";
$replaceCount = 0;
$result = str_replace($old, $new, $content, $replaceCount);
printf("str_replace count: %d -> %s\n", $replaceCount, $replaceCount === 1 ? 'OK' : 'ОШИБКА');
if ($replaceCount !== 1) { echo "ПРЕРЫВАЮ\n"; exit(1); }

$written = file_put_contents($file, $result);
printf("file_put_contents вернул: %s (подготовлено %d байт)\n", var_export($written, true), strlen($result));

clearstatcache(true, $file);
$reread = file_get_contents($file);
$md5after = md5($reread);
$lenafter = strlen($reread);

echo "\n######## ПОВТОРНОЕ ЧТЕНИЕ ########\n";
printf("после: %d байт, md5 %s\n", $lenafter, $md5after);
printf("\nразмер до / после: %d / %d  -> ожидалось %d, равны: %s\n",
    $lenbefore, $lenafter, 23082, $lenafter === $lenbefore && $lenafter === 23082 ? 'YES' : 'NO');
printf("md5 ДО   : %s\n", $md5before);
printf("md5 ПОСЛЕ: %s\n", $md5after);

$sameAsPrepared = ($reread === $result);
$oldGone        = (substr_count($reread, $old) === 0);
$newThereOnce   = (substr_count($reread, $new) === 1);
$noCapital      = (preg_match('/\b(Вы|Вам|Вами|Вас|Ваш[а-яё]*)\b/u', $reread) === 0);

printf("\nпрочитанное == подготовленному : %s\n", $sameAsPrepared ? 'да' : 'НЕТ');
printf("старой подстроки больше нет     : %s\n", $oldGone ? 'да' : 'НЕТ');
printf("новая подстрока ровно 1 раз     : %s\n", $newThereOnce ? 'да' : 'НЕТ');
printf("прописных форм Вы/Вам/Вас/Ваш в файле: %s\n", $noCapital ? 'ни одной' : 'ЕСТЬ');

$cr = substr_count($reread, "\r");
$lf = substr_count($reread, "\n");
$crlf = substr_count($reread, "\r\n");
printf("\nCR: %d  LF: %d  CRLF пар: %d  одиночных CR: %d  одиночных LF: %d\n",
    $cr, $lf, $crlf, $cr - $crlf, $lf - $crlf);
printf("первые 8 байт: %s\n", implode(' ', str_split(bin2hex(substr($reread, 0, 8)), 2)));
printf("последние 2 байта: %s\n", implode(' ', str_split(bin2hex(substr($reread, -2)), 2)));

$round = serialize_blocks(parse_blocks($reread));
printf("круговорот блоков identical: %s\n", $reread === $round ? 'YES' : 'NO');

$ok = $sameAsPrepared && $oldGone && $newThereOnce && $noCapital
    && $lenafter === $lenbefore && $cr === 343 && $lf === 343 && $reread === $round;
echo "\nЭТАП 2-БИС: " . ($ok ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ') . "\n";

echo "\n-- строка 47 после правки --\n";
$pos = strpos($reread, '<h2>');
$lineNo = substr_count(substr($reread, 0, $pos), "\n") + 1;
$lineEnd = strpos($reread, "\r\n", $pos);
printf("%d| %s\n", $lineNo, substr($reread, strrpos(substr($reread, 0, $pos), "\r\n") + 2, $lineEnd - (strrpos(substr($reread, 0, $pos), "\r\n") + 2)));
