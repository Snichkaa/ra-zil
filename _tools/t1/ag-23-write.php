<?php
/**
 * 2.2, шаг 2 — запись. РОВНО ОДИН wp_update_post на объект: при
 * WP_POST_REVISIONS = 5 каждая лишняя запись вытесняла бы старую ревизию.
 * Пишется ровно та строка, что проверена скриптом ag-22-dry.
 */
require dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb;

$MAP = require __DIR__ . '/ag-22-map.php';
$dir = dirname( __DIR__, 2 ) . '/_backup/t1/agent-prepared';

$bk = array();
foreach ( json_decode( file_get_contents( dirname( __DIR__, 2 ) . '/_backup/t1/agent-pass-before.json' ), true ) as $r ) {
	$bk[ (int) $r['ID'] ] = $r;
}

echo "=== ПРЕДПОЛЁТНАЯ ПРОВЕРКА ===\n";
printf( "get_option('use_balanceTags') = %s\n", var_export( get_option( 'use_balanceTags' ), true ) );
if ( '1' === (string) get_option( 'use_balanceTags' ) ) { echo "СТОП: balanceTags включён.\n"; exit( 71 ); }
printf( "WP_POST_REVISIONS = %s\n", defined( 'WP_POST_REVISIONS' ) ? var_export( WP_POST_REVISIONS, true ) : '(не определена)' );
echo "ревизий на объект ДО записи:\n";
$rev_before = array();
foreach ( array_keys( $MAP ) as $id ) {
	$rev_before[ $id ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='revision' AND post_parent=%d", $id ) );
	printf( "  ID=%-4s ревизий=%d\n", $id, $rev_before[ $id ] );
}

kses_remove_filters();
echo "\nkses_remove_filters() вызван\n";
printf( "has_filter('content_save_pre','wp_filter_post_kses') = %s\n", var_export( has_filter( 'content_save_pre', 'wp_filter_post_kses' ), true ) );

$calls = 0;

foreach ( $MAP as $id => $pairs ) {
	echo "\n" . str_repeat( '#', 78 ) . "\n";
	printf( "# ID=%d — %d замен, ОДНА запись\n", $id, count( $pairs ) );
	echo str_repeat( '#', 78 ) . "\n";

	$cur      = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID=%d", $id ) );
	$prepared = file_get_contents( $dir . '/' . $id . '.txt' );

	/* Подготовленное должно быть ровно результатом замен на текущем значении. */
	$expect = $cur;
	foreach ( $pairs as $p ) {
		if ( 1 !== substr_count( $expect, $p[0] ) ) {
			printf( "СТОП: «%s» встречается %d раз, а не 1.\n", $p[0], substr_count( $expect, $p[0] ) );
			exit( 72 );
		}
		$expect = str_replace( $p[0], $p[1], $expect );
	}
	printf( "подготовленный файл == результат замен на текущем значении: %s\n", $prepared === $expect ? 'да' : 'НЕТ' );
	if ( $prepared !== $expect ) { echo "СТОП.\n"; exit( 73 ); }

	printf( "ДО:  %d байт (strlen) / %d символов (mb_strlen), md5=%s\n", strlen( $cur ), mb_strlen( $cur ), md5( $cur ) );

	$ret = wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $prepared ) ), true );
	$calls++;
	if ( is_wp_error( $ret ) ) {
		printf( "wp_update_post ВЕРНУЛ WP_Error: %s\n", $ret->get_error_message() );
		exit( 74 );
	}
	printf( "wp_update_post вернул = %s  (вызовов всего: %d)\n", var_export( $ret, true ), $calls );

	$after = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID=%d", $id ) );
	printf( "ПОСЛЕ: %d байт (strlen) / %d символов (mb_strlen), md5=%s\n", strlen( $after ), mb_strlen( $after ), md5( $after ) );
	printf( "записанное совпадает с подготовленным: %s\n", $after === $prepared ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' );
	if ( $after !== $prepared ) {
		printf( "  подготовлено md5=%s len=%d\n", md5( $prepared ), strlen( $prepared ) );
		printf( "  в базе       md5=%s len=%d\n", md5( $after ), strlen( $after ) );
		exit( 75 );
	}

	$d_b = 0; $d_c = 0;
	foreach ( $pairs as $p ) { $d_b += strlen( $p[1] ) - strlen( $p[0] ); $d_c += mb_strlen( $p[1] ) - mb_strlen( $p[0] ); }
	printf( "strlen:    было %d, стало %d, дельта %+d | сумма дельт замен %+d  %s\n",
		strlen( $cur ), strlen( $after ), strlen( $after ) - strlen( $cur ), $d_b,
		strlen( $after ) - strlen( $cur ) === $d_b ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' );
	printf( "mb_strlen: было %d, стало %d, дельта %+d | сумма дельт замен %+d  %s\n",
		mb_strlen( $cur ), mb_strlen( $after ), mb_strlen( $after ) - mb_strlen( $cur ), $d_c,
		mb_strlen( $after ) - mb_strlen( $cur ) === $d_c ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' );

	$r = $wpdb->get_row( $wpdb->prepare( "SELECT post_title, post_name, post_excerpt FROM {$wpdb->posts} WHERE ID=%d", $id ), ARRAY_A );
	printf( "post_name  = [%s]  %s\n", $r['post_name'], $r['post_name'] === $bk[ $id ]['post_name'] ? 'не изменился' : 'ИЗМЕНИЛСЯ' );
	printf( "post_title = [%s]  %s\n", $r['post_title'], $r['post_title'] === $bk[ $id ]['post_title'] ? 'не изменился' : 'ИЗМЕНИЛСЯ' );
	printf( "post_excerpt %s\n", $r['post_excerpt'] === $bk[ $id ]['post_excerpt'] ? 'не тронут' : 'ИЗМЕНИЛСЯ' );
}

echo "\n" . str_repeat( '=', 78 ) . "\n";
printf( "вызовов wp_update_post всего: %d (объектов: %d) — по одному на объект\n", $calls, count( $MAP ) );
echo "\nревизий на объект ПОСЛЕ записи:\n";
foreach ( array_keys( $MAP ) as $id ) {
	$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='revision' AND post_parent=%d", $id ) );
	printf( "  ID=%-4s было=%d стало=%d (лимит %s)\n", $id, $rev_before[ $id ], $n, defined( 'WP_POST_REVISIONS' ) ? WP_POST_REVISIONS : '—' );
}
echo "\nПроверка отдельным процессом: ag-24-verify.php\n";
