<?php
require_once __DIR__ . '/../../wp-load.php';
$file = WP_CONTENT_DIR . '/themes/razil/templates/front-page.html';
$content = file_get_contents($file);

echo "######## 1.3a grep: любые wp:buttons / wp:button в front-page.html ########\n";
foreach (explode("\n", $content) as $i => $line) {
    if (stripos($line, 'wp:buttons') !== false || stripos($line, 'wp:button') !== false
        || stripos($line, 'wp-block-button') !== false) {
        printf("line %3d: %s\n", $i + 1, rtrim($line, "\r"));
    }
}
$nBtns = preg_match_all('/<!--\s*\/?wp:buttons\b/', $content);
$nBtn  = preg_match_all('/<!--\s*\/?wp:button\s/', $content);
echo "\nвхождений 'wp:buttons' (открывающих+закрывающих): {$nBtns}\n";
echo "вхождений 'wp:button ' (открывающих+закрывающих): {$nBtn}\n";

echo "\n######## 1.3b сырые строки 20-40 ########\n";
$lines = explode("\n", $content);
for ($i = 19; $i < 40 && $i < count($lines); $i++) {
    printf("%3d| %s\n", $i + 1, rtrim($lines[$i], "\r"));
}

echo "\n######## 1.3c сырые строки 145-165 ########\n";
for ($i = 144; $i < 165 && $i < count($lines); $i++) {
    printf("%3d| %s\n", $i + 1, rtrim($lines[$i], "\r"));
}

echo "\n######## 1.3d разобранные узлы core/button (путь + attrs + innerHTML) ########\n";
$blocks = parse_blocks($content);
$nodes = [];
$collect = function ($bs, $path) use (&$collect, &$nodes) {
    foreach ($bs as $i => $b) {
        $p = $path === '' ? (string) $i : $path . '.' . $i;
        $nodes[$p] = $b;
        if (!empty($b['innerBlocks'])) { $collect($b['innerBlocks'], $p); }
    }
};
$collect($blocks, '');
foreach ($nodes as $path => $b) {
    if ($b['blockName'] === 'core/button' || $b['blockName'] === 'core/buttons') {
        echo str_repeat('-', 74), "\n";
        echo "[{$path}] {$b['blockName']}\n";
        echo "attrs: " . json_encode($b['attrs'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n";
        echo "parentPath: " . (strpos($path, '.') === false ? '(top level)' : substr($path, 0, strrpos($path, '.'))) . "\n";
        echo "serialize_block:\n" . serialize_block($b) . "\n";
    }
}
