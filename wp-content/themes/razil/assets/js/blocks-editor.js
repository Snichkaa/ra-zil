/**
 * Регистрация блоков темы в редакторе.
 *
 * Блоки зарегистрированы в PHP и рисуются через render.php, но в панели
 * вставки они попадают только после регистрации на стороне JavaScript.
 * Метаданные приходят из PHP в window.razilBlocks – придумывать их здесь
 * не нужно, они берутся из реального реестра WordPress.
 *
 * Файл написан без JSX, поэтому сборка ему не нужна.
 *
 * @package Razil
 */

( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.element ) {
		return;
	}

	var list = window.razilBlocks || [];
	var el = wp.element.createElement;
	var ServerSideRender = wp.serverSideRender;
	var useBlockProps = wp.blockEditor && wp.blockEditor.useBlockProps;

	list.forEach( function ( meta ) {
		// Не регистрируем повторно, если у блока посвятил собственный скрипт.
		if ( wp.blocks.getBlockType( meta.name ) ) {
			return;
		}

		wp.blocks.registerBlockType( meta.name, {
			apiVersion: 3,
			title: meta.title,
			description: meta.description,
			category: meta.category,
			icon: meta.icon,
			attributes: meta.attributes,

			edit: function ( props ) {
				var blockProps = useBlockProps ? useBlockProps() : {};

				if ( ! ServerSideRender ) {
					return el(
						'div',
						blockProps,
						el( 'p', {}, meta.title + ' – предпросмотр недоступен' )
					);
				}

				return el(
					'div',
					blockProps,
					el( ServerSideRender, {
						block: meta.name,
						attributes: props.attributes
					} )
				);
			},

			// Блок динамический: разметку отдаёт render.php.
			save: function () {
				return null;
			}
		} );
	} );
} )( window.wp );
