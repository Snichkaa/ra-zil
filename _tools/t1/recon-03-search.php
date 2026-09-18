<?php
require dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb;

$needles = [
    'Ритуальный агент',
    'ритуальный агент',
    'кладбище и поминальный обед',
    'Юридическая помощь при оформлении документов',
    'Организация похорон',
    'Транспортировка умерших',
    'транспортировка умерших',
];

$fields = ['post_title', 'post_name', 'post_content', 'post_excerpt'];

foreach ($needles as $n) {
    echo str_repeat('=', 78) . "\n";
    echo "NEEDLE: «{$n}»\n";
    $total = 0;
    foreach ($fields as $f) {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_type, post_status, post_title, {$f} AS val FROM {$wpdb->posts} WHERE {$f} LIKE %s AND post_type NOT IN ('revision')",
            '%' . $wpdb->esc_like($n) . '%'
        ));
        foreach ($rows as $r) {
            $c = mb_substr_count($r->val, $n);
            $total += $c;
            printf("  posts.%-13s ID=%-5s type=%-10s status=%-9s hits=%d  title=%s\n", $f, $r->ID, $r->post_type, $r->post_status, $c, $r->post_title);
        }
    }
    // postmeta
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT meta_id, post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_value LIKE %s",
        '%' . $wpdb->esc_like($n) . '%'
    ));
    foreach ($rows as $r) {
        $c = mb_substr_count($r->meta_value, $n);
        $total += $c;
        printf("  postmeta       post_id=%-5s key=%-28s hits=%d\n", $r->post_id, $r->meta_key, $c);
    }
    // options
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT option_id, option_name FROM {$wpdb->options} WHERE option_value LIKE %s",
        '%' . $wpdb->esc_like($n) . '%'
    ));
    foreach ($rows as $r) {
        printf("  options        name=%s\n", $r->option_name);
        $total++;
    }
    // terms
    $rows = $wpdb->get_results($wpdb->prepare("SELECT term_id, name, slug FROM {$wpdb->terms} WHERE name LIKE %s", '%' . $wpdb->esc_like($n) . '%'));
    foreach ($rows as $r) { printf("  terms          term_id=%s name=%s\n", $r->term_id, $r->name); $total++; }
    if ($total === 0) echo "  (совпадений в БД нет)\n";
    echo "  ИТОГО в БД: {$total}\n";
}

echo "\n" . str_repeat('=', 78) . "\n";
echo "=== REVISIONS containing needles (info only) ===\n";
foreach (['Ритуальный агент', 'кладбище и поминальный обед'] as $n) {
    $c = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='revision' AND (post_content LIKE %s OR post_excerpt LIKE %s)", '%'.$wpdb->esc_like($n).'%', '%'.$wpdb->esc_like($n).'%'));
    echo "  «{$n}»: {$c} ревизий\n";
}
