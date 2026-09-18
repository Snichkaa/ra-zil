<?php
require_once __DIR__ . '/../../wp-load.php';
$file = WP_CONTENT_DIR . '/themes/razil/templates/front-page.html';
$content = file_get_contents($file);
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

$re = '/\b(вы|вам|вами|вас|ваш[а-яё]*)\b/ui';
$rows = [];
foreach ($nodes as $path => $b) {
    $html = $b['innerHTML'];
    if (trim($html) === '') { continue; }
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (!preg_match_all($re, $text, $m, PREG_OFFSET_CAPTURE)) { continue; }
    foreach ($m[1] as $hit) {
        $word = $hit[0];
        $first = mb_substr($word, 0, 1, 'UTF-8');
        $upper = mb_strtoupper($first, 'UTF-8') === $first;
        $charOff = mb_strlen(substr($text, 0, $hit[1]), 'UTF-8');
        $cs = max(0, $charOff - 26);
        $ctx = mb_substr($text, $cs, ($charOff - $cs) + mb_strlen($word, 'UTF-8') + 26, 'UTF-8');
        $rows[] = [$path, $word, $upper ? 'ПРОПИСНАЯ' : 'строчная', preg_replace('/\s+/u', ' ', $ctx)];
    }
}
printf("%-10s %-8s %-10s %s\n", 'ПУТЬ', 'СЛОВО', 'РЕГИСТР', 'КОНТЕКСТ');
echo str_repeat('=', 110), "\n";
$up = 0; $lo = 0;
foreach ($rows as $r) {
    printf("%-10s %-8s %-10s ...%s...\n", '['.$r[0].']', $r[1], $r[2], $r[3]);
    if ($r[2] === 'ПРОПИСНАЯ') { $up++; } else { $lo++; }
}
echo str_repeat('=', 110), "\n";
echo "ВСЕГО по главной: " . count($rows) . "   ПРОПИСНАЯ: {$up}   строчная: {$lo}\n";
