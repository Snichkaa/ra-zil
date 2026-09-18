<?php
/**
 * 2.1 — parts/header.html, текст кнопки. Чтение 'rb' / запись 'wb'.
 */
$root = dirname( __DIR__, 2 );
$path = $root . '/wp-content/themes/razil/parts/header.html';
$bak  = $root . '/_backup/t1/templates/header.html.before';

function rd( $p ) { $fh = fopen( $p, 'rb' ); $b = stream_get_contents( $fh ); fclose( $fh ); return $b; }
function wr( $p, $b ) { $fh = fopen( $p, 'wb' ); $n = fwrite( $fh, $b ); fclose( $fh ); return $n; }
function eol_of( $b ) {
	$crlf = substr_count( $b, chr( 13 ) . chr( 10 ) );
	$lf   = substr_count( $b, chr( 10 ) );
	if ( $crlf > 0 && 0 === $lf - $crlf ) { return 'CRLF'; }
	if ( 0 === $crlf && $lf > 0 ) { return 'LF'; }
	return 'СМЕШАННЫЕ';
}

$FIND = '>Вызвать агента</a>';
$REPL = '>Вызвать специалиста</a>';

$before = rd( $path );

echo "=== БЭКАП ===\n";
wr( $bak, $before );
clearstatcache( true, $bak );
printf( "%s -> %s\n", 'parts/header.html', str_replace( chr( 92 ), '/', $bak ) );
printf( "  исходник: %d байт, MD5 %s, EOL %s\n", strlen( $before ), md5( $before ), eol_of( $before ) );
printf( "  копия:    %d байт, MD5 %s, EOL %s  %s\n", filesize( $bak ), md5_file( $bak ), eol_of( rd( $bak ) ),
	md5_file( $bak ) === md5( $before ) ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' );
if ( md5_file( $bak ) !== md5( $before ) ) { echo "СТОП.\n"; exit( 60 ); }

echo "\n=== DRY-RUN ===\n";
printf( "искомое: [%s]  (%d байт)\n", $FIND, strlen( $FIND ) );
printf( "замена:  [%s]  (%d байт)\n", $REPL, strlen( $REPL ) );
$n = substr_count( $before, $FIND );
printf( "вхождений в файле: %d\n", $n );
if ( 1 !== $n ) { printf( "СТОП: ожидалось 1, найдено %d.\n", $n ); exit( 61 ); }
$lines = preg_split( '/\r\n/', $before );
echo "строка 61 было:\n" . $lines[60] . "\n";
echo "строка 61 станет:\n" . str_replace( $FIND, $REPL, $lines[60] ) . "\n";

echo "\n=== ЗАПИСЬ ===\n";
$new     = str_replace( $FIND, $REPL, $before );
$written = wr( $path, $new );
clearstatcache( true, $path );
$after = rd( $path );

printf( "fwrite вернул: %d байт\n", $written );
printf( "размер: было %d -> стало %d (дельта %+d, ожидалось %+d)  %s\n",
	strlen( $before ), strlen( $after ), strlen( $after ) - strlen( $before ),
	strlen( $REPL ) - strlen( $FIND ),
	( strlen( $after ) - strlen( $before ) ) === ( strlen( $REPL ) - strlen( $FIND ) ) ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' );
printf( "MD5: было %s -> стало %s\n", md5( $before ), md5( $after ) );
printf( "записанное совпадает с подготовленным: %s\n", $after === $new ? 'да' : 'НЕТ' );
printf( "EOL: было %s -> стало %s  %s\n", eol_of( $before ), eol_of( $after ), eol_of( $before ) === eol_of( $after ) ? 'сохранён' : 'ИЗМЕНИЛСЯ' );
printf( "первые 4 байта, hex = %s\n", strtoupper( implode( ' ', str_split( bin2hex( substr( $after, 0, 4 ) ), 2 ) ) ) );
printf( "UTF-8 BOM: %s | UTF-16 BOM: %s\n",
	0 === strncmp( $after, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ), 3 ) ? 'ПОЯВИЛСЯ' : 'нет',
	( 0 === strncmp( $after, chr( 0xFE ) . chr( 0xFF ), 2 ) || 0 === strncmp( $after, chr( 0xFF ) . chr( 0xFE ), 2 ) ) ? 'ПОЯВИЛСЯ' : 'нет' );
printf( "валидный UTF-8: %s\n", mb_check_encoding( $after, 'UTF-8' ) ? 'да' : 'НЕТ' );
printf( "CRLF=%d, всего LF=%d, одиночных LF=%d, одиночных CR=%d\n",
	substr_count( $after, chr( 13 ) . chr( 10 ) ), substr_count( $after, chr( 10 ) ),
	substr_count( $after, chr( 10 ) ) - substr_count( $after, chr( 13 ) . chr( 10 ) ),
	substr_count( $after, chr( 13 ) ) - substr_count( $after, chr( 13 ) . chr( 10 ) ) );

$lines = preg_split( '/\r\n/', $after );
echo "\nстрока 61 после правки:\n" . $lines[60] . "\n";
printf( "href сохранён: %s\n", false !== strpos( $lines[60], 'href="tel:+74212605290"' ) ? 'да' : 'НЕТ' );
printf( "вхождений «Вызвать агента» после: %d (ожидается 0)\n", substr_count( $after, 'Вызвать агента' ) );
printf( "вхождений «Вызвать специалиста» после: %d (ожидается 1)\n", substr_count( $after, 'Вызвать специалиста' ) );
if ( $after !== $new || eol_of( $before ) !== eol_of( $after ) ) { exit( 62 ); }
