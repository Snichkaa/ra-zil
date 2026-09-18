<?php
/**
 * Этап 2.3, шаг 2 — переименование post_title через wp_update_post().
 * post_name в массив не передаётся. Ревизии допустимы.
 */
require dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb;

$bk_path = dirname( __DIR__, 2 ) . '/_backup/t1/posts-11-15-before.json';
$bk      = array();
foreach ( json_decode( file_get_contents( $bk_path ), true ) as $row ) {
	$bk[ (int) $row['ID'] ] = $row;
}

echo "=== ПРЕДПОЛЁТНАЯ ПРОВЕРКА ===\n";
$balance = get_option( 'use_balanceTags' );
printf( "get_option('use_balanceTags') = %s\n", var_export( $balance, true ) );
if ( '1' === (string) $balance ) {
	echo "СТОП: balanceTags включён, он перепишет блочную разметку post_content при wp_update_post.\n";
	exit( 5 );
}
echo "balanceTags выключен — post_content при пересохранении не перебалансируется.\n";

kses_remove_filters();
echo "kses_remove_filters() вызван\n";
printf( "has_filter('content_save_pre','wp_filter_post_kses') = %s\n", var_export( has_filter( 'content_save_pre', 'wp_filter_post_kses' ), true ) );
printf( "has_filter('title_save_pre','wp_filter_kses')        = %s\n\n", var_export( has_filter( 'title_save_pre', 'wp_filter_kses' ), true ) );

$targets = array(
	11 => 'Организация достойной церемонии прощания и погребения',
	13 => 'Перевозка и транспортировка усопших',
	15 => 'Помощь с документами и формальностями в первые дни',
);

echo "=== ЗАПИСЬ через wp_update_post() ===\n";
foreach ( $targets as $id => $title ) {
	echo str_repeat( '-', 74 ) . "\n";
	$was = $wpdb->get_row( $wpdb->prepare( "SELECT post_title, post_name, post_content FROM {$wpdb->posts} WHERE ID=%d", $id ), ARRAY_A );
	printf( "ID=%d\n", $id );
	printf( "  было   = [%s]\n", $was['post_title'] );
	printf( "  станет = [%s]\n", $title );

	$arr = array(
		'ID'         => $id,
		'post_title' => $title,
	);
	echo '  массив в wp_update_post: ' . json_encode( array_keys( $arr ) ) . " (post_name отсутствует — верно)\n";

	$ret = wp_update_post( wp_slash( $arr ), true );
	if ( is_wp_error( $ret ) ) {
		printf( "  wp_update_post ВЕРНУЛ WP_Error: %s\n", $ret->get_error_message() );
		exit( 6 );
	}
	printf( "  wp_update_post вернул = %s\n", var_export( $ret, true ) );

	$now = $wpdb->get_row( $wpdb->prepare( "SELECT post_title, post_name, post_content, post_modified FROM {$wpdb->posts} WHERE ID=%d", $id ), ARRAY_A );
	printf( "  в БД post_title = [%s]\n", $now['post_title'] );
	printf( "  сверка записанного с подготовленным: %s\n", $now['post_title'] === $title ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' );
	if ( $now['post_title'] !== $title ) {
		printf( "    подготовлено, hex = %s\n", bin2hex( $title ) );
		printf( "    в базе,       hex = %s\n", bin2hex( $now['post_title'] ) );
		exit( 7 );
	}
	printf( "  post_name = [%s]  %s\n", $now['post_name'], $now['post_name'] === $bk[ $id ]['post_name'] ? 'не изменился' : 'ИЗМЕНИЛСЯ' );
	printf( "  post_modified = %s\n", $now['post_modified'] );
	printf( "  post_content md5: бэкап=%s  сейчас=%s  %s\n",
		md5( $bk[ $id ]['post_content'] ),
		md5( $now['post_content'] ),
		$now['post_content'] === $bk[ $id ]['post_content'] ? 'НЕ ТРОНУТ' : 'ИЗМЕНИЛСЯ ПРИ ПЕРЕСОХРАНЕНИИ' );
	if ( $now['post_content'] !== $bk[ $id ]['post_content'] ) {
		echo "  СТОП: wp_update_post переписал post_content.\n";
		exit( 8 );
	}
	printf( "  post_excerpt: %s\n", $wpdb->get_var( $wpdb->prepare( "SELECT post_excerpt FROM {$wpdb->posts} WHERE ID=%d", $id ) ) === $bk[ $id ]['post_excerpt'] ? 'не тронут' : 'ИЗМЕНИЛСЯ' );
}

echo "\n=== ID 12 и 14 — не трогали ===\n";
foreach ( array( 12, 14 ) as $id ) {
	$r = $wpdb->get_row( $wpdb->prepare( "SELECT post_title, post_name FROM {$wpdb->posts} WHERE ID=%d", $id ), ARRAY_A );
	printf( "ID=%-3s post_title=[%s]  %s\n", $id, $r['post_title'], $r['post_title'] === $bk[ $id ]['post_title'] ? 'не изменился' : 'ИЗМЕНИЛСЯ' );
}

echo "\n=== ревизии, созданные этой записью ===\n";
foreach ( array( 11, 13, 15 ) as $id ) {
	$revs = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_date, post_title FROM {$wpdb->posts} WHERE post_type='revision' AND post_parent=%d ORDER BY ID DESC LIMIT 3", $id ) );
	printf( "ID=%-3s последние ревизии: ", $id );
	echo implode( ', ', array_map( function ( $r ) { return $r->ID . ' (' . $r->post_date . ')'; }, $revs ) ) . "\n";
}

echo "\nИндексаблы пересоберутся в shutdown этого процесса. Проверка — step-23c-verify.php\n";
