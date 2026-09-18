<?php
require dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb;

echo "=== Проверка: есть ли шаблоны/части в БД (wp_template / wp_template_part) ===\n";
$n = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type IN ('wp_template','wp_template_part','wp_navigation')");
echo "записей wp_template/wp_template_part/wp_navigation в БД: {$n}\n";
$src = get_stylesheet_directory() . '/templates/archive-services.html';
echo "источник /uslugi/: {$src}\n";
echo "существует: " . (file_exists($src) ? 'да' : 'нет') . "\n\n";

$html = file_get_contents($src);
$blocks = parse_blocks($html);
echo "=== parse_blocks ВЕРХНЕГО УРОВНЯ archive-services.html (count=" . count($blocks) . ") ===\n";
foreach ($blocks as $i => $b) {
    $name = $b['blockName'] === null ? '(null — сырой HTML/пробелы)' : $b['blockName'];
    if ($b['blockName'] === null && trim($b['innerHTML']) === '') continue;
    $cls = $b['attrs']['className'] ?? '(нет)';
    $inner = preg_replace('/\s+/u', ' ', trim($b['innerHTML']));
    printf("[%02d] blockName = %s\n", $i, $name);
    printf("     className = %s\n", $cls);
    printf("     attrs     = %s\n", json_encode($b['attrs'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    printf("     innerHTML[0:120] = %s\n", mb_substr($inner, 0, 120));
    printf("     innerBlocks = %d\n", count($b['innerBlocks']));
}

echo "\n=== ВЛОЖЕННОЕ ДЕРЕВО (только значимые блоки) ===\n";
$walk = function ($bs, $d = 0) use (&$walk) {
    foreach ($bs as $b) {
        if ($b['blockName'] === null) { continue; }
        $cls = isset($b['attrs']['className']) ? ' .' . $b['attrs']['className'] : '';
        $extra = '';
        if ($b['blockName'] === 'core/query') {
            $extra = ' query=' . json_encode($b['attrs']['query'], JSON_UNESCAPED_UNICODE);
        }
        $txt = preg_replace('/\s+/u', ' ', trim(wp_strip_all_tags($b['innerHTML'])));
        echo str_repeat('  ', $d) . '- ' . $b['blockName'] . $cls . $extra;
        if ($txt !== '') echo '  «' . mb_substr($txt, 0, 80) . '»';
        echo "\n";
        $walk($b['innerBlocks'], $d + 1);
    }
};
$walk($blocks);

echo "\n=== 5 вхождений «транспортиров» в теле ID=13 ===\n";
$c = get_post(13)->post_content;
$pos = 0; $k = 1;
while (($pos = mb_stripos($c, 'транспортиров', $pos)) !== false) {
    $frag = preg_replace('/\s+/u', ' ', wp_strip_all_tags(mb_substr($c, max(0, $pos - 90), 200)));
    echo "  [{$k}] ..." . $frag . "...\n";
    $k++; $pos += 13;
}
