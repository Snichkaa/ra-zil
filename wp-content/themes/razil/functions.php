<?php
/**
 * Razil Theme Functions
 *
 * Тема «Земля и Люди». Блочная тема: почти всё оформление живёт
 * в theme.json, здесь только подключение файлов и точечные правки.
 *
 * @package Razil
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Версия для сброса кеша.
 *
 * Локально (WP_DEBUG включён) берём время изменения файла, чтобы правки в CSS
 * были видны без Ctrl+F5. На продакшене — фиксированная строка.
 */
define( 'RAZIL_VERSION', '1.0.0' );

/**
 * Версия конкретного файла темы для аргумента $ver.
 *
 * @param string $relative_path Путь относительно корня темы.
 * @return string
 */
function razil_asset_version( $relative_path ) {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		$file = get_theme_file_path( $relative_path );
		if ( file_exists( $file ) ) {
			return (string) filemtime( $file );
		}
	}

	return RAZIL_VERSION;
}

/**
 * Регистрация блоков темы.
 *
 * Блоки лежат в build/blocks/<имя>/block.json. Регистрируем каталог,
 * а не файл: register_block_type() сам прочитает block.json внутри.
 */
function razil_register_blocks() {
	$dirs = glob( get_theme_file_path( 'build/blocks/*' ), GLOB_ONLYDIR );

	foreach ( (array) $dirs as $dir ) {
		if ( file_exists( $dir . '/block.json' ) ) {
			register_block_type( $dir );
		}
	}
}
add_action( 'init', 'razil_register_blocks' );

/**
 * Категория для блоков темы.
 *
 * Без неё блоки с "category": "razil" регистрируются,
 * но не отображаются в панели вставки.
 *
 * @param array $categories Категории блоков.
 * @return array
 */
function razil_block_category( $categories ) {
	array_unshift(
		$categories,
		array(
			'slug'  => 'razil',
			'title' => 'Земля и Люди',
			'icon'  => null,
		)
	);

	return $categories;
}
add_filter( 'block_categories_all', 'razil_block_category' );

/**
 * Регистрация блоков на стороне редактора.
 *
 * Блок, объявленный только в PHP, рисуется на сайте, но в панели вставки
 * не попадает: редактору нужна регистрация в JavaScript. Собираем метаданные
 * из реального реестра WordPress и отдаём их одним скриптом – так список
 * блоков не нужно дублировать внутри.
 */
function razil_block_editor_assets() {
	$payload = array();

	foreach ( WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type ) {
		if ( 0 !== strpos( $name, 'razil/' ) ) {
			continue;
		}

		$payload[] = array(
			'name'        => $name,
			'title'       => $type->title ? $type->title : $name,
			'description' => $type->description ? $type->description : '',
			'category'    => $type->category ? $type->category : 'widgets',
			'icon'        => $type->icon ? $type->icon : 'block-default',
			'attributes'  => (object) ( is_array( $type->attributes ) ? $type->attributes : array() ),
		);
	}

	if ( ! $payload ) {
		return;
	}

	wp_enqueue_script(
		'razil-blocks-editor',
		get_theme_file_uri( 'assets/js/blocks-editor.js' ),
		array(
			'wp-blocks',
			'wp-element',
			'wp-block-editor',
			'wp-components',
			'wp-server-side-render',
		),
		razil_asset_version( 'assets/js/blocks-editor.js' ),
		true
	);

	wp_add_inline_script(
		'razil-blocks-editor',
		'window.razilBlocks = ' . wp_json_encode( $payload ) . ';',
		'before'
	);
}
add_action( 'enqueue_block_editor_assets', 'razil_block_editor_assets' );

/**
 * ДЕБАГ – удали, когда блоки появят в редакторе.
 *
 * Показывает в админке, сколько блоков razil попало в реестр WordPress.
 */
function razil_debug_blocks_notice() {
	$registered = WP_Block_Type_Registry::get_instance()->get_all_registered();
	$mine       = array();

	foreach ( $registered as $name => $type ) {
		if ( 0 === strpos( $name, 'razil/' ) ) {
			$mine[] = $name;
		}
	}

	$script = file_exists( get_theme_file_path( 'assets/js/blocks-editor.js' ) )
		? 'blocks-editor.js на месте'
		: 'ФАЙЛ assets/js/blocks-editor.js НЕТ';

	printf(
		'<div class="notice notice-%1$s"><p><strong>Блоки razil: в реестре %2$d. %3$s</strong></p></div>',
		$mine ? 'success' : 'error',
		count( $mine ),
		esc_html( $script )
	);
}
add_action( 'admin_notices', 'razil_debug_blocks_notice' );

/**
 * Стили фронтенда.
 *
 * Порядок важен: tokens.css объявляет примитивы --rz-*, на которые
 * ссылается палитра в theme.json. Он должен грузиться первым.
 *
 * Шрифты здесь НЕ подключаются: @font-face генерирует WordPress
 * из блока fontFace в theme.json.
 */
function razil_enqueue_styles() {
	wp_enqueue_style(
		'razil-tokens',
		get_theme_file_uri( 'assets/css/tokens.css' ),
		array(),
		razil_asset_version( 'assets/css/tokens.css' )
	);

	wp_enqueue_style(
		'razil-main',
		get_theme_file_uri( 'assets/css/main.css' ),
		array( 'razil-tokens' ),
		razil_asset_version( 'assets/css/main.css' )
	);
}
add_action( 'wp_enqueue_scripts', 'razil_enqueue_styles' );

/**
 * Предзагрузка PT Sans 400.
 *
 * Первый экран набран им и должен появиться на медленном мобильном
 * интернете за секунду. Остальные начертания грузятся обычным порядком.
 *
 * crossorigin обязателен даже для своего домена: шрифты запрашиваются
 * в режиме CORS, без атрибута файл скачается дважды.
 *
 * @param array $resources Ресурсы для предзагрузки.
 * @return array
 */
function razil_preload_fonts( $resources ) {
	$resources[] = array(
		'href'        => get_theme_file_uri( 'assets/fonts/pt-sans-400.woff2' ),
		'as'          => 'font',
		'type'        => 'font/woff2',
		'crossorigin' => 'anonymous',
	);

	return $resources;
}
add_filter( 'wp_preload_resources', 'razil_preload_fonts' );

/**
 * Поддержка возможностей темы.
 */
function razil_setup() {
	add_theme_support( 'custom-logo' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );

	// Обрезка изображений под пропорции из макета.
	add_image_size( 'razil-product', 800, 800, true );   // товар, 1:1
	add_image_size( 'razil-branch', 1200, 800, true );   // филиал, 3:2
}
add_action( 'after_setup_theme', 'razil_setup' );

/**
 * Стили блочного редактора.
 *
 * tokens.css обязателен: без него переменные --rz-* в редакторе
 * не определены и палитра из theme.json не отрисуется.
 */
function razil_editor_styles() {
	add_theme_support( 'editor-styles' );
	add_editor_style(
		array(
			'assets/css/tokens.css',
			'assets/css/editor-styles.css',
		)
	);
}
add_action( 'after_setup_theme', 'razil_editor_styles' );

/**
 * Убираем то, что тормозит первый экран.
 *
 * Скрипт определения эмодзи — лишний запрос и лишний парсинг
 * на каждой странице. Эмодзи в проекте не используются.
 */
function razil_trim_head() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'rsd_link' );
}
add_action( 'init', 'razil_trim_head' );

/**
 * Отключаем стили ядра для блоков.
 *
 * Дизайн-система задана целиком в theme.json и main.css.
 * Дефолтные отступы и цвета ядра с ней конфликтуют.
 */
function razil_remove_core_block_styles() {
	wp_dequeue_style( 'wp-block-library-theme' );
}
add_action( 'wp_enqueue_scripts', 'razil_remove_core_block_styles', 20 );