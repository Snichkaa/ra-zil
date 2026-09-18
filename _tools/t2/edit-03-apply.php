<?php
require_once __DIR__ . '/../../wp-load.php';
require_once __DIR__ . '/edit-01-defs.php';

$file = rz_front_path();

$before = file_get_contents($file);
echo "== состояние до всех замен ==\n";
echo "байт: " . strlen($before) . "   md5: " . md5($before) . "\n";

$fail = false;

foreach (rz_edits() as $id => $e) {
    echo "\n", str_repeat('#', 78), "\n";
    echo "## ПРИМЕНЯЮ ЗАМЕНУ {$id}\n";
    echo str_repeat('#', 78), "\n";

    // Читаем файл заново перед каждой заменой: никаких построчных циклов,
    // никакой нормализации переводов строк.
    $content = file_get_contents($file);
    $md5in   = md5($content);
    $lenin   = strlen($content);
    printf("до : %d байт, md5 %s\n", $lenin, $md5in);

    $count = 0;
    $result = str_replace($e['old'], $e['new'], $content, $count);
    printf("str_replace count: %d  ->  %s\n", $count, $count === 1 ? 'OK' : 'ОШИБКА');
    if ($count !== 1) {
        echo "ПРЕРЫВАЮ: count не равен 1, запись не выполнена\n";
        $fail = true;
        break;
    }

    $expectedLen = $lenin - strlen($e['old']) + strlen($e['new']);
    $written = file_put_contents($file, $result);
    printf("file_put_contents вернул: %s (ожидалось %d байт)\n",
        var_export($written, true), strlen($result));
    printf("ожидаемая длина по арифметике: %d байт\n", $expectedLen);

    // Повторное чтение файла с диска.
    clearstatcache(true, $file);
    $reread = file_get_contents($file);

    $sameAsPrepared = ($reread === $result);
    $oldGone        = (substr_count($reread, $e['old']) === 0);
    $newThereOnce   = (substr_count($reread, $e['new']) === 1);
    $lenOk          = (strlen($reread) === $expectedLen);

    printf("\nповторное чтение: %d байт, md5 %s\n", strlen($reread), md5($reread));
    printf("  прочитанное == подготовленному : %s\n", $sameAsPrepared ? 'да' : 'НЕТ');
    printf("  старой подстроки в файле больше нет : %s\n", $oldGone ? 'да' : 'НЕТ');
    printf("  новая подстрока встречается ровно 1 раз : %s\n", $newThereOnce ? 'да' : 'НЕТ');
    printf("  длина совпала с арифметикой : %s\n", $lenOk ? 'да' : 'НЕТ');

    $ok = $sameAsPrepared && $oldGone && $newThereOnce && $lenOk && $written === strlen($result);
    echo "\nЗАМЕНА {$id}: " . ($ok ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ') . "\n";
    if (!$ok) { $fail = true; break; }

    echo "\n-- фрагмент файла после записи (контекст +-80 символов) --\n";
    echo ">>>" . rz_ctx($reread, $e['new'], 80) . "<<<\n";
}

echo "\n", str_repeat('=', 78), "\n";
echo "ИТОГ ПРИМЕНЕНИЯ: " . ($fail ? 'ЕСТЬ РАСХОЖДЕНИЕ' : 'все три замены СОВПАЛО') . "\n";
