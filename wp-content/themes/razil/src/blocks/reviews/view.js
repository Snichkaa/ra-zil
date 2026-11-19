/** Infinite review carousel. Complete neighbour sets prevent empty edges. */
const AUTOPLAY_MS = 7000;
const reducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' );

function initCarousel( root ) {
 const track = root.querySelector( '[data-rz-track]' );
 if ( ! track ) { return; }
 const items = Array.from( track.children );
 const total = items.length;
 const counter = root.querySelector( '[data-rz-counter]' );
 const nav = root.querySelector( '[data-rz-nav]' );
 if ( ! total ) { return; }
 if ( nav ) { nav.classList.toggle( 'rz-reviews__nav--hidden', total < 2 ); }
 if ( total < 2 ) { return; }

 // Originals occupy the middle set on both desktop and touch screens.
 function copy( item, index ) {
  const clone = item.cloneNode( true );
  clone.dataset.rzClone = '';
  clone.dataset.rzIndex = String( index );
  clone.setAttribute( 'aria-hidden', 'true' );
  clone.inert = true;
  clone.removeAttribute( 'id' );
  clone.querySelectorAll( '[id]' ).forEach( ( el ) => el.removeAttribute( 'id' ) );
  return clone;
 }
 const before = document.createDocumentFragment();
 const after = document.createDocumentFragment();
 items.forEach( ( item, i ) => {
  item.dataset.rzIndex = String( i );
  before.appendChild( copy( item, i ) );
  after.appendChild( copy( item, i ) );
 } );
 track.prepend( before );
 track.append( after );
 const cells = Array.from( track.children );
 let target = total;
 let current = 0;
 let moving = false;
 let userTookOver = false;
 let paused = false;
 let timer;
 let settleTimer;
 let frame = null;
 const wrap = ( n ) => ( ( n % total ) + total ) % total;
 function offset( i ) {
  return cells[i].offsetLeft - ( track.clientWidth - cells[i].offsetWidth ) / 2;
 }
 function nearest() {
  return cells.reduce( ( best, cell, i ) =>
   Math.abs( offset( i ) - track.scrollLeft ) < Math.abs( offset( best ) - track.scrollLeft ) ? i : best, 0 );
 }
 function sync() {
  if ( counter ) { counter.textContent = ( current + 1 ) + ' из ' + total; }
  cells.forEach( ( cell ) => cell.classList.toggle( 'is-current', Number( cell.dataset.rzIndex ) === current ) );
 }
 function jump( left ) {
  // Explicit instant overrides CSS smooth scrolling; no delayed clone reset.
  track.scrollTo( { left, behavior: 'instant' } );
 }
 function rebase() {
  const canonical = total + wrap( target );
  if ( target !== canonical ) {
   const delta = offset( canonical ) - offset( target );
   target = canonical;
   jump( track.scrollLeft + delta );
  }
 }
 function settle() {
  if ( frame !== null ) { return; }
  if ( moving && Math.abs( track.scrollLeft - offset( target ) ) > 2 ) { return; }
  moving = false;
  target = nearest();
  current = wrap( target );
  rebase();
  sync();
 }
 function step( direction ) {
  window.cancelAnimationFrame( frame );
  frame = null;
  // Keep the requested index while animation runs: fast repeated clicks count.
  if ( ! moving ) { target = nearest(); }
  if ( target + direction < 1 || target + direction > cells.length - 2 ) { rebase(); }
  target += direction;
  current = wrap( target );
  moving = true;
  sync();
  const from = track.scrollLeft;
  const destination = offset( target );
  if ( reducedMotion.matches ) { jump( destination ); settle(); return; }
  // A fixed short transition responds immediately, even after rapid clicks.
  // Native smooth scrolling otherwise spends over a second on a long retarget.
  track.style.scrollSnapType = 'none';
  const started = performance.now();
  function tick( now ) {
   const progress = Math.min( 1, ( now - started ) / 260 );
   jump( from + ( destination - from ) * ( 1 - Math.pow( 1 - progress, 3 ) ) );
   if ( progress < 1 ) { frame = window.requestAnimationFrame( tick ); }
   else {
    frame = null;
    track.style.scrollSnapType = '';
    settle();
   }
  }
  frame = window.requestAnimationFrame( tick );
 }
 function stop() { window.clearInterval( timer ); }
 function start() {
  stop();
  if ( ! userTookOver && ! paused && ! document.hidden && ! reducedMotion.matches ) {
   timer = window.setInterval( () => step( 1 ), AUTOPLAY_MS );
  }
 }
 function takeOver() { userTookOver = true; stop(); }
 for ( const [ selector, direction ] of [ [ '[data-rz-prev]', -1 ], [ '[data-rz-next]', 1 ] ] ) {
  root.querySelector( selector )?.addEventListener( 'click', () => { takeOver(); step( direction ); } );
 }
 for ( const event of [ 'pointerdown', 'wheel', 'keydown' ] ) {
  track.addEventListener( event, () => {
   takeOver();
   window.cancelAnimationFrame( frame );
   frame = null;
   moving = false;
   track.style.scrollSnapType = '';
  }, { passive: true } );
 }
 // scrollend has no extra debounce. The fallback is only for older browsers.
 track.addEventListener( 'scrollend', settle );
 track.addEventListener( 'scroll', () => {
  if ( ! moving ) { current = wrap( nearest() ); sync(); }
  if ( !( 'onscrollend' in track ) ) {
   window.clearTimeout( settleTimer );
   settleTimer = window.setTimeout( settle, 80 );
  }
 }, { passive: true } );
 root.addEventListener( 'mouseenter', () => { paused = true; stop(); } );
 root.addEventListener( 'mouseleave', () => { paused = false; start(); } );
 root.addEventListener( 'focusin', () => { paused = true; stop(); } );
 root.addEventListener( 'focusout', ( event ) => {
  if ( !root.contains( event.relatedTarget ) ) { paused = false; start(); }
 } );
 document.addEventListener( 'visibilitychange', start );
 reducedMotion.addEventListener( 'change', start );
 function layout() {
  window.cancelAnimationFrame( frame );
  frame = null;
  track.style.scrollSnapType = '';
  moving = false;
  target = total + current;
  jump( offset( target ) );
  sync();
 }
 new ResizeObserver( layout ).observe( track );
 layout();
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
