<?php
/**
 * Title: Услуга: частые вопросы
 * Slug: razil/service-faq
 * Description: Пять вопросов с ответами; первый открыт.
 * Categories: razil-service
 * Keywords: услуга, вопросы, FAQ
 * Post Types: services
 * Inserter: true
 *
 * ГРУППА БЕЗ data-accordion. Правило «открыт только один вопрос»
 * раньше держалось на этом HTML-атрибуте, и из-за него группа была
 * невалидна в редакторе: core/group произвольных атрибутов
 * не сохраняет, и владелец сайта видел «Блок содержит неожиданное
 * содержимое» (проверка П-5 нашла это на всех пяти страницах).
 * Теперь правило ищет класс rz-svc-faq__list — см. разбор по И17
 * в отчёте, задача 10.
 *
 * РАЗМЕТКА ВОПРОСОВ — core/details. Это не оформление, а условие
 * работы микроразметки: inc/schema.php собирает пары «вопрос —
 * ответ» именно из блоков core/details, беря вопрос из <summary>.
 * Другой блок с тем же видом оставил бы страницу без FAQPage.
 *
 * @package Razil
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"align":"full","className":"rz-svc-sec rz-svc-faq","style":{"color":{"background":"var(\u002d\u002drz-bg)"}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignfull rz-svc-sec rz-svc-faq has-background" style="background-color:var(--rz-bg)"><!-- wp:group {"className":"rz-svc-sec__inner","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-sec__inner"><!-- wp:heading -->
<h2 class="wp-block-heading">Частые вопросы</h2>
<!-- /wp:heading -->

<!-- wp:group {"className":"rz-svc-faq__list","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-faq__list"><!-- wp:details {"showContent":true} -->
<details class="wp-block-details" open><summary>Первый вопрос?</summary><!-- wp:paragraph -->
<p>Ответ на вопрос.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details -->
<details class="wp-block-details"><summary>Второй вопрос?</summary><!-- wp:paragraph -->
<p>Ответ на вопрос.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details -->
<details class="wp-block-details"><summary>Третий вопрос?</summary><!-- wp:paragraph -->
<p>Ответ на вопрос.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details -->
<details class="wp-block-details"><summary>Четвёртый вопрос?</summary><!-- wp:paragraph -->
<p>Ответ на вопрос.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details -->

<!-- wp:details -->
<details class="wp-block-details"><summary>Пятый вопрос?</summary><!-- wp:paragraph -->
<p>Ответ на вопрос.</p>
<!-- /wp:paragraph --></details>
<!-- /wp:details --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
