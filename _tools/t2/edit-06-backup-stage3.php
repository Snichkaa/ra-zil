<?php
/**
 * Бэкап перед первой записью Этапа 3.
 */
require_once __DIR__ . '/../../wp-load.php';
require_once __DIR__ . '/edit-01-defs.php';

$src = rz_front_path();
$stamp = date('Ymd-His');
$dst = dirname(__DIR__, 2) . '/_backup/t2/front-page.html.' . $stamp;

if (file_exists($dst)) { echo "REFUSING: backup already exists: {$dst}\n"; exit(1); }

$ok = copy($src, $dst);
clearstatcache(true, $src);
clearstatcache(true, $dst);

echo "copy() returned: " . ($ok ? 'true' : 'false') . "\n\n";
printf("src  : %s\n", $src);
printf("       %d байт, md5 %s\n", filesize($src), md5_file($src));
printf("dst  : %s\n", $dst);
printf("       %d байт, md5 %s\n", filesize($dst), md5_file($dst));

$sizeMatch = filesize($src) === filesize($dst);
$md5Match  = md5_file($src) === md5_file($dst);
printf("\nsize match: %s\n", $sizeMatch ? 'YES' : 'NO');
printf("md5 match : %s\n", $md5Match ? 'YES' : 'NO');
printf("ВЕРДИКТ   : %s\n", $sizeMatch && $md5Match ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ');

echo "\n################################################################\n";
echo "## ИСХОДНЫЙ MD5 ДЛЯ ЭТАПА 3 (зафиксирован)\n";
echo "################################################################\n";
printf("md5    : %s\n", md5_file($src));
printf("байт   : %d\n", filesize($src));
printf("бэкап  : %s\n", basename($dst));

// Записываем опорную точку этапа рядом, чтобы следующие скрипты могли сверяться.
file_put_contents(__DIR__ . '/stage3-baseline.txt',
    md5_file($src) . "\n" . filesize($src) . "\n" . basename($dst) . "\n");
echo "\nопорная точка сохранена в _tools/t2/stage3-baseline.txt\n";
