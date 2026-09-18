<?php
/**
 * Этап 2.3, шаг 1 — dry-run переименования post_title. Только чтение.
 * Дополнительно: какие фильтры висят на *_save_pre, чтобы wp_update_post
 * не переписал post_content по дороге (он тянет его из БД и сохраняет обратно).
 */
require dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb, $wp_filter;

$ids = array( 11, 12, 13, 14, 15 );

$targets = array(
	11 => 'Организация достойной церемонии прощания и погребения',
	13 => 'Перевозка и транспортировка усопших',
	15 => 'Помощь с документами и формальностями в первые дни',
);

echo "=== DRY-RUN: было / стало по post_title ===\n";
foreach ( $ids as $id ) {
	$r = $wpdb->get_row( $wpdb->prepare( "SELECT post_title, post_name, post_status, post_modified FROM {$wpdb->posts} WHERE ID=%d", $id ), ARRAY_A );
	echo str_repeat( '-', 74 ) . "\n";
	printf( "ID=%d  (post_status=%s, post_modified=%s)\n", $id, $r['post_status'], $r['post_modified'] );
	printf( "  БЫЛО   post_title = [%s]  (%d байт / %d симв.)\n", $r['post_title'], strlen( $r['post_title'] ), mb_strlen( $r['post_title'] ) );
	if ( isset( $targets[ $id ] ) ) {
		printf( "  СТАНЕТ post_title = [%s]  (%d байт / %d симв.)\n", $targets[ $id ], strlen( $targets[ $id ] ), mb_strlen( $targets[ $id ] ) );
	} else {
		echo "  СТАНЕТ post_title = (не трогаем — вне списка 11/13/15)\n";
	}
	printf( "  post_name = [%s] — в массив wp_update_post НЕ передаётся, ожидается без изменений\n", $r['post_name'] );
	printf( "  sanitize_title(нового заголовка) дал бы = [%s] — справочно, применяться не должно\n",
		isset( $targets[ $id ] ) ? sanitize_title( $targets[ $id ] ) : '-' );
}

echo "\n=== ФИЛЬТРЫ, которые отработают при wp_update_post ===\n";
$describe = function ( $cb ) {
	try {
		if ( $cb instanceof Closure ) { $r = new ReflectionFunction( $cb ); $n = 'Closure'; }
		elseif ( is_string( $cb ) )   { $r = new ReflectionFunction( $cb ); $n = $cb; }
		elseif ( is_array( $cb ) )    { $cls = is_object( $cb[0] ) ? get_class( $cb[0] ) : $cb[0];
		                                $r = new ReflectionMethod( $cls, $cb[1] ); $n = $cls . '::' . $cb[1]; }
		elseif ( is_object( $cb ) )   { $r = new ReflectionMethod( get_class( $cb ), '__invoke' ); $n = get_class( $cb ) . '::__invoke'; }
		else { return array( '(неопознан)', '' ); }
		$f = str_replace( chr( 92 ), '/', (string) $r->getFileName() );
		$f = str_replace( str_replace( chr( 92 ), '/', ABSPATH ), '', $f );
		return array( $n, $f . ':' . $r->getStartLine() );
	} catch ( Throwable $e ) { return array( '(reflection error)', $e->getMessage() ); }
};
foreach ( array( 'title_save_pre', 'content_save_pre', 'excerpt_save_pre', 'name_save_pre', 'wp_insert_post_data' ) as $hook ) {
	echo "--- {$hook} ---\n";
	if ( empty( $wp_filter[ $hook ] ) ) { echo "  (пусто)\n"; continue; }
	foreach ( $wp_filter[ $hook ]->callbacks as $prio => $cbs ) {
		foreach ( $cbs as $cb ) {
			list( $n, $src ) = $describe( $cb['function'] );
			printf( "  prio=%-4s %-56s %s\n", $prio, $n, $src );
		}
	}
}
echo "\nПосле kses_remove_filters() из этого списка уйдут wp_filter_post_kses / wp_filter_kses.\n";

echo "\n=== КОНТРОЛЬНЫЕ СУММЫ post_content ДО записи (для сверки, что он не поехал) ===\n";
foreach ( $ids as $id ) {
	$c = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID=%d", $id ) );
	printf( "ID=%-3s md5=%s  len=%d байт\n", $id, md5( $c ), strlen( $c ) );
}
echo "\nсверка с бэкапом posts-11-15-before.json:\n";
$bk = json_decode( file_get_contents( dirname( __DIR__, 2 ) . '/_backup/t1/posts-11-15-before.json' ), true );
foreach ( $bk as $row ) {
	$cur = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID=%d", $row['ID'] ) );
	printf( "ID=%-3s post_content vs бэкап: %s\n", $row['ID'], $cur === $row['post_content'] ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' );
}

echo "\n=== снимок «до» для этапа 2.3 ===\n";
$before = array( 'post_title' => array(), 'post_name' => array(), 'content_md5' => array(), 'excerpt' => array(), 'indexable' => array(), 'title' => array() );
$t = $wpdb->prefix . 'yoast_indexable';
wp_cache_flush();
foreach ( $ids as $id ) {
	$r = $wpdb->get_row( $wpdb->prepare( "SELECT post_title, post_name, post_content, post_excerpt FROM {$wpdb->posts} WHERE ID=%d", $id ), ARRAY_A );
	$before['post_title'][ $id ]  = $r['post_title'];
	$before['post_name'][ $id ]   = $r['post_name'];
	$before['content_md5'][ $id ] = md5( $r['post_content'] );
	$before['excerpt'][ $id ]     = $r['post_excerpt'];
	$before['indexable'][ $id ]   = $wpdb->get_row( $wpdb->prepare( "SELECT id, updated_at, title, breadcrumb_title, description FROM {$t} WHERE object_type='post' AND object_id=%d", $id ), ARRAY_A );
	$before['title'][ $id ]       = YoastSEO()->meta->for_post( $id )->title;
	printf( "ID=%-3s indexable.updated_at=%s  <title>=[%s]\n", $id, $before['indexable'][ $id ]['updated_at'], $before['title'][ $id ] );
}
$path = dirname( __DIR__, 2 ) . '/_backup/t1/step23-before.json';
file_put_contents( $path, json_encode( $before, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
clearstatcache( true, $path );
echo "\nснимок сохранён: " . str_replace( chr( 92 ), '/', $path ) . ' (' . filesize( $path ) . " байт, MD5 " . md5_file( $path ) . ")\n";
echo "ЗАПИСИ В БД НЕ ПРОИЗВОДИЛОСЬ.\n";
