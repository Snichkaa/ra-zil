<?php
require_once __DIR__ . '/../../wp-load.php';
echo "home_url: " . home_url() . "\n";
echo "site_url: " . site_url() . "\n";
echo "WP_DEBUG: " . ( defined('WP_DEBUG') && WP_DEBUG ? 'true' : 'false' ) . "\n";
echo "theme: " . get_stylesheet() . "\n";
echo "\n=== wp_template posts in DB (theme override) ===\n";
$q = get_posts( array( 'post_type' => 'wp_template', 'numberposts' => -1, 'post_status' => 'any' ) );
foreach ( $q as $p ) {
    echo sprintf( "id=%d slug=%s status=%s len=%d\n", $p->ID, $p->post_name, $p->post_status, strlen( $p->post_content ) );
}
echo "\n=== wp_template_part posts in DB ===\n";
$q2 = get_posts( array( 'post_type' => 'wp_template_part', 'numberposts' => -1, 'post_status' => 'any' ) );
foreach ( $q2 as $p ) {
    echo sprintf( "id=%d slug=%s status=%s len=%d\n", $p->ID, $p->post_name, $p->post_status, strlen( $p->post_content ) );
}
echo "\n=== front page setting ===\n";
echo "show_on_front: " . get_option('show_on_front') . "\n";
echo "page_on_front: " . get_option('page_on_front') . "\n";
