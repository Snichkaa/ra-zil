<?php
/** Match download sizes to the actual layouts; prioritize only first-screen media. */
defined( 'ABSPATH' ) || exit;

function razil_responsive_block_images( string $html, array $block ): string {
    $class = $block['attrs']['className'] ?? '';
    $sizes = '';
    $lead = str_contains( $class, 'rz-svc-hero__media' );
    if ( $lead ) {
        // Service heroes stack below 1024px; the photograph then spans the row.
        $sizes = '(max-width: 1023px) calc(100vw - 32px), (max-width: 1239px) 44vw, 510px';
    } elseif ( str_contains( $class, 'rz-landscape-gallery' ) || str_contains( $class, 'rz-svc-gallery' ) ) {
        $sizes = '(max-width: 599px) calc(100vw - 40px), (max-width: 899px) calc((100vw - 56px) / 2), (max-width: 1239px) calc((100vw - 72px) / 3), 376px';
    } elseif ( 'core/image' === ( $block['blockName'] ?? '' ) && preg_match( '/rz-(first-aid__photo|service-card__photo)/', $class ) ) {
        $sizes = '(max-width: 767px) calc(100vw - 32px), (max-width: 1239px) 45vw, 400px';
    } elseif ( 'core/image' === ( $block['blockName'] ?? '' ) && preg_match( '/rz-(info-photo|contact-photo|about-fleet__photo|cafe__photo)/', $class ) ) {
        $sizes = '(max-width: 767px) calc(100vw - 40px), (max-width: 1239px) 48vw, 580px';
    }
    if ( ! $sizes || ! str_contains( $html, '<img' ) ) { return $html; }
    $tags = new WP_HTML_Tag_Processor( $html );
    while ( $tags->next_tag( 'img' ) ) {
        // The image lightbox adds hidden copies: their source is managed by core.
        if ( $tags->has_class( 'wp-lightbox-image' ) ) { continue; }
        $tags->set_attribute( 'sizes', $sizes );
        if ( $lead ) {
            $tags->set_attribute( 'loading', 'eager' );
            $tags->set_attribute( 'fetchpriority', 'high' );
        } else {
            $tags->set_attribute( 'loading', 'lazy' );
            $tags->remove_attribute( 'fetchpriority' );
        }
        $tags->set_attribute( 'decoding', 'async' );
    }
    return $tags->get_updated_html();
}
add_filter( 'render_block', 'razil_responsive_block_images', 20, 2 );
