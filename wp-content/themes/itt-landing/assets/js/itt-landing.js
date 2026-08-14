/**
 * ITT landing page interactions.
 *
 * Semester tabs, single-open accordions, the testimonial slider, the video
 * facades, the rotating quote and the lead form. Everything is progressive:
 * the markup works without this file, the script only adds the behaviour from
 * the design.
 */
( function () {
	'use strict';

	var config = window.ittLanding || {};
	var i18n = config.i18n || {};

	/* --------------------------------------------------------------------
	 * Semester tabs
	 * ----------------------------------------------------------------- */

	function initTabs() {
		var tablist = document.querySelector( '[role="tablist"]' );

		if ( ! tablist ) {
			return;
		}

		var tabs = Array.prototype.slice.call( tablist.querySelectorAll( '[role="tab"]' ) );

		/**
		 * Show one tab and hide the rest.
		 *
		 * @param {number}  index      Tab to activate.
		 * @param {boolean} moveFocus  Whether to move focus to the new tab.
		 */
		function select( index, moveFocus ) {
			tabs.forEach( function ( tab, i ) {
				var active = i === index;
				var panel = document.getElementById( tab.getAttribute( 'aria-controls' ) );

				tab.setAttribute( 'aria-selected', active ? 'true' : 'false' );
				tab.setAttribute( 'tabindex', active ? '0' : '-1' );
				tab.classList.toggle( 'is-active', active );

				if ( panel ) {
					panel.hidden = ! active;
				}
			} );

			if ( moveFocus ) {
				tabs[ index ].focus();
			}
		}

		tabs.forEach( function ( tab, index ) {
			tab.addEventListener( 'click', function () {
				select( index, false );
			} );

			tab.addEventListener( 'keydown', function ( event ) {
				// The tablist is RTL: ArrowLeft moves forward, ArrowRight back.
				var step = 0;

				if ( 'ArrowLeft' === event.key ) {
					step = 1;
				} else if ( 'ArrowRight' === event.key ) {
					step = -1;
				} else if ( 'Home' === event.key ) {
					event.preventDefault();
					select( 0, true );
					return;
				} else if ( 'End' === event.key ) {
					event.preventDefault();
					select( tabs.length - 1, true );
					return;
				}

				if ( 0 === step ) {
					return;
				}

				event.preventDefault();
				select( ( index + step + tabs.length ) % tabs.length, true );
			} );
		} );

		select( 0, false );
	}

	/* --------------------------------------------------------------------
	 * Accordions — native <details>, limited to one open per group
	 * ----------------------------------------------------------------- */

	function initAccordions() {
		document.querySelectorAll( '[data-itt-accordion]' ).forEach( function ( group ) {
			var items = Array.prototype.slice.call( group.querySelectorAll( 'details' ) );

			items.forEach( function ( item ) {
				item.addEventListener( 'toggle', function () {
					if ( ! item.open ) {
						return;
					}

					items.forEach( function ( other ) {
						if ( other !== item ) {
							other.open = false;
						}
					} );
				} );
			} );
		} );
	}

	/* --------------------------------------------------------------------
	 * Testimonial slider
	 * ----------------------------------------------------------------- */

	function initSlider() {
		var slider = document.querySelector( '[data-itt-slider]' );

		if ( ! slider ) {
			return;
		}

		var track = slider.querySelector( '.itt-slider__viewport' );
		var buttons = slider.querySelectorAll( '[data-itt-slide]' );

		if ( ! track ) {
			return;
		}

		/**
		 * Grey out an arrow once the track cannot scroll further that way.
		 */
		function syncButtons() {
			var max = track.scrollWidth - track.clientWidth;
			// scrollLeft is negative in RTL; compare on distance travelled.
			var offset = Math.abs( track.scrollLeft );

			buttons.forEach( function ( button ) {
				var atEnd = 'next' === button.getAttribute( 'data-itt-slide' )
					? offset >= max - 2
					: offset <= 2;

				button.disabled = atEnd;
			} );
		}

		buttons.forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var page = track.clientWidth + 18;
				var direction = 'next' === button.getAttribute( 'data-itt-slide' ) ? -1 : 1;

				track.scrollBy( { left: direction * page, behavior: 'smooth' } );
			} );
		} );

		track.addEventListener( 'scroll', function () {
			window.requestAnimationFrame( syncButtons );
		}, { passive: true } );

		window.addEventListener( 'resize', syncButtons, { passive: true } );

		syncButtons();
	}

	/* --------------------------------------------------------------------
	 * Video facades — nothing is loaded from YouTube/Vimeo until asked
	 * ----------------------------------------------------------------- */

	function initFacades() {
		document.querySelectorAll( '[data-itt-video]' ).forEach( function ( facade ) {
			facade.addEventListener( 'click', function () {
				var src = facade.getAttribute( 'data-itt-video' );
				var label = facade.textContent.trim();
				var player;

				if ( 'file' === facade.getAttribute( 'data-itt-video-type' ) ) {
					// A video uploaded to the media library: native controls, so
					// captions, volume and playback speed stay keyboard operable.
					player = document.createElement( 'video' );
					player.src = src;
					player.controls = true;
					player.preload = 'metadata';
					player.setAttribute( 'playsinline', '' );
					player.setAttribute( 'aria-label', label );
					player.className = 'itt-facade__frame';
				} else {
					player = document.createElement( 'iframe' );
					player.src = src;
					player.title = label;
					player.allow = 'accelerometer; autoplay; encrypted-media; picture-in-picture';
					player.allowFullscreen = true;
					player.className = 'itt-facade__frame';
				}

				facade.replaceWith( player );
				player.focus();

				if ( 'function' === typeof player.play ) {
					player.play().catch( function () {
						// Autoplay refused: the visitor presses the native control.
					} );
				}
			} );
		} );
	}

	/* --------------------------------------------------------------------
	 * Rotating quote
	 * ----------------------------------------------------------------- */

	function initQuotes() {
		var wrapper = document.querySelector( '[data-itt-quotes]' );

		if ( ! wrapper ) {
			return;
		}

		var quotes = Array.prototype.slice.call( wrapper.querySelectorAll( '[data-itt-quote]' ) );

		if ( quotes.length < 2 ) {
			return;
		}

		var current = 0;
		var timer = null;

		/**
		 * Show one quote.
		 *
		 * @param {number} index Quote to show.
		 */
		function show( index ) {
			current = ( index + quotes.length ) % quotes.length;

			quotes.forEach( function ( quote, i ) {
				quote.hidden = i !== current;
			} );
		}

		function stop() {
			window.clearInterval( timer );
			timer = null;
		}

		function play() {
			// Auto-rotation is a courtesy, never a requirement: it stays off for
			// visitors who asked for reduced motion, and stops on interaction.
			if ( timer || document.querySelector( '.itt-page.itt-no-motion' ) ) {
				return;
			}

			timer = window.setInterval( function () {
				show( current + 1 );
			}, 7000 );
		}

		wrapper.querySelectorAll( '[data-itt-quote-nav]' ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				stop();
				show( current + ( 'next' === button.getAttribute( 'data-itt-quote-nav' ) ? 1 : -1 ) );
			} );
		} );

		wrapper.addEventListener( 'mouseenter', stop );
		wrapper.addEventListener( 'focusin', stop );
		wrapper.addEventListener( 'mouseleave', play );

		document.addEventListener( 'visibilitychange', function () {
			if ( document.hidden ) {
				stop();
			} else {
				play();
			}
		} );

		window.addEventListener( 'beforeunload', stop );

		show( 0 );
		play();
	}

	/* --------------------------------------------------------------------
	 * Lead form
	 * ----------------------------------------------------------------- */

	function initForm() {
		var form = document.querySelector( '[data-itt-form]' );

		if ( ! form || ! config.submitUrl ) {
			return;
		}

		var summary = form.querySelector( '[data-itt-form-errors]' );
		var submit = form.querySelector( '[data-itt-submit]' );
		var submitLabel = submit ? submit.textContent : '';
		var rendered = Math.floor( Date.now() / 1000 );

		/**
		 * Show or clear the message attached to one field.
		 *
		 * @param {string} field   Field name.
		 * @param {string} message Message, empty to clear.
		 */
		function setFieldError( field, message ) {
			var input = form.querySelector( '[name="' + field + '"]' );
			var slot = form.querySelector( '[data-itt-error="' + field + '"]' );

			if ( slot ) {
				slot.textContent = message;
			}

			if ( input ) {
				if ( message ) {
					input.setAttribute( 'aria-invalid', 'true' );
				} else {
					input.removeAttribute( 'aria-invalid' );
				}
			}
		}

		/**
		 * Client-side mirror of the server validation, for a faster answer.
		 *
		 * @return {Object} Field name => message.
		 */
		function validate() {
			var errors = {};
			var name = form.elements.name.value.trim();
			var phone = form.elements.phone.value.replace( /\D/g, '' );
			var email = form.elements.email ? form.elements.email.value.trim() : '';

			if ( name.length < 2 ) {
				errors.name = i18n.nameRequired;
			}

			if ( phone.length < 9 ) {
				errors.phone = i18n.phoneInvalid;
			}

			if ( email && ! /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test( email ) ) {
				errors.email = i18n.emailInvalid;
			}

			// The server refuses a lead without consent; check here too so the
			// visitor is told before a round trip.
			if ( form.elements.consent && ! form.elements.consent.checked ) {
				errors.consent = i18n.consentRequired;
			}

			return errors;
		}

		/**
		 * Announce a submission-level message.
		 *
		 * @param {string} message Message, empty to hide the block.
		 */
		function announce( message ) {
			if ( ! summary ) {
				return;
			}

			summary.textContent = message;
			summary.hidden = ! message;
		}

		/**
		 * Fetch a fresh REST nonce. Never printed into the cached HTML.
		 *
		 * @return {Promise<string>} The nonce, or an empty string.
		 */
		function fetchNonce() {
			if ( ! config.nonceUrl ) {
				return Promise.resolve( '' );
			}

			return fetch( config.nonceUrl, { credentials: 'same-origin' } )
				.then( function ( response ) {
					return response.ok ? response.json() : {};
				} )
				.then( function ( data ) {
					return data.nonce || '';
				} )
				.catch( function () {
					return '';
				} );
		}

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			[ 'name', 'phone', 'email', 'consent' ].forEach( function ( field ) {
				setFieldError( field, '' );
			} );

			var errors = validate();
			var keys = Object.keys( errors );

			if ( keys.length ) {
				keys.forEach( function ( field ) {
					setFieldError( field, errors[ field ] );
				} );

				announce( i18n.errorsHeading + ' ' + keys.map( function ( key ) {
					return errors[ key ];
				} ).join( ' ' ) );

				var first = form.querySelector( '[name="' + keys[ 0 ] + '"]' );

				if ( first ) {
					first.focus();
				}

				return;
			}

			announce( '' );

			if ( submit ) {
				submit.setAttribute( 'aria-disabled', 'true' );
				submit.textContent = i18n.sending;
			}

			var payload = {
				name: form.elements.name.value.trim(),
				phone: form.elements.phone.value.trim(),
				email: form.elements.email ? form.elements.email.value.trim() : '',
				message: form.elements.message ? form.elements.message.value.trim() : '',
				consent: form.elements.consent ? form.elements.consent.checked : true,
				hp: form.elements.hp ? form.elements.hp.value : '',
				ts: rendered
			};

			fetchNonce()
				.then( function ( nonce ) {
					return fetch( config.submitUrl, {
						method: 'POST',
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': nonce
						},
						body: JSON.stringify( payload )
					} );
				} )
				.then( function ( response ) {
					return response.json().then( function ( data ) {
						return { ok: response.ok, data: data };
					} );
				} )
				.then( function ( result ) {
					if ( ! result.ok ) {
						throw new Error( ( result.data && result.data.message ) || i18n.genericError );
					}

					if ( result.data.redirect ) {
						window.location.assign( result.data.redirect );
						return;
					}

					succeed();
				} )
				.catch( function ( error ) {
					if ( submit ) {
						submit.removeAttribute( 'aria-disabled' );
						submit.textContent = submitLabel;
					}

					announce( error.message || i18n.genericError );

					if ( summary ) {
						summary.focus();
					}
				} );
		} );

		/**
		 * Replace the form with a confirmation when no thank-you page is set.
		 */
		function succeed() {
			var box = document.createElement( 'div' );
			var title = document.createElement( 'p' );

			box.className = 'itt-form__success';
			title.className = 'itt-form__success-title';
			title.textContent = i18n.success;
			box.appendChild( title );
			box.setAttribute( 'role', 'status' );
			box.setAttribute( 'tabindex', '-1' );

			form.replaceWith( box );
			box.focus();
		}
	}

	initTabs();
	initAccordions();
	initSlider();
	initFacades();
	initQuotes();
	initForm();
}() );
