<?php
$items = isset( $attributes['items'] ) ? $attributes['items'] : [];
?>
<section class="wp-block-razil-advantages">
	<div class="rz-advantages-list">
		<?php foreach ( $items as $item ) : ?>
			<div class="rz-advantage">
				<p class="rz-advantage-accent"><?php echo esc_html( $item['accent'] ); ?></p>
				<p class="rz-advantage-text"><?php echo esc_html( $item['text'] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
</section>
