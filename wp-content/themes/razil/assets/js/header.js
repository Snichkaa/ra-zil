/**
 * Шапка: мобильное меню, умная шапка при прокрутке, тень.
 *
 * Без сборки — файл подключается напрямую в подвале.
 *
 * Прокрутку фона при открытом меню намеренно НЕ запираем.
 * Прежняя версия ставила document.body.style.overflow, и любое
 * значение overflow, отличное от visible, отключает
 * position: sticky у липкой формы на странице отзывов.
 * Панель занимает экран ниже шапки и прокручивается сама.
 */
( function () {
	'use strict';

	var burger = document.querySelector( '[data-rz-burger]' );
	var menu = document.getElementById( 'rz-menu' );
	var header = document.querySelector( '.rz-header' );

	/* ==================================================== ЗВОНКИ ПО tel: */

	/**
	 * Ссылка tel: ведёт себя по-разному в зависимости от того, чем она
	 * оформлена в разметке.
	 *
	 * Кнопка, то есть ссылка внутри .wp-block-button — «Позвоните нам»,
	 * «Вызвать агента», «Получить точную стоимость», «Уточнить наличие»:
	 * набор номера подтверждается диалогом. Кнопка занимает половину
	 * экрана, и случайное касание не должно звонить.
	 *
	 * Номер текстом — в шапке, в мобильном меню, в подвале, в тексте
	 * страниц: нажатие не делает ничего. Атрибут href остаётся в
	 * разметке: по нему номер распознаётся браузером и поисковиками,
	 * и на нём же будет строиться будущее поведение.
	 *
	 * Короткие служебные номера — 112, 103, 102, 03, 02 в блоке «что
	 * делать, если смерть наступила дома» — не перехватываются вовсе.
	 * Их нажимают, чтобы вызвать помощь сейчас; препятствие на этом
	 * пути опаснее случайного звонка. Признак — меньше шести цифр,
	 * поэтому правило не привязано к номеру агентства и не станет
	 * ещё одним местом, где этот номер записан.
	 *
	 * Обработчик один и висит на document. Липкая панель звонка
	 * появляется по прокрутке, а делегирование покрывает её без
	 * повторной привязки.
	 */

	var SHORT_NUMBER_DIGITS = 6;

	var telHref = '';
	var telReturn = null;

	/**
	 * Написание номера для диалога. В кнопке текст другой («Вызвать
	 * агента»), а цифры из href читались бы как «+74212605290». Тот же
	 * номер на каждой странице выведен и текстом — в шапке, в меню или
	 * в подвале; берём написание оттуда, чтобы не форматировать номер
	 * в скрипте и не держать его здесь второй копией.
	 */
	function telText( href ) {
		var twins = document.querySelectorAll( 'a[href="' + href + '"]' );

		for ( var i = 0; i < twins.length; i++ ) {
			var text = twins[ i ].textContent.trim();
			if ( /^[+\d][\d\s()+-]{5,}$/.test( text ) ) {
				return text;
			}
		}

		return href.replace( /^tel:/, '' );
	}

	/**
	 * Диалог собирается один раз при инициализации, а не на каждый клик.
	 * Возвращает null, если <dialog> в браузере нет — тогда роль окна
	 * играет window.confirm.
	 */
	function buildTelDialog() {
		var dialog = document.createElement( 'dialog' );

		if ( 'function' !== typeof dialog.showModal || ! document.body ) {
			return null;
		}

		dialog.className = 'rz-tel-dialog';
		dialog.setAttribute( 'aria-labelledby', 'rz-tel-dialog-title' );

		// Содержимое лежит во вложенном блоке нарочно: задний фон
		// принадлежит самому dialog, и по target клика видно, попал он
		// в окно или мимо.
		var box = document.createElement( 'div' );
		box.className = 'rz-tel-dialog__box';

		var title = document.createElement( 'p' );
		title.className = 'rz-tel-dialog__title';
		title.id = 'rz-tel-dialog-title';
		title.textContent = 'Позвонить в агентство?';

		var number = document.createElement( 'p' );
		number.className = 'rz-tel-dialog__number';

		var row = document.createElement( 'div' );
		row.className = 'rz-tel-dialog__row';

		var ok = document.createElement( 'button' );
		ok.type = 'button';
		ok.className = 'rz-tel-dialog__ok';
		ok.textContent = 'Позвонить';

		var cancel = document.createElement( 'button' );
		cancel.type = 'button';
		cancel.className = 'rz-tel-dialog__cancel';
		cancel.textContent = 'Отмена';

		row.appendChild( ok );
		row.appendChild( cancel );
		box.appendChild( title );
		box.appendChild( number );
		box.appendChild( row );
		dialog.appendChild( box );

		// Переход синхронно, в самом обработчике клика. Вынести его
		// в событие close или в таймер нельзя: браузер считает такую
		// навигацию не вызванной жестом пользователя и блокирует.
		ok.addEventListener( 'click', function () {
			window.location.href = telHref;
			dialog.close();
		} );

		cancel.addEventListener( 'click', function () {
			dialog.close();
		} );

		dialog.addEventListener( 'click', function ( event ) {
			if ( event.target === dialog ) {
				dialog.close();
			}
		} );

		// Esc закрывает окно средствами браузера, поэтому фокус
		// возвращаем на close — он приходит и после Esc, и после
		// любой из двух кнопок.
		dialog.addEventListener( 'close', function () {
			if ( telReturn && document.contains( telReturn ) ) {
				telReturn.focus();
			}
			telReturn = null;
		} );

		document.body.appendChild( dialog );
		return dialog;
	}

	var telDialog = buildTelDialog();

	document.addEventListener( 'click', function ( event ) {
		var target = event.target;

		if ( ! target || 'function' !== typeof target.closest ) {
			return;
		}

		var link = target.closest( 'a[href^="tel:"]' );

		if ( ! link ) {
			return;
		}

		var href = link.getAttribute( 'href' );

		if ( href.replace( /\D/g, '' ).length < SHORT_NUMBER_DIGITS ) {
			return;
		}

		event.preventDefault();

		if ( ! link.closest( '.wp-block-button' ) ) {
			return;
		}

		if ( ! telDialog ) {
			if ( window.confirm( 'Позвонить по номеру ' + telText( href ) + '?' ) ) {
				window.location.href = href;
			}
			return;
		}

		telHref = href;
		telReturn = link;
		telDialog.querySelector( '.rz-tel-dialog__number' ).textContent = telText( href );
		telDialog.showModal();
		telDialog.querySelector( '.rz-tel-dialog__ok' ).focus();
	} );

	/* ==================================================== ШАПКА */

	if ( ! header ) {
		return;
	}

	var DESKTOP = window.matchMedia( '(min-width: 62rem)' );
	var CALM = window.matchMedia( '(prefers-reduced-motion: reduce)' );

	/* ==================================================== МОБИЛЬНОЕ МЕНЮ */

	var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])';

	function isOpen() {
		return !! menu && ! menu.hasAttribute( 'hidden' );
	}

	/**
	 * Порядок обхода: бургер и содержимое панели.
	 * Бургер входит в цикл, потому что он же и закрывает меню —
	 * запирать фокус только внутри панели значило бы отрезать выход.
	 */
	function focusRing() {
		var list = [ burger ];
		Array.prototype.forEach.call( menu.querySelectorAll( FOCUSABLE ), function ( el ) {
			if ( el.offsetParent !== null ) {
				list.push( el );
			}
		} );
		return list;
	}

	function setOpen( open ) {
		if ( ! burger || ! menu ) {
			return;
		}

		if ( open ) {
			menu.removeAttribute( 'hidden' );
		} else {
			menu.setAttribute( 'hidden', '' );
		}

		burger.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		burger.setAttribute( 'aria-label', open ? 'Закрыть меню' : 'Открыть меню' );

		// Пока панель открыта, шапка остаётся на месте.
		if ( open ) {
			header.classList.remove( 'rz-header--hidden' );
		}

		if ( ! open ) {
			burger.focus();
		}
	}

	if ( burger && menu ) {
		burger.addEventListener( 'click', function () {
			setOpen( ! isOpen() );
		} );

		menu.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( 'a[href]' ) ) {
				setOpen( false );
			}
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( ! isOpen() ) {
				return;
			}

			if ( 'Escape' === event.key ) {
				setOpen( false );
				return;
			}

			if ( 'Tab' !== event.key ) {
				return;
			}

			var ring = focusRing();
			if ( ring.length < 2 ) {
				return;
			}

			var first = ring[ 0 ];
			var last = ring[ ring.length - 1 ];
			var active = document.activeElement;

			// Фокус мог оказаться вне цикла — например на адресной строке
			// или на элементе страницы под панелью. Возвращаем его в цикл.
			if ( ring.indexOf( active ) === -1 ) {
				event.preventDefault();
				first.focus();
				return;
			}

			if ( event.shiftKey && active === first ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && active === last ) {
				event.preventDefault();
				first.focus();
			}
		} );

		// Панель существует только на узких экранах: при переходе
		// на десктоп закрываем, иначе она останется открытой в памяти
		// и вернётся при обратном сужении.
		DESKTOP.addEventListener( 'change', function ( event ) {
			if ( event.matches && isOpen() ) {
				setOpen( false );
			}
		} );
	}

	/* ==================================================== ПРОКРУТКА */

	var lastY = window.scrollY;
	var ticking = false;

	var TOP_ZONE = 100; // в первых 100px шапку не скрываем
	var STEP = 8;       // порог, ниже которого считаем дрожанием

	function onFrame() {
		ticking = false;

		var y = window.scrollY;

		// Тень — на всех ширинах.
		header.classList.toggle( 'rz-header--scrolled', y > STEP );

		var smart = ! DESKTOP.matches && ! CALM.matches;

		if ( ! smart || isOpen() || y <= TOP_ZONE ) {
			header.classList.remove( 'rz-header--hidden' );
			lastY = y;
			return;
		}

		var delta = y - lastY;

		if ( delta > STEP ) {
			header.classList.add( 'rz-header--hidden' );
			lastY = y;
		} else if ( delta < 0 ) {
			header.classList.remove( 'rz-header--hidden' );
			lastY = y;
		}
	}

	function onScroll() {
		if ( ! ticking ) {
			ticking = true;
			window.requestAnimationFrame( onFrame );
		}
	}

	window.addEventListener( 'scroll', onScroll, { passive: true } );

	DESKTOP.addEventListener( 'change', function () {
		header.classList.remove( 'rz-header--hidden' );
		lastY = window.scrollY;
	} );

	/* ==================================================== ЛИПКАЯ ПОЛОСА */

	/**
	 * Полоса появляется, когда первый экран целиком ушёл из вида,
	 * и прячется, когда в кадре призыв .rz-cta: внизу страницы он
	 * дублирует её по смыслу — кнопка звонка и тут же телефон.
	 *
	 * Наблюдатель, а не обработчик scroll: привязка к элементу, а не
	 * к числу пикселей, и браузер сам считает пересечения вне
	 * основного потока.
	 *
	 * Ширину здесь не проверяем: класс вешается на любой, а показывает
	 * полосу только CSS внутри медиазапроса. Состояние по умолчанию —
	 * скрытое, поэтому без JS полоса не появляется вовсе.
	 */
	var stickyCall = document.querySelector( '.rz-sticky-call' );
	var cta = document.querySelector( '.rz-cta' );

	// На внутренних страницах героя нет. Первая секция контента там
	// тянется почти на всю страницу и из кадра не уходит, поэтому
	// ориентиром служит заголовок первого уровня.
	var firstScreen = document.querySelector( '.rz-hero' ) ||
		document.querySelector( 'h1' );

	if ( stickyCall && 'IntersectionObserver' in window ) {
		var firstScreenGone = false;
		var ctaInView = false;

		var syncStickyCall = function () {
			stickyCall.classList.toggle( 'is-visible', firstScreenGone && ! ctaInView );
		};

		if ( firstScreen ) {
			new IntersectionObserver( function ( entries ) {
				firstScreenGone = ! entries[ 0 ].isIntersecting;
				syncStickyCall();
			} ).observe( firstScreen );
		}

		if ( cta ) {
			new IntersectionObserver( function ( entries ) {
				ctaInView = entries[ 0 ].isIntersecting;
				syncStickyCall();
			} ).observe( cta );
		}
	}

	// Состояние тени при загрузке страницы с уже прокрученной позицией
	// (возврат назад, переход по якорю).
	onFrame();
}() );
