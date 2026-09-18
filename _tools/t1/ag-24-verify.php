<?php
/**
 * 2.2, шаг 3 + этап 3 — проверка отдельным процессом.
 * Эталоны <title> зашиты константами из разведки.
 */
require dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb;

$root = dirname( __DIR__, 2 );
$MAP  = require __DIR__ . '/ag-22-map.php';
$rel  = function ( $p ) use ( $root ) { return str_replace( str_replace( chr( 92 ), '/', $root ) . '/', '', str_replace( chr( 92 ), '/', $p ) ); };

$ETALON_TITLE = array(
	11 => 'Организация похорон в Хабаровске - Земля и Люди',
	12 => 'Кремация в Хабаровске - Земля и Люди',
	13 => 'Транспортировка умерших в Хабаровске - Земля и Люди',
	14 => 'Благоустройство мест захоронения в Хабаровске - Земля и Люди',
	15 => 'Юридическая помощь при оформлении документов после смерти - Земля и Люди',
);

$bk = array();
foreach ( json_decode( file_get_contents( $root . '/_backup/t1/agent-pass-before.json' ), true ) as $r ) { $bk[ (int) $r['ID'] ] = $r; }

function rd( $p ) { $fh = fopen( $p, 'rb' ); $b = stream_get_contents( $fh ); fclose( $fh ); return $b; }
function plain_of( $h ) { return html_entity_decode( wp_strip_all_tags( preg_replace( '/<!--.*?-->/us', ' ', $h ) ), ENT_QUOTES, 'UTF-8' ); }

$fail = array();

wp_cache_flush();
echo "wp_cache_flush() вызван перед чтениями\n\n";

/* ---------------------------------------------------- поблочное сравнение */
echo "================== 2.2 ПОБЛОЧНОЕ СРАВНЕНИЕ С БЭКАПОМ ==================\n";
$flatten = function ( $blocks, $path = '' ) use ( &$flatten ) {
	$out = array();
	foreach ( $blocks as $i => $b ) {
		$p = $path . '/' . $i . ':' . ( null === $b['blockName'] ? '(html)' : $b['blockName'] );
		$out[] = array(
			'path'         => $p,
			'blockName'    => $b['blockName'],
			'attrs'        => json_encode( $b['attrs'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			'innerContent' => $b['innerContent'],
		);
		$out = array_merge( $out, $flatten( $b['innerBlocks'], $p ) );
	}
	return $out;
};

foreach ( $MAP as $id => $pairs ) {
	$now = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID=%d", $id ) );
	$was = $bk[ $id ]['post_content'];

	echo str_repeat( '-', 78 ) . "\n";
	printf( "ID=%d (замен: %d)\n", $id, count( $pairs ) );

	$rt = serialize_blocks( parse_blocks( $now ) );
	printf( "  круговорот parse_blocks->serialize_blocks: %s (%d байт, md5 %s)\n",
		$rt === $now ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ', strlen( $rt ), md5( $rt ) );
	if ( $rt !== $now ) { $fail[] = "круговорот ID={$id}"; }

	$fa = $flatten( parse_blocks( $was ) );
	$fb = $flatten( parse_blocks( $now ) );
	printf( "  блоков: бэкап %d, сейчас %d  %s\n", count( $fa ), count( $fb ), count( $fa ) === count( $fb ) ? '(совпало)' : '(РАСХОЖДЕНИЕ)' );
	if ( count( $fa ) !== count( $fb ) ) { $fail[] = "число блоков ID={$id}"; }

	$dn = $da = $di = 0;
	$ic_paths = array();
	for ( $i = 0, $n = min( count( $fa ), count( $fb ) ); $i < $n; $i++ ) {
		if ( $fa[ $i ]['blockName'] !== $fb[ $i ]['blockName'] ) {
			$dn++;
			printf( "    blockName @ %s: [%s] -> [%s]\n", $fa[ $i ]['path'], $fa[ $i ]['blockName'], $fb[ $i ]['blockName'] );
		}
		if ( $fa[ $i ]['attrs'] !== $fb[ $i ]['attrs'] ) {
			$da++;
			printf( "    attrs @ %s:\n      было  = %s\n      стало = %s\n", $fa[ $i ]['path'], $fa[ $i ]['attrs'], $fb[ $i ]['attrs'] );
		}
		if ( $fa[ $i ]['innerContent'] !== $fb[ $i ]['innerContent'] ) { $di++; $ic_paths[] = $fa[ $i ]['path']; }
	}
	printf( "  расхождений: blockName=%d, attrs=%d, innerContent=%d\n", $dn, $da, $di );
	printf( "  блоки с изменённым innerContent: %s\n", $ic_paths ? implode( ', ', $ic_paths ) : '(нет)' );
	if ( 0 !== $dn ) { $fail[] = "blockName изменился, ID={$id}"; }
	if ( 0 !== $da ) { $fail[] = "attrs изменились, ID={$id}"; }

	/* Сводим отличия к набору заданных замен. */
	$expect = $was;
	foreach ( $pairs as $p ) { $expect = str_replace( $p[0], $p[1], $expect ); }
	printf( "  бэкап + заданные замены === текущее: %s\n", $expect === $now ? 'да' : 'НЕТ' );
	if ( $expect !== $now ) { $fail[] = "отличие не сводится к заданным заменам, ID={$id}"; }

	printf( "  strlen:    бэкап %d -> сейчас %d (%+d)\n", strlen( $was ), strlen( $now ), strlen( $now ) - strlen( $was ) );
	printf( "  mb_strlen: бэкап %d -> сейчас %d (%+d)\n", mb_strlen( $was ), mb_strlen( $now ), mb_strlen( $now ) - mb_strlen( $was ) );
}

/* ------------------------------------------------------------------- 3.1 */
echo "\n================== 3.1. <title> 11-15 и post_name ==================\n";
foreach ( array( 11, 12, 13, 14, 15 ) as $id ) {
	wp_cache_flush();
	$got  = YoastSEO()->meta->for_post( $id )->title;
	$same = ( $got === $ETALON_TITLE[ $id ] );
	printf( "ID=%-3s <title> = [%s]\n", $id, $got );
	printf( "        эталон  = [%s]  %s\n", $ETALON_TITLE[ $id ], $same ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' );
	if ( ! $same ) { $fail[] = "<title> ID={$id}"; }
}
echo "\npost_name затронутых объектов (11, 12, 18, 19, 25), сверка с бэкапом:\n";
foreach ( array_keys( $MAP ) as $id ) {
	$now  = $wpdb->get_var( $wpdb->prepare( "SELECT post_name FROM {$wpdb->posts} WHERE ID=%d", $id ) );
	$same = ( $now === $bk[ $id ]['post_name'] );
	printf( "  ID=%-4s бэкап=[%s] сейчас=[%s] %s\n", $id, $bk[ $id ]['post_name'], $now, $same ? 'не изменился' : 'ИЗМЕНИЛСЯ' );
	if ( ! $same ) { $fail[] = "post_name ID={$id}"; }
}

/* ------------------------------------------------------------------- 3.2 */
echo "\n================== 3.2. ОСТАВШИЕСЯ «агент» ==================\n";
$RE = '/агент\p{Cyrillic}*/iu';
$left_worker = 0;

echo "--- БД: post_content + post_excerpt, publish, без ревизий ---\n";
$rows = $wpdb->get_results( "SELECT ID, post_type, post_title, post_content, post_excerpt FROM {$wpdb->posts} WHERE post_type <> 'revision' AND post_status='publish' ORDER BY ID" );
foreach ( $rows as $r ) {
	foreach ( array( 'post_content', 'post_excerpt' ) as $f ) {
		$plain = plain_of( $r->$f );
		if ( ! preg_match_all( $RE, $plain, $m ) ) { continue; }
		foreach ( $m[0] as $form ) {
			$is_a = ( 0 === mb_stripos( $form, 'агентств' ) );
			if ( ! $is_a ) { $left_worker++; }
			printf( "  ID=%-4s %-13s «%s» — %s\n", $r->ID, $f, $form,
				$is_a ? 'ОСТАВЛЕНО: «агентство», род деятельности' : 'СОТРУДНИК — требует решения' );
		}
	}
}

echo "\n--- файлы темы (.html, .php) ---\n";
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/wp-content/themes/razil', FilesystemIterator::SKIP_DOTS ) );
$tf = array();
foreach ( $it as $f ) { if ( $f->isFile() && in_array( strtolower( $f->getExtension() ), array( 'html', 'php' ), true ) ) { $tf[] = $f->getPathname(); } }
sort( $tf );
foreach ( $tf as $p ) {
	$b = rd( $p );
	if ( ! preg_match( $RE, $b ) ) { continue; }
	foreach ( preg_split( '/\r\n|\n|\r/', $b ) as $i => $l ) {
		if ( ! preg_match_all( $RE, $l, $m ) ) { continue; }
		foreach ( $m[0] as $form ) {
			$is_a  = ( 0 === mb_stripos( $form, 'агентств' ) );
			$is_fp = ( false !== strpos( $rel( $p ), 'front-page.html' ) );
			if ( ! $is_a ) { $left_worker++; }
			printf( "  %-52s:%-4d «%s» — %s\n", $rel( $p ), $i + 1, $form,
				$is_a ? 'ОСТАВЛЕНО: «агентство»' : ( $is_fp ? 'ОСТАВЛЕНО: front-page.html отложен по вашему запрету' : 'СОТРУДНИК — требует решения' ) );
		}
	}
}

echo "\n--- PHP razil-core ---\n";
$it2 = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/wp-content/plugins/razil-core', FilesystemIterator::SKIP_DOTS ) );
$pf = array();
foreach ( $it2 as $f ) { if ( $f->isFile() && 'php' === strtolower( $f->getExtension() ) ) { $pf[] = $f->getPathname(); } }
sort( $pf );
foreach ( $pf as $p ) {
	$b = rd( $p );
	if ( ! preg_match( $RE, $b ) ) { continue; }
	foreach ( preg_split( '/\r\n|\n|\r/', $b ) as $i => $l ) {
		if ( ! preg_match_all( $RE, $l, $m ) ) { continue; }
		$is_comment = (bool) preg_match( '/^\s*(\*|\/\/|\/\*)/', $l );
		foreach ( $m[0] as $form ) {
			$is_a = ( 0 === mb_stripos( $form, 'агентств' ) );
			printf( "  %-52s:%-4d «%s» — %s\n", $rel( $p ), $i + 1, $form,
				$is_a ? 'ОСТАВЛЕНО: «агентство»' : ( $is_comment ? 'ОСТАВЛЕНО: комментарий в PHP, на фронт не выводится' : 'подсказка админки — этап 4' ) );
		}
	}
}

echo "\n--- wp_options ---\n";
$n = 0;
foreach ( $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options}" ) as $o ) {
	if ( is_string( $o->option_value ) && preg_match_all( $RE, $o->option_value, $m ) ) {
		foreach ( $m[0] as $form ) { printf( "  wp_options.%s «%s»\n", $o->option_name, $form ); $n++; }
	}
}
if ( 0 === $n ) { echo "  (ни одного)\n"; }

printf( "\nостаточных «агент»-СОТРУДНИКОВ вне отложенного front-page.html: см. пометки выше\n" );

/* ------------------------------------------------------------------- 3.3 */
echo "\n================== 3.3. header.html: BOM, EOL, UTF-8 ==================\n";
$hp = $root . '/wp-content/themes/razil/parts/header.html';
$hb = rd( $hp );
$bb = rd( $root . '/_backup/t1/templates/header.html.before' );
$crlf = substr_count( $hb, chr( 13 ) . chr( 10 ) );
$lf   = substr_count( $hb, chr( 10 ) );
printf( "первые 4 байта, hex = %s\n", strtoupper( implode( ' ', str_split( bin2hex( substr( $hb, 0, 4 ) ), 2 ) ) ) );
printf( "UTF-8 BOM: %s | UTF-16 BOM: %s\n",
	0 === strncmp( $hb, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ), 3 ) ? 'ПОЯВИЛСЯ' : 'нет',
	( 0 === strncmp( $hb, chr( 0xFE ) . chr( 0xFF ), 2 ) || 0 === strncmp( $hb, chr( 0xFF ) . chr( 0xFE ), 2 ) ) ? 'ПОЯВИЛСЯ' : 'нет' );
printf( "CRLF=%d, всего LF=%d, одиночных LF=%d, одиночных CR=%d -> %s\n", $crlf, $lf, $lf - $crlf,
	substr_count( $hb, chr( 13 ) ) - $crlf, ( $crlf > 0 && 0 === $lf - $crlf ) ? 'CRLF' : 'НЕ CRLF' );
printf( "EOL до правки: CRLF=%d, одиночных LF=%d\n", substr_count( $bb, chr( 13 ) . chr( 10 ) ), substr_count( $bb, chr( 10 ) ) - substr_count( $bb, chr( 13 ) . chr( 10 ) ) );
printf( "валидный UTF-8: %s\n", mb_check_encoding( $hb, 'UTF-8' ) ? 'да' : 'НЕТ' );
printf( "размер %d -> %d байт, MD5 %s -> %s\n", strlen( $bb ), strlen( $hb ), md5( $bb ), md5( $hb ) );
if ( 0 !== $lf - $crlf || ! mb_check_encoding( $hb, 'UTF-8' ) ) { $fail[] = '3.3 header.html'; }

/* ------------------------------------------------------------------- 3.4 */
echo "\n================== 3.4. СБРОС КЭША ==================\n";
printf( "класс объектного кэша: %s\n", get_class( $GLOBALS['wp_object_cache'] ) );
printf( "wp_cache_flush() = %s -> объектный кэш сброшен целиком\n", var_export( wp_cache_flush(), true ) );
foreach ( array( 11, 12, 18, 19, 25 ) as $id ) { clean_post_cache( $id ); }
echo "clean_post_cache() по 11, 12, 18, 19, 25 -> записи и их мета сброшены\n";
wp_clean_themes_cache();
echo "wp_clean_themes_cache() -> кэш реестра тем сброшен (правился parts/header.html)\n";
if ( function_exists( 'wp_cache_flush_runtime' ) ) { wp_cache_flush_runtime(); echo "wp_cache_flush_runtime() -> рантайм-кэш сброшен\n"; }
if ( class_exists( 'WP_Theme_JSON_Resolver' ) && method_exists( 'WP_Theme_JSON_Resolver', 'clean_cached_data' ) ) {
	WP_Theme_JSON_Resolver::clean_cached_data();
	echo "WP_Theme_JSON_Resolver::clean_cached_data() -> кэш theme.json сброшен\n";
}

/* ------------------------------------------------------------------- 3.5 */
echo "\n================== 3.5. АБЗАЦ СЕКЦИИ «Сроки» ID=11 ЦЕЛИКОМ ==================\n";
$c   = $wpdb->get_var( "SELECT post_content FROM {$wpdb->posts} WHERE ID=11" );
$pos = strpos( $c, 'выезжает по Хабаровску круглосуточно' );
if ( false === $pos ) {
	echo "(опорная фраза не найдена)\n";
} else {
	$s = strrpos( substr( $c, 0, $pos ), '<p>' );
	$e = strpos( $c, '</p>', $pos );
	$para = substr( $c, $s, $e + 4 - $s );
	echo $para . "\n\n";
	printf( "длина абзаца: %d байт (strlen) / %d символов (mb_strlen)\n", strlen( $para ), mb_strlen( $para ) );
	$plain = plain_of( $para );
	printf( "вхождений «специалист» в любой форме: %d\n", preg_match_all( '/специалист\p{Cyrillic}*/iu', $plain, $mm ) );
	if ( ! empty( $mm[0] ) ) { printf( "  словоформы: %s\n", implode( ', ', array_map( function ( $x ) { return '«' . $x . '»'; }, $mm[0] ) ) ); }
	printf( "вхождений «агент» в любой форме: %d\n", preg_match_all( $RE, $plain, $mm2 ) );
	if ( ! empty( $mm2[0] ) ) { printf( "  словоформы: %s\n", implode( ', ', array_map( function ( $x ) { return '«' . $x . '»'; }, $mm2[0] ) ) ); }
}

echo "\n\n========================= ИТОГ =========================\n";
if ( $fail ) {
	echo "ЕСТЬ РАСХОЖДЕНИЯ:\n";
	foreach ( $fail as $f ) { echo "  - {$f}\n"; }
} else {
	echo "Все автоматические проверки пройдены, расхождений нет.\n";
}
exit( $fail ? 80 : 0 );
