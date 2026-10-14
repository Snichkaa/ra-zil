<?php
/**
 * Title: Услуга: стоимость — прайс
 * Slug: razil/service-pricelist
 * Description: Стоимость списком: название работы слева, цена справа.
 * Categories: razil-service
 * Keywords: услуга, стоимость, цена, прайс
 * Post Types: services
 * Inserter: true
 *
 * СТРОКИ С «…» НЕ УДАЛЯЮТСЯ. В левой колонке — названия работ,
 * а это содержание страницы; пустая правая ячейка означает только,
 * что цену ещё не назвали. Удалить строку значило бы убрать
 * со страницы сведение о том, что такая работа есть.
 *
 * ЦЕНА НЕ РАЗРЫВАЕТСЯ ТОЛЬКО ОТ 760 px. Ниже перенос разрешён —
 * решение 18 отчёта от 19 сентября: при 200 % текста nowrap уводит
 * страницу вбок, и ради неразрывного числа ломается вся раскладка.
 *
 * @package Razil
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"align":"full","className":"rz-svc-sec rz-svc-price","style":{"color":{"background":"var(\u002d\u002drz-surface)"}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignfull rz-svc-sec rz-svc-price has-background" style="background-color:var(--rz-surface)"><!-- wp:group {"className":"rz-svc-sec__inner","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-sec__inner"><!-- wp:group {"className":"rz-svc-price__head","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-price__head"><!-- wp:group {"className":"rz-svc-price__text","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-price__text"><!-- wp:heading -->
<h2 class="wp-block-heading">Сколько стоит услуга</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Пояснение к стоимости.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:paragraph {"className":"is-style-panel"} -->
<p class="is-style-panel">Оговорка, относящаяся ко всему разделу.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:table {"hasFixedLayout":false,"className":"is-style-pricelist"} -->
<figure class="wp-block-table is-style-pricelist"><table><thead><tr><th>Услуга</th><th>Стоимость</th></tr></thead><tbody><tr><td>Название работы</td><td>…</td></tr><tr><td>Ещё одна работа</td><td>…</td></tr></tbody></table></figure>
<!-- /wp:table --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
