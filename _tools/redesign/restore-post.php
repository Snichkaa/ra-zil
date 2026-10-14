<?php
/**
 * Откат содержимого одной записи из побайтового бэкапа.
 *
 * Пара к write-post.php: те же снятые фильтры, тот же wp_slash(), то же
 * чтение обратно с побайтовым сравнением. Откат, который сам не проверил
 * результат, ничем не лучше неудачной записи.
 *
 * Запуск: php _tools/redesign/restore-post.php <id> [каталог бэкапа]
 */

require dirname( __DIR__, 2 ) . '/wp-load.php';

$root = dirname( __DIR__, 2 );

if ( count( $argv ) < 2 ) {
	fwrite( STDERR, "Запуск: php restore-post.php <id> [каталог]\n" );
	exit( 2 );
}

$post_id = (int) $argv[1];
$dir     = isset( $argv[2] ) ? $argv[2] : $root . '/_backup/redesign-2026-09-19/posts';
$file    = $dir . '/' . $post_id . '.html';

if ( ! is_file( $file ) ) {
	fwrite( STDERR, "Бэкапа записи #{$post_id} нет: {$file}\n" );
	exit( 2 );
}

$post = get_post( $post_id );
if ( ! $post ) {
	fwrite( STDERR, "Записи #{$post_id} нет\n" );
	exit( 2 );
}

$backup = file_get_contents( $file );

printf( "=== Откат записи #%d [%s] %s\n", $post->ID, $post->post_type, $post->post_title );
printf( "    из бэкапа: %d байт, md5=%s\n", strlen( $backup ), md5( $backup ) );
printf( "    сейчас:    %d байт, md5=%s\n", strlen( $post->post_content ), md5( $post->post_content ) );

if ( $backup === $post->post_content ) {
	printf( "СОВПАЛО: запись уже в исходном состоянии, откат не нужен\n" );
	exit( 0 );
}

kses_remove_filters();
remove_all_filters( 'content_save_pre' );
remove_all_filters( 'content_filtered_save_pre' );
remove_all_filters( 'wp_insert_post_data' );

$result = wp_update_post(
	array(
		'ID'           => $post_id,
		'post_content' => wp_slash( $backup ),
	),
	true
);

if ( is_wp_error( $result ) ) {
	printf( "ОТКАТ: ОШИБКА — %s\n", $result->get_error_message() );
	exit( 1 );
}

clean_post_cache( $post_id );
wp_cache_delete( $post_id, 'posts' );

global $wpdb;
$stored = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id ) );

if ( $stored === $backup ) {
	printf( "ОТКАТ: СОВПАЛО (%d байт, md5=%s)\n", strlen( $stored ), md5( $stored ) );
	exit( 0 );
}

printf( "ОТКАТ: РАСХОЖДЕНИЕ — в базе %d байт (md5=%s), ожидалось %d (md5=%s)\n", strlen( (string) $stored ), md5( (string) $stored ), strlen( $backup ), md5( $backup ) );
printf( "Восстанавливать из общего дампа: _backup/redesign-2026-09-19/wp_posts_postmeta.sql\n" );
exit( 1 );
