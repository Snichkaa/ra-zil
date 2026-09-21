<?php
/**
 * Снимок всего видимого текста сайта.
 *
 * Источники: файлы шаблонов темы (templates/*.html, parts/*.html) и
 * post_content всех записей и страниц. Разметка снимается, пробелы
 * нормализуются, текст режется на предложения, предложения сортируются.
 *
 * Сортировка нужна затем, что перестановка блоков — разрешённая операция
 * редизайна, а дописывание и потеря текста — запрещённые. Сравнивать
 * надо множества предложений, а не их порядок, поэтому снимок сразу
 * приводится к каноническому виду.
 *
 * Запуск: php _tools/redesign/snapshot-text.php > _reports/text-before.txt
 */

// Скрипт лежит на два уровня ниже корня сайта.
require dirname( __DIR__, 2 ) . '/wp-load.php';

/**
 * Разметка -> чистый текст.
 *
 * Комментарии блоков, script и style выбрасываются целиком: их
 * содержимое не видно посетителю, но состоит из слов и попало бы в
 * снимок как ложные предложения.
 */
function rz_visible_text( $html ) {
	$html = (string) $html;

	// Комментарии HTML, а значит и разделители блоков Гутенберга.
	$html = preg_replace( '/<!--.*?-->/s', ' ', $html );
	// Содержимое script и style вместе с тегами.
	$html = preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html );
	// Блочные теги превращаются в разрыв, чтобы соседние строки
	// не склеились в одно слово при снятии разметки.
	$html = preg_replace( '#</(p|div|li|h[1-6]|td|th|tr|section|article|figcaption|dt|dd|summary|details|blockquote)>#i', "\n", $html );
	$html = preg_replace( '#<br\s*/?>#i', "\n", $html );

	$text = wp_strip_all_tags( $html );
	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

	// Неразрывный пробел U+00A0 и узкий неразрывный U+202F — обычными
	// пробелами: в разметке они встречаются в номерах и ценах, и без
	// нормализации одно и то же предложение считалось бы разным.
	$text = str_replace( array( "\xC2\xA0", "\xE2\x80\xAF" ), ' ', $text );

	return $text;
}

/**
 * Текст -> отсортированный список предложений.
 *
 * Границей считается точка, восклицательный или вопросительный знак,
 * а также перевод строки: в шаблонах много коротких фрагментов без
 * финальной точки — заголовки, подписи, пункты списков.
 */
function rz_sentences( $text ) {
	$parts = preg_split( '/(?<=[.!?])\s+|\R+/u', $text );
	$out   = array();

	foreach ( (array) $parts as $part ) {
		$part = preg_replace( '/\s+/u', ' ', $part );
		$part = trim( $part );
		if ( '' === $part ) {
			continue;
		}
		$out[] = $part;
	}

	sort( $out, SORT_STRING );

	return $out;
}

/** Вывод одной группы снимка. */
function rz_dump_group( $title, $source, $content ) {
	$sentences = rz_sentences( rz_visible_text( $content ) );

	echo '===== ' . $title . "\n";
	echo '----- источник: ' . $source . "\n";
	echo '----- предложений: ' . count( $sentences ) . "\n";
	foreach ( $sentences as $s ) {
		echo $s . "\n";
	}
	echo "\n";
}

$theme_dir = get_stylesheet_directory();

$files = array();
// patterns/ намеренно не сканируется. Паттерн — это скелет для
// редактора: он лежит в теме, но ни на одной странице сайта
// не отображается, пока его не вставили. Его рыба («Вводный
// текст услуги.», «Пункт состава.», «Вопрос.», «Ответ.»)
// попадала в снимок и засоряла базу сравнения, маскируя
// настоящие расхождения в содержимом страниц.
foreach ( array( 'templates', 'parts' ) as $sub ) {
	$dir = $theme_dir . '/' . $sub;
	if ( ! is_dir( $dir ) ) {
		continue;
	}
	foreach ( glob( $dir . '/*.{html,php}', GLOB_BRACE ) as $file ) {
		$files[] = $file;
	}
}
sort( $files, SORT_STRING );

foreach ( $files as $file ) {
	$rel = str_replace( '\\', '/', substr( $file, strlen( $theme_dir ) + 1 ) );
	rz_dump_group( 'ФАЙЛ ' . $rel, $file, file_get_contents( $file ) );
}

// Записи всех публичных типов плюс черновики и приватные: снимок
// сравнивается сам с собой до и после, и пропуск неопубликованных
// записей скрыл бы правку, которая их задела.
$types = get_post_types( array( 'public' => true ), 'names' );
$types = array_values( array_diff( $types, array( 'attachment' ) ) );
sort( $types, SORT_STRING );

$posts = get_posts(
	array(
		'post_type'        => $types,
		'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future' ),
		'numberposts'      => -1,
		'orderby'          => 'ID',
		'order'            => 'ASC',
		'suppress_filters' => true,
	)
);

foreach ( $posts as $post ) {
	rz_dump_group(
		sprintf( 'ЗАПИСЬ %d [%s] %s', $post->ID, $post->post_type, $post->post_title ),
		'post_content #' . $post->ID,
		$post->post_content
	);
}

echo '===== ИТОГО групп: ' . ( count( $files ) + count( $posts ) ) . "\n";
