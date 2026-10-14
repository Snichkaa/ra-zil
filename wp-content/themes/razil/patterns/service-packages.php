<?php
/**
 * Title: Услуга: стоимость — комплексы
 * Slug: razil/service-packages
 * Description: Стоимость комплексами: четыре карточки с ценой и составом.
 * Categories: razil-service
 * Keywords: услуга, стоимость, цена, комплекс
 * Post Types: services
 * Inserter: true
 *
 * Годится ТОЛЬКО там, где в тексте страницы названы сами комплексы.
 * Нет названий — ставится прайс (razil/service-pricelist), а не эти
 * карточки с придуманными именами тарифов.
 *
 * [ЦЕНА] и [СОСТАВ] — рыба паттерна, и на живые страницы они
 * не попадают НИКОГДА. Чек-лист перед публикацией ищет обе строки
 * поиском по странице.
 *
 * ЦЕНЫ В КАРТОЧКАХ НА ОДНОЙ ЛИНИИ. Заголовок получает min-height
 * в две строки, поэтому цена под однословным названием стоит там же,
 * где под двухсловным. Без этого ряд карточек читался бы ступенькой.
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

<!-- wp:group {"className":"rz-svc-price__cards","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-price__cards"><!-- wp:group {"className":"rz-svc-card rz-svc-pack","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-card rz-svc-pack"><!-- wp:heading {"level":3,"className":"rz-svc-pack__name"} -->
<h3 class="wp-block-heading rz-svc-pack__name">Социальные похороны</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"rz-svc-pack__price"} -->
<p class="rz-svc-pack__price">от [ЦЕНА] ₽</p>
<!-- /wp:paragraph -->

<!-- wp:list {"className":"rz-svc-pack__list"} -->
<ul class="wp-block-list rz-svc-pack__list"><!-- wp:list-item -->
<li>[СОСТАВ]</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"rz-svc-card rz-svc-pack","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-card rz-svc-pack"><!-- wp:heading {"level":3,"className":"rz-svc-pack__name"} -->
<h3 class="wp-block-heading rz-svc-pack__name">Базовый</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"rz-svc-pack__price"} -->
<p class="rz-svc-pack__price">от [ЦЕНА] ₽</p>
<!-- /wp:paragraph -->

<!-- wp:list {"className":"rz-svc-pack__list"} -->
<ul class="wp-block-list rz-svc-pack__list"><!-- wp:list-item -->
<li>[СОСТАВ]</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"rz-svc-card rz-svc-pack","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-card rz-svc-pack"><!-- wp:heading {"level":3,"className":"rz-svc-pack__name"} -->
<h3 class="wp-block-heading rz-svc-pack__name">Стандартный</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"rz-svc-pack__price"} -->
<p class="rz-svc-pack__price">от [ЦЕНА] ₽</p>
<!-- /wp:paragraph -->

<!-- wp:list {"className":"rz-svc-pack__list"} -->
<ul class="wp-block-list rz-svc-pack__list"><!-- wp:list-item -->
<li>[СОСТАВ]</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"rz-svc-card rz-svc-pack","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-card rz-svc-pack"><!-- wp:heading {"level":3,"className":"rz-svc-pack__name"} -->
<h3 class="wp-block-heading rz-svc-pack__name">Расширенный</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"rz-svc-pack__price"} -->
<p class="rz-svc-pack__price">от [ЦЕНА] ₽</p>
<!-- /wp:paragraph -->

<!-- wp:list {"className":"rz-svc-pack__list"} -->
<ul class="wp-block-list rz-svc-pack__list"><!-- wp:list-item -->
<li>[СОСТАВ]</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
