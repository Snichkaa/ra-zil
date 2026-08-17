/**
 * Карусель отзывов — надстройка над CSS scroll-snap.
 *
 * Без этого файла карусель остаётся рабочей: трек прокручивается
 * пальцем, колесом и с клавиатуры. JS добавляет стрелки, точки
 * и автопрокрутку, поэтому все элементы управления отдаются
 * из PHP скрытыми и раскрываются здесь.
 */

const AUTOPLAY_MS = 6000;

/** Пользователь просил меньше движения — уважаем на всех уровнях. */
const reducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' );

function initCarousel( root ) {
	const track = root.querySelector( '[data-rz-track]' );
	const prev = root.querySelector( '[data-rz-prev]' );
	const next = root.querySelector( '[data-rz-next]' );
	const dotsBox = root.querySelector( '[data-rz-dots]' );

	if ( ! track ) {
		return;
	}

	const slides = Array.from( track.children );
	if ( slides.length === 0 ) {
		return;
	}

	let pages = 1;
	let current = 0;
	let timer = null;
	let paused = false;

	const smooth = () => ( reducedMotion.matches ? 'auto' : 'smooth' );

	/** Сколько карточек помещается целиком — считаем по реальной ширине слайда. */
	function perView() {
		const slideWidth = slides[ 0 ].getBoundingClientRect().width;
		if ( ! slideWidth ) {
			return 1;
		}
		return Math.max( 1, Math.round( track.clientWidth / slideWidth ) );
	}

	function pageCount() {
		return Math.max( 1, Math.ceil( slides.length / perView() ) );
	}

	/** Позиция страницы в пикселях: страница шириной с видимую область. */
	function offsetOf( page ) {
		return Math.min(
			page * track.clientWidth,
			track.scrollWidth - track.clientWidth
		);
	}

	function goTo( page, behavior ) {
		const target = ( page + pages ) % pages;
		current = target;
		track.scrollTo( {
			left: offsetOf( target ),
			behavior: behavior || smooth(),
		} );
		syncDots();
	}

	function syncDots() {
		if ( ! dotsBox ) {
			return;
		}
		Array.from( dotsBox.children ).forEach( ( dot, i ) => {
			const active = i === current;
			dot.classList.toggle( 'is-active', active );
			if ( active ) {
				dot.setAttribute( 'aria-current', 'true' );
			} else {
				dot.removeAttribute( 'aria-current' );
			}
		} );
	}

	function buildDots() {
		if ( ! dotsBox ) {
			return;
		}
		dotsBox.textContent = '';

		for ( let i = 0; i < pages; i++ ) {
			const dot = document.createElement( 'button' );
			dot.type = 'button';
			dot.className = 'rz-reviews__dot';
			dot.setAttribute( 'aria-label', `Отзывы, страница ${ i + 1 } из ${ pages }` );
			dot.addEventListener( 'click', () => {
				goTo( i );
				restart();
			} );
			dotsBox.appendChild( dot );
		}

		dotsBox.hidden = pages < 2;
		syncDots();
	}

	function layout() {
		const nextPages = pageCount();
		if ( nextPages !== pages ) {
			pages = nextPages;
			buildDots();
		}

		const many = pages > 1;
		if ( prev ) {
			prev.hidden = ! many;
		}
		if ( next ) {
			next.hidden = ! many;
		}
		if ( dotsBox ) {
			dotsBox.hidden = ! many;
		}

		if ( current > pages - 1 ) {
			goTo( pages - 1, 'auto' );
		}
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
		if ( paused || pages < 2 || reducedMotion.matches ) {
			return;
		}
		timer = window.setInterval( () => goTo( current + 1 ), AUTOPLAY_MS );
	}

	function restart() {
		start();
	}

	function pause() {
		paused = true;
		stop();
	}

	function resume() {
		paused = false;
		start();
	}

	// --- события

	if ( prev ) {
		prev.addEventListener( 'click', () => {
			goTo( current - 1 );
			restart();
		} );
	}
	if ( next ) {
		next.addEventListener( 'click', () => {
			goTo( current + 1 );
			restart();
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

	// Свайп и прокрутка ведут трек сами — только подхватываем позицию.
	let scrollTick = null;
	track.addEventListener(
		'scroll',
		() => {
			window.clearTimeout( scrollTick );
			scrollTick = window.setTimeout( () => {
				const width = track.clientWidth || 1;
				current = Math.min( pages - 1, Math.round( track.scrollLeft / width ) );
				syncDots();
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

	if ( typeof reducedMotion.addEventListener === 'function' ) {
		reducedMotion.addEventListener( 'change', start );
	}

	pages = pageCount();
	buildDots();
	layout();
	start();
}

function init() {
	document.querySelectorAll( '[data-rz-reviews]' ).forEach( initCarousel );
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
