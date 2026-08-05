<?php
/**
 * Plugin Name:       Razil Core
 * Description:       Ядро проекта ra-zil.ru: типы записей, таксономии, структура URL.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Nika
 * Text Domain:       razil-core
 *
 * @package RazilCore
 */

// Прямой доступ к файлу запрещён.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Версия плагина. При её изменении правила URL перегенерируются автоматически.
 */
define( 'RAZIL_CORE_VERSION', '0.1.0' );

/**
 * Путь к папке плагина.
 */
define( 'RAZIL_CORE_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Подключаем модули.
 */
require_once RAZIL_CORE_PATH . 'inc/post-types.php';
require_once RAZIL_CORE_PATH . 'inc/taxonomies.php';
require_once RAZIL_CORE_PATH . 'inc/rewrite.php';

/**
 * При активации плагина пересобираем правила URL.
 *
 * Без этого адреса вида /katalog/venki/venok-11/ будут отдавать 404,
 * потому что WordPress хранит правила в базе и сам их не обновляет.
 */
function razil_core_activate(): void {
	razil_register_post_types();
	razil_register_taxonomies();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'razil_core_activate' );

/**
 * При деактивации чистим правила, чтобы не оставлять мусор.
 */
function razil_core_deactivate(): void {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'razil_core_deactivate' );

/**
 * Автоматический сброс правил при обновлении версии плагина.
 *
 * Удобно при разработке: поменяли слаг в коде — правила подхватятся сами,
 * без ручного захода в «Настройки → Постоянные ссылки».
 */
function razil_core_maybe_flush(): void {
	if ( get_option( 'razil_core_version' ) !== RAZIL_CORE_VERSION ) {
		flush_rewrite_rules();
		update_option( 'razil_core_version', RAZIL_CORE_VERSION );
	}
}
add_action( 'init', 'razil_core_maybe_flush', 99 );
