<?php
/**
 * Этап 2.4, шаг 1 — dry-run по post_excerpt ID=11. Только чтение.
 * «Было» сверяется с эталоном из задания побайтово: если текущее значение
 * не равно ожидаемому, писать нельзя.
 */
require dirname(__DIR__, 2) . '/wp-load.php';
global $wpdb;

$OLD = 'Возьмём на себя документы, транспорт, кладбище и поминальный обед. Ритуальный агент приедет в любое время суток и будет рядом на всех этапах прощания.';
$NEW = 'Возьмём на себя документы, транспорт, организацию захоронения и поминальный обед. Наш специалист приедет в любое время суток и будет рядом на всех этапах прощания.';

$cur = $wpdb->get_var( "SELECT post_excerpt FROM {$wpdb->posts} WHERE ID=11" );

echo "=== DRY-RUN 2.4: post_excerpt ID=11 ===\n\n";
echo "БЫЛО (из БД):\n[" . $cur . "]\n";
printf( "  %d байт / %d символов, md5=%s\n\n", strlen( $cur ), mb_strlen( $cur ), md5( $cur ) );

echo "ОЖИДАЕМОЕ «было» (эталон из задания):\n[" . $OLD . "]\n";
printf( "  %d байт / %d символов, md5=%s\n\n", strlen( $OLD ), mb_strlen( $OLD ), md5( $OLD ) );

$match = ( $cur === $OLD );
printf( "сверка текущего значения с эталоном «было»: %s\n", $match ? 'СОВПАЛО' : 'РАСХОЖДЕНИЕ' );
if ( ! $match ) {
	echo "  текущее, hex = " . bin2hex( $cur ) . "\n";
	echo "  эталон,  hex = " . bin2hex( $OLD ) . "\n";
	echo "СТОП: писать нельзя, исходное значение не то, что описано в задании.\n";
	exit( 10 );
}

echo "\nСТАНЕТ:\n[" . $NEW . "]\n";
printf( "  %d байт / %d символов, md5=%s\n", strlen( $NEW ), mb_strlen( $NEW ), md5( $NEW ) );

printf( "\nдельта: %+d байт, %+d символов\n", strlen( $NEW ) - strlen( $OLD ), mb_strlen( $NEW ) - mb_strlen( $OLD ) );

echo "\n=== пословный diff ===\n";
$a = preg_split( '/(?<= )/u', $OLD );
$b = preg_split( '/(?<= )/u', $NEW );
$n = max( count( $a ), count( $b ) );
for ( $i = 0; $i < $n; $i++ ) {
	$x = isset( $a[ $i ] ) ? $a[ $i ] : '';
	$y = isset( $b[ $i ] ) ? $b[ $i ] : '';
	if ( $x !== $y ) {
		printf( "  поз.%-3d  было=[%s]  стало=[%s]\n", $i, $x, $y );
	}
}

echo "\n=== контроль ключевых подстрок ===\n";
foreach ( array( 'кладбище и поминальный обед', 'Ритуальный агент', 'организацию захоронения', 'Наш специалист' ) as $s ) {
	printf( "  «%-30s»  в «было»=%d  в «стало»=%d\n", $s, mb_substr_count( $OLD, $s ), mb_substr_count( $NEW, $s ) );
}

echo "\nНапоминание: этот excerpt выводится в двух местах — карточка на /uslugi/\n";
echo "(core/post-excerpt внутри query loop) и лид под H1 на странице услуги\n";
echo "(core/post-excerpt в single-services.html). Оба места получат новый текст.\n";
echo "\nЗАПИСИ В БД НЕ ПРОИЗВОДИЛОСЬ.\n";
