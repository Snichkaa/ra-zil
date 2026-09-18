<?php
require_once __DIR__ . '/../../wp-load.php';

$file = WP_CONTENT_DIR . '/themes/razil/templates/front-page.html';
$content = file_get_contents($file);

$needles = [
  'через кассу с выдачей чека',
  'от оформления документов',
  'Что делать, если уход наступил дома',
  'Не пускайте в дом посторонних ритуальных агентов',
];

echo "### A. Raw byte offsets in file ###\n";
foreach ($needles as $n) {
    echo "-- '{$n}'\n";
    $off = 0; $hits = 0;
    while (($p = strpos($content, $n, $off)) !== false) {
        $hits++;
        $line = substr_count(substr($content, 0, $p), "\n") + 1;
        echo "   byte {$p}, line {$line}\n";
        $off = $p + 1;
    }
    if (!$hits) echo "   NOT FOUND\n";
    echo "   total: {$hits}\n";
}

echo "\n### B. Block paths ###\n";
$blocks = parse_blocks($content);

function walk($blocks, $path, $needles, &$out) {
    foreach ($blocks as $i => $b) {
        $p = $path === '' ? (string)$i : $path . '.' . $i;
        $name = $b['blockName'] === null ? '(null)' : $b['blockName'];
        $html = $b['innerHTML'];
        foreach ($needles as $n) {
            $off = 0;
            while (($q = strpos($html, $n, $off)) !== false) {
                $out[] = [
                    'needle' => $n,
                    'path'   => $p,
                    'block'  => $name,
                    'class'  => isset($b['attrs']['className']) ? $b['attrs']['className'] : '',
                    'posInInnerHTML' => $q,
                    'innerHTML' => $html,
                ];
                $off = $q + 1;
            }
        }
        if (!empty($b['innerBlocks'])) walk($b['innerBlocks'], $p, $needles, $out);
    }
}
$out = [];
walk($blocks, '', $needles, $out);

foreach ($out as $h) {
    echo str_repeat('-', 74), "\n";
    echo "needle : {$h['needle']}\n";
    echo "path   : [{$h['path']}]\n";
    echo "block  : {$h['block']}\n";
    echo "class  : '{$h['class']}'\n";
    echo "offset : {$h['posInInnerHTML']} (within that block's innerHTML)\n";
    echo "innerHTML:\n" . $h['innerHTML'] . "\n";
}
echo str_repeat('-', 74), "\n";
echo "matches in block innerHTML: " . count($out) . "\n";
