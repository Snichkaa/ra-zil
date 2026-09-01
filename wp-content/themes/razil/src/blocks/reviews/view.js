/**
 * Карусель отзывов — надстройка над CSS scroll-snap.
 *
 * Без этого файла карусель остаётся рабочей: трек прокручивается
 * пальцем, колесом и с клавиатуры. JS добавляет стрелки, счётчик,
 * автопрокрутку и бесконечную ленту.
 *
 * Модель данных:
 *   items — оригиналы, снимаются ОДИН раз при инициализации и никогда
 *           не пересобираются. По ним считаются счётчик, остаток в goTo
 *           и условие автопрокрутки.
 *   cells — актуальные дети трека, пересобираются при каждой сборке
 *           или разборке клонов. По ним считаются позиции прокрутки.
 *   current — индекс ОРИГИНАЛА, 0..items.length-1.
 * Перевод между списками: data-rz-index на каждой ячейке (обратно)
 * и cellIndexOf() (прямо).
 *
 * Видимостью навигации управляет класс на .rz-reviews__nav, а не атрибут
 * hidden: [hidden]{display:none} имеет нулевую специфичность и проигрывает
 * авторскому .rz-reviews__arrow{display:flex}.
 */

const AUTOPLAY_MS = 7000;

/**
 * Клоны нужны только там, где видны соседние карточки. Ниже 48rem карточка
 * во всю ширину, подглядывания нет, и перепрыгивание дало бы лишь риск
 * подёргивания на тач-устройствах.
 */
const CLONES_FROM = '(min-width: 48rem)';

/** Пользователь просил меньше движения — уважаем на всех уровнях. */
const reducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' );

function initCarousel( root ) {
	const track = root.querySelector( '[data-rz-track]' );
	const prev = root.querySelector( '[data-rz-prev]' );
	const next = root.querySelector( '[data-rz-next]' );
	const counter = root.querySelector( '[data-rz-counter]' );
	const nav = root.querySelector( '[data-rz-nav]' );

	if ( ! track ) {
		return;
	}

	/** Оригиналы. Снято до какого-либо клонирования, дальше неизменно. */
	const items = Array.from( track.children );
	if ( items.length === 0 ) {
		return;
	}

	const wide = window.matchMedia( CLONES_FROM );

	let cells = items.slice();
	let cloned = false;
	let current = 0;
	let timer = null;
	let paused = false;

	/** Человек взялся листать сам — автопрокрутку больше не запускаем до перезагрузки. */
	let userTookOver = false;

	/**
	 * Прокрутку начал код, а не человек. Обработчик scroll срабатывает и от
	 * программной прокрутки, и без этого признака автопрокрутка остановила бы
	 * сама себя после первого же слайда, а перепрыгивание с клона — сразу же.
	 */
	let isProgrammatic = false;

	const smooth = () => ( reducedMotion.matches ? 'auto' : 'smooth' );

	// --- клоны и перевод индексов

	/**
	 * Собирает или разбирает клоны и пересобирает cells.
	 *
	 * Клоны создаёт JS, а не render.php: без JS лента должна оставаться
	 * корректной — три отзыва, без дубликатов.
	 *
	 * @param {boolean} shouldClone Нужны ли клоны на текущей ширине.
	 */
	function buildCells( shouldClone ) {
		track.querySelectorAll( '[data-rz-clone]' ).forEach( ( el ) => el.remove() );

		if ( shouldClone ) {
			const head = items[ items.length - 1 ].cloneNode( true );
			const tail = items[ 0 ].cloneNode( true );

			[ head, tail ].forEach( ( clone ) => {
				clone.setAttribute( 'aria-hidden', 'true' );
				clone.setAttribute( 'data-rz-clone', '' );
				clone.removeAttribute( 'id' );
				clone
					.querySelectorAll( '[id]' )
					.forEach( ( el ) => el.removeAttribute( 'id' ) );
			} );

			track.insertBefore( head, items[ 0 ] );
			track.appendChild( tail );
		}

		cloned = shouldClone;
		cells = Array.from( track.children );

		cells.forEach( ( cell, i ) => {
			let originalIndex = i;

			if ( cloned ) {
				if ( i === 0 ) {
					originalIndex = items.length - 1;
				} else if ( i === cells.length - 1 ) {
					originalIndex = 0;
				} else {
					originalIndex = i - 1;
				}
			}

			cell.setAttribute( 'data-rz-index', String( originalIndex ) );
		} );
	}

	/** Оригинал -> ячейка. С клонами всё сдвинуто вправо на один. */
	function cellIndexOf( originalIndex ) {
		return cloned ? originalIndex + 1 : originalIndex;
	}

	/** Ячейка -> оригинал, по атрибуту, проставленному при сборке. */
	function originalIndexOf( cellIndex ) {
		return Number( cells[ cellIndex ].dataset.rzIndex );
	}

	// --- позиции

	/**
	 * Позиция ячейки: центрируем её в треке. Индексация по cells.
	 * scroll-snap-align: center доводит до точной позиции.
	 */
	function offsetOf( cellIndex ) {
		const cell = cells[ cellIndex ];
		const centered =
			cell.offsetLeft - ( track.clientWidth - cell.offsetWidth ) / 2;

		return Math.max(
			0,
			Math.min( centered, track.scrollWidth - track.clientWidth )
		);
	}

	/** Индекс ЯЧЕЙКИ, чей центр ближе к центру трека. */
	function nearestSlide() {
		const viewCenter = track.scrollLeft + track.clientWidth / 2;
		let best = 0;
		let bestDistance = Infinity;

		cells.forEach( ( cell, i ) => {
			const cellCenter = cell.offsetLeft + cell.offsetWidth / 2;
			const distance = Math.abs( cellCenter - viewCenter );
			if ( distance < bestDistance ) {
				bestDistance = distance;
				best = i;
			}
		} );

		return best;
	}

	/** Плавная прокрутка к ячейке. Адресует cells напрямую. */
	function scrollToCell( cellIndex, behavior ) {
		const left = offsetOf( cellIndex );

		// Признак ставим только если прокрутка реально произойдёт, иначе он
		// остался бы висеть и следующий свайп человека прочитался бы как код.
		isProgrammatic = Math.abs( track.scrollLeft - left ) > 1;

		track.scrollTo( {
			left,
			behavior: behavior || smooth(),
		} );
	}

	/**
	 * Мгновенная установка позиции.
	 *
	 * Порядок операций обязателен именно такой: сохранить scroll-behavior,
	 * поставить auto, изменить scrollLeft, вернуть сохранённое. Без последнего
	 * шага плавная прокрутка пропадёт до перезагрузки страницы.
	 *
	 * behavior: 'auto' в scrollTo() здесь не годится — по спецификации это
	 * «взять значение из CSS», то есть smooth, а не «мгновенно».
	 */
	function jumpToCell( cellIndex ) {
		const saved = track.style.scrollBehavior;
		const left = offsetOf( cellIndex );

		track.style.scrollBehavior = 'auto';
		// Признак ставим непосредственно перед сдвигом: общий дебаунс на 120 мс
		// мог успеть его сбросить, и тогда прыжок засчитался бы за ручное
		// вмешательство и остановил автопрокрутку.
		isProgrammatic = true;
		track.scrollLeft = left;
		track.style.scrollBehavior = saved;
	}

	// --- состояние

	/**
	 * Счётчик показывает номер оригинала, клоны в знаменатель не входят.
	 * is-current ставится по совпадению data-rz-index, поэтому оригинал
	 * и его клон подсвечиваются одновременно — подглядывающая копия
	 * не оказывается приглушённой не в такт.
	 */
	function sync() {
		if ( counter ) {
			counter.textContent = `${ current + 1 } из ${ items.length }`;
		}

		cells.forEach( ( cell ) => {
			cell.classList.toggle(
				'is-current',
				Number( cell.dataset.rzIndex ) === current
			);
		} );
	}

	/**
	 * Переход к оригиналу. Снаружи принимает индекс оригинала, внутри
	 * переводит в индекс ячейки.
	 *
	 * Выход за диапазон адресует клон, а не настоящий крайний слайд: клон
	 * стоит рядом, поэтому прокрутка короткая и идёт в ту же сторону, куда
	 * человек нажал. Прыжок на оригинал произойдёт потом, после остановки.
	 */
	function goTo( originalIndex, behavior ) {
		const total = items.length;
		const wrapped = ( ( originalIndex % total ) + total ) % total;

		let cellIndex = cellIndexOf( wrapped );

		if ( cloned ) {
			if ( originalIndex >= total ) {
				cellIndex = cells.length - 1;
			} else if ( originalIndex < 0 ) {
				cellIndex = 0;
			}
		}

		current = wrapped;
		scrollToCell( cellIndex, behavior );
		sync();
	}

	/**
	 * Если прокрутка остановилась на клоне — мгновенно переставляем её
	 * на оригинал. Идёт своим путём, отдельно от дебаунса, который считает
	 * текущий слайд: там 120 мс, и сосед справа появлялся с заметной задержкой.
	 *
	 * Вызывается из двух источников — scrollend и короткого таймера, — поэтому
	 * защищён от повторного срабатывания: после прыжка ближайшей становится
	 * ячейка-оригинал, и следующий вызов выходит по проверке ниже.
	 */
	function hopFromClone() {
		if ( ! cloned ) {
			return;
		}

		const cellIndex = nearestSlide();
		const isHead = cellIndex === 0;
		const isTail = cellIndex === cells.length - 1;

		if ( ! isHead && ! isTail ) {
			return;
		}

		jumpToCell( cellIndexOf( isHead ? items.length - 1 : 0 ) );
		sync();
	}

	/** Пересборка ленты: клоны по текущей ширине, позиция на текущем оригинале. */
	function rebuild() {
		buildCells( items.length > 1 && wide.matches );

		if ( nav ) {
			nav.classList.toggle( 'rz-reviews__nav--hidden', items.length < 2 );
		}

		jumpToCell( cellIndexOf( current ) );
		sync();
	}

	/** Ширина слайда поменялась — текущую карточку надо снова центрировать. */
	function layout() {
		jumpToCell( cellIndexOf( current ) );
		sync();
	}

	// --- автопрокрутка

	function stop() {
		if ( timer ) {
			window.clearInterval( timer );
			timer = null;
		}
	}

	function start() {
		stop();
		if (
			paused ||
			userTookOver ||
			items.length < 2 ||
			reducedMotion.matches
		) {
			return;
		}
		timer = window.setInterval( () => goTo( current + 1 ), AUTOPLAY_MS );
	}

	function pause() {
		paused = true;
		stop();
	}

	function resume() {
		paused = false;
		start();
	}

	/** Человек взялся листать сам — двигать страницу под ним нельзя. */
	function takeOver() {
		userTookOver = true;
		stop();
	}

	// --- события

	if ( prev ) {
		prev.addEventListener( 'click', () => {
			takeOver();
			goTo( current - 1 );
		} );
	}
	if ( next ) {
		next.addEventListener( 'click', () => {
			takeOver();
			goTo( current + 1 );
		} );
	}

	root.addEventListener( 'mouseenter', pause );
	root.addEventListener( 'mouseleave', resume );
	root.addEventListener( 'focusin', pause );
	root.addEventListener( 'focusout', ( e ) => {
		if ( ! root.contains( e.relatedTarget ) ) {
			resume();
		}
	} );

	/**
	 * Перепрыгивание отдельно от определения текущего слайда.
	 *
	 * scrollend приходит ровно в момент остановки прокрутки — это и есть
	 * самый ранний корректный момент. Где события нет, берём короткий таймер
	 * на 40 мс: прыжок успевает произойти до того, как глаз заметит край ленты.
	 */
	const HOP_FALLBACK_MS = 40;
	const hasScrollEnd = 'onscrollend' in window;

	let hopTick = null;
	if ( hasScrollEnd ) {
		track.addEventListener( 'scrollend', hopFromClone, { passive: true } );
	}

	// Свайп и прокрутка ведут трек сами — только подхватываем позицию.
	let scrollTick = null;
	track.addEventListener(
		'scroll',
		() => {
			if ( ! hasScrollEnd ) {
				window.clearTimeout( hopTick );
				hopTick = window.setTimeout( hopFromClone, HOP_FALLBACK_MS );
			}

			window.clearTimeout( scrollTick );
			scrollTick = window.setTimeout( () => {
				const wasProgrammatic = isProgrammatic;
				isProgrammatic = false;

				if ( ! wasProgrammatic ) {
					takeOver();
				}

				current = originalIndexOf( nearestSlide() );
				sync();
			}, 120 );
		},
		{ passive: true }
	);

	// Вкладка не видна — не крутим впустую.
	document.addEventListener( 'visibilitychange', () => {
		if ( document.hidden ) {
			stop();
		} else {
			start();
		}
	} );

	let resizeTick = null;
	window.addEventListener( 'resize', () => {
		window.clearTimeout( resizeTick );
		resizeTick = window.setTimeout( layout, 150 );
	} );

	// Пересечение 48rem — собрать или разобрать клоны.
	if ( typeof wide.addEventListener === 'function' ) {
		wide.addEventListener( 'change', rebuild );
	}

	if ( typeof reducedMotion.addEventListener === 'function' ) {
		reducedMotion.addEventListener( 'change', start );
	}

	// Стартовая позиция — первый оригинал, мгновенно: иначе лента
	// откроется на клоне последнего отзыва.
	rebuild();
	start();
}

/**
 * Список отзывов на отдельной странице.
 *
 * Карточки все уже в разметке — задача скрипта только прятать лишние
 * и открывать их порциями. Без JS страница остаётся полной: атрибут hidden
 * ставится здесь, а не в PHP, поэтому при отключённом скрипте видны
 * все отзывы сразу, а не пять.
 *
 * Логика карусели сюда не заходит: это отдельная функция, работающая
 * со своим корнем [data-rz-list].
 */
function initList( root ) {
	const items = Array.from( root.querySelectorAll( '.rz-reviews-list__item' ) );
	const more = root.querySelector( '[data-rz-more]' );
	const counter = root.querySelector( '[data-rz-list-counter]' );

	if ( items.length === 0 ) {
		return;
	}

	// Шаг приходит из разметки, а не зашит в код: значение задаётся
	// атрибутом блока и должно меняться без правки скрипта.
	const step = Math.max( 1, Number( root.dataset.rzStep ) || 5 );
	let shown = Math.min( step, items.length );

	function sync() {
		items.forEach( ( item, i ) => {
			item.hidden = i >= shown;
		} );

		if ( counter ) {
			counter.textContent = `${ shown } из ${ items.length }`;
		}

		const done = shown >= items.length;
		if ( more ) {
			more.hidden = done;
		}

		// Показывать нечего с самого начала — прячем и кнопку, и счётчик.
		if ( items.length <= step ) {
			if ( more ) {
				more.hidden = true;
			}
			if ( counter ) {
				counter.hidden = true;
			}
		}
	}

	if ( more ) {
		more.addEventListener( 'click', () => {
			const first = shown;
			shown = Math.min( shown + step, items.length );
			sync();

			/*
			 * Фокус переводим на первую из открытых карточек. Без этого
			 * человек с клавиатуры остаётся на кнопке, которая после
			 * последнего нажатия исчезает, и фокус улетает в конец страницы.
			 */
			const target = items[ first ];
			if ( target ) {
				target.setAttribute( 'tabindex', '-1' );
				target.focus( { preventScroll: false } );
			}
		} );
	}

	sync();
}

function init() {
	document.querySelectorAll( '[data-rz-reviews]' ).forEach( initCarousel );
	document.querySelectorAll( '[data-rz-list]' ).forEach( initList );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
