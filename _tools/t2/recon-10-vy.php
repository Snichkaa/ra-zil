<?php
require_once __DIR__ . '/../../wp-load.php';
$file = WP_CONTENT_DIR . '/themes/razil/templates/front-page.html';
$blocks = parse_blocks(file_get_contents($file));

echo "######## 1.2a innerHTML [5.1] core/paragraph rz-first-aid__lead ########\n";
echo ">>>" . $blocks[5]['innerBlocks'][1]['innerHTML'] . "<<<\n";
echo "attrs: " . json_encode($blocks[5]['innerBlocks'][1]['attrs'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n";

echo "\n######## 1.2b innerHTML [5.3] core/paragraph rz-first-aid__note ########\n";
echo ">>>" . $blocks[5]['innerBlocks'][3]['innerHTML'] . "<<<\n";
echo "attrs: " . json_encode($blocks[5]['innerBlocks'][3]['attrs'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n";

echo "\n######## 1.2c все словоформы вы/вам/вас/ваш внутри блока [5] ########\n";

// Пути: только те узлы блока [5], где есть текст.
$nodes = [];
$collect = function ($bs, $path) use (&$collect, &$nodes) {
    foreach ($bs as $i => $b) {
        $p = $path === '' ? (string) $i : $path . '.' . $i;
        $nodes[$p] = $b;
        if (!empty($b['innerBlocks'])) { $collect($b['innerBlocks'], $p); }
    }
};
$collect([5 => $blocks[5]], '');

$re = '/\b(вы|вам|вами|вас|ваш[а-яё]*)\b/ui';
$total = 0;
$byCase = ['прописная' => 0, 'строчная' => 0];

foreach ($nodes as $path => $b) {
    $html = $b['innerHTML'];
    if (trim($html) === '') { continue; }
    // Теги убираем, чтобы не ловить слова внутри атрибутов; текст в теге не встречается.
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (!preg_match_all($re, $text, $m, PREG_OFFSET_CAPTURE)) { continue; }
    $name = $b['blockName'] === null ? '(null)' : $b['blockName'];
    $cn = isset($b['attrs']['className']) ? $b['attrs']['className'] : '';
    echo str_repeat('-', 74), "\n";
    echo "[{$path}] {$name}" . ($cn !== '' ? "  className='{$cn}'" : '') . "\n";
    foreach ($m[1] as $hit) {
        $word = $hit[0];
        $off  = $hit[1];
        $first = mb_substr($word, 0, 1, 'UTF-8');
        $isUpper = mb_strtoupper($first, 'UTF-8') === $first;
        $case = $isUpper ? 'прописная' : 'строчная';
        $byCase[$case]++;
        $total++;
        $ctxStart = max(0, $off - 30);
        $ctx = mb_substr($text, mb_strlen(substr($text, 0, $ctxStart), 'UTF-8'), 0, 'UTF-8');
        // контекст ±30 символов вокруг слова, по символам
        $charOff = mb_strlen(substr($text, 0, $off), 'UTF-8');
        $cs = max(0, $charOff - 30);
        $ctx = mb_substr($text, $cs, ($charOff - $cs) + mb_strlen($word, 'UTF-8') + 30, 'UTF-8');
        printf("    '%s'  первая буква: %s (%s)  байт-офсет в тексте: %d\n", $word, $first, $case, $off);
        printf("        ...%s...\n", preg_replace('/\s+/u', ' ', $ctx));
    }
}
echo str_repeat('-', 74), "\n";
echo "ИТОГО в блоке [5]: {$total} вхождений\n";
echo "  с прописной: {$byCase['прописная']}\n";
echo "  со строчной: {$byCase['строчная']}\n";
