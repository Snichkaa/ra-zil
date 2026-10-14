<?php
/**
 * Арт-дирекция первого экрана.
 *
 * core/image не умеет отдавать <picture>, а wp_filter_content_tags()
 * к содержимому блочного шаблона не применяется — поэтому и srcset
 * здесь тоже собирается вручную.
 *
 * Кадры ищутся по пути файла в медиатеке, а не по номеру вложения.
 * Номер выдаётся при загрузке и на другом сайте будет другим: те же
 * два файла, залитые заново, получат новые ID, и привязка к номеру
 * выключила бы арт-дирекцию молча. Путь файла переживает перенос.
 */
defined( 'ABSPATH' ) || exit;

const RAZIL_HERO_FILE_DESKTOP = '2026/10/kazarmenny-glavnyy-ekran.webp';
// The new full-resolution photograph supports both layouts through CSS cropping.
// Leave empty to use its responsive srcset instead of the previous mobile photo.
const RAZIL_HERO_FILE_MOBILE  = '';
const RAZIL_HERO_MOBILE_MEDIA = '(max-width: 47.99rem)';

if ( ! function_exists( 'razil_hero_attachment_id' ) ) {
	/**
	 * Номер вложения по пути файла в каталоге загрузок.
	 *
	 * Путь задаётся ровно так, как его хранит ядро в _wp_attached_file:
	 * относительно uploads, без ведущего слеша — '2026/09/имя.webp'.
	 *
	 * Ответ кэшируется на время запроса, и отрицательный тоже: фильтр
	 * render_block вызывается на каждый блок страницы, и промах без
	 * кэша означал бы запрос к базе на каждом из них.
	 *
	 * @param string $file Путь файла относительно каталога загрузок.
	 * @return int Номер вложения или 0, если такого файла в медиатеке нет.
	 */
	function razil_hero_attachment_id( $file ) {
		static $cache = array();

		$file = (string) $file;
		if ( '' === $file ) {
			return 0;
		}
		if ( isset( $cache[ $file ] ) ) {
			return $cache[ $file ];
		}

		global $wpdb;

		// LIMIT 1 с сортировкой по номеру: если один и тот же путь окажется
		// у двух вложений, выбор не должен зависеть от порядка строк в таблице.
		$cache[ $file ] = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				  WHERE meta_key = '_wp_attached_file' AND meta_value = %s
				  ORDER BY post_id ASC
				  LIMIT 1",
				$file
			)
		);

		return $cache[ $file ];
	}
}

function razil_hero_picture( $block_content, $block ) {
	if ( empty( $block['blockName'] ) || 'core/image' !== $block['blockName'] ) {
		return $block_content;
	}
	$class = isset( $block['attrs']['className'] ) ? $block['attrs']['className'] : '';
	if ( false === strpos( $class, 'rz-hero__photo' ) ) {
		return $block_content;
	}

	// Дальше любой отказ возвращает $block_content — фотографию из разметки
	// шаблона. Своей разметкой блок заменяется только когда собран целиком.
	$desktop_id = razil_hero_attachment_id( RAZIL_HERO_FILE_DESKTOP );
	if ( ! $desktop_id ) {
		return $block_content; // десктопного кадра в медиатеке нет
	}

	$img = wp_get_attachment_image(
		$desktop_id,
		'full',
		false,
		array(
			'class'         => 'wp-image-' . $desktop_id,
			'sizes'         => '100vw',
			'loading'       => 'eager',
			'fetchpriority' => 'high',
			'decoding'      => 'async',
		)
	);
	if ( ! $img ) {
		return $block_content;
	}

	$open  = '<figure class="wp-block-image alignfull size-full rz-hero__photo">';
	$close = '</figure>';

	$mobile_id  = razil_hero_attachment_id( RAZIL_HERO_FILE_MOBILE );
	$mobile_src = $mobile_id ? wp_get_attachment_image_url( $mobile_id, 'full' ) : '';
	if ( ! $mobile_src ) {
		// Мобильного кадра нет — отдаём десктопный без <picture>. Он тянет
		// свой srcset и остаётся отзывчивым, теряется только смена кадра.
		return $open . $img . $close;
	}

	$mobile_meta = wp_get_attachment_metadata( $mobile_id );

	$source = sprintf(
		'<source media="%s" srcset="%s" width="%d" height="%d" />',
		esc_attr( RAZIL_HERO_MOBILE_MEDIA ),
		esc_url( $mobile_src ),
		isset( $mobile_meta['width'] ) ? (int) $mobile_meta['width'] : 0,
		isset( $mobile_meta['height'] ) ? (int) $mobile_meta['height'] : 0
	);

	return $open . '<picture>' . $source . $img . '</picture>' . $close;
}
add_filter( 'render_block', 'razil_hero_picture', 10, 2 );
