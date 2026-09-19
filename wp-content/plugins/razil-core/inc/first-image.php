<?php
/**
 * Приоритет загрузки у первого содержательного снимка страницы.
 *
 * §9 концепции: все фотографии грузятся лениво, кроме изображения
 * первого экрана — у него loading="eager" и fetchpriority="high".
 *
 * На главной это уже сделано: панораму офиса собирает razil_hero_picture()
 * и сама проставляет ей приоритет. А на страницах услуг и на контактах
 * главный снимок лежит в содержимом записи с жёстко прописанным
 * loading="lazy". Ядро в такой разметке ничего не меняет: атрибут уже
 * задан, и wp_filter_content_tags() его уважает. В итоге самое крупное
 * изображение первого экрана — то самое, по которому считается LCP, —
 * грузилось в последнюю очередь.
 *
 * Отбор по классу, а не по порядку в документе. По порядку первым
 * оказался бы логотип в шапке: он тоже core/image, но лежит в части
 * шаблона, а не в содержимом. Классы rz-service-photo и
 * rz-contacts__photo стоят ровно на главных снимках страниц.
 *
 * @package RazilCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Классы, которыми помечен главный снимок содержательной страницы.
 *
 * Список, а не один класс: у страницы услуги и у контактов снимки
 * играют одну роль, но живут в разных композициях и называются
 * по-разному.
 */
const RAZIL_LEAD_IMAGE_CLASSES = array( 'rz-service-photo', 'rz-contacts__photo' );

/**
 * Первому такому снимку на странице — немедленная загрузка.
 *
 * Считается именно первый: ниже по странице снимки той же роли
 * встречаются снова (галерея работ), и поднимать приоритет всем
 * значило бы отменить ленивую загрузку вовсе.
 *
 * Счётчик статический и живёт в пределах запроса. Сбрасывать его
 * не нужно: на один ответ приходится одна страница.
 *
 * @param string $block_content Готовая разметка блока.
 * @param array  $block         Описание блока.
 * @return string
 */
function razil_lead_image_priority( $block_content, $block ) {
	static $done = false;

	if ( $done || empty( $block['blockName'] ) || 'core/image' !== $block['blockName'] ) {
		return $block_content;
	}

	$class = isset( $block['attrs']['className'] ) ? (string) $block['attrs']['className'] : '';

	$match = false;
	foreach ( RAZIL_LEAD_IMAGE_CLASSES as $lead ) {
		if ( false !== strpos( $class, $lead ) ) {
			$match = true;
			break;
		}
	}

	if ( ! $match ) {
		return $block_content;
	}

	// Тег ищется по подстроке, а не разбором HTML: разметка приходит
	// от core/image и устроена предсказуемо, а полноценный разбор ради
	// двух атрибутов обошёлся бы дороже самой правки.
	if ( false === strpos( $block_content, '<img' ) ) {
		return $block_content;
	}

	$done = true;

	// loading подменяется, если он есть, и дописывается, если его нет.
	if ( false !== strpos( $block_content, 'loading="lazy"' ) ) {
		$block_content = str_replace( 'loading="lazy"', 'loading="eager"', $block_content );
	} elseif ( false === strpos( $block_content, 'loading=' ) ) {
		$block_content = str_replace( '<img ', '<img loading="eager" ', $block_content );
	}

	if ( false === strpos( $block_content, 'fetchpriority=' ) ) {
		$block_content = str_replace( '<img ', '<img fetchpriority="high" ', $block_content );
	}

	return $block_content;
}
add_filter( 'render_block', 'razil_lead_image_priority', 10, 2 );
