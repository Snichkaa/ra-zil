<?php
require dirname(__DIR__, 2) . '/wp-load.php';

function out($s = '') { echo $s . "\n"; }

$page = get_page_by_path('uslugi', OBJECT, ['page', 'post']);
if (!$page) {
    // try any post type
    global $wpdb;
    $r = $wpdb->get_row("SELECT ID, post_type, post_title, post_status FROM {$wpdb->posts} WHERE post_name='uslugi'");
    out('get_page_by_path FAILED; raw lookup: ' . print_r($r, true));
    exit(1);
}
out('=== PAGE /uslugi/ ===');
out('ID=' . $page->ID . ' post_type=' . $page->post_type . ' post_status=' . $page->post_status);
out('post_title=' . $page->post_title);
out('post_name=' . $page->post_name);
out('post_parent=' . $page->post_parent);
out('menu_order=' . $page->menu_order);
out('page_template meta=' . get_post_meta($page->ID, '_wp_page_template', true));
out('content length=' . strlen($page->post_content));
out('');

$blocks = parse_blocks($page->post_content);
out('=== TOP-LEVEL parse_blocks (count=' . count($blocks) . ') ===');
foreach ($blocks as $i => $b) {
    $name = $b['blockName'] === null ? '(null/html)' : $b['blockName'];
    $cls  = isset($b['attrs']['className']) ? $b['attrs']['className'] : '';
    $inner = preg_replace('/\s+/u', ' ', $b['innerHTML']);
    out(sprintf('[%02d] blockName=%s', $i, $name));
    out('     className=' . $cls);
    out('     attrs=' . json_encode($b['attrs'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    out('     innerHTML[0:120]=' . mb_substr($inner, 0, 120));
    out('     innerBlocks=' . count($b['innerBlocks']));
}
out('');
out('=== FULL RAW CONTENT ===');
out($page->post_content);
