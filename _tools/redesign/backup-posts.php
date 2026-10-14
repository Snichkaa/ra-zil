<?php
/**
 * Побайтовый бэкап содержимого записей.
 *
 * Дамп mysqldump годится, чтобы вернуть таблицу целиком, но вытащить из
 * него одну запись — значит разбирать многомегабайтный INSERT. Фазы 5 и 6
 * откатывают страницы по одной, поэтому рядом с дампом кладётся простая
 * копия: один файл на запись, содержимое как есть, без экранирования.
 *
 * Запуск: php _tools/redesign/backup-posts.php [каталог]
 * По умолчанию: _backup/redesign-2026-09-19/posts/
 */

require dirname( __DIR__, 2 ) . '/wp-load.php';

$root = dirname( __DIR__, 2 );
$dir  = isset( $argv[1] ) ? $argv[1] : $root . '/_backup/redesign-2026-09-19/posts';

if ( ! is_dir( $dir ) && ! mkdir( $dir, 0777, true ) ) {
	fwrite( STDERR, "Не удалось создать каталог {$dir}\n" );
	exit( 1 );
}

$types = get_post_types( array( 'public' => true ), 'names' );
$types = array_values( array_diff( $types, array( 'attachment' ) ) );
sort( $types, SORT_STRING );

$posts = get_posts(
	array(
		'post_type'        => $types,
		'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future' ),
		'numberposts'      => -1,
		'orderby'          => 'ID',
		'order'            => 'ASC',
		'suppress_filters' => true,
	)
);

$index = array();

foreach ( $posts as $post ) {
	$file = $dir . '/' . $post->ID . '.html';

	// Двоичная запись: содержимое кладётся байт в байт, без перевода
	// концов строк. Файл потом сравнивается с прочитанным из базы,
	// и любая нормализация превратила бы сравнение в ложное расхождение.
	$fh = fopen( $file, 'wb' );
	fwrite( $fh, $post->post_content );
	fclose( $fh );

	$index[] = array(
		'id'     => $post->ID,
		'type'   => $post->post_type,
		'status' => $post->post_status,
		'slug'   => $post->post_name,
		'title'  => $post->post_title,
		'bytes'  => strlen( $post->post_content ),
		'md5'    => md5( $post->post_content ),
	);

	printf( "%-5d %-10s %-7d байт  md5=%s  %s\n", $post->ID, $post->post_type, strlen( $post->post_content ), md5( $post->post_content ), $post->post_title );
}

$json = wp_json_encode( $index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
$fh   = fopen( $dir . '/index.json', 'wb' );
fwrite( $fh, $json );
fclose( $fh );

printf( "\n--- Сохранено записей: %d в %s\n", count( $posts ), $dir );
