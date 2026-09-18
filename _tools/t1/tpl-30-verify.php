<?php
/**
 * Этап 3 — проверка после правок в файлах темы.
 */
require dirname(__DIR__, 2) . '/wp-load.php';

$root    = dirname( __DIR__, 2 );
$bak_dir = $root . '/_backup/t1/templates';

$FILES = array(
	'parts/footer.html'               => array( 'path' => $root . '/wp-content/themes/razil/parts/footer.html',               'bak' => $bak_dir . '/footer.html.before',               'eol' => 'CRLF' ),
	'templates/archive-services.html' => array( 'path' => $root . '/wp-content/themes/razil/templates/archive-services.html', 'bak' => $bak_dir . '/archive-services.html.before', 'eol' => 'CRLF' ),
	'templates/single-services.html'  => array( 'path' => $root . '/wp-content/themes/razil/templates/single-services.html',  'bak' => $bak_dir . '/single-services.html.before',  'eol' => 'LF' ),
);

function rd( $p ) { $fh = fopen( $p, 'rb' ); $b = stream_get_contents( $fh ); fclose( $fh ); return $b; }
function eol_of( $b ) {
	$crlf = substr_count( $b, chr( 13 ) . chr( 10 ) );
	$lf   = substr_count( $b, chr( 10 ) );
	if ( $crlf > 0 && $lf - $crlf === 0 ) { return 'CRLF'; }
	if ( 0 === $crlf && $lf > 0 ) { return 'LF'; }
	return 'СМЕШАННЫЕ';
}

$fail = array();

echo "================== 1. BOM, переводы строк, кодировка ==================\n";
foreach ( $FILES as $label => $f ) {
	$b   = rd( $f['path'] );
	$bb  = rd( $f['bak'] );
	echo str_repeat( '-', 78 ) . "\n";
	echo "{$label}\n";
	printf( "  первые 4 байта, hex = %s\n", strtoupper( implode( ' ', str_split( bin2hex( substr( $b, 0, 4 ) ), 2 ) ) ) );
	$bom8  = 0 === strncmp( $b, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ), 3 );
	$bom16 = ( 0 === strncmp( $b, chr( 0xFE ) . chr( 0xFF ), 2 ) ) || ( 0 === strncmp( $b, chr( 0xFF ) . chr( 0xFE ), 2 ) );
	printf( "  UTF-8 BOM: %s | UTF-16 BOM: %s\n", $bom8 ? 'ПОЯВИЛСЯ' : 'нет', $bom16 ? 'ПОЯВИЛСЯ' : 'нет' );
	if ( $bom8 || $bom16 ) { $fail[] = "1: BOM в {$label}"; }

	$crlf = substr_count( $b, chr( 13 ) . chr( 10 ) );
	$lf   = substr_count( $b, chr( 10 ) );
	printf( "  CRLF=%d, всего LF=%d, одиночных LF=%d, одиночных CR=%d\n",
		$crlf, $lf, $lf - $crlf, substr_count( $b, chr( 13 ) ) - $crlf );
	printf( "  EOL: до правки %s -> после %s (ожидалось %s)  %s\n",
		eol_of( $bb ), eol_of( $b ), $f['eol'],
		( eol_of( $b ) === eol_of( $bb ) && eol_of( $b ) === $f['eol'] ) ? 'СОХРАНЁН' : 'ИЗМЕНИЛСЯ' );
	if ( eol_of( $b ) !== eol_of( $bb ) || eol_of( $b ) !== $f['eol'] ) { $fail[] = "1: EOL в {$label}"; }

	printf( "  валидный UTF-8: %s\n", mb_check_encoding( $b, 'UTF-8' ) ? 'да' : 'НЕТ' );
	if ( ! mb_check_encoding( $b, 'UTF-8' ) ) { $fail[] = "1: не UTF-8 в {$label}"; }
	printf( "  последний байт: %s%s\n", strtoupper( bin2hex( substr( $b, -1 ) ) ), "\n" === substr( $b, -1 ) ? ' (перевод строки на месте)' : ' (ПЕРЕВОДА СТРОКИ НЕТ)' );
	printf( "  размер: %d -> %d байт, MD5 %s -> %s\n", strlen( $bb ), strlen( $b ), md5( $bb ), md5( $b ) );
}

echo "\n================== 2. parse_blocks ==================\n";
$walk = function ( $blocks, $d = 0 ) use ( &$walk ) {
	foreach ( $blocks as $b ) {
		if ( null === $b['blockName'] ) { continue; }
		$cls = isset( $b['attrs']['className'] ) ? ' .' . $b['attrs']['className'] : '';
		$txt = preg_replace( '/\s+/u', ' ', trim( wp_strip_all_tags( $b['innerHTML'] ) ) );
		echo str_repeat( '  ', $d ) . '- ' . $b['blockName'] . $cls;
		if ( '' !== $txt ) { echo '  «' . mb_substr( $txt, 0, 70 ) . '»'; }
		echo "\n";
		$walk( $b['innerBlocks'], $d + 1 );
	}
};
$names = function ( $blocks ) use ( &$names ) {
	$out = array();
	foreach ( $blocks as $b ) {
		if ( null !== $b['blockName'] ) { $out[] = $b['blockName']; }
		$out = array_merge( $out, $names( $b['innerBlocks'] ) );
	}
	return $out;
};

foreach ( array( 'templates/archive-services.html', 'templates/single-services.html' ) as $label ) {
	$b   = rd( $FILES[ $label ]['path'] );
	$blk = parse_blocks( $b );
	echo str_repeat( '-', 78 ) . "\n";
	echo "{$label}\n";
	printf( "блоков верхнего уровня (включая пустые html-узлы): %d\n", count( $blk ) );
	echo "--- дерево ---\n";
	$walk( $blk );
	$all = $names( $blk );
	printf( "\nвсего именованных блоков: %d\n", count( $all ) );
	echo 'список: ' . implode( ', ', $all ) . "\n";

	echo "--- круговорот parse_blocks -> serialize_blocks ---\n";
	$rt = serialize_blocks( $blk );
	printf( "исходник %d байт md5=%s | после круговорота %d байт md5=%s | побайтово: %s\n",
		strlen( $b ), md5( $b ), strlen( $rt ), md5( $rt ), $rt === $b ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' );
	if ( $rt !== $b ) { $fail[] = "2: круговорот не побайтовый в {$label}"; }
}

echo "\n--- 2a. archive: цепочка wp:query -> wp:post-template -> .rz-service-card ---\n";
$ab  = parse_blocks( rd( $FILES['templates/archive-services.html']['path'] ) );
$find = function ( $blocks, $name, $cls = null ) use ( &$find ) {
	foreach ( $blocks as $b ) {
		if ( $b['blockName'] === $name && ( null === $cls || ( isset( $b['attrs']['className'] ) && $b['attrs']['className'] === $cls ) ) ) { return $b; }
		$r = $find( $b['innerBlocks'], $name, $cls );
		if ( $r ) { return $r; }
	}
	return null;
};
$q = $find( $ab, 'core/query' );
printf( "core/query найден: %s\n", $q ? 'да' : 'НЕТ' );
$pt = $q ? $find( array( $q ), 'core/post-template' ) : null;
printf( "  core/post-template внутри core/query: %s\n", $pt ? 'да' : 'НЕТ' );
$card = $pt ? $find( array( $pt ), 'core/group', 'rz-service-card' ) : null;
printf( "  core/group .rz-service-card внутри post-template: %s\n", $card ? 'да' : 'НЕТ' );
if ( $card ) {
	printf( "    вложенные блоки карточки: %s\n", implode( ', ', $names( array( $card ) ) ) );
}
if ( ! $q || ! $pt || ! $card ) { $fail[] = '2a: цепочка query -> post-template -> rz-service-card нарушена'; }

echo "\n--- 2b. single: post-title, post-excerpt, post-content на месте ---\n";
$sb  = parse_blocks( rd( $FILES['templates/single-services.html']['path'] ) );
$sn  = $names( $sb );
foreach ( array( 'core/post-title', 'core/post-excerpt', 'core/post-content' ) as $need ) {
	$c = count( array_keys( $sn, $need, true ) );
	printf( "  %-22s вхождений в дереве = %d %s\n", $need, $c, 1 === $c ? '(на месте)' : '(ОЖИДАЛОСЬ 1)' );
	if ( 1 !== $c ) { $fail[] = "2b: {$need} в single-services.html — {$c} шт."; }
}
$h1 = $find( $sb, 'core/post-title' );
printf( "  core/post-title attrs = %s (level 1 = H1)\n", json_encode( $h1['attrs'], JSON_UNESCAPED_UNICODE ) );
$pe = $find( $sb, 'core/post-excerpt' );
printf( "  core/post-excerpt attrs = %s\n", json_encode( $pe['attrs'], JSON_UNESCAPED_UNICODE ) );

echo "\n================== 3. атрибуты wp:query до и после ==================\n";
$q_bak = $find( parse_blocks( rd( $FILES['templates/archive-services.html']['bak'] ) ), 'core/query' );
$a_was = $q_bak['attrs'];
$a_now = $q['attrs'];
echo "ДО:\n"    . json_encode( $a_was, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";
echo "ПОСЛЕ:\n" . json_encode( $a_now, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n";

echo "\n--- поключевое сравнение ---\n";
$keys = array_unique( array_merge( array_keys( $a_was ), array_keys( $a_now ) ) );
$changed = array();
foreach ( $keys as $k ) {
	if ( 'query' === $k ) { continue; }
	$x = array_key_exists( $k, $a_was ) ? json_encode( $a_was[ $k ], JSON_UNESCAPED_UNICODE ) : '(нет ключа)';
	$y = array_key_exists( $k, $a_now ) ? json_encode( $a_now[ $k ], JSON_UNESCAPED_UNICODE ) : '(нет ключа)';
	printf( "  %-12s было=%-14s стало=%-14s %s\n", $k, $x, $y, $x === $y ? '' : '<-- ИЗМЕНИЛСЯ' );
	if ( $x !== $y ) { $changed[] = $k; }
}
$qk = array_unique( array_merge( array_keys( $a_was['query'] ), array_keys( $a_now['query'] ) ) );
foreach ( $qk as $k ) {
	$x = array_key_exists( $k, $a_was['query'] ) ? json_encode( $a_was['query'][ $k ], JSON_UNESCAPED_UNICODE ) : '(нет ключа)';
	$y = array_key_exists( $k, $a_now['query'] ) ? json_encode( $a_now['query'][ $k ], JSON_UNESCAPED_UNICODE ) : '(нет ключа)';
	printf( "  query.%-10s было=%-14s стало=%-14s %s\n", $k, $x, $y, $x === $y ? '' : '<-- ИЗМЕНИЛСЯ' );
	if ( $x !== $y ) { $changed[] = 'query.' . $k; }
}
printf( "\nизменённых атрибутов: %d -> %s\n", count( $changed ), $changed ? implode( ', ', $changed ) : '(ни одного)' );
if ( array( 'query.orderBy' ) !== $changed ) { $fail[] = '3: изменён не ровно один атрибут (' . implode( ', ', $changed ) . ')'; }

echo "\n================== 4. сброс кэша ==================\n";
printf( "класс объектного кэша: %s\n", get_class( $GLOBALS['wp_object_cache'] ) );
printf( "wp_cache_flush() вернул = %s -> сброшен объектный кэш целиком\n", var_export( wp_cache_flush(), true ) );
foreach ( array( 11, 12, 13, 14, 15 ) as $id ) { clean_post_cache( $id ); }
echo "clean_post_cache() по 11,12,13,14,15 -> сброшены записи и их мета\n";
if ( function_exists( 'wp_cache_flush_runtime' ) ) { wp_cache_flush_runtime(); echo "wp_cache_flush_runtime() -> сброшен рантайм-кэш\n"; }
// Кэш файловых шаблонов FSE.
wp_cache_delete( get_stylesheet() . '-blocks', 'theme_json' );
wp_clean_themes_cache();
echo "wp_clean_themes_cache() -> сброшен кэш реестра тем\n";
if ( class_exists( 'WP_Theme_JSON_Resolver' ) && method_exists( 'WP_Theme_JSON_Resolver', 'clean_cached_data' ) ) {
	WP_Theme_JSON_Resolver::clean_cached_data();
	echo "WP_Theme_JSON_Resolver::clean_cached_data() -> сброшен кэш theme.json\n";
}
$tr = $GLOBALS['wpdb']->get_col( "SELECT option_name FROM {$GLOBALS['wpdb']->options} WHERE option_name LIKE '_transient_wp_theme_files%' OR option_name LIKE '_transient_%template%'" );
printf( "транзиентов шаблонов в wp_options: %d %s\n", count( $tr ), $tr ? '(' . implode( ', ', $tr ) . ')' : '' );
echo "ПРИМЕЧАНИЕ: шаблоны FSE этой темы лежат файлами (wp_template в БД = 0 строк),\n";
echo "их разбор не кэшируется между запросами — правка файла видна сразу.\n";

echo "\n================== 5. порядок карточек с новыми параметрами ==================\n";
echo "параметры взяты из атрибутов блока после правки: postType=services, orderBy=menu_order, order=asc, perPage=9\n\n";
$wpq = new WP_Query( array(
	'post_type'      => $a_now['query']['postType'],
	'posts_per_page' => $a_now['query']['perPage'],
	'orderby'        => $a_now['query']['orderBy'],
	'order'          => strtoupper( $a_now['query']['order'] ),
	'post_status'    => 'publish',
) );
$got = array();
$i   = 1;
foreach ( $wpq->posts as $p ) {
	printf( "%d) ID=%-4s menu_order=%-4s %s\n", $i++, $p->ID, $p->menu_order, $p->post_title );
	$got[] = (int) $p->ID;
}
$expect = array( 11, 12, 14, 13, 15 );
printf( "\nполучено: %s\n", implode( ', ', $got ) );
printf( "ожидалось: %s\n", implode( ', ', $expect ) );
printf( "ВЕРДИКТ: %s\n", $got === $expect ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' );
if ( $got !== $expect ) { $fail[] = '5: порядок карточек не совпал'; }

echo "\n\n========================= ИТОГ =========================\n";
if ( $fail ) {
	echo "ЕСТЬ РАСХОЖДЕНИЯ:\n";
	foreach ( $fail as $f ) { echo "  - {$f}\n"; }
} else {
	echo "Все проверки пройдены, расхождений нет.\n";
}
exit( $fail ? 50 : 0 );
