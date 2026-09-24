<?php
/**
 * Title: Услуга: первый экран
 * Slug: razil/service-hero
 * Description: Первый экран страницы услуги: заголовок, короткое описание, кнопка звонка и кнопка заявки, три факта и снимок с подписью.
 * Categories: razil-service
 * Keywords: услуга, первый экран, телефон, заявка
 * Post Types: services
 * Inserter: true
 *
 * Вставляется ПЕРВЫМ блоком содержимого.
 *
 * ТЕЛЕФОН. Номер стоит здесь резервным значением — тем же, что
 * в parts/header.html, — и подменяется на лету буфером вывода
 * (razil-core/inc/org-settings.php, razil_org_buffer на template_redirect).
 * Вызывать razil_org() прямо тут НЕЛЬЗЯ: паттерн вставляется в запись
 * один раз, результат вызова застынет в базе, и карта замен, которая
 * ищет резервное написание, его больше не найдёт — номер протухнет
 * при первой же правке настройки.
 *
 * КНОПКА ЗВОНКА — core/button, а не своя ссылка. Это не про оформление:
 * header.js показывает окно подтверждения звонка только у ссылки tel:,
 * лежащей внутри .wp-block-button (строка 216). На настольном компьютере
 * нажатие на tel: иначе не делает ничего, и кнопка читалась бы как
 * сломанная. Обёртку даёт блок ядра, подделывать её классом не нужно.
 *
 * КНОПКА ЗАЯВКИ — шорткод [razil_callback_button]. Разбор, почему
 * не «то же, что в шапке», — в отчёте прогона, задача 4.
 *
 * ФАКТЫ. Здесь стоит нейтральная рыба. На живых страницах факт ставится
 * только по правилу Т4 задания: его содержание обязано быть в тексте
 * ЭТОЙ ЖЕ страницы. Второго абзаца у факта может не быть — вёрстка
 * это переживает.
 *
 * СНИМОК. core/post-featured-image: изображение записи, а не картинка
 * в содержимом. Пропорция НЕ задаётся атрибутом блока: ядро вписало бы
 * её инлайновым стилем в figure, и на телефоне, где снимку нужна
 * фиксированная высота, инлайн было бы не перебить ничем, кроме
 * !important. Пропорция живёт в main.css и меняется по ширине окна.
 * Если у записи изображения нет, блок не выводит ничего,
 * и на его место ставится заглушка .rz-photo-slot той же геометрии —
 * так сделано на благоустройстве (задача 12).
 *
 * @package Razil
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"align":"full","className":"rz-svc-sec rz-svc-hero","style":{"color":{"background":"var(\u002d\u002drz-bg)"}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignfull rz-svc-sec rz-svc-hero has-background" style="background-color:var(--rz-bg)"><!-- wp:group {"className":"rz-svc-sec__inner rz-svc-hero__grid","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-sec__inner rz-svc-hero__grid"><!-- wp:group {"className":"rz-svc-hero__text","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-hero__text"><!-- wp:post-title {"level":1,"className":"rz-svc-hero__title"} /-->

<!-- wp:post-excerpt {"className":"rz-svc-hero__lead"} /-->

<!-- wp:group {"className":"rz-svc-hero__actions","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-hero__actions"><!-- wp:buttons {"className":"rz-svc-hero__call-wrap"} -->
<div class="wp-block-buttons rz-svc-hero__call-wrap"><!-- wp:button {"className":"rz-svc-hero__call"} -->
<div class="wp-block-button rz-svc-hero__call"><a class="wp-block-button__link wp-element-button" href="tel:+74212605290"><span class="rz-svc-hero__call-text"><span class="rz-svc-hero__call-prefix">Позвонить: </span>+7 (4212) 60-52-90</span></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->

<!-- wp:shortcode -->
[razil_callback_button label="Вызвать специалиста" class="rz-svc-hero__cta"]
<!-- /wp:shortcode --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"rz-svc-hero__facts","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-hero__facts"><!-- wp:group {"className":"rz-svc-hero__fact","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-hero__fact"><!-- wp:paragraph -->
<p>Первый факт</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>короткое пояснение</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"rz-svc-hero__fact","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-hero__fact"><!-- wp:paragraph -->
<p>Второй факт</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>короткое пояснение</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"rz-svc-hero__fact","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-hero__fact"><!-- wp:paragraph -->
<p>Третий факт</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>короткое пояснение</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"rz-svc-hero__media","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-hero__media"><!-- wp:post-featured-image {"className":"rz-svc-hero__photo"} /-->

<!-- wp:paragraph {"className":"rz-svc-hero__caption"} -->
<p class="rz-svc-hero__caption">Подпись к снимку</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
