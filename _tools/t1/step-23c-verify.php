<?php
/**
 * Этап 2.3, шаг 3 — проверка отдельным процессом.
 * Эталоны <title> зашиты константами из разведки.
 */
require dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb;

$t   = $wpdb->prefix . 'yoast_indexable';
$ids = array( 11, 12, 13, 14, 15 );

/** Эталонные <title> — зафиксированы в разведке, зашиты, не читаются из базы. */
$ETALON_TITLE = array(
	11 => 'Организация похорон в Хабаровске - Земля и Люди',
	12 => 'Кремация в Хабаровске - Земля и Люди',
	13 => 'Транспортировка умерших в Хабаровске - Земля и Люди',
	14 => 'Благоустройство мест захоронения в Хабаровске - Земля и Люди',
	15 => 'Юридическая помощь при оформлении документов после смерти - Земля и Люди',
);
/** Новые post_title — то, что НЕ должно оказаться в колонке title индексабла. */
$NEW_POST_TITLE = array(
	11 => 'Организация достойной церемонии прощания и погребения',
	13 => 'Перевозка и транспортировка усопших',
	15 => 'Помощь с документами и формальностями в первые дни',
);
/** Зафиксированные шаблоны из этапа 2.2. */
$FIXED_TPL = array(
	11 => 'Организация похорон в Хабаровске %%sep%% %%sitename%%',
	13 => 'Транспортировка умерших в Хабаровске %%sep%% %%sitename%%',
	15 => 'Юридическая помощь при оформлении документов после смерти %%sep%% %%sitename%%',
);

$bk = array();
foreach ( json_decode( file_get_contents( dirname( __DIR__, 2 ) . '/_backup/t1/posts-11-15-before.json' ), true ) as $r ) {
	$bk[ (int) $r['ID'] ] = $r;
}
$s23 = json_decode( file_get_contents( dirname( __DIR__, 2 ) . '/_backup/t1/step23-before.json' ), true );

$abort = false;

echo "=== 1. post_title / post_name после записи ===\n";
printf( "%-4s %-56s %-26s %s\n", 'ID', 'post_title', 'post_name', 'post_name vs бэкап' );
echo str_repeat( '-', 116 ) . "\n";
foreach ( $ids as $id ) {
	$r = $wpdb->get_row( $wpdb->prepare( "SELECT post_title, post_name FROM {$wpdb->posts} WHERE ID=%d", $id ), ARRAY_A );
	$same_name = ( $r['post_name'] === $bk[ $id ]['post_name'] );
	printf( "%-4s %-56s %-26s %s\n", $id, $r['post_title'], $r['post_name'], $same_name ? 'не изменился' : 'ИЗМЕНИЛСЯ' );
	if ( ! $same_name ) { $abort = true; }
}

echo "\n=== 2. отрендеренный <title>: сверка с эталоном разведки ===\n";
echo "способ: YoastSEO()->meta->for_post(\$id)->title, wp_cache_flush() перед каждым чтением\n\n";
foreach ( $ids as $id ) {
	wp_cache_flush();
	$got  = YoastSEO()->meta->for_post( $id )->title;
	$same = ( $got === $ETALON_TITLE[ $id ] );
	echo str_repeat( '-', 74 ) . "\n";
	printf( "ID=%d\n", $id );
	printf( "  эталон   = [%s]\n", $ETALON_TITLE[ $id ] );
	printf( "  получено = [%s]\n", $got );
	printf( "  длины: эталон %d байт / %d симв., получено %d байт / %d симв.\n",
		strlen( $ETALON_TITLE[ $id ] ), mb_strlen( $ETALON_TITLE[ $id ] ), strlen( $got ), mb_strlen( $got ) );
	printf( "  ВЕРДИКТ: %s\n", $same ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' );
	if ( ! $same ) {
		printf( "    эталон,   hex = %s\n", bin2hex( $ETALON_TITLE[ $id ] ) );
		printf( "    получено, hex = %s\n", bin2hex( $got ) );
		$abort = true;
	}
}

echo "\n=== 3. wp_yoast_indexable после записи ===\n";
foreach ( $ids as $id ) {
	$r = $wpdb->get_row( $wpdb->prepare( "SELECT id, updated_at, title, breadcrumb_title, description FROM {$t} WHERE object_type='post' AND object_id=%d", $id ), ARRAY_A );
	$was = $s23['indexable'][ $id ];
	echo str_repeat( '-', 74 ) . "\n";
	printf( "ID=%d\n", $id );
	printf( "  updated_at       было=%s  стало=%s  %s\n", $was['updated_at'], $r['updated_at'], $was['updated_at'] === $r['updated_at'] ? '(не менялся)' : '(сдвинулся)' );
	printf( "  title            было=%s\n", null === $was['title'] ? '(NULL)' : '[' . $was['title'] . ']' );
	printf( "  title            стало=%s\n", null === $r['title'] ? '(NULL)' : '[' . $r['title'] . ']' );
	printf( "  breadcrumb_title было=[%s]\n", $was['breadcrumb_title'] );
	printf( "  breadcrumb_title стало=[%s]\n", $r['breadcrumb_title'] );
	printf( "  description      стало=%s\n", null === $r['description'] ? '(NULL)' : '[' . $r['description'] . ']' );

	if ( isset( $NEW_POST_TITLE[ $id ] ) ) {
		$is_new_title  = ( $r['title'] === $NEW_POST_TITLE[ $id ] );
		$is_fixed_tpl  = ( $r['title'] === $FIXED_TPL[ $id ] );
		$is_old_render = ( $r['title'] === $ETALON_TITLE[ $id ] );
		printf( "  колонка title содержит новый post_title? %s  <- ОБРЫВ, если ДА\n", $is_new_title ? 'ДА' : 'нет' );
		printf( "  колонка title = зафиксированный шаблон? %s\n", $is_fixed_tpl ? 'да' : 'нет' );
		printf( "  колонка title = раскрытая прежняя строка? %s\n", $is_old_render ? 'да' : 'нет' );
		if ( $is_new_title ) { $abort = true; }
		if ( ! $is_fixed_tpl && ! $is_old_render ) {
			echo "  ВНИМАНИЕ: колонка title — ни шаблон, ни прежняя раскрытая строка.\n";
			$abort = true;
		}
		$bc_ok = ( $r['breadcrumb_title'] === $NEW_POST_TITLE[ $id ] );
		printf( "  breadcrumb_title = новый post_title? %s (ожидается да)\n", $bc_ok ? 'да' : 'НЕТ' );
	} else {
		printf( "  (12/14 — не трогали) breadcrumb_title совпал с прежним: %s\n", $r['breadcrumb_title'] === $was['breadcrumb_title'] ? 'да' : 'НЕТ' );
	}
}

echo "\n=== 4. сводка ===\n";
printf( "post_name всех пяти не изменились: %s\n", $abort ? 'см. выше' : 'да' );
printf( "<title> записей 12 и 14 не изменились: проверено выше (эталон = значение разведки)\n" );

echo "\nИТОГ ЭТАПА 2.3: " . ( $abort ? 'ОБРЫВ — есть расхождения, дальше не идти' : 'всё сошлось' ) . "\n";
exit( $abort ? 9 : 0 );
