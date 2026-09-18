<?php
/**
 * Досмотр: блок post-excerpt записан как «wp:post-excerpt», без префикса core/.
 * Только чтение.
 */
$root = dirname( __DIR__, 2 );

$single  = $root . '/wp-content/themes/razil/templates/single-services.html';
$archive = $root . '/wp-content/themes/razil/templates/archive-services.html';

function ctx( $path, $needle, $label, $around = 3 ) {
	$fh = fopen( $path, 'rb' );
	$b  = stream_get_contents( $fh );
	fclose( $fh );
	echo str_repeat( '-', 78 ) . "\n";
	echo "ЦЕЛЬ: {$label}\n";
	echo 'игла: «' . $needle . "»\n";
	$lines = preg_split( '/\r\n|\n|\r/', $b );
	$hits  = array();
	foreach ( $lines as $i => $l ) { if ( false !== strpos( $l, $needle ) ) { $hits[] = $i; } }
	printf( "строк с вхождением: %d\n", count( $hits ) );
	if ( ! $hits ) { echo "(не найдено)\n"; return; }
	foreach ( $hits as $h ) {
		echo '--- строка ' . ( $h + 1 ) . ", контекст +-{$around} ---\n";
		for ( $i = max( 0, $h - $around ); $i <= min( count( $lines ) - 1, $h + $around ); $i++ ) {
			printf( "%s%4d | %s\n", $i === $h ? '>>' : '  ', $i + 1, $lines[ $i ] );
		}
	}
}

ctx( $single, 'wp:post-excerpt', '2) templates/single-services.html — блок post-excerpt' );
ctx( $archive, 'wp:post-excerpt', 'справочно: archive-services.html — блок post-excerpt в карточке' );

echo "\n" . str_repeat( '=', 78 ) . "\n";
echo "ВСЕ вхождения «post-excerpt» по HTML-файлам темы\n";
echo str_repeat( '=', 78 ) . "\n";
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/wp-content/themes/razil', FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $f ) {
	if ( ! $f->isFile() || 'html' !== strtolower( $f->getExtension() ) ) { continue; }
	$txt = file_get_contents( $f->getPathname() );
	$n   = substr_count( $txt, 'post-excerpt' );
	if ( $n ) {
		printf( "%-62s вхождений=%d\n", str_replace( str_replace( chr( 92 ), '/', $root ) . '/', '', str_replace( chr( 92 ), '/', $f->getPathname() ) ), $n );
	}
}

echo "\n" . str_repeat( '=', 78 ) . "\n";
echo "ПОЛНОЕ СОДЕРЖИМОЕ single-services.html с номерами строк\n";
echo str_repeat( '=', 78 ) . "\n";
$fh = fopen( $single, 'rb' );
$b  = stream_get_contents( $fh );
fclose( $fh );
foreach ( preg_split( '/\r\n|\n|\r/', $b ) as $i => $l ) {
	printf( "%3d | %s\n", $i + 1, $l );
}

echo "\n" . str_repeat( '=', 78 ) . "\n";
echo "ПОЛНОЕ СОДЕРЖИМОЕ archive-services.html с номерами строк\n";
echo str_repeat( '=', 78 ) . "\n";
$fh = fopen( $archive, 'rb' );
$b  = stream_get_contents( $fh );
fclose( $fh );
foreach ( preg_split( '/\r\n|\n|\r/', $b ) as $i => $l ) {
	printf( "%3d | %s\n", $i + 1, $l );
}

echo "\nЗАПИСИ НЕ ПРОИЗВОДИЛОСЬ.\n";
