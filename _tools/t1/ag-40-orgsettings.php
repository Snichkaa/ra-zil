<?php
/**
 * Этап 4 — org-settings.php, строка 321. Чтение 'rb' / запись 'wb'.
 */
$root = dirname( __DIR__, 2 );
$path = $root . '/wp-content/plugins/razil-core/inc/org-settings.php';
$bak  = $root . '/_backup/t1/org-settings.php.before';

function rd( $p ) { $fh = fopen( $p, 'rb' ); $b = stream_get_contents( $fh ); fclose( $fh ); return $b; }
function wr( $p, $b ) { $fh = fopen( $p, 'wb' ); $n = fwrite( $fh, $b ); fclose( $fh ); return $n; }
function eol_of( $b ) {
	$crlf = substr_count( $b, chr( 13 ) . chr( 10 ) );
	$lf   = substr_count( $b, chr( 10 ) );
	if ( $crlf > 0 && 0 === $lf - $crlf ) { return 'CRLF'; }
	if ( 0 === $crlf && $lf > 0 ) { return 'LF'; }
	return 'СМЕШАННЫЕ';
}

$FIND = 'на кнопке «Вызвать агента»';
$REPL = 'на кнопке «Вызвать специалиста»';

$before = rd( $path );

echo "=== СОСТОЯНИЕ ФАЙЛА ДО ПРАВКИ ===\n";
printf( "размер = %d байт\n", strlen( $before ) );
printf( "MD5    = %s\n", md5( $before ) );
printf( "SHA256 = %s\n", hash( 'sha256', $before ) );
printf( "первые 4 байта, hex = %s\n", strtoupper( implode( ' ', str_split( bin2hex( substr( $before, 0, 4 ) ), 2 ) ) ) );
printf( "UTF-8 BOM: %s | UTF-16 BOM: %s\n",
	0 === strncmp( $before, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ), 3 ) ? 'ЕСТЬ' : 'нет',
	( 0 === strncmp( $before, chr( 0xFE ) . chr( 0xFF ), 2 ) || 0 === strncmp( $before, chr( 0xFF ) . chr( 0xFE ), 2 ) ) ? 'ЕСТЬ' : 'нет' );
printf( "CRLF=%d, всего LF=%d, одиночных LF=%d -> %s\n",
	substr_count( $before, chr( 13 ) . chr( 10 ) ), substr_count( $before, chr( 10 ) ),
	substr_count( $before, chr( 10 ) ) - substr_count( $before, chr( 13 ) . chr( 10 ) ), eol_of( $before ) );
printf( "валидный UTF-8: %s\n", mb_check_encoding( $before, 'UTF-8' ) ? 'да' : 'НЕТ' );

echo "\n=== БЭКАП ===\n";
wr( $bak, $before );
clearstatcache( true, $bak );
printf( "%s -> %s\n", 'inc/org-settings.php', str_replace( chr( 92 ), '/', $bak ) );
printf( "  копия: %d байт, MD5 %s, EOL %s  %s\n", filesize( $bak ), md5_file( $bak ), eol_of( rd( $bak ) ),
	md5_file( $bak ) === md5( $before ) ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' );
if ( md5_file( $bak ) !== md5( $before ) ) { echo "СТОП.\n"; exit( 90 ); }

echo "\n=== DRY-RUN ===\n";
printf( "искомое: [%s]  (%d байт)\n", $FIND, strlen( $FIND ) );
printf( "замена:  [%s]  (%d байт)\n", $REPL, strlen( $REPL ) );
$n = substr_count( $before, $FIND );
printf( "вхождений в файле: %d\n", $n );
if ( 1 !== $n ) { printf( "СТОП: ожидалось 1, найдено %d.\n", $n ); exit( 91 ); }

$eol_sep = ( 'CRLF' === eol_of( $before ) ) ? chr( 13 ) . chr( 10 ) : chr( 10 );
$lines   = explode( $eol_sep, $before );
$idx     = null;
foreach ( $lines as $i => $l ) { if ( false !== strpos( $l, $FIND ) ) { $idx = $i; break; } }
printf( "строка с вхождением: %d\n\n", $idx + 1 );
echo "СТРОКА ЦЕЛИКОМ, БЫЛО:\n" . $lines[ $idx ] . "\n\n";
echo "СТРОКА ЦЕЛИКОМ, СТАНЕТ:\n" . str_replace( $FIND, $REPL, $lines[ $idx ] ) . "\n\n";
echo "контекст +-3:\n";
for ( $i = max( 0, $idx - 3 ); $i <= min( count( $lines ) - 1, $idx + 3 ); $i++ ) {
	printf( "%s%4d | %s\n", $i === $idx ? '>>' : '  ', $i + 1, $lines[ $i ] );
}

echo "\n=== ЗАПИСЬ ===\n";
$new     = str_replace( $FIND, $REPL, $before );
$written = wr( $path, $new );
clearstatcache( true, $path );
$after = rd( $path );

printf( "fwrite вернул: %d байт\n", $written );
printf( "размер: было %d -> стало %d (дельта %+d, ожидалось %+d)  %s\n",
	strlen( $before ), strlen( $after ), strlen( $after ) - strlen( $before ), strlen( $REPL ) - strlen( $FIND ),
	( strlen( $after ) - strlen( $before ) ) === ( strlen( $REPL ) - strlen( $FIND ) ) ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' );
printf( "MD5: было %s -> стало %s\n", md5( $before ), md5( $after ) );
printf( "записанное совпадает с подготовленным: %s\n", $after === $new ? 'да' : 'НЕТ' );
printf( "EOL: было %s -> стало %s  %s\n", eol_of( $before ), eol_of( $after ), eol_of( $before ) === eol_of( $after ) ? 'сохранён' : 'ИЗМЕНИЛСЯ' );
printf( "первые 4 байта, hex = %s\n", strtoupper( implode( ' ', str_split( bin2hex( substr( $after, 0, 4 ) ), 2 ) ) ) );
printf( "UTF-8 BOM: %s | UTF-16 BOM: %s\n",
	0 === strncmp( $after, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ), 3 ) ? 'ПОЯВИЛСЯ' : 'нет',
	( 0 === strncmp( $after, chr( 0xFE ) . chr( 0xFF ), 2 ) || 0 === strncmp( $after, chr( 0xFF ) . chr( 0xFE ), 2 ) ) ? 'ПОЯВИЛСЯ' : 'нет' );
printf( "CRLF=%d, всего LF=%d, одиночных LF=%d, одиночных CR=%d\n",
	substr_count( $after, chr( 13 ) . chr( 10 ) ), substr_count( $after, chr( 10 ) ),
	substr_count( $after, chr( 10 ) ) - substr_count( $after, chr( 13 ) . chr( 10 ) ),
	substr_count( $after, chr( 13 ) ) - substr_count( $after, chr( 13 ) . chr( 10 ) ) );
printf( "валидный UTF-8: %s\n", mb_check_encoding( $after, 'UTF-8' ) ? 'да' : 'НЕТ' );
printf( "вхождений «Вызвать агента» после: %d (ожидается 0)\n", substr_count( $after, 'Вызвать агента' ) );
printf( "вхождений «Вызвать специалиста» после: %d (ожидается 1)\n", substr_count( $after, 'Вызвать специалиста' ) );

$lines = explode( $eol_sep, $after );
echo "\nстрока " . ( $idx + 1 ) . " после правки:\n" . $lines[ $idx ] . "\n";
printf( "\nстрок в файле: было %d, стало %d %s\n", count( explode( $eol_sep, $before ) ), count( $lines ),
	count( explode( $eol_sep, $before ) ) === count( $lines ) ? '(не изменилось)' : '(ИЗМЕНИЛОСЬ)' );
if ( $after !== $new || eol_of( $before ) !== eol_of( $after ) ) { exit( 92 ); }
