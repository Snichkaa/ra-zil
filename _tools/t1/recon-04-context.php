<?php
require dirname(__DIR__, 2) . '/wp-load.php';

echo "=== HEADINGS в post_content записей 11-15 ===\n";
foreach ([11,12,13,14,15] as $id) {
    $p = get_post($id);
    echo "--- ID={$id} «{$p->post_title}» ---\n";
    if (preg_match_all('#<!-- wp:heading(.*?)-->\s*(<h[1-6][^>]*>)(.*?)(</h[1-6]>)#us', $p->post_content, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) {
            echo "   " . trim($x[2]) . ' ' . wp_strip_all_tags($x[3]) . "\n";
        }
    } else { echo "   (заголовков wp:heading нет)\n"; }
}

echo "\n=== КОНТЕКСТ «Ритуальный агент» и «Организация похорон» в ID=11 ===\n";
$c = get_post(11)->post_content;
foreach (['Ритуальный агент', 'Организация похорон'] as $n) {
    $pos = 0;
    while (($pos = mb_strpos($c, $n, $pos)) !== false) {
        echo "--- «{$n}» @ char {$pos} ---\n";
        echo mb_substr($c, max(0, $pos - 260), 620) . "\n\n";
        $pos += mb_strlen($n);
    }
}

echo "=== ПЕРВЫЙ абзац ID=13 + все вхождения «транспортиров»/«перевоз» ===\n";
$c13 = get_post(13)->post_content;
if (preg_match('#<!-- wp:paragraph.*?-->\s*<p[^>]*>(.*?)</p>#us', $c13, $m)) {
    echo "ПЕРВЫЙ АБЗАЦ: " . wp_strip_all_tags($m[1]) . "\n\n";
}
foreach (['транспортиров', 'Транспортиров', 'перевоз', 'Перевоз', 'умерш', 'усопш'] as $n) {
    echo sprintf("%-16s content=%d  title=%d  excerpt=%d\n", $n,
        mb_substr_count($c13, $n),
        mb_substr_count(get_post(13)->post_title, $n),
        mb_substr_count(get_post(13)->post_excerpt, $n));
}

echo "\n=== ТЕКУЩИЙ ПОРЯДОК карточек по запросу архива (postType=services, orderBy=title, order=asc, perPage=9) ===\n";
$q = new WP_Query(['post_type'=>'services','posts_per_page'=>9,'orderby'=>'title','order'=>'ASC','post_status'=>'publish']);
$i = 1;
foreach ($q->posts as $p) {
    printf("%d) ID=%-4s menu_order=%-3s %s\n", $i++, $p->ID, $p->menu_order, $p->post_title);
}
