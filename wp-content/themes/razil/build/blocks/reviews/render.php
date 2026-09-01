<?php
/**
 * Отзывы: карусель или список.
 *
 * Источник данных по умолчанию — записи CPT reviews, все, по дате от новых к старым.
 * Атрибут items оставлен как ручной режим: если он заполнен, база не запрашивается.
 *
 * Режим вывода задаёт атрибут display:
 *   carousel — прокручиваемая лента, по умолчанию (главная);
 *   list     — вертикальный список с показом по частям (страница отзывов).
 * Разметка карточки одна на оба режима, поэтому правки звёзд, даты
 * и источника делаются в одном месте.
 *
 * Разметка карусели рассчитана на работу без JS: трек — обычный прокручиваемый
 * список со scroll-snap. Стрелки и счётчик отдаются скрытыми, их показывает view.js.
 *
 * @var array $attributes
 */

$rz_items = array();

// --- ручной режим
if ( ! empty( $attributes['items'] ) && is_array( $attributes['items'] ) ) {
	foreach ( $attributes['items'] as $rz_item ) {
		$rz_items[] = array(
			'text'   => isset( $rz_item['text'] ) ? (string) $rz_item['text'] : '',
			'name'   => isset( $rz_item['name'] ) ? (string) $rz_item['name'] : '',
			'date'   => isset( $rz_item['date'] ) ? (string) $rz_item['date'] : '',
			'rating' => isset( $rz_item['rating'] ) ? (int) $rz_item['rating'] : 0,
			'source' => isset( $rz_item['source'] ) ? (string) $rz_item['source'] : '',
		);
	}
} else {
	/*
	 * Ограничение выборки. Ноль означает «без ограничения»: на странице
	 * отзывов нужны все записи, на главной — только несколько свежих,
	 * иначе карусель растёт вместе с архивом.
	 */
	$rz_limit = isset( $attributes['limit'] ) ? (int) $attributes['limit'] : 0;

	// --- из базы
	$rz_query = new WP_Query(
		array(
			'post_type'              => 'reviews',
			'post_status'            => 'publish',
			'posts_per_page'         => $rz_limit > 0 ? $rz_limit : -1,
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
			// 0 означает «оценка не указана», а не «ноль звёзд»: блок звёзд тогда не выводится.
			'rating' => (int) get_post_meta( $rz_post->ID, '_razil_rating', true ),
			'source' => (string) get_post_meta( $rz_post->ID, '_razil_source', true ),
		);
	}
}

if ( empty( $rz_items ) ) {
	return '';
}

/*
 * Разметка звезды берётся из файла иконки — он единственный источник формы.
 * Читаем один раз на весь блок и подставляем класс: заливкой и толщиной
 * штриха управляет CSS, поэтому один файл обслуживает оба состояния.
 * Через <img src> нельзя — внешний SVG не наследует currentColor.
 */
$rz_star_file = get_theme_file_path( 'assets/icons/icon-zvezda.svg' );
$rz_star_svg  = is_readable( $rz_star_file ) ? trim( (string) file_get_contents( $rz_star_file ) ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

/**
 * Собирает разметку одной карточки — одну на оба режима вывода.
 *
 * Замыкание, а не именованная функция: файл подключается на каждый экземпляр
 * блока, и при двух блоках на странице объявление повторилось бы.
 */
$rz_card = static function ( array $item ) use ( $rz_star_svg ): string {
	$out = '<article class="rz-review">';

	if ( $item['rating'] >= 1 && $item['rating'] <= 5 && '' !== $rz_star_svg ) {
		$out .= '<div class="rz-review__rating">';
		$out .= '<span class="screen-reader-text">'
			. esc_html( sprintf( 'Оценка %d из 5', $item['rating'] ) )
			. '</span>';

		for ( $star = 1; $star <= 5; $star++ ) {
			$class = $star <= $item['rating']
				? 'rz-review__star rz-review__star--on'
				: 'rz-review__star';

			$out .= str_replace( '<svg ', '<svg class="' . esc_attr( $class ) . '" ', $rz_star_svg );
		}

		$out .= '</div>';
	}

	$out .= '<p class="rz-review__text">' . esc_html( $item['text'] ) . '</p>';
	$out .= '<footer class="rz-review__meta">';
	$out .= '<p class="rz-review__name">' . esc_html( $item['name'] ) . '</p>';

	if ( '' !== $item['date'] ) {
		$out .= '<p class="rz-review__date">' . esc_html( $item['date'] ) . '</p>';
	}

	if ( '' !== $item['source'] ) {
		$out .= '<p class="rz-review__source">' . esc_html( 'Источник: ' . $item['source'] ) . '</p>';
	}

	$out .= '</footer></article>';

	return $out;
};

$rz_display = isset( $attributes['display'] ) ? (string) $attributes['display'] : 'carousel';
$rz_step    = isset( $attributes['step'] ) ? (int) $attributes['step'] : 5;
$rz_step    = $rz_step > 0 ? $rz_step : 5;

/*
 * Список. Все записи попадают в разметку сразу, лишние скрывает JS: если
 * рендерить только первые пять, остальные отзывы не окажутся в HTML,
 * и поисковик их не увидит — а это главная ценность страницы.
 */
if ( 'list' === $rz_display ) {
	$rz_wrapper = get_block_wrapper_attributes( array( 'class' => 'rz-reviews-list' ) );
	?>
	<section <?php echo $rz_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?> data-rz-list data-rz-step="<?php echo esc_attr( (string) $rz_step ); ?>">

		<ul class="rz-reviews-list__items">
			<?php foreach ( $rz_items as $rz_item ) : ?>
				<li class="rz-reviews-list__item">
					<?php echo $rz_card( $rz_item ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</li>
			<?php endforeach; ?>
		</ul>

		<button type="button" class="wp-element-button rz-btn-outline__link rz-reviews-list__more" data-rz-more>
			Показать ещё
		</button>

		<p class="rz-reviews-list__counter" data-rz-list-counter aria-live="polite"></p>

	</section>
	<?php
	return;
}

$rz_wrapper = get_block_wrapper_attributes( array( 'class' => 'rz-reviews' ) );
?>
<section <?php echo $rz_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput ?> role="region" aria-label="Отзывы клиентов" data-rz-reviews>

	<div class="rz-reviews__viewport">

		<ul class="rz-reviews__track" tabindex="0" data-rz-track>
			<?php foreach ( $rz_items as $rz_item ) : ?>
				<li class="rz-reviews__slide">
					<?php echo $rz_card( $rz_item ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				</li>
			<?php endforeach; ?>
		</ul>

	</div>

	<div class="rz-reviews__nav" data-rz-nav>

		<button type="button" class="rz-reviews__arrow rz-reviews__arrow--prev" aria-label="Предыдущий отзыв" data-rz-prev>
			<span aria-hidden="true">&larr;</span>
		</button>

		<p class="rz-reviews__counter" data-rz-counter aria-live="polite"></p>

		<button type="button" class="rz-reviews__arrow rz-reviews__arrow--next" aria-label="Следующий отзыв" data-rz-next>
			<span aria-hidden="true">&rarr;</span>
		</button>

	</div>

</section>
