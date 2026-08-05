<?php
/**
 * Razil Theme Functions
 *
 * @package Razil
 */

// Предотвращаем прямой доступ
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Подключаем локальные шрифты через @font-face в CSS
 */
function razil_register_fonts() {
	wp_register_style(
		'razil-fonts',
		get_template_directory_uri() . '/assets/css/fonts.css',
		array(),
		'1.0.0'
	);
}
add_action( 'wp_enqueue_scripts', 'razil_register_fonts' );

/**
 * Подключаем основные стили темы
 */
function razil_enqueue_styles() {
	// Шрифты
	wp_enqueue_style( 'razil-fonts' );

	// Основные стили
	wp_enqueue_style(
		'razil-main',
		get_template_directory_uri() . '/assets/css/main.css',
		array( 'razil-fonts' ),
		'1.0.0'
	);
}
add_action( 'wp_enqueue_scripts', 'razil_enqueue_styles' );

/**
 * Убеждаемся, что jQuery не подключается на фронтенде
 */
function razil_dequeue_jquery() {
	if ( ! is_admin() ) {
		wp_dequeue_script( 'jquery' );
		wp_dequeue_script( 'jquery-core' );
	}
}
add_action( 'wp_enqueue_scripts', 'razil_dequeue_jquery', 100 );

/**
 * Добавляем поддержку фич WordPress
 */
function razil_setup() {
	// Поддержка меню
	register_nav_menus(
		array(
			'primary' => __( 'Основное меню', 'razil' ),
		)
	);

	// Поддержка логотипа
	add_theme_support( 'custom-logo' );

	// Поддержка HTML5 для элементов
	add_theme_support(
		'html5',
		array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'script',
			'style',
		)
	);
}
add_action( 'after_setup_theme', 'razil_setup' );

/**
 * Добавляем editor-styles для блочного редактора
 */
function razil_add_editor_styles() {
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/editor-styles.css' );
}
add_action( 'after_setup_theme', 'razil_add_editor_styles' );
