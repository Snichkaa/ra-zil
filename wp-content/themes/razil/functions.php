<?php
/**
 * Razil Theme Functions
 *
 * Тема «Земля и Люди». Блочная тема: почти всё оформление живёт
 * в theme.json, здесь только подключение файлов и точечные правки.
 *
 * @package Razil
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Версия для сброса кеша.
 *
 * Локально (WP_DEBUG включён) берём время изменения файла, чтобы правки в CSS
 * были видны без Ctrl+F5. На продакшене — фиксированная строка.
 */
define( 'RAZIL_VERSION', '1.0.0' );

/**
 * Версия конкретного файла темы для аргумента $ver.
 *
 * @param string $relative_path Путь относительно корня темы.
 * @return string
 */
function razil_asset_version( $relative_path ) {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		$file = get_theme_file_path( $relative_path );
		if ( file_exists( $file ) ) {
			return (string) filemtime( $file );
		}
	}

	return RAZIL_VERSION;
}

/**
 * Регистрация блоков темы.
 *
 * Блоки лежат в build/blocks/<имя>/block.json. Регистрируем каталог,
 * а не файл: register_block_type() сам прочитает block.json внутри.
 */
function razil_register_blocks() {
	$dirs = glob( get_theme_file_path( 'build/blocks/*' ), GLOB_ONLYDIR );

	foreach ( (array) $dirs as $dir ) {
		if ( file_exists( $dir . '/block.json' ) ) {
			register_block_type( $dir );
		}
	}
}
add_action( 'init', 'razil_register_blocks' );

/**
 * Категория паттернов «Услуга».
 *
 * Без регистрации категория всё равно «работает» — паттерн с неизвестной
 * категорией не пропадает, — но в боковой панели вставки он попадает
 * в общий список без заголовка, и найти нужный можно только поиском.
 * Паттернов услуги семь, поэтому своя вкладка им нужна.
 *
 * Имя razil-service, а не razil: категория razil уже стоит у старого
 * patterns/service-page.php, и класть рядом семь новых паттернов в ту же
 * кучу — значит потерять разницу между «вся страница одним куском»
 * и «одна секция».
 */
function razil_register_pattern_categories() {
	register_block_pattern_category(
		'razil-service',
		array(
			'label'       => 'Услуга',
			'description' => 'Секции страницы услуги: первый экран, вступление, что входит, сроки, документы, стоимость, вопросы.',
		)
	);
}
add_action( 'init', 'razil_register_pattern_categories' );

/**
 * Стили блоков для страниц услуг.
 *
 * Регистрируются из PHP, без сборки. Собранного JavaScript у темы нет
 * вовсе — все семнадцать её блоков серверные, в build/blocks/* лежат
 * только block.json и render.php, — и заводить ради пяти вариантов
 * оформления клиентский код было бы несоразмерно. WordPress сам
 * отдаёт зарегистрированные стили редактору через реестр типов блоков,
 * и в панели блока они появляются списком «Стили».
 *
 * Класс у стиля с именем foo — is-style-foo. Именно он и стоит
 * в правилах main.css.
 *
 * Стили заведены ТОЛЬКО для тех ролей, где вариантов два и оба
 * осмысленны: список с номерами и без, обычный абзац и заметка.
 * Там, где вариант один, класс пишется в паттерне напрямую —
 * лишняя запись в панели блока сбивает с толку не меньше, чем
 * её отсутствие.
 */
function razil_register_block_styles() {
	register_block_style(
		'core/group',
		array(
			'name'  => 'no-numbers',
			'label' => 'Без номеров',
		)
	);

	register_block_style(
		'core/paragraph',
		array(
			'name'  => 'note',
			'label' => 'Заметка',
		)
	);

	/*
	 * Отклонение на шкале сроков. Стиль пункта списка, а не всей
	 * шкалы: отклоняется одна точка из четырёх, остальные —
	 * обычные шаги.
	 */
	register_block_style(
		'core/list-item',
		array(
			'name'  => 'delay',
			'label' => 'Отклонение',
		)
	);

	/*
	 * Подпись группы в карточке документов. Абзац, а не заголовок:
	 * «Основные документы» — не раздел страницы, а ярлык списка,
	 * и H4 между H2 и содержимым создал бы уровень, которого
	 * в структуре нет.
	 */
	register_block_style(
		'core/paragraph',
		array(
			'name'  => 'label',
			'label' => 'Метка',
		)
	);

	/*
	 * Два вида списка документов. «Галочки» — то, что нужно всем;
	 * «Дополнительные» — то, что нужно в отдельных случаях. Разница
	 * видна маркером, а не подписью «необязательно» у каждого пункта.
	 */
	register_block_style(
		'core/list',
		array(
			'name'  => 'checks',
			'label' => 'Галочки',
		)
	);

	register_block_style(
		'core/list',
		array(
			'name'  => 'extra',
			'label' => 'Дополнительные',
		)
	);
}
add_action( 'init', 'razil_register_block_styles' );

/**
 * Категория для блоков темы.
 *
 * Без неё блоки с "category": "razil" регистрируются,
 * но не отображаются в панели вставки.
 *
 * @param array $categories Категории блоков.
 * @return array
 */
function razil_block_category( $categories ) {
	array_unshift(
		$categories,
		array(
			'slug'  => 'razil',
			'title' => 'Земля и Люди',
			'icon'  => null,
		)
	);

	return $categories;
}
add_filter( 'block_categories_all', 'razil_block_category' );

/**
 * Регистрация блоков на стороне редактора.
 *
 * Блок, объявленный только в PHP, рисуется на сайте, но в панели вставки
 * не попадает: редактору нужна регистрация в JavaScript. Собираем метаданные
 * из реального реестра WordPress и отдаём их одним скриптом – так список
 * блоков не нужно дублировать внутри.
 */
function razil_block_editor_assets() {
	$payload = array();

	foreach ( WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type ) {
		if ( 0 !== strpos( $name, 'razil/' ) ) {
			continue;
		}

		$payload[] = array(
			'name'        => $name,
			'title'       => $type->title ? $type->title : $name,
			'description' => $type->description ? $type->description : '',
			'category'    => $type->category ? $type->category : 'widgets',
			'icon'        => $type->icon ? $type->icon : 'block-default',
			'attributes'  => (object) ( is_array( $type->attributes ) ? $type->attributes : array() ),
		);
	}

	if ( ! $payload ) {
		return;
	}

	wp_enqueue_script(
		'razil-blocks-editor',
		get_theme_file_uri( 'assets/js/blocks-editor.js' ),
		array(
			'wp-blocks',
			'wp-element',
			'wp-block-editor',
			'wp-components',
			'wp-server-side-render',
		),
		razil_asset_version( 'assets/js/blocks-editor.js' ),
		true
	);

	wp_add_inline_script(
		'razil-blocks-editor',
		'window.razilBlocks = ' . wp_json_encode( $payload ) . ';',
		'before'
	);
}
add_action( 'enqueue_block_editor_assets', 'razil_block_editor_assets' );

/**
 * ДЕБАГ – удали, когда блоки появят в редакторе.
 *
 * Показывает в админке, сколько блоков razil попало в реестр WordPress.
 */
function razil_debug_blocks_notice() {
	$registered = WP_Block_Type_Registry::get_instance()->get_all_registered();
	$mine       = array();

	foreach ( $registered as $name => $type ) {
		if ( 0 === strpos( $name, 'razil/' ) ) {
			$mine[] = $name;
		}
	}

	$script = file_exists( get_theme_file_path( 'assets/js/blocks-editor.js' ) )
		? 'blocks-editor.js на месте'
		: 'ФАЙЛ assets/js/blocks-editor.js НЕТ';

	printf(
		'<div class="notice notice-%1$s"><p><strong>Блоки razil: в реестре %2$d. %3$s</strong></p></div>',
		$mine ? 'success' : 'error',
		count( $mine ),
		esc_html( $script )
	);
}
add_action( 'admin_notices', 'razil_debug_blocks_notice' );

/**
 * Стили фронтенда.
 *
 * Порядок важен: tokens.css объявляет примитивы --rz-*, на которые
 * ссылается палитра в theme.json. Он должен грузиться первым.
 *
 * Шрифты здесь НЕ подключаются: @font-face генерирует WordPress
 * из блока fontFace в theme.json.
 */
function razil_enqueue_styles() {
	wp_enqueue_style(
		'razil-tokens',
		get_theme_file_uri( 'assets/css/tokens.css' ),
		array(),
		razil_asset_version( 'assets/css/tokens.css' )
	);

	wp_enqueue_style(
		'razil-main',
		get_theme_file_uri( 'assets/css/main.css' ),
		array( 'razil-tokens' ),
		razil_asset_version( 'assets/css/main.css' )
	);
}
add_action( 'wp_enqueue_scripts', 'razil_enqueue_styles' );

/**
 * Скрипты фронтенда.
 *
 * Единственный свой скрипт на фронте: шапка. В подвале, без зависимостей —
 * он ничего не ждёт и работает с готовой разметкой.
 *
 * Версия через razil_asset_version, как у стилей: при WP_DEBUG это
 * filemtime, в бою — версия темы.
 */
function razil_enqueue_scripts() {
	wp_enqueue_script(
		'razil-header',
		get_theme_file_uri( 'assets/js/header.js' ),
		array(),
		razil_asset_version( 'assets/js/header.js' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'razil_enqueue_scripts' );

/**
 * Предзагрузка PT Sans 400.
 *
 * Первый экран набран им и должен появиться на медленном мобильном
 * интернете за секунду. Остальные начертания грузятся обычным порядком.
 *
 * crossorigin обязателен даже для своего домена: шрифты запрашиваются
 * в режиме CORS, без атрибута файл скачается дважды.
 *
 * @param array $resources Ресурсы для предзагрузки.
 * @return array
 */
function razil_preload_fonts( $resources ) {
	$resources[] = array(
		'href'        => get_theme_file_uri( 'assets/fonts/pt-sans-400.woff2' ),
		'as'          => 'font',
		'type'        => 'font/woff2',
		'crossorigin' => 'anonymous',
	);

	return $resources;
}
add_filter( 'wp_preload_resources', 'razil_preload_fonts' );

/**
 * Поддержка возможностей темы.
 */
function razil_setup() {
	add_theme_support( 'custom-logo' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );

	// Обрезка изображений под пропорции из макета.
	add_image_size( 'razil-product', 800, 800, true );   // товар, 1:1
	add_image_size( 'razil-branch', 1200, 800, true );   // филиал, 3:2
}
add_action( 'after_setup_theme', 'razil_setup' );

/**
 * Стили блочного редактора.
 *
 * tokens.css обязателен: без него переменные --rz-* в редакторе
 * не определены и палитра из theme.json не отрисуется.
 */
function razil_editor_styles() {
	add_theme_support( 'editor-styles' );
	add_editor_style(
		array(
			'assets/css/tokens.css',
			'assets/css/editor-styles.css',
		)
	);
}
add_action( 'after_setup_theme', 'razil_editor_styles' );

/**
 * Убираем то, что тормозит первый экран.
 *
 * Скрипт определения эмодзи — лишний запрос и лишний парсинг
 * на каждой странице. Эмодзи в проекте не используются.
 */
function razil_trim_head() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'rsd_link' );
}
add_action( 'init', 'razil_trim_head' );

/**
 * Отключаем стили ядра для блоков.
 *
 * Дизайн-система задана целиком в theme.json и main.css.
 * Дефолтные отступы и цвета ядра с ней конфликтуют.
 */
function razil_remove_core_block_styles() {
	wp_dequeue_style( 'wp-block-library-theme' );
}
add_action( 'wp_enqueue_scripts', 'razil_remove_core_block_styles', 20 );

/**
 * Пустой анонс должен оставаться пустым.
 *
 * Если у записи не заполнен post_excerpt, WordPress генерирует анонс
 * автоматически — берёт первые 55 слов из post_content. На странице услуги
 * блок wp:post-excerpt стоит перед wp:post-content, поэтому вводный абзац
 * выводился дважды подряд.
 *
 * Отключаем автогенерацию для типов, где анонс — осознанное поле редактора:
 * пусто в базе значит пусто на странице.
 */
function razil_no_auto_excerpt( $excerpt, $post ) {
	if ( ! $post instanceof WP_Post ) {
		return $excerpt;
	}

	if ( ! in_array( $post->post_type, array( 'services', 'products' ), true ) ) {
		return $excerpt;
	}

	return '' === trim( (string) $post->post_excerpt ) ? '' : $excerpt;
}
add_filter( 'get_the_excerpt', 'razil_no_auto_excerpt', 10, 2 );

/**
 * Предзагрузка фотографии первого экрана: это LCP-элемент главной
 * на всех ширинах.
 *
 * Кадра два, и выбирает между ними <picture> в разметке — значит
 * предзагрузок тоже две, каждая со своим media. Одна общая тянула бы
 * десктопную панораму и на телефон, где она нигде не показывается,
 * и браузер качал бы два файла вместо одного.
 *
 * Десктопная предзагрузка несёт imagesrcset и imagesizes, повторяя то,
 * что стоит у самого тега img: без них она называет один конкретный
 * адрес — полный размер, — и браузер обязан скачать именно его, а вариант
 * по srcset выбирает потом отдельно, то есть снова два файла. Мобильная
 * идёт без них намеренно: у <source> один адрес без srcset, повторять
 * там нечего.
 *
 * Номера вложений даёт razil_hero_attachment_id() плагина razil-core:
 * функция ищет их по пути файла в медиатеке, а сами пути лежат в
 * константах RAZIL_HERO_FILE_DESKTOP и RAZIL_HERO_FILE_MOBILE — там же,
 * где их читает фильтр, собирающий <picture>. Номер вложению выдаётся
 * при загрузке и на другом сайте будет другим; путь файла перенос
 * переживает.
 *
 * Запасных числовых номеров на случай выключенного плагина здесь нет, и
 * это выбор, а не упущение. Без плагина <picture> не соберётся, в
 * разметке останется обычный img из шаблона, и предзагрузка по зашитому
 * номеру тянула бы файл, которого на странице нет. Поэтому, не найдя
 * функции, предзагрузка тихо выключается.
 *
 * Печатается только если в разметке текущего шаблона найден класс
 * rz-hero__photo. Без этой проверки перевёрстка первого экрана ломала бы
 * страницу молча: предзагрузка продолжила бы тянуть фотографию, которую
 * на странице уже никто не показывает. Источник — глобальная переменная
 * ядра с содержимым текущего шаблона: она заполняется при подборе
 * шаблона, то есть до вывода head, и учитывает копию из базы, если
 * шаблон когда-нибудь сохранят в редакторе сайта.
 */
function razil_preload_hero_image() {
	if ( ! is_front_page() ) {
		return;
	}

	// Номера вложений выдаёт резолвер плагина razil-core по пути файла.
	// Плагина нет — нет и резолвера: выходим тихо. Предзагружать кадр,
	// который без плагина в разметку не попадёт, значило бы качать
	// лишний файл, а разбирать отсутствие плагина ошибкой — ронять шапку.
	if ( ! function_exists( 'razil_hero_attachment_id' )
		|| ! defined( 'RAZIL_HERO_FILE_DESKTOP' )
		|| ! defined( 'RAZIL_HERO_FILE_MOBILE' ) ) {
		return;
	}

	// Сторож узнаёт героя по классу блока, а не по номеру вложения:
	// номер выдаётся при загрузке и на другом сайте будет другим,
	// класс переживает и перенос, и замену самой фотографии.
	$tpl = isset( $GLOBALS['_wp_current_template_content'] ) ? $GLOBALS['_wp_current_template_content'] : '';
	if ( false === strpos( $tpl, 'rz-hero__photo' ) ) {
		return;
	}

	$desktop_id = razil_hero_attachment_id( RAZIL_HERO_FILE_DESKTOP );
	$mobile_id  = razil_hero_attachment_id( RAZIL_HERO_FILE_MOBILE );

	$desktop_src = $desktop_id ? wp_get_attachment_image_url( $desktop_id, 'full' ) : '';
	$mobile_src  = $mobile_id ? wp_get_attachment_image_url( $mobile_id, 'full' ) : '';
	if ( ! $desktop_src || ! $mobile_src ) {
		return;
	}
	$desktop_srcset = wp_get_attachment_image_srcset( $desktop_id, 'full' );

	printf(
		'<link rel="preload" as="image" media="(max-width: 47.99rem)" href="%s" fetchpriority="high" />' . "\n",
		esc_url( $mobile_src )
	);
	// Порог здесь 47.99rem, а не 48rem, хотя у <source> стоит max-width:
	// 47.99rem: пересечение безвреднее разрыва — при 48rem вьюпорт строго
	// между порогами не совпал бы ни с одной предзагрузкой, а при
	// пересечении браузер просто берёт первую подошедшую.
	printf(
		'<link rel="preload" as="image" media="(min-width: 47.99rem)" href="%s"%s imagesizes="100vw" fetchpriority="high" />' . "\n",
		esc_url( $desktop_src ),
		$desktop_srcset ? ' imagesrcset="' . esc_attr( $desktop_srcset ) . '"' : ''
	);
}
add_action( 'wp_head', 'razil_preload_hero_image', 2 );
