<?php
require_once __DIR__ . '/../../wp-load.php';
$file = WP_CONTENT_DIR . '/themes/razil/templates/front-page.html';
$blocks = parse_blocks(file_get_contents($file));
echo "######## BLOCK [3] rz-hero ########\n";
echo serialize_block($blocks[3]);
echo "\n\n######## BLOCK [9] rz-advantages ########\n";
echo serialize_block($blocks[9]);
