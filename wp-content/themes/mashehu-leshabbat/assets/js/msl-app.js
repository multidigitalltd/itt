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
		myPiece: -1,
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

	/*
	 * An editor can close the campaign while a visitor is sitting on the page —
	 * or the page may have come from a cache that predates the closure. Letting
	 * them walk through all three steps only to be refused at the end is the
	 * worst possible way to tell them, so the entry points go the moment /stats
	 * says so.
	 */
	function renderClosed() {
		$$('[data-msl-open-join]').forEach(function (button) {
			button.hidden = config.campaign.closed;
		});

		if (config.campaign.closed && modal && !modal.hidden) { closeJoin(); }

		renderUrgency();
	}

	function applyStats(data) {
		state.participants = data.participants;
		state.pct = data.pct;

		if (typeof data.closed === 'boolean' && data.closed !== config.campaign.closed) {
			config.campaign.closed = data.closed;
			renderClosed();
		}

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
		/* A signed-in person's link is theirs for good and the server already
		   rendered it; a code minted by this session's join comes next; failing
		   both, the campaign's own address, which is still worth sharing. */
		if (config.auth && config.auth.link) { return config.auth.link; }

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

				/* A device that kept the code but lost the position gets it
				   back here, which is what makes "my candle" survive a cleared
				   cookie jar or a second browser. */
				if (typeof data.piece === 'number' && data.piece >= 0 && state.myPiece < 0) {
					state.myPiece = data.piece;
					writeCookie(config.cookies.piece, String(data.piece), config.cookies.refDays);
					renderMyCandle();
				}
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
	 * The artwork and the wall draw a fixed number of candles at any count: one
	 * drawn candle stands for a slice of the participants, not for one of them.
	 * So a click resolves to the *range* of people behind that candle, which is
	 * also the only way a named participant stays reachable once the campaign
	 * has passed six figures and the wall is still four hundred candles wide.
	 */
	function pieceWindow(index, drawn) {
		var people = Math.max(1, canvasEngine.state.count || 0);
		var span = Math.max(1, drawn);
		var from = Math.max(0, Math.floor(index * people / span));
		var to = Math.max(from, Math.floor((index + 1) * people / span) - 1);

		/* Trim a wide slice from its older end, not its newer one: the people
		   with a name on file are the ones who joined most recently, and
		   clipping the top of the range would hide every one of them. */
		return { from: Math.max(from, to - 499), to: to };
	}

	/* A small integer hash, so a position always resolves to the same name
	   without an array the size of the campaign. */
	function hashAt(n, salt) {
		return (Math.imul(n + salt * 131, 2654435761) >>> 9) % 100000;
	}

	function lines(text) {
		return String(text || '').split('\n').map(function (line) {
			return line.trim();
		}).filter(Boolean);
	}

	/* The labels a participant can pick, read off the join form rather than
	   shipped twice: the form already has them in the current language. */
	function thingLabels() {
		return $$('.msl-option__text').map(function (el) {
			return el.textContent.trim();
		}).filter(Boolean);
	}

	/*
	 * A stand-in for a candle with no record.
	 *
	 * The count this campaign arrived with was counted, not catalogued, so most
	 * candles have no name on file. The campaign would rather show a name than
	 * an apology, so one is derived from the position: the same candle always
	 * shows the same person, the lists are editable in the content panel, and a
	 * real join in the slice always wins over this. Turning the switch off in
	 * section 03 puts the honest card back.
	 */
	function standIn(ordinal) {
		if (!config.campaign.demoNames) { return null; }

		var names = lines(t('stage.demo_first_names'));

		if (!names.length) { return null; }

		var cities = lines(t('stage.demo_cities'));
		var things = thingLabels();

		return {
			name: names[hashAt(ordinal, 7) % names.length],
			place: cities.length ? cities[hashAt(ordinal, 13) % cities.length] : '',
			thing: things.length ? things[hashAt(ordinal, 29) % things.length] : ''
		};
	}

	/* A named participant in the slice is the one worth showing; failing that,
	   any record at all; failing that, none, and the card says so. */
	function pickFrom(pieces) {
		var keys = pieces ? Object.keys(pieces) : [];
		var i;

		for (i = 0; i < keys.length; i++) {
			if (pieces[keys[i]] && pieces[keys[i]].name) { return pieces[keys[i]]; }
		}

		return keys.length ? pieces[keys[0]] : null;
	}

	/*
	 * Owner details come from the server, one request per slice, cached so that
	 * clicking back and forth between two candles is not two requests each time.
	 */
	function loadPieces(win, done) {
		var key = win.from + ':' + win.to;

		if (Object.prototype.hasOwnProperty.call(state.pieces, key)) {
			done(state.pieces[key]);
			return;
		}

		window.fetch(config.rest.pieces + '?from=' + win.from + '&to=' + win.to, { credentials: 'same-origin' })
			.then(function (r) { return r.ok ? r.json() : null; })
			.then(function (data) {
				state.pieces[key] = pickFrom(data && data.pieces) || standIn(win.from);
				done(state.pieces[key]);
			})
			.catch(function () { done(standIn(win.from)); });
	}

	function hidePick(container) {
		if (container) { container.hidden = true; }
	}

	/*
	 * Every lit candle belongs to somebody, but not every one of them has a name
	 * on file: the count the campaign arrived with was counted rather than
	 * catalogued, and participants may also choose to stay unnamed. Those
	 * candles still answer the click — a click that does nothing reads as broken,
	 * which is exactly what an empty card looked like.
	 */
	function showPick(container, person) {
		if (!container) { return; }

		var name = $('[data-msl-pick-name]', container);
		var sub = $('[data-msl-pick-sub]', container);
		var label;
		var detail;

		if (person && person.name) {
			label = person.name;
			detail = [person.place, person.thing].filter(Boolean).join(' · ');
		} else if (person) {
			label = t('screens.pick_anon');
			detail = person.thing || t('screens.pick_anon_sub');
		} else {
			label = t('screens.pick_none');
			detail = t('screens.pick_none_sub');
		}

		if (name) { name.textContent = label; }
		if (sub) { sub.textContent = detail; }

		container.hidden = false;
	}

	/*
	 * "My candle".
	 *
	 * The artwork draws a fixed number of candles whatever the count, so a
	 * participant's position has to be mapped onto the cell that stands for it —
	 * the same slice arithmetic pieceWindow() does, run the other way. Then the
	 * camera goes to that cell, the zoom goes in far enough for one candle to be
	 * a candle, and the card opens on it.
	 */
	function myCell() {
		if (state.myPiece < 0) { return -1; }

		var drawn = canvasEngine.litCount();
		var people = Math.max(1, canvasEngine.state.count || 0);

		if (drawn < 1) { return -1; }

		return Math.max(0, Math.min(drawn - 1, Math.floor(state.myPiece * drawn / people)));
	}

	function renderMyCandle() {
		var button = $('[data-msl-my-candle]');

		if (button) { button.hidden = state.myPiece < 0; }
	}

	function showMyCandle() {
		var index = myCell();
		var cell = index < 0 ? null : canvasEngine.cellAt(index);

		if (!cell) { return; }

		state.artPick = index;
		canvasEngine.setState({
			artPick: index,
			fx: cell.nx,
			fy: cell.ny,
			artZ: Math.max(2, canvasEngine.state.artZ)
		});

		/* The visitor's own record is the one that must never be a stand-in, so
		   this asks for their exact position rather than their slice. */
		loadPieces({ from: state.myPiece, to: state.myPiece }, function (person) {
			showPick($('[data-msl-art-pick]'), person);
		});

		renderHints();
	}

	function bindMyCandle() {
		var button = $('[data-msl-my-candle]');

		if (!button) { return; }

		button.addEventListener('click', function () {
			goto('art');
			showMyCandle();
		});

		state.myPiece = parseInt(readCookie(config.cookies.piece), 10);

		if (isNaN(state.myPiece)) { state.myPiece = -1; }

		renderMyCandle();
	}

	/*
	 * Pointer gestures on a canvas.
	 *
	 * One pointer drags, two pinch, and a press that barely moved is a tap. It
	 * is written once because the artwork and the wall want exactly the same
	 * behaviour and a phone has no zoom buttons worth hitting: a person's first
	 * instinct on a picture is to pinch it.
	 *
	 * Deltas are incremental rather than measured from where the gesture began,
	 * so a second finger arriving or leaving mid-gesture does not make the view
	 * jump.
	 */
	function bindGestures(cv, on) {
		var points = {};
		var last = null;
		var moved = 0;
		var pinching = false;

		var list = function () {
			return Object.keys(points).map(function (id) { return points[id]; });
		};

		var centre = function (ps) {
			return {
				x: (ps[0].x + ps[1].x) / 2,
				y: (ps[0].y + ps[1].y) / 2,
				d: Math.hypot(ps[0].x - ps[1].x, ps[0].y - ps[1].y)
			};
		};

		var reset = function () {
			var ps = list();

			last = ps.length === 2 ? centre(ps) : (ps.length === 1 ? { x: ps[0].x, y: ps[0].y, d: 0 } : null);
			pinching = ps.length === 2;
		};

		cv.addEventListener('pointerdown', function (event) {
			points[event.pointerId] = { x: event.clientX, y: event.clientY };

			if (Object.keys(points).length === 1) { moved = 0; }

			reset();

			try { cv.setPointerCapture(event.pointerId); } catch (e) { /* not fatal */ }
		});

		cv.addEventListener('pointermove', function (event) {
			if (!points[event.pointerId]) { return; }

			points[event.pointerId] = { x: event.clientX, y: event.clientY };

			var ps = list();

			if (!last) { return; }

			if (ps.length >= 2) {
				var now = centre(ps);

				if (last.d > 0 && now.d > 0 && on.zoom) { on.zoom(now.d / last.d, now.x, now.y); }

				if (on.pan) { on.pan(now.x - last.x, now.y - last.y); }

				moved = 999;
				last = now;

				// Two fingers on a canvas is a pinch, never a page scroll.
				event.preventDefault();

				return;
			}

			var dx = ps[0].x - last.x;
			var dy = ps[0].y - last.y;

			moved += Math.hypot(dx, dy);

			if (on.pan) { on.pan(dx, dy); }

			last = { x: ps[0].x, y: ps[0].y, d: 0 };

			if (moved > 6) { event.preventDefault(); }
		});

		var end = function (event) {
			var wasPinching = pinching;
			var had = Object.keys(points).length;

			delete points[event.pointerId];
			reset();

			if (had > 1 || wasPinching) { return; }

			/* Six pixels of slop: a press that wandered slightly is still a tap,
			   and a drag that ends on a candle must not select it. */
			if (moved < 6 && on.tap) { on.tap(event.clientX, event.clientY); }
		};

		cv.addEventListener('pointerup', end);
		cv.addEventListener('pointercancel', function (event) {
			delete points[event.pointerId];
			reset();
		});

		/* The browser's own panning would fight every one of these. */
		cv.style.touchAction = 'none';
	}

	function bindArtView() {
		var cv = $('[data-msl-art-surface]');

		if (!cv) { return; }

		var selectAt = function (clientX, clientY) {
			var index = canvasEngine.artHitIndex(cv, clientX, clientY);

			if (index === null || state.artPick === index) {
				state.artPick = null;
				canvasEngine.setState({ artPick: null });
				hidePick($('[data-msl-art-pick]'));
				renderHints();
				return;
			}

			state.artPick = index;
			canvasEngine.setState({
				artPick: index,
				artZ: canvasEngine.state.artZ === 0 ? 1 : canvasEngine.state.artZ
			});

			loadPieces(pieceWindow(index, canvasEngine.litCount()), function (person) {
				if (state.artPick === index) { showPick($('[data-msl-art-pick]'), person); }
			});

			renderHints();
		};

		bindGestures(cv, {
			pan: function (dx, dy) {
				var rect = cv.getBoundingClientRect();
				var S = Math.min(rect.width, rect.height) * 0.94;
				var Z = canvasEngine.state.artZoom || 1;

				canvasEngine.setState({
					fx: Math.max(0.04, Math.min(0.96, canvasEngine.state.fx - dx / (S * Z))),
					fy: Math.max(0.04, Math.min(0.96, canvasEngine.state.fy - dy / (S * Z)))
				});
			},
			zoom: function (factor) {
				var steps = canvasEngine.zooms;
				var next = (canvasEngine.state.artZoom || 1) * factor;

				canvasEngine.setState({
					artZoom: Math.max(steps[0], Math.min(steps[steps.length - 1], next))
				});

				renderHints();
			},
			tap: selectAt
		});

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
						hidePick($('[data-msl-art-pick]'));
					}
				}

				renderHints();
			});
		});
	}

	function bindWall() {
		var cv = $('[data-msl-wall-surface]');

		if (!cv) { return; }

		var selectAt = function (clientX, clientY) {
			var index = canvasEngine.wallHitIndex(cv, clientX, clientY);

			if (index === null || state.wallPick === index) {
				state.wallPick = null;
				canvasEngine.setState({ wallPick: null });
				hidePick($('[data-msl-wall-pick]'));
				renderHints();
				return;
			}

			state.wallPick = index;
			canvasEngine.setState({ wallPick: index });

			loadPieces(pieceWindow(index, canvasEngine.wallLitCount(cv)), function (person) {
				if (state.wallPick === index) { showPick($('[data-msl-wall-pick]'), person); }
			});

			renderHints();
		};

		/* The wall pans only once there is somewhere to pan to, so at rest a
		   finger dragged across it still scrolls the screen behind it. */
		bindGestures(cv, {
			pan: function (dx, dy) {
				if ((canvasEngine.state.wallZoom || 1) > 1) { canvasEngine.panWall(cv, dx, dy); }
			},
			zoom: function (factor, cx, cy) {
				canvasEngine.wallZoomAt(cv, (canvasEngine.state.wallZoom || 1) * factor, cx, cy);
				renderHints();
			},
			tap: selectAt
		});

		$$('[data-msl-wall-zoom]').forEach(function (button) {
			button.addEventListener('click', function () {
				var rect = cv.getBoundingClientRect();
				var now = canvasEngine.state.wallZoom || 1;
				var next = button.dataset.mslWallZoom === 'in' ? now * 1.8 : now / 1.8;

				canvasEngine.wallZoomAt(cv, next, rect.left + rect.width / 2, rect.top + rect.height / 2);

				if (next <= 1) {
					state.wallPick = null;
					canvasEngine.setState({ wallPick: null, wallZoom: 1, wallPanX: 0, wallPanY: 0 });
					hidePick($('[data-msl-wall-pick]'));
				}

				renderHints();
			});
		});
	}


	/* ------------------------------------------------------------------
	 * The invitation
	 * --------------------------------------------------------------- */

	/*
	 * The campaign's ask: carry this to the people you know. It opens once, a
	 * little after the page has settled, and closing it is remembered — a popup
	 * that returns on every load is a popup people learn to dismiss without
	 * reading, which costs the campaign the one thing it was for.
	 *
	 * It never opens on top of something else the visitor is already doing.
	 */
	var inviteModal = $('[data-msl-modal="invite"]');
	var inviteReturn = null;

	function inviteSeenKey() {
		return 'msl_invite_seen';
	}

	function inviteDismissed() {
		var until = 0;

		try { until = parseInt(window.localStorage.getItem(inviteSeenKey()) || '0', 10); } catch (e) { until = 0; }

		return !isNaN(until) && until > Date.now();
	}

	function rememberInviteDismissed() {
		var days = (config.auth && config.auth.days) || 0;

		if (days < 1) { return; }

		try {
			window.localStorage.setItem(inviteSeenKey(), String(Date.now() + days * 86400000));
		} catch (e) { /* A browser that refuses storage just sees it again. */ }
	}

	function openInvite() {
		if (!inviteModal || !inviteModal.hidden) { return; }

		inviteReturn = document.activeElement;
		inviteModal.hidden = false;
		document.body.classList.add('msl-locked');
		renderReferral();

		var focusable = $('.msl-btn, .msl-invite__close', inviteModal);

		if (focusable) { focusable.focus(); }
	}

	function closeInvite() {
		if (!inviteModal || inviteModal.hidden) { return; }

		inviteModal.hidden = true;
		document.body.classList.remove('msl-locked');
		rememberInviteDismissed();

		if (inviteReturn && inviteReturn.focus) { inviteReturn.focus(); }

		inviteReturn = null;
	}

	/* The account menu. A plain disclosure: it closes on Escape, on a click
	   anywhere else, and when the control itself is pressed again. */
	function bindAccount() {
		var wrap = $('[data-msl-account]');

		if (!wrap) { return; }

		var toggle = $('[data-msl-account-toggle]', wrap);
		var menu = $('.msl-account__menu', wrap);

		if (!toggle || !menu) { return; }

		var setOpen = function (open) {
			menu.hidden = !open;
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		};

		toggle.addEventListener('click', function (event) {
			event.stopPropagation();
			setOpen(menu.hidden);
		});

		document.addEventListener('click', function (event) {
			if (!wrap.contains(event.target)) { setOpen(false); }
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') { setOpen(false); }
		});
	}

	/* The site menu. On a phone it is the only way to reach the rest of the
	   page, so it closes on a choice as well as on Escape and on a click away —
	   an anchor that scrolls behind an open menu is a menu nobody closed. */
	function bindMenu() {
		var wrap = $('[data-msl-menu]');

		if (!wrap) { return; }

		var toggle = $('[data-msl-menu-toggle]', wrap);
		var list = $('.msl-menu__list', wrap);

		if (!toggle || !list) { return; }

		var setOpen = function (open) {
			list.hidden = !open;
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			wrap.classList.toggle('is-open', open);
		};

		toggle.addEventListener('click', function (event) {
			event.stopPropagation();
			setOpen(list.hidden);
		});

		$$('.msl-menu__link', wrap).forEach(function (link) {
			link.addEventListener('click', function () { setOpen(false); });
		});

		document.addEventListener('click', function (event) {
			if (!wrap.contains(event.target)) { setOpen(false); }
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') { setOpen(false); }
		});
	}

	function bindInvite() {
		if (!inviteModal) { return; }

		$$('[data-msl-close-invite]').forEach(function (node) {
			node.addEventListener('click', closeInvite);
		});

		$$('[data-msl-open-invite]').forEach(function (node) {
			node.addEventListener('click', function () { openInvite(); });
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && !inviteModal.hidden) { closeInvite(); }
		});

		if (inviteDismissed()) { return; }

		var delay = (config.auth && config.auth.delay) || 0;

		window.setTimeout(function () {
			/* Not over the join form, not over a full-screen panel, and not
			   while the visitor is reading something further down the page
			   that they went looking for. */
			if (state.screen !== 'home') { return; }

			var join = $('[data-msl-modal="join"]');

			if (join && !join.hidden) { return; }

			openInvite();
		}, Math.max(0, delay) * 1000);
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

		/* The position is what "my candle" flies to. The referral code can
		   recover it from the server later, but on this device it is known now
		   and a cookie saves the round trip on every later visit. */
		if (typeof result.piece_index === 'number') {
			state.myPiece = result.piece_index;
			writeCookie(config.cookies.piece, String(result.piece_index), config.cookies.refDays);
			renderMyCandle();
		}

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
	/*
	 * Keep the candle field moving.
	 *
	 * The lighting and the burning out are CSS; this only decides where the
	 * next one appears. A candle is moved at the moment its cycle restarts,
	 * which is the one moment it is invisible — moving a lit candle would read
	 * as a candle sliding across the page.
	 *
	 * Both gutters are fed in turn so neither is ever left dark by chance, and
	 * the middle third is never used: the headline lives there.
	 */
	function startEmbers() {
		var embers = $$('[data-msl-ember]');

		if (!embers.length || reduceMotion) { return; }

		var flip = 0;

		embers.forEach(function (ember) {
			ember.addEventListener('animationiteration', function () {
				var near = (flip++ % 2) === 0;
				var x = near ? 1 + Math.random() * 21 : 78 + Math.random() * 21;

				ember.style.setProperty('--msl-ember-x', x.toFixed(1) + '%');
				ember.style.setProperty('--msl-ember-y', (4 + Math.random() * 88).toFixed(1) + '%');
				ember.style.setProperty('--msl-ember-rot', (Math.random() * 17 - 8.5).toFixed(1) + 'deg');
				ember.style.setProperty('--msl-ember-scale', (0.52 + Math.random() * 0.6).toFixed(2));
			});
		});
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

		/*
		 * The server renders Hebrew so the HTML stays identical for every
		 * visitor and a full-page cache cannot hand one visitor's language to
		 * the next. Applying the stored preference is therefore this side's job:
		 * `?lang=` first (it is also honoured server-side, so this only matters
		 * when a cache strips query strings), then the cookie.
		 */
		var requested = new URLSearchParams(window.location.search).get('lang');
		var preferred = config.langs.indexOf(requested) !== -1 ? requested : readCookie(config.cookies.lang);

		if (config.langs.indexOf(preferred) !== -1 && preferred !== state.lang) {
			state.lang = preferred;
		}

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
		bindMyCandle();
		bindWall();
		bindShare();
		bindMenu();
		bindAccount();
		bindInvite();
		startEmbers();

		syncOptions();
		applyLanguage();
		renderClosed();

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
