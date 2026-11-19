<?php
/**
 * Запрет индексации отдельных страниц.
 *
 * Раньше эту роль играл инлайновый <script> в templates/page-spasibo.html,
 * который дописывал meta robots после загрузки страницы. Так это не работает:
 * к моменту, когда скрипт отрабатывает, робот уже прочитал разметку, а часть
 * роботов JavaScript не исполняет вовсе. Правильное место — фильтр wp_robots,
 * он формирует тот самый тег на сервере.
 *
 * @package RazilCore
 */

// Прямой доступ к файлу запрещён.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Слаги страниц, закрытых от индексации.
 *
 * По слагам, а не по идентификаторам: идентификаторы у страниц на разных
 * копиях сайта разные, а слаг переживает и перенос, и пересоздание страницы.
 */
const RAZIL_NOINDEX_SLUGS = array( 'spasibo' );

function razil_is_production(): bool {
	$configured = 'ra-zil.ru' === wp_parse_url( home_url(), PHP_URL_HOST );
	$requested = strtolower( preg_replace( '/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '' ) );
	return $configured && ( 'cli' === PHP_SAPI || 'ra-zil.ru' === $requested );
}

/** Host guard prevents a copied production database from indexing staging. */
add_action( 'send_headers', static function () {
	if ( ! razil_is_production() ) { header( 'X-Robots-Tag: noindex, nofollow', true ); }
} );
add_filter( 'wpseo_robots_array', static function ( $robots ) {
	if ( ! razil_is_production() || is_page( RAZIL_NOINDEX_SLUGS ) ) {
		$robots['index'] = 'noindex';
		$robots['follow'] = razil_is_production() ? 'follow' : 'nofollow';
	}
	return $robots;
} );
add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', static function ( $ids ) {
	foreach ( RAZIL_NOINDEX_SLUGS as $slug ) { $post = get_page_by_path( $slug ); if ( $post ) { $ids[] = $post->ID; } }
	return array_unique( $ids );
} );
add_filter( 'wp_sitemaps_posts_query_args', static function ( $args ) {
	$args['post__not_in'] = array_merge( $args['post__not_in'] ?? array(), apply_filters( 'wpseo_exclude_from_sitemap_by_post_ids', array() ) );
	return $args;
} );
add_filter( 'wpseo_sitemap_exclude_post_type', static function ( $excluded, $type ) {
	if ( in_array( $type, array( 'callback','products' ), true ) ) { return true; }
	return $excluded;
}, 10, 2 );
add_filter( 'robots_txt', static function ( $text ) {
	// Do not disallow the tour HTML: crawlers must read its noindex.
	if ( razil_is_production() && ! str_contains( $text, 'Sitemap:' ) ) { $text .= "\nSitemap: " . home_url( '/sitemap_index.xml' ) . "\n"; }
	return $text;
}, 20 );

/**
 * Закрывает от индексации страницы из списка.
 *
 * Через wp_robots_no_robots, а не ручной установкой ключей: этот хелпер
 * ядра ставит noindex и заодно решает, писать follow или nofollow, глядя
 * на настройку видимости сайта. Свой набор ключей рано или поздно
 * разошёлся бы с тем, что ядро считает правильным.
 *
 * @param array<string, bool|string> $robots Директивы для meta robots.
 *
 * @return array<string, bool|string>
 */
function razil_robots_noindex( array $robots ): array {
	// is_page принимает массив и сверяет по идентификатору, заголовку и слагу.
	if ( razil_is_production() && ! is_page( RAZIL_NOINDEX_SLUGS ) ) {
		return $robots;
	}

	return wp_robots_no_robots( $robots );
}
add_filter( 'wp_robots', 'razil_robots_noindex' );
