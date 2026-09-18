<?php
/**
 * Этап 2.4, шаг 2 — запись post_excerpt ID=11 через wp_update_post().
 */
require dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb;

$OLD = 'Возьмём на себя документы, транспорт, кладбище и поминальный обед. Ритуальный агент приедет в любое время суток и будет рядом на всех этапах прощания.';
$NEW = 'Возьмём на себя документы, транспорт, организацию захоронения и поминальный обед. Наш специалист приедет в любое время суток и будет рядом на всех этапах прощания.';

$bk = array();
foreach ( json_decode( file_get_contents( dirname( __DIR__, 2 ) . '/_backup/t1/posts-11-15-before.json' ), true ) as $r ) {
	$bk[ (int) $r['ID'] ] = $r;
}

$cur = $wpdb->get_var( "SELECT post_excerpt FROM {$wpdb->posts} WHERE ID=11" );
if ( $cur !== $OLD ) {
	echo "СТОП: текущее значение post_excerpt не равно эталону «было».\n";
	exit( 10 );
}

kses_remove_filters();
echo "kses_remove_filters() вызван\n";
printf( "has_filter('excerpt_save_pre','wp_filter_post_kses') = %s\n\n", var_export( has_filter( 'excerpt_save_pre', 'wp_filter_post_kses' ), true ) );

echo "=== ЗАПИСЬ post_excerpt ID=11 ===\n";
printf( "подготовлено (%d байт / %d симв.):\n[%s]\n\n", strlen( $NEW ), mb_strlen( $NEW ), $NEW );

$ret = wp_update_post( wp_slash( array( 'ID' => 11, 'post_excerpt' => $NEW ) ), true );
if ( is_wp_error( $ret ) ) {
	printf( "wp_update_post ВЕРНУЛ WP_Error: %s\n", $ret->get_error_message() );
	exit( 11 );
}
printf( "wp_update_post вернул = %s\n\n", var_export( $ret, true ) );

$now = $wpdb->get_row( "SELECT post_title, post_name, post_excerpt, post_content FROM {$wpdb->posts} WHERE ID=11", ARRAY_A );
echo "прочитано из БД:\n[" . $now['post_excerpt'] . "]\n";
printf( "  %d байт / %d симв., md5=%s\n", strlen( $now['post_excerpt'] ), mb_strlen( $now['post_excerpt'] ), md5( $now['post_excerpt'] ) );
printf( "сверка записанного с подготовленным: %s\n", $now['post_excerpt'] === $NEW ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' );
if ( $now['post_excerpt'] !== $NEW ) {
	printf( "  подготовлено, hex = %s\n", bin2hex( $NEW ) );
	printf( "  в базе,       hex = %s\n", bin2hex( $now['post_excerpt'] ) );
	exit( 12 );
}

echo "\n=== побочные поля не тронуты ===\n";
printf( "post_content md5: бэкап=%s  сейчас=%s  %s\n",
	md5( $bk[11]['post_content'] ), md5( $now['post_content'] ),
	$now['post_content'] === $bk[11]['post_content'] ? 'НЕ ТРОНУТ' : 'ИЗМЕНИЛСЯ' );
if ( $now['post_content'] !== $bk[11]['post_content'] ) { exit( 13 ); }
printf( "post_name = [%s]  %s\n", $now['post_name'], $now['post_name'] === $bk[11]['post_name'] ? 'не изменился' : 'ИЗМЕНИЛСЯ' );
printf( "post_title = [%s]  (переименован на этапе 2.3, ожидаемо)\n", $now['post_title'] );

echo "\nПроверка отдельным процессом: step-24c-verify.php\n";
