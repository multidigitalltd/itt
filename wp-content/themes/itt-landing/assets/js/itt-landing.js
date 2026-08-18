/**
 * ITT landing page interactions.
 *
 * Semester tabs, single-open accordions, the testimonial slider, the video
 * facades and the lead form. Everything is progressive:
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
		// Explicitly the syllabus tablist. Selecting on [role="tablist"] alone
		// picked whichever came first in the document, which broke the moment
		// the video gallery — also a tablist — landed above it in the page.
		var tablist = document.querySelector( '[data-itt-tabs]' );

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

	function initSliders() {
		document.querySelectorAll( '[data-itt-slider]' ).forEach( initSlider );
	}

	/**
	 * Wire one slider's arrows to its own track.
	 *
	 * @param {Element} slider The slider wrapper.
	 */
	function initSlider( slider ) {
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
	 * "Read more" on the written testimonials
	 * ----------------------------------------------------------------- */

	function initReadMore() {
		var toggles = Array.prototype.slice.call( document.querySelectorAll( '[data-itt-more]' ) );

		if ( ! toggles.length ) {
			return;
		}

		/**
		 * Clamp a quote and reveal its toggle, but only when the text really is
		 * taller than the clamp — a button that expands nothing is worse than no
		 * button at all.
		 *
		 * @param {Element} button The toggle.
		 */
		function measure( button ) {
			var text = document.getElementById( button.getAttribute( 'aria-controls' ) );

			if ( ! text || 'true' === button.getAttribute( 'aria-expanded' ) ) {
				return;
			}

			text.classList.add( 'is-clamped' );

			var overflows = text.scrollHeight - text.clientHeight > 2;

			button.hidden = ! overflows;

			if ( ! overflows ) {
				text.classList.remove( 'is-clamped' );
			}
		}

		toggles.forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var text = document.getElementById( button.getAttribute( 'aria-controls' ) );
				var expanded = 'true' === button.getAttribute( 'aria-expanded' );
				var label = button.querySelector( '[data-itt-more-label]' );

				if ( ! text ) {
					return;
				}

				text.classList.toggle( 'is-clamped', expanded );
				button.setAttribute( 'aria-expanded', expanded ? 'false' : 'true' );

				if ( label ) {
					// Fall back in place: a missing string must never blank the button.
					label.textContent = expanded
						? ( i18n.readMore || 'קרא עוד' )
						: ( i18n.readLess || 'הצג פחות' );
				}
			} );
		} );

		function measureAll() {
			toggles.forEach( measure );
		}

		measureAll();

		// The clamp is measured in lines, so it has to be re-checked once the
		// real font is in and whenever the column width changes.
		if ( document.fonts && document.fonts.ready ) {
			document.fonts.ready.then( measureAll );
		}

		var resizeTimer = null;

		window.addEventListener( 'resize', function () {
			window.clearTimeout( resizeTimer );
			resizeTimer = window.setTimeout( measureAll, 200 );
		}, { passive: true } );
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
				slot.textContent = message || '';
			}

			if ( input ) {
				if ( message ) {
					input.setAttribute( 'aria-invalid', 'true' );
				} else {
					input.removeAttribute( 'aria-invalid' );
				}

				// aria-invalid on a checkbox has nothing to repaint, so the row
				// carries the state instead — otherwise an unticked consent box
				// is reported by the summary with nothing on screen to look at.
				var field_el = input.closest( '.itt-field' );

				if ( field_el ) {
					field_el.classList.toggle( 'is-invalid', !! message );
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
				errors.consent = i18n.consentRequired || 'שדה חובה — יש לאשר את תנאי השימוש ומדיניות הפרטיות.';
			}

			if ( config.turnstile && ! turnstileToken() ) {
				errors.turnstile = i18n.turnstileMissing || 'נא להשלים את אימות האבטחה שמתחת לטופס.';
			}

			return errors;
		}

		/**
		 * The Turnstile token, if the widget has produced one.
		 *
		 * @return {string} Token, or an empty string.
		 */
		function turnstileToken() {
			var input = form.querySelector( '[name="cf-turnstile-response"]' );

			return input ? input.value : '';
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

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			[ 'name', 'phone', 'email', 'consent', 'turnstile' ].forEach( function ( field ) {
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
				ts: rendered,
				turnstile: turnstileToken(),
				page: parseInt( form.getAttribute( 'data-itt-page' ), 10 ) || 0
			};

			// credentials: 'omit' on purpose. With a WordPress auth cookie
			// attached, WordPress runs its REST cookie-nonce check before the
			// endpoint is reached and rejects the request with "Cookie check
			// failed" whenever that nonce has gone stale — which a page cache
			// or an expired session makes routine. The endpoint is public, so
			// there is nothing for the cookie to authenticate anyway.
			fetch( config.submitUrl, {
				method: 'POST',
				credentials: 'omit',
				headers: {
					'Content-Type': 'application/json'
				},
				body: JSON.stringify( payload )
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

					// A Turnstile token is good for one submission. Without a
					// reset, a visitor who fixes a typo and tries again is
					// rejected for a token that was already spent.
					if ( config.turnstile && window.turnstile ) {
						window.turnstile.reset();
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

	/**
	 * The testimonial video gallery: one stage, many thumbnails.
	 *
	 * Thumbnails are a tablist and each stage entry its tabpanel, so the
	 * keyboard behaviour people already expect from tabs — arrows to move,
	 * Home/End to jump, one stop in the tab order — comes with the pattern.
	 * The prev/next buttons at the top drive exactly the same selection, which
	 * is the point: they change the large video, not a separate row.
	 */
	function initVideoGalleries() {
		document.querySelectorAll( '[data-itt-vgallery]' ).forEach( function ( gallery ) {
			var tabs = Array.prototype.slice.call( gallery.querySelectorAll( '[role="tab"]' ) );

			if ( tabs.length < 2 ) {
				return;
			}

			/**
			 * Show one testimonial and mark its thumbnail.
			 *
			 * @param {number}  index     Position to show.
			 * @param {boolean} moveFocus Whether to focus the thumbnail.
			 */
			function select( index, moveFocus ) {
				var next = ( index + tabs.length ) % tabs.length;

				// Retrigger the fade on the entry that is about to show. The
				// class has to come off and go back on for the animation to
				// replay when the same panel is reselected.
				var incoming = document.getElementById( tabs[ next ].getAttribute( 'aria-controls' ) );

				if ( incoming ) {
					incoming.classList.remove( 'is-entering' );
					void incoming.offsetWidth;
					incoming.classList.add( 'is-entering' );
				}

				tabs.forEach( function ( tab, i ) {
					var on = i === next;
					var panel = document.getElementById( tab.getAttribute( 'aria-controls' ) );

					tab.setAttribute( 'aria-selected', on ? 'true' : 'false' );
					tab.setAttribute( 'tabindex', on ? '0' : '-1' );
					tab.classList.toggle( 'is-active', on );

					if ( panel ) {
						panel.hidden = ! on;
					}
				} );

				// Keep the chosen thumbnail in view when the strip scrolls.
				if ( tabs[ next ].scrollIntoView ) {
					tabs[ next ].scrollIntoView( { block: 'nearest', inline: 'nearest' } );
				}

				if ( moveFocus ) {
					tabs[ next ].focus();
				}
			}

			/**
			 * Index of the thumbnail currently selected.
			 *
			 * @return {number} Zero-based position.
			 */
			function current() {
				var found = tabs.findIndex( function ( tab ) {
					return 'true' === tab.getAttribute( 'aria-selected' );
				} );

				return found > -1 ? found : 0;
			}

			tabs.forEach( function ( tab, i ) {
				tab.addEventListener( 'click', function () {
					select( i, false );
				} );
			} );

			gallery.querySelectorAll( '[data-itt-vgallery-step]' ).forEach( function ( button ) {
				button.addEventListener( 'click', function () {
					select( current() + parseInt( button.getAttribute( 'data-itt-vgallery-step' ), 10 ), false );
				} );
			} );

			gallery.addEventListener( 'keydown', function ( event ) {
				if ( ! event.target.closest( '[role="tab"]' ) ) {
					return;
				}

				// RTL: ArrowLeft advances, because the strip reads right to left.
				var moves = {
					ArrowLeft: 1,
					ArrowRight: -1,
					ArrowDown: 1,
					ArrowUp: -1
				};

				if ( 'Home' === event.key ) {
					event.preventDefault();
					select( 0, true );
					return;
				}

				if ( 'End' === event.key ) {
					event.preventDefault();
					select( tabs.length - 1, true );
					return;
				}

				if ( undefined === moves[ event.key ] ) {
					return;
				}

				event.preventDefault();
				select( current() + moves[ event.key ], true );
			} );
		} );
	}

	initTabs();
	initAccordions();
	initSliders();
	initVideoGalleries();
	initReadMore();
	initFacades();
	initForm();
}() );
