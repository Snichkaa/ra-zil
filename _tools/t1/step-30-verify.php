<?php
/**
 * Этап 3 — итоговая проверка. Отдельный процесс.
 * Эталоны зашиты константами, а не перечитываются из текущей базы.
 */
require dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb;

$t   = $wpdb->prefix . 'yoast_indexable';
$ids = array( 11, 12, 13, 14, 15 );

$ETALON_TITLE = array(
	11 => 'Организация похорон в Хабаровске - Земля и Люди',
	12 => 'Кремация в Хабаровске - Земля и Люди',
	13 => 'Транспортировка умерших в Хабаровске - Земля и Люди',
	14 => 'Благоустройство мест захоронения в Хабаровске - Земля и Люди',
	15 => 'Юридическая помощь при оформлении документов после смерти - Земля и Люди',
);
$NEW_POST_TITLE = array(
	11 => 'Организация достойной церемонии прощания и погребения',
	13 => 'Перевозка и транспортировка усопших',
	15 => 'Помощь с документами и формальностями в первые дни',
);
$FIND    = 'Ритуальный агент выезжает по Хабаровску круглосуточно';
$REPLACE = 'Наш специалист выезжает по Хабаровску круглосуточно';

$bk = array();
foreach ( json_decode( file_get_contents( dirname( __DIR__, 2 ) . '/_backup/t1/posts-11-15-before.json' ), true ) as $r ) {
	$bk[ (int) $r['ID'] ] = $r;
}

$fail = array();

/* ------------------------------------------------------------------ 1 */
echo "================== 1. КРУГОВОРОТ БЛОКОВ post_content ID=11 ==================\n";
$cur = $wpdb->get_var( "SELECT post_content FROM {$wpdb->posts} WHERE ID=11" );
$was = $bk[11]['post_content'];

echo "--- 1a. parse_blocks -> serialize_blocks на текущем значении ---\n";
$rt = serialize_blocks( parse_blocks( $cur ) );
printf( "в БД:             %d байт, md5=%s\n", strlen( $cur ), md5( $cur ) );
printf( "после круговорота: %d байт, md5=%s\n", strlen( $rt ), md5( $rt ) );
printf( "побайтово: %s\n", $rt === $cur ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' );
if ( $rt !== $cur ) { $fail[] = '1a: круговорот не побайтовый'; }

echo "\n--- 1b. побайтовый diff «бэкап vs текущее» ---\n";
$lp = 0;
$min = min( strlen( $was ), strlen( $cur ) );
while ( $lp < $min && $was[ $lp ] === $cur[ $lp ] ) { $lp++; }
$ls = 0;
while ( $ls < ( $min - $lp ) && $was[ strlen( $was ) - 1 - $ls ] === $cur[ strlen( $cur ) - 1 - $ls ] ) { $ls++; }
$was_mid = substr( $was, $lp, strlen( $was ) - $lp - $ls );
$cur_mid = substr( $cur, $lp, strlen( $cur ) - $lp - $ls );
printf( "общий префикс: %d байт\n", $lp );
printf( "общий суффикс: %d байт\n", $ls );
printf( "различающийся участок в бэкапе:  [%s]  (%d байт)\n", $was_mid, strlen( $was_mid ) );
printf( "различающийся участок в текущем: [%s]  (%d байт)\n", $cur_mid, strlen( $cur_mid ) );
$one_sub = ( substr( $was, 0, $lp ) . $REPLACE . substr( $was, $lp + strlen( $was_mid ) ) === $cur )
	|| ( str_replace( $FIND, $REPLACE, $was ) === $cur );
printf( "отличие ровно в одной подстроке: %s\n", $one_sub ? 'да' : 'НЕТ' );
printf( "str_replace(FIND, REPLACE, бэкап) === текущее: %s\n", str_replace( $FIND, $REPLACE, $was ) === $cur ? 'да' : 'НЕТ' );
if ( str_replace( $FIND, $REPLACE, $was ) !== $cur ) { $fail[] = '1b: отличие не сводится к одной замене'; }

echo "\n--- 1c. поблочное сравнение attrs и innerContent (без serialize_block) ---\n";
$flatten = function ( $blocks, $path = '' ) use ( &$flatten ) {
	$out = array();
	foreach ( $blocks as $i => $b ) {
		$p = $path . '/' . $i . ':' . ( null === $b['blockName'] ? '(html)' : $b['blockName'] );
		$out[] = array(
			'path'         => $p,
			'blockName'    => $b['blockName'],
			'attrs'        => json_encode( $b['attrs'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
			'innerContent' => $b['innerContent'],
			'innerHTML'    => $b['innerHTML'],
		);
		$out = array_merge( $out, $flatten( $b['innerBlocks'], $p ) );
	}
	return $out;
};
$fa = $flatten( parse_blocks( $was ) );
$fb = $flatten( parse_blocks( $cur ) );
printf( "блоков в бэкапе: %d, в текущем: %d  %s\n", count( $fa ), count( $fb ), count( $fa ) === count( $fb ) ? '(совпало)' : '(РАСХОЖДЕНИЕ)' );
if ( count( $fa ) !== count( $fb ) ) { $fail[] = '1c: разное число блоков'; }

$diff_name = $diff_attrs = $diff_ic = 0;
$ic_changed = array();
$n = min( count( $fa ), count( $fb ) );
for ( $i = 0; $i < $n; $i++ ) {
	if ( $fa[ $i ]['blockName'] !== $fb[ $i ]['blockName'] ) {
		$diff_name++;
		printf( "  blockName расходится @ %s: [%s] -> [%s]\n", $fa[ $i ]['path'], $fa[ $i ]['blockName'], $fb[ $i ]['blockName'] );
	}
	if ( $fa[ $i ]['attrs'] !== $fb[ $i ]['attrs'] ) {
		$diff_attrs++;
		printf( "  attrs расходятся @ %s:\n    было  = %s\n    стало = %s\n", $fa[ $i ]['path'], $fa[ $i ]['attrs'], $fb[ $i ]['attrs'] );
	}
	if ( $fa[ $i ]['innerContent'] !== $fb[ $i ]['innerContent'] ) {
		$diff_ic++;
		$ic_changed[] = $fa[ $i ]['path'];
		printf( "  innerContent расходится @ %s:\n", $fa[ $i ]['path'] );
		$ca = $fa[ $i ]['innerContent'];
		$cb = $fb[ $i ]['innerContent'];
		printf( "    элементов: было %d, стало %d\n", count( $ca ), count( $cb ) );
		for ( $k = 0; $k < max( count( $ca ), count( $cb ) ); $k++ ) {
			$x = array_key_exists( $k, $ca ) ? $ca[ $k ] : '(нет элемента)';
			$y = array_key_exists( $k, $cb ) ? $cb[ $k ] : '(нет элемента)';
			if ( $x !== $y ) {
				printf( "    [%d] было  = %s\n", $k, null === $x ? '(null — место вложенного блока)' : '[' . $x . ']' );
				printf( "    [%d] стало = %s\n", $k, null === $y ? '(null — место вложенного блока)' : '[' . $y . ']' );
			}
		}
	}
}
printf( "\nитого расхождений: blockName=%d, attrs=%d, innerContent=%d\n", $diff_name, $diff_attrs, $diff_ic );
printf( "блоки с изменённым innerContent: %s\n", $ic_changed ? implode( ', ', $ic_changed ) : '(нет)' );
if ( 0 !== $diff_name || 0 !== $diff_attrs ) { $fail[] = '1c: изменились blockName или attrs'; }
if ( 1 !== $diff_ic ) { $fail[] = '1c: innerContent изменился не ровно в одном блоке (' . $diff_ic . ')'; }

/* ------------------------------------------------------------------ 2 */
echo "\n================== 2. post_name всех пяти ==================\n";
printf( "%-5s %-26s %-26s %s\n", 'ID', 'бэкап', 'сейчас', 'вердикт' );
echo str_repeat( '-', 90 ) . "\n";
foreach ( $ids as $id ) {
	$now = $wpdb->get_var( $wpdb->prepare( "SELECT post_name FROM {$wpdb->posts} WHERE ID=%d", $id ) );
	$same = ( $now === $bk[ $id ]['post_name'] );
	printf( "%-5s %-26s %-26s %s\n", $id, $bk[ $id ]['post_name'], $now, $same ? 'не изменился' : 'ИЗМЕНИЛСЯ' );
	if ( ! $same ) { $fail[] = "2: post_name {$id} изменился"; }
}

/* ------------------------------------------------------------------ 3 */
echo "\n================== 3. финальные <title> и meta description ==================\n";
echo "способ: YoastSEO()->meta->for_post(\$id), wp_cache_flush() перед каждым чтением\n\n";
foreach ( $ids as $id ) {
	wp_cache_flush();
	$m     = YoastSEO()->meta->for_post( $id );
	$same  = ( $m->title === $ETALON_TITLE[ $id ] );
	$fixed = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='_yoast_wpseo_title'", $id ) );
	echo str_repeat( '-', 74 ) . "\n";
	printf( "ID=%d\n", $id );
	printf( "  источник титла   = %s\n", null === $fixed ? "шаблон title-services (мета не задана)" : "мета _yoast_wpseo_title" );
	printf( "  эталон разведки  = [%s]\n", $ETALON_TITLE[ $id ] );
	printf( "  сейчас           = [%s]\n", $m->title );
	printf( "  ВЕРДИКТ          : %s\n", $same ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' );
	printf( "  description      = [%s]  (в разведке было пусто)\n", $m->description );
	if ( ! $same ) { $fail[] = "3: <title> {$id} разошёлся"; }
	if ( '' !== $m->description ) { $fail[] = "3: description {$id} перестал быть пустым"; }
}
echo "\nдля 12 и 14 мета _yoast_wpseo_title отсутствует — титл собирается шаблоном, как и раньше:\n";
foreach ( array( 12, 14 ) as $id ) {
	printf( "  ID=%-3s строк _yoast_wpseo_title = %d\n", $id, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='_yoast_wpseo_title'", $id ) ) );
}
printf( "title-services = [%s]\n", get_option( 'wpseo_titles' )['title-services'] );

/* ------------------------------------------------------------------ 4 */
echo "\n================== 4. вхождения в post_content + post_excerpt (без ревизий) ==================\n";
foreach ( array( 'Ритуальный агент', 'кладбище и поминальный обед' ) as $needle ) {
	$total = 0;
	echo "--- «{$needle}» ---\n";
	foreach ( array( 'post_content', 'post_excerpt' ) as $f ) {
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT ID, post_type, post_title, {$f} AS val FROM {$wpdb->posts}
			  WHERE post_type <> 'revision' AND {$f} LIKE %s",
			'%' . $wpdb->esc_like( $needle ) . '%'
		) );
		foreach ( $rows as $r ) {
			$c = substr_count( $r->val, $needle );
			if ( 0 === $c ) { continue; }
			$total += $c;
			printf( "  %s ID=%s type=%s hits=%d title=%s\n", $f, $r->ID, $r->post_type, $c, $r->post_title );
		}
	}
	printf( "  ИТОГО = %d  %s\n", $total, 0 === $total ? '(ожидается 0)' : '(ОЖИДАЛСЯ 0)' );
	if ( 0 !== $total ) { $fail[] = "4: «{$needle}» ещё встречается"; }
}
echo "\nсправочно, регистрозависимость: строчный вариант «ритуальный агент» вне зоны правок\n";
$low = $wpdb->get_results( $wpdb->prepare(
	"SELECT ID, post_type, post_title, post_content FROM {$wpdb->posts} WHERE post_type <> 'revision' AND post_content LIKE %s",
	'%' . $wpdb->esc_like( 'ритуальный агент' ) . '%'
) );
foreach ( $low as $r ) {
	printf( "  ID=%s type=%s hits(строчное)=%d title=%s\n", $r->ID, $r->post_type, substr_count( $r->post_content, 'ритуальный агент' ), $r->post_title );
}
echo "\nревизии (намеренно не чистим, старый текст в истории остаётся):\n";
foreach ( array( 'Ритуальный агент', 'кладбище и поминальный обед' ) as $needle ) {
	printf( "  «%s» в ревизиях: %d записей\n", $needle, (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='revision' AND (post_content LIKE %s OR post_excerpt LIKE %s)",
		'%' . $wpdb->esc_like( $needle ) . '%', '%' . $wpdb->esc_like( $needle ) . '%' ) ) );
}

/* ------------------------------------------------------------------ 5 */
echo "\n================== 5. wp_yoast_indexable 11-15 ==================\n";
foreach ( $ids as $id ) {
	$r = $wpdb->get_row( $wpdb->prepare( "SELECT id, updated_at, title, breadcrumb_title, description FROM {$t} WHERE object_type='post' AND object_id=%d", $id ), ARRAY_A );
	echo str_repeat( '-', 74 ) . "\n";
	printf( "ID=%d  (строка индексабла id=%s, updated_at=%s)\n", $id, $r['id'], $r['updated_at'] );
	printf( "  title            = %s\n", null === $r['title'] ? '(NULL)' : '[' . $r['title'] . ']' );
	printf( "  breadcrumb_title = %s\n", null === $r['breadcrumb_title'] ? '(NULL)' : '[' . $r['breadcrumb_title'] . ']' );
	printf( "  description      = %s\n", null === $r['description'] ? '(NULL)' : '[' . $r['description'] . ']' );
	if ( isset( $NEW_POST_TITLE[ $id ] ) && $r['title'] === $NEW_POST_TITLE[ $id ] ) {
		$fail[] = "5: в title индексабла {$id} лежит новый post_title";
	}
}

echo "\n=== сводка по трём переименованным ===\n";
printf( "%-5s %-54s %s\n", 'ID', 'post_title (видимый H1 и заголовок карточки)', 'breadcrumb_title' );
echo str_repeat( '-', 116 ) . "\n";
foreach ( array( 11, 13, 15 ) as $id ) {
	$pt = $wpdb->get_var( $wpdb->prepare( "SELECT post_title FROM {$wpdb->posts} WHERE ID=%d", $id ) );
	$bc = $wpdb->get_var( $wpdb->prepare( "SELECT breadcrumb_title FROM {$t} WHERE object_type='post' AND object_id=%d", $id ) );
	printf( "%-5s %-54s %s\n", $id, $pt, $bc );
}

echo "\n\n========================= ИТОГ ЭТАПА 3 =========================\n";
if ( $fail ) {
	echo "ЕСТЬ РАСХОЖДЕНИЯ:\n";
	foreach ( $fail as $f ) { echo "  - {$f}\n"; }
} else {
	echo "Все проверки пройдены, расхождений нет.\n";
}
exit( $fail ? 30 : 0 );
