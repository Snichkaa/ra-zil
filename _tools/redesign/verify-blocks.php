<?php
/**
 * Проверка круговорота блочной разметки.
 *
 * Для каждого шаблона темы и каждой записи проверяется равенство
 * serialize_blocks( parse_blocks( $c ) ) === $c. Сравнение побайтовое:
 * совпадение числа блоков ничего не доказывает — разобрать и собрать
 * можно с потерей пробелов, атрибутов или порядка ключей в JSON.
 *
 * Запуск: php _tools/redesign/verify-blocks.php
 * Код возврата 1, если хоть один объект не прошёл.
 */

require dirname( __DIR__, 2 ) . '/wp-load.php';

$failed = 0;
$total  = 0;

/**
 * Одна проверка круговорота.
 *
 * При расхождении печатается первая разошедшаяся позиция с окрестностью:
 * без неё «УПАЛ» не подсказывает, что именно чинить.
 */
function rz_roundtrip( $label, $content ) {
	global $failed, $total;

	$total++;

	$back = serialize_blocks( parse_blocks( $content ) );

	if ( $back === $content ) {
		printf( "ПРОШЁЛ  %s (%d байт)\n", $label, strlen( $content ) );
		return true;
	}

	$failed++;

	$len = min( strlen( $content ), strlen( $back ) );
	$at  = $len;
	for ( $i = 0; $i < $len; $i++ ) {
		if ( $content[ $i ] !== $back[ $i ] ) {
			$at = $i;
			break;
		}
	}

	printf(
		"УПАЛ    %s (было %d байт, стало %d, первое расхождение на байте %d)\n",
		$label,
		strlen( $content ),
		strlen( $back ),
		$at
	);
	printf( "        было:  %s\n", rz_excerpt( $content, $at ) );
	printf( "        стало: %s\n", rz_excerpt( $back, $at ) );

	return false;
}

/** Окрестность байта в печатаемом виде. */
function rz_excerpt( $s, $at ) {
	$from = max( 0, $at - 40 );
	$part = substr( $s, $from, 120 );
	$part = str_replace( array( "\r", "\n", "\t" ), array( '\r', '\n', '\t' ), $part );
	return $part;
}

$theme_dir = get_stylesheet_directory();

$files = array();
foreach ( array( 'templates', 'parts' ) as $sub ) {
	$dir = $theme_dir . '/' . $sub;
	if ( ! is_dir( $dir ) ) {
		continue;
	}
	foreach ( glob( $dir . '/*.html' ) as $file ) {
		$files[] = $file;
	}
}
sort( $files, SORT_STRING );

echo "--- Шаблоны темы\n";
foreach ( $files as $file ) {
	$rel = str_replace( '\\', '/', substr( $file, strlen( $theme_dir ) + 1 ) );
	rz_roundtrip( $rel, file_get_contents( $file ) );
}

$types = get_post_types( array( 'public' => true ), 'names' );
$types = array_values( array_diff( $types, array( 'attachment' ) ) );
sort( $types, SORT_STRING );

$posts = get_posts(
	array(
		'post_type'        => $types,
		'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future' ),
		'numberposts'      => -1,
		'orderby'          => 'ID',
		'order'            => 'ASC',
		'suppress_filters' => true,
	)
);

echo "\n--- Записи\n";
foreach ( $posts as $post ) {
	rz_roundtrip(
		sprintf( '#%d [%s] %s', $post->ID, $post->post_type, $post->post_title ),
		$post->post_content
	);
}

printf( "\n--- ИТОГО: проверено %d, упало %d\n", $total, $failed );

exit( $failed > 0 ? 1 : 0 );
