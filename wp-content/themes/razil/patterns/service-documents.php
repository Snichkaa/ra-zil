<?php
/**
 * Title: Услуга: документы
 * Slug: razil/service-documents
 * Description: Какие документы нужны: пояснение слева, карточка со списками справа.
 * Categories: razil-service
 * Keywords: услуга, документы, справки
 * Post Types: services
 * Inserter: true
 *
 * ГРУПП В КАРТОЧКЕ ОДНА ИЛИ ДВЕ. На благоустройстве список один —
 * вторую группу просто удаляют, и карточка остаётся законченной:
 * расстояние между группами задаёт gap, а не поля, и с одной группой
 * его нет вовсе.
 *
 * ПОДПИСЬ ГРУППЫ — абзац со стилем «Метка», а не заголовок. «Основные
 * документы» не раздел страницы, а ярлык списка; H4 между H2
 * и содержимым создал бы уровень, которого в структуре нет,
 * и обход по заголовкам стал бы длиннее без пользы.
 *
 * ЖИРНОЕ НАЧАЛО пункта в списке «Дополнительные» ставится тегом
 * <strong> в разметке, а не стилями: выделено не «первое слово»,
 * а условие, при котором документ нужен, и граница его — смысловая.
 * Правилом CSS такую границу не провести.
 *
 * @package Razil
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:group {"align":"full","className":"rz-svc-sec rz-svc-docs","style":{"color":{"background":"var(\u002d\u002drz-bg)"}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignfull rz-svc-sec rz-svc-docs has-background" style="background-color:var(--rz-bg)"><!-- wp:group {"className":"rz-svc-sec__inner rz-svc-docs__grid","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-sec__inner rz-svc-docs__grid"><!-- wp:group {"className":"rz-svc-docs__text","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-docs__text"><!-- wp:heading -->
<h2 class="wp-block-heading">Какие документы нужны</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Пояснение к списку документов.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"rz-svc-card rz-svc-docs__card","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-card rz-svc-docs__card"><!-- wp:group {"className":"rz-svc-docs__group","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-docs__group"><!-- wp:paragraph {"className":"is-style-label"} -->
<p class="is-style-label">Основные документы</p>
<!-- /wp:paragraph -->

<!-- wp:list {"className":"is-style-checks"} -->
<ul class="wp-block-list is-style-checks"><!-- wp:list-item -->
<li>Документ из списка</li>
<!-- /wp:list-item -->

<!-- wp:list-item -->
<li>Ещё документ из списка</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:group -->

<!-- wp:group {"className":"rz-svc-docs__group","layout":{"type":"default"}} -->
<div class="wp-block-group rz-svc-docs__group"><!-- wp:paragraph {"className":"is-style-label"} -->
<p class="is-style-label">В отдельных случаях</p>
<!-- /wp:paragraph -->

<!-- wp:list {"className":"is-style-extra"} -->
<ul class="wp-block-list is-style-extra"><!-- wp:list-item -->
<li><strong>Условие</strong> — какой документ понадобится</li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
