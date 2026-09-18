<?php
/**
 * Этап 1 разведки по файлам темы. Только чтение.
 * BOM и переводы строк проверяются в бинарном режиме.
 */
$root = dirname( __DIR__, 2 );

$files = array(
	'footer'  => $root . '/wp-content/themes/razil/parts/footer.html',
	'single'  => $root . '/wp-content/themes/razil/templates/single-services.html',
	'archive' => $root . '/wp-content/themes/razil/templates/archive-services.html',
);

$BOM_UTF8    = chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF );
$BOM_UTF16BE = chr( 0xFE ) . chr( 0xFF );
$BOM_UTF16LE = chr( 0xFF ) . chr( 0xFE );

$raw = array();

foreach ( $files as $key => $path ) {
	echo str_repeat( '=', 78 ) . "\n";
	echo 'ФАЙЛ: ' . str_replace( chr( 92 ), '/', $path ) . "\n";
	echo str_repeat( '=', 78 ) . "\n";

	if ( ! is_file( $path ) ) { echo "НЕТ ТАКОГО ФАЙЛА\n"; continue; }

	$fh = fopen( $path, 'rb' );
	$b  = stream_get_contents( $fh );
	fclose( $fh );
	$raw[ $key ] = $b;

	printf( "размер, байт = %d\n", strlen( $b ) );
	printf( "filesize()   = %d\n", filesize( $path ) );
	printf( "MD5          = %s\n", md5( $b ) );
	printf( "MD5 (md5_file) = %s\n", md5_file( $path ) );
	printf( "SHA-256      = %s\n", hash( 'sha256', $b ) );

	echo "\n--- BOM (чтение в бинарном режиме 'rb', первые 4 байта) ---\n";
	$head = substr( $b, 0, 4 );
	printf( "первые 4 байта, hex = %s\n", strtoupper( implode( ' ', str_split( bin2hex( $head ), 2 ) ) ) );
	printf( "  UTF-8 BOM (EF BB BF)  : %s\n", 0 === strncmp( $b, $BOM_UTF8, 3 ) ? 'ЕСТЬ' : 'нет' );
	printf( "  UTF-16BE BOM (FE FF)  : %s\n", 0 === strncmp( $b, $BOM_UTF16BE, 2 ) ? 'ЕСТЬ' : 'нет' );
	printf( "  UTF-16LE BOM (FF FE)  : %s\n", 0 === strncmp( $b, $BOM_UTF16LE, 2 ) ? 'ЕСТЬ' : 'нет' );

	echo "\n--- переводы строк (подсчёт по байтам) ---\n";
	$crlf = substr_count( $b, chr( 13 ) . chr( 10 ) );
	$cr   = substr_count( $b, chr( 13 ) );
	$lf   = substr_count( $b, chr( 10 ) );
	printf( "CRLF (0D 0A) = %d\n", $crlf );
	printf( "всего CR (0D) = %d, всего LF (0A) = %d\n", $cr, $lf );
	printf( "одиночных LF (не в составе CRLF) = %d\n", $lf - $crlf );
	printf( "одиночных CR (не в составе CRLF) = %d\n", $cr - $crlf );
	$type = ( $crlf > 0 && $lf - $crlf === 0 ) ? 'CRLF (везде)'
		: ( ( 0 === $crlf && $lf > 0 ) ? 'LF (везде)' : 'СМЕШАННЫЕ' );
	printf( "ВЫВОД: %s\n", $type );
	printf( "последний байт файла, hex = %s%s\n",
		strtoupper( bin2hex( substr( $b, -1 ) ) ),
		"\n" === substr( $b, -1 ) ? ' (файл кончается переводом строки)' : ' (перевода строки в конце нет)' );
	printf( "кодировка: валидный UTF-8 = %s\n", mb_check_encoding( $b, 'UTF-8' ) ? 'да' : 'НЕТ' );
	echo "\n";
}

/** Печать целевых строк с контекстом +-3. */
function ctx( $body, $needle, $label, $path ) {
	echo str_repeat( '-', 78 ) . "\n";
	echo "ЦЕЛЬ: {$label}\n";
	echo 'файл: ' . $path . "\n";
	$lines = preg_split( '/\r\n|\n|\r/', $body );
	$hits  = array();
	foreach ( $lines as $i => $l ) {
		if ( false !== strpos( $l, $needle ) ) { $hits[] = $i; }
	}
	printf( "строк с вхождением «%s»: %d\n", $needle, count( $hits ) );
	if ( ! $hits ) { echo "(не найдено)\n"; return; }
	foreach ( $hits as $h ) {
		echo "--- строка " . ( $h + 1 ) . ", контекст +-3 ---\n";
		for ( $i = max( 0, $h - 3 ); $i <= min( count( $lines ) - 1, $h + 3 ); $i++ ) {
			printf( "%s%4d | %s\n", $i === $h ? '>>' : '  ', $i + 1, $lines[ $i ] );
		}
	}
}

echo str_repeat( '=', 78 ) . "\n";
echo "ЦЕЛЕВЫЕ СТРОКИ\n";
echo str_repeat( '=', 78 ) . "\n";

ctx( $raw['footer'], '/uslugi/transportirovka/', '1) parts/footer.html — ссылка на /uslugi/transportirovka/', 'wp-content/themes/razil/parts/footer.html' );
ctx( $raw['single'], 'core/post-excerpt', '2) templates/single-services.html — блок core/post-excerpt', 'wp-content/themes/razil/templates/single-services.html' );
ctx( $raw['archive'], '"orderBy":"title"', '3) templates/archive-services.html — core/query с orderBy title', 'wp-content/themes/razil/templates/archive-services.html' );
ctx( $raw['archive'], 'rz-callback__lead', '4) templates/archive-services.html — абзац .rz-callback__lead', 'wp-content/themes/razil/templates/archive-services.html' );

echo "\n" . str_repeat( '=', 78 ) . "\n";
echo "ДОПОЛНИТЕЛЬНО: все вхождения core/post-excerpt по теме\n";
echo str_repeat( '=', 78 ) . "\n";
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/wp-content/themes/razil', FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $f ) {
	if ( ! $f->isFile() || 'html' !== strtolower( $f->getExtension() ) ) { continue; }
	$txt = file_get_contents( $f->getPathname() );
	$n   = substr_count( $txt, 'core/post-excerpt' );
	if ( $n ) {
		printf( "%-64s вхождений=%d\n", str_replace( str_replace( chr( 92 ), '/', $root ) . '/', '', str_replace( chr( 92 ), '/', $f->getPathname() ) ), $n );
	}
}

echo "\nвхождения «Транспортировка умерших» по всем файлам темы и плагина:\n";
foreach ( array( $root . '/wp-content/themes/razil', $root . '/wp-content/plugins/razil-core' ) as $dir ) {
	$it2 = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it2 as $f ) {
		if ( ! $f->isFile() ) { continue; }
		if ( ! in_array( strtolower( $f->getExtension() ), array( 'html', 'php', 'json' ), true ) ) { continue; }
		$txt = @file_get_contents( $f->getPathname() );
		$n   = substr_count( (string) $txt, 'Транспортировка умерших' );
		if ( $n ) {
			printf( "  %-70s вхождений=%d\n", str_replace( str_replace( chr( 92 ), '/', $root ) . '/', '', str_replace( chr( 92 ), '/', $f->getPathname() ) ), $n );
		}
	}
}

echo "\nвхождения «агент перезвонит сам» по файлам темы:\n";
$it3 = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/wp-content/themes/razil', FilesystemIterator::SKIP_DOTS ) );
foreach ( $it3 as $f ) {
	if ( ! $f->isFile() || ! in_array( strtolower( $f->getExtension() ), array( 'html', 'php' ), true ) ) { continue; }
	$txt = @file_get_contents( $f->getPathname() );
	$n   = substr_count( (string) $txt, 'агент перезвонит сам' );
	if ( $n ) {
		printf( "  %-70s вхождений=%d\n", str_replace( str_replace( chr( 92 ), '/', $root ) . '/', '', str_replace( chr( 92 ), '/', $f->getPathname() ) ), $n );
	}
}

echo "\nЗАПИСИ НЕ ПРОИЗВОДИЛОСЬ.\n";
