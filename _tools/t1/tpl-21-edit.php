<?php
/**
 * Этап 2 по файлам темы: бэкап + точечные замены в бинарном режиме.
 * Чтение 'rb' / запись 'wb' — переводы строк и кодировка не конвертируются.
 */
$root    = dirname( __DIR__, 2 );
$bak_dir = $root . '/_backup/t1/templates';
if ( ! is_dir( $bak_dir ) ) { mkdir( $bak_dir, 0777, true ); }

$FILES = array(
	'footer'  => array( 'path' => $root . '/wp-content/themes/razil/parts/footer.html',               'rel' => 'parts/footer.html',               'eol' => 'CRLF' ),
	'archive' => array( 'path' => $root . '/wp-content/themes/razil/templates/archive-services.html', 'rel' => 'templates/archive-services.html', 'eol' => 'CRLF' ),
	'single'  => array( 'path' => $root . '/wp-content/themes/razil/templates/single-services.html',  'rel' => 'templates/single-services.html',  'eol' => 'LF' ),
);

function rd( $p ) { $fh = fopen( $p, 'rb' ); $b = stream_get_contents( $fh ); fclose( $fh ); return $b; }
function wr( $p, $b ) { $fh = fopen( $p, 'wb' ); $n = fwrite( $fh, $b ); fclose( $fh ); return $n; }
function eol_of( $b ) {
	$crlf = substr_count( $b, chr( 13 ) . chr( 10 ) );
	$lf   = substr_count( $b, chr( 10 ) );
	if ( $crlf > 0 && $lf - $crlf === 0 ) { return 'CRLF'; }
	if ( 0 === $crlf && $lf > 0 ) { return 'LF'; }
	return 'СМЕШАННЫЕ';
}

/* ---------------------------------------------------------------- БЭКАП */
echo "================== БЭКАП в _backup/t1/templates/ ==================\n";
foreach ( $FILES as $k => $f ) {
	$b    = rd( $f['path'] );
	$dest = $bak_dir . '/' . basename( $f['path'] ) . '.before';
	wr( $dest, $b );
	clearstatcache( true, $dest );
	printf( "%-40s -> %s\n", $f['rel'], str_replace( chr( 92 ), '/', $dest ) );
	printf( "   исходник: %d байт, MD5 %s, EOL %s\n", strlen( $b ), md5( $b ), eol_of( $b ) );
	printf( "   копия:    %d байт, MD5 %s, EOL %s  %s\n",
		filesize( $dest ), md5_file( $dest ), eol_of( rd( $dest ) ),
		md5_file( $dest ) === md5( $b ) ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' );
	if ( md5_file( $dest ) !== md5( $b ) ) { echo "СТОП: бэкап не совпал с исходником.\n"; exit( 40 ); }
	$FILES[ $k ]['before'] = $b;
}

/* Общая процедура одной замены. */
function edit( &$F, $key, $find, $repl, $label ) {
	$f = $F[ $key ];
	$b = rd( $f['path'] );
	echo "\n" . str_repeat( '-', 78 ) . "\n";
	echo "{$label}\n";
	echo 'файл: ' . $f['rel'] . "\n";
	printf( "искомое: [%s]  (%d байт)\n", $find, strlen( $find ) );
	printf( "замена:  [%s]  (%d байт)\n", $repl, strlen( $repl ) );
	$n = substr_count( $b, $find );
	printf( "вхождений в файле: %d\n", $n );
	if ( 1 !== $n ) {
		printf( "СТОП: ожидалось ровно 1 вхождение, найдено %d. Файл не тронут.\n", $n );
		exit( 41 );
	}
	$eol_before = eol_of( $b );
	$md5_before = md5( $b );
	$len_before = strlen( $b );

	$new = str_replace( $find, $repl, $b );
	$written = wr( $f['path'], $new );
	clearstatcache( true, $f['path'] );
	$after = rd( $f['path'] );

	printf( "fwrite вернул: %d байт\n", $written );
	printf( "размер: было %d -> стало %d  (дельта %+d, ожидалось %+d)  %s\n",
		$len_before, strlen( $after ), strlen( $after ) - $len_before,
		strlen( $repl ) - strlen( $find ),
		( strlen( $after ) - $len_before ) === ( strlen( $repl ) - strlen( $find ) ) ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' );
	printf( "MD5:    было %s -> стало %s\n", $md5_before, md5( $after ) );
	printf( "записанное совпадает с подготовленным: %s\n", $after === $new ? 'да' : 'НЕТ' );
	printf( "EOL:    было %s -> стало %s  %s\n", $eol_before, eol_of( $after ),
		$eol_before === eol_of( $after ) ? 'сохранён' : 'ИЗМЕНИЛСЯ' );
	printf( "BOM после записи: %s\n", 0 === strncmp( $after, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ), 3 ) ? 'ПОЯВИЛСЯ' : 'нет' );
	printf( "валидный UTF-8: %s\n", mb_check_encoding( $after, 'UTF-8' ) ? 'да' : 'НЕТ' );
	printf( "вхождений искомого после замены: %d (ожидается 0)\n", substr_count( $after, $find ) );
	printf( "вхождений замены после записи:  %d (ожидается 1)\n", substr_count( $after, $repl ) );
	if ( $after !== $new || $eol_before !== eol_of( $after ) ) { echo "СТОП.\n"; exit( 42 ); }
}

/* ------------------------------------------------------------------ 2.1 */
echo "\n\n================== 2.1 parts/footer.html ==================\n";
edit( $FILES, 'footer', '>Транспортировка умерших</a>', '>Транспортировка</a>', '2.1 текст ссылки, href не трогаем' );
$b = rd( $FILES['footer']['path'] );
$lines = preg_split( '/\r\n/', $b );
echo "\nстрока 77 после правки:\n" . $lines[76] . "\n";
printf( "href в строке сохранён: %s\n", false !== strpos( $lines[76], 'href="/uslugi/transportirovka/"' ) ? 'да' : 'НЕТ' );

/* ------------------------------------------------------------------ 2.3 */
echo "\n\n================== 2.3 templates/archive-services.html, wp:query ==================\n";
edit( $FILES, 'archive', '"order":"asc","orderBy":"title"', '"order":"asc","orderBy":"menu_order"', '2.3 orderBy в атрибутах запроса' );
$b = rd( $FILES['archive']['path'] );
$lines = preg_split( '/\r\n/', $b );
echo "\nстрока 15 после правки:\n" . $lines[14] . "\n";

/* ------------------------------------------------------------------ 2.4 */
echo "\n\n================== 2.4 «агент» -> «специалист», два файла ==================\n";
edit( $FILES, 'archive', 'и агент перезвонит сам', 'и специалист перезвонит сам', '2.4a archive-services.html, строка 39' );
edit( $FILES, 'single',  'и агент перезвонит сам', 'и специалист перезвонит сам', '2.4b single-services.html, строка 20' );

echo "\n--- сверка двух строк между собой ПОСЛЕ правки ---\n";
$la = preg_split( '/\r\n/', rd( $FILES['archive']['path'] ) )[38];
$ls = preg_split( '/\n/', rd( $FILES['single']['path'] ) )[19];
printf( "archive-services.html:39 = [%s]\n", $la );
printf( "  %d байт, md5=%s\n", strlen( $la ), md5( $la ) );
printf( "single-services.html:20  = [%s]\n", $ls );
printf( "  %d байт, md5=%s\n", strlen( $ls ), md5( $ls ) );
printf( "ПОБАЙТОВО ИДЕНТИЧНЫ: %s\n", $la === $ls ? 'ДА' : 'НЕТ' );
if ( $la !== $ls ) {
	printf( "  archive, hex = %s\n", bin2hex( $la ) );
	printf( "  single,  hex = %s\n", bin2hex( $ls ) );
	exit( 43 );
}

/* --------------------------------------------------------------- ИТОГИ */
echo "\n\n================== MD5 ФАЙЛОВ ПОСЛЕ ВСЕХ ПРАВОК ==================\n";
printf( "%-40s %-10s %-34s %-34s %s\n", 'файл', 'размер', 'MD5 до', 'MD5 после', 'EOL' );
echo str_repeat( '-', 130 ) . "\n";
foreach ( $FILES as $k => $f ) {
	$after = rd( $f['path'] );
	printf( "%-40s %-10s %-34s %-34s %s\n", $f['rel'], strlen( $after ), md5( $f['before'] ), md5( $after ), eol_of( $after ) );
}

echo "\nwp:post-excerpt в single-services.html (пункт 2.2 отменён, блок не трогали):\n";
printf( "  вхождений = %d (ожидается 1)\n", substr_count( rd( $FILES['single']['path'] ), 'wp:post-excerpt' ) );
