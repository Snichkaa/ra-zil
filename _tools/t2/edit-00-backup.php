<?php
require_once __DIR__ . '/../../wp-load.php';

$src = WP_CONTENT_DIR . '/themes/razil/templates/front-page.html';
$stamp = date('Ymd-His');
$dst = dirname(__DIR__, 2) . '/_backup/t2/front-page.html.' . $stamp;

if (!is_dir(dirname($dst))) { echo "NO BACKUP DIR: " . dirname($dst) . "\n"; exit(1); }
if (file_exists($dst)) { echo "REFUSING: backup already exists: {$dst}\n"; exit(1); }

$ok = copy($src, $dst);
clearstatcache(true, $src);
clearstatcache(true, $dst);

echo "copy() returned: " . ($ok ? 'true' : 'false') . "\n\n";
printf("src path : %s\n", $src);
printf("src size : %d bytes\n", filesize($src));
printf("src md5  : %s\n", md5_file($src));
echo "\n";
printf("dst path : %s\n", $dst);
printf("dst size : %d bytes\n", filesize($dst));
printf("dst md5  : %s\n", md5_file($dst));
echo "\n";
$sizeMatch = filesize($src) === filesize($dst);
$md5Match  = md5_file($src) === md5_file($dst);
echo "size match: " . ($sizeMatch ? 'YES' : 'NO') . "\n";
echo "md5 match : " . ($md5Match  ? 'YES' : 'NO') . "\n";
echo "VERDICT   : " . ($sizeMatch && $md5Match ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ') . "\n";
echo "\nbackup basename for later reference: " . basename($dst) . "\n";
