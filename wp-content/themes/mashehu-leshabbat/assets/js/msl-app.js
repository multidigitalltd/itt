/*
 * The page.
 *
 * One state machine over one document. Every screen is already in the DOM and
 * is switched with `hidden`, which is what lets the artwork canvas stay mounted
 * through the join sequence — the camera pull-back at the end only works
 * because nothing is torn down and rebuilt underneath it.
 *
 * Nothing here reaches the network on load. The counters, the feed and the
 * referral count all arrive after first paint, so the HTML itself carries no
 * per-visitor value and stays cacheable end to end.
 */

(function () {
	'use strict';

	var config = window.MSL;

	if (!config || !window.MSLCanvas) { return; }

	var canvasEngine = window.MSLCanvas;
	var $ = function (sel, root) { return (root || document).querySelector(sel); };
	var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

	var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	var state = {
		screen: 'home',
		step: 1,
		lang: config.lang,
		participants: config.stats.participants,
		pct: config.stats.pct,
		rate: 0,
		lastPoll: 0,
		refCode: '',
		refCount: 0,
		nextMilestone: 0,
		result: null,
		openedAt: 0,
		artPick: null,
		wallPick: null,
		last10: config.stats.last10,
		countries: config.stats.countries,
		expected: config.stats.participants,
		pieces: {}
	};

	/* ------------------------------------------------------------------
	 * Cookies
	 * --------------------------------------------------------------- */

	function readCookie(name) {
		var match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)'));
		return match ? decodeURIComponent(match[1]) : '';
	}

	function writeCookie(name, value, days) {
		var expires = new Date(Date.now() + days * 864e5).toUTCString();
		document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + expires + '; path=/; SameSite=Lax';
	}

	/* ------------------------------------------------------------------
	 * Formatting
	 * --------------------------------------------------------------- */

	function num(value) {
		return Number(value).toLocaleString(state.lang === 'he' ? 'he-IL' : 'en-US');
	}

	/* printf-style, with the same two shapes PHP uses on the server: a bare %d
	   or %s, and the numbered %1$s form for sentences that reorder between the
	   two languages. */
	function format(template, values) {
		var i = 0;

		return String(template)
			.replace(/%(\d+)\$[sd]/g, function (all, position) {
				var v = values[Number(position) - 1];
				return v === undefined ? all : v;
			})
			.replace(/%[sd]/g, function (all) {
				var v = values[i++];
				return v === undefined ? all : v;
			});
	}

	/* ------------------------------------------------------------------
	 * Language
	 * --------------------------------------------------------------- */

	function dictionary() {
		return config.i18n[state.lang] || {};
	}

	function t(key) {
		var value = dictionary()[key];
		return value === undefined ? '' : value;
	}

	function applyLanguage() {
		var dict = dictionary();

		document.documentElement.lang = state.lang === 'he' ? 'he-IL' : 'en-US';
		document.documentElement.dir = state.lang === 'he' ? 'rtl' : 'ltr';
		document.body.classList.toggle('msl-page--he', state.lang === 'he');
		document.body.classList.toggle('msl-page--en', state.lang === 'en');

		$$('[data-msl-i18n]').forEach(function (node) {
			var value = dict[node.dataset.mslI18n];

			if (value === undefined) { return; }

			/* Sentences with a number in them keep their template on the node,
			   so the value can be re-interpolated rather than concatenated —
			   the number does not sit in the same place in both languages. */
			if (node.dataset.mslTemplate !== undefined) {
				node.dataset.mslTemplate = value;
				return;
			}

			node.textContent = value;
		});

		var toggle = $('[data-msl-lang-label]');

		if (toggle) {
			toggle.textContent = dict['chrome.lang_btn_' + state.lang] || (state.lang === 'he' ? 'EN' : 'עב');
		}

		writeCookie(config.cookies.lang, state.lang, 180);

		renderCounters();
		renderTemplates();
		renderReferral();
		renderCountdown();
		renderUrgency();
		renderHints();
		renderResult();
	}

	/* ------------------------------------------------------------------
	 * Counters
	 * --------------------------------------------------------------- */

	function renderCounters() {
		$$('[data-msl-counter]').forEach(function (node) { node.textContent = num(state.participants); });
		$$('[data-msl-counter-minus-one]').forEach(function (node) { node.textContent = num(Math.max(0, state.participants - 1)); });
		$$('[data-msl-pct]').forEach(function (node) { node.textContent = String(state.pct); });

		$$('[data-msl-progress]').forEach(function (node) {
			node.setAttribute('aria-valuenow', String(state.pct));
			var fill = node.firstElementChild;
			if (fill) { fill.style.width = state.pct + '%'; }
		});

		var summary = $('[data-msl-art-summary]');

		if (summary) {
			summary.textContent = format(t('screens.art_summary'), [num(state.participants), state.pct]);
		}

		canvasEngine.setState({ count: state.participants });
	}

	function renderTemplates() {
		var last10 = $('[data-msl-last10]');

		if (last10) {
			last10.textContent = format(last10.dataset.mslTemplate, [num(state.last10)]);
		}

		var mapSub = $('[data-msl-map-sub]');

		if (mapSub) {
			mapSub.textContent = format(mapSub.dataset.mslTemplate, [num(state.countries)]);
		}
	}

	function renderStat(selector, value) {
		$$(selector).forEach(function (node) { node.textContent = num(value); });
	}

	function applyStats(data) {
		state.participants = data.participants;
		state.pct = data.pct;

		state.last10 = data.last10;
		state.countries = data.countries;

		renderStat('[data-msl-countries]', data.countries);
		renderStat('[data-msl-cities]', data.cities);
		renderStat('[data-msl-dedications]', data.dedications);
		renderTemplates();
		renderCounters();
	}

	/*
	 * Between polls the counter walks up at the rate the last two polls
	 * observed, rather than sitting still and then jumping. That difference is
	 * the whole reason the page reads as live rather than as a page that
	 * refreshes.
	 */
	function interpolate() {
		if (state.rate <= 0 || document.hidden) { return; }

		var elapsed = (Date.now() - state.lastPoll) / 1000;
		var projected = Math.floor(state.expected + state.rate * elapsed);

		if (projected > state.participants) {
			state.participants = projected;
			renderCounters();
		}
	}

	function pollStats() {
		if (document.hidden) { return; }

		window.fetch(config.rest.stats, { credentials: 'same-origin' })
			.then(function (r) { return r.ok ? r.json() : null; })
			.then(function (data) {
				if (!data) { return; }

				var now = Date.now();

				if (state.lastPoll > 0) {
					var seconds = (now - state.lastPoll) / 1000;
					var gained = data.participants - state.expected;
					state.rate = seconds > 0 ? Math.max(0, gained / seconds) : 0;
				}

				state.expected = data.participants;
				state.lastPoll = now;

				/* Never walk the number backwards: the interpolation may have
				   run ahead of the poll, and a counter that ticks down is
				   worse than one that is briefly optimistic. */
				if (data.participants >= state.participants) {
					applyStats(data);
				} else {
					applyStats(Object.assign({}, data, { participants: state.participants }));
				}
			})
			.catch(function () { /* The seeded numbers stay on screen. */ });
	}

	function pollFeed() {
		if (document.hidden) { return; }

		window.fetch(config.rest.feed, { credentials: 'same-origin' })
			.then(function (r) { return r.ok ? r.json() : null; })
			.then(function (data) {
				if (!data || !data.rows || data.rows.length < 6) { return; }
				renderMarquee(data.rows.map(function (row) { return row.text; }));
			})
			.catch(function () { /* The seeded rows keep scrolling. */ });
	}

	function renderMarquee(rows) {
		var track = $('[data-msl-marquee]');

		if (!track) { return; }

		var halves = $$('.msl-marquee__half', track);

		halves.forEach(function (half, index) {
			half.textContent = '';

			rows.forEach(function (text) {
				var li = document.createElement('li');
				li.className = 'msl-marquee__item';

				var dot = document.createElement('span');
				dot.className = 'msl-marquee__dot';
				dot.setAttribute('aria-hidden', 'true');

				var label = document.createElement('span');
				label.textContent = text;

				li.appendChild(dot);
				li.appendChild(label);
				half.appendChild(li);
			});

			/* Only the first half is real content; the second exists so the
			   loop has something to scroll into. */
			if (index === 1) { half.setAttribute('aria-hidden', 'true'); }
		});
	}

	/* ------------------------------------------------------------------
	 * Countdown
	 * --------------------------------------------------------------- */

	function pad(n) {
		return String(n).padStart(2, '0');
	}

	function renderCountdown() {
		var node = $('[data-msl-countdown]');

		if (!node) { return; }

		var remaining = Math.max(0, config.campaign.candleLighting - Math.floor(Date.now() / 1000));
		var days = Math.floor(remaining / 86400);
		var parsha = t('campaign.parsha');

		if (days > 0) {
			node.textContent = format(t('chrome.countdown_days'), [parsha, num(days)]);
			return;
		}

		var clock = pad(Math.floor((remaining % 86400) / 3600)) + ':' + pad(Math.floor((remaining % 3600) / 60)) + ':' + pad(remaining % 60);
		node.textContent = format(t('chrome.countdown_clock'), [parsha, clock]);
	}

	function renderUrgency() {
		var node = $('[data-msl-urgency]');

		if (!node) { return; }

		if (config.campaign.closed) {
			node.textContent = t('closing.closed_note');
			return;
		}

		var hours = (config.campaign.candleLighting - Date.now() / 1000) / 3600;

		node.textContent = hours < 12
			? format(t('closing.urgency_soon'), [Math.max(1, Math.round(hours))])
			: t('closing.urgency_default');
	}

	/* ------------------------------------------------------------------
	 * Referral
	 * --------------------------------------------------------------- */

	function shareUrl() {
		return state.refCode ? config.joinBase + state.refCode + '/' : window.location.origin + '/';
	}

	function renderReferral() {
		var url = shareUrl();
		var shown = url.replace(/^https?:\/\//, '').replace(/\/$/, '');

		$$('[data-msl-link]').forEach(function (node) {
			node.dataset.mslUrl = url;
			node.textContent = shown;
		});

		$$('[data-msl-whatsapp]').forEach(function (node) {
			node.href = 'https://wa.me/?text=' + encodeURIComponent(format(node.dataset.mslTemplate || t('referral.wa_message'), [url]));
		});

		$$('[data-msl-refcount]').forEach(function (node) { node.textContent = num(state.refCount); });

		var next = state.nextMilestone || 0;

		$$('[data-msl-refnext]').forEach(function (node) {
			node.textContent = next > 0 ? format(node.dataset.mslTemplate || t('referral.next_goal'), [num(next)]) : '';
		});

		$$('[data-msl-refbar]').forEach(function (node) {
			var pct = next > 0 ? Math.min(100, Math.round((state.refCount / next) * 100)) : 0;
			node.setAttribute('aria-valuemax', String(Math.max(1, next)));
			node.setAttribute('aria-valuenow', String(state.refCount));
			var fill = node.firstElementChild;
			if (fill) { fill.style.width = pct + '%'; }
		});

		$$('[data-msl-milestone]').forEach(function (node) {
			node.classList.toggle('is-reached', state.refCount >= Number(node.dataset.mslMilestone));
		});
	}

	function pollReferral() {
		if (!state.refCode) { return; }

		window.fetch(config.rest.referral + '/' + state.refCode, { credentials: 'same-origin' })
			.then(function (r) { return r.ok ? r.json() : null; })
			.then(function (data) {
				if (!data) { return; }
				state.refCount = data.count;
				state.nextMilestone = data.next;
				renderReferral();
			})
			.catch(function () { /* Leave the last known count on screen. */ });
	}

	/* ------------------------------------------------------------------
	 * Screens
	 * --------------------------------------------------------------- */

	var lastFocus = null;

	function focusables(root) {
		return $$('a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])', root)
			.filter(function (node) {
				/* Not offsetParent: it is null for everything inside a
				   position:fixed ancestor, which is every overlay here. */
				return node.getClientRects().length > 0 || node === document.activeElement;
			});
	}

	/* Tab has to stay inside whatever is open. Without this the focus ring walks
	   off behind the overlay and the page looks broken to anyone not using a
	   mouse. */
	function trapFocus(event, root) {
		if (event.key !== 'Tab') { return; }

		var items = focusables(root);

		if (!items.length) { return; }

		var first = items[0];
		var last = items[items.length - 1];

		if (event.shiftKey && document.activeElement === first) {
			event.preventDefault();
			last.focus();
		} else if (!event.shiftKey && document.activeElement === last) {
			event.preventDefault();
			first.focus();
		}
	}

	function openOverlay(root) {
		lastFocus = document.activeElement;
		root.hidden = false;
		document.body.classList.add('is-locked');

		if (root.hasAttribute('data-msl-focus-self')) {
			root.setAttribute('tabindex', '-1');
			root.focus();
			return;
		}

		var first = focusables(root)[0];

		if (first) { first.focus(); }
	}

	function closeOverlay(root) {
		root.hidden = true;

		if (!$('.msl-screen:not([hidden])') && !$('.msl-modal:not([hidden])')) {
			document.body.classList.remove('is-locked');
		}

		if (lastFocus && lastFocus.isConnected) { lastFocus.focus(); }
	}

	function panel(name) {
		return $('[data-msl-screen-panel="' + name + '"]');
	}

	function goto(name) {
		var current = panel(state.screen);

		if (current) { closeOverlay(current); }

		if (name === 'home') {
			state.screen = 'home';
			document.body.dataset.mslScreen = 'home';
			canvasEngine.stopWow();
			return;
		}

		state.screen = name;
		document.body.dataset.mslScreen = name;

		var next = panel(name);

		if (next) { openOverlay(next); }

		if (name === 'wall') { canvasEngine.resetWall(); }
	}

	/* ------------------------------------------------------------------
	 * The artwork viewer
	 * --------------------------------------------------------------- */

	function renderHints() {
		var artHint = $('[data-msl-art-hint]');

		if (artHint) {
			artHint.textContent = state.artPick !== null
				? t('screens.art_hint_pick')
				: (canvasEngine.state.artZ === 0 ? t('screens.art_hint_zoom') : t('screens.art_hint_pan'));
		}

		var wallHint = $('[data-msl-wall-hint]');

		if (wallHint) {
			wallHint.textContent = state.wallPick !== null ? t('screens.wall_hint_pick') : t('screens.wall_hint');
		}

		var level = $('[data-msl-zoom-level]');

		if (level) { level.textContent = '×' + canvasEngine.zooms[canvasEngine.state.artZ]; }
	}

	/*
	 * Owner details come from the server, in windows rather than one request per
	 * click. A candle whose position predates the site has no record — the
	 * campaign's earlier participants were counted, not catalogued — so it shows
	 * no card at all rather than an invented name.
	 */
	function loadPieces(index, done) {
		var window_ = 100;
		var from = Math.max(0, Math.floor(index / window_) * window_);

		if (state.pieces[from]) {
			done(state.pieces[from][index] || null);
			return;
		}

		window.fetch(config.rest.pieces + '?from=' + from + '&to=' + (from + window_ - 1), { credentials: 'same-origin' })
			.then(function (r) { return r.ok ? r.json() : null; })
			.then(function (data) {
				state.pieces[from] = (data && data.pieces) || {};
				done(state.pieces[from][index] || null);
			})
			.catch(function () { done(null); });
	}

	function showPick(container, person) {
		if (!container) { return; }

		if (!person || (!person.name && !person.thing)) {
			container.hidden = true;
			return;
		}

		var name = $('[data-msl-pick-name]', container);
		var sub = $('[data-msl-pick-sub]', container);

		if (name) { name.textContent = person.name || ''; }

		if (sub) {
			sub.textContent = [person.place, person.thing].filter(Boolean).join(' · ');
		}

		container.hidden = false;
	}

	function bindArtView() {
		var cv = $('[data-msl-art-surface]');

		if (!cv) { return; }

		var drag = null;

		cv.addEventListener('pointerdown', function (event) {
			drag = {
				x: event.clientX,
				y: event.clientY,
				fx: canvasEngine.state.fx,
				fy: canvasEngine.state.fy,
				moved: 0
			};

			try { cv.setPointerCapture(event.pointerId); } catch (e) { /* not fatal */ }
		});

		cv.addEventListener('pointermove', function (event) {
			if (!drag) { return; }

			var rect = cv.getBoundingClientRect();
			var S = Math.min(rect.width, rect.height) * 0.94;
			var Z = canvasEngine.zooms[canvasEngine.state.artZ];
			var dx = event.clientX - drag.x;
			var dy = event.clientY - drag.y;

			drag.moved = Math.max(drag.moved, Math.hypot(dx, dy));

			canvasEngine.setState({
				fx: Math.max(0.04, Math.min(0.96, drag.fx - dx / (S * Z))),
				fy: Math.max(0.04, Math.min(0.96, drag.fy - dy / (S * Z)))
			});
		});

		var release = function (event) {
			var d = drag;
			drag = null;

			/* Six pixels of slop: a click that wandered slightly is still a
			   click, and a drag that ends on a candle must not select it. */
			if (!d || d.moved >= 6) { return; }

			var index = canvasEngine.artHitIndex(cv, event.clientX, event.clientY);

			if (index === null) {
				state.artPick = null;
				canvasEngine.setState({ artPick: null });
				showPick($('[data-msl-art-pick]'), null);
				renderHints();
				return;
			}

			if (state.artPick === index) {
				state.artPick = null;
				canvasEngine.setState({ artPick: null });
				showPick($('[data-msl-art-pick]'), null);
				renderHints();
				return;
			}

			state.artPick = index;
			canvasEngine.setState({
				artPick: index,
				fx: canvasEngine.state.fx,
				fy: canvasEngine.state.fy,
				artZ: canvasEngine.state.artZ === 0 ? 1 : canvasEngine.state.artZ
			});

			loadPieces(index, function (person) {
				showPick($('[data-msl-art-pick]'), person);
			});

			renderHints();
		};

		cv.addEventListener('pointerup', release);
		cv.addEventListener('pointercancel', function () { drag = null; });

		$$('[data-msl-zoom]').forEach(function (button) {
			button.addEventListener('click', function () {
				var z = canvasEngine.state.artZ;

				if (button.dataset.mslZoom === 'in') {
					canvasEngine.setState({ artZ: Math.min(canvasEngine.zooms.length - 1, z + 1) });
				} else {
					var next = Math.max(0, z - 1);
					canvasEngine.setState({ artZ: next, artPick: next === 0 ? null : canvasEngine.state.artPick });

					if (next === 0) {
						state.artPick = null;
						showPick($('[data-msl-art-pick]'), null);
					}
				}

				renderHints();
			});
		});
	}

	function bindWall() {
		var cv = $('[data-msl-wall-surface]');

		if (!cv) { return; }

		cv.addEventListener('click', function (event) {
			var index = canvasEngine.wallHitIndex(cv, event.clientX, event.clientY);

			if (state.wallPick === index) {
				state.wallPick = null;
				canvasEngine.setState({ wallPick: null });
				showPick($('[data-msl-wall-pick]'), null);
				renderHints();
				return;
			}

			state.wallPick = index;
			canvasEngine.setState({ wallPick: index });

			loadPieces(index, function (person) {
				showPick($('[data-msl-wall-pick]'), person);
			});

			renderHints();
		});
	}

	/* ------------------------------------------------------------------
	 * The join flow
	 * --------------------------------------------------------------- */

	var modal = $('[data-msl-modal="join"]');
	var form = $('[data-msl-join]');

	function chosen() {
		return $$('[data-msl-option]').filter(function (input) { return input.checked; });
	}

	function syncOptions() {
		var picked = chosen();
		var full = picked.length >= config.campaign.maxThings;
		var group = $('.msl-options');

		if (group) { group.classList.toggle('is-full', full); }

		/* Once three are chosen the rest are genuinely unavailable, so they are
		   disabled rather than left clickable and silently ignored. */
		$$('[data-msl-option]').forEach(function (input) {
			input.disabled = full && !input.checked;
		});

		var other = picked.some(function (input) { return input.dataset.mslOther === '1'; });
		var otherField = $('[data-msl-other-field]');

		if (otherField) { otherField.hidden = !other; }

		var next = $('[data-msl-next="2"]');

		if (next) { next.disabled = picked.length === 0; }
	}

	function setStep(step) {
		state.step = step;

		$$('[data-msl-step]').forEach(function (section) {
			section.hidden = Number(section.dataset.mslStep) !== step;
		});

		$$('.msl-steps__segment').forEach(function (segment, index) {
			segment.classList.toggle('is-done', index < step);
		});

		var first = $('[data-msl-next="2"]');
		var group = $('[data-msl-foot="2"]');
		var submit = $('[data-msl-submit]');

		if (first) { first.hidden = step !== 1; }
		if (group) { group.hidden = step !== 2; }
		if (submit) { submit.hidden = step !== 3; }

		var back = $('[data-msl-back]');

		if (back) { back.hidden = step === 1; }

		var heading = $('[data-msl-step="' + step + '"] .msl-step__title');
		var sheet = $('[data-msl-sheet]');

		if (heading) {
			if (!heading.id) { heading.id = 'msl-join-title-' + step; }
			if (sheet) { sheet.setAttribute('aria-labelledby', heading.id); }

			heading.setAttribute('tabindex', '-1');
			heading.focus();
		}
	}

	function openJoin() {
		if (!modal) { return; }

		state.openedAt = Date.now();
		syncOptions();
		clearErrors();
		openOverlay(modal);

		/* After openOverlay, not before: opening the dialog moves focus to its
		   first control, and the step heading is the better landing place —
		   it is what tells a screen reader what this dialog is asking. */
		setStep(1);
	}

	function closeJoin() {
		if (modal) { closeOverlay(modal); }
	}

	function clearErrors() {
		$$('.msl-field__error').forEach(function (node) { node.textContent = ''; });
		$$('.msl-input').forEach(function (node) { node.removeAttribute('aria-invalid'); });

		var summary = $('[data-msl-form-error]');

		if (summary) { summary.textContent = ''; }
	}

	function showFieldError(id, message) {
		var field = document.getElementById(id);
		var target = document.getElementById('msl-error-' + id.replace('msl-', ''));

		if (target) { target.textContent = message; }

		if (field) {
			field.setAttribute('aria-invalid', 'true');
			field.focus();
		}
	}

	function validate() {
		clearErrors();

		var name = $('#msl-first-name');
		var city = $('#msl-city');
		var email = $('#msl-email');
		var phone = $('#msl-phone');

		if (!name.value.trim()) {
			showFieldError('msl-first-name', t('join.err_name'));
			return false;
		}

		if (!city.value.trim()) {
			showFieldError('msl-city', t('join.err_city'));
			return false;
		}

		if (email.value.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email.value.trim())) {
			showFieldError('msl-email', t('join.err_email'));
			return false;
		}

		if (phone.value.trim() && phone.value.replace(/\D+/g, '').length < 9) {
			showFieldError('msl-phone', t('join.err_phone'));
			return false;
		}

		return true;
	}

	function collect(nonce) {
		var dedication = $$('[data-msl-dedication]').filter(function (input) { return input.checked; })[0];

		return {
			things: chosen().map(function (input) { return Number(input.value); }),
			custom_label: ($('#msl-custom-label') || {}).value || '',
			first_name: $('#msl-first-name').value.trim(),
			city: $('#msl-city').value.trim(),
			country: $('#msl-country').value.trim(),
			email: $('#msl-email').value.trim(),
			phone: $('#msl-phone').value.trim(),
			is_anonymous: $('#msl-anon').checked ? 1 : 0,
			dedication: dedication ? Number(dedication.value) : null,
			dedication_body: $('#msl-dedication-body').value.trim(),
			lang: state.lang,
			referred_by: readCookie(config.cookies.ref),
			hp: $('#msl-hp').value,
			elapsed: (Date.now() - state.openedAt) / 1000,
			nonce: nonce
		};
	}

	function submit(event) {
		event.preventDefault();

		if (!validate()) { return; }

		var button = $('[data-msl-submit]');
		var label = button.textContent;

		button.disabled = true;
		button.textContent = button.dataset.mslSending || label;

		/* The nonce is fetched here rather than printed into the page: a nonce
		   in the HTML would make the whole page uncacheable, and would go stale
		   in a cache within the day. */
		window.fetch(config.rest.nonce, { credentials: 'same-origin', cache: 'no-store' })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				return window.fetch(config.rest.join, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify(collect(data.nonce))
				});
			})
			.then(function (response) {
				return response.json().then(function (body) {
					return { ok: response.ok, body: body };
				});
			})
			.then(function (result) {
				button.disabled = false;
				button.textContent = label;

				if (!result.ok) {
					var summary = $('[data-msl-form-error]');
					var message = result.body && result.body.message ? result.body.message[state.lang] : t('join.err_generic');

					if (summary) { summary.textContent = message || t('join.err_generic'); }

					return;
				}

				onJoined(result.body);
			})
			.catch(function () {
				button.disabled = false;
				button.textContent = label;

				var summary = $('[data-msl-form-error]');

				if (summary) { summary.textContent = t('join.err_generic'); }
			});
	}

	function onJoined(result) {
		state.result = result;
		state.refCode = result.referral_code;
		state.refCount = result.referral_count || 0;
		state.nextMilestone = result.next_milestone || 0;
		state.participants = result.participants;
		state.expected = result.participants;
		state.lastPoll = Date.now();

		writeCookie(config.cookies.mine, result.referral_code, config.cookies.refDays);

		closeJoin();
		renderReferral();
		renderResult();
		goto('wow');

		canvasEngine.setState({ count: result.participants - 1 });
		canvasEngine.startWow({
			count: function () {
				state.participants = result.participants;
				canvasEngine.setState({ count: result.participants });
				renderCounters();

				var counter = $('[data-msl-wow-count]');
				if (counter) { counter.classList.add('is-shown'); }
			},
			text: function () {
				var text = $('[data-msl-wow-text]');
				if (text) { text.classList.add('is-shown'); }

				var cta = $('[data-msl-goto="result"]');
				if (cta) { cta.focus(); }
			}
		});
	}

	function renderResult() {
		var thing = $('[data-msl-my-thing]');

		if (thing) {
			var labels = chosen().map(function (input) {
				if (input.dataset.mslOther === '1') {
					var custom = ($('#msl-custom-label') || {}).value || '';
					return custom.trim() || $('.msl-option__text', input.parentNode).textContent;
				}

				return $('.msl-option__text', input.parentNode).textContent;
			});

			thing.textContent = labels.join(' · ');
		}

		var dedication = $('[data-msl-my-dedication]');

		if (dedication) {
			var kind = $$('[data-msl-dedication]').filter(function (input) { return input.checked; })[0];
			var body = ($('#msl-dedication-body') || {}).value || '';
			var line = [kind ? $('label[for="' + kind.id + '"]').textContent.trim() : '', body.trim()].filter(Boolean).join(' ');

			dedication.textContent = line;
			dedication.hidden = line === '';
		}
	}

	/* ------------------------------------------------------------------
	 * Sharing
	 * --------------------------------------------------------------- */

	function bindShare() {
		$$('[data-msl-copy]').forEach(function (button) {
			button.addEventListener('click', function () {
				var url = shareUrl();
				var label = button.textContent;
				var done = function () {
					button.textContent = button.dataset.mslCopied || label;
					window.setTimeout(function () { button.textContent = label; }, 1800);
				};

				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(url).then(done, done);
					return;
				}

				/* Older Safari and any non-secure context: a hidden field and
				   the old command, rather than no copy button at all. */
				var field = document.createElement('input');
				field.value = url;
				field.setAttribute('aria-hidden', 'true');
				field.style.position = 'fixed';
				field.style.opacity = '0';
				document.body.appendChild(field);
				field.select();

				try { document.execCommand('copy'); } catch (e) { /* nothing more to try */ }

				document.body.removeChild(field);
				done();
			});
		});

		$$('[data-msl-share]').forEach(function (button) {
			button.addEventListener('click', function () {
				var url = shareUrl();

				if (navigator.share) {
					navigator.share({ title: t('chrome.brand'), text: t('closing.title'), url: url }).catch(function () { /* dismissed */ });
					return;
				}

				/* No native sheet: the copy button is the honest fallback, so
				   press it rather than opening a window nobody asked for. */
				var copy = $('[data-msl-copy]');

				if (copy) { copy.click(); }
			});
		});
	}

	/* ------------------------------------------------------------------
	 * The collage
	 * --------------------------------------------------------------- */

	/*
	 * One tile at a time fades out, swaps to its other layer and fades back, so
	 * a pool larger than eight images cycles through the positions over the
	 * course of a visit. It stops when the page is not on screen — there is no
	 * reason to keep animating behind an overlay.
	 */
	function startCollage() {
		var collage = $('[data-msl-collage]');

		if (!collage || reduceMotion) { return; }

		var tiles = $$('[data-msl-tile]', collage);

		if (tiles.length === 0) { return; }

		window.setInterval(function () {
			if (document.hidden || state.screen !== 'home') { return; }

			var tile = tiles[Math.floor(Math.random() * tiles.length)];

			tile.classList.add('is-swapping');

			window.setTimeout(function () {
				var layers = $$('.msl-collage__layer', tile);

				layers.forEach(function (layer) { layer.classList.toggle('is-on'); });
				tile.classList.remove('is-swapping');
			}, 720);
		}, 3200);
	}

	/* ------------------------------------------------------------------
	 * Wiring
	 * --------------------------------------------------------------- */

	function bindEvents() {
		$$('[data-msl-open-join]').forEach(function (button) {
			button.addEventListener('click', function () {
				if (config.campaign.closed) { return; }
				openJoin();
			});
		});

		$$('[data-msl-dismiss]').forEach(function (node) {
			node.addEventListener('click', closeJoin);
		});

		$$('[data-msl-goto]').forEach(function (button) {
			button.addEventListener('click', function () { goto(button.dataset.mslGoto); });
		});

		$$('[data-msl-option]').forEach(function (input) {
			input.addEventListener('change', syncOptions);
		});

		$$('[data-msl-next]').forEach(function (button) {
			button.addEventListener('click', function () { setStep(Number(button.dataset.mslNext)); });
		});

		var back = $('[data-msl-back]');

		if (back) {
			back.addEventListener('click', function () { setStep(Math.max(1, state.step - 1)); });
		}

		var skip = $('[data-msl-skip]');

		if (skip) {
			skip.addEventListener('click', function () { setStep(3); });
		}

		if (form) { form.addEventListener('submit', submit); }

		var langToggle = $('[data-msl-lang-toggle]');

		if (langToggle) {
			langToggle.addEventListener('click', function () {
				state.lang = state.lang === 'he' ? 'en' : 'he';
				applyLanguage();
			});
		}

		/* Escape closes whatever is open, innermost first. */
		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				if (modal && !modal.hidden) { closeJoin(); return; }
				if (state.screen !== 'home') { goto('home'); }
				return;
			}

			if (modal && !modal.hidden) { trapFocus(event, modal); return; }

			var open = panel(state.screen);

			if (open && !open.hidden) { trapFocus(event, open); }
		});

		document.addEventListener('visibilitychange', function () {
			if (document.hidden) { return; }

			/* Coming back to the tab should feel like coming back to something
			   that kept happening, so both feeds refresh at once. */
			pollStats();
			pollFeed();
		});
	}

	/* ------------------------------------------------------------------
	 * Boot
	 * --------------------------------------------------------------- */

	function boot() {
		document.body.dataset.mslScreen = 'home';

		state.expected = state.participants;
		state.lastPoll = Date.now();

		/* An invite link puts the inviter's code in the address; keep it in a
		   first-party cookie so the attribution survives the whole flow. */
		var match = window.location.pathname.match(/\/join\/([A-Za-z0-9]{6,12})\/?$/);

		if (match) {
			writeCookie(config.cookies.ref, match[1].toLowerCase(), config.cookies.refDays);
		}

		state.refCode = readCookie(config.cookies.mine);

		canvasEngine.init({
			target: config.campaign.target,
			count: state.participants,
			accent: config.campaign.accent,
			artwork: config.campaign.artwork,
			mapData: config.mapData,
			mapPoints: config.mapPoints,
			still: reduceMotion
		});

		document.documentElement.style.setProperty('--msl-flame', config.campaign.accent);

		bindEvents();
		bindArtView();
		bindWall();
		bindShare();
		startCollage();

		syncOptions();
		renderCounters();
		renderReferral();
		renderHints();
		renderCountdown();
		renderUrgency();

		if (state.refCode) { pollReferral(); }

		window.setInterval(renderCountdown, 1000);
		window.setInterval(renderUrgency, 60000);
		window.setInterval(pollStats, 8000);
		window.setInterval(pollFeed, 15000);
		window.setInterval(interpolate, 1000);
		window.setInterval(pollReferral, 30000);

		pollStats();
		pollFeed();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
}());
