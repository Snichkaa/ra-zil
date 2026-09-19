<?php
/**
 * Title: Страница услуги
 * Slug: razil/service-page
 * Description: Композиция страницы услуги: вводный экран, состав работ четырьмя группами, галерея результата, документы, сроки временной схемой, стоимость и частые вопросы.
 * Categories: razil
 * Keywords: услуга, состав, сроки, вопросы
 * Block Types: core/post-content
 * Inserter: true
 *
 * Скелет композиции из раздела 20 концепции. Тексты здесь — со страницы
 * благоустройства: они показывают, что и куда пишется, и заменяются
 * текстом своей услуги.
 *
 * Блоки, для которых у услуги нет данных, УДАЛЯЮТСЯ целиком, а не
 * заполняются общими словами: пустой раздел хуже отсутствующего
 * (§23.2 концепции).
 *
 * @package Razil
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"className":"rz-sec"} --><div class="wp-block-group rz-sec">
<!-- wp:group {"className":"rz-side rz-side\u002d\u002dphoto-left"} -->
<div class="wp-block-group rz-side rz-side--photo-left">
<!-- wp:group {"className":"rz-side__text"} -->
<div class="wp-block-group rz-side__text">
<!-- wp:paragraph -->
<p>Вводный текст услуги.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

<!-- wp:html -->
<div class="rz-photo-slot" data-slot="1" style="--rz-slot-ratio: 4/3">
<span class="rz-photo-slot__num">Фото 1</span>
<span class="rz-photo-slot__hint">Главное изображение услуги</span>
</div>
<!-- /wp:html -->
</div>
<!-- /wp:group -->

<!-- wp:group {"className":"rz-callout"} -->
<div class="wp-block-group rz-callout">
<!-- wp:paragraph -->
<p>Важное примечание.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
</div><!-- /wp:group -->
<!-- wp:group {"className":"rz-sec"} --><div class="wp-block-group rz-sec">
<!-- wp:heading {"level":2} -->
<h2>Что входит в услугу</h2>
<!-- /wp:heading -->

<!-- wp:group {"className":"rz-compose"} -->
<div class="wp-block-group rz-compose">
<!-- wp:group {"className":"rz-info-card"} -->
<div class="wp-block-group rz-info-card">
<!-- wp:heading {"level":3,"fontSize":"heading-s"} -->
<h3 class="has-heading-s-font-size">Подготовка участка</h3>
<!-- /wp:heading -->
<!-- wp:list {"className":"rz-info-card__list"} -->
<ul class="wp-block-list rz-info-card__list">
<!-- wp:list-item -->
<li>Пункт состава.</li>
<!-- /wp:list-item -->
</ul>
<!-- /wp:list -->
</div>
<!-- /wp:group -->
<!-- wp:group {"className":"rz-info-card"} -->
<div class="wp-block-group rz-info-card">
<!-- wp:heading {"level":3,"fontSize":"heading-s"} -->
<h3 class="has-heading-s-font-size">Основание и покрытие</h3>
<!-- /wp:heading -->
<!-- wp:list {"className":"rz-info-card__list"} -->
<ul class="wp-block-list rz-info-card__list">
<!-- wp:list-item -->
<li>Пункт состава.</li>
<!-- /wp:list-item -->
</ul>
<!-- /wp:list -->
</div>
<!-- /wp:group -->
<!-- wp:group {"className":"rz-info-card"} -->
<div class="wp-block-group rz-info-card">
<!-- wp:heading {"level":3,"fontSize":"heading-s"} -->
<h3 class="has-heading-s-font-size">Оформление участка</h3>
<!-- /wp:heading -->
<!-- wp:list {"className":"rz-info-card__list"} -->
<ul class="wp-block-list rz-info-card__list">
<!-- wp:list-item -->
<li>Пункт состава.</li>
<!-- /wp:list-item -->
</ul>
<!-- /wp:list -->
</div>
<!-- /wp:group -->
<!-- wp:group {"className":"rz-info-card"} -->
<div class="wp-block-group rz-info-card">
<!-- wp:heading {"level":3,"fontSize":"heading-s"} -->
<h3 class="has-heading-s-font-size">Уход и завершение</h3>
<!-- /wp:heading -->
<!-- wp:list {"className":"rz-info-card__list"} -->
<ul class="wp-block-list rz-info-card__list">
<!-- wp:list-item -->
<li>Пункт состава.</li>
<!-- /wp:list-item -->
</ul>
<!-- /wp:list -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->

<!-- wp:group {"className":"rz-project-gallery"} -->
<div class="wp-block-group rz-project-gallery">
<!-- wp:html -->
<div class="rz-photo-slot" data-slot="1" style="--rz-slot-ratio: 4/3">
<span class="rz-photo-slot__num">Фото 1</span>
<span class="rz-photo-slot__hint">Готовый результат, общий план</span>
</div>
<!-- /wp:html -->
<!-- wp:html -->
<div class="rz-photo-slot" data-slot="2" style="--rz-slot-ratio: 4/3">
<span class="rz-photo-slot__num">Фото 2</span>
<span class="rz-photo-slot__hint">До работ</span>
</div>
<!-- /wp:html -->
<!-- wp:html -->
<div class="rz-photo-slot" data-slot="3" style="--rz-slot-ratio: 4/3">
<span class="rz-photo-slot__num">Фото 3</span>
<span class="rz-photo-slot__hint">После работ, тот же ракурс</span>
</div>
<!-- /wp:html -->
<!-- wp:html -->
<div class="rz-photo-slot" data-slot="4" style="--rz-slot-ratio: 4/3">
<span class="rz-photo-slot__num">Фото 4</span>
<span class="rz-photo-slot__hint">Другой завершённый проект</span>
</div>
<!-- /wp:html -->
<!-- wp:html -->
<div class="rz-photo-slot" data-slot="5" style="--rz-slot-ratio: 4/3">
<span class="rz-photo-slot__num">Фото 5</span>
<span class="rz-photo-slot__hint">Крупная деталь</span>
</div>
<!-- /wp:html -->
</div>
<!-- /wp:group -->
</div><!-- /wp:group -->
<!-- wp:group {"className":"rz-sec"} --><div class="wp-block-group rz-sec">
<!-- wp:group {"className":"rz-docs-split"} -->
<div class="wp-block-group rz-docs-split">
<!-- wp:heading {"level":2} -->
<h2>Какие документы нужны</h2>
<!-- /wp:heading -->

<!-- wp:group {"className":"rz-docs-split__body"} -->
<div class="wp-block-group rz-docs-split__body">
<!-- wp:list -->
<ul class="wp-block-list">
<!-- wp:list-item -->
<li>Документ от заказчика.</li>
<!-- /wp:list-item -->
</ul>
<!-- /wp:list -->

<!-- wp:paragraph -->
<p>Пояснение об ограничениях.</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
</div>
<!-- /wp:group -->
</div><!-- /wp:group -->
<!-- wp:group {"className":"rz-sec"} --><div class="wp-block-group rz-sec">
<!-- wp:heading {"level":2} -->
<h2>Сроки</h2>
<!-- /wp:heading -->

<!-- wp:list {"className":"rz-timeline"} -->
<ul class="wp-block-list rz-timeline">
<!-- wp:list-item -->
<li>Этап и его срок.</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Этап и его срок.</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Этап и его срок.</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li>Этап и его срок.</li>
<!-- /wp:list-item -->
</ul>
<!-- /wp:list -->
</div><!-- /wp:group -->
<!-- wp:group {"className":"rz-sec"} --><div class="wp-block-group rz-sec">
<!-- wp:heading {"level":2} -->
<h2>Сколько стоит услуга</h2>
<!-- /wp:heading -->

<!-- wp:table -->
<figure class="wp-block-table"><table><thead><tr><th>Работа</th><th>Стоимость</th></tr></thead><tbody><tr><td>Работа</td><td>…</td></tr></tbody></table></figure>
<!-- /wp:table -->
</div><!-- /wp:group -->
<!-- wp:group {"className":"rz-sec"} --><div class="wp-block-group rz-sec">
<!-- wp:heading {"level":2} -->
<h2>Частые вопросы</h2>
<!-- /wp:heading -->

<!-- wp:group {"className":"rz-accordion","metadata":{"name":"Частые вопросы"}} -->
<div class="wp-block-group rz-accordion" data-accordion>
<!-- wp:details {"showContent":true} -->
<details class="wp-block-details" open><summary>Вопрос.</summary>
<!-- wp:paragraph -->
<p>Ответ.</p>
<!-- /wp:paragraph -->
</details>
<!-- /wp:details -->
<!-- wp:details -->
<details class="wp-block-details"><summary>Вопрос.</summary>
<!-- wp:paragraph -->
<p>Ответ.</p>
<!-- /wp:paragraph -->
</details>
<!-- /wp:details -->
<!-- wp:details -->
<details class="wp-block-details"><summary>Вопрос.</summary>
<!-- wp:paragraph -->
<p>Ответ.</p>
<!-- /wp:paragraph -->
</details>
<!-- /wp:details -->
</div>
<!-- /wp:group -->
</div><!-- /wp:group -->
