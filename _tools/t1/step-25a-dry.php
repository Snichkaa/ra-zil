<?php
/**
 * Этап 2.5, шаг 1 — dry-run замены в post_content ID=11. Только чтение.
 * Единица измерения длины назначается явно и используется с обеих сторон.
 */
require dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb;

$FIND    = 'Ритуальный агент выезжает по Хабаровску круглосуточно';
$REPLACE = 'Наш специалист выезжает по Хабаровску круглосуточно';

$c = $wpdb->get_var( "SELECT post_content FROM {$wpdb->posts} WHERE ID=11" );

echo "=== DRY-RUN 2.5: post_content ID=11 ===\n\n";
printf( "post_content сейчас: %d байт (strlen) / %d символов (mb_strlen), md5=%s\n\n", strlen( $c ), mb_strlen( $c ), md5( $c ) );

echo "=== счётчики вхождений ===\n";
$n_find = substr_count( $c, $FIND );
printf( "substr_count(post_content, «%s») = %d\n", $FIND, $n_find );
printf( "substr_count(post_content, «Ритуальный агент») = %d\n", substr_count( $c, 'Ритуальный агент' ) );
printf( "substr_count(post_content, «Наш специалист») = %d\n", substr_count( $c, 'Наш специалист' ) );
printf( "substr_count(post_content, «Организация похорон обычно происходит на третий день после смерти») = %d\n",
	substr_count( $c, 'Организация похорон обычно происходит на третий день после смерти' ) );

if ( 1 !== $n_find ) {
	printf( "\nСТОП: ожидалось ровно 1 вхождение искомой строки, найдено %d. Не пишем.\n", $n_find );
	exit( 20 );
}
echo "\nровно одно вхождение — условие выполнено\n";

echo "\n=== АБЗАЦ ЦЕЛИКОМ: было ===\n";
$pos = strpos( $c, $FIND );
$p_start = strrpos( substr( $c, 0, $pos ), '<p>' );
$p_end   = strpos( $c, '</p>', $pos );
$para_was = substr( $c, $p_start, $p_end + 4 - $p_start );
echo $para_was . "\n";
printf( "\n(абзац: %d байт / %d символов)\n", strlen( $para_was ), mb_strlen( $para_was ) );

echo "\n=== АБЗАЦ ЦЕЛИКОМ: стало ===\n";
$para_new = str_replace( $FIND, $REPLACE, $para_was );
echo $para_new . "\n";
printf( "\n(абзац: %d байт / %d символов)\n", strlen( $para_new ), mb_strlen( $para_new ) );

echo "\n=== с блочными комментариями вокруг, чтобы видеть границы блока ===\n";
$ctx_start = max( 0, $p_start - 40 );
echo substr( $c, $ctx_start, ( $p_end + 4 - $ctx_start ) + 30 ) . "\n";

echo "\n=== дельта ===\n";
printf( "искомое:    «%s»  %d байт / %d символов\n", $FIND, strlen( $FIND ), mb_strlen( $FIND ) );
printf( "замена:     «%s»  %d байт / %d символов\n", $REPLACE, strlen( $REPLACE ), mb_strlen( $REPLACE ) );
printf( "дельта подстроки: %+d байт (strlen), %+d символов (mb_strlen)\n",
	strlen( $REPLACE ) - strlen( $FIND ), mb_strlen( $REPLACE ) - mb_strlen( $FIND ) );

$c_new = str_replace( $FIND, $REPLACE, $c );
printf( "\npost_content станет: %d байт / %d символов, md5=%s\n", strlen( $c_new ), mb_strlen( $c_new ), md5( $c_new ) );
printf( "дельта post_content: %+d байт (strlen), %+d символов (mb_strlen)\n",
	strlen( $c_new ) - strlen( $c ), mb_strlen( $c_new ) - mb_strlen( $c ) );
printf( "дельта подстроки и дельта поля совпадают: %s\n",
	( strlen( $c_new ) - strlen( $c ) === strlen( $REPLACE ) - strlen( $FIND ) )
	&& ( mb_strlen( $c_new ) - mb_strlen( $c ) === mb_strlen( $REPLACE ) - mb_strlen( $FIND ) ) ? 'да' : 'НЕТ' );

echo "\n=== счётчики в «станет» ===\n";
printf( "«Ритуальный агент» = %d (ожидается 0)\n", substr_count( $c_new, 'Ритуальный агент' ) );
printf( "«Наш специалист» = %d (ожидается 1)\n", substr_count( $c_new, 'Наш специалист' ) );
printf( "«Организация похорон обычно происходит на третий день после смерти» = %d (ожидается 1, оставляем)\n",
	substr_count( $c_new, 'Организация похорон обычно происходит на третий день после смерти' ) );

echo "\n=== круговорот блоков для «станет»: parse_blocks -> serialize_blocks ===\n";
$rt = serialize_blocks( parse_blocks( $c_new ) );
printf( "исходная строка «станет»: %d байт, md5=%s\n", strlen( $c_new ), md5( $c_new ) );
printf( "после круговорота:        %d байт, md5=%s\n", strlen( $rt ), md5( $rt ) );
printf( "побайтово: %s\n", $rt === $c_new ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ — писать нельзя' );
if ( $rt !== $c_new ) { exit( 21 ); }

$path = dirname( __DIR__, 2 ) . '/_backup/t1/step25-content-11-new.txt';
file_put_contents( $path, $c_new );
clearstatcache( true, $path );
printf( "\nподготовленный post_content сохранён: %s (%d байт, md5 %s)\n", str_replace( chr( 92 ), '/', $path ), filesize( $path ), md5_file( $path ) );
echo "ЗАПИСИ В БД НЕ ПРОИЗВОДИЛОСЬ.\n";
