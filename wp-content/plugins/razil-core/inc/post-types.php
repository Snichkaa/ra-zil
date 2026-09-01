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

	/**
	 * Отзывы.
	 *
	 * Выводятся блоком razil/reviews на главной, своей страницы нет.
	 * Страница /otzyvy/ обсуждалась, но не делается.
	 * Редактируются через админку, но не имеют публичного адреса.
	 */
	register_post_type(
		'reviews',
		array(
			'labels'             => array(
				'name'               => 'Отзывы',
				'singular_name'      => 'Отзыв',
				'add_new'            => 'Добавить отзыв',
				'add_new_item'       => 'Новый отзыв',
				'edit_item'          => 'Редактировать отзыв',
				'new_item'           => 'Новый отзыв',
				'view_item'          => 'Посмотреть отзыв',
				'search_items'       => 'Искать отзывы',
				'not_found'          => 'Отзывы не найдены',
				'menu_name'          => 'Отзывы',
			),
			'public'             => false,
			'show_ui'            => true,
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-format-quote',
			'menu_position'      => 22,
			'hierarchical'       => false,
			'has_archive'        => false,
			'publicly_queryable' => false,
			'rewrite'            => false,
			'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields', 'revisions' ),
		)
	);

	/**
	 * Сотрудники.
	 *
	 * Выводятся на странице /o-kompanii/, своей страницы нет.
	 */
	register_post_type(
		'employees',
		array(
			'labels'             => array(
				'name'               => 'Сотрудники',
				'singular_name'      => 'Сотрудник',
				'add_new'            => 'Добавить сотрудника',
				'add_new_item'       => 'Новый сотрудник',
				'edit_item'          => 'Редактировать сотрудника',
				'new_item'           => 'Новый сотрудник',
				'view_item'          => 'Посмотреть профиль',
				'search_items'       => 'Искать сотрудников',
				'not_found'          => 'Сотрудники не найдены',
				'menu_name'          => 'Сотрудники',
			),
			'public'             => false,
			'show_ui'            => true,
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-groups',
			'menu_position'      => 23,
			'hierarchical'       => false,
			'has_archive'        => false,
			'publicly_queryable' => false,
			'rewrite'            => false,
			'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields', 'revisions' ),
		)
	);

	/**
	 * Заявки с форм.
	 *
	 * Входящие обращения. Создаются программой, не человеком.
	 * Редактировать вручную нельзя — только смотреть и удалять.
	 */
	register_post_type(
		'leads',
		array(
			'labels'             => array(
				'name'               => 'Заявки',
				'singular_name'      => 'Заявка',
				'edit_item'          => 'Посмотреть заявку',
				'view_item'          => 'Посмотреть заявку',
				'search_items'       => 'Искать заявки',
				'not_found'          => 'Заявки не найдены',
				'menu_name'          => 'Заявки',
			),
			'public'             => false,
			'show_ui'            => true,
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-email',
			'menu_position'      => 24,
			'hierarchical'       => false,
			'has_archive'        => false,
			'publicly_queryable' => false,
			'rewrite'            => false,
			'supports'           => array( 'title', 'custom-fields' ),
			'capabilities'       => array(
				'create_posts'       => 'do_not_allow', // создавать нельзя через админку
				'edit_posts'         => 'manage_options',
				'edit_others_posts'  => false,
				'delete_posts'       => 'manage_options',
				'delete_others_posts' => false,
				'publish_posts'      => false,
				'read_posts'         => 'manage_options',
			),
			'map_meta_cap'       => true,
		)
	);
}
add_action( 'init', 'razil_register_post_types', 5 );
