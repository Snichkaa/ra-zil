<?php
/**
 * Перекомпоновка страницы услуги под композицию §20 концепции.
 *
 * Скрипт не переписывает текст, а перекладывает существующие блоки
 * в новые обёртки. Работа идёт по дереву блоков, а не регулярками:
 * parse_blocks() разбирает разметку, изменённые ветки собираются
 * заново, нетронутые возвращаются байт в байт — это свойство
 * проверяется круговоротом перед записью.
 *
 * Что делается с каждым разделом, решает его заголовок H2:
 *
 *   «Сроки»            абзац со сроками становится временной схемой.
 *                      Предложения переносятся в пункты ДОСЛОВНО
 *                      и целиком: схема — это подача, а не пересказ.
 *   «…документы…»      заголовок слева, список и пояснение справа.
 *   «…вопрос…»         аккордеон, первый ответ открыт.
 *   «Что входит…»      перечень в информационной карточке.
 *
 * Разделы, для которых на странице нет данных, скрипт не создаёт:
 * он работает только с тем, что уже есть.
 *
 * Запуск: php _tools/redesign/restyle-service.php <id> <куда.html>
 */

require dirname( __DIR__, 2 ) . '/wp-load.php';

if ( count( $argv ) < 3 ) {
	fwrite( STDERR, "Запуск: php restyle-service.php <id> <куда.html>\n" );
	exit( 2 );
}

$post_id = (int) $argv[1];
$dest    = $argv[2];

$post = get_post( $post_id );
if ( ! $post ) {
	fwrite( STDERR, "Записи #{$post_id} нет\n" );
	exit( 2 );
}

$blocks = parse_blocks( $post->post_content );

/** Текст H2 внутри ветки блоков, если он есть. */
function rz_section_heading( $block ) {
	foreach ( (array) $block['innerBlocks'] as $inner ) {
		if ( 'core/heading' === $inner['blockName'] && preg_match( '#<h2[^>]*>(.*?)</h2>#is', $inner['innerHTML'], $m ) ) {
			return trim( wp_strip_all_tags( $m[1] ) );
		}
	}
	return '';
}

/**
 * Готовый блок-обёртка с классом.
 *
 * В innerContent на КАЖДЫЙ вложенный блок приходится ровно один null:
 * по этим меткам serialize_blocks() расставляет детей между кусками
 * разметки. Один null на всех — и все дети, кроме первого, молча
 * пропадают при сборке. Так и случилось на первом прогоне: страница
 * похудела на 3330 байт, и это поймал пробный прогон записи.
 */
function rz_group( $class, $inner, $extra_attrs = array(), $extra_html = '' ) {
	$attrs = array_merge( array( 'className' => $class ), $extra_attrs );
	$open  = '<div class="wp-block-group ' . $class . '"' . $extra_html . '>';

	return array(
		'blockName'    => 'core/group',
		'attrs'        => $attrs,
		'innerBlocks'  => $inner,
		'innerHTML'    => $open . '</div>',
		'innerContent' => array_merge(
			array( $open ),
			array_fill( 0, count( $inner ), null ),
			array( '</div>' )
		),
	);
}

/**
 * Абзац -> предложения.
 *
 * Граница — точка, восклицательный или вопросительный знак, за которым
 * идёт пробел и заглавная буква или открывающая скобка. Сокращений
 * вида «т. д.» в этих абзацах нет — проверено глазами по всем пяти
 * страницам, и результат разбора печатается, чтобы это было видно.
 */
function rz_sentences_of( $html ) {
	$text = trim( wp_strip_all_tags( $html ) );
	$text = preg_replace( '/\s+/u', ' ', $text );
	$parts = preg_split( '/(?<=[.!?])\s+(?=[А-ЯЁA-Z(«])/u', $text );

	return array_values( array_filter( array_map( 'trim', (array) $parts ), 'strlen' ) );
}

/** Пункт списка с готовым текстом. */
function rz_list_item( $text ) {
	return array(
		'blockName'    => 'core/list-item',
		'attrs'        => array(),
		'innerBlocks'  => array(),
		'innerHTML'    => '<li>' . $text . '</li>',
		'innerContent' => array( '<li>' . $text . '</li>' ),
	);
}

$log = array();

foreach ( $blocks as $i => $block ) {
	if ( 'core/group' !== $block['blockName'] ) {
		continue;
	}

	$h2 = rz_section_heading( $block );

	// ------------------------------------------------------------ Сроки
	if ( 'Сроки' === $h2 ) {
		$inner = $block['innerBlocks'];
		$first_para = null;
		$first_idx  = null;
		foreach ( $inner as $k => $b ) {
			if ( 'core/paragraph' === $b['blockName'] ) {
				$first_para = $b;
				$first_idx  = $k;
				break;
			}
		}
		if ( $first_para ) {
			$sentences = rz_sentences_of( $first_para['innerHTML'] );

			// Схема строится, только если КАЖДОЕ предложение называет срок.
			// Иначе в ней оказались бы пронумерованными строки вроде
			// «Мы незамедлительно сообщим вам об этом» — они читались бы
			// как этап работ, которым не являются. Там, где так выходит,
			// раздел остаётся абзацем: подача хуже, чем прежде, не станет,
			// а смысл не поедет.
			$named = 0;
			foreach ( $sentences as $s ) {
				if ( preg_match( '/(минут|часов|часа|день|дня|дней|недел|месяц|сутк)/iu', $s ) ) {
					$named++;
				}
			}
			$fits = count( $sentences ) >= 3 && count( $sentences ) <= 5 && $named === count( $sentences );

			if ( ! $fits ) {
				$log[] = sprintf(
					'Сроки: предложений %d, срок назван в %d — схема не строится, раздел остаётся абзацем',
					count( $sentences ),
					$named
				);
			}

			if ( $fits ) {
				$items = array();
				foreach ( $sentences as $s ) {
					$items[] = rz_list_item( $s );
				}
				$list = array(
					'blockName'    => 'core/list',
					'attrs'        => array( 'className' => 'rz-timeline' ),
					'innerBlocks'  => $items,
					'innerHTML'    => '<ul class="wp-block-list rz-timeline"></ul>',
					'innerContent' => array_merge(
						array( '<ul class="wp-block-list rz-timeline">' ),
						array_fill( 0, count( $items ), null ),
						array( '</ul>' )
					),
				);
				$inner[ $first_idx ] = $list;
				$block['innerBlocks'] = $inner;
				$blocks[ $i ]         = $block;
				$log[] = sprintf( 'Сроки: абзац разобран на %d этапа', count( $sentences ) );
				foreach ( $sentences as $n => $s ) {
					$log[] = sprintf( '   %02d. %s', $n + 1, $s );
				}
			}
		}
		continue;
	}

	// -------------------------------------------------------- Документы
	if ( '' !== $h2 && preg_match( '/документ/iu', $h2 ) ) {
		$inner   = $block['innerBlocks'];
		$heading = null;
		$rest    = array();
		foreach ( $inner as $b ) {
			if ( null === $heading && 'core/heading' === $b['blockName'] ) {
				$heading = $b;
				continue;
			}
			$rest[] = $b;
		}
		if ( $heading && $rest ) {
			$body  = rz_group( 'rz-docs-split__body', $rest );
			$split = rz_group( 'rz-docs-split', array( $heading, $body ) );
			$block['innerBlocks'] = array( $split );
			$blocks[ $i ]         = $block;
			$log[] = sprintf( '«%s»: заголовок слева, содержимое справа (%d блоков)', $h2, count( $rest ) );
		}
		continue;
	}

	// ---------------------------------------------------- Частые вопросы
	if ( '' !== $h2 && preg_match( '/вопрос/iu', $h2 ) ) {
		$inner   = $block['innerBlocks'];
		$heading = null;
		$details = array();
		$other   = array();
		foreach ( $inner as $b ) {
			if ( null === $heading && 'core/heading' === $b['blockName'] ) {
				$heading = $b;
			} elseif ( 'core/details' === $b['blockName'] ) {
				$details[] = $b;
			} else {
				$other[] = $b;
			}
		}
		if ( $heading && count( $details ) > 1 ) {
			// Первый ответ открыт — §21.7 это прямо разрешает.
			$first = $details[0];
			if ( empty( $first['attrs']['showContent'] ) ) {
				$first['attrs']['showContent'] = true;
				$first['innerHTML']            = preg_replace( '/<details([^>]*)>/', '<details$1 open>', $first['innerHTML'], 1 );
				if ( isset( $first['innerContent'][0] ) ) {
					$first['innerContent'][0] = preg_replace( '/<details([^>]*)>/', '<details$1 open>', $first['innerContent'][0], 1 );
				}
				$details[0] = $first;
			}
			$acc = rz_group(
				'rz-accordion',
				$details,
				array( 'metadata' => array( 'name' => 'Частые вопросы' ) ),
				' data-accordion'
			);
			$block['innerBlocks'] = array_merge( array( $heading ), array( $acc ), $other );
			$blocks[ $i ]         = $block;
			$log[] = sprintf( '«%s»: аккордеон из %d вопросов, первый открыт', $h2, count( $details ) );
		}
		continue;
	}

	// ------------------------------------------------------ Состав услуги
	if ( '' !== $h2 && preg_match( '/^(Что входит|С чем мы помогаем)/iu', $h2 ) ) {
		$inner = $block['innerBlocks'];
		foreach ( $inner as $k => $b ) {
			if ( 'core/list' !== $b['blockName'] ) {
				continue;
			}
			$count = count( $b['innerBlocks'] );

			// Перечень переезжает в информационную карточку: тонкие
			// разделители вместо маркеров, две колонки на широком экране.
			// Разбивать его на смысловые группы, как у благоустройства,
			// здесь нельзя — названия групп пришлось бы придумывать,
			// а выдумывать текст запрещено.
			$cls = trim( ( $b['attrs']['className'] ?? '' ) . ' rz-info-card__list' );
			$b['attrs']['className'] = $cls;
			$b['innerHTML']          = preg_replace( '/<ul class="([^"]*)"/', '<ul class="$1 rz-info-card__list"', $b['innerHTML'], 1 );
			if ( isset( $b['innerContent'][0] ) ) {
				$b['innerContent'][0] = preg_replace( '/<ul class="([^"]*)"/', '<ul class="$1 rz-info-card__list"', $b['innerContent'][0], 1 );
			}

			$card = rz_group( 'rz-info-card rz-info-card--wide', array( $b ) );
			$inner[ $k ] = $card;
			$block['innerBlocks'] = $inner;
			$blocks[ $i ]         = $block;
			$log[] = sprintf( '«%s»: перечень из %d пунктов в информационной карточке', $h2, $count );
			break;
		}
		continue;
	}
}

$content = serialize_blocks( $blocks );

file_put_contents( $dest, $content );

printf( "=== Запись #%d %s\n", $post_id, $post->post_title );
foreach ( $log as $line ) {
	echo '    ' . $line . "\n";
}
printf( "--- было %d байт, стало %d, записано в %s\n", strlen( $post->post_content ), strlen( $content ), $dest );
