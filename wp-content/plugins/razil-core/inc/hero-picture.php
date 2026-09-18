<?php
/**
 * Арт-дирекция первого экрана.
 *
 * core/image не умеет отдавать <picture>, а wp_filter_content_tags()
 * к содержимому блочного шаблона не применяется — поэтому и srcset
 * здесь тоже собирается вручную.
 */
defined( 'ABSPATH' ) || exit;

const RAZIL_HERO_IMAGE_DESKTOP = 241;
const RAZIL_HERO_IMAGE_MOBILE  = 242;
const RAZIL_HERO_MOBILE_MEDIA  = '(max-width: 47.99rem)';

function razil_hero_picture( $block_content, $block ) {
	if ( empty( $block['blockName'] ) || 'core/image' !== $block['blockName'] ) {
		return $block_content;
	}
	$class = isset( $block['attrs']['className'] ) ? $block['attrs']['className'] : '';
	if ( false === strpos( $class, 'rz-hero__photo' ) ) {
		return $block_content;
	}
	$id = isset( $block['attrs']['id'] ) ? (int) $block['attrs']['id'] : 0;
	if ( RAZIL_HERO_IMAGE_DESKTOP !== $id ) {
		return $block_content;
	}

	$mobile_src = wp_get_attachment_image_url( RAZIL_HERO_IMAGE_MOBILE, 'full' );
	if ( ! $mobile_src ) {
		return $block_content; // мобильного кадра нет — отдаём блок как есть
	}
	$mobile_meta = wp_get_attachment_metadata( RAZIL_HERO_IMAGE_MOBILE );

	$img = wp_get_attachment_image(
		RAZIL_HERO_IMAGE_DESKTOP,
		'full',
		false,
		array(
			'class'         => 'wp-image-' . RAZIL_HERO_IMAGE_DESKTOP,
			'sizes'         => '100vw',
			'loading'       => 'eager',
			'fetchpriority' => 'high',
			'decoding'      => 'async',
		)
	);
	if ( ! $img ) {
		return $block_content;
	}

	$source = sprintf(
		'<source media="%s" srcset="%s" width="%d" height="%d" />',
		esc_attr( RAZIL_HERO_MOBILE_MEDIA ),
		esc_url( $mobile_src ),
		isset( $mobile_meta['width'] ) ? (int) $mobile_meta['width'] : 0,
		isset( $mobile_meta['height'] ) ? (int) $mobile_meta['height'] : 0
	);

	return '<figure class="wp-block-image alignfull size-full rz-hero__photo">'
		. '<picture>' . $source . $img . '</picture></figure>';
}
add_filter( 'render_block', 'razil_hero_picture', 10, 2 );
