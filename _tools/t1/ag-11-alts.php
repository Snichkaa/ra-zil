<?php
require dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb;
echo "=== postmeta._wp_attachment_image_alt со словом «агент» ===\n";
$rows = $wpdb->get_results("SELECT meta_id, post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key='_wp_attachment_image_alt' AND meta_value LIKE '%агент%' ORDER BY post_id");
foreach ($rows as $r) {
    preg_match_all('/агент\p{Cyrillic}*/iu', $r->meta_value, $m);
    $forms = [];
    foreach ($m[0] as $f) {
        $forms[] = '«' . $f . '»' . (0 === mb_stripos($f, 'агентств') ? ' [АГЕНТСТВО]' : ' [СОТРУДНИК]');
    }
    printf("post_id=%-5s meta_id=%-5s\n  alt: %s\n  словоформы: %s\n\n", $r->post_id, $r->meta_id, $r->meta_value, implode(', ', $forms));
}
printf("всего строк: %d\n", count($rows));
