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

	/*
	 * Отметка «скрипт работает» — первым делом, до любой другой работы.
	 *
	 * По ней CSS показывает то, что без JavaScript бессмысленно: кнопку
	 * обратного звонка, которая умеет только открывать модальное окно.
	 * Кнопка, не реагирующая на нажатие, читается как поломка сайта,
	 * поэтому по умолчанию она скрыта, а показывается этим классом.
	 *
	 * Порядок именно такой, а не обратный: показать и потом спрятать
	 * значило бы мигание при загрузке.
	 */
	document.documentElement.classList.add( 'rz-js' );

	var burger = document.querySelector( '[data-rz-burger]' );
	var menu = document.getElementById( 'rz-menu' );
	var header = document.querySelector( '.rz-header' );

	// Общая на весь файл: её спрашивают и меню, и аккордеон вопросов.
	// Объявлена здесь, а не в разделе шапки, потому что до того раздела
	// стоит ранний return по отсутствию шапки.
	var CALM = window.matchMedia( '(prefers-reduced-motion: reduce)' );

	/*
	 * Есть ли настоящий указатель — мышь или трекпад.
	 *
	 * Спрашиваем два условия сразу, и оба нужны. hover: hover означает,
	 * что указатель умеет наводиться, не нажимая; pointer: fine — что он
	 * точный. Палец не умеет ни того, ни другого, стилус точен, но не
	 * наводится. Вместе они отделяют настольный компьютер и ноутбук
	 * от телефона и планшета надёжнее, чем каждое поодиночке.
	 *
	 * Ноутбук с сенсорным экраном сообщает hover: hover и pointer: fine,
	 * потому что основной указатель у него всё-таки мышь, — и получит
	 * диалог. Это верно: tel: там ведёт себя по-настольному.
	 */
	var POINTER = window.matchMedia( '(hover: hover) and (pointer: fine)' );

	/* ==================================================== ЗВОНКИ ПО tel: */

	/**
	 * Ссылка tel: ведёт себя по-разному в зависимости от того, чем она
	 * оформлена в разметке.
	 *
	 * Все ссылки tel: работают как обычные ссылки: нажал — идёт вызов.
	 * Единственное исключение — кнопки на устройстве с мышью.
	 *
	 * Кнопка, то есть ссылка внутри .wp-block-button — «Позвоните нам»,
	 * «Вызвать агента», «Получить точную стоимость», «Уточнить наличие»:
	 * на настольном компьютере набор подтверждается диалогом. Там tel:
	 * без подтверждения открывает Skype или не делает ничего, и человек
	 * не понимает, что произошло.
	 *
	 * На телефоне и планшете диалога нет. Система сама показывает номер
	 * и спрашивает перед набором, то есть подтверждение уже встроено
	 * в платформу, и наше окно оказывалось вторым подряд.
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

		/*
		 * Сенсорное устройство — отдаём ссылку системе. Порядок проверок
		 * важен: preventDefault ниже, а не здесь, иначе отменялся бы
		 * и тот вызов, который мы собираемся пропустить.
		 */
		if ( ! POINTER.matches ) {
			return;
		}

		// Номер текстом на настольном компьютере тоже звонит сам.
		if ( ! link.closest( '.wp-block-button' ) ) {
			return;
		}

		event.preventDefault();

		if ( ! telDialog ) {
			if ( window.confirm( 'Позвонить по номеру ' + telText( href ) + '?' ) ) {
				window.location.href = href;
			}
			return;
		}

		// Два модальных окна одновременно не держим: они перехватывали бы
		// фокус друг у друга, и Esc закрывал бы не то, что человек видит.
		closeCallbackDialog();

		telHref = href;
		telReturn = link;
		telDialog.querySelector( '.rz-tel-dialog__number' ).textContent = telText( href );
		telDialog.showModal();
		telDialog.querySelector( '.rz-tel-dialog__ok' ).focus();
	} );

	/* ==================================================== ОБРАТНЫЙ ЗВОНОК */

	/**
	 * Окно с формой заявки. В отличие от окна подтверждения звонка,
	 * оно приходит с сервера готовым: внутри настоящая форма, которая
	 * без JavaScript отправляется обычным POST. Скрипт только открывает
	 * и закрывает окно.
	 *
	 * Разметку окно печатает шорткод [razil_callback_button] вместе
	 * с кнопкой, поэтому здесь его может и не быть — на страницах,
	 * где кнопки нет.
	 */
	var cbDialog = document.getElementById( 'rz-cb-dialog' );
	var cbReturn = null;

	function callbackUsable() {
		return cbDialog && 'function' === typeof cbDialog.showModal;
	}

	function closeCallbackDialog() {
		if ( callbackUsable() && cbDialog.open ) {
			cbDialog.close();
		}
	}

	/**
	 * Открыть окно и увести в него фокус.
	 *
	 * @param {Element|null} opener Кнопка, которую нажали: на неё
	 *                              возвращается фокус после закрытия.
	 */
	function openCallbackDialog( opener ) {
		if ( ! callbackUsable() ) {
			return false;
		}

		if ( telDialog && telDialog.open ) {
			telDialog.close();
		}

		cbReturn = opener || null;
		cbDialog.showModal();

		// Сообщение об ошибке важнее поля: если окно открылось само после
		// неудачной отправки, человеку сначала нужно прочитать, что не так.
		var notice = cbDialog.querySelector( '[data-rz-notice]' );

		if ( notice ) {
			notice.focus();
			return true;
		}

		// Ловушку пропускаем: она текстовая, но с tabindex="-1".
		var first = cbDialog.querySelector( 'input:not([type="hidden"]):not([tabindex="-1"])' );

		if ( first ) {
			first.focus();
		}

		return true;
	}

	if ( cbDialog ) {
		// Клик по фону: у самого dialog фон и есть, поэтому попадание
		// мимо окна видно по target.
		cbDialog.addEventListener( 'click', function ( event ) {
			if ( event.target === cbDialog ) {
				cbDialog.close();
			}
		} );

		// Esc закрывает окно силами браузера, здесь только возврат фокуса.
		cbDialog.addEventListener( 'close', function () {
			if ( cbReturn && document.contains( cbReturn ) ) {
				cbReturn.focus();
			}

			cbReturn = null;
		} );

		document.addEventListener( 'click', function ( event ) {
			var target = event.target;

			if ( ! target || 'function' !== typeof target.closest ) {
				return;
			}

			if ( target.closest( '[data-rz-cb-close]' ) ) {
				closeCallbackDialog();
				return;
			}

			var opener = target.closest( '[data-rz-cb-open]' );

			if ( opener ) {
				openCallbackDialog( opener );
			}
		} );

		// Заявку отправляли из окна, вернулись с ошибкой на ту же страницу:
		// открываем окно сразу, иначе человек не поймёт, что заявка не ушла.
		if ( cbDialog.hasAttribute( 'data-rz-cb-autoopen' ) ) {
			openCallbackDialog( null );
		}
	}

	/* ==================================================== ВОПРОСЫ И ОТВЕТЫ */

	/**
	 * Плавное раскрытие блоков «вопрос — ответ».
	 *
	 * Почему это не делается одним CSS. Высота содержимого — auto, а auto
	 * анимировать не из чего: это ключевое слово, а не число, и браузеру
	 * нечего интерполировать между кадрами. Поэтому <details> раскрывается
	 * рывком, сколько переходов на него ни вешай.
	 *
	 * Обходных путей три, и выбран третий:
	 *
	 *   1. Обёртка вокруг содержимого и grid-template-rows от 0fr к 1fr.
	 *      Работает и без скрипта, но core/details выводит абзацы прямо
	 *      внутри details, без обёртки. Пришлось бы либо править разметку
	 *      всех страниц в базе, либо вставлять обёртку скриптом — то есть
	 *      всё равно скрипт, только ещё и с вмешательством в DOM.
	 *
	 *   2. Псевдоэлемент ::details-content вместе с interpolate-size.
	 *      Сделан ровно для этого случая и не требует ни обёртки, ни
	 *      скрипта, но поддержан пока не везде: где не поддержан, тихо
	 *      не сработает. Для одинакового поведения в браузерах не годится.
	 *
	 *   3. Скрипт: измерить обе высоты и проанимировать переход между
	 *      числами. Работает везде, где есть element.animate, и ничего
	 *      не меняет в разметке.
	 *
	 * Без скрипта аккордеон остаётся родным <details> и работает как
	 * обычно, просто мгновенно. Это и есть требуемое поведение,
	 * а не поломка.
	 */
	var faq = document.querySelectorAll( '.entry-content .wp-block-details' );

	if ( faq.length ) {
		/*
		 * Длительность берём из той же переменной, что и все переходы темы,
		 * чтобы не заводить второй источник правды. В токене записано
		 * «160ms», parseFloat отрежет единицы.
		 */
		var faqMs = parseFloat(
			getComputedStyle( document.documentElement ).getPropertyValue( '--rz-transition' )
		) || 160;

		Array.prototype.forEach.call( faq, function ( item ) {
			var summary = item.querySelector( 'summary' );

			if ( ! summary ) {
				return;
			}

			var running = null;

			summary.addEventListener( 'click', function ( event ) {
				// Меньше движения — отдаём родное мгновенное поведение.
				if ( CALM.matches || typeof item.animate !== 'function' ) {
					return;
				}

				event.preventDefault();

				if ( running ) {
					running.cancel();
					running = null;
					item.classList.remove( 'is-closing' );
				}

				var wasOpen = item.open;
				var from = item.offsetHeight;
				var to;

				if ( wasOpen ) {
					/*
					 * Закрываем. Чтобы узнать высоту в закрытом виде, на миг
					 * снимаем open и меряем. Промежуточного кадра при этом
					 * не будет: браузер рисует между задачами, а мы успеваем
					 * вернуть open в той же задаче.
					 */
					item.open = false;
					to = item.offsetHeight;
					item.open = true;

					// Галочка не должна ждать конца схлопывания:
					// класс поворачивает её сразу, см. main.css.
					item.classList.add( 'is-closing' );
				} else {
					item.open = true;
					to = item.offsetHeight;
				}

				// Пока высота меньше содержимого, лишнее нужно обрезать.
				item.style.overflow = 'hidden';

				running = item.animate(
					[ { height: from + 'px' }, { height: to + 'px' } ],
					{ duration: faqMs, easing: 'ease' }
				);

				running.onfinish = function () {
					running = null;
					item.style.overflow = '';
					item.classList.remove( 'is-closing' );

					if ( wasOpen ) {
						item.open = false;
					}
				};
			} );
		} );
	}

	/* ==================================================== ШАПКА */

	if ( ! header ) {
		return;
	}

	var DESKTOP = window.matchMedia( '(min-width: 62rem)' );

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
	 * и дальше остаётся до конца страницы.
	 *
	 * Появление одностороннее намеренно. Раньше полоса ещё и пряталась,
	 * когда в кадр попадал призыв .rz-cta внизу страницы. У границы призыва
	 * это давало мерцание: малейшее движение прокрутки вверх-вниз
	 * переключало видимость туда и обратно. Вдобавок на страницах услуг
	 * призыв скрыт, у скрытого элемента нулевые размеры, и наблюдатель вёл
	 * себя там иначе, чем на остальных страницах. Одностороннее появление
	 * снимает и мерцание, и разницу между страницами: наблюдатель за
	 * призывом убран совсем.
	 *
	 * Наблюдатель, а не обработчик scroll: привязка к элементу, а не
	 * к числу пикселей, и браузер сам считает пересечения вне
	 * основного потока.
	 *
	 * Ширину здесь не проверяем: класс вешается на любой, а показывает
	 * полосу только CSS внутри медиазапроса. Состояние по умолчанию —
	 * скрытое, поэтому без JS полоса не появляется вовсе и не мелькает
	 * при загрузке.
	 */
	var stickyCall = document.querySelector( '.rz-sticky-call' );

	// На внутренних страницах героя нет. Первая секция контента там
	// тянется почти на всю страницу и из кадра не уходит, поэтому
	// ориентиром служит заголовок первого уровня.
	var firstScreen = document.querySelector( '.rz-hero' ) ||
		document.querySelector( 'h1' );

	if ( stickyCall && firstScreen && 'IntersectionObserver' in window ) {
		var stickyWatch = new IntersectionObserver( function ( entries ) {
			if ( entries[ 0 ].isIntersecting ) {
				return;
			}

			stickyCall.classList.add( 'is-visible' );

			// Показали — больше следить не за чем.
			stickyWatch.disconnect();
		} );

		stickyWatch.observe( firstScreen );
	}

	// Состояние тени при загрузке страницы с уже прокрученной позицией
	// (возврат назад, переход по якорю).
	onFrame();
}() );
