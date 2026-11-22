<?php
/**
 * Title: Услуга: три фото
 * Slug: razil/service-gallery
 * Description: Ряд из трёх снимков одной высоты с подписями. Пока снимков нет — заглушки с заданием фотографу.
 * Categories: razil-service
 * Keywords: услуга, фото, галерея
 * Post Types: services
 * Inserter: true
 *
 * Ставится ВНУТРЬ секции «Что входит», последним блоком её контейнера,
 * после заметки. Своей секции у ряда нет намеренно: три снимка —
 * это иллюстрация к составу услуги, а не отдельный раздел, и полоса
 * фона вокруг них читалась бы как новая тема.
 *
 * ЯЧЕЙКА ОДНОЙ ВЫСОТЫ. И снимок, и заглушка получают ровно 260 px
 * (220 на телефоне), поэтому подстановка настоящего кадра вместо
 * заглушки не двигает ряд по вертикали. Ниже подписи высота уже
 * своя: у заглушки подписи нет — задание фотографу написано внутри
 * рамки, — а у снимка есть. Это единственный сдвиг, который останется
 * при замене, и он в один ряд текста.
 *
 * ALT ОБЯЗАТЕЛЕН у каждого снимка (П18). У заглушки роль alt играет
 * видимый текст внутри рамки.
 *
 * @package Razil
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"className":"rz-svc-gallery","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-gallery"><!-- wp:html -->
<div class="rz-photo-slot rz-svc-gallery__slot" data-slot="1">
<span class="rz-photo-slot__num">Фото 1</span>
<span class="rz-photo-slot__hint">Что снимать</span>
</div>
<!-- /wp:html -->

<!-- wp:html -->
<div class="rz-photo-slot rz-svc-gallery__slot" data-slot="2">
<span class="rz-photo-slot__num">Фото 2</span>
<span class="rz-photo-slot__hint">Что снимать</span>
</div>
<!-- /wp:html -->

<!-- wp:html -->
<div class="rz-photo-slot rz-svc-gallery__slot" data-slot="3">
<span class="rz-photo-slot__num">Фото 3</span>
<span class="rz-photo-slot__hint">Что снимать</span>
</div>
<!-- /wp:html --></div>
<!-- /wp:group -->
