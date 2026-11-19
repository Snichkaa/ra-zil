<?php
/**
 * Title: Услуга: стоимость — комплексы
 * Slug: razil/service-packages
 * Description: Стоимость комплексами: три карточки с ценой и составом.
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
<!-- wp:group {"className":"rz-svc-price__cards","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-price__cards">
<!-- wp:group {"className":"rz-svc-card rz-svc-pack","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-card rz-svc-pack"><!-- wp:heading {"level":3,"className":"rz-svc-pack__name"} -->
<h3 class="wp-block-heading rz-svc-pack__name">Базовый</h3><!-- /wp:heading -->
<!-- wp:paragraph {"className":"rz-svc-pack__price"} --><p class="rz-svc-pack__price">от 50 000 ₽</p><!-- /wp:paragraph -->
<!-- wp:list {"className":"rz-svc-pack__list"} --><ul class="wp-block-list rz-svc-pack__list"><!-- wp:list-item --><li>Оформление документов для морга, ЗАГСа и кладбища</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Бригада для проведения захоронения</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Катафалк в день захоронения</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Гроб с бархатной обивкой</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Подушка и комплект внутреннего убранства гроба</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Памятник из мраморной крошки</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Нанесение надписи на памятник</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Траурный венок с лентой</li><!-- /wp:list-item -->
</ul><!-- /wp:list --></div><!-- /wp:group -->

<!-- wp:group {"className":"rz-svc-card rz-svc-pack","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-card rz-svc-pack"><!-- wp:heading {"level":3,"className":"rz-svc-pack__name"} -->
<h3 class="wp-block-heading rz-svc-pack__name">Стандартный</h3><!-- /wp:heading -->
<!-- wp:paragraph {"className":"rz-svc-pack__price"} --><p class="rz-svc-pack__price">от 75 000 ₽</p><!-- /wp:paragraph -->
<!-- wp:list {"className":"rz-svc-pack__list"} --><ul class="wp-block-list rz-svc-pack__list"><!-- wp:list-item --><li>Оформление документов для морга, ЗАГСа и кладбища</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Бригада для проведения захоронения</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Катафалк в день захоронения</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Гроб с бархатной обивкой</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Внутреннее убранство гроба</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Памятник из мраморной крошки</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Нанесение надписи на памятник</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Траурный венок с лентой</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Зал для прощания — 20 минут</li><!-- /wp:list-item -->
</ul><!-- /wp:list --></div><!-- /wp:group -->

<!-- wp:group {"className":"rz-svc-card rz-svc-pack","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-card rz-svc-pack"><!-- wp:heading {"level":3,"className":"rz-svc-pack__name"} -->
<h3 class="wp-block-heading rz-svc-pack__name">Расширенный</h3><!-- /wp:heading -->
<!-- wp:paragraph {"className":"rz-svc-pack__price"} --><p class="rz-svc-pack__price">от 110 000 ₽</p><!-- /wp:paragraph -->
<!-- wp:list {"className":"rz-svc-pack__list"} --><ul class="wp-block-list rz-svc-pack__list"><!-- wp:list-item --><li>Оформление необходимых документов</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Бригада для проведения захоронения</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Катафалк в день захоронения</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Элитный деревянный гроб</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Внутреннее убранство гроба</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Траурный венок с лентой</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Автобус для сопровождающих</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Зал для прощания — 20 минут</li><!-- /wp:list-item -->
<!-- wp:list-item --><li>Гранитный памятник с надписью, высотой до 80 см</li><!-- /wp:list-item -->
</ul><!-- /wp:list --></div><!-- /wp:group -->
</div><!-- /wp:group -->