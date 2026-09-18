<?php
/**
 * Загрузка двух фотографий первого экрана в медиатеку.
 *
 * WP-CLI на проекте нет, поэтому standalone-скрипт через wp-load.php.
 *
 * Запуск:
 *   php _scripts/import-hero-photos.php          — разведка, в БД ничего не пишется
 *   php _scripts/import-hero-photos.php dry      — то же самое явно
 *   php _scripts/import-hero-photos.php apply    — копирование и запись
 *
 * Отчёт пишется в _scripts/import-hero-photos.<режим>.txt в UTF-8 без BOM:
 * консоль Windows кириллицу в кодовой странице 866 показывает мусором,
 * а файл читается как есть.
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 'Только из командной строки.' );
}

$mode = isset( $argv[1] ) ? strtolower( trim( $argv[1] ) ) : 'dry';

if ( ! in_array( $mode, array( 'dry', 'apply' ), true ) ) {
	fwrite( STDERR, "Режим: dry или apply.\n" );
	exit( 2 );
}

$root        = str_replace( '\\', '/', dirname( __DIR__ ) );
$source_dir  = $root . '/_import/media';
$report_file = str_replace( '\\', '/', __DIR__ ) . '/import-hero-photos.' . $mode . '.txt';

/* ------------------------------------------------------------------ */
/* Что именно грузим. Всё остальное в _import/media не трогаем.        */
/* ------------------------------------------------------------------ */

$targets = array(
	'ofis-ulica-panorama.webp' => array(
		'title' => 'Офис «Земля и Люди», вид с улицы — панорама',
		'alt'   => 'Здание офиса ритуального агентства «Земля и Люди» на переулке Казарменном, 9 в Хабаровске — вид с улицы',
	),
	'ofis-ulica-mobile.webp' => array(
		'title' => 'Офис «Земля и Люди», вид с улицы — мобильный кадр',
		'alt'   => 'Здание офиса ритуального агентства «Земля и Люди» в Хабаровске — вид с улицы',
	),
);

/* ------------------------------------------------------------------ */
/* Отчёт                                                               */
/* ------------------------------------------------------------------ */

$lines = array();

function rep( $text = '' ) {
	global $lines;
	$lines[] = $text;
}

function flush_report( $exit_code ) {
	global $lines, $report_file;

	file_put_contents( $report_file, implode( "\n", $lines ) . "\n" );

	// В консоль — только путь: кириллица там всё равно не читается.
	fwrite( STDOUT, 'Отчёт: ' . $report_file . "\n" );
	fwrite( STDOUT, 'Код выхода: ' . $exit_code . "\n" );

	exit( $exit_code );
}

function bytes( $n ) {
	return number_format( (int) $n, 0, ',', ' ' ) . ' Б';
}

/* ------------------------------------------------------------------ */
/* Загрузка WordPress                                                  */
/* ------------------------------------------------------------------ */

// wp-load ожидает заполненный $_SERVER: без хоста ядро строит адреса
// от пустой строки, и guid вложения получился бы битым.
$_SERVER['HTTP_HOST']       = 'ra-zil.test';
$_SERVER['SERVER_NAME']     = 'ra-zil.test';
$_SERVER['REQUEST_URI']     = '/';
$_SERVER['REQUEST_METHOD']  = 'GET';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';

define( 'WP_USE_THEMES', false );

require_once $root . '/wp-load.php';

rep( '================================================================' );
rep( 'Загрузка фотографий первого экрана — режим: ' . strtoupper( $mode ) );
rep( 'Время: ' . date( 'Y-m-d H:i:s' ) );
rep( 'Сайт: ' . home_url() . '   |   WordPress ' . get_bloginfo( 'version' ) );
rep( '================================================================' );
rep();

/* ------------------------------------------------------------------ */
/* 1. Полный список файлов в папке-источнике                           */
/* ------------------------------------------------------------------ */

rep( '--- 1. Содержимое ' . $source_dir . ' ---' );
rep();

if ( ! is_dir( $source_dir ) ) {
	rep( 'ОШИБКА: папки-источника нет. Остановка, в БД ничего не записано.' );
	flush_report( 1 );
}

$all = array_values(
	array_filter(
		scandir( $source_dir ),
		static function ( $f ) use ( $source_dir ) {
			return '.' !== $f && '..' !== $f && is_file( $source_dir . '/' . $f );
		}
	)
);

sort( $all, SORT_STRING );

$name_width = 0;

foreach ( $all as $f ) {
	$name_width = max( $name_width, strlen( $f ) );
}

foreach ( $all as $f ) {
	rep( sprintf(
		'  %-' . $name_width . 's  %16s%s',
		$f,
		bytes( filesize( $source_dir . '/' . $f ) ),
		isset( $targets[ $f ] ) ? '   <== целевой' : ''
	) );
}

rep();
rep( '  Всего файлов: ' . count( $all ) );
rep();

/* ------------------------------------------------------------------ */
/* 2. Разбор двух целевых файлов                                       */
/* ------------------------------------------------------------------ */

rep( '--- 2. Целевые файлы ---' );
rep();

$missing = array();
$info    = array();

foreach ( $targets as $name => $meta ) {
	$path = $source_dir . '/' . $name;

	rep( '  ' . $name );

	if ( ! is_file( $path ) ) {
		rep( '    НЕТ ФАЙЛА по пути ' . $path );
		rep();
		$missing[] = $name;
		continue;
	}

	$dims  = @getimagesize( $path );
	$finfo = finfo_open( FILEINFO_MIME_TYPE );
	$mime  = $finfo ? finfo_file( $finfo, $path ) : '(fileinfo недоступен)';

	if ( $finfo ) {
		finfo_close( $finfo );
	}

	// Ядро проверяет расширение отдельно от содержимого: если они разойдутся,
	// wp_insert_attachment примет файл, а wp_generate_attachment_metadata
	// не сделает под-размеры. Поэтому сверяем обе стороны здесь.
	$checked = wp_check_filetype_and_ext( $path, $name );

	rep( '    путь        : ' . $path );
	rep( '    размер      : ' . bytes( filesize( $path ) ) );
	rep( '    пиксели     : ' . ( $dims ? $dims[0] . ' x ' . $dims[1] : '(getimagesize не распознал)' ) );
	rep( '    MIME (finfo): ' . $mime );
	rep( '    MIME (WP)   : ' . ( $checked['type'] ? $checked['type'] : '(WP не признал тип)' ) );
	rep( '    расширение  : ' . ( $checked['ext'] ? $checked['ext'] : '(нет)' ) );

	if ( $checked['type'] && $mime !== $checked['type'] ) {
		rep( '    ВНИМАНИЕ: реальный MIME и тип по расширению разошлись.' );
	}

	rep( '    заголовок   : ' . $meta['title'] );
	rep( '    alt         : ' . $meta['alt'] );
	rep();

	$info[ $name ] = array(
		'path' => $path,
		'mime' => $checked['type'] ? $checked['type'] : $mime,
	);
}

/* ------------------------------------------------------------------ */
/* 3. Совпадения в медиатеке по имени файла                            */
/* ------------------------------------------------------------------ */

rep( '--- 3. Проверка медиатеки на такие же имена файлов ---' );
rep();

global $wpdb;

$collisions = array();
$upload     = wp_upload_dir();

rep( '  Каталог загрузок этого месяца: ' . $upload['path'] );
rep( '  Базовый URL                  : ' . $upload['url'] );

if ( ! empty( $upload['error'] ) ) {
	rep( '  ОШИБКА каталога загрузок: ' . $upload['error'] );
}

rep();

foreach ( array_keys( $targets ) as $name ) {
	rep( '  ' . $name );

	// _wp_attached_file хранится как «2026/09/имя.webp» либо просто «имя.webp»
	// для загрузок без разбивки по месяцам — ищем оба вида.
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT pm.post_id, pm.meta_value, p.post_title, p.post_date, p.post_mime_type
			   FROM {$wpdb->postmeta} pm
			   JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			  WHERE pm.meta_key = '_wp_attached_file'
			    AND ( pm.meta_value = %s OR pm.meta_value LIKE %s )
			  ORDER BY pm.post_id",
			$name,
			'%/' . $wpdb->esc_like( $name )
		)
	);

	if ( $rows ) {
		foreach ( $rows as $r ) {
			rep( sprintf(
				'    СОВПАДЕНИЕ: вложение ID %d, _wp_attached_file = %s, «%s», %s, %s',
				$r->post_id,
				$r->meta_value,
				$r->post_title,
				$r->post_mime_type,
				$r->post_date
			) );

			$collisions[] = $name;
		}
	} else {
		rep( '    в медиатеке нет' );
	}

	// Отдельно — физический файл в каталоге этого месяца. Вложения может
	// не быть, а файл лежать: тогда copy() затёр бы чужой файл.
	$dest = $upload['path'] . '/' . $name;

	if ( file_exists( $dest ) ) {
		rep( '    ФАЙЛ УЖЕ ЛЕЖИТ на диске: ' . $dest . ' (' . bytes( filesize( $dest ) ) . ')' );
		$collisions[] = $name;
	} else {
		rep( '    файла на диске нет: ' . $dest );
	}

	rep();
}

/* ------------------------------------------------------------------ */
/* Итог разведки                                                       */
/* ------------------------------------------------------------------ */

if ( $missing ) {
	rep( '================================================================' );
	rep( 'ОСТАНОВКА: не найдены файлы — ' . implode( ', ', $missing ) );
	rep( 'В базу данных ничего не записано.' );
	rep( '================================================================' );
	flush_report( 1 );
}

if ( 'dry' === $mode ) {
	rep( '================================================================' );
	rep( 'Разведка завершена. В базу данных ничего не записано.' );

	if ( $collisions ) {
		rep( 'Есть совпадения по именам (раздел 3) — apply остановится.' );
	} else {
		rep( 'Совпадений нет, путь для apply свободен.' );
	}

	rep( '================================================================' );
	flush_report( 0 );
}

/* ================================================================== */
/* РЕЖИМ APPLY                                                         */
/* ================================================================== */

if ( $collisions ) {
	rep( '================================================================' );
	rep( 'ОСТАНОВКА: совпадения по именам — ' . implode( ', ', array_unique( $collisions ) ) );
	rep( 'Переименовывать в «-1» молча не стану: решение за человеком.' );
	rep( 'В базу данных ничего не записано.' );
	rep( '================================================================' );
	flush_report( 1 );
}

require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';

rep( '--- 4. Запись ---' );
rep();

$created = array();

foreach ( $targets as $name => $meta ) {
	rep( '  ' . $name );

	$dest = $upload['path'] . '/' . $name;

	if ( ! copy( $info[ $name ]['path'], $dest ) ) {
		rep( '    ОШИБКА копирования в ' . $dest );
		rep();
		continue;
	}

	rep( '    скопирован в ' . $dest . ' (' . bytes( filesize( $dest ) ) . ')' );

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => $info[ $name ]['mime'],
			'post_title'     => $meta['title'],
			'post_content'   => '',
			'post_status'    => 'inherit',
			'guid'           => $upload['url'] . '/' . $name,
		),
		$dest,
		0,
		true
	);

	if ( is_wp_error( $attachment_id ) ) {
		rep( '    ОШИБКА wp_insert_attachment: ' . $attachment_id->get_error_message() );
		rep();
		continue;
	}

	rep( '    wp_insert_attachment -> ID ' . $attachment_id );

	$metadata = wp_generate_attachment_metadata( $attachment_id, $dest );

	if ( empty( $metadata ) ) {
		rep( '    ВНИМАНИЕ: wp_generate_attachment_metadata вернула пусто' );
	}

	wp_update_attachment_metadata( $attachment_id, $metadata );
	rep( '    метаданные записаны, под-размеров: ' . ( isset( $metadata['sizes'] ) ? count( $metadata['sizes'] ) : 0 ) );

	update_post_meta( $attachment_id, '_wp_attachment_image_alt', $meta['alt'] );
	rep( '    alt записан' );
	rep();

	$created[ $name ] = $attachment_id;
}

/* ------------------------------------------------------------------ */
/* 5. Перечитывание из базы                                            */
/* ------------------------------------------------------------------ */

rep( '--- 5. Состояние из базы после записи ---' );
rep();

// Кэш объектов держит то, что мы только что положили сами. Сбрасываем,
// чтобы отчёт показывал именно строки таблиц, а не память процесса.
wp_cache_flush();

foreach ( $created as $name => $id ) {
	clean_post_cache( $id );

	$post = get_post( $id );

	if ( ! $post ) {
		rep( '  ' . $name . ': вложение ID ' . $id . ' в базе НЕ НАЙДЕНО' );
		rep();
		continue;
	}

	$file = get_post_meta( $id, '_wp_attached_file', true );
	$md   = wp_get_attachment_metadata( $id );
	$alt  = get_post_meta( $id, '_wp_attachment_image_alt', true );
	$abs  = get_attached_file( $id );

	rep( '  ' . $name );
	rep( '    ID                : ' . $post->ID );
	rep( '    post_title        : ' . $post->post_title );
	rep( '    post_type/status  : ' . $post->post_type . ' / ' . $post->post_status );
	rep( '    post_mime_type    : ' . $post->post_mime_type );
	rep( '    _wp_attached_file : ' . $file );
	rep( '    полный URL        : ' . wp_get_attachment_url( $id ) );
	rep( '    путь на диске     : ' . $abs . ( file_exists( $abs ) ? ' (есть, ' . bytes( filesize( $abs ) ) . ')' : ' (ФАЙЛА НЕТ)' ) );
	rep( '    оригинал          : ' . ( isset( $md['width'] ) ? $md['width'] . ' x ' . $md['height'] : '(нет в метаданных)' ) );
	rep( '    alt               : ' . ( '' === $alt ? '(пусто)' : $alt ) );

	if ( ! empty( $md['sizes'] ) ) {
		rep( '    под-размеры (' . count( $md['sizes'] ) . '):' );

		$dir = dirname( $abs );

		foreach ( $md['sizes'] as $slug => $s ) {
			rep( sprintf(
				'      %-16s %-34s %5d x %-5d  %-12s %s',
				$slug,
				$s['file'],
				$s['width'],
				$s['height'],
				$s['mime-type'],
				file_exists( $dir . '/' . $s['file'] ) ? 'файл есть' : 'ФАЙЛА НЕТ'
			) );
		}
	} else {
		rep( '    под-размеры       : нет' );
	}

	rep();
}

rep( '================================================================' );
rep( 'Готово. Создано вложений: ' . count( $created ) . ' из ' . count( $targets ) );
rep( 'Вложение 100 не затрагивалось.' );
rep( '================================================================' );

flush_report( count( $created ) === count( $targets ) ? 0 : 1 );
