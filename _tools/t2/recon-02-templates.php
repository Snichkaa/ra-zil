<?php
require_once __DIR__ . '/../../wp-load.php';

echo "== wp_template / wp_template_part posts in DB ==\n";
$q = get_posts([
    'post_type'   => ['wp_template', 'wp_template_part'],
    'post_status' => 'any',
    'numberposts' => -1,
]);
if (!$q) { echo "(none)\n"; }
foreach ($q as $t) {
    $terms = wp_get_post_terms($t->ID, 'wp_theme', ['fields' => 'names']);
    echo sprintf("ID=%d type=%s slug=%s status=%s theme=%s len=%d\n",
        $t->ID, $t->post_type, $t->post_name, $t->post_status,
        implode(',', (array) $terms), strlen($t->post_content));
}

echo "\n== template files on disk (razil) ==\n";
$dir = WP_CONTENT_DIR . '/themes/razil';
foreach (['templates', 'parts', 'patterns'] as $sub) {
    foreach ((array) glob($dir . '/' . $sub . '/*') as $f) {
        echo sprintf("%-46s %7d bytes\n", $sub . '/' . basename($f), filesize($f));
    }
}

echo "\n== pages in DB ==\n";
foreach (get_posts(['post_type'=>'page','post_status'=>'any','numberposts'=>-1]) as $pg) {
    echo sprintf("ID=%-4d status=%-8s len=%-7d slug=%-24s title=%s\n",
        $pg->ID, $pg->post_status, strlen($pg->post_content), $pg->post_name, $pg->post_title);
}

echo "\n== posts (any type) containing marker strings ==\n";
global $wpdb;
$needles = [
  'через кассу с выдачей чека',
  'от оформления документов',
  'Что делать, если уход наступил дома',
  'Не пускайте в дом посторонних ритуальных агентов',
];
foreach ($needles as $n) {
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT ID, post_type, post_name, post_status FROM {$wpdb->posts} WHERE post_content LIKE %s",
        '%' . $wpdb->esc_like($n) . '%'
    ));
    echo "-- '{$n}':\n";
    if (!$rows) { echo "   (no DB rows)\n"; }
    foreach ($rows as $r) { echo "   ID={$r->ID} type={$r->post_type} slug={$r->post_name} status={$r->post_status}\n"; }
}
