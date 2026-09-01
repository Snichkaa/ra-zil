<?php
/**
 * Иконки услуг в карточках каталога.
 *
 * Карточки печатает wp:post-template одним шаблоном на все записи,
 * поэтому инлайновый SVG в разметку каждой карточки не вставить —
 * иконка добавляется фильтром render_block на блоке заголовка.
 *
 * @package RazilCore
 */

// Прямой доступ к файлу запрещён.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Карта «слаг услуги — файл иконки».
 *
 * Связь «услуга — иконка» выводится из post_name, мета-поля нет
 * намеренно: набор услуг фиксирован по ТЗ, слаги стабильны, а пустое
 * поле давало бы тихий отказ — забытая запись осталась бы без иконки,
 * и это заметили бы не сразу.
 *
 * При переименовании слага или добавлении услуги правка вносится ЗДЕСЬ.
 * Слаг не найден — карточка выводится без иконки, без ошибки.
 */
function razil_service_icon_map(): array {
	return array(
		'organizatsiya-pohoron'   => 'svc-arka.svg',
		'krematsiya'              => 'svc-urna.svg',
		'transportirovka'         => 'svc-mikroavtobus.svg',
		'blagoustroystvo'         => 'svc-ograda.svg',
		'yuridicheskaya-pomoshch' => 'svc-dokument.svg',
	);
}

/**
 * Возвращает разметку иконки для слага услуги.
 *
 * Файлы свои, из темы, поэтому содержимое отдаётся как есть.
 * Через <img src> вставлять нельзя: внешний SVG не наследует
 * currentColor и не переключится в тёмной теме.
 *
 * Результат кэшируется в пределах запроса: на архиве пять карточек,
 * и без кэша каждый файл читался бы столько раз, сколько раз встретился.
 *
 * @param string $slug Значение post_name записи услуги.
 */
function razil_service_icon_svg( string $slug ): string {
	static $cache = array();

	if ( isset( $cache[ $slug ] ) ) {
		return $cache[ $slug ];
	}

	$map = razil_service_icon_map();

	if ( ! isset( $map[ $slug ] ) ) {
		$cache[ $slug ] = '';

		return '';
	}

	$path = get_theme_file_path( 'assets/icons/' . $map[ $slug ] );

	if ( ! is_readable( $path ) ) {
		$cache[ $slug ] = '';

		return '';
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$cache[ $slug ] = trim( (string) file_get_contents( $path ) );

	return $cache[ $slug ];
}

/**
 * Добавляет иконку слева от заголовка карточки услуги.
 *
 * Срабатывает только на архиве услуг: на самой странице услуги
 * заголовок печатает тот же блок core/post-title, и без проверки
 * иконка появилась бы и там.
 *
 * @param string $block_content Готовая разметка блока.
 * @param array  $block         Описание блока.
 */
function razil_service_card_icon( string $block_content, array $block ): string {
	if ( ! isset( $block['blockName'] ) || 'core/post-title' !== $block['blockName'] ) {
		return $block_content;
	}

	if ( ! is_post_type_archive( 'services' ) ) {
		return $block_content;
	}

	if ( 'services' !== get_post_type() ) {
		return $block_content;
	}

	$svg = razil_service_icon_svg( (string) get_post_field( 'post_name' ) );

	if ( '' === $svg ) {
		return $block_content;
	}

	return '<div class="rz-service-card__head">'
		. '<span class="rz-icon">' . $svg . '</span>'
		. $block_content
		. '</div>';
}
add_filter( 'render_block', 'razil_service_card_icon', 10, 2 );
