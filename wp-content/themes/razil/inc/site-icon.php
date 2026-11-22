<?php
/**
 * Use the agency emblem when a site icon has not been chosen in WordPress.
 * This also covers WordPress's /favicon.ico redirect and the login screen.
 *
 * @package Razil
 */

defined( 'ABSPATH' ) || exit;

function razil_default_site_icon_url( $url, $size, $blog_id ) {
	if ( $blog_id && (int) $blog_id !== get_current_blog_id() ) {
		return $url;
	}

	// Keep the administrator's choice if its attachment still exists.
	$icon_id = (int) get_option( 'site_icon' );
	if ( $icon_id && wp_get_attachment_image_url( $icon_id, array( $size, $size ) ) ) {
		return $url;
	}

	$asset_size = 512;
	foreach ( array( 32, 180, 192, 270, 512 ) as $candidate ) {
		if ( $size <= $candidate ) {
			$asset_size = $candidate;
			break;
		}
	}

	$path = 'assets/icons/site-icon-' . $asset_size . '.png';
	return add_query_arg( 'v', razil_asset_version( $path ), get_theme_file_uri( $path ) );
}
add_filter( 'get_site_icon_url', 'razil_default_site_icon_url', 10, 3 );
