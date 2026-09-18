<?php
/**
 * Этап 3, пункт 6 — сброс кэшей + разбор, что с ревизиями.
 */
require dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb;

echo "================== ЧТО ЗА КЭШИ ВООБЩЕ ЕСТЬ ==================\n";
printf( "wp_using_ext_object_cache()          = %s\n", var_export( wp_using_ext_object_cache(), true ) );
printf( "drop-in wp-content/object-cache.php  = %s\n", file_exists( WP_CONTENT_DIR . '/object-cache.php' ) ? 'есть' : 'нет' );
printf( "drop-in wp-content/advanced-cache.php= %s\n", file_exists( WP_CONTENT_DIR . '/advanced-cache.php' ) ? 'есть' : 'нет' );
printf( "WP_CACHE                             = %s\n", defined( 'WP_CACHE' ) ? var_export( WP_CACHE, true ) : '(не определена)' );
printf( "класс кэша                           = %s\n", get_class( $GLOBALS['wp_object_cache'] ) );
printf( "расширение redis  загружено = %s\n", extension_loaded( 'redis' ) ? 'да' : 'нет' );
printf( "расширение memcached загружено = %s\n", extension_loaded( 'memcached' ) ? 'да' : 'нет' );
printf( "расширение apcu загружено = %s\n", extension_loaded( 'apcu' ) ? 'да' : 'нет' );
printf( "php_sapi_name()                      = %s\n", php_sapi_name() );
echo "активные плагины: " . implode( ', ', get_option( 'active_plugins' ) ) . "\n";
$dropins = array();
foreach ( glob( WP_CONTENT_DIR . '/*.php' ) as $f ) { $dropins[] = basename( $f ); }
echo "файлы в wp-content: " . implode( ', ', $dropins ) . "\n";

echo "\n--- FastCGI-кэш ---\n";
$nginx_conf = glob( 'C:/laragon/etc/nginx/*.conf' );
printf( "каталог конфигов nginx C:/laragon/etc/nginx: %s\n", is_dir( 'C:/laragon/etc/nginx' ) ? 'есть' : 'нет' );
printf( "каталог конфигов apache C:/laragon/etc/apache2: %s\n", is_dir( 'C:/laragon/etc/apache2' ) ? 'есть' : 'нет' );
$fcgi_hits = array();
foreach ( array( 'C:/laragon/etc/nginx', 'C:/laragon/etc/apache2' ) as $dir ) {
	if ( ! is_dir( $dir ) ) { continue; }
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		if ( ! $f->isFile() ) { continue; }
		$txt = @file_get_contents( $f->getPathname() );
		if ( false !== $txt && ( stripos( $txt, 'fastcgi_cache' ) !== false || stripos( $txt, 'proxy_cache_path' ) !== false ) ) {
			$fcgi_hits[] = str_replace( chr( 92 ), '/', $f->getPathname() );
		}
	}
}
printf( "конфигов с директивами fastcgi_cache/proxy_cache_path: %d\n", count( $fcgi_hits ) );
foreach ( $fcgi_hits as $h ) { echo "  {$h}\n"; }

echo "\n================== СБРОС ==================\n";
$r1 = wp_cache_flush();
printf( "wp_cache_flush() вернул = %s  -> сброшен объектный кэш (%s)\n", var_export( $r1, true ), get_class( $GLOBALS['wp_object_cache'] ) );

foreach ( array( 11, 12, 13, 14, 15 ) as $id ) {
	clean_post_cache( $id );
}
echo "clean_post_cache() по 11,12,13,14,15 -> сброшены записи и их мета в кэше постов\n";

if ( function_exists( 'wp_cache_flush_runtime' ) ) {
	wp_cache_flush_runtime();
	echo "wp_cache_flush_runtime() -> сброшен рантайм-кэш\n";
}

wp_cache_delete( 'alloptions', 'options' );
echo "wp_cache_delete('alloptions','options') -> сброшен кэш автозагружаемых опций\n";

// Транзиенты Yoast, связанные с выводом.
$tr = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpseo%' OR option_name LIKE '_transient_timeout_wpseo%'" );
printf( "транзиентов wpseo в wp_options: %d %s\n", count( $tr ), $tr ? '(' . implode( ', ', $tr ) . ')' : '' );

echo "\n=== чего НЕ сбрасывали и почему ===\n";
echo "FastCGI-кэш: ";
if ( $fcgi_hits ) {
	echo "конфиги с директивами найдены (см. выше) — нужен доступ к каталогу кэша, сбрасывать не стал.\n";
} else {
	echo "не настроен — директив fastcgi_cache/proxy_cache_path в конфигах нет, сбрасывать нечего.\n";
}
echo "Кэш страниц плагином: плагинов кэширования среди активных нет, drop-in advanced-cache.php отсутствует.\n";
echo "OPcache сбрасывать не требуется — правились данные в БД, не PHP-файлы.\n";

echo "\n\n================== РЕВИЗИИ: почему счётчик уменьшился ==================\n";
printf( "WP_POST_REVISIONS = %s\n", defined( 'WP_POST_REVISIONS' ) ? var_export( WP_POST_REVISIONS, true ) : '(не определена — значит true, без ограничения)' );
printf( "wp_revisions_to_keep() для ID=11: %s\n", wp_revisions_to_keep( get_post( 11 ) ) );
echo "\nревизий на запись сейчас:\n";
foreach ( array( 11, 12, 13, 14, 15 ) as $id ) {
	$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='revision' AND post_parent=%d", $id ) );
	printf( "  ID=%-3s ревизий=%d\n", $id, $n );
}
printf( "\nвсего ревизий в базе: %d\n", (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='revision'" ) );
echo "\nревизии ID=11 с датами и наличием старых фраз:\n";
$revs = $wpdb->get_results( "SELECT ID, post_date, post_content, post_excerpt FROM {$wpdb->posts} WHERE post_type='revision' AND post_parent=11 ORDER BY ID" );
foreach ( $revs as $r ) {
	printf( "  rev ID=%-5s %s  «Ритуальный агент» в content=%d, в excerpt=%d\n",
		$r->ID, $r->post_date, substr_count( $r->post_content, 'Ритуальный агент' ), substr_count( $r->post_excerpt, 'Ритуальный агент' ) );
}
