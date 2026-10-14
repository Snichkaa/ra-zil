<?php
/**
 * Title: Услуга: что входит
 * Slug: razil/service-stages
 * Description: Состав услуги: четыре карточки-группы со списками, под ними заметка.
 * Categories: razil-service
 * Keywords: услуга, состав, что входит, этапы
 * Post Types: services
 * Inserter: true
 *
 * ЧИСЛО КАРТОЧЕК НЕ ЗАКРЕПЛЕНО. Сетка в две колонки переживает и три
 * карточки, и пять, и шесть: последняя в нечётном ряду просто занимает
 * свою половину. Проверено замером, а не предположением.
 *
 * НОМЕРА КАРТОЧЕК рисуются счётчиком CSS по заголовкам H3. Если группе
 * назначить стиль «Без номеров» (класс is-style-no-numbers), кругов
 * не будет вовсе — так сделано на перевозке и на помощи с документами,
 * где состав не последовательность шагов, а перечень направлений.
 *
 * ЗАМЕТКА — абзац со стилем «Заметка» (is-style-note). Сюда уходит
 * текст, который относится ко всему разделу, но не помещается
 * ни в одну группу: по правилу Т7 задания такой текст не удаляется,
 * а ставится туда, где уместен по смыслу.
 *
 * @package Razil
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"align":"full","className":"rz-svc-sec rz-svc-stages","style":{"color":{"background":"var(\u002d\u002drz-bg)"}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignfull rz-svc-sec rz-svc-stages has-background" style="background-color:var(--rz-bg)"><!-- wp:group {"className":"rz-svc-sec__inner","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-sec__inner"><!-- wp:heading -->
<h2 class="wp-block-heading">Что входит в услугу</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"className":"rz-svc-stages__lead"} -->
<p class="rz-svc-stages__lead">Короткое пояснение к составу услуги.</p>
<!-- /wp:paragraph -->

<!-- wp:group {"className":"rz-svc-stages__grid","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-stages__grid"><!-- wp:group {"className":"rz-svc-card rz-svc-stages__card","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-card rz-svc-stages__card"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Первая группа</h3>
<!-- /wp:heading -->

<!-- wp:list {"className":"rz-svc-stages__list"} -->
<ul class="wp-block-list rz-svc-stages__list"><!-- wp:list-item -->
<li>Пункт состава</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Ещё пункт состава</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"rz-svc-card rz-svc-stages__card","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-card rz-svc-stages__card"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Вторая группа</h3>
<!-- /wp:heading -->

<!-- wp:list {"className":"rz-svc-stages__list"} -->
<ul class="wp-block-list rz-svc-stages__list"><!-- wp:list-item -->
<li>Пункт состава</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Ещё пункт состава</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"rz-svc-card rz-svc-stages__card","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-card rz-svc-stages__card"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Третья группа</h3>
<!-- /wp:heading -->

<!-- wp:list {"className":"rz-svc-stages__list"} -->
<ul class="wp-block-list rz-svc-stages__list"><!-- wp:list-item -->
<li>Пункт состава</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Ещё пункт состава</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"rz-svc-card rz-svc-stages__card","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-card rz-svc-stages__card"><!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">Четвёртая группа</h3>
<!-- /wp:heading -->

<!-- wp:list {"className":"rz-svc-stages__list"} -->
<ul class="wp-block-list rz-svc-stages__list"><!-- wp:list-item -->
<li>Пункт состава</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Ещё пункт состава</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:paragraph {"className":"is-style-note"} -->
<p class="is-style-note">Примечание ко всему разделу.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
