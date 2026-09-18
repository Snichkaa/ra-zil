<?php
/**
 * 2.2, шаг 1 — dry-run по пяти записям. Только чтение.
 * Подготовленные строки складываются в _backup/t1/agent-prepared/<ID>.txt
 * и оттуда же берутся скриптом записи: то, что проверено, то и пишется.
 */
require dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb;

$MAP = require __DIR__ . '/ag-22-map.php';
$dir = dirname( __DIR__, 2 ) . '/_backup/t1/agent-prepared';
if ( ! is_dir( $dir ) ) { mkdir( $dir, 0777, true ); }

/** Предложение, в котором сидит позиция — по тексту без разметки. */
function sentence_at( $plain, $pos ) {
	$parts = preg_split( '/(?<=[.!?…])\s+/u', $plain, -1, PREG_SPLIT_OFFSET_CAPTURE );
	$best  = $plain;
	foreach ( $parts as $p ) {
		if ( $p[1] <= $pos ) { $best = $p[0]; } else { break; }
	}
	return trim( preg_replace( '/\s+/u', ' ', $best ) );
}

function plain_of( $html ) {
	$t = wp_strip_all_tags( preg_replace( '/<!--.*?-->/us', ' ', $html ) );
	return html_entity_decode( $t, ENT_QUOTES, 'UTF-8' );
}

$abort = false;

foreach ( $MAP as $id => $pairs ) {
	$c = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID=%d", $id ) );
	$t = $wpdb->get_var( $wpdb->prepare( "SELECT post_title FROM {$wpdb->posts} WHERE ID=%d", $id ) );

	echo str_repeat( '#', 78 ) . "\n";
	printf( "# ID=%d «%s» — замен: %d\n", $id, $t, count( $pairs ) );
	echo str_repeat( '#', 78 ) . "\n";
	printf( "post_content сейчас: %d байт (strlen) / %d символов (mb_strlen), md5=%s\n\n", strlen( $c ), mb_strlen( $c ), md5( $c ) );

	/* --- счётчики --- */
	echo "--- substr_count по каждой искомой строке ---\n";
	$bad = false;
	foreach ( $pairs as $k => $p ) {
		$n = substr_count( $c, $p[0] );
		printf( "  [%d] «%s» = %d %s\n", $k + 1, $p[0], $n, 1 === $n ? '' : '   <-- НЕ 1' );
		if ( 1 !== $n ) { $bad = true; }
	}
	/* Замена не должна уже присутствовать — иначе повторный прогон. */
	foreach ( $pairs as $k => $p ) {
		$n = substr_count( $c, $p[1] );
		if ( 0 !== $n ) { printf( "  ВНИМАНИЕ: строка замены «%s» уже встречается %d раз\n", $p[1], $n ); }
	}
	if ( $bad ) {
		printf( "\nСТОП по ID=%d: не все искомые строки встречаются ровно один раз. Объект пропущен.\n\n", $id );
		$abort = true;
		continue;
	}
	echo "все искомые строки встречаются ровно по одному разу\n";

	/* --- dry-run по предложениям --- */
	echo "\n--- предложения: было / стало ---\n";
	$plain_was = plain_of( $c );
	$new       = $c;
	foreach ( $pairs as $k => $p ) {
		// strpos, а не mb_strpos: PREG_SPLIT_OFFSET_CAPTURE отдаёт байтовые
		// смещения, смешивать их с символьными нельзя.
		$pos = strpos( $plain_was, $p[0] );
		$s_was = ( false !== $pos ) ? sentence_at( $plain_was, $pos ) : '(предложение не выделено: строка попала на границу разметки)';
		$s_new = str_replace( $p[0], $p[1], $s_was );
		printf( "[%d] замена: «%s» -> «%s»\n", $k + 1, $p[0], $p[1] );
		printf( "    было : %s\n", $s_was );
		printf( "    стало: %s\n\n", $s_new );
		$new = str_replace( $p[0], $p[1], $new );
	}

	/* --- дельты --- */
	$d_bytes = 0;
	$d_chars = 0;
	foreach ( $pairs as $p ) {
		$d_bytes += strlen( $p[1] ) - strlen( $p[0] );
		$d_chars += mb_strlen( $p[1] ) - mb_strlen( $p[0] );
	}
	echo "--- ожидаемые дельты ---\n";
	printf( "сумма дельт замен: %+d байт (strlen), %+d символов (mb_strlen)\n", $d_bytes, $d_chars );
	printf( "подготовленная строка: %d байт / %d символов, md5=%s\n", strlen( $new ), mb_strlen( $new ), md5( $new ) );
	printf( "фактическая дельта:    %+d байт (strlen), %+d символов (mb_strlen)  %s\n",
		strlen( $new ) - strlen( $c ), mb_strlen( $new ) - mb_strlen( $c ),
		( strlen( $new ) - strlen( $c ) === $d_bytes && mb_strlen( $new ) - mb_strlen( $c ) === $d_chars ) ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' );
	if ( strlen( $new ) - strlen( $c ) !== $d_bytes ) { $abort = true; }

	/* --- круговорот блоков --- */
	echo "\n--- круговорот parse_blocks -> serialize_blocks на подготовленной строке ---\n";
	$rt = serialize_blocks( parse_blocks( $new ) );
	printf( "подготовлено: %d байт md5=%s\n", strlen( $new ), md5( $new ) );
	printf( "круговорот:   %d байт md5=%s\n", strlen( $rt ), md5( $rt ) );
	printf( "побайтово: %s\n", $rt === $new ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ — писать нельзя' );
	if ( $rt !== $new ) { $abort = true; continue; }

	/* --- остаточные «агенты» --- */
	echo "\n--- что останется в этой записи после замен ---\n";
	$rest = array();
	if ( preg_match_all( '/агент\p{Cyrillic}*/iu', plain_of( $new ), $m ) ) {
		foreach ( $m[0] as $f ) { $rest[] = $f; }
	}
	if ( $rest ) {
		foreach ( array_count_values( $rest ) as $form => $n ) {
			printf( "  «%s» x%d %s\n", $form, $n, 0 === mb_stripos( $form, 'агентств' ) ? '(агентство — оставляем намеренно)' : '(СОТРУДНИК — проверить)' );
		}
	} else {
		echo "  (ни одного)\n";
	}

	$f = $dir . '/' . $id . '.txt';
	file_put_contents( $f, $new );
	clearstatcache( true, $f );
	printf( "\nподготовленная строка сохранена: %s (%d байт, md5 %s)\n\n", str_replace( chr( 92 ), '/', $f ), filesize( $f ), md5_file( $f ) );
}

echo str_repeat( '=', 78 ) . "\n";
echo 'ЗАПИСИ В БД НЕ ПРОИЗВОДИЛОСЬ. ' . ( $abort ? 'ЕСТЬ ПРОБЛЕМНЫЕ ОБЪЕКТЫ — см. выше.' : 'Все пять объектов готовы к записи.' ) . "\n";
exit( $abort ? 70 : 0 );
