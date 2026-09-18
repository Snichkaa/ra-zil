<?php
/**
 * Этап 2.1 — бэкап записей 11-15 до правок.
 * Читает сырые значения напрямую из wp_posts, мимо фильтров вывода.
 */
require dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb;

$ids  = array( 11, 12, 13, 14, 15 );
$rows = array();

foreach ( $ids as $id ) {
	$r = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT ID, post_title, post_name, post_excerpt, post_content, menu_order
			   FROM {$wpdb->posts} WHERE ID = %d",
			$id
		),
		ARRAY_A
	);
	if ( ! $r ) {
		echo "ОШИБКА: записи {$id} нет в wp_posts\n";
		exit( 1 );
	}
	$r['ID']         = (int) $r['ID'];
	$r['menu_order'] = (int) $r['menu_order'];
	$rows[]          = $r;
}

$path = dirname( __DIR__, 2 ) . '/_backup/t1/posts-11-15-before.json';
$json = json_encode( $rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
if ( false === $json ) {
	echo 'ОШИБКА json_encode: ' . json_last_error_msg() . "\n";
	exit( 1 );
}
$written = file_put_contents( $path, $json );

echo "=== ЭТАП 2.1. БЭКАП ===\n";
echo 'файл            = ' . str_replace( chr( 92 ), '/', $path ) . "\n";
echo "file_put_contents вернул = {$written}\n";
clearstatcache( true, $path );
echo 'размер, байт    = ' . filesize( $path ) . "\n";
echo 'MD5             = ' . md5_file( $path ) . "\n";
echo 'SHA-256         = ' . hash_file( 'sha256', $path ) . "\n";
echo "\n=== сверка: файл читается обратно и совпадает с БД ===\n";
$back = json_decode( file_get_contents( $path ), true );
echo 'json_decode ok  = ' . ( is_array( $back ) ? 'да' : 'НЕТ: ' . json_last_error_msg() ) . "\n";
echo 'записей в файле = ' . count( $back ) . "\n";
$all_ok = true;
foreach ( $back as $i => $b ) {
	$same = ( $b === $rows[ $i ] );
	$all_ok = $all_ok && $same;
	printf(
		"ID=%-3s title_len=%-4s name_len=%-3s excerpt_len=%-4s content_len=%-6s menu_order=%-3s round-trip=%s\n",
		$b['ID'],
		strlen( $b['post_title'] ),
		strlen( $b['post_name'] ),
		strlen( $b['post_excerpt'] ),
		strlen( $b['post_content'] ),
		$b['menu_order'],
		$same ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ'
	);
}
echo 'ИТОГО round-trip: ' . ( $all_ok ? 'СОВПАЛО по всем пяти' : 'РАСХОЖДЕНИЕ' ) . "\n";
