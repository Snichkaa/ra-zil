<?php
/**
 * Хлебные крошки: где показывать, как размечать, как подписывать.
 *
 * Саму цепочку строит Yoast, он же кладёт её в микроразметку BreadcrumbList.
 * Здесь только видимый элемент: блок yoast-seo/breadcrumbs вставлен один раз
 * в parts/header.html — эту часть подключают все шаблоны темы, — а всё
 * остальное делают фильтры.
 *
 * Почему фильтры, а не обёртка из wp:html в шаблоне: если Yoast когда-нибудь
 * отключат, незарегистрированный блок отдаёт пустую строку и не оставляет
 * в разметке ничего. Обёртка <nav> из wp:html осталась бы в теме и дала бы
 * пустую полосу под шапкой. Фильтры при отключённом плагине просто
 * не сработают.
 *
 * @package RazilCore
 */

// Прямой доступ к файлу запрещён.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Типы записей, у которых показываются крошки.
 *
 * Крошки имеют смысл там, где есть настоящая иерархия. У услуг она есть:
 * «Главная — Услуги — название». Дерево страниц на сайте плоское, вложенных
 * страниц нет ни одной, поэтому вне услуг цепочка выродилась бы в «Главная —
 * Заголовок»: второе звено дословно повторяет H1, а первое дублирует ссылку
 * с эмблемы в шапке.
 *
 * Каталог включается добавлением 'products' в этот список — одной правкой.
 * Рубрики типа подхватятся сами, см. razil_breadcrumbs_wanted().
 */
const RAZIL_BREADCRUMBS_TYPES = array( 'services' );

/**
 * Нужны ли крошки на текущей странице.
 *
 * Проверяются три случая: одиночная запись перечисленных типов, архив типа
 * и рубрика типа. Рубрики выводятся из самих типов через
 * get_object_taxonomies, чтобы при добавлении типа не пришлось помнить
 * ещё и про его таксономии.
 *
 * @return bool
 */
function razil_breadcrumbs_wanted(): bool {
	$types = RAZIL_BREADCRUMBS_TYPES;

	if ( is_singular( $types ) || is_post_type_archive( $types ) ) {
		return true;
	}

	$taxonomies = array();
	foreach ( $types as $type ) {
		$taxonomies = array_merge( $taxonomies, get_object_taxonomies( $type ) );
	}

	return ! empty( $taxonomies ) && is_tax( $taxonomies );
}

/**
 * Убирает блок крошек там, где они не нужны.
 *
 * Гасить вывод фильтром wpseo_breadcrumb_output недостаточно: блок Yoast
 * оборачивает вывод презентера в свой div безусловно, и от пустого вывода
 * в разметке остался бы <div class="yoast-breadcrumbs"></div>. render_block
 * снимает блок целиком, и на страницах без крошек не остаётся ничего.
 *
 * @param string               $content Разметка блока.
 * @param array<string, mixed> $block   Разобранный блок.
 *
 * @return string
 */
function razil_breadcrumbs_gate( $content, $block ) {
	if ( ! is_array( $block ) || 'yoast-seo/breadcrumbs' !== ( $block['blockName'] ?? '' ) ) {
		return $content;
	}

	return razil_breadcrumbs_wanted() ? $content : '';
}
add_filter( 'render_block', 'razil_breadcrumbs_gate', 10, 2 );

/**
 * Обёртка списка: ol вместо span.
 *
 * @return string
 */
function razil_breadcrumbs_wrapper(): string {
	return 'ol';
}
add_filter( 'wpseo_breadcrumb_output_wrapper', 'razil_breadcrumbs_wrapper' );

/**
 * Класс на списке. По умолчанию Yoast не ставит никакого.
 *
 * @return string
 */
function razil_breadcrumbs_class(): string {
	return 'rz-breadcrumbs__list';
}
add_filter( 'wpseo_breadcrumb_output_class', 'razil_breadcrumbs_class' );

/**
 * Звено списка: li вместо span.
 *
 * @return string
 */
function razil_breadcrumbs_item(): string {
	return 'li';
}
add_filter( 'wpseo_breadcrumb_single_link_wrapper', 'razil_breadcrumbs_item' );

/**
 * Разделитель убирается из разметки и рисуется в CSS.
 *
 * Текстовый «»» стоял прямо между звеньями, а текст внутри ol недопустим.
 * Пустая строка не оставляет узла с текстом: презентер обрамляет разделитель
 * пробелами, а пробельный узел внутри ol разрешён.
 *
 * @return string
 */
function razil_breadcrumbs_separator(): string {
	return '';
}
add_filter( 'wpseo_breadcrumb_separator', 'razil_breadcrumbs_separator' );

/**
 * Оборачивает список в озвучиваемую навигацию.
 *
 * Ярлык обязателен: на странице несколько элементов nav — шапка, подвал,
 * крошки, — и без ярлыка скринридер объявит их одинаково.
 *
 * @param string $output Готовая разметка списка.
 *
 * @return string
 */
function razil_breadcrumbs_nav( $output ) {
	if ( ! is_string( $output ) || '' === trim( $output ) ) {
		return $output;
	}

	return '<nav class="rz-breadcrumbs" aria-label="Путь к текущей странице">' . $output . '</nav>';
}
add_filter( 'wpseo_breadcrumb_output', 'razil_breadcrumbs_nav' );

/**
 * Переводит первое звено: Home → Главная.
 *
 * Подпись приходит из настройки breadcrumbs-home, которая у Yoast по
 * умолчанию английская и переводом плагина не покрывается.
 *
 * Правится именно здесь, на уровне презентера, а НЕ фильтром
 * wpseo_breadcrumb_links: тот применяется в генераторе, то есть до
 * разветвления на видимый вывод и микроразметку, и переписал бы заодно
 * BreadcrumbList. Схему трогать не договаривались — там осталось Home.
 *
 * Условие на точное «Home» намеренно: если подпись однажды зададут
 * в настройках Yoast руками, эта правка отступит и не будет спорить
 * с администратором.
 *
 * @param string               $link       Разметка звена.
 * @param array<string, mixed> $breadcrumb Данные звена.
 *
 * @return string
 */
function razil_breadcrumbs_home_label( $link, $breadcrumb ) {
	if ( ! is_string( $link ) || ! is_array( $breadcrumb ) ) {
		return $link;
	}

	if ( 'Home' !== ( $breadcrumb['text'] ?? '' ) ) {
		return $link;
	}

	return str_replace( '>Home<', '>Главная<', $link );
}
add_filter( 'wpseo_breadcrumb_single_link', 'razil_breadcrumbs_home_label', 10, 2 );
