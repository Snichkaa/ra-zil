<?php
require_once __DIR__ . '/../../wp-load.php';
$file = WP_CONTENT_DIR . '/themes/razil/templates/front-page.html';
$blocks = parse_blocks(file_get_contents($file));

$list = $blocks[9]['innerBlocks'][1]; // rz-advantages__list
echo "######## 1.4 карточки преимуществ [9.1.*] ########\n";
echo "карточек в списке: " . count($list['innerBlocks']) . "\n";

foreach ($list['innerBlocks'] as $i => $card) {
    $path = "9.1.{$i}";
    echo str_repeat('=', 74), "\n";
    echo "[{$path}] {$card['blockName']}  attrs=" . json_encode($card['attrs'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n";
    foreach ($card['innerBlocks'] as $j => $ch) {
        $cp = "{$path}.{$j}";
        if ($ch['blockName'] !== 'core/paragraph') {
            echo "  [{$cp}] {$ch['blockName']} (пропускаю, не абзац)\n";
            continue;
        }
        echo "  [{$cp}] core/paragraph  attrs=" . json_encode($ch['attrs'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n";
        echo "  innerHTML >>>" . $ch['innerHTML'] . "<<<\n";
        // текст абзаца без тегов
        if (preg_match('#<p\b[^>]*>(.*)</p>#us', $ch['innerHTML'], $m)) {
            $txt = html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $lastChar = mb_substr($txt, -1, 1, 'UTF-8');
            $lastBytes = bin2hex(mb_substr($txt, -1, 1, 'UTF-8'));
            printf("  текст (%d симв.): %s\n", mb_strlen($txt, 'UTF-8'), $txt);
            printf("  ПОСЛЕДНИЙ СИМВОЛ: '%s' (hex %s)  ->  точка в конце: %s\n",
                $lastChar, $lastBytes, $lastChar === '.' ? 'ЕСТЬ' : 'НЕТ');
        }
    }
}
echo str_repeat('=', 74), "\n";
echo "\n######## сводка: точка в конце абзаца по каждой карточке ########\n";
foreach ($list['innerBlocks'] as $i => $card) {
    foreach ($card['innerBlocks'] as $j => $ch) {
        if ($ch['blockName'] !== 'core/paragraph') { continue; }
        if (preg_match('#<p\b[^>]*>(.*)</p>#us', $ch['innerHTML'], $m)) {
            $txt = html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $bold = '';
            if (preg_match('#<strong>(.*?)</strong>#us', $ch['innerHTML'], $b)) { $bold = $b[1]; }
            printf("[9.1.%d.%d] %-28s точка: %s\n", $i, $j, mb_substr($bold, 0, 28, 'UTF-8'),
                mb_substr($txt, -1, 1, 'UTF-8') === '.' ? 'ЕСТЬ' : 'НЕТ');
        }
    }
}
