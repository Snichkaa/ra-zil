<?php
require_once __DIR__ . '/../../wp-load.php';
$file = WP_CONTENT_DIR . '/themes/razil/templates/front-page.html';
$content = file_get_contents($file);
$blocks = parse_blocks($content);

function dump($blocks, $path, $depth) {
    foreach ($blocks as $i => $b) {
        $p = $path === '' ? (string)$i : $path . '.' . $i;
        $name = $b['blockName'] === null ? '(null)' : $b['blockName'];
        if ($name === '(null)' && trim($b['innerHTML']) === '') continue;
        $pad = str_repeat('  ', $depth);
        $attrs = isset($b['attrs']) ? $b['attrs'] : [];
        echo "{$pad}[{$p}] {$name}";
        if ($attrs) echo "  attrs=" . json_encode($attrs, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        echo "\n";
        $ih = $b['innerHTML'];
        if (trim($ih) !== '') {
            echo "{$pad}  innerHTML >>>" . $ih . "<<<\n";
        }
        if (!empty($b['innerBlocks'])) dump($b['innerBlocks'], $p, $depth + 1);
    }
}

echo "######## BLOCK [5]  rz-first-aid — FULL SUBTREE ########\n";
dump([5 => $blocks[5]], '', 0);

echo "\n\n######## BLOCK [5] RAW SERIALIZED (serialize_block) ########\n";
echo serialize_block($blocks[5]);

echo "\n\n######## tel: links inside block [5] ########\n";
$ser = serialize_block($blocks[5]);
if (preg_match_all('/<a\b[^>]*href="tel:[^"]*"[^>]*>.*?<\/a>/us', $ser, $m)) {
    foreach ($m[0] as $k => $a) { echo "[$k] {$a}\n"; }
} else { echo "(none)\n"; }
echo "total tel anchors: " . (isset($m[0]) ? count($m[0]) : 0) . "\n";

echo "\n\n######## ALL tel: anchors in whole file ########\n";
preg_match_all('/<a\b[^>]*href="tel:[^"]*"[^>]*>.*?<\/a>/us', $content, $m2);
foreach ($m2[0] as $k => $a) {
    $pos = strpos($content, $a);
    $line = substr_count(substr($content, 0, $pos), "\n") + 1;
    echo "[$k] line {$line}: {$a}\n";
}
