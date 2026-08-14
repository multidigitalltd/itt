/**
 * ITT motion layer.
 *
 * Scroll reveals, the marker sweep, pop-ins, staggered card rises and the
 * count-up numbers — all built on IntersectionObserver, all one-shot, and all
 * skipped when the visitor asks for reduced motion or presses "stop animations".
 *
 * Runs on both ITT templates; the page renders in its finished state without it.
 */
( function () {
	'use strict';

	var page = document.querySelector( '.itt-page' );

	if ( ! page ) {
		return;
	}

	var STORAGE_KEY = 'ittMotion';
	var reduced = window.matchMedia( '(prefers-reduced-motion: reduce)' );

	/**
	 * Whether animation should be suppressed for this visitor.
	 *
	 * @return {boolean} True when motion is off.
	 */
	function motionOff() {
		var stored = null;

		try {
			stored = window.localStorage.getItem( STORAGE_KEY );
		} catch ( e ) {
			stored = null;
		}

		if ( 'off' === stored ) {
			return true;
		}

		if ( 'on' === stored ) {
			return false;
		}

		return reduced.matches;
	}

	/**
	 * Observe a set of elements once, running a callback on first intersection.
	 *
	 * @param {NodeList|Array} nodes     Elements to watch.
	 * @param {Function}       onEnter   Called with (element, index).
	 * @param {Object}         options   IntersectionObserver options.
	 */
	function observeOnce( nodes, onEnter, options ) {
		var list = Array.prototype.slice.call( nodes );

		if ( ! list.length ) {
			return;
		}

		var observer = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( ! entry.isIntersecting ) {
					return;
				}

				observer.unobserve( entry.target );
				onEnter( entry.target, list.indexOf( entry.target ) );
			} );
		}, options );

		list.forEach( function ( node ) {
			observer.observe( node );
		} );
	}

	/**
	 * Animate one number from zero to its rendered value.
	 *
	 * @param {Element} el Element carrying data-count.
	 */
	function countUp( el ) {
		var target = parseInt( el.getAttribute( 'data-count' ), 10 );

		if ( isNaN( target ) || target <= 0 ) {
			return;
		}

		var comma = el.hasAttribute( 'data-count-comma' );
		var start = performance.now();
		var duration = 1100;

		function tick( now ) {
			var progress = Math.min( 1, ( now - start ) / duration );
			var value = Math.round( target * ( 1 - Math.pow( 1 - progress, 3 ) ) );

			el.textContent = comma ? value.toLocaleString( 'en-US' ) : String( value );

			if ( progress < 1 ) {
				requestAnimationFrame( tick );
			}
		}

		requestAnimationFrame( tick );
	}

	/**
	 * Wire every scroll-driven effect.
	 */
	function start() {
		page.classList.add( 'js-itt' );

		observeOnce(
			document.querySelectorAll( '.itt-reveal' ),
			function ( el, index ) {
				el.style.transitionDelay = Math.min( index * 0.12, 0.36 ) + 's';
				el.classList.add( 'is-visible' );
			},
			{ threshold: 0.12, rootMargin: '0px 0px -40px 0px' }
		);

		observeOnce(
			document.querySelectorAll( '.itt-hl' ),
			function ( el ) {
				var order = parseInt( el.getAttribute( 'data-hl' ), 10 ) || 0;

				window.setTimeout( function () {
					el.classList.add( 'is-visible' );
				}, 220 + order * 260 );
			},
			{ threshold: 0.55 }
		);

		observeOnce(
			document.querySelectorAll( '.itt-pop' ),
			function ( el ) {
				el.classList.add( 'is-visible' );
			},
			{ threshold: 0.6 }
		);

		observeOnce(
			document.querySelectorAll( '.itt-rise' ),
			function ( el, index ) {
				window.setTimeout( function () {
					el.classList.add( 'is-visible' );
				}, ( index % 3 ) * 120 + Math.floor( index / 3 ) * 60 );
			},
			{ threshold: 0.25 }
		);

		observeOnce( document.querySelectorAll( '.itt-count' ), countUp, { threshold: 0.8 } );
	}

	/**
	 * Apply the current motion preference to the page.
	 */
	function apply() {
		var off = motionOff();

		page.classList.toggle( 'itt-no-motion', off );

		document.querySelectorAll( '[data-itt-motion-toggle]' ).forEach( function ( button ) {
			button.setAttribute( 'aria-pressed', off ? 'true' : 'false' );
		} );

		if ( ! off && ! page.classList.contains( 'js-itt' ) ) {
			start();
		}
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-itt-motion-toggle]' );

		if ( ! button ) {
			return;
		}

		var next = 'true' === button.getAttribute( 'aria-pressed' ) ? 'on' : 'off';

		try {
			window.localStorage.setItem( STORAGE_KEY, next );
		} catch ( e ) {
			// A blocked storage is not a reason to ignore the click.
		}

		apply();
	} );

	reduced.addEventListener( 'change', apply );

	apply();
}() );
