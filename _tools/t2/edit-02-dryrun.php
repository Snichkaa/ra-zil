<?php
require_once __DIR__ . '/../../wp-load.php';
require_once __DIR__ . '/edit-01-defs.php';

$file    = rz_front_path();
$content = file_get_contents($file);

echo "файл  : {$file}\n";
echo "байт  : " . strlen($content) . "\n";
echo "md5   : " . md5($content) . "\n";
echo "DRY-RUN: НИЧЕГО НЕ ЗАПИСЫВАЕТСЯ\n";

$allOk = true;

foreach (rz_edits() as $id => $e) {
    echo "\n", str_repeat('#', 78), "\n";
    echo "## ЗАМЕНА {$id}\n";
    echo str_repeat('#', 78), "\n";

    $count = substr_count($content, $e['old']);
    printf("вхождений искомой подстроки в файле: %d  ->  %s\n",
        $count, $count === 1 ? 'OK (ровно 1)' : 'ОШИБКА (должно быть ровно 1)');
    if ($count !== 1) { $allOk = false; }

    printf("длина старой подстроки: %d симв. / %d байт\n",
        mb_strlen($e['old'], 'UTF-8'), strlen($e['old']));
    printf("длина новой  подстроки: %d симв. / %d байт\n",
        mb_strlen($e['new'], 'UTF-8'), strlen($e['new']));
    printf("прирост: %+d симв. / %+d байт\n",
        mb_strlen($e['new'], 'UTF-8') - mb_strlen($e['old'], 'UTF-8'),
        strlen($e['new']) - strlen($e['old']));

    echo "\n-- ИСКОМАЯ ПОДСТРОКА --\n>>>{$e['old']}<<<\n";
    echo "-- НОВАЯ ПОДСТРОКА --\n>>>{$e['new']}<<<\n";

    echo "\n-- БЫЛО (контекст +-80 символов) --\n";
    echo ">>>" . rz_ctx($content, $e['old'], 80) . "<<<\n";

    // Пробная замена делается на КОПИИ переменной, файл не трогаем.
    $probeCount = 0;
    $probe = str_replace($e['old'], $e['new'], $content, $probeCount);
    echo "\n-- СТАЛО (контекст +-80 символов) --\n";
    echo ">>>" . rz_ctx($probe, $e['new'], 80) . "<<<\n";
    printf("\nstr_replace count на пробе: %d\n", $probeCount);
}

echo "\n", str_repeat('=', 78), "\n";
echo "ИТОГ DRY-RUN: " . ($allOk ? 'все три подстроки уникальны, можно писать' : 'ЕСТЬ ПРОБЛЕМА, писать нельзя') . "\n";
echo "файл не изменён, md5 всё ещё: " . md5_file($file) . "\n";
