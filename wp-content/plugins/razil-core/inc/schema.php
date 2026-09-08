<?php
/**
 * Микроразметка сверх той, что даёт Yoast: FAQPage и Service.
 *
 * Yoast строит граф из узлов WebPage, BreadcrumbList, WebSite и Organization.
 * Своих типов для услуги и для аккордеонов ядра у него нет: FAQPage он умеет
 * собирать только из собственного блока yoast/faq-block, а у нас вопросы
 * лежат в core/details.
 *
 * Почему узлы добавляются в граф Yoast, а не выводятся отдельным блоком
 * ld+json: в графе у каждого узла есть @id, и узлы ссылаются друг на друга.
 * Услуга указывает на организацию, вопросы — на страницу. Отдельный блок
 * этих связей не получит: пришлось бы либо оставить узлы без хозяина, либо
 * продублировать Organization и WebPage с другими @id, и на странице
 * оказались бы два описания одной организации.
 *
 * Почему фильтр над массивом, а не свой класс через wpseo_schema_graph_pieces:
 * тот путь требует наследовать абстрактный класс Yoast, то есть привязать
 * плагин к иерархии классов чужого кода. При отключённом Yoast класс
 * не найдётся. Фильтр над обычным массивом такой связи не создаёт —
 * нет плагина, фильтр просто не вызовется.
 *
 * @package RazilCore
 */

// Прямой доступ к файлу запрещён.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Типы записей, у которых выводится узел Service.
 *
 * Услуга — то, что агентство делает за деньги, и у неё есть название
 * и описание. Товары каталога сюда не входят: у них свой тип Product,
 * но размечать его пока нечем — ни цены, ни описания, ни изображения
 * в данных нет.
 */
const RAZIL_SCHEMA_SERVICE_TYPES = array( 'services' );

/**
 * Добавляет наши узлы в конец графа.
 *
 * ТОЛЬКО В КОНЕЦ, и это не вкусовщина. После этого фильтра Yoast вызывает
 * remove_empty_breadcrumb, а тот работает по индексам массива: находит
 * позицию BreadcrumbList и вырезает её через array_splice. Вставка в начало
 * сдвинула бы индексы, и вырезан оказался бы чужой узел.
 *
 * @param array<int, array<string, mixed>> $graph   Граф Yoast.
 * @param object                           $context Контекст страницы (Meta_Tags_Context).
 *
 * @return array<int, array<string, mixed>>
 */
function razil_schema_extend( $graph, $context ) {
	if ( ! is_array( $graph ) || ! is_object( $context ) ) {
		return $graph;
	}

	$graph = razil_schema_add_faq( $graph, $context );
	$graph = razil_schema_add_service( $graph, $context );

	return $graph;
}
add_filter( 'wpseo_schema_graph', 'razil_schema_extend', 10, 2 );

/**
 * Приводит @type узла к массиву.
 *
 * У Yoast тип бывает и строкой, и массивом: validate_type сворачивает
 * массив из одного значения в строку.
 *
 * @param array<string, mixed> $node Узел графа.
 *
 * @return array<int, string>
 */
function razil_schema_node_types( array $node ): array {
	$type = $node['@type'] ?? array();

	return is_array( $type ) ? $type : array( $type );
}

/**
 * Ищет в графе узел нужного типа и возвращает его ключ.
 *
 * Ищем по факту, а не по индексу: порядок узлов у Yoast зависит от типа
 * страницы, а на странице услуги, например, между WebPage и BreadcrumbList
 * стоит ещё ImageObject.
 *
 * @param array<int, array<string, mixed>> $graph Граф.
 * @param string                           $type  Искомый тип.
 *
 * @return int|null Ключ узла или null.
 */
function razil_schema_find( array $graph, string $type ) {
	foreach ( $graph as $key => $node ) {
		if ( is_array( $node ) && in_array( $type, razil_schema_node_types( $node ), true ) ) {
			return $key;
		}
	}

	return null;
}

/**
 * Собирает пары «вопрос — ответ» из блоков core/details.
 *
 * Блоки берутся у самого Yoast: он разбирает содержимое страницы и
 * раскладывает блоки по типам в $context->blocks. Своего parse_blocks
 * и уж тем менее регекспа по готовой разметке не нужно.
 *
 * Вопрос лежит внутри <summary>, а не в атрибутах блока: core/details
 * объявляет summary как "source": "rich-text", "selector": "summary",
 * то есть хранит его в разметке. Читаем ровно этим селектором через
 * WP_HTML_Processor — тем же контрактом, которым пользуется редактор.
 * Регексп по разметке дал бы то же на сегодняшнем содержимом, но сломался
 * бы на вложенном теге и не заметил бы смены структуры блока.
 *
 * Если ядро однажды изменит разметку блока, изменится и selector в его
 * block.json. Тогда вопрос окажется пустым, пара будет отброшена, а при
 * пустом наборе узел не выведется вовсе. Это деградация, а не поломка.
 *
 * @param object $context Контекст страницы.
 *
 * @return array<int, array{вопрос: string, ответ: string}>
 */
function razil_schema_faq_pairs( $context ): array {
	$pairs = array();

	if ( ! class_exists( 'WP_HTML_Processor' ) ) {
		return $pairs;
	}

	$blocks = $context->blocks ?? array();
	if ( ! is_array( $blocks ) || empty( $blocks['core/details'] ) ) {
		return $pairs;
	}

	foreach ( $blocks['core/details'] as $block ) {
		if ( ! is_array( $block ) ) {
			continue;
		}

		$question = razil_schema_summary_text( $block['innerHTML'] ?? '' );
		$answer   = razil_schema_inner_text( $block['innerBlocks'] ?? array() );

		// Обе половины обязательны: разметка не должна утверждать,
		// что на странице есть ответ, которого там нет.
		if ( '' === $question || '' === $answer ) {
			continue;
		}

		$pairs[] = array(
			'вопрос' => $question,
			'ответ'  => $answer,
		);
	}

	return $pairs;
}

/**
 * Достаёт текст из первого <summary> в разметке блока.
 *
 * Обход по токенам, а не next_tag: на открывающем теге
 * get_modifiable_text() возвращает пустую строку, текст лежит в отдельном
 * текстовом узле. Вложенные теги внутри вопроса — <em>, <strong> —
 * при таком обходе склеиваются в чистый текст.
 *
 * @param string $html Разметка блока.
 *
 * @return string Текст вопроса или пустая строка.
 */
function razil_schema_summary_text( string $html ): string {
	if ( '' === trim( $html ) ) {
		return '';
	}

	$processor = WP_HTML_Processor::create_fragment( $html );
	if ( null === $processor ) {
		return '';
	}

	$text   = '';
	$inside = false;

	while ( $processor->next_token() ) {
		$kind = $processor->get_token_type();
		$name = $processor->get_token_name();

		if ( '#tag' === $kind && 'SUMMARY' === $name ) {
			if ( $processor->is_tag_closer() ) {
				break;
			}
			$inside = true;
			continue;
		}

		if ( $inside && '#text' === $kind ) {
			$text .= $processor->get_modifiable_text();
		}
	}

	return razil_schema_tidy( $text );
}

/**
 * Склеивает текст вложенных блоков — это ответ на вопрос.
 *
 * @param array<int, mixed> $blocks Вложенные блоки.
 *
 * @return string
 */
function razil_schema_inner_text( array $blocks ): string {
	$text = '';

	foreach ( $blocks as $block ) {
		if ( ! is_array( $block ) ) {
			continue;
		}
		$text .= ' ' . wp_strip_all_tags( $block['innerHTML'] ?? '' );
	}

	return razil_schema_tidy( $text );
}

/**
 * Приводит текст к одной строке без лишних пробелов.
 *
 * Флаг u обязателен: без него \s не покрывает неразрывный пробел,
 * а он в текстах встречается.
 *
 * @param string $text Исходный текст.
 *
 * @return string
 */
function razil_schema_tidy( string $text ): string {
	$text = wp_specialchars_decode( $text, ENT_QUOTES );
	$text = preg_replace( '~\s+~u', ' ', $text );

	return trim( (string) $text );
}

/**
 * Добавляет вопросы и помечает страницу как FAQPage.
 *
 * Форма повторяет то, как это делает сам Yoast для своего блока
 * (см. src/generators/schema/faq.php): отдельного узла FAQPage нет,
 * вместо него тип добавляется к узлу страницы, а вопросы становятся
 * самостоятельными узлами Question, на которые страница ссылается
 * через mainEntity. Так требует и Google: FAQPage с mainEntity —
 * массивом вопросов.
 *
 * @param array<int, array<string, mixed>> $graph   Граф.
 * @param object                           $context Контекст.
 *
 * @return array<int, array<string, mixed>>
 */
function razil_schema_add_faq( array $graph, $context ): array {
	$pairs = razil_schema_faq_pairs( $context );
	if ( empty( $pairs ) ) {
		return $graph;
	}

	$page_key = razil_schema_find( $graph, 'WebPage' );
	if ( null === $page_key ) {
		return $graph;
	}

	$canonical = (string) ( $context->canonical ?? '' );
	if ( '' === $canonical ) {
		return $graph;
	}

	$language = get_bloginfo( 'language' );
	$refs     = array();

	foreach ( $pairs as $index => $pair ) {
		$id     = $canonical . '#faq-' . ( $index + 1 );
		$refs[] = array( '@id' => $id );

		/*
		 * url намеренно не выводится, хотя Yoast в своём блоке его ставит.
		 * У него у каждого вопроса есть якорь в разметке, а core/details
		 * никакого id не печатает: ссылка вида #faq-1 никуда не привела бы,
		 * то есть разметка утверждала бы место, которого на странице нет.
		 * @id таким обязательством не является — это идентификатор узла
		 * в графе, и у самого Yoast это #organization, #website, #breadcrumb.
		 *
		 * position оставлен для однородности с блоком Yoast. В schema.org
		 * это свойство ListItem, а не Question; валидаторы неизвестные
		 * свойства пропускают, но знать об этом стоит.
		 */
		$graph[] = array(
			'@type'          => 'Question',
			'@id'            => $id,
			'position'       => $index + 1,
			'name'           => $pair['вопрос'],
			'answerCount'    => 1,
			'acceptedAnswer' => array(
				'@type'      => 'Answer',
				'text'       => $pair['ответ'],
				'inLanguage' => $language,
			),
			'inLanguage'     => $language,
		);
	}

	// Тип добавляется к уже собранному узлу страницы: к моменту этого
	// фильтра WebPage готов, и повлиять на context->schema_page_type,
	// как это делает Yoast в своём is_needed(), уже поздно.
	$types = razil_schema_node_types( $graph[ $page_key ] );
	if ( ! in_array( 'FAQPage', $types, true ) ) {
		$types[] = 'FAQPage';
	}

	$graph[ $page_key ]['@type']      = count( $types ) === 1 ? reset( $types ) : array_values( $types );
	$graph[ $page_key ]['mainEntity'] = $refs;

	return $graph;
}

/**
 * Добавляет узел Service на страницу услуги.
 *
 * Только три поля, и все — настоящие. Цены нет: мета с ценой не существует,
 * а все ячейки столбца «Стоимость» в таблицах услуг заполнены многоточиями.
 * offers без price невалиден по правилам Google, поэтому его нет вовсе —
 * лучше отсутствие узла, чем узел, утверждающий несуществующую цену.
 * Схема.org для Service offers и не требует.
 *
 * areaServed не выводится намеренно: «Хабаровск» живёт в заголовках услуг
 * и в адресе, зашитом в разметку подвала, но как данные его нет. Появится
 * поле — появится и свойство.
 *
 * @param array<int, array<string, mixed>> $graph   Граф.
 * @param object                           $context Контекст.
 *
 * @return array<int, array<string, mixed>>
 */
function razil_schema_add_service( array $graph, $context ): array {
	if ( ! is_singular( RAZIL_SCHEMA_SERVICE_TYPES ) ) {
		return $graph;
	}

	$post = $context->post ?? null;
	if ( ! $post instanceof WP_Post ) {
		return $graph;
	}

	$name = razil_schema_tidy( (string) $post->post_title );
	if ( '' === $name ) {
		return $graph;
	}

	$canonical = (string) ( $context->canonical ?? '' );
	if ( '' === $canonical ) {
		return $graph;
	}

	$node = array(
		'@type' => 'Service',
		'@id'   => $canonical . '#service',
		'name'  => $name,
	);

	// Описание — только если оно есть. Отрывок услуги заполнен вручную
	// и служит же описанием в Open Graph.
	$description = razil_schema_tidy( (string) $post->post_excerpt );
	if ( '' !== $description ) {
		$node['description'] = $description;
	}

	/*
	 * Исполнитель — ссылка на узел организации, который Yoast уже построил.
	 * Идентификатор берётся из самого графа, а не собирается из строк:
	 * так мы следуем за соглашением плагина, а если организации в графе нет
	 * (сайт может быть представлен человеком), свойство просто не выводится.
	 */
	$org_key = razil_schema_find( $graph, 'Organization' );
	if ( null !== $org_key && ! empty( $graph[ $org_key ]['@id'] ) ) {
		$node['provider'] = array( '@id' => $graph[ $org_key ]['@id'] );
	}

	$node['mainEntityOfPage'] = array( '@id' => $canonical );
	$node['inLanguage']       = get_bloginfo( 'language' );

	$graph[] = $node;

	return $graph;
}
