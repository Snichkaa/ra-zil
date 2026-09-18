<?php
/**
 * Этап 5, шаг 2 — одна запись wp_update_post по ID=11.
 */
require dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb;

$root = dirname( __DIR__, 2 );
$FIND = 'Специалист незамедлительно сообщит вам об этом.';
$REPL = 'Мы незамедлительно сообщим вам об этом.';

$snap     = json_decode( file_get_contents( $root . '/_backup/t1/post-11-before-povtor.json' ), true );
$prepared = file_get_contents( $root . '/_backup/t1/agent-prepared/11-povtor.txt' );

$cur = $wpdb->get_var( "SELECT post_content FROM {$wpdb->posts} WHERE ID=11" );

echo "=== ПРЕДПОЛЁТНАЯ ПРОВЕРКА ===\n";
printf( "get_option('use_balanceTags') = %s\n", var_export( get_option( 'use_balanceTags' ), true ) );
if ( '1' === (string) get_option( 'use_balanceTags' ) ) { exit( 110 ); }
printf( "текущее значение == снимок: %s\n", $cur === $snap['post_content'] ? 'да' : 'НЕТ' );
if ( $cur !== $snap['post_content'] ) { echo "СТОП: содержимое изменилось после снимка.\n"; exit( 111 ); }
printf( "substr_count(«%s») = %d\n", $FIND, substr_count( $cur, $FIND ) );
if ( 1 !== substr_count( $cur, $FIND ) ) { exit( 112 ); }
printf( "подготовленный файл == str_replace на текущем: %s\n", $prepared === str_replace( $FIND, $REPL, $cur ) ? 'да' : 'НЕТ' );
if ( $prepared !== str_replace( $FIND, $REPL, $cur ) ) { exit( 113 ); }

$rev_before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='revision' AND post_parent=11" );
$rev_ids_before = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type='revision' AND post_parent=11 ORDER BY ID" );
printf( "ревизий до записи: %d (%s)\n", $rev_before, implode( ', ', $rev_ids_before ) );

kses_remove_filters();
echo "kses_remove_filters() вызван\n";
printf( "has_filter('content_save_pre','wp_filter_post_kses') = %s\n\n", var_export( has_filter( 'content_save_pre', 'wp_filter_post_kses' ), true ) );

echo "=== ДЛИНЫ ДО ЗАПИСИ ===\n";
printf( "strlen    = %d байт\n", strlen( $cur ) );
printf( "mb_strlen = %d символов\n", mb_strlen( $cur ) );
printf( "md5       = %s\n\n", md5( $cur ) );

echo "=== ЗАПИСЬ (одна, wp_update_post) ===\n";
$ret = wp_update_post( wp_slash( array( 'ID' => 11, 'post_content' => $prepared ) ), true );
if ( is_wp_error( $ret ) ) { printf( "WP_Error: %s\n", $ret->get_error_message() ); exit( 114 ); }
printf( "wp_update_post вернул = %s\n\n", var_export( $ret, true ) );

$after = $wpdb->get_var( "SELECT post_content FROM {$wpdb->posts} WHERE ID=11" );

echo "=== СВЕРКА ===\n";
printf( "записанное совпадает с подготовленным: %s\n", $after === $prepared ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' );
if ( $after !== $prepared ) { exit( 115 ); }

echo "\n=== ДЛИНЫ ПОСЛЕ ЗАПИСИ ===\n";
printf( "strlen    = %d байт\n", strlen( $after ) );
printf( "mb_strlen = %d символов\n", mb_strlen( $after ) );
printf( "md5       = %s\n", md5( $after ) );

echo "\n=== ДЕЛЬТЫ (функция названа явно, сравнивается сама с собой) ===\n";
printf( "strlen:    было %d, стало %d, дельта %+d | дельта подстроки strlen:    %+d  %s\n",
	strlen( $cur ), strlen( $after ), strlen( $after ) - strlen( $cur ), strlen( $REPL ) - strlen( $FIND ),
	( strlen( $after ) - strlen( $cur ) ) === ( strlen( $REPL ) - strlen( $FIND ) ) ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' );
printf( "mb_strlen: было %d, стало %d, дельта %+d | дельта подстроки mb_strlen: %+d  %s\n",
	mb_strlen( $cur ), mb_strlen( $after ), mb_strlen( $after ) - mb_strlen( $cur ), mb_strlen( $REPL ) - mb_strlen( $FIND ),
	( mb_strlen( $after ) - mb_strlen( $cur ) ) === ( mb_strlen( $REPL ) - mb_strlen( $FIND ) ) ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' );

echo "\n=== ПОБОЧНЫЕ ПОЛЯ ===\n";
$r = $wpdb->get_row( "SELECT post_title, post_name, post_excerpt FROM {$wpdb->posts} WHERE ID=11", ARRAY_A );
printf( "post_name    = [%s]  %s\n", $r['post_name'], $r['post_name'] === $snap['post_name'] ? 'не изменился' : 'ИЗМЕНИЛСЯ' );
printf( "post_title   = [%s]  %s\n", $r['post_title'], $r['post_title'] === $snap['post_title'] ? 'не изменился' : 'ИЗМЕНИЛСЯ' );
printf( "post_excerpt %s\n", $r['post_excerpt'] === $snap['post_excerpt'] ? 'не тронут' : 'ИЗМЕНИЛСЯ' );

echo "\n=== РЕВИЗИИ ===\n";
$rev_ids_after = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type='revision' AND post_parent=11 ORDER BY ID" );
printf( "было %d (%s)\n", $rev_before, implode( ', ', $rev_ids_before ) );
printf( "стало %d (%s)\n", count( $rev_ids_after ), implode( ', ', $rev_ids_after ) );
$gone = array_diff( $rev_ids_before, $rev_ids_after );
$new  = array_diff( $rev_ids_after, $rev_ids_before );
printf( "вытеснено: %s | добавлено: %s\n", $gone ? implode( ', ', $gone ) : '(ничего)', $new ? implode( ', ', $new ) : '(ничего)' );

echo "\nПроверка отдельным процессом: ag-60-final.php\n";
