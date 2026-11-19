<?php
/** PII-free measurement adapters. Counters remain off until separately configured. */
defined( 'ABSPATH' ) || exit;
function razil_analytics_assets(): void {
	$service = is_singular( 'services' ) ? get_post_field( 'post_name', get_queried_object_id() ) : 'general';
	$services = array();
	foreach ( get_posts( array( 'post_type' => 'services', 'post_status' => 'publish', 'numberposts' => -1 ) ) as $post ) { $services[ $post->ID ] = $post->post_name; }
	$config = array(
		'enabled' => false,
		'metrika' => 0,
		'ga4' => '',
		'service' => $service,
		'services' => $services,
		'phones' => array( razil_org_tel(), razil_org_tel( 'phone_office' ), razil_org_tel( 'phone_legal' ) ),
		'receipt' => $GLOBALS['razil_callback_receipt'] ?? null,
	);
	$config = apply_filters( 'razil_analytics_config', $config );
	// Staging measurements never reach production counters.
	$config['enabled'] = ! empty( $config['enabled'] ) && 'ra-zil.ru' === wp_parse_url( home_url(), PHP_URL_HOST );
	wp_enqueue_script( 'razil-analytics', plugins_url( 'assets/analytics.js', RAZIL_CORE_PATH . 'razil-core.php' ), array(), '2026.11.19.1', true );
	wp_add_inline_script( 'razil-analytics', 'window.razilAnalytics=' . wp_json_encode( $config, JSON_HEX_TAG|JSON_HEX_AMP ) . ';', 'before' );
}
add_action( 'wp_enqueue_scripts', 'razil_analytics_assets' );
