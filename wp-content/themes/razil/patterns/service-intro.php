<?php
/**
 * Title: Услуга: вступление
 * Slug: razil/service-intro
 * Description: Вступление к услуге: два-три абзаца слева и карточка первых действий с кнопкой звонка справа.
 * Categories: razil-service
 * Keywords: услуга, вступление, первые действия
 * Post Types: services
 * Inserter: true
 *
 * Идёт вторым блоком, сразу за первым экраном.
 *
 * КАРТОЧКУ МОЖНО УДАЛИТЬ. На четырёх услугах из пяти готового текста
 * для неё нет, и выдумывать его нельзя (И18). Если карточку убрать,
 * текст остаётся в одной колонке и НЕ растягивается во всю ширину:
 * сетка ловит отсутствие карточки через :has() и сама переходит
 * в одну колонку с мерой строки. Пустоты справа не возникает —
 * возникает поле, а это разные вещи.
 *
 * ПОРЯДОК ПУНКТОВ В РЫБЕ не случаен: первым стоит звонок в агентство,
 * как на главной в разделе «Что делать, если уход наступил дома».
 * Два разных порядка первых действий на одном сайте — это хуже,
 * чем отсутствие одного из них.
 *
 * НОМЕРА ПУНКТОВ рисуются счётчиками CSS, а не лежат в тексте.
 * Пункт можно переставить, вставить или удалить — нумерация
 * пересчитается сама, и в тексте не останется осиротевшей цифры.
 *
 * @package Razil
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"align":"full","className":"rz-svc-sec rz-svc-intro","style":{"color":{"background":"var(\u002d\u002drz-surface)"}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignfull rz-svc-sec rz-svc-intro has-background" style="background-color:var(--rz-surface)"><!-- wp:group {"className":"rz-svc-sec__inner rz-svc-intro__grid","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-sec__inner rz-svc-intro__grid"><!-- wp:group {"className":"rz-svc-intro__text","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-intro__text"><!-- wp:paragraph -->
<p>Вводный абзац услуги.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Второй вводный абзац услуги.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"rz-svc-card rz-svc-card\u002d\u002dsand rz-svc-intro__card","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-card rz-svc-card--sand rz-svc-intro__card"><!-- wp:heading {"className":"rz-svc-card__title"} -->
<h2 class="wp-block-heading rz-svc-card__title">Если уход наступил дома</h2>
<!-- /wp:heading -->

<!-- wp:list {"ordered":true,"className":"rz-svc-steps"} -->
<ol class="wp-block-list rz-svc-steps"><!-- wp:list-item -->
<li>Позвоните в агентство.</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Дождитесь специалиста.</li>
<!-- /wp:list-item --></ol>
<!-- /wp:list -->

<!-- wp:buttons {"className":"rz-svc-card__call-wrap"} -->
<div class="wp-block-buttons rz-svc-card__call-wrap"><!-- wp:button {"className":"rz-svc-card__call"} -->
<div class="wp-block-button rz-svc-card__call"><a class="wp-block-button__link wp-element-button" href="tel:+74212605290"><span class="rz-svc-hero__call-text">Позвонить круглосуточно</span></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
