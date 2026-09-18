<?php
/**
 * Этап 6 — финальная сводка по потоку 1. Отдельный процесс.
 */
require dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb;

$root = dirname( __DIR__, 2 );
$rel  = function ( $p ) use ( $root ) { return str_replace( str_replace( chr( 92 ), '/', $root ) . '/', '', str_replace( chr( 92 ), '/', $p ) ); };
function rd( $p ) { $fh = fopen( $p, 'rb' ); $b = stream_get_contents( $fh ); fclose( $fh ); return $b; }
function plain_of( $h ) { return html_entity_decode( wp_strip_all_tags( preg_replace( '/<!--.*?-->/us', ' ', $h ) ), ENT_QUOTES, 'UTF-8' ); }

$ETALON_TITLE = array(
	11 => 'Организация похорон в Хабаровске - Земля и Люди',
	12 => 'Кремация в Хабаровске - Земля и Люди',
	13 => 'Транспортировка умерших в Хабаровске - Земля и Люди',
	14 => 'Благоустройство мест захоронения в Хабаровске - Земля и Люди',
	15 => 'Юридическая помощь при оформлении документов после смерти - Земля и Люди',
);
/** post_name из самого первого бэкапа сессии. */
$BK1 = array();
foreach ( json_decode( file_get_contents( $root . '/_backup/t1/posts-11-15-before.json' ), true ) as $r ) { $BK1[ (int) $r['ID'] ] = $r; }
$BK2 = array();
foreach ( json_decode( file_get_contents( $root . '/_backup/t1/agent-pass-before.json' ), true ) as $r ) { $BK2[ (int) $r['ID'] ] = $r; }

$fail = array();
$RE   = '/агент\p{Cyrillic}*/iu';

wp_cache_flush();

echo "================== 6.1. АБЗАЦ «Сроки» ID=11 ЦЕЛИКОМ ==================\n";
$c   = $wpdb->get_var( "SELECT post_content FROM {$wpdb->posts} WHERE ID=11" );
$pos = strpos( $c, 'выезжает по Хабаровску круглосуточно' );
$s   = strrpos( substr( $c, 0, $pos ), '<p>' );
$e   = strpos( $c, '</p>', $pos );
$para = substr( $c, $s, $e + 4 - $s );
echo $para . "\n\n";
printf( "длина: %d байт (strlen) / %d символов (mb_strlen)\n", strlen( $para ), mb_strlen( $para ) );
$pl = plain_of( $para );
printf( "«специалист» в любой форме: %d", preg_match_all( '/специалист\p{Cyrillic}*/iu', $pl, $m ) );
echo empty( $m[0] ) ? "\n" : '  (' . implode( ', ', $m[0] ) . ")\n";
printf( "«агент» в любой форме: %d\n", preg_match_all( $RE, $pl ) );

echo "\n================== 6.2. <title> 11-15 ==================\n";
foreach ( array( 11, 12, 13, 14, 15 ) as $id ) {
	wp_cache_flush();
	$got  = YoastSEO()->meta->for_post( $id )->title;
	$same = ( $got === $ETALON_TITLE[ $id ] );
	printf( "ID=%-3s [%s]\n", $id, $got );
	printf( "       эталон: [%s]  %s\n", $ETALON_TITLE[ $id ], $same ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' );
	if ( ! $same ) { $fail[] = "<title> ID={$id}"; }
}

echo "\n================== 6.3. post_name ==================\n";
printf( "%-5s %-26s %-26s %-22s %s\n", 'ID', 'эталон из бэкапа', 'сейчас', 'бэкап', 'вердикт' );
echo str_repeat( '-', 110 ) . "\n";
foreach ( array( 11, 12, 13, 14, 15, 18, 19, 25 ) as $id ) {
	$now = $wpdb->get_var( $wpdb->prepare( "SELECT post_name FROM {$wpdb->posts} WHERE ID=%d", $id ) );
	if ( isset( $BK1[ $id ] ) )      { $exp = $BK1[ $id ]['post_name']; $src = 'posts-11-15-before'; }
	elseif ( isset( $BK2[ $id ] ) )  { $exp = $BK2[ $id ]['post_name']; $src = 'agent-pass-before'; }
	else                             { $exp = '(нет бэкапа)'; $src = '—'; }
	$same = ( $now === $exp );
	printf( "%-5s %-26s %-26s %-22s %s\n", $id, $exp, $now, $src, $same ? 'не изменился' : 'ИЗМЕНИЛСЯ' );
	if ( ! $same ) { $fail[] = "post_name ID={$id}"; }
}

echo "\n================== 6.4. ОСТАВШИЕСЯ «агент» ==================\n";
$worker_left = array();

echo "--- БД: post_content + post_excerpt, publish, без ревизий ---\n";
$n = 0;
foreach ( $wpdb->get_results( "SELECT ID, post_type, post_title, post_content, post_excerpt FROM {$wpdb->posts} WHERE post_type <> 'revision' AND post_status='publish' ORDER BY ID" ) as $r ) {
	foreach ( array( 'post_content', 'post_excerpt' ) as $f ) {
		if ( ! preg_match_all( $RE, plain_of( $r->$f ), $m ) ) { continue; }
		foreach ( $m[0] as $form ) {
			$is_a = ( 0 === mb_stripos( $form, 'агентств' ) );
			if ( ! $is_a ) { $worker_left[] = "БД ID={$r->ID}.{$f} «{$form}»"; }
			printf( "  ID=%-4s %-13s «%s» — %s\n", $r->ID, $f, $form, $is_a ? 'ОСТАВЛЕНО: «агентство», род деятельности' : 'СОТРУДНИК' );
			$n++;
		}
	}
}
printf( "  итого: %d\n", $n );

echo "\n--- файлы темы (.html, .php) ---\n";
$tf = array();
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/wp-content/themes/razil', FilesystemIterator::SKIP_DOTS ) );
foreach ( $it as $f ) { if ( $f->isFile() && in_array( strtolower( $f->getExtension() ), array( 'html', 'php' ), true ) ) { $tf[] = $f->getPathname(); } }
sort( $tf );
$n = 0;
foreach ( $tf as $p ) {
	$b = rd( $p );
	if ( ! preg_match( $RE, $b ) ) { continue; }
	foreach ( preg_split( '/\r\n|\n|\r/', $b ) as $i => $l ) {
		if ( ! preg_match_all( $RE, $l, $m ) ) { continue; }
		foreach ( $m[0] as $form ) {
			$is_a  = ( 0 === mb_stripos( $form, 'агентств' ) );
			$is_fp = ( false !== strpos( $rel( $p ), 'front-page.html' ) );
			if ( ! $is_a && ! $is_fp ) { $worker_left[] = $rel( $p ) . ":{$i}" . " «{$form}»"; }
			printf( "  %-52s:%-4d «%s» — %s\n", $rel( $p ), $i + 1, $form,
				$is_a ? 'ОСТАВЛЕНО: «агентство»' : ( $is_fp ? 'ОСТАВЛЕНО: front-page.html вне зоны' : 'СОТРУДНИК' ) );
			$n++;
		}
	}
}
printf( "  итого: %d\n", $n );

echo "\n--- PHP razil-core ---\n";
$pf = array();
$it2 = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/wp-content/plugins/razil-core', FilesystemIterator::SKIP_DOTS ) );
foreach ( $it2 as $f ) { if ( $f->isFile() && 'php' === strtolower( $f->getExtension() ) ) { $pf[] = $f->getPathname(); } }
sort( $pf );
$n = 0;
foreach ( $pf as $p ) {
	$b = rd( $p );
	if ( ! preg_match( $RE, $b ) ) { continue; }
	foreach ( preg_split( '/\r\n|\n|\r/', $b ) as $i => $l ) {
		if ( ! preg_match_all( $RE, $l, $m ) ) { continue; }
		$is_comment = (bool) preg_match( '/^\s*(\*|\/\/|\/\*)/', $l );
		foreach ( $m[0] as $form ) {
			$is_a = ( 0 === mb_stripos( $form, 'агентств' ) );
			if ( ! $is_a && ! $is_comment ) { $worker_left[] = $rel( $p ) . ':' . ( $i + 1 ) . " «{$form}»"; }
			printf( "  %-52s:%-4d «%s» — %s\n", $rel( $p ), $i + 1, $form,
				$is_a ? 'ОСТАВЛЕНО: «агентство»' : ( $is_comment ? 'ОСТАВЛЕНО: комментарий в PHP' : 'СОТРУДНИК В КОДЕ' ) );
			$n++;
		}
	}
}
printf( "  итого: %d\n", $n );

echo "\n--- wp_options ---\n";
$n = 0;
foreach ( $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options}" ) as $o ) {
	if ( is_string( $o->option_value ) && preg_match_all( $RE, $o->option_value, $m ) ) {
		foreach ( $m[0] as $form ) { printf( "  wp_options.%s «%s»\n", $o->option_name, $form ); $n++; }
	}
}
if ( 0 === $n ) { echo "  (ни одного)\n"; }

echo "\nСОТРУДНИКОВ-«агентов» вне front-page.html и вне комментариев PHP: " . count( $worker_left ) . "\n";
foreach ( $worker_left as $w ) { echo "  - {$w}\n"; }

echo "\n================== 6.5. ПОРЯДОК КАРТОЧЕК ==================\n";
$tpl = rd( $root . '/wp-content/themes/razil/templates/archive-services.html' );
$q   = null;
$find_q = function ( $bs ) use ( &$find_q ) {
	foreach ( $bs as $b ) {
		if ( 'core/query' === $b['blockName'] ) { return $b; }
		$r = $find_q( $b['innerBlocks'] );
		if ( $r ) { return $r; }
	}
	return null;
};
$q = $find_q( parse_blocks( $tpl ) );
echo 'параметры из блока: ' . json_encode( $q['attrs']['query'], JSON_UNESCAPED_UNICODE ) . "\n\n";
$wpq = new WP_Query( array(
	'post_type'      => $q['attrs']['query']['postType'],
	'posts_per_page' => $q['attrs']['query']['perPage'],
	'orderby'        => $q['attrs']['query']['orderBy'],
	'order'          => strtoupper( $q['attrs']['query']['order'] ),
	'post_status'    => 'publish',
) );
$got = array();
$i   = 1;
foreach ( $wpq->posts as $p ) { printf( "%d) ID=%-4s menu_order=%-4s %s\n", $i++, $p->ID, $p->menu_order, $p->post_title ); $got[] = (int) $p->ID; }
$expect = array( 11, 12, 14, 13, 15 );
printf( "\nполучено: %s | ожидалось: %s | %s\n", implode( ', ', $got ), implode( ', ', $expect ), $got === $expect ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' );
if ( $got !== $expect ) { $fail[] = 'порядок карточек'; }

echo "\n================== 6.6. ИЗМЕНЕНО ПОТОКОМ 1 ==================\n";
echo "--- файлы ---\n";
$mine = array(
	'wp-content/themes/razil/parts/footer.html'               => 'текст пункта меню',
	'wp-content/themes/razil/parts/header.html'               => 'текст кнопки',
	'wp-content/themes/razil/templates/archive-services.html' => 'orderBy запроса + лид',
	'wp-content/themes/razil/templates/single-services.html'  => 'лид',
	'wp-content/plugins/razil-core/inc/org-settings.php'      => 'подсказка админки',
);
foreach ( $mine as $f => $what ) {
	$b = rd( $root . '/' . $f );
	$crlf = substr_count( $b, chr( 13 ) . chr( 10 ) );
	$lf   = substr_count( $b, chr( 10 ) );
	printf( "%-56s %-8s MD5 %s  EOL %s  BOM %s  — %s\n", $f, strlen( $b ), md5( $b ),
		( $crlf > 0 && 0 === $lf - $crlf ) ? 'CRLF' : ( ( 0 === $crlf ) ? 'LF' : 'MIX' ),
		0 === strncmp( $b, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ), 3 ) ? 'ЕСТЬ' : 'нет', $what );
}

echo "\n--- объекты БД ---\n";
foreach ( array( 11, 12, 13, 15, 18, 19, 25 ) as $id ) {
	$r = $wpdb->get_row( $wpdb->prepare( "SELECT post_title, post_name, post_content, post_excerpt, menu_order FROM {$wpdb->posts} WHERE ID=%d", $id ), ARRAY_A );
	printf( "ID=%-4s content md5=%s (%d байт)  excerpt md5=%s  menu_order=%s\n", $id, md5( $r['post_content'] ), strlen( $r['post_content'] ), md5( $r['post_excerpt'] ), $r['menu_order'] );
}
echo "ID=14  затронут только menu_order: " . $wpdb->get_var( "SELECT menu_order FROM {$wpdb->posts} WHERE ID=14" ) . "\n";
echo "postmeta: _yoast_wpseo_title у 11, 13, 15:\n";
foreach ( array( 11, 13, 15 ) as $id ) {
	printf( "  ID=%-4s [%s]\n", $id, $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='_yoast_wpseo_title'", $id ) ) );
}

echo "\n--- бэкапы в _backup/t1/ ---\n";
$bdir = $root . '/_backup/t1';
$list = array();
$itb  = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $bdir, FilesystemIterator::SKIP_DOTS ) );
foreach ( $itb as $f ) { if ( $f->isFile() ) { $list[] = $f->getPathname(); } }
sort( $list );
foreach ( $list as $f ) {
	printf( "%-58s %-9s MD5 %s\n", $rel( $f ), filesize( $f ), md5_file( $f ) );
}
printf( "всего файлов бэкапа: %d\n", count( $list ) );

echo "\n================== 6.8. СБРОС КЭША ==================\n";
printf( "wp_cache_flush() = %s -> объектный кэш (%s)\n", var_export( wp_cache_flush(), true ), get_class( $GLOBALS['wp_object_cache'] ) );
clean_post_cache( 11 );
echo "clean_post_cache(11) -> запись и её мета\n";
wp_clean_themes_cache();
echo "wp_clean_themes_cache() -> кэш реестра тем\n";
if ( function_exists( 'wp_cache_flush_runtime' ) ) { wp_cache_flush_runtime(); echo "wp_cache_flush_runtime() -> рантайм-кэш\n"; }
if ( class_exists( 'WP_Theme_JSON_Resolver' ) && method_exists( 'WP_Theme_JSON_Resolver', 'clean_cached_data' ) ) {
	WP_Theme_JSON_Resolver::clean_cached_data();
	echo "WP_Theme_JSON_Resolver::clean_cached_data() -> кэш theme.json\n";
}
echo "\nopcache:\n";
printf( "  extension_loaded('Zend OPcache') = %s\n", var_export( extension_loaded( 'Zend OPcache' ), true ) );
printf( "  function_exists('opcache_reset') = %s\n", var_export( function_exists( 'opcache_reset' ), true ) );
if ( function_exists( 'opcache_get_status' ) ) {
	$st = @opcache_get_status( false );
	printf( "  opcache_get_status() = %s\n", false === $st ? 'false (в CLI кэш не включён)' : 'массив, enabled=' . var_export( $st['opcache_enabled'], true ) );
}
if ( function_exists( 'opcache_reset' ) ) {
	$r = @opcache_reset();
	printf( "  opcache_reset() вернул = %s\n", var_export( $r, true ) );
} else {
	echo "  opcache_reset() недоступен\n";
}
echo "  ВАЖНО: этот процесс — CLI (php_sapi_name = " . php_sapi_name() . "). У CLI отдельный\n";
echo "  экземпляр OPcache; сброс здесь НЕ очищает кэш веб-сервера. Для веба нужен\n";
echo "  перезапуск PHP-FPM/Apache либо вызов opcache_reset() из HTTP-запроса.\n";

echo "\n\n========================= ИТОГ =========================\n";
if ( $fail ) {
	echo "ЕСТЬ РАСХОЖДЕНИЯ:\n";
	foreach ( $fail as $f ) { echo "  - {$f}\n"; }
} else {
	echo "Все автоматические проверки пройдены, расхождений нет.\n";
}
exit( $fail ? 120 : 0 );
