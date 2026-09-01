<?php
/**
 * Рендер блока steps
 */

$steps = isset( $attributes['steps'] ) ? $attributes['steps'] : [];
?>

<section class="wp-block-razil-steps">
	<div class="rz-steps-container">
		<?php foreach ( $steps as $index => $step ) : ?>
			<details class="rz-step" <?php echo 0 === $index ? 'open' : ''; ?>>
				<summary class="rz-step-summary">
					<span class="rz-step-number"><?php echo intval( $step['number'] ); ?></span>
					<span class="rz-step-title"><?php echo esc_html( $step['title'] ); ?></span>
				</summary>
				<div class="rz-step-content">
					<p><?php echo esc_html( $step['description'] ); ?></p>
				</div>
			</details>
		<?php endforeach; ?>
	</div>
</section>
