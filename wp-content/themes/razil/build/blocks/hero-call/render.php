<?php
/**
 * Рендер блока hero-call
 *
 * @var array $attributes Атрибуты блока
 * @var string $content Контент блока
 * @var WP_Block $block Объект блока
 */

$label = isset( $attributes['label'] ) ? esc_html( $attributes['label'] ) : '';
$title = isset( $attributes['title'] ) ? esc_html( $attributes['title'] ) : '';
$subtitle = isset( $attributes['subtitle'] ) ? esc_html( $attributes['subtitle'] ) : '';
$phone = isset( $attributes['phone'] ) ? esc_attr( $attributes['phone'] ) : '';
$phone_label = isset( $attributes['phoneLabel'] ) ? esc_html( $attributes['phoneLabel'] ) : '';
?>

<section class="wp-block-razil-hero-call">
	<div class="rz-hero-container">
		<?php if ( ! empty( $label ) ) : ?>
			<p class="rz-hero-label"><?php echo $label; ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $title ) ) : ?>
			<h1 class="rz-hero-title"><?php echo $title; ?></h1>
		<?php endif; ?>

		<?php if ( ! empty( $subtitle ) ) : ?>
			<p class="rz-hero-subtitle"><?php echo $subtitle; ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $phone ) ) : ?>
			<div class="rz-hero-actions">
				<a href="tel:<?php echo str_replace( array( ' ', '(', ')', '-' ), '', $phone ); ?>" class="rz-hero-phone-btn">
					☎ <?php echo $phone; ?>
				</a>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $phone_label ) ) : ?>
			<p class="rz-hero-note"><?php echo $phone_label; ?></p>
		<?php endif; ?>
	</div>
</section>
