<?php
/**
 * Дополнительные поля отзывов: оценка и площадка.
 *
 * Оценка нужна для вывода звёзд в блоке razil/reviews, площадка —
 * чтобы подписать, откуда отзыв перенесён. У старых записей поля пустые,
 * и блок обязан выглядеть нормально без них: оценка 0 означает
 * «не указана», а не «ноль звёзд».
 *
 * Имена ключей начинаются с подчёркивания намеренно. В supports CPT reviews
 * есть 'custom-fields', и поля без подчёркивания появились бы на экране
 * произвольных полей рядом с метабоксом ниже, то есть дважды.
 *
 * @package RazilCore
 */

// Прямой доступ к файлу запрещён.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ключ мета-поля с оценкой.
 */
const RAZIL_REVIEW_RATING_KEY = '_razil_rating';

/**
 * Ключ мета-поля с названием площадки.
 */
const RAZIL_REVIEW_SOURCE_KEY = '_razil_source';

/**
 * Максимальная длина названия площадки.
 */
const RAZIL_REVIEW_SOURCE_MAX = 32;

/**
 * Площадки для подсказок в метабоксе.
 *
 * Это именно подсказки: datalist не ограничивает ввод, площадки со временем
 * добавятся, и править код ради новой строки не придётся.
 */
function razil_review_sources(): array {
	return array( '2ГИС', 'Flamp', 'Otello', 'Booking', 'НетМонет', 'Т-Банк', 'СберЧаевые' );
}

/**
 * Приводит оценку к целому от 1 до 5.
 *
 * Всё, что вне диапазона, становится нулём — признаком «оценка не указана».
 *
 * @param mixed $value Сырое значение.
 */
function razil_sanitize_review_rating( $value ): int {
	$rating = (int) $value;

	return ( $rating >= 1 && $rating <= 5 ) ? $rating : 0;
}

/**
 * Чистит название площадки и обрезает до допустимой длины.
 *
 * @param mixed $value Сырое значение.
 */
function razil_sanitize_review_source( $value ): string {
	$source = sanitize_text_field( (string) $value );

	return mb_substr( $source, 0, RAZIL_REVIEW_SOURCE_MAX );
}

/**
 * Регистрирует мета-поля отзывов.
 */
function razil_register_review_meta(): void {
	register_post_meta(
		'reviews',
		RAZIL_REVIEW_RATING_KEY,
		array(
			'type'              => 'integer',
			'description'       => 'Оценка отзыва от 1 до 5, 0 — не указана',
			'single'            => true,
			'default'           => 0,
			'show_in_rest'      => true,
			'sanitize_callback' => 'razil_sanitize_review_rating',
			'auth_callback'     => static function (): bool {
				return current_user_can( 'edit_posts' );
			},
		)
	);

	register_post_meta(
		'reviews',
		RAZIL_REVIEW_SOURCE_KEY,
		array(
			'type'              => 'string',
			'description'       => 'Площадка, с которой перенесён отзыв',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => 'razil_sanitize_review_source',
			'auth_callback'     => static function (): bool {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'init', 'razil_register_review_meta', 10 );

/**
 * Добавляет метабокс на экран редактирования отзыва.
 */
function razil_add_review_meta_box(): void {
	add_meta_box(
		'razil-review-data',
		'Данные отзыва',
		'razil_render_review_meta_box',
		'reviews',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'razil_add_review_meta_box' );

/**
 * Выводит содержимое метабокса.
 *
 * @param WP_Post $post Текущая запись.
 */
function razil_render_review_meta_box( WP_Post $post ): void {
	wp_nonce_field( 'razil_save_review_meta', 'razil_review_nonce' );

	$rating = (int) get_post_meta( $post->ID, RAZIL_REVIEW_RATING_KEY, true );
	$source = (string) get_post_meta( $post->ID, RAZIL_REVIEW_SOURCE_KEY, true );
	?>
	<p>
		<label for="razil_rating"><strong>Оценка</strong></label><br />
		<select name="razil_rating" id="razil_rating" style="width: 100%;">
			<option value="" <?php selected( 0, $rating ); ?>>Не указана</option>
			<?php foreach ( range( 1, 5 ) as $value ) : ?>
				<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $value, $rating ); ?>>
					<?php echo esc_html( (string) $value ); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>

	<p>
		<label for="razil_source"><strong>Площадка</strong></label><br />
		<input
			type="text"
			name="razil_source"
			id="razil_source"
			list="razil-sources"
			maxlength="<?php echo esc_attr( (string) RAZIL_REVIEW_SOURCE_MAX ); ?>"
			value="<?php echo esc_attr( $source ); ?>"
			style="width: 100%;"
		/>
		<datalist id="razil-sources">
			<?php foreach ( razil_review_sources() as $preset ) : ?>
				<option value="<?php echo esc_attr( $preset ); ?>"></option>
			<?php endforeach; ?>
		</datalist>
	</p>
	<?php
}

/**
 * Сохраняет поля отзыва.
 *
 * @param int $post_id Идентификатор записи.
 */
function razil_save_review_meta( int $post_id ): void {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	$nonce = isset( $_POST['razil_review_nonce'] )
		? sanitize_text_field( wp_unslash( $_POST['razil_review_nonce'] ) )
		: '';

	if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'razil_save_review_meta' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// --- оценка
	$rating_raw = isset( $_POST['razil_rating'] )
		? sanitize_text_field( wp_unslash( $_POST['razil_rating'] ) )
		: '';
	$rating     = razil_sanitize_review_rating( $rating_raw );

	if ( 0 === $rating ) {
		delete_post_meta( $post_id, RAZIL_REVIEW_RATING_KEY );
	} else {
		update_post_meta( $post_id, RAZIL_REVIEW_RATING_KEY, $rating );
	}

	// --- площадка
	$source_raw = isset( $_POST['razil_source'] )
		? wp_unslash( $_POST['razil_source'] )
		: '';
	$source     = razil_sanitize_review_source( $source_raw );

	if ( '' === $source ) {
		delete_post_meta( $post_id, RAZIL_REVIEW_SOURCE_KEY );
	} else {
		update_post_meta( $post_id, RAZIL_REVIEW_SOURCE_KEY, $source );
	}
}
add_action( 'save_post_reviews', 'razil_save_review_meta' );

/**
 * Добавляет колонки «Оценка» и «Площадка» в список отзывов.
 *
 * Вставляем перед колонкой даты, а не в конец: при семнадцати записях
 * нужно видеть, где поля не заполнены, не открывая каждую.
 *
 * @param array $columns Колонки таблицы.
 */
function razil_review_columns( array $columns ): array {
	$result = array();

	foreach ( $columns as $key => $label ) {
		if ( 'date' === $key ) {
			$result['razil_rating'] = 'Оценка';
			$result['razil_source'] = 'Площадка';
		}

		$result[ $key ] = $label;
	}

	// Колонки даты может не быть — тогда добавляем в конец.
	if ( ! isset( $result['razil_rating'] ) ) {
		$result['razil_rating'] = 'Оценка';
		$result['razil_source'] = 'Площадка';
	}

	return $result;
}
add_filter( 'manage_reviews_posts_columns', 'razil_review_columns' );

/**
 * Выводит значения новых колонок.
 *
 * @param string $column  Ключ колонки.
 * @param int    $post_id Идентификатор записи.
 */
function razil_review_column_content( string $column, int $post_id ): void {
	if ( 'razil_rating' === $column ) {
		$rating = (int) get_post_meta( $post_id, RAZIL_REVIEW_RATING_KEY, true );
		echo esc_html( $rating > 0 ? (string) $rating : '—' );

		return;
	}

	if ( 'razil_source' === $column ) {
		$source = (string) get_post_meta( $post_id, RAZIL_REVIEW_SOURCE_KEY, true );
		echo esc_html( '' !== $source ? $source : '—' );
	}
}
add_action( 'manage_reviews_posts_custom_column', 'razil_review_column_content', 10, 2 );
