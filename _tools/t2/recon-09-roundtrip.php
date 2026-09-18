<?php
require_once __DIR__ . '/../../wp-load.php';
$file = WP_CONTENT_DIR . '/themes/razil/templates/front-page.html';
$orig = file_get_contents($file);
$re = serialize_blocks(parse_blocks($orig));
echo "orig bytes : " . strlen($orig) . "\n";
echo "round bytes: " . strlen($re) . "\n";
echo "identical  : " . ($orig === $re ? 'YES (byte-for-byte)' : 'NO') . "\n";
if ($orig !== $re) {
    $n = min(strlen($orig), strlen($re));
    for ($i = 0; $i < $n; $i++) { if ($orig[$i] !== $re[$i]) break; }
    echo "first diff at byte {$i}\n";
    echo "orig  ...>>>" . substr($orig, max(0,$i-60), 140) . "<<<\n";
    echo "round ...>>>" . substr($re,   max(0,$i-60), 140) . "<<<\n";
}
