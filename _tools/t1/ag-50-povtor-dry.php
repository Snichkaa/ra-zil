<?php
/**
 * Этап 5, шаг 1 — снимок и dry-run. Только чтение.
 */
require dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb;

$FIND = 'Специалист незамедлительно сообщит вам об этом.';
$REPL = 'Мы незамедлительно сообщим вам об этом.';

$root = dirname( __DIR__, 2 );

/* --- снимок --- */
$r = $wpdb->get_row( "SELECT ID, post_title, post_name, post_content, post_excerpt FROM {$wpdb->posts} WHERE ID=11", ARRAY_A );
$r['ID'] = (int) $r['ID'];
$snap = $root . '/_backup/t1/post-11-before-povtor.json';
file_put_contents( $snap, json_encode( $r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
clearstatcache( true, $snap );

echo "=== СНИМОК ===\n";
echo 'файл    = ' . str_replace( chr( 92 ), '/', $snap ) . "\n";
echo 'размер  = ' . filesize( $snap ) . " байт\n";
echo 'MD5     = ' . md5_file( $snap ) . "\n";
echo 'SHA-256 = ' . hash_file( 'sha256', $snap ) . "\n";
$back = json_decode( file_get_contents( $snap ), true );
echo 'round-trip с БД: ' . ( $back === $r ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' ) . "\n";
if ( $back !== $r ) { exit( 100 ); }

$c = $r['post_content'];
printf( "\npost_content: %d байт (strlen) / %d символов (mb_strlen), md5=%s\n", strlen( $c ), mb_strlen( $c ), md5( $c ) );

echo "\n=== DRY-RUN ===\n";
printf( "искомое: [%s]\n  %d байт (strlen) / %d символов (mb_strlen)\n", $FIND, strlen( $FIND ), mb_strlen( $FIND ) );
printf( "замена:  [%s]\n  %d байт (strlen) / %d символов (mb_strlen)\n", $REPL, strlen( $REPL ), mb_strlen( $REPL ) );
$n = substr_count( $c, $FIND );
printf( "substr_count = %d\n", $n );
if ( 1 !== $n ) { printf( "СТОП: ожидалось 1, найдено %d.\n", $n ); exit( 101 ); }
echo "ровно одно вхождение — условие выполнено\n";

/* --- абзац целиком --- */
$pos = strpos( $c, $FIND );
$s   = strrpos( substr( $c, 0, $pos ), '<p>' );
$e   = strpos( $c, '</p>', $pos );
$para_was = substr( $c, $s, $e + 4 - $s );
$para_new = str_replace( $FIND, $REPL, $para_was );

echo "\n=== АБЗАЦ ЦЕЛИКОМ, БЫЛО ===\n" . $para_was . "\n";
printf( "(%d байт / %d символов)\n", strlen( $para_was ), mb_strlen( $para_was ) );
echo "\n=== АБЗАЦ ЦЕЛИКОМ, СТАНЕТ ===\n" . $para_new . "\n";
printf( "(%d байт / %d символов)\n", strlen( $para_new ), mb_strlen( $para_new ) );

echo "\n=== границы блока ===\n";
$cs = max( 0, $s - 40 );
echo substr( $c, $cs, ( $e + 4 - $cs ) + 30 ) . "\n";

echo "\n=== ДЕЛЬТА ===\n";
printf( "дельта подстроки: %+d байт (strlen), %+d символов (mb_strlen)\n",
	strlen( $REPL ) - strlen( $FIND ), mb_strlen( $REPL ) - mb_strlen( $FIND ) );
$new = str_replace( $FIND, $REPL, $c );
printf( "post_content станет: %d байт / %d символов, md5=%s\n", strlen( $new ), mb_strlen( $new ), md5( $new ) );
printf( "дельта post_content: %+d байт (strlen), %+d символов (mb_strlen)\n",
	strlen( $new ) - strlen( $c ), mb_strlen( $new ) - mb_strlen( $c ) );
printf( "дельты совпадают: %s\n",
	( strlen( $new ) - strlen( $c ) === strlen( $REPL ) - strlen( $FIND )
	  && mb_strlen( $new ) - mb_strlen( $c ) === mb_strlen( $REPL ) - mb_strlen( $FIND ) ) ? 'да' : 'НЕТ' );

echo "\n=== КРУГОВОРОТ parse_blocks -> serialize_blocks на подготовленной строке ===\n";
$rt = serialize_blocks( parse_blocks( $new ) );
printf( "подготовлено: %d байт md5=%s\n", strlen( $new ), md5( $new ) );
printf( "круговорот:   %d байт md5=%s\n", strlen( $rt ), md5( $rt ) );
printf( "побайтово: %s\n", $rt === $new ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ — писать нельзя' );
if ( $rt !== $new ) { exit( 102 ); }

echo "\n=== СЛОВОФОРМЫ В АБЗАЦЕ ПОСЛЕ ЗАМЕНЫ ===\n";
$plain = html_entity_decode( wp_strip_all_tags( $para_new ), ENT_QUOTES, 'UTF-8' );
printf( "«специалист» в любой форме: %d\n", preg_match_all( '/специалист\p{Cyrillic}*/iu', $plain, $m ) );
if ( ! empty( $m[0] ) ) { printf( "  %s\n", implode( ', ', array_map( function ( $x ) { return '«' . $x . '»'; }, $m[0] ) ) ); }
printf( "«агент» в любой форме: %d\n", preg_match_all( '/агент\p{Cyrillic}*/iu', $plain ) );

$f = $root . '/_backup/t1/agent-prepared/11-povtor.txt';
file_put_contents( $f, $new );
clearstatcache( true, $f );
printf( "\nподготовленная строка сохранена: %s (%d байт, md5 %s)\n", str_replace( chr( 92 ), '/', $f ), filesize( $f ), md5_file( $f ) );

printf( "\nревизий у ID=11 сейчас: %d (лимит WP_POST_REVISIONS = %s)\n",
	(int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='revision' AND post_parent=11" ),
	defined( 'WP_POST_REVISIONS' ) ? WP_POST_REVISIONS : '(не определена)' );
echo "самая старая ревизия ID=11 будет вытеснена этой записью:\n";
$old = $wpdb->get_row( "SELECT ID, post_date FROM {$wpdb->posts} WHERE post_type='revision' AND post_parent=11 ORDER BY ID ASC LIMIT 1" );
printf( "  rev ID=%s от %s\n", $old->ID, $old->post_date );

echo "\nЗАПИСИ В БД НЕ ПРОИЗВОДИЛОСЬ.\n";
