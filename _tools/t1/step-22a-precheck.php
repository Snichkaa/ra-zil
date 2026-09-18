<?php
/**
 * Этап 2.2, пункт Б — предзаписная проверка. Только чтение.
 * Ровно одна строка индексабла на каждый object_id 11-15 при object_type='post'.
 */
require dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb;

$t = $wpdb->prefix . 'yoast_indexable';

echo "=== Б.1. COUNT(*) по object_id, object_type='post' ===\n";
$sql = "SELECT object_id, COUNT(*) AS c
          FROM {$t}
         WHERE object_type='post' AND object_id IN (11,12,13,14,15)
         GROUP BY object_id
         ORDER BY object_id";
echo "SQL: " . preg_replace( '/\s+/', ' ', $sql ) . "\n\n";
$rows = $wpdb->get_results( $sql );
$max  = 0;
$seen = array();
foreach ( $rows as $r ) {
	printf( "object_id=%-4s COUNT(*)=%s\n", $r->object_id, $r->c );
	$seen[ (int) $r->object_id ] = (int) $r->c;
	$max = max( $max, (int) $r->c );
}
foreach ( array( 11, 12, 13, 14, 15 ) as $id ) {
	if ( ! isset( $seen[ $id ] ) ) {
		printf( "object_id=%-4s COUNT(*)=0  (строки нет вовсе)\n", $id );
	}
}

echo "\n=== Б.2. без фильтра по object_type — нет ли строк других типов с теми же id ===\n";
$any = $wpdb->get_results(
	"SELECT object_id, object_type, COUNT(*) AS c
	   FROM {$t}
	  WHERE object_id IN (11,12,13,14,15)
	  GROUP BY object_id, object_type
	  ORDER BY object_id, object_type"
);
foreach ( $any as $r ) {
	printf( "object_id=%-4s object_type=%-10s COUNT(*)=%s\n", $r->object_id, $r->object_type, $r->c );
}

echo "\n=== Б.3. created_at и updated_at раздельными колонками (снятие вопроса о двух метках у ID=15) ===\n";
$rows = $wpdb->get_results(
	"SELECT id, object_id, object_type, object_sub_type, created_at, updated_at, title, breadcrumb_title, description, permalink_hash
	   FROM {$t}
	  WHERE object_type='post' AND object_id IN (11,12,13,14,15)
	  ORDER BY object_id, id"
);
printf( "строк выбрано: %d\n\n", count( $rows ) );
printf( "%-5s %-10s %-16s %-21s %-21s %s\n", 'id', 'object_id', 'sub_type', 'created_at', 'updated_at', 'title' );
echo str_repeat( '-', 110 ) . "\n";
foreach ( $rows as $r ) {
	printf(
		"%-5s %-10s %-16s %-21s %-21s %s\n",
		$r->id,
		$r->object_id,
		$r->object_sub_type,
		$r->created_at,
		$r->updated_at,
		null === $r->title ? '(NULL)' : '[' . $r->title . ']'
	);
}

echo "\n=== Б.4. вердикт ===\n";
$dupes = array_filter( $seen, function ( $c ) { return $c > 1; } );
$missing = array_diff( array( 11, 12, 13, 14, 15 ), array_keys( $seen ) );
if ( $dupes ) {
	echo "ДУБЛИ НАЙДЕНЫ — писать нельзя. Полный дамп дублирующихся строк:\n";
	foreach ( array_keys( $dupes ) as $id ) {
		$d = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE object_type='post' AND object_id=%d", $id ), ARRAY_A );
		print_r( $d );
	}
	echo "СТОП.\n";
	exit( 2 );
}
if ( $missing ) {
	echo 'ОТСУТСТВУЮТ строки для object_id: ' . implode( ', ', $missing ) . " — писать нельзя.\nСТОП.\n";
	exit( 3 );
}
echo "Ровно одна строка на каждый object_id 11-15 при object_type='post'. Дублей нет, пропусков нет.\n";
echo "Можно переходить к записи.\n";
