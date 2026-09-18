<?php
/**
 * Разведка по слову «агент». Только чтение.
 */
require dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb;

$root = dirname( __DIR__, 2 );
$rel  = function ( $p ) use ( $root ) {
	return str_replace( str_replace( chr( 92 ), '/', $root ) . '/', '', str_replace( chr( 92 ), '/', $p ) );
};

function rd( $p ) { $fh = fopen( $p, 'rb' ); $b = stream_get_contents( $fh ); fclose( $fh ); return $b; }

/** Все файлы темы и плагина по расширениям. */
function scan( $dir, array $exts ) {
	$out = array();
	if ( ! is_dir( $dir ) ) { return $out; }
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		if ( $f->isFile() && in_array( strtolower( $f->getExtension() ), $exts, true ) ) { $out[] = $f->getPathname(); }
	}
	sort( $out );
	return $out;
}

echo "##################################################################\n";
echo "# 1.1. КНОПКА «Вызвать агента»\n";
echo "##################################################################\n\n";

echo "--- поиск по всем файлам темы (parts/, templates/, любые .html/.php) ---\n";
$theme_files = scan( $root . '/wp-content/themes/razil', array( 'html', 'php' ) );
printf( "просмотрено файлов темы: %d\n", count( $theme_files ) );
$found_btn = array();
foreach ( $theme_files as $p ) {
	$b = rd( $p );
	if ( false === mb_stripos( $b, 'Вызвать' ) ) { continue; }
	$lines = preg_split( '/\r\n|\n|\r/', $b );
	foreach ( $lines as $i => $l ) {
		if ( false !== mb_stripos( $l, 'Вызвать' ) ) {
			$found_btn[] = array( 'file' => $p, 'line' => $i + 1, 'lines' => $lines, 'idx' => $i );
		}
	}
}
printf( "файлов темы с текстом «Вызвать»: %d вхождений\n\n", count( $found_btn ) );
foreach ( $found_btn as $h ) {
	echo str_repeat( '-', 78 ) . "\n";
	printf( "%s, строка %d\n", $rel( $h['file'] ), $h['line'] );
	for ( $i = max( 0, $h['idx'] - 3 ); $i <= min( count( $h['lines'] ) - 1, $h['idx'] + 3 ); $i++ ) {
		printf( "%s%4d | %s\n", $i === $h['idx'] ? '>>' : '  ', $i + 1, $h['lines'][ $i ] );
	}
}

echo "\n--- поиск по PHP-файлам razil-core ---\n";
$plug_files = scan( $root . '/wp-content/plugins/razil-core', array( 'php' ) );
printf( "просмотрено PHP-файлов плагина: %d\n", count( $plug_files ) );
$hits = 0;
foreach ( $plug_files as $p ) {
	$b = rd( $p );
	if ( false === mb_stripos( $b, 'Вызвать' ) ) { continue; }
	$lines = preg_split( '/\r\n|\n|\r/', $b );
	foreach ( $lines as $i => $l ) {
		if ( false !== mb_stripos( $l, 'Вызвать' ) ) {
			$hits++;
			echo str_repeat( '-', 78 ) . "\n";
			printf( "%s, строка %d\n", $rel( $p ), $i + 1 );
			for ( $k = max( 0, $i - 3 ); $k <= min( count( $lines ) - 1, $i + 3 ); $k++ ) {
				printf( "%s%4d | %s\n", $k === $i ? '>>' : '  ', $k + 1, $lines[ $k ] );
			}
		}
	}
}
if ( 0 === $hits ) { echo "(в PHP плагина текста «Вызвать» нет)\n"; }

echo "\n--- шорткод razil_callback_button: регистрация и вывод ---\n";
foreach ( $plug_files as $p ) {
	$b = rd( $p );
	if ( false === strpos( $b, 'razil_callback_button' ) ) { continue; }
	$lines = preg_split( '/\r\n|\n|\r/', $b );
	foreach ( $lines as $i => $l ) {
		if ( false !== strpos( $l, 'razil_callback_button' ) ) {
			printf( "%s:%d | %s\n", $rel( $p ), $i + 1, trim( $l ) );
		}
	}
}
printf( "\nshortcode_exists('razil_callback_button') = %s\n", var_export( shortcode_exists( 'razil_callback_button' ), true ) );
if ( shortcode_exists( 'razil_callback_button' ) ) {
	global $shortcode_tags;
	$cb = $shortcode_tags['razil_callback_button'];
	$name = is_array( $cb ) ? ( ( is_object( $cb[0] ) ? get_class( $cb[0] ) : $cb[0] ) . '::' . $cb[1] ) : ( is_string( $cb ) ? $cb : 'Closure' );
	echo "callback = {$name}\n";
	try {
		$r = is_string( $cb ) ? new ReflectionFunction( $cb ) : new ReflectionFunction( $cb );
		printf( "объявлен в %s:%d\n", $rel( $r->getFileName() ), $r->getStartLine() );
		echo "--- тело функции ---\n";
		$src = file( $r->getFileName() );
		for ( $i = $r->getStartLine() - 1; $i < $r->getEndLine(); $i++ ) {
			printf( "%4d | %s", $i + 1, $src[ $i ] );
		}
	} catch ( Throwable $e ) { echo 'reflection: ' . $e->getMessage() . "\n"; }
}

echo "\n--- все вызовы шорткода razil_callback_button с параметрами ---\n";
foreach ( scan( $root . '/wp-content/themes/razil', array( 'html', 'php' ) ) as $p ) {
	$b = rd( $p );
	if ( false === strpos( $b, 'razil_callback_button' ) ) { continue; }
	$lines = preg_split( '/\r\n|\n|\r/', $b );
	foreach ( $lines as $i => $l ) {
		if ( false !== strpos( $l, 'razil_callback_button' ) ) {
			printf( "ФАЙЛ %s:%d | %s\n", $rel( $p ), $i + 1, trim( $l ) );
		}
	}
}
$db_sc = $wpdb->get_results( "SELECT ID, post_type, post_title, post_content FROM {$wpdb->posts} WHERE post_type <> 'revision' AND post_content LIKE '%razil_callback_button%'" );
foreach ( $db_sc as $r ) {
	preg_match_all( '/\[razil_callback_button[^\]]*\]/u', $r->post_content, $m );
	printf( "БД ID=%s (%s) «%s»: %s\n", $r->ID, $r->post_type, $r->post_title, implode( ' | ', $m[0] ) );
}
if ( ! $db_sc ) { echo "(в контенте БД вызовов шорткода нет)\n"; }

echo "\n--- wp_options и postmeta: не задан ли текст кнопки настройкой ---\n";
foreach ( array( 'Вызвать', 'агент' ) as $needle ) {
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_id, option_name, LENGTH(option_value) len FROM {$wpdb->options} WHERE option_value LIKE %s", '%' . $wpdb->esc_like( $needle ) . '%' ) );
	printf( "wp_options со значением, содержащим «%s»: %d\n", $needle, count( $rows ) );
	foreach ( $rows as $r ) { printf( "   option_name=%s (len=%s)\n", $r->option_name, $r->len ); }
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, post_id, meta_key FROM {$wpdb->postmeta} WHERE meta_value LIKE %s", '%' . $wpdb->esc_like( $needle ) . '%' ) );
	printf( "postmeta со значением, содержащим «%s»: %d\n", $needle, count( $rows ) );
	foreach ( $rows as $r ) { printf( "   post_id=%s meta_key=%s\n", $r->post_id, $r->meta_key ); }
}

echo "\n\n##################################################################\n";
echo "# 1.2. ПОЛНЫЙ АУДИТ СЛОВА «агент»\n";
echo "##################################################################\n";

$RE = '/агент\p{Cyrillic}*/iu';

/** Разбить на предложения и вернуть то, где сидит позиция. */
function sentence_at( $text, $pos ) {
	$plain = $text;
	$parts = preg_split( '/(?<=[.!?…])\s+/u', $plain, -1, PREG_SPLIT_OFFSET_CAPTURE );
	$best  = $plain;
	foreach ( $parts as $p ) {
		if ( $p[1] <= $pos ) { $best = $p[0]; } else { break; }
	}
	return trim( preg_replace( '/\s+/u', ' ', $best ) );
}

function report_text( $text, $src ) {
	$plain = wp_strip_all_tags( preg_replace( '/<!--.*?-->/us', ' ', $text ) );
	$plain = html_entity_decode( $plain, ENT_QUOTES, 'UTF-8' );
	if ( ! preg_match_all( '/агент\p{Cyrillic}*/iu', $plain, $m, PREG_OFFSET_CAPTURE ) ) { return 0; }
	foreach ( $m[0] as $hit ) {
		$form = $hit[0];
		$is_agentstvo = ( 0 === mb_stripos( $form, 'агентств' ) );
		printf( "  источник: %s\n", $src );
		printf( "  словоформа: «%s»%s\n", $form, $is_agentstvo ? '   <-- АГЕНТСТВО, род деятельности, НЕ ТРОГАЕМ' : '' );
		printf( "  предложение: %s\n\n", sentence_at( $plain, $hit[1] ) );
	}
	return count( $m[0] );
}

echo "\n--- 1.2a. БД: post_content и post_excerpt, опубликованные, без ревизий ---\n";
$rows = $wpdb->get_results(
	"SELECT ID, post_type, post_status, post_title, post_content, post_excerpt
	   FROM {$wpdb->posts}
	  WHERE post_type <> 'revision' AND post_status = 'publish'
	  ORDER BY post_type, ID"
);
printf( "просмотрено записей: %d\n\n", count( $rows ) );
$db_total = 0;
foreach ( $rows as $r ) {
	$db_total += report_text( $r->post_content, "ID={$r->ID} ({$r->post_type}) поле post_content — «{$r->post_title}»" );
	$db_total += report_text( $r->post_excerpt, "ID={$r->ID} ({$r->post_type}) поле post_excerpt — «{$r->post_title}»" );
}
printf( "ИТОГО в БД (publish, без ревизий): %d вхождений\n", $db_total );

echo "\nдля контроля — неопубликованные записи (draft и прочее), без ревизий:\n";
$rows2 = $wpdb->get_results( "SELECT ID, post_type, post_status, post_title, post_content, post_excerpt FROM {$wpdb->posts} WHERE post_type <> 'revision' AND post_status <> 'publish'" );
$n2 = 0;
foreach ( $rows2 as $r ) {
	$c = preg_match_all( $RE, wp_strip_all_tags( $r->post_content . ' ' . $r->post_excerpt ) );
	if ( $c ) { printf( "  ID=%s (%s/%s) «%s» вхождений=%d\n", $r->ID, $r->post_type, $r->post_status, $r->post_title, $c ); $n2 += $c; }
}
if ( 0 === $n2 ) { echo "  (ни одного)\n"; }

echo "\n--- 1.2b. Файлы темы (.html и .php) ---\n";
$file_total = 0;
foreach ( scan( $root . '/wp-content/themes/razil', array( 'html', 'php' ) ) as $p ) {
	$b = rd( $p );
	if ( ! preg_match( $RE, $b ) ) { continue; }
	$lines = preg_split( '/\r\n|\n|\r/', $b );
	foreach ( $lines as $i => $l ) {
		if ( ! preg_match_all( $RE, $l, $m ) ) { continue; }
		foreach ( $m[0] as $form ) {
			$is_a = ( 0 === mb_stripos( $form, 'агентств' ) );
			printf( "  источник: %s, строка %d\n", $rel( $p ), $i + 1 );
			printf( "  словоформа: «%s»%s\n", $form, $is_a ? '   <-- АГЕНТСТВО, НЕ ТРОГАЕМ' : '' );
			printf( "  строка: %s\n\n", trim( $l ) );
			$file_total++;
		}
	}
}
printf( "ИТОГО в файлах темы: %d вхождений\n", $file_total );

echo "\n--- 1.2c. PHP-файлы razil-core ---\n";
$plug_total = 0;
foreach ( scan( $root . '/wp-content/plugins/razil-core', array( 'php' ) ) as $p ) {
	$b = rd( $p );
	if ( ! preg_match( $RE, $b ) ) { continue; }
	$lines = preg_split( '/\r\n|\n|\r/', $b );
	foreach ( $lines as $i => $l ) {
		if ( ! preg_match_all( $RE, $l, $m ) ) { continue; }
		foreach ( $m[0] as $form ) {
			$is_a = ( 0 === mb_stripos( $form, 'агентств' ) );
			printf( "  источник: %s, строка %d\n", $rel( $p ), $i + 1 );
			printf( "  словоформа: «%s»%s\n", $form, $is_a ? '   <-- АГЕНТСТВО, НЕ ТРОГАЕМ' : '' );
			printf( "  строка: %s\n\n", trim( $l ) );
			$plug_total++;
		}
	}
}
printf( "ИТОГО в PHP плагина: %d вхождений\n", $plug_total );

echo "\n--- 1.2d. wp_options ---\n";
$opt = $wpdb->get_results( "SELECT option_id, option_name, option_value FROM {$wpdb->options}" );
$opt_total = 0;
foreach ( $opt as $o ) {
	if ( ! is_string( $o->option_value ) ) { continue; }
	if ( ! preg_match_all( $RE, $o->option_value, $m, PREG_OFFSET_CAPTURE ) ) { continue; }
	foreach ( $m[0] as $hit ) {
		$is_a = ( 0 === mb_stripos( $hit[0], 'агентств' ) );
		printf( "  источник: wp_options.%s\n", $o->option_name );
		printf( "  словоформа: «%s»%s\n", $hit[0], $is_a ? '   <-- АГЕНТСТВО, НЕ ТРОГАЕМ' : '' );
		printf( "  фрагмент: ...%s...\n\n", preg_replace( '/\s+/u', ' ', mb_substr( $o->option_value, max( 0, mb_strlen( mb_substr( $o->option_value, 0, $hit[1] ) ) - 90 ), 220 ) ) );
		$opt_total++;
	}
}
printf( "ИТОГО в wp_options: %d вхождений\n", $opt_total );

printf( "\n===== ВСЕГО по всем источникам (без ревизий): %d =====\n", $db_total + $file_total + $plug_total + $opt_total );

echo "\n\n##################################################################\n";
echo "# 1.3. ФАЙЛЫ-КАНДИДАТЫ НА ПРАВКУ\n";
echo "##################################################################\n";
$cands = array();
foreach ( $found_btn as $h ) { $cands[ $h['file'] ] = true; }
foreach ( array_keys( $cands ) as $p ) {
	$b    = rd( $p );
	$crlf = substr_count( $b, chr( 13 ) . chr( 10 ) );
	$lf   = substr_count( $b, chr( 10 ) );
	$eol  = ( $crlf > 0 && 0 === $lf - $crlf ) ? 'CRLF' : ( ( 0 === $crlf && $lf > 0 ) ? 'LF' : 'СМЕШАННЫЕ' );
	echo str_repeat( '-', 78 ) . "\n";
	printf( "%s\n", $rel( $p ) );
	printf( "  размер = %d байт\n", strlen( $b ) );
	printf( "  MD5    = %s\n", md5( $b ) );
	printf( "  SHA256 = %s\n", hash( 'sha256', $b ) );
	printf( "  первые 4 байта, hex = %s\n", strtoupper( implode( ' ', str_split( bin2hex( substr( $b, 0, 4 ) ), 2 ) ) ) );
	printf( "  UTF-8 BOM: %s | UTF-16 BOM: %s\n",
		0 === strncmp( $b, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ), 3 ) ? 'ЕСТЬ' : 'нет',
		( 0 === strncmp( $b, chr( 0xFE ) . chr( 0xFF ), 2 ) || 0 === strncmp( $b, chr( 0xFF ) . chr( 0xFE ), 2 ) ) ? 'ЕСТЬ' : 'нет' );
	printf( "  CRLF=%d, всего LF=%d, одиночных LF=%d -> %s\n", $crlf, $lf, $lf - $crlf, $eol );
	printf( "  валидный UTF-8: %s\n", mb_check_encoding( $b, 'UTF-8' ) ? 'да' : 'НЕТ' );
}

echo "\n--- запись ID=11, если правим тело ---\n";
$c = $wpdb->get_var( "SELECT post_content FROM {$wpdb->posts} WHERE ID=11" );
printf( "post_content: %d байт (strlen) / %d символов (mb_strlen), md5=%s\n", strlen( $c ), mb_strlen( $c ), md5( $c ) );
printf( "substr_count(«Агент незамедлительно сообщит вам об этом.») = %d\n", substr_count( $c, 'Агент незамедлительно сообщит вам об этом.' ) );

echo "\nЗАПИСИ НЕ ПРОИЗВОДИЛОСЬ.\n";
