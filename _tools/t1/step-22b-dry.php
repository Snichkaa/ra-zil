<?php
/**
 * Этап 2.2, шаг 1 — dry-run и снимок состояния «до». Только чтение.
 * Снимок кладётся в _backup/t1/step22-before.json и сверяется скриптом step-22d.
 */
require dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb;

$t   = $wpdb->prefix . 'yoast_indexable';
$ids = array( 11, 12, 13, 14, 15 );

$targets = array(
	11 => 'Организация похорон в Хабаровске %%sep%% %%sitename%%',
	13 => 'Транспортировка умерших в Хабаровске %%sep%% %%sitename%%',
	15 => 'Юридическая помощь при оформлении документов после смерти %%sep%% %%sitename%%',
);

echo "=== ПРОВЕРКА: зарегистрирован ли watcher Yoast на updated_post_meta ===\n";
foreach ( array( 'added_post_meta', 'updated_post_meta' ) as $h ) {
	printf( "has_action('%s') = %s\n", $h, var_export( has_action( $h ), true ) );
}
echo "WPSEO_Meta::\$meta_prefix = [" . WPSEO_Meta::$meta_prefix . "]\n";
echo "ключ _yoast_wpseo_title начинается с префикса: "
	. ( strpos( '_yoast_wpseo_title', WPSEO_Meta::$meta_prefix ) === 0 ? 'да' : 'НЕТ' ) . "\n\n";

echo "=== DRY-RUN: было / стало по мета _yoast_wpseo_title ===\n";
foreach ( $ids as $id ) {
	$cnt = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='_yoast_wpseo_title'", $id ) );
	$cur = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='_yoast_wpseo_title'", $id ) );
	echo str_repeat( '-', 74 ) . "\n";
	printf( "ID=%d\n", $id );
	printf( "  строк в postmeta сейчас = %d\n", $cnt );
	printf( "  БЫЛО  = %s\n", null === $cur ? '(ключа нет)' : '[' . $cur . ']' );
	if ( isset( $targets[ $id ] ) ) {
		printf( "  СТАНЕТ= [%s]\n", $targets[ $id ] );
		printf( "  длина значения: %d байт / %d символов\n", strlen( $targets[ $id ] ), mb_strlen( $targets[ $id ] ) );
	} else {
		echo "  СТАНЕТ= (не трогаем — вне списка 11/13/15)\n";
	}
}

echo "\n=== СНИМОК «ДО»: индексаблы ===\n";
$before = array( 'indexable' => array(), 'meta' => array(), 'title' => array(), 'description' => array(), 'post_title' => array(), 'post_name' => array() );

$read_indexable = function () use ( $wpdb, $t, $ids ) {
	$out = array();
	foreach ( $ids as $id ) {
		$r = $wpdb->get_row( $wpdb->prepare( "SELECT id, object_id, created_at, updated_at, title, breadcrumb_title, description FROM {$t} WHERE object_type='post' AND object_id=%d", $id ), ARRAY_A );
		$out[ $id ] = $r;
	}
	return $out;
};

$snap1 = $read_indexable();
printf( "%-5s %-21s %-21s %s\n", 'id', 'created_at', 'updated_at', 'title' );
foreach ( $ids as $id ) {
	printf( "%-5s %-21s %-21s %s\n", $id, $snap1[ $id ]['created_at'], $snap1[ $id ]['updated_at'], null === $snap1[ $id ]['title'] ? '(NULL)' : '[' . $snap1[ $id ]['title'] . ']' );
}

echo "\n=== СНИМОК «ДО»: отрендеренный <title> (wp_cache_flush перед чтением) ===\n";
wp_cache_flush();
echo "wp_cache_flush() вызван\n";
foreach ( $ids as $id ) {
	$m = YoastSEO()->meta->for_post( $id );
	$before['title'][ $id ]       = $m->title;
	$before['description'][ $id ] = $m->description;
	printf( "ID=%-3s title       = [%s]\n", $id, $m->title );
	printf( "ID=%-3s description = [%s]\n", $id, $m->description );
}

echo "\n=== контроль: не сдвинул ли сам рендер updated_at индексабла ===\n";
$snap2 = $read_indexable();
foreach ( $ids as $id ) {
	printf(
		"ID=%-3s updated_at до рендера=%s  после рендера=%s  %s\n",
		$id,
		$snap1[ $id ]['updated_at'],
		$snap2[ $id ]['updated_at'],
		$snap1[ $id ]['updated_at'] === $snap2[ $id ]['updated_at'] ? 'не изменился' : 'ИЗМЕНИЛСЯ ПРИ ЧТЕНИИ'
	);
}

foreach ( $ids as $id ) {
	$before['indexable'][ $id ]  = $snap2[ $id ];
	$before['meta'][ $id ]       = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='_yoast_wpseo_title'", $id ) );
	$before['post_title'][ $id ] = $wpdb->get_var( $wpdb->prepare( "SELECT post_title FROM {$wpdb->posts} WHERE ID=%d", $id ) );
	$before['post_name'][ $id ]  = $wpdb->get_var( $wpdb->prepare( "SELECT post_name FROM {$wpdb->posts} WHERE ID=%d", $id ) );
}
$before['targets'] = $targets;

$path = dirname( __DIR__, 2 ) . '/_backup/t1/step22-before.json';
file_put_contents( $path, json_encode( $before, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
clearstatcache( true, $path );
echo "\nснимок сохранён: " . str_replace( chr( 92 ), '/', $path ) . ' (' . filesize( $path ) . " байт, MD5 " . md5_file( $path ) . ")\n";
echo "ЗАПИСИ В БД НЕ ПРОИЗВОДИЛОСЬ.\n";
