<?php
/**
 * Карусель отзывов.
 *
 * Источник данных по умолчанию — записи CPT reviews, все, по дате от новых к старым.
 * Атрибут items оставлен как ручной режим: если он заполнен, база не запрашивается.
 *
 * Разметка рассчитана на работу без JS: трек — обычный прокручиваемый список
 * со scroll-snap, свайп и горизонтальная прокрутка доступны сразу.
 * Стрелки и точки отдаются скрытыми, их показывает view.js.
 *
 * @var array $attributes
 */

$rz_items = array();

// --- ручной режим
if ( ! empty( $attributes['items'] ) && is_array( $attributes['items'] ) ) {
	foreach ( $attributes['items'] as $rz_item ) {
		$rz_items[] = array(
			'text' => isset( $rz_item['text'] ) ? (string) $rz_item['text'] : '',
			'name' => isset( $rz_item['name'] ) ? (string) $rz_item['name'] : '',
			'date' => isset( $rz_item['date'] ) ? (string) $rz_item['date'] : '',
		);
	}
} else {
	// --- из базы
	$rz_query = new WP_Query(
		array(
			'post_type'              => 'reviews',
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		)
	);

	foreach ( $rz_query->posts as $rz_post ) {
		$rz_stamp = get_post_timestamp( $rz_post );

		$rz_items[] = array(
			'text' => wp_strip_all_tags( $rz_post->post_content ),
			'name' => $rz_post->post_title,
			// «Май 2020» → «май 2020»: месяц с прописной выглядит как имя собственное.
			'date' => $rz_stamp ? mb_strtolower( wp_date( 'F Y', $rz_stamp ) ) : '',
		);
	}
}

if ( empty( $rz_items ) ) {
	return '';
}

$rz_wrapper = get_block_wrapper_attributes(
	array(
		'class' => 'rz-reviews',
	)
);
?>
<section <?php echo $rz_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?> role="region" aria-label="Отзывы клиентов" data-rz-reviews>

	<div class="rz-reviews__viewport">

		<button type="button" class="rz-reviews__arrow rz-reviews__arrow--prev" aria-label="Предыдущие отзывы" data-rz-prev hidden>
			<span aria-hidden="true">&larr;</span>
		</button>

		<ul class="rz-reviews__track" tabindex="0" data-rz-track>
			<?php foreach ( $rz_items as $rz_i => $rz_item ) : ?>
				<li class="rz-reviews__slide">
					<article class="rz-review">
						<p class="rz-review__text"><?php echo esc_html( $rz_item['text'] ); ?></p>
						<footer class="rz-review__meta">
							<p class="rz-review__name"><?php echo esc_html( $rz_item['name'] ); ?></p>
							<?php if ( '' !== $rz_item['date'] ) : ?>
								<p class="rz-review__date"><?php echo esc_html( $rz_item['date'] ); ?></p>
							<?php endif; ?>
						</footer>
					</article>
				</li>
			<?php endforeach; ?>
		</ul>

		<button type="button" class="rz-reviews__arrow rz-reviews__arrow--next" aria-label="Следующие отзывы" data-rz-next hidden>
			<span aria-hidden="true">&rarr;</span>
		</button>

	</div>

	<div class="rz-reviews__dots" data-rz-dots hidden></div>

</section>
