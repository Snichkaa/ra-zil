<?php
/**
 * Бэкап записей 11, 12, 18, 19, 25 до прохода по терминологии.
 */
require dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb;

$ids  = array( 11, 12, 18, 19, 25 );
$rows = array();

foreach ( $ids as $id ) {
	$r = $wpdb->get_row( $wpdb->prepare(
		"SELECT ID, post_title, post_name, post_content, post_excerpt FROM {$wpdb->posts} WHERE ID = %d",
		$id
	), ARRAY_A );
	if ( ! $r ) { echo "ОШИБКА: записи {$id} нет\n"; exit( 1 ); }
	$r['ID'] = (int) $r['ID'];
	$rows[]  = $r;
}

$path = dirname( __DIR__, 2 ) . '/_backup/t1/agent-pass-before.json';
$json = json_encode( $rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
file_put_contents( $path, $json );
clearstatcache( true, $path );

echo "=== БЭКАП ===\n";
echo 'файл    = ' . str_replace( chr( 92 ), '/', $path ) . "\n";
echo 'размер  = ' . filesize( $path ) . " байт\n";
echo 'MD5     = ' . md5_file( $path ) . "\n";
echo 'SHA-256 = ' . hash_file( 'sha256', $path ) . "\n\n";

$back = json_decode( file_get_contents( $path ), true );
echo "round-trip сверка с БД:\n";
$ok = true;
foreach ( $back as $i => $b ) {
	$same = ( $b === $rows[ $i ] );
	$ok   = $ok && $same;
	printf( "ID=%-4s title_len=%-4s name_len=%-3s content_len=%-6s excerpt_len=%-4s %s\n",
		$b['ID'], strlen( $b['post_title'] ), strlen( $b['post_name'] ),
		strlen( $b['post_content'] ), strlen( $b['post_excerpt'] ),
		$same ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' );
}
echo 'ИТОГО: ' . ( $ok ? 'СОВПАЛО по всем пяти' : 'РАСХОЖДЕНИЕ' ) . "\n";
exit( $ok ? 0 : 1 );
