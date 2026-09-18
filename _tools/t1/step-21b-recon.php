<?php
/**
 * Этап 2.1b — разведка перед записью. Только чтение.
 *  1) таблица индексаблов Yoast и строки для object_id 11-15;
 *  2) наличие <!--nextpage--> в post_content 11, 13, 15.
 */
require dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb;

echo "=== 1. wp_yoast_indexable ===\n";
echo 'префикс таблиц = ' . $wpdb->prefix . "\n";

$table  = $wpdb->prefix . 'yoast_indexable';
$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

echo "ожидаемое имя  = {$table}\n";
echo 'SHOW TABLES LIKE вернул = ' . ( null === $exists ? '(пусто — таблицы НЕТ)' : $exists ) . "\n\n";

echo "все таблицы с 'yoast' в имени:\n";
$yt = $wpdb->get_col( "SHOW TABLES LIKE '%yoast%'" );
echo $yt ? implode( "\n", $yt ) . "\n" : "(ни одной)\n";
echo "\n";

if ( null === $exists ) {
	echo "ВЫВОД: таблицы {$table} в базе НЕТ. Индексаблы Yoast не материализованы,\n";
	echo "выводить по object_id 11-15 нечего.\n";
} else {
	$cols = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
	echo 'колонок в таблице: ' . count( $cols ) . "\n";
	foreach ( array( 'object_id', 'object_type', 'object_sub_type', 'title', 'breadcrumb_title', 'description', 'permalink', 'updated_at' ) as $c ) {
		echo sprintf( "  запрошенная колонка %-18s : %s\n", $c, in_array( $c, $cols, true ) ? 'есть' : 'ОТСУТСТВУЕТ' );
	}
	echo "\nвсего строк в таблице: " . $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) . "\n";
	echo "строк с object_type='post': " . $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE object_type='post'" ) . "\n\n";

	$rows = $wpdb->get_results(
		"SELECT id, object_id, object_type, object_sub_type, title, breadcrumb_title, description, permalink, updated_at
		   FROM {$table}
		  WHERE object_id IN (11,12,13,14,15) AND object_type='post'
		  ORDER BY object_id"
	);
	if ( ! $rows ) {
		echo "СТРОК для object_id 11-15 с object_type='post' НЕТ.\n";
		$any = $wpdb->get_results( "SELECT id, object_id, object_type, object_sub_type FROM {$table} WHERE object_id IN (11,12,13,14,15) ORDER BY object_id" );
		echo "строк с этими object_id любого object_type: " . count( $any ) . "\n";
		foreach ( $any as $a ) {
			printf( "  id=%s object_id=%s object_type=%s object_sub_type=%s\n", $a->id, $a->object_id, $a->object_type, $a->object_sub_type );
		}
	} else {
		foreach ( $rows as $r ) {
			echo str_repeat( '-', 74 ) . "\n";
			printf( "id               = %s\n", $r->id );
			printf( "object_id        = %s\n", $r->object_id );
			printf( "object_type      = %s\n", $r->object_type );
			printf( "object_sub_type  = %s\n", $r->object_sub_type );
			printf( "title            = %s\n", null === $r->title ? '(NULL)' : '[' . $r->title . ']' );
			printf( "breadcrumb_title = %s\n", null === $r->breadcrumb_title ? '(NULL)' : '[' . $r->breadcrumb_title . ']' );
			printf( "description      = %s\n", null === $r->description ? '(NULL)' : '[' . $r->description . ']' );
			printf( "permalink        = %s\n", null === $r->permalink ? '(NULL)' : $r->permalink );
			printf( "updated_at       = %s\n", $r->updated_at );
		}
	}
}

echo "\n=== 2. <!--nextpage--> в post_content ===\n";
$needle = '<!--nextpage-->';
foreach ( array( 11, 13, 15 ) as $id ) {
	$c = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $id ) );
	$strict  = substr_count( $c, $needle );
	$loose   = preg_match_all( '/<!--\s*nextpage\s*-->/i', $c );
	printf( "ID=%-3s точных '<!--nextpage-->' = %d ; по регулярке с пробелами/регистром = %d\n", $id, $strict, $loose );
}
echo "\nдля полноты — те же счётчики по всем пяти записям:\n";
foreach ( array( 11, 12, 13, 14, 15 ) as $id ) {
	$c = $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $id ) );
	printf( "ID=%-3s = %d\n", $id, preg_match_all( '/<!--\s*nextpage\s*-->/i', $c ) );
}
echo "\nчерез штатный WP (post_password/pagination):\n";
foreach ( array( 11, 13, 15 ) as $id ) {
	$p = get_post( $id );
	$pages = explode( '<!--nextpage-->', $p->post_content );
	printf( "ID=%-3s страниц после explode = %d (1 = разбиения нет)\n", $id, count( $pages ) );
}
