<?php
/**
 * Структура адресов каталога.
 *
 * Задача: получить адреса вида /katalog/venki/venok-11/
 * Для этого нужны две согласованные вещи:
 *
 *   1. Правило разбора — как WordPress понимает входящий адрес.
 *   2. Фильтр ссылок — как WordPress формирует адрес при выводе.
 *
 * Если сделать только первое, ссылки на сайте будут неправильные.
 * Если только второе — ссылки будут красивые, но отдавать 404.
 *
 * @package RazilCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 1. Правило разбора адреса товара.
 *
 * Шаблон читается так:
 *   ^katalog/   — адрес начинается с katalog
 *   ([^/]+)/    — первый сегмент: раздел (в разборе не участвует, но должен быть)
 *   ([^/]+)     — второй сегмент: слаг товара
 *   /?$         — необязательный слеш на конце
 *
 * $matches[2] — это второй сегмент, то есть слаг товара.
 *
 * Приоритет 'top' обязателен: правило должно проверяться раньше,
 * чем правило таксономии, иначе адрес товара будет считаться разделом.
 */
function razil_add_rewrite_rules(): void {
	add_rewrite_rule(
		'^katalog/([^/]+)/([^/]+)/?$',
		'index.php?products=$matches[2]',
		'top'
	);
}
add_action( 'init', 'razil_add_rewrite_rules', 10 );

/**
 * 2. Формирование ссылки на товар.
 *
 * WordPress по умолчанию не знает, в каком разделе лежит товар,
 * поэтому адрес собираем сами из слага раздела и слага товара.
 *
 * @param string  $post_link Исходная ссылка.
 * @param WP_Post $post      Объект записи.
 * @return string
 */
function razil_product_permalink( string $post_link, $post ): string {
	if ( ! $post instanceof WP_Post || 'products' !== $post->post_type ) {
		return $post_link;
	}

	// Без слага товара ссылку собрать нельзя (например, у черновика без заголовка).
	if ( empty( $post->post_name ) ) {
		return $post_link;
	}

	$section = 'bez-razdela';

	$terms = get_the_terms( $post->ID, 'product_category' );
	if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
		// Если разделов выбрано несколько, берём первый.
		$section = $terms[0]->slug;
	}

	return home_url( '/katalog/' . $section . '/' . $post->post_name . '/' );
}
add_filter( 'post_type_link', 'razil_product_permalink', 10, 2 );
