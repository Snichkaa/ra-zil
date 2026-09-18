<?php
require dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb;
echo "=== POST TYPES ===\n";
foreach ($wpdb->get_results("SELECT post_type, post_status, COUNT(*) c FROM {$wpdb->posts} GROUP BY post_type, post_status ORDER BY post_type") as $r) {
    printf("%-22s %-12s %d\n", $r->post_type, $r->post_status, $r->c);
}
echo "\n=== ALL non-revision/non-attachment posts ===\n";
$rows = $wpdb->get_results("SELECT ID, post_type, post_status, post_name, post_title, post_parent, menu_order FROM {$wpdb->posts} WHERE post_type NOT IN ('revision','attachment','nav_menu_item') ORDER BY post_type, menu_order, ID");
foreach ($rows as $r) {
    printf("ID=%-5s type=%-18s status=%-10s menu_order=%-3s parent=%-4s name=%-38s title=%s\n",
        $r->ID, $r->post_type, $r->post_status, $r->menu_order, $r->post_parent, $r->post_name, $r->post_title);
}
echo "\n=== nav_menu_item posts ===\n";
$rows = $wpdb->get_results("SELECT ID, post_status, post_name, post_title, menu_order FROM {$wpdb->posts} WHERE post_type='nav_menu_item' ORDER BY menu_order");
if (!$rows) echo "(none)\n";
foreach ($rows as $r) { printf("ID=%-5s status=%-10s order=%-3s title=[%s] name=%s\n", $r->ID, $r->post_status, $r->menu_order, $r->post_title, $r->post_name); }
echo "\n=== wp_navigation posts ===\n";
$rows = $wpdb->get_results("SELECT ID, post_status, post_name, post_title FROM {$wpdb->posts} WHERE post_type='wp_navigation'");
if (!$rows) echo "(none)\n";
foreach ($rows as $r) { printf("ID=%s status=%s name=%s title=%s\n", $r->ID, $r->post_status, $r->post_name, $r->post_title); }
echo "\n=== nav_menu taxonomy terms ===\n";
$terms = get_terms(['taxonomy' => 'nav_menu', 'hide_empty' => false]);
if (is_wp_error($terms) || !$terms) echo "(none)\n";
else foreach ($terms as $t) printf("term_id=%s slug=%s name=%s count=%s\n", $t->term_id, $t->slug, $t->name, $t->count);
echo "\n=== active theme ===\n";
echo get_stylesheet() . " / template=" . get_template() . "\n";
echo "\n=== active plugins ===\n";
print_r(get_option('active_plugins'));
