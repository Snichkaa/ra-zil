<?php
/**
 * Этап 2.2, шаг 2 — запись меты _yoast_wpseo_title для ID 11, 13, 15.
 * update_post_meta(), чтобы отработал Indexable_Post_Meta_Watcher.
 * Индексабл пересобирается этим watcher'ом в register_shutdown_function,
 * поэтому состояние «после» читает отдельный процесс (step-22d-verify.php).
 */
require dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb;

kses_remove_filters();
echo "kses_remove_filters() вызван\n";
echo "has_filter('content_save_pre','wp_filter_post_kses') = " . var_export( has_filter( 'content_save_pre', 'wp_filter_post_kses' ), true ) . "\n\n";

$targets = array(
	11 => 'Организация похорон в Хабаровске %%sep%% %%sitename%%',
	13 => 'Транспортировка умерших в Хабаровске %%sep%% %%sitename%%',
	15 => 'Юридическая помощь при оформлении документов после смерти %%sep%% %%sitename%%',
);

echo "=== ЗАПИСЬ ===\n";
foreach ( $targets as $id => $value ) {
	echo str_repeat( '-', 74 ) . "\n";
	printf( "ID=%d\n", $id );
	printf( "  подготовлено   = [%s]\n", $value );
	printf( "  после wp_slash = [%s]\n", wp_slash( $value ) );

	$ret = update_post_meta( $id, '_yoast_wpseo_title', wp_slash( $value ) );
	printf( "  update_post_meta вернул = %s\n", var_export( $ret, true ) );

	$stored = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='_yoast_wpseo_title'", $id ) );
	$rows   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='_yoast_wpseo_title'", $id ) );
	printf( "  строк в postmeta = %d\n", $rows );
	printf( "  прочитано из БД  = [%s]\n", null === $stored ? '(NULL)' : $stored );
	printf( "  сверка записанного с подготовленным: %s\n", $stored === $value ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' );
	if ( $stored !== $value ) {
		printf( "    подготовлено, hex = %s\n", bin2hex( $value ) );
		printf( "    в базе,       hex = %s\n", bin2hex( (string) $stored ) );
	}
	printf( "  длина в БД: %d байт / %d символов\n", strlen( (string) $stored ), mb_strlen( (string) $stored ) );
}

echo "\n=== ID 12 и 14 — контроль неприкосновенности ===\n";
foreach ( array( 12, 14 ) as $id ) {
	$rows = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='_yoast_wpseo_title'", $id ) );
	printf( "ID=%-3s строк _yoast_wpseo_title = %d (ожидается 0)\n", $id, $rows );
}

echo "\n=== _yoast_wpseo_metadesc — не трогали ===\n";
$md = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_yoast_wpseo_metadesc'" );
printf( "строк _yoast_wpseo_metadesc во всей postmeta = %d (ожидается 0)\n", $md );

echo "\n=== все строки _yoast_wpseo_title в базе после записи ===\n";
$all = $wpdb->get_results( "SELECT meta_id, post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key='_yoast_wpseo_title' ORDER BY post_id" );
foreach ( $all as $r ) {
	printf( "meta_id=%-6s post_id=%-4s value=[%s]\n", $r->meta_id, $r->post_id, $r->meta_value );
}
echo 'всего строк: ' . count( $all ) . "\n";

echo "\nПересборка индексаблов произойдёт в register_shutdown_function этого процесса.\n";
echo "Состояние «после» читается отдельным процессом: step-22d-verify.php\n";
