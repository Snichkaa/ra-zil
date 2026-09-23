<?php
/**
 * Безопасная запись содержимого записи с полным циклом проверки.
 *
 * Порядок ровно такой, какого требует задание:
 *   1. пробный прогон — круговорот serialize_blocks(parse_blocks($c));
 *   2. запись через wp_slash() при снятых фильтрах kses;
 *   3. чтение обратно из базы и побайтовое сравнение.
 *
 * Пробный прогон обязателен и не пропускается: если кандидат не
 * переживает разбор и сборку, редактор сайта при первом же открытии
 * перепишет разметку по-своему, и расхождение всплывёт позже и тише.
 *
 * Запуск:
 *   php _tools/redesign/write-post.php <id> <файл> --dry-run
 *   php _tools/redesign/write-post.php <id> <файл>
 */

require dirname( __DIR__, 2 ) . '/wp-load.php';

$args    = array_slice( $argv, 1 );
$dry_run = false;

foreach ( $args as $k => $arg ) {
	if ( '--dry-run' === $arg ) {
		$dry_run = true;
		unset( $args[ $k ] );
	}
}
$args = array_values( $args );

if ( count( $args ) < 2 ) {
	fwrite( STDERR, "Запуск: php write-post.php <id> <файл> [--dry-run]\n" );
	exit( 2 );
}

$post_id = (int) $args[0];
$file    = $args[1];

$post = get_post( $post_id );
if ( ! $post ) {
	fwrite( STDERR, "Записи #{$post_id} нет\n" );
	exit( 2 );
}
if ( ! is_file( $file ) ) {
	fwrite( STDERR, "Файла {$file} нет\n" );
	exit( 2 );
}

$candidate = file_get_contents( $file );
$current   = $post->post_content;

printf( "=== Запись #%d [%s] %s\n", $post->ID, $post->post_type, $post->post_title );
printf( "    файл кандидата: %s (%d байт)\n", $file, strlen( $candidate ) );
printf( "    сейчас в базе:  %d байт, md5=%s\n", strlen( $current ), md5( $current ) );

// --- Шаг 1. Пробный прогон: круговорот блоков.

$roundtrip = serialize_blocks( parse_blocks( $candidate ) );

if ( $roundtrip !== $candidate ) {
	$len = min( strlen( $candidate ), strlen( $roundtrip ) );
	$at  = $len;
	for ( $i = 0; $i < $len; $i++ ) {
		if ( $candidate[ $i ] !== $roundtrip[ $i ] ) {
			$at = $i;
			break;
		}
	}
	printf( "ПРОБНЫЙ ПРОГОН: УПАЛ — круговорот не сходится на байте %d\n", $at );
	printf( "    было:  %s\n", rz_snippet( $candidate, $at ) );
	printf( "    стало: %s\n", rz_snippet( $roundtrip, $at ) );
	printf( "ЗАПИСЬ НЕ ВЫПОЛНЕНА\n" );
	exit( 1 );
}

printf( "ПРОБНЫЙ ПРОГОН: ПРОШЁЛ — круговорот побайтово сошёлся (%d байт)\n", strlen( $candidate ) );

// Сводка по тексту: сколько предложений появилось и исчезло. Это не
// заменяет общий снимок текста, но ловит грубую потерю прямо на месте.
$before_set = rz_sentence_set( $current );
$after_set  = rz_sentence_set( $candidate );
$lost       = array_diff( $before_set, $after_set );
$added      = array_diff( $after_set, $before_set );

printf( "ТЕКСТ: предложений было %d, стало %d; пропало %d, появилось %d\n", count( $before_set ), count( $after_set ), count( $lost ), count( $added ) );
foreach ( $lost as $s ) {
	printf( "    ПРОПАЛО:   %s\n", $s );
}
foreach ( $added as $s ) {
	printf( "    ПОЯВИЛОСЬ: %s\n", $s );
}

if ( $dry_run ) {
	printf( "ПРОБНЫЙ ПРОГОН ЗАВЕРШЁН, в базу ничего не записано\n" );
	exit( 0 );
}

// --- Шаг 2. Запись.

// Фильтры kses режут разметку у пользователя без права unfiltered_html,
// а в командной строке текущего пользователя нет вовсе. Снимаются и
// остальные обработчики content_save_pre: среди них замена эмодзи,
// балансировка тегов и подстановка rel у ссылок — каждый способен
// переписать разметку молча, и тогда сравнение ниже покажет расхождение
// не по нашей вине, но откатывать пришлось бы всё равно.
kses_remove_filters();
remove_all_filters( 'content_save_pre' );
remove_all_filters( 'content_filtered_save_pre' );
remove_all_filters( 'wp_insert_post_data' );

$result = wp_update_post(
	array(
		'ID'           => $post_id,
		// wp_update_post ждёт экранированные данные и снимает слеши сам.
		// Без wp_slash() каждая кавычка в JSON атрибутов блока потеряла бы
		// обратный слеш, и разметка развалилась бы на первой же записи.
		'post_content' => wp_slash( $candidate ),
	),
	true
);

if ( is_wp_error( $result ) ) {
	printf( "ЗАПИСЬ: ОШИБКА — %s\n", $result->get_error_message() );
	exit( 1 );
}

// --- Шаг 3. Чтение обратно и побайтовое сравнение.

// Кэш записи чистится принудительно: get_post отдал бы объект из
// памяти процесса, и сравнение проверило бы переменную саму с собой.
clean_post_cache( $post_id );
wp_cache_delete( $post_id, 'posts' );

global $wpdb;
$stored = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id ) );

if ( $stored === $candidate ) {
	printf( "ЧТЕНИЕ ОБРАТНО: СОВПАЛО (%d байт, md5=%s)\n", strlen( $stored ), md5( $stored ) );

	// Слаг проверяется отдельно и после записи: wp_update_post умеет
	// пересчитать post_name сам, если заголовок или статус меняются,
	// а адрес страницы — SEO-поле, которое трогать запрещено.
	$slug_now = $wpdb->get_var( $wpdb->prepare( "SELECT post_name FROM {$wpdb->posts} WHERE ID = %d", $post_id ) );
	if ( $slug_now === $post->post_name ) {
		printf( "СЛАГ: не изменился (%s)\n", $slug_now );
		exit( 0 );
	}

	printf( "СЛАГ: ИЗМЕНИЛСЯ — был %s, стал %s\n", $post->post_name, (string) $slug_now );
	printf( "ТРЕБУЕТСЯ НЕМЕДЛЕННЫЙ ОТКАТ: php _tools/redesign/restore-post.php %d\n", $post_id );
	exit( 1 );
}

$len = min( strlen( $candidate ), strlen( (string) $stored ) );
$at  = $len;
for ( $i = 0; $i < $len; $i++ ) {
	if ( $candidate[ $i ] !== $stored[ $i ] ) {
		$at = $i;
		break;
	}
}

printf( "ЧТЕНИЕ ОБРАТНО: РАСХОЖДЕНИЕ\n" );
printf( "    отправляли %d байт (md5=%s)\n", strlen( $candidate ), md5( $candidate ) );
printf( "    в базе     %d байт (md5=%s)\n", strlen( (string) $stored ), md5( (string) $stored ) );
printf( "    первое расхождение на байте %d\n", $at );
printf( "    отправляли: %s\n", rz_snippet( $candidate, $at ) );
printf( "    в базе:     %s\n", rz_snippet( (string) $stored, $at ) );
printf( "ТРЕБУЕТСЯ НЕМЕДЛЕННЫЙ ОТКАТ: php _tools/redesign/restore-post.php %d\n", $post_id );
exit( 1 );

/** Окрестность байта в печатаемом виде. */
function rz_snippet( $s, $at ) {
	$from = max( 0, $at - 40 );
	$part = substr( $s, $from, 120 );
	return str_replace( array( "\r", "\n", "\t" ), array( '\r', '\n', '\t' ), $part );
}

/** Множество предложений видимого текста — для сводки о потерях. */
function rz_sentence_set( $html ) {
	$html = preg_replace( '/<!--.*?-->/s', ' ', (string) $html );
	$html = preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html );
	$html = preg_replace( '#</(p|div|li|h[1-6]|td|th|tr|section|article|figcaption|dt|dd|summary|details|blockquote)>#i', "\n", $html );
	$html = preg_replace( '#<br\s*/?>#i', "\n", $html );

	$text = wp_strip_all_tags( $html );
	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = str_replace( array( "\xC2\xA0", "\xE2\x80\xAF" ), ' ', $text );

	$out = array();
	foreach ( (array) preg_split( '/(?<=[.!?])\s+|\R+/u', $text ) as $part ) {
		$part = trim( preg_replace( '/\s+/u', ' ', $part ) );
		if ( '' !== $part ) {
			$out[] = $part;
		}
	}

	return array_values( array_unique( $out ) );
}
