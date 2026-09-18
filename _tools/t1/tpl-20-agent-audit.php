<?php
/**
 * Пункт 2.5 — аудит слова «агент» в single-services.html. Только чтение.
 * Для полноты — то же самое по двум другим целевым файлам.
 */
$root = dirname( __DIR__, 2 );

$files = array(
	'templates/single-services.html'  => $root . '/wp-content/themes/razil/templates/single-services.html',
	'templates/archive-services.html' => $root . '/wp-content/themes/razil/templates/archive-services.html',
	'parts/footer.html'               => $root . '/wp-content/themes/razil/parts/footer.html',
);

foreach ( $files as $label => $path ) {
	$fh = fopen( $path, 'rb' );
	$b  = stream_get_contents( $fh );
	fclose( $fh );

	echo str_repeat( '=', 78 ) . "\n";
	echo "ФАЙЛ: {$label}\n";
	echo str_repeat( '=', 78 ) . "\n";

	$lines = preg_split( '/\r\n|\n|\r/', $b );
	$hits  = array();
	foreach ( $lines as $i => $l ) {
		if ( false !== mb_stripos( $l, 'агент' ) ) {
			$hits[] = $i;
		}
	}
	printf( "вхождений подстроки «агент» (без учёта регистра) по строкам: %d\n", count( $hits ) );
	printf( "всего вхождений в файле: %d\n\n", preg_match_all( '/агент/iu', $b ) );

	if ( ! $hits ) { echo "(ни одного)\n\n"; continue; }

	foreach ( $hits as $h ) {
		printf( "строка %d, вхождений в строке: %d\n", $h + 1, preg_match_all( '/агент/iu', $lines[ $h ] ) );
		echo '  ' . $lines[ $h ] . "\n";
		preg_match_all( '/агент\p{L}*/iu', $lines[ $h ], $m );
		echo '  словоформы: ' . implode( ', ', array_map( function ( $x ) { return '«' . $x . '»'; }, $m[0] ) ) . "\n\n";
	}
}

echo str_repeat( '=', 78 ) . "\n";
echo "СВЕРКА ЦЕЛЕВЫХ СТРОК МЕЖДУ ДВУМЯ ФАЙЛАМИ (до правки)\n";
echo str_repeat( '=', 78 ) . "\n";
$a = preg_split( '/\r\n|\n|\r/', file_get_contents( $files['templates/archive-services.html'] ) );
$s = preg_split( '/\r\n|\n|\r/', file_get_contents( $files['templates/single-services.html'] ) );
$la = $a[38]; // строка 39
$ls = $s[19]; // строка 20
printf( "archive-services.html:39 = [%s]\n", $la );
printf( "  %d байт, md5=%s\n", strlen( $la ), md5( $la ) );
printf( "single-services.html:20  = [%s]\n", $ls );
printf( "  %d байт, md5=%s\n", strlen( $ls ), md5( $ls ) );
printf( "строки побайтово идентичны: %s\n", $la === $ls ? 'ДА' : 'нет' );
if ( $la !== $ls ) {
	printf( "  archive, hex = %s\n", bin2hex( $la ) );
	printf( "  single,  hex = %s\n", bin2hex( $ls ) );
}

echo "\nЗАПИСИ НЕ ПРОИЗВОДИЛОСЬ.\n";
