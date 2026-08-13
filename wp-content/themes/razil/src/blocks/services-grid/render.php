<?php
$services = isset( $attributes['services'] ) ? $attributes['services'] : [];
?>
<section class="wp-block-razil-services-grid">
	<div class="rz-services-grid">
		<?php foreach ( $services as $service ) : ?>
			<div class="rz-service-card">
				<h3 class="rz-service-title"><?php echo esc_html( $service['title'] ); ?></h3>
				<p class="rz-service-desc"><?php echo esc_html( $service['description'] ); ?></p>
				<p class="rz-service-price">от <?php echo esc_html( $service['price'] ); ?> ₽</p>
			</div>
		<?php endforeach; ?>
	</div>
</section>
