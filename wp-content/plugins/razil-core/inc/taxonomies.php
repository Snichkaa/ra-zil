<?php
/**
 * Таксономии проекта.
 *
 * @package RazilCore
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Регистрация таксономий.
 */
function razil_register_taxonomies(): void {

	/**
	 * Разделы каталога.
	 *
	 * Адреса: /katalog/venki/
	 *
	 * 'hierarchical' => true в интерфейсе означает «выбор галочками, как рубрики».
	 * А вот в 'rewrite' стоит 'hierarchical' => false — специально:
	 * так адрес раздела всегда состоит из одного сегмента, и не возникает
	 * путаницы с адресами товаров, у которых сегментов два.
	 */
	register_taxonomy(
		'product_category',
		array( 'products' ),
		array(
			'labels'            => array(
				'name'              => 'Разделы каталога',
				'singular_name'     => 'Раздел',
				'search_items'      => 'Искать разделы',
				'all_items'         => 'Все разделы',
				'parent_item'       => 'Родительский раздел',
				'parent_item_colon' => 'Родительский раздел:',
				'edit_item'         => 'Редактировать раздел',
				'update_item'       => 'Обновить раздел',
				'add_new_item'      => 'Добавить раздел',
				'new_item_name'     => 'Название нового раздела',
				'menu_name'         => 'Разделы каталога',
			),
			'public'            => true,
			'hierarchical'      => true,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array(
				'slug'         => 'katalog',
				'with_front'   => false,
				'hierarchical' => false,
			),
		)
	);
}
add_action( 'init', 'razil_register_taxonomies', 5 );
