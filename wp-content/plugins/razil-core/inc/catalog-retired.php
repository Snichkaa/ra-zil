<?php
/** Catalog declined by the customer. Keep archived data private and routes closed. */
defined( 'ABSPATH' ) || exit;
add_filter( 'register_post_type_args', static function ( $args, $type ) {
    if ( 'products' === $type ) {
        foreach ( array( 'public', 'publicly_queryable', 'show_ui', 'show_in_rest', 'query_var', 'rewrite' ) as $key ) { $args[$key] = false; }
        $args['exclude_from_search'] = true;
    }
    return $args;
}, 10, 2 );
add_filter( 'register_taxonomy_args', static function ( $args, $taxonomy ) {
    if ( 'product_category' === $taxonomy ) {
        foreach ( array( 'public', 'publicly_queryable', 'show_ui', 'show_in_rest', 'query_var', 'rewrite' ) as $key ) { $args[$key] = false; }
    }
    return $args;
}, 10, 2 );
add_action( 'template_redirect', static function () {
    $path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
    if ( is_string( $path ) && preg_match( '~^/katalog(?:/|$)~', $path ) ) {
        global $wp_query;
        $wp_query->set_404();
        status_header( 404 );
        nocache_headers();
        remove_action( 'template_redirect', 'redirect_canonical' );
        remove_action( 'template_redirect', 'wp_old_slug_redirect' );
    }
}, 0 );
