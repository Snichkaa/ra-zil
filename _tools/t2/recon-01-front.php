<?php
require_once __DIR__ . '/../../wp-load.php';

$front_id = (int) get_option('page_on_front');
$show_on_front = get_option('show_on_front');
echo "show_on_front = {$show_on_front}\n";
echo "page_on_front = {$front_id}\n";

$p = get_post($front_id);
if (!$p) { echo "NO POST\n"; exit(1); }
echo "post_title  = {$p->post_title}\n";
echo "post_name   = {$p->post_name}\n";
echo "post_type   = {$p->post_type}\n";
echo "post_status = {$p->post_status}\n";
echo "template    = " . get_post_meta($front_id, '_wp_page_template', true) . "\n";
echo "content len = " . strlen($p->post_content) . " bytes\n";
echo str_repeat('=', 78), "\n";

$blocks = parse_blocks($p->post_content);
function cls($b) {
    $a = isset($b['attrs']) ? $b['attrs'] : [];
    $c = isset($a['className']) ? $a['className'] : '';
    return $c;
}
foreach ($blocks as $i => $b) {
    $name = $b['blockName'] === null ? '(freeform/null)' : $b['blockName'];
    if ($name === '(freeform/null)' && trim($b['innerHTML']) === '') { continue; }
    $html = preg_replace('/\s+/u', ' ', $b['innerHTML']);
    $snip = mb_substr($html, 0, 120, 'UTF-8');
    echo "[{$i}] {$name}\n";
    echo "    className: '" . cls($b) . "'\n";
    $attrs = $b['attrs'];
    unset($attrs['className']);
    if (!empty($attrs)) {
        echo "    attrs    : " . json_encode($attrs, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . "\n";
    }
    echo "    inner120 : {$snip}\n";
    echo "    children : " . count($b['innerBlocks']) . "\n";
}
