/**
 * ITT accessibility layer.
 *
 * Two jobs in one file, because they share the same preference store:
 *
 *  1. The floating accessibility widget — text size, contrast modes, link
 *     highlighting, readable font, text spacing and animation stop. Fixed to
 *     the viewport so it is reachable from any scroll position, as ת"י 5568
 *     expects; preferences persist in localStorage across pages.
 *  2. The scroll-driven motion layer — reveals, marker sweeps, pop-ins,
 *     staggered rises and count-ups, all skipped when motion is off.
 *
 * The page renders in its finished state without this file.
 */
( function () {
	'use strict';

	var page = document.querySelector( '.itt-page' );

	if ( ! page ) {
		return;
	}

	var STORAGE_KEY = 'ittA11y';
	var FONT_STEPS = [ 100, 112, 125, 140, 160 ];
	var reduced = window.matchMedia( '(prefers-reduced-motion: reduce)' );
	var motionStarted = false;

	/**
	 * Read the stored preferences.
	 *
	 * @return {Object} Preference bag; empty when storage is unavailable.
	 */
	function load() {
		try {
			return JSON.parse( window.localStorage.getItem( STORAGE_KEY ) || '{}' ) || {};
		} catch ( e ) {
			return {};
		}
	}

	var prefs = load();

	/**
	 * Persist the preferences. A blocked storage must never break the widget.
	 */
	function save() {
		try {
			window.localStorage.setItem( STORAGE_KEY, JSON.stringify( prefs ) );
		} catch ( e ) {
			// Private browsing or a storage quota — the session still works.
		}
	}

	/* --------------------------------------------------------------------
	 * Applying preferences
	 * ----------------------------------------------------------------- */

	/**
	 * Whether animation should be suppressed right now.
	 *
	 * @return {boolean} True when motion is off.
	 */
	function motionOff() {
		if ( true === prefs[ 'no-motion' ] ) {
			return true;
		}

		if ( false === prefs[ 'no-motion' ] ) {
			return false;
		}

		return reduced.matches;
	}

	/**
	 * Push every stored preference onto the page and the controls.
	 */
	function apply() {
		document.querySelectorAll( '[data-itt-a11y-toggle]' ).forEach( function ( button ) {
			var key = button.getAttribute( 'data-itt-a11y-toggle' );
			var on = 'no-motion' === key ? motionOff() : true === prefs[ key ];

			page.classList.toggle( 'itt-' + key, on );
			button.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
		} );

		var size = FONT_STEPS.indexOf( prefs.font ) > -1 ? prefs.font : 100;

		page.style.setProperty( '--itt-font-scale', size / 100 );
		page.classList.toggle( 'itt-scaled', 100 !== size );

		document.querySelectorAll( '[data-itt-a11y-font-value]' ).forEach( function ( el ) {
			el.textContent = size + '%';
		} );

		if ( ! motionOff() && ! motionStarted ) {
			startMotion();
		}
	}

	/* --------------------------------------------------------------------
	 * Widget behaviour
	 * ----------------------------------------------------------------- */

	var widget = document.querySelector( '[data-itt-a11y]' );
	var panel = widget && widget.querySelector( '[id="itt-a11y-panel"]' );
	var lastTrigger = null;

	/**
	 * Focusable children of the panel, for the focus trap.
	 *
	 * @return {Element[]} Focusable elements in DOM order.
	 */
	function panelFocusables() {
		return Array.prototype.slice.call(
			panel.querySelectorAll( 'button, a[href], input, select, textarea' )
		).filter( function ( el ) {
			return ! el.disabled && null !== el.offsetParent;
		} );
	}

	/**
	 * Open or close the panel.
	 *
	 * @param {boolean} open    Desired state.
	 * @param {Element} trigger Element that asked for the change.
	 */
	function setOpen( open, trigger ) {
		if ( ! panel ) {
			return;
		}

		panel.hidden = ! open;
		widget.classList.toggle( 'is-open', open );

		document.querySelectorAll( '[data-itt-a11y-open][aria-expanded]' ).forEach( function ( el ) {
			el.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		} );

		if ( open ) {
			lastTrigger = trigger || lastTrigger;
			var first = panelFocusables()[ 0 ];

			if ( first ) {
				first.focus();
			}
		} else if ( lastTrigger ) {
			lastTrigger.focus();
			lastTrigger = null;
		}
	}

	document.addEventListener( 'click', function ( event ) {
		var open = event.target.closest( '[data-itt-a11y-open]' );
		var close = event.target.closest( '[data-itt-a11y-close]' );
		var toggle = event.target.closest( '[data-itt-a11y-toggle]' );
		var step = event.target.closest( '[data-itt-a11y-font]' );
		var reset = event.target.closest( '[data-itt-a11y-reset]' );

		if ( open ) {
			setOpen( panel.hidden, open );
			return;
		}

		if ( close ) {
			setOpen( false );
			return;
		}

		if ( toggle ) {
			var key = toggle.getAttribute( 'data-itt-a11y-toggle' );
			var now = 'no-motion' === key ? motionOff() : true === prefs[ key ];

			prefs[ key ] = ! now;
			save();
			apply();
			return;
		}

		if ( step ) {
			var current = FONT_STEPS.indexOf( prefs.font ) > -1 ? FONT_STEPS.indexOf( prefs.font ) : 0;
			var next = 'up' === step.getAttribute( 'data-itt-a11y-font' ) ? current + 1 : current - 1;

			prefs.font = FONT_STEPS[ Math.max( 0, Math.min( FONT_STEPS.length - 1, next ) ) ];
			save();
			apply();
			return;
		}

		if ( reset ) {
			prefs = {};
			save();
			apply();
			return;
		}

		// A click outside an open panel closes it, without stealing focus back.
		if ( panel && ! panel.hidden && ! event.target.closest( '[data-itt-a11y]' ) ) {
			lastTrigger = null;
			setOpen( false );
		}
	} );

	document.addEventListener( 'keydown', function ( event ) {
		if ( ! panel || panel.hidden ) {
			return;
		}

		if ( 'Escape' === event.key ) {
			event.preventDefault();
			setOpen( false );
			return;
		}

		if ( 'Tab' !== event.key ) {
			return;
		}

		var items = panelFocusables();

		if ( ! items.length ) {
			return;
		}

		var first = items[ 0 ];
		var last = items[ items.length - 1 ];

		if ( event.shiftKey && document.activeElement === first ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && document.activeElement === last ) {
			event.preventDefault();
			first.focus();
		}
	} );

	/* --------------------------------------------------------------------
	 * Motion layer
	 * ----------------------------------------------------------------- */

	/**
	 * Observe a set of elements once, running a callback on first intersection.
	 *
	 * @param {NodeList|Array} nodes   Elements to watch.
	 * @param {Function}       onEnter Called with (element, index).
	 * @param {Object}         options IntersectionObserver options.
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
	 * Wire every scroll-driven effect. Runs at most once per page load.
	 */
	function startMotion() {
		motionStarted = true;
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

	reduced.addEventListener( 'change', apply );

	apply();
}() );
