<?php
/**
 * Title: Услуга: сроки
 * Slug: razil/service-timeline
 * Description: Шкала сроков из трёх-четырёх точек и исходный абзац под ней.
 * Categories: razil-service
 * Keywords: услуга, сроки, шкала, сколько ждать
 * Post Types: services
 * Inserter: true
 *
 * ШКАЛА — НАСТОЯЩИЙ СПИСОК. ul/li, а не набор групп: для экранного
 * диктора это перечень из четырёх пунктов, и он объявляет их число
 * заранее. Набор вложенных div прочитался бы сплошным текстом,
 * в котором «1 рабочий день» и «документы» слиплись бы с соседями.
 * Круги и линии рисуются псевдоэлементами, в разметке их нет.
 *
 * ШКАЛА НЕ ЗАМЕНЯЕТ АБЗАЦ. По правилу Т6 задания исходный текст
 * раздела «Сроки» сохраняется целиком, дословно, примечанием под
 * шкалой. Шкала — выжимка поверх абзаца, а не вместо него: в абзаце
 * есть оговорки («точное время зависит от загруженности», «если
 * выпадает выходной»), которых в четырёх словах не скажешь.
 *
 * ТОЧКИ СТАВЯТСЯ ПО ПРАВИЛУ Т4: значение точки обязано быть в тексте
 * ЭТОЙ ЖЕ страницы. Нет источника — точки нет, и трёх точек вместо
 * четырёх достаточно.
 *
 * ОТКЛОНЕНИЕ — стиль блока «Отклонение» на пункте списка
 * (класс is-style-delay). Круг у такой точки тёплый, а не зелёный:
 * это не шаг, а оговорка — «плюс один-три дня, если выходной».
 *
 * @package Razil
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"align":"full","className":"rz-svc-sec rz-svc-timeline","style":{"color":{"background":"var(\u002d\u002drz-surface)"}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignfull rz-svc-sec rz-svc-timeline has-background" style="background-color:var(--rz-surface)"><!-- wp:group {"className":"rz-svc-sec__inner","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-sec__inner"><!-- wp:heading -->
<h2 class="wp-block-heading">Сроки</h2>
<!-- /wp:heading -->

<!-- wp:list {"className":"rz-svc-timeline__list"} -->
<ul class="wp-block-list rz-svc-timeline__list"><!-- wp:list-item -->
<li><span class="rz-svc-timeline__term">Значение</span><span class="rz-svc-timeline__exp">Пояснение к точке</span></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><span class="rz-svc-timeline__term">Значение</span><span class="rz-svc-timeline__exp">Пояснение к точке</span></li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li><span class="rz-svc-timeline__term">Значение</span><span class="rz-svc-timeline__exp">Пояснение к точке</span></li>
<!-- /wp:list-item -->

<!-- wp:list-item {"className":"is-style-delay"} -->
<li class="is-style-delay"><span class="rz-svc-timeline__term">Значение</span><span class="rz-svc-timeline__exp">Пояснение к точке</span></li>
<!-- /wp:list-item --></ul>
<!-- /wp:list -->

<!-- wp:paragraph {"className":"rz-svc-timeline__note"} -->
<p class="rz-svc-timeline__note">Исходный абзац о сроках целиком.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
