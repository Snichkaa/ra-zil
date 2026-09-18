<?php
require_once __DIR__ . '/../../wp-load.php';

$file = WP_CONTENT_DIR . '/themes/razil/templates/front-page.html';
$content = file_get_contents($file);
echo "file: {$file}\n";
echo "bytes: " . strlen($content) . "\n";
echo str_repeat('=', 78), "\n";

$blocks = parse_blocks($content);
echo "top-level nodes: " . count($blocks) . "\n\n";

foreach ($blocks as $i => $b) {
    $name = $b['blockName'] === null ? '(null)' : $b['blockName'];
    $raw  = $b['innerHTML'];
    if ($name === '(null)' && trim($raw) === '') {
        echo "[{$i}] (whitespace only)\n";
        continue;
    }
    $attrs = isset($b['attrs']) ? $b['attrs'] : [];
    $cn = isset($attrs['className']) ? $attrs['className'] : '';
    unset($attrs['className']);
    $flat = preg_replace('/\s+/u', ' ', $raw);
    echo "[{$i}] {$name}\n";
    echo "     className: '{$cn}'\n";
    if ($attrs) echo "     attrs    : " . json_encode($attrs, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n";
    echo "     inner120 : " . mb_substr($flat, 0, 120, 'UTF-8') . "\n";
    echo "     children : " . count($b['innerBlocks']) . "\n";
}
