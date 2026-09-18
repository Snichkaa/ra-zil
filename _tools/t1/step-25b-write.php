<?php
/**
 * Этап 2.5, шаг 2 — запись post_content ID=11 через wp_update_post().
 * Единица измерения: strlen (байты) и mb_strlen (символы), обе считаются
 * одной и той же функцией с обеих сторон сравнения.
 */
require dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb;

$FIND    = 'Ритуальный агент выезжает по Хабаровску круглосуточно';
$REPLACE = 'Наш специалист выезжает по Хабаровску круглосуточно';

$before = $wpdb->get_var( "SELECT post_content FROM {$wpdb->posts} WHERE ID=11" );
$prepared = file_get_contents( dirname( __DIR__, 2 ) . '/_backup/t1/step25-content-11-new.txt' );

echo "=== ПРЕДПОЛЁТНАЯ ПРОВЕРКА ===\n";
printf( "substr_count(«%s») = %d\n", $FIND, substr_count( $before, $FIND ) );
if ( 1 !== substr_count( $before, $FIND ) ) { echo "СТОП: вхождение не одно.\n"; exit( 20 ); }
printf( "подготовленный файл совпадает с str_replace на текущем значении: %s\n",
	$prepared === str_replace( $FIND, $REPLACE, $before ) ? 'да' : 'НЕТ' );
if ( $prepared !== str_replace( $FIND, $REPLACE, $before ) ) { echo "СТОП.\n"; exit( 22 ); }
printf( "get_option('use_balanceTags') = %s\n", var_export( get_option( 'use_balanceTags' ), true ) );

kses_remove_filters();
echo "kses_remove_filters() вызван\n";
printf( "has_filter('content_save_pre','wp_filter_post_kses') = %s\n\n", var_export( has_filter( 'content_save_pre', 'wp_filter_post_kses' ), true ) );

echo "=== ДЛИНЫ ДО ЗАПИСИ ===\n";
printf( "strlen    = %d байт\n", strlen( $before ) );
printf( "mb_strlen = %d символов\n", mb_strlen( $before ) );
printf( "md5       = %s\n\n", md5( $before ) );

echo "=== ЗАПИСЬ ===\n";
$ret = wp_update_post( wp_slash( array( 'ID' => 11, 'post_content' => $prepared ) ), true );
if ( is_wp_error( $ret ) ) {
	printf( "wp_update_post ВЕРНУЛ WP_Error: %s\n", $ret->get_error_message() );
	exit( 23 );
}
printf( "wp_update_post вернул = %s\n\n", var_export( $ret, true ) );

$after = $wpdb->get_var( "SELECT post_content FROM {$wpdb->posts} WHERE ID=11" );

echo "=== СВЕРКА ЗАПИСАННОГО С ПОДГОТОВЛЕННЫМ ===\n";
printf( "побайтово: %s\n", $after === $prepared ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' );
if ( $after !== $prepared ) {
	printf( "  подготовлено md5=%s len=%d\n", md5( $prepared ), strlen( $prepared ) );
	printf( "  в базе       md5=%s len=%d\n", md5( $after ), strlen( $after ) );
	exit( 24 );
}

echo "\n=== ДЛИНЫ ПОСЛЕ ЗАПИСИ ===\n";
printf( "strlen    = %d байт\n", strlen( $after ) );
printf( "mb_strlen = %d символов\n", mb_strlen( $after ) );
printf( "md5       = %s\n", md5( $after ) );

echo "\n=== ДЕЛЬТА (функция названа явно, одна и та же с обеих сторон) ===\n";
printf( "strlen:    было %d, стало %d, дельта %+d байт   | дельта подстроки strlen:    %+d\n",
	strlen( $before ), strlen( $after ), strlen( $after ) - strlen( $before ), strlen( $REPLACE ) - strlen( $FIND ) );
printf( "mb_strlen: было %d, стало %d, дельта %+d символа | дельта подстроки mb_strlen: %+d\n",
	mb_strlen( $before ), mb_strlen( $after ), mb_strlen( $after ) - mb_strlen( $before ), mb_strlen( $REPLACE ) - mb_strlen( $FIND ) );
$ok_bytes = ( strlen( $after ) - strlen( $before ) ) === ( strlen( $REPLACE ) - strlen( $FIND ) );
$ok_chars = ( mb_strlen( $after ) - mb_strlen( $before ) ) === ( mb_strlen( $REPLACE ) - mb_strlen( $FIND ) );
printf( "дельта по strlen соответствует замене:    %s\n", $ok_bytes ? 'да' : 'НЕТ' );
printf( "дельта по mb_strlen соответствует замене: %s\n", $ok_chars ? 'да' : 'НЕТ' );
printf( "ожидалось -2 символа / -4 байта: %s\n",
	( mb_strlen( $after ) - mb_strlen( $before ) === -2 && strlen( $after ) - strlen( $before ) === -4 ) ? 'да' : 'НЕТ' );

echo "\n=== СЧЁТЧИКИ ===\n";
printf( "«Ритуальный агент» в post_content = %d (ожидается 0)\n", substr_count( $after, 'Ритуальный агент' ) );
printf( "«Наш специалист» в post_content = %d (ожидается 1)\n", substr_count( $after, 'Наш специалист' ) );
printf( "«Организация похорон обычно происходит на третий день после смерти» = %d (ожидается 1)\n",
	substr_count( $after, 'Организация похорон обычно происходит на третий день после смерти' ) );

echo "\n=== побочные поля ===\n";
$r = $wpdb->get_row( "SELECT post_title, post_name, post_excerpt FROM {$wpdb->posts} WHERE ID=11", ARRAY_A );
printf( "post_name    = [%s]\n", $r['post_name'] );
printf( "post_title   = [%s]\n", $r['post_title'] );
printf( "post_excerpt содержит «Наш специалист»: %d (этап 2.4)\n", mb_substr_count( $r['post_excerpt'], 'Наш специалист' ) );

echo "\nПроверка отдельным процессом: step-25c-verify.php\n";
exit( ( $ok_bytes && $ok_chars ) ? 0 : 25 );
