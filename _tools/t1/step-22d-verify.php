<?php
/**
 * Этап 2.2, шаг 3 — проверка в чистом процессе.
 * wp_cache_flush() перед каждым чтением отрендеренного <title>.
 */
require dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb;

$t   = $wpdb->prefix . 'yoast_indexable';
$ids = array( 11, 12, 13, 14, 15 );

$before = json_decode( file_get_contents( dirname( __DIR__, 2 ) . '/_backup/t1/step22-before.json' ), true );

/** Значения из разведки — эталон, зашит константами, а не взят из текущей БД. */
$recon = array(
	11 => 'Организация похорон в Хабаровске - Земля и Люди',
	13 => 'Транспортировка умерших в Хабаровске - Земля и Люди',
	15 => 'Юридическая помощь при оформлении документов после смерти - Земля и Люди',
);

echo "=== В.1. updated_at индексабла: ДО записи / ПОСЛЕ записи ===\n";
printf( "%-5s %-21s %-21s %-14s %s\n", 'ID', 'updated_at ДО', 'updated_at ПОСЛЕ', 'изменился?', 'title в колонке' );
echo str_repeat( '-', 120 ) . "\n";
$moved = array();
foreach ( $ids as $id ) {
	$now = $wpdb->get_row( $wpdb->prepare( "SELECT id, created_at, updated_at, title, breadcrumb_title, description FROM {$t} WHERE object_type='post' AND object_id=%d", $id ), ARRAY_A );
	$was = $before['indexable'][ $id ];
	$chg = ( $was['updated_at'] !== $now['updated_at'] );
	$moved[ $id ] = $chg;
	printf(
		"%-5s %-21s %-21s %-14s %s\n",
		$id,
		$was['updated_at'],
		$now['updated_at'],
		$chg ? 'ДА' : 'нет',
		null === $now['title'] ? '(NULL)' : '[' . $now['title'] . ']'
	);
}
echo "\nожидание: ДА для 11/13/15 (мету писали), нет для 12/14 (не трогали)\n";

echo "\n=== В.2. отрендеренный <title>, тем же способом, что в разведке ===\n";
echo "способ: YoastSEO()->meta->for_post(\$id)->title\n\n";
$ok_all = true;
foreach ( $ids as $id ) {
	wp_cache_flush();
	$m       = YoastSEO()->meta->for_post( $id );
	$title   = $m->title;
	$desc    = $m->description;
	$expect  = isset( $recon[ $id ] ) ? $recon[ $id ] : $before['title'][ $id ];
	$same    = ( $title === $expect );
	$source  = isset( $recon[ $id ] ) ? 'эталон разведки' : 'снимок «до» (12/14 не трогали)';

	echo str_repeat( '-', 74 ) . "\n";
	printf( "ID=%d   (wp_cache_flush() вызван перед чтением)\n", $id );
	printf( "  эталон (%s)\n", $source );
	printf( "  ожидалось = [%s]\n", $expect );
	printf( "  получено  = [%s]\n", $title );
	printf( "  длины: ожидалось %d байт / %d симв., получено %d байт / %d симв.\n",
		strlen( $expect ), mb_strlen( $expect ), strlen( $title ), mb_strlen( $title ) );
	printf( "  ВЕРДИКТ: %s\n", $same ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' );
	if ( ! $same ) {
		printf( "    ожидалось, hex = %s\n", bin2hex( $expect ) );
		printf( "    получено,  hex = %s\n", bin2hex( $title ) );
		$ok_all = false;
	}
	printf( "  description = [%s]%s\n", $desc, '' === $desc ? ' (пусто, как и было)' : '' );
}

echo "\n=== В.3. достоверность: связка «updated_at сдвинулся» + «title совпал» ===\n";
foreach ( array( 11, 13, 15 ) as $id ) {
	wp_cache_flush();
	$title = YoastSEO()->meta->for_post( $id )->title;
	$same  = ( $title === $recon[ $id ] );
	if ( $same && ! $moved[ $id ] ) {
		printf( "ID=%-3s НЕДОСТОВЕРНО: title совпал, но updated_at индексабла не изменился.\n", $id );
		$ok_all = false;
	} elseif ( $same && $moved[ $id ] ) {
		printf( "ID=%-3s достоверно: updated_at сдвинулся И title совпал.\n", $id );
	} else {
		printf( "ID=%-3s РАСХОЖДЕНИЕ по title (updated_at сдвинулся: %s).\n", $id, $moved[ $id ] ? 'да' : 'нет' );
	}
}

echo "\n=== В.4. мета в БД сейчас ===\n";
foreach ( $ids as $id ) {
	$v = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='_yoast_wpseo_title'", $id ) );
	printf( "ID=%-3s _yoast_wpseo_title = %s\n", $id, null === $v ? '(ключа нет)' : '[' . $v . ']' );
}
printf( "строк _yoast_wpseo_metadesc во всей postmeta = %d\n", (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_yoast_wpseo_metadesc'" ) );

echo "\n=== В.5. post_title / post_name не трогали ===\n";
foreach ( $ids as $id ) {
	$r = $wpdb->get_row( $wpdb->prepare( "SELECT post_title, post_name FROM {$wpdb->posts} WHERE ID=%d", $id ), ARRAY_A );
	printf(
		"ID=%-3s post_title=%s  post_name=%s  %s\n",
		$id,
		$r['post_title'] === $before['post_title'][ $id ] ? 'не изменился' : 'ИЗМЕНИЛСЯ',
		$r['post_name'] === $before['post_name'][ $id ] ? 'не изменился' : 'ИЗМЕНИЛСЯ',
		'[' . $r['post_title'] . ']'
	);
}

echo "\nИТОГ ЭТАПА 2.2: " . ( $ok_all ? 'ВСЕ ТРИ СОВПАЛИ, результат достоверен' : 'ЕСТЬ РАСХОЖДЕНИЯ — дальше не идти' ) . "\n";
exit( $ok_all ? 0 : 4 );
