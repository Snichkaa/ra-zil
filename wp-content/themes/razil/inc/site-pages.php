<?php
/** Public information pages and the locally hosted virtual tour. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function razil_is_document_page() {
	return is_page( array( 'politika', 'soglasie', 'polzovatelskoe-soglashenie' ) );
}

function razil_information_assets() {
	if ( ! ( is_front_page() || is_post_type_archive( 'services' ) || is_page( array( 'o-kompanii', 'kontakty', 'otzyvy', 'rekvizity', 'spasibo' ) ) || razil_is_document_page() ) ) {
		return;
	}
	wp_enqueue_style( 'razil-information', get_theme_file_uri( 'assets/css/information.css' ), array( 'razil-main' ), razil_asset_version( 'assets/css/information.css' ) );
	wp_enqueue_script( 'razil-information', get_theme_file_uri( 'assets/js/information.js' ), array(), razil_asset_version( 'assets/js/information.js' ), true );
}
add_action( 'wp_enqueue_scripts', 'razil_information_assets', 15 );

add_filter( 'body_class', static function ( $classes ) {
	if ( razil_is_document_page() ) {
		$classes[] = 'rz-document-page';
	}
	if ( is_page( 'rekvizity' ) ) {
		$classes[] = 'rz-details-page';
	}
	if ( is_page( 'spasibo' ) ) {
		$classes[] = 'rz-thanks-page';
	}
	return $classes;
} );

/** Kept in uploads: the 265 MB package travels with media, not with the theme. */
function razil_tour_url() {
	$uploads = wp_upload_dir();
	return trailingslashit( $uploads['baseurl'] ) . 'virtual-tour/index.html';
}

function razil_virtual_tour() {
	$url = add_query_arg( array( 's' => 'pano159', 'norotation' => '' ), razil_tour_url() );
	$poster = content_url( 'uploads/2026/09/ofis-ulica-panorama.webp' );
	ob_start();
	?>
	<div class="rz-tour" data-rz-tour data-tour-url="<?php echo esc_url( razil_tour_url() ); ?>">
		<div class="rz-tour__cover">
			<img src="<?php echo esc_url( $poster ); ?>" alt="Здание агентства «Земля и Люди»" width="1294" height="647" loading="lazy" decoding="async" />
			<div class="rz-tour__invitation">
				<span class="rz-tour__badge" aria-hidden="true">360°</span>
				<p>Офис, зал прощания и территория</p>
				<a class="wp-element-button" href="<?php echo esc_url( $url ); ?>" data-tour-start>Начать виртуальный тур</a>
			</div>
		</div>
		<div class="rz-tour__viewer" hidden>
			<div class="rz-tour__toolbar">
				<label>Посмотреть <select data-tour-scene><option value="pano159">Территорию</option><option value="pano24">Офис</option><option value="pano23">Зал прощания</option></select></label>
				<div class="rz-tour__tools"><button type="button" data-tour-fullscreen hidden>На весь экран</button><button type="button" data-tour-close>Закрыть тур</button></div>
			</div>
			<div class="rz-tour__frame"></div>
		</div>
		<div class="rz-tour__caption"><p data-tour-status role="status">Осмотритесь до посещения: перемещайтесь по стрелкам внутри панорамы.</p><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">Открыть тур отдельно<span class="screen-reader-text"> — в новой вкладке</span></a></div>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'razil_virtual_tour', 'razil_virtual_tour' );

function razil_tour_preview() {
	ob_start();
	?>
	<div class="rz-tour-preview">
		<div class="rz-tour-preview__text"><p class="rz-eyebrow">Знакомство с агентством</p><h2>Посмотрите, где мы работаем</h2><p>Офис, зал прощания и территория в одном виртуальном туре. Можно заранее осмотреть помещения и понять, как найти вход.</p><a class="wp-element-button" href="<?php echo esc_url( home_url( '/o-kompanii/#virtual-tour' ) ); ?>">Посмотреть 3D-тур</a></div>
		<a class="rz-tour-preview__image" href="<?php echo esc_url( home_url( '/o-kompanii/#virtual-tour' ) ); ?>" aria-label="Перейти к виртуальному туру по агентству"><img src="<?php echo esc_url( content_url( 'uploads/2026/09/ofis-ulica-panorama.webp' ) ); ?>" alt="" width="1294" height="647" loading="lazy" decoding="async" /><span aria-hidden="true">360° · Виртуальный тур</span></a>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'razil_tour_preview', 'razil_tour_preview' );

/** Short labels belong to the directory; post titles and SEO remain unchanged. */
function razil_directory_labels() {
	return array(
		'organizatsiya-pohoron' => array( 'Организация похорон', 'Документы, транспорт и проведение церемонии. Специалист сопровождает семью на каждом этапе.' ),
		'krematsiya' => array( 'Кремация', 'Организация прощания и кремации, оформление документов и получение урны с прахом.' ),
		'blagoustroystvo' => array( 'Благоустройство захоронений', 'Ограды, отсыпка, плитка и озеленение. Подбор решения с учётом ваших пожеланий и бюджета.' ),
		'transportirovka' => array( 'Перевозка усопших', 'Перевозка в морг, катафалк на день похорон и доставка в другие города и страны.' ),
		'yuridicheskaya-pomoshch' => array( 'Помощь с документами', 'Оформление свидетельства о смерти, пособий и льгот, восстановление утраченных документов.' ),
	);
}
add_filter( 'render_block_core/post-title', static function ( $html, $parsed, $block ) {
	if ( ! is_post_type_archive( 'services' ) ) {
		return $html;
	}
	$post = get_post( $block->context['postId'] ?? 0 );
	$labels = razil_directory_labels();
	if ( $post && isset( $labels[ $post->post_name ] ) ) {
		$html = str_replace( esc_html( $post->post_title ), esc_html( $labels[ $post->post_name ][0] ), $html );
	}
	return $html;
}, 20, 3 );
add_filter( 'get_the_excerpt', static function ( $excerpt, $post ) {
	$labels = razil_directory_labels();
	return is_post_type_archive( 'services' ) && isset( $labels[ $post->post_name ] ) ? $labels[ $post->post_name ][1] : $excerpt;
}, 20, 2 );

/** Add an outline at render time, preserving the legal text in the database. */
add_filter( 'the_content', static function ( $content ) {
	if ( ! razil_is_document_page() || ! is_main_query() || ! in_the_loop() ) {
		return $content;
	}
	$links = array();
	$content = preg_replace_callback( '#<h2\b[^>]*>(.*?)</h2>#su', static function ( $match ) use ( &$links ) {
		$id = 'document-section-' . ( count( $links ) + 1 );
		$label = trim( preg_replace( '/^\d+\.\s*/u', '', wp_strip_all_tags( $match[1] ) ) );
		$label = mb_strtolower( $label );
		$label = mb_strtoupper( mb_substr( $label, 0, 1 ) ) . mb_substr( $label, 1 );
		$links[] = '<li><a href="#' . esc_attr( $id ) . '">' . esc_html( $label ) . '</a></li>';
		$tag = new WP_HTML_Tag_Processor( $match[0] );
		$tag->next_tag( 'H2' );
		$tag->set_attribute( 'id', $id );
		return $tag->get_updated_html();
	}, $content );
	if ( ! $links ) {
		return $content;
	}
	return '<div class="rz-document-layout"><nav class="rz-document-toc" aria-label="Содержание документа"><details><summary>Содержание документа</summary><ol>' . implode( '', $links ) . '</ol></details></nav><div class="rz-document-text">' . $content . '</div></div>';
}, 20 );

/** Server-side feedback must be visible even when JS is disabled. */
add_filter( 'render_block_core/details', static function ( $html, $block ) {
	if ( is_page( 'otzyvy' ) && str_contains( $block['attrs']['className'] ?? '', 'rz-review-compose' ) && isset( $_GET['rz'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tag = new WP_HTML_Tag_Processor( $html );
		$tag->next_tag( 'DETAILS' );
		$tag->set_attribute( 'open', true );
		return $tag->get_updated_html();
	}
	return $html;
}, 10, 2 );
