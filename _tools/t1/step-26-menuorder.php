<?php
/**
 * Этап 2.6 — menu_order прямым UPDATE по wp_posts, без wp_update_post.
 * Ревизии не плодятся, индексаблы menu_order не касается.
 * После записи — clean_post_cache() по каждому ID.
 */
require dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb;

$ORDER = array(
	11 => 10,
	12 => 20,
	14 => 30,
	13 => 40,
	15 => 50,
);

echo "=== DRY-RUN 2.6 ===\n";
printf( "%-5s %-14s %-14s %s\n", 'ID', 'menu_order было', 'станет', 'post_title' );
echo str_repeat( '-', 100 ) . "\n";
foreach ( $ORDER as $id => $ord ) {
	$r = $wpdb->get_row( $wpdb->prepare( "SELECT menu_order, post_title FROM {$wpdb->posts} WHERE ID=%d", $id ), ARRAY_A );
	printf( "%-5s %-14s %-14s %s\n", $id, $r['menu_order'], $ord, $r['post_title'] );
}

echo "\n=== ЗАПИСЬ: \$wpdb->update() по wp_posts ===\n";
$rev_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='revision'" );
$mod_before = array();
foreach ( array_keys( $ORDER ) as $id ) {
	$mod_before[ $id ] = $wpdb->get_var( $wpdb->prepare( "SELECT post_modified FROM {$wpdb->posts} WHERE ID=%d", $id ) );
}
printf( "ревизий в базе до записи: %d\n\n", $rev_before );

foreach ( $ORDER as $id => $ord ) {
	$res = $wpdb->update( $wpdb->posts, array( 'menu_order' => $ord ), array( 'ID' => $id ), array( '%d' ), array( '%d' ) );
	printf( "ID=%-4s wpdb->update вернул = %s   last_error=%s\n", $id, var_export( $res, true ), '' === $wpdb->last_error ? '(нет)' : $wpdb->last_error );
}

echo "\n=== clean_post_cache() ===\n";
foreach ( array_keys( $ORDER ) as $id ) {
	clean_post_cache( $id );
	printf( "clean_post_cache(%d) выполнен\n", $id );
}

echo "\n=== SELECT ID, menu_order, post_title после записи ===\n";
$rows = $wpdb->get_results( "SELECT ID, menu_order, post_title FROM {$wpdb->posts} WHERE ID IN (11,12,13,14,15) ORDER BY menu_order, ID" );
printf( "%-5s %-12s %s\n", 'ID', 'menu_order', 'post_title' );
echo str_repeat( '-', 100 ) . "\n";
foreach ( $rows as $r ) {
	printf( "%-5s %-12s %s\n", $r->ID, $r->menu_order, $r->post_title );
}

echo "\n=== сверка с заданием ===\n";
$ok = true;
foreach ( $ORDER as $id => $ord ) {
	$got = (int) $wpdb->get_var( $wpdb->prepare( "SELECT menu_order FROM {$wpdb->posts} WHERE ID=%d", $id ) );
	printf( "ID=%-4s задано=%-4s в БД=%-4s %s\n", $id, $ord, $got, $got === $ord ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' );
	$ok = $ok && ( $got === $ord );
}

echo "\n=== побочных эффектов не было ===\n";
$rev_after = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='revision'" );
printf( "ревизий после записи: %d (было %d, дельта %+d — ожидается 0)\n", $rev_after, $rev_before, $rev_after - $rev_before );
foreach ( array_keys( $ORDER ) as $id ) {
	$m = $wpdb->get_var( $wpdb->prepare( "SELECT post_modified FROM {$wpdb->posts} WHERE ID=%d", $id ) );
	printf( "ID=%-4s post_modified было=%s стало=%s %s\n", $id, $mod_before[ $id ], $m, $m === $mod_before[ $id ] ? '(не тронут)' : 'ИЗМЕНИЛСЯ' );
}

echo "\n=== что отдаёт get_post() после clean_post_cache ===\n";
foreach ( array_keys( $ORDER ) as $id ) {
	printf( "ID=%-4s get_post()->menu_order = %s\n", $id, get_post( $id )->menu_order );
}

echo "\n=== фактический порядок карточек СЕЙЧАС (запрос архива не менялся: orderBy=title, order=asc) ===\n";
$q = new WP_Query( array( 'post_type' => 'services', 'posts_per_page' => 9, 'orderby' => 'title', 'order' => 'ASC', 'post_status' => 'publish' ) );
$i = 1;
foreach ( $q->posts as $p ) {
	printf( "%d) ID=%-4s menu_order=%-4s %s\n", $i++, $p->ID, $p->menu_order, $p->post_title );
}

echo "\n=== порядок, который получился бы при orderBy=menu_order ===\n";
$q2 = new WP_Query( array( 'post_type' => 'services', 'posts_per_page' => 9, 'orderby' => 'menu_order', 'order' => 'ASC', 'post_status' => 'publish' ) );
$i = 1;
foreach ( $q2->posts as $p ) {
	printf( "%d) ID=%-4s menu_order=%-4s %s\n", $i++, $p->ID, $p->menu_order, $p->post_title );
}

echo "\nВИЗУАЛЬНОГО ЭФФЕКТА НЕТ: блок core/query в templates/archive-services.html\n";
echo "по-прежнему сортирует orderBy=title. Смена orderBy — файл темы, вне этой зоны.\n";
exit( $ok ? 0 : 26 );
