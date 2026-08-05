<?php
/**
 * Типы записей проекта.
 *
 * @package RazilCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Регистрация типов записей.
 */
function razil_register_post_types(): void {

	/**
	 * Услуги.
	 *
	 * Адреса: /uslugi/kremaciya/
	 * Архив:  /uslugi/
	 *
	 * Здесь достаточно штатного механизма WordPress — вложенности нет,
	 * поэтому 'rewrite' задаём обычным слагом.
	 */
	register_post_type(
		'services',
		array(
			'labels'             => array(
				'name'               => 'Услуги',
				'singular_name'      => 'Услуга',
				'add_new'            => 'Добавить услугу',
				'add_new_item'       => 'Новая услуга',
				'edit_item'          => 'Редактировать услугу',
				'new_item'           => 'Новая услуга',
				'view_item'          => 'Посмотреть услугу',
				'search_items'       => 'Искать услуги',
				'not_found'          => 'Услуги не найдены',
				'menu_name'          => 'Услуги',
			),
			'public'             => true,
			'show_ui'            => true,
			'show_in_rest'       => true, // нужно для редактора Gutenberg
			'menu_icon'          => 'dashicons-heart',
			'menu_position'      => 20,
			'hierarchical'       => false,
			'has_archive'        => 'uslugi',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions' ),
			'rewrite'            => array(
				'slug'       => 'uslugi',
				'with_front' => false, // не подмешивать общий префикс постоянных ссылок
			),
		)
	);

	/**
	 * Товары каталога.
	 *
	 * Адреса: /katalog/venki/venok-11/
	 *
	 * Внимание: 'rewrite' => false — это не ошибка.
	 * Адрес товара зависит от его раздела, а штатный механизм WordPress
	 * так не умеет. Поэтому правила и ссылки формируются вручную
	 * в файле inc/rewrite.php.
	 */
	register_post_type(
		'products',
		array(
			'labels'             => array(
				'name'               => 'Товары',
				'singular_name'      => 'Товар',
				'add_new'            => 'Добавить товар',
				'add_new_item'       => 'Новый товар',
				'edit_item'          => 'Редактировать товар',
				'new_item'           => 'Новый товар',
				'view_item'          => 'Посмотреть товар',
				'search_items'       => 'Искать товары',
				'not_found'          => 'Товары не найдены',
				'menu_name'          => 'Каталог',
			),
			'public'             => true,
			'show_ui'            => true,
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-products',
			'menu_position'      => 21,
			'hierarchical'       => false,
			'has_archive'        => false, // страницу /katalog/ сделаем обычной страницей
			'publicly_queryable' => true,
			'query_var'          => 'products',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions' ),
			'rewrite'            => false, // см. inc/rewrite.php
			'taxonomies'         => array( 'product_category' ),
		)
	);
}
add_action( 'init', 'razil_register_post_types', 5 );
