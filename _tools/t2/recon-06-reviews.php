<?php
require_once __DIR__ . '/../../wp-load.php';
$file = WP_CONTENT_DIR . '/themes/razil/templates/front-page.html';
$content = file_get_contents($file);
$blocks = parse_blocks($content);

echo "######## BLOCK [13] rz-reviews-section — RAW SERIALIZED ########\n";
echo serialize_block($blocks[13]);

echo "\n\n######## Where it ends in the file ########\n";
$ser13 = serialize_block($blocks[13]);
$pos = strpos($content, "<!-- wp:group {\"className\":\"rz-reviews-section");
$lineStart = substr_count(substr($content, 0, $pos), "\n") + 1;
$end = $pos + strlen($ser13);
$lineEnd = substr_count(substr($content, 0, $end), "\n") + 1;
echo "starts at byte {$pos} (line {$lineStart}), ends at byte {$end} (line {$lineEnd})\n";
echo "--- file text AFTER block [13] (next 400 bytes) ---\n";
echo substr($content, $end, 400), "\n--- end ---\n";

echo "\n\n######## BLOCK [11] rz-steps (the neighbour before) — RAW ########\n";
echo serialize_block($blocks[11]);
