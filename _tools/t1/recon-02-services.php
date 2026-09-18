<?php
require dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb;
foreach ([11,12,13,14,15] as $id) {
    $p = get_post($id);
    echo str_repeat('=', 78) . "\n";
    printf("ID=%d  post_type=%s  status=%s  menu_order=%d\n", $p->ID, $p->post_type, $p->post_status, $p->menu_order);
    echo "post_title   = " . $p->post_title . "\n";
    echo "post_name    = " . $p->post_name . "\n";
    echo "permalink    = " . get_permalink($p) . "\n";
    echo "excerpt?     = " . (trim($p->post_excerpt) === '' ? 'NO (empty)' : 'YES len=' . strlen($p->post_excerpt)) . "\n";
    echo "post_excerpt = [" . $p->post_excerpt . "]\n";
    echo "content len  = " . strlen($p->post_content) . "\n";
    echo "--- post_content[0:400 chars] ---\n";
    echo mb_substr($p->post_content, 0, 400) . "\n";
    echo "--- meta ---\n";
    foreach (['_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_yoast_wpseo_focuskw'] as $k) {
        $v = get_post_meta($id, $k, true);
        printf("%-24s = [%s]\n", $k, $v === '' ? '(empty/absent)' : $v);
    }
    echo "--- all meta keys ---\n";
    $keys = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT meta_key FROM {$wpdb->postmeta} WHERE post_id=%d ORDER BY meta_key", $id));
    echo implode(', ', $keys) . "\n";
}
