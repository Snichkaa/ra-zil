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
	if ( ! is_page( RAZIL_NOINDEX_SLUGS ) ) {
		return $robots;
	}

	return wp_robots_no_robots( $robots );
}
add_filter( 'wp_robots', 'razil_robots_noindex' );
