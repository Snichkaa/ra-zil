<?php
/**
 * Этап 4.0, добор. Фактические классы в DOM и порядок таблиц стилей.
 * Только чтение, ничего не пишет.
 */
require_once __DIR__ . '/../../wp-load.php';

echo "################################################################\n";
echo "## ПОРЯДОК ПЕЧАТИ ТАБЛИЦ СТИЛЕЙ\n";
echo "################################################################\n";
do_action('wp_enqueue_scripts');
global $wp_styles;
$wp_styles->all_deps($wp_styles->queue);
foreach ($wp_styles->to_do as $i => $handle) {
    $src = isset($wp_styles->registered[$handle]->src) ? $wp_styles->registered[$handle]->src : '';
    $extra = $wp_styles->registered[$handle]->extra;
    $inlineLen = 0;
    foreach (['after', 'before'] as $k) {
        if (!empty($extra[$k])) { $inlineLen += strlen(implode('', (array) $extra[$k])); }
    }
    printf("  %2d. %-26s %-58s %s\n", $i + 1, $handle,
        $src ? $src : '(только инлайн)',
        $inlineLen ? '[инлайн ' . $inlineLen . ' байт]' : '');
}
echo "\nВЫВОД: правила main.css идут ПОЗЖЕ global-styles, значит при равной\n";
echo "специфичности (0,1,0) побеждает main.css.\n";

echo "\n################################################################\n";
echo "## ФАКТИЧЕСКИЕ КЛАССЫ: существующая группа только с className\n";
echo "################################################################\n";
$sample = '<!-- wp:group {"className":"rz-reviews__head"} --><div class="wp-block-group rz-reviews__head"><!-- wp:paragraph --><p>x</p><!-- /wp:paragraph --></div><!-- /wp:group -->';
echo "разметка в шаблоне:\n  " . $sample . "\n\n";
echo "после render:\n  " . trim(do_blocks($sample)) . "\n";

echo "\n################################################################\n";
echo "## ФАКТИЧЕСКИЕ КЛАССЫ: предлагаемая обёртка rz-callout\n";
echo "################################################################\n";
$proposed = '<!-- wp:group {"className":"rz-callout rz-callout--warn"} -->' . "\r\n"
    . '<div class="wp-block-group rz-callout rz-callout--warn">' . "\r\n"
    . '<!-- wp:paragraph {"className":"rz-first-aid__warning"} -->' . "\r\n"
    . '<p class="rz-first-aid__warning"><strong>Не пускайте в дом посторонних ритуальных агентов.</strong> Иногда они приезжают сами, без вызова, и представляются сотрудниками служб. Наш агент назовёт свою фамилию, которую вы услышите от нас по телефону.</p>' . "\r\n"
    . '<!-- /wp:paragraph -->' . "\r\n"
    . '</div>' . "\r\n"
    . '<!-- /wp:group -->';
echo "разметка:\n" . $proposed . "\n\n";
$rendered = do_blocks($proposed);
echo "после render:\n" . $rendered . "\n";
if (preg_match('/<div class="([^"]*)"/', $rendered, $m)) {
    echo "\nИТОГОВАЯ ЦЕПОЧКА КЛАССОВ ОБЁРТКИ В DOM: " . $m[1] . "\n";
    echo "классы по одному:\n";
    foreach (preg_split('/\s+/', trim($m[1])) as $c) { echo "  - {$c}\n"; }
}

echo "\n-- проверка круговорота предлагаемой разметки --\n";
$p = parse_blocks($proposed);
$s = serialize_blocks($p);
printf("parse -> serialize identical: %s\n", $proposed === $s ? 'YES' : 'NO');
printf("className как вернул parse_blocks   : %s\n", var_export($p[0]['attrs']['className'], true));
if (preg_match('/"className":"([^"]*)"/', $s, $mm)) {
    printf("className как вернул serialize_blocks: %s\n", var_export($mm[1], true));
}

echo "\n################################################################\n";
echo "## ТЕКУЩАЯ ГРУППА [5]: какие классы у неё в DOM\n";
echo "################################################################\n";
$file = WP_CONTENT_DIR . '/themes/razil/templates/front-page.html';
$blocks = parse_blocks(file_get_contents($file));
$grp = serialize_block($blocks[5]);
$renderedGrp = do_blocks($grp);
if (preg_match('/<div class="([^"]*)"/', $renderedGrp, $m5)) {
    echo "обёртка блока [5]: " . $m5[1] . "\n";
}
echo "\n-- позиция абзаца предупреждения среди детей группы [5] --\n";
$kids = [];
foreach ($blocks[5]['innerBlocks'] as $i => $b) {
    $cn = isset($b['attrs']['className']) ? $b['attrs']['className'] : '';
    $kids[] = sprintf("[5.%d] %s %s", $i, $b['blockName'], $cn);
}
foreach ($kids as $i => $k) {
    $mark = '';
    if ($i === 0) { $mark .= ' <- :first-child'; }
    if ($i === count($kids) - 1) { $mark .= ' <- :last-child'; }
    echo "  " . $k . $mark . "\n";
}

echo "\n################################################################\n";
echo "## 4.0.5. КНОПКА rz-reviews-list__more: УСЛОВИЕ\n";
echo "################################################################\n";
$render = WP_CONTENT_DIR . '/themes/razil/src/blocks/reviews/render.php';
$src = file_get_contents($render);
$lines = explode("\n", $src);
// найти строку с кнопкой и показать окружение с запасом
foreach ($lines as $i => $l) {
    if (strpos($l, 'rz-reviews-list__more') !== false) {
        $from = max(0, $i - 30);
        $to = min(count($lines) - 1, $i + 12);
        for ($j = $from; $j <= $to; $j++) { printf("%3d| %s\n", $j + 1, rtrim($lines[$j], "\r")); }
        break;
    }
}
echo "\n-- как читаются атрибуты limit / display --\n";
foreach ($lines as $i => $l) {
    if (preg_match('/\$attributes|\$rz_limit|\$rz_display|limit|display/', $l)
        && !preg_match('/^\s*\*/', $l) && trim($l) !== '') {
        printf("%3d| %s\n", $i + 1, rtrim($l, "\r"));
    }
}

echo "\n-- block.json блока reviews: значения по умолчанию --\n";
foreach (['src/blocks/reviews/block.json', 'build/blocks/reviews/block.json'] as $bj) {
    $p2 = WP_CONTENT_DIR . '/themes/razil/' . $bj;
    if (file_exists($p2)) {
        echo "--- {$bj}\n" . file_get_contents($p2) . "\n";
    }
}
