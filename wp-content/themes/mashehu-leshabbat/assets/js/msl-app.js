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
		/* A group page's own tally. Separate from `participants` on purpose:
		   the two are different truths about the same candles, and merging
		   them would make one of the numbers on screen a lie. */
		groupCount: config.group ? config.group.count : 0,
		pct: config.stats.pct,
		/* What the server last said. `participants` is what is on screen and
		   walks towards it; the two differ only for about a second after a
		   poll. Everything anybody sees is therefore a number the server
		   issued, which is what makes two people looking together agree. */
		target: config.stats.participants,
		refCode: '',
		refCount: 0,
		nextMilestone: 0,
		result: null,
		openedAt: 0,
		artPick: null,
		myPiece: -1,
		wallPick: null,
		mapPick: null,
		last10: config.stats.last10,
		countries: config.stats.countries,
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

	/* The same lookup in a named language, for the few strings that have to be
	   written into the page in both at once — values that came from an API and
	   have no dictionary entry of their own to be swapped for. */
	function tIn(lang, key) {
		var value = (config.i18n[lang] || {})[key];
		return value === undefined ? '' : value;
	}

	/* Nodes carrying a copy key for an attribute rather than for their text.
	   Adding a fourth kind means adding it here; the loop itself is generic. */
	var ATTR_I18N = '[data-msl-template-i18n], [data-msl-anon-i18n], [data-msl-first-i18n]';

	function applyLanguage() {
		var dict = dictionary();

		document.documentElement.lang = state.lang === 'he' ? 'he-IL' : 'en-US';
		document.documentElement.dir = state.lang === 'he' ? 'rtl' : 'ltr';
		document.body.classList.toggle('msl-page--he', state.lang === 'he');
		document.body.classList.toggle('msl-page--en', state.lang === 'en');

		/*
		 * Values that live in an attribute rather than in the text: a message
		 * template, the two alternative sentences on the invitation card. Each
		 * one names its own copy key in `data-msl-<name>-i18n`, and that is the
		 * whole point of it existing.
		 *
		 * Without it the rule below had to guess, and it guessed that a node
		 * with a template holds that template as its own text — true of "%d
		 * people in the last ten minutes", false of the WhatsApp button, whose
		 * text is "Send on WhatsApp" and whose template is the message being
		 * sent. So every language pass overwrote the message with the button's
		 * own label, and the link the message existed to carry disappeared:
		 * WhatsApp opened with the words "Send on WhatsApp" and nothing else.
		 * Measured in a browser before and after.
		 */
		$$(ATTR_I18N).forEach(function (node) {
			Array.prototype.forEach.call(node.attributes, function (attr) {
				var match = /^data-msl-(.+)-i18n$/.exec(attr.name);

				if (!match) { return; }

				var value = dict[attr.value];

				if (value === undefined) { return; }

				node.setAttribute('data-msl-' + match[1], value);
			});
		});

		$$('[data-msl-i18n]').forEach(function (node) {
			var value = dict[node.dataset.mslI18n];

			if (value === undefined) { return; }

			/* Sentences with a number in them keep their template on the node,
			   so the value can be re-interpolated rather than concatenated —
			   the number does not sit in the same place in both languages. A
			   node that names its template's key does not come through here:
			   its text and its template are two different strings. */
			if (node.dataset.mslTemplate !== undefined && node.dataset.mslTemplateI18n === undefined) {
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
		renderAgo();
		renderReferral();
		renderCountdown();
		renderUrgency();
		renderHints();
		renderResult();
	}

	/* ------------------------------------------------------------------
	 * Counters
	 * --------------------------------------------------------------- */

	/* ------------------------------------------------------------------
	 * Groups
	 *
	 * On a group's page the artwork is the group's: its shape, its accent, its
	 * count against its target. The campaign's own numbers are untouched — the
	 * header still counts everybody, because a light lit in a group is a light
	 * in the main artwork too and saying otherwise on the same screen would be
	 * a contradiction the visitor has to resolve.
	 * --------------------------------------------------------------- */

	function artCount() {
		return config.group ? state.groupCount : state.participants;
	}

	function renderGroup() {
		if (!config.group) { return; }

		var target = Math.max(1, config.group.target);
		var pct = Math.min(100, Math.floor(state.groupCount * 100 / target));

		$$('[data-msl-group-count]').forEach(function (node) { node.textContent = num(state.groupCount); });
		$$('[data-msl-group-count-minus-one]').forEach(function (node) { node.textContent = num(Math.max(0, state.groupCount - 1)); });
		$$('[data-msl-group-pct]').forEach(function (node) { node.textContent = num(pct); });

		$$('[data-msl-group-progress]').forEach(function (node) {
			node.setAttribute('aria-valuenow', String(pct));
			var fill = node.firstElementChild;
			if (fill) { fill.style.width = pct + '%'; }
		});

		/* The artwork is the same number in another form, so it moves with it
		   rather than waiting for the next full redraw. */
		if (canvasEngine.setState) {
			canvasEngine.setState({ count: state.groupCount });
		}
	}

	/* ------------------------------------------------------------------
	 * "Four minutes ago"
	 *
	 * The server writes the sentence once and the browser keeps it true: the
	 * page may be served from a cache, and it is in any case read for longer
	 * than a minute by somebody watching their family answer. Every node keeps
	 * the moment itself in `datetime`, so this is a re-derivation and not an
	 * increment — a tab left open overnight is right when it is looked at
	 * again, not a day behind.
	 * --------------------------------------------------------------- */

	function agoText(seconds) {
		var days = Math.floor(seconds / 86400);

		if (days > 2) { return format(t('groups.ago_days'), [num(days)]); }
		if (days === 2) { return t('groups.ago_two_days'); }
		if (days === 1) { return t('groups.ago_yesterday'); }

		var hours = Math.floor(seconds / 3600);

		if (hours > 2) { return format(t('groups.ago_hours'), [num(hours)]); }
		if (hours === 2) { return t('groups.ago_two_hours'); }
		if (hours === 1) { return t('groups.ago_hour'); }

		var minutes = Math.floor(seconds / 60);

		if (minutes > 2) { return format(t('groups.ago_minutes'), [num(minutes)]); }
		if (minutes === 2) { return t('groups.ago_two_minutes'); }
		if (minutes === 1) { return t('groups.ago_minute'); }

		return t('groups.ago_now');
	}

	function renderAgo() {
		var now = Date.now();

		$$('[data-msl-ago]').forEach(function (node) {
			var when = Date.parse(node.getAttribute('datetime'));

			if (isNaN(when)) { return; }

			node.textContent = agoText(Math.max(0, Math.round((now - when) / 1000)));
		});
	}

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

		canvasEngine.setState({ count: artCount() });
		renderGroup();
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
		state.target = data.participants;
		state.pct = data.pct;

		/* A figure below what is on screen is a correction, not an animation:
		   land on it at once rather than easing down. */
		if (state.participants > state.target) {
			state.participants = state.target;
		}

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
	 * The counter walks up to the number the last poll returned, rather than
	 * replacing it in one jump. It closes the gap in about a second and then
	 * stops — it never walks past the server's figure.
	 *
	 * It used to project forwards instead, at the rate the last two polls
	 * happened to observe, and refuse to come down again. Both halves of that
	 * were wrong together: one eight-second window that catches a +1 reads as
	 * 450 an hour where the truth is 150, the projection runs ahead on that
	 * guess, and the refusal to come down makes each browser keep its own
	 * overshoot for as long as the tab is open. Two people looking at the same
	 * campaign on two screens saw two different numbers, drifting further
	 * apart the longer they watched. The server's figure is arithmetic on the
	 * clock and is the same for everybody; the only job here is to reach it.
	 */
	function walkCounter() {
		if (document.hidden || state.participants === state.target) { return; }

		if (state.participants > state.target) {
			state.participants = state.target;
		} else {
			var gap = state.target - state.participants;
			state.participants = Math.min( state.target, state.participants + Math.max( 1, Math.ceil( gap / 5 ) ) );
		}

		renderCounters();
	}

	function pollStats() {
		if (document.hidden) { return; }

		window.fetch(config.rest.stats, { credentials: 'same-origin' })
			.then(function (r) { return r.ok ? r.json() : null; })
			.then(function (data) {
				if (!data) { return; }

				applyStats(data);
			})
			.catch(function () { /* The seeded numbers stay on screen. */ });
	}

	/*
	 * The personal area, to somebody the server says is signed in.
	 *
	 * This page declares itself uncacheable three ways over, and a host that
	 * obeys none of them serves the stored signed-out copy to a person who is
	 * signed in: they sign in, come back, and meet the same stored page. The
	 * marker element only exists on the signed-out screen, so asking here means
	 * "the page says signed out" — and /session answers from the cookie, past
	 * any cache, whether that is true.
	 *
	 * One reload with a key the cache has never seen usually fetches the real
	 * page. If the answer is still "signed in" after that reload, no amount of
	 * reloading will help, and the note says what will.
	 */
	/*
	 * Arriving on somebody's personal link.
	 *
	 * The point of sharing is showing what you made, so a link opens on the
	 * sharer's own candle rather than at the top of the page: the artwork, the
	 * camera on their light, their card open on it, the line that names them,
	 * how many people have already lit one through that link, and the button
	 * that lights the next.
	 *
	 * The name and the count come from /referral, not from the HTML, so the
	 * page itself stays identical for everybody and stays cacheable. The
	 * artwork has to have drawn before a position can be turned into a cell,
	 * so this waits for the first frames rather than asking too early.
	 */
	function inviteCode() {
		var match = window.location.pathname.match(/\/join\/([A-Za-z0-9]{6,12})\/?$/);

		return match ? match[1].toLowerCase() : '';
	}

	function fillInviteCard(data) {
		var card = $('[data-msl-invite-card]');

		if (!card) { return; }

		var title = $('[data-msl-invite-title]', card);
		var count = $('[data-msl-invite-count]', card);

		if (title) {
			title.textContent = data.name
				? format(title.dataset.mslTemplate, [data.name])
				: title.dataset.mslAnon;
		}

		if (count) {
			count.textContent = data.count > 0
				? format(count.dataset.mslTemplate, [num(data.count)])
				: count.dataset.mslFirst;
		}

		card.hidden = false;
	}

	function landOnInvite(code) {
		window.fetch(config.rest.referral + '/' + code, { credentials: 'same-origin' })
			.then(function (r) { return r.ok ? r.json() : null; })
			.then(function (data) {
				if (!data) { return; }

				fillInviteCard(data);

				if (!(data.piece >= 0)) { return; }

				goto('art');

				var tries = 0;
				var land = function () {
					if (showPiece(data.piece) || tries > 120) { return; }

					tries++;
					window.requestAnimationFrame(land);
				};

				land();
			})
			.catch(function () { /* The page is still the campaign page. */ });
	}

	function probeSession() {
		var probe = $('[data-msl-session-probe]');

		if (!probe || !config.rest || !config.rest.session) { return; }

		window.fetch(config.rest.session, { credentials: 'same-origin', cache: 'no-store' })
			.then(function (r) { return r.ok ? r.json() : null; })
			.then(function (data) {
				if (!data || !data.signedIn) { return; }

				var url = new URL(window.location.href);

				if (url.searchParams.has('msl_fresh')) {
					probe.hidden = false;
					return;
				}

				url.searchParams.set('msl_fresh', String(Date.now()));
				window.location.replace(url.toString());
			})
			.catch(function () { /* Offline: the sign-in form is still usable. */ });
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

	/* Fixed in each language, not a content field: see msl_parsha_prefix(). */
	function parshaPrefix() {
		return state.lang === 'en' ? 'Parashat ' : 'פרשת ';
	}

	function renderCountdown() {
		var node = $('[data-msl-countdown]');

		if (!node) { return; }

		var remaining = Math.max(0, config.campaign.candleLighting - Math.floor(Date.now() / 1000));
		var days = Math.floor(remaining / 86400);
		/* The fetched name when the site is pulling times, the content field
		   when it is not. The server rendered the same choice into the HTML.
		   Whole names either way — the fetched one already carries "parashat"
		   or is a festival that must not be given it, and the typed field is a
		   portion, so it is the one that needs the word put back. */
		var auto = config.campaign.parsha || {};
		var parsha = auto[state.lang] || (parshaPrefix() + t('campaign.parsha'));

		if (days > 2) {
			node.textContent = format(t('chrome.countdown_days'), [parsha, num(days)]);
			return;
		}

		/* One, two, many: Hebrew counts them with different words. */
		if (days === 2 || days === 1) {
			node.textContent = format(t(days === 2 ? 'chrome.countdown_2days' : 'chrome.countdown_day'), [parsha]);
			return;
		}

		/* Under a day the unit changes rather than the number growing a colon.
		   Mirrors msl_countdown() exactly — the server renders the first frame
		   and this keeps it moving, so the two must agree on every boundary. */
		var hours = Math.floor(remaining / 3600);

		if (hours > 2) {
			node.textContent = format(t('chrome.countdown_hours'), [parsha, num(hours)]);
			return;
		}

		if (hours === 2 || hours === 1) {
			node.textContent = format(t(hours === 2 ? 'chrome.countdown_2hours' : 'chrome.countdown_hour'), [parsha]);
			return;
		}

		var minutes = Math.floor(remaining / 60);

		if (minutes > 1) {
			node.textContent = format(t('chrome.countdown_minutes'), [parsha, num(minutes)]);
			return;
		}

		node.textContent = format(t('chrome.countdown_minute'), [parsha]);
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

	/* Whether this visitor actually owns a personal link. */
	function hasOwnLink() {
		return !!((config.auth && config.auth.link) || state.refCode);
	}

	function shareUrl() {
		/* A signed-in person's link is theirs for good and the server already
		   rendered it; a code minted by this session's join comes next; failing
		   both, the campaign's own address, which is still worth sharing. */
		if (config.auth && config.auth.link) { return config.auth.link; }

		return state.refCode ? config.joinBase + state.refCode + '/' : window.location.origin + '/';
	}

	function renderReferral() {
		var mine = hasOwnLink();
		var url = shareUrl();
		var shown = url.replace(/^https?:\/\//, '').replace(/\/$/, '');

		/*
		 * Only somebody who has a code sees a link. Everybody else sees the
		 * invitation to get one — the card used to print the site's own front
		 * page under the words "your personal link", which is not a personal
		 * link and carries nobody's attribution.
		 */
		$$('[data-msl-link-ready]').forEach(function (node) { node.hidden = !mine; });
		$$('[data-msl-link-get]').forEach(function (node) { node.hidden = mine; });

		if (!mine) { return; }

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

	/*
	 * A dedication arrives as its two halves — the key of the copy that says
	 * "for the healing of", and the name. It is put together here rather than
	 * on the server because the language switch happens in this browser with
	 * no reload, and a sentence baked server-side would stay in the language
	 * the page was cached in.
	 */
	function dedText(pair) {
		if (!pair || !pair.name) { return ''; }

		return (t(pair.key) + ' ' + pair.name).trim();
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
		var ded = $('[data-msl-pick-ded]', container);
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

		/*
		 * What the group this candle was lit through was opened for. It has a
		 * line of its own rather than being appended to the one above: a city
		 * and a small undertaking are details about a person, and "for the
		 * healing of Sarah bat Rachel" is the reason the candle is lit.
		 *
		 * The stand-in people invented for candles with no record have no
		 * group and therefore no dedication, which is why this reads the
		 * property rather than assuming it is there.
		 */
		if (ded) {
			ded.textContent = dedText(person && person.ded);
			ded.hidden = ded.textContent === '';
		}

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
	function cellForPiece(piece) {
		if (!(piece >= 0)) { return -1; }

		var drawn = canvasEngine.litCount();
		var people = Math.max(1, canvasEngine.state.count || 0);

		if (drawn < 1) { return -1; }

		return Math.max(0, Math.min(drawn - 1, Math.floor(piece * drawn / people)));
	}

	function myCell() {
		return cellForPiece(state.myPiece);
	}

	/* The camera on one person's candle, with their own row in the card. */
	function showPiece(piece) {
		var index = cellForPiece(piece);
		var cell = index < 0 ? null : canvasEngine.cellAt(index);

		if (!cell) { return false; }

		state.artPick = index;
		canvasEngine.setState({
			artPick: index,
			fx: cell.nx,
			fy: cell.ny,
			artZ: Math.max(2, canvasEngine.state.artZ)
		});

		loadPieces({ from: piece, to: piece }, function (person) {
			showPick($('[data-msl-art-pick]'), person);
		});

		renderHints();

		return true;
	}

	function renderMyCandle() {
		var button = $('[data-msl-my-candle]');

		if (button) { button.hidden = state.myPiece < 0; }
	}

	/* The visitor's own record is the one that must never be a stand-in, so this
	   asks for their exact position rather than their slice. */
	function showMyCandle() {
		showPiece(state.myPiece);
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

			/*
			 * On a group's page the artwork is the group's, and so is every
			 * candle in it: the answer is on the page already. Asking the
			 * pieces endpoint here would be worse than not asking — it maps a
			 * cell to a position in the *campaign's* artwork, so on a group of
			 * two hundred it would confidently name the campaign's first two
			 * hundred participants, none of whom are in this group.
			 */
			if (config.group) {
				showPick($('[data-msl-art-pick]'), { name: '', place: '', thing: '', ded: config.group.ded });
			} else {
				loadPieces(pieceWindow(index, canvasEngine.litCount()), function (person) {
					if (state.artPick === index) { showPick($('[data-msl-art-pick]'), person); }
				});
			}

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

	/* ------------------------------------------------------------------
	 * The map
	 *
	 * The same three gestures as the artwork — drag, pinch, tap — plus the two
	 * buttons, because a keyboard has no pinch. Tapping a light names the
	 * country it is in and says how many candles are lit there, which is the
	 * question the map has been inviting since it was first drawn and could not
	 * answer.
	 * --------------------------------------------------------------- */

	function renderMapPick() {
		var card = $('[data-msl-map-pick]');

		if (!card) { return; }

		var point = state.mapPick === null ? null : canvasEngine.mapPointAt(state.mapPick);

		if (!point || !point.country) {
			card.hidden = true;
			return;
		}

		var total = (config.mapCountries || {})[point.country] || point.n || 0;
		var place = $('[data-msl-map-place]');
		var count = $('[data-msl-map-count]');

		if (place) { place.textContent = point.country; }

		if (count) {
			count.textContent = 1 === total
				? t('map.pick_one')
				: format(count.dataset.mslTemplate || t('map.pick_count'), [num(total)]);
		}

		card.hidden = false;
	}

	function renderMapLevel() {
		var level = $('[data-msl-map-level]');

		if (level) {
			/* One decimal, because the map zooms continuously rather than in
			   steps, and "×2.6" is a truer answer than a rounded "×3". */
			var z = canvasEngine.state.mapZoom || 1;
			level.textContent = '×' + (z < 1.05 ? '1' : z.toFixed(1).replace(/\.0$/, ''));
		}
	}

	function bindMap() {
		var cv = $('[data-msl-map-surface]');

		if (!cv || !canvasEngine.mapHitIndex) { return; }

		var selectAt = function (clientX, clientY) {
			var rect = cv.getBoundingClientRect();
			var index = canvasEngine.mapHitIndex(cv, clientX - rect.left, clientY - rect.top);

			state.mapPick = (index === null || state.mapPick === index) ? null : index;
			canvasEngine.setState({ mapPick: state.mapPick });
			renderMapPick();
		};

		/* Panning only once there is somewhere to pan to: at rest a finger
		   dragged across the map has to scroll the page behind it, or the map
		   becomes a trap halfway down a long page. */
		bindGestures(cv, {
			pan: function (dx, dy) {
				if ((canvasEngine.state.mapZoom || 1) <= 1.001) { return false; }

				canvasEngine.panMap(cv, dx, dy);

				return true;
			},
			zoom: function (factor, cx, cy) {
				var rect = cv.getBoundingClientRect();

				canvasEngine.mapZoomAt(
					cv,
					factor,
					(cx === undefined ? rect.width / 2 : cx - rect.left),
					(cy === undefined ? rect.height / 2 : cy - rect.top)
				);

				renderMapLevel();
			},
			tap: selectAt
		});

		cv.addEventListener('wheel', function (event) {
			if (!event.deltaY) { return; }

			event.preventDefault();

			var rect = cv.getBoundingClientRect();

			canvasEngine.mapZoomAt(cv, event.deltaY < 0 ? 1.18 : 1 / 1.18, event.clientX - rect.left, event.clientY - rect.top);
			renderMapLevel();
		}, { passive: false });

		$$('[data-msl-map-zoom]').forEach(function (button) {
			button.addEventListener('click', function () {
				canvasEngine.mapZoomAt(cv, button.dataset.mslMapZoom === 'in' ? 1.6 : 1 / 1.6, cv.clientWidth / 2, cv.clientHeight / 2);

				/* All the way out is all the way out: the pick goes with it,
				   because at ×1 a single dot is not something anybody chose. */
				if ((canvasEngine.state.mapZoom || 1) <= 1.001) {
					canvasEngine.resetMap();
					state.mapPick = null;
					renderMapPick();
				}

				renderMapLevel();
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
	 * Coming into view
	 * --------------------------------------------------------------- */

	/*
	 * Sections settle in as they are reached.
	 *
	 * The `msl-js` class is added here rather than printed by the server, so the
	 * hidden state cannot exist unless this code is running to undo it. A page
	 * whose CSS hides its content and whose script failed to load is a blank
	 * page, and that is not a trade worth making for an animation.
	 *
	 * Each element is unobserved once it has arrived: this runs while the whole
	 * page scrolls, and an observer that keeps firing for things already shown
	 * is work with nothing to show for it.
	 */
	function startEntrances() {
		var targets = $$('[data-msl-rise], [data-msl-rise-group]');

		if (!targets.length) { return; }

		if (reduceMotion || !('IntersectionObserver' in window)) {
			targets.forEach(function (el) { el.classList.add('is-in'); });
			document.documentElement.classList.add('msl-js');

			return;
		}

		document.documentElement.classList.add('msl-js');

		var seen = new window.IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) { return; }

				entry.target.classList.add('is-in');
				seen.unobserve(entry.target);
			});
		}, {
			/* A little before the edge, so a section is already settled by the
			   time it is properly on screen rather than animating under the
			   reader's eye. */
			rootMargin: '0px 0px -12% 0px',
			threshold: 0.08
		});

		targets.forEach(function (el) { seen.observe(el); });

		/* Whatever is already on screen at load arrives immediately: the first
		   thing a visitor sees should not have to be scrolled into place. */
		window.requestAnimationFrame(function () {
			targets.forEach(function (el) {
				if (el.getBoundingClientRect().top < window.innerHeight * 0.92) {
					el.classList.add('is-in');
					seen.unobserve(el);
				}
			});
		});
	}

	/* ------------------------------------------------------------------
	 * The candles in the hero
	 * --------------------------------------------------------------- */

	/*
	 * Fourteen candles that light, go out, and come back somewhere else.
	 *
	 * Everything that moves on its own — the sway, the flicker, the breathing
	 * halo, the bob — is CSS. This owns only the state: which candles are
	 * alight, which have gone, and where the next one comes back. A candle is
	 * moved while it is dark, so what the eye sees is a candle that has appeared
	 * in a new place rather than one sliding across the hero.
	 */
	var heroCandles = {
		nodes: [],
		state: [],
		timer: 0
	};

	function paintCandle(index) {
		var node = heroCandles.nodes[index];
		var st = heroCandles.state[index];

		if (!node || !st) { return; }

		node.style.opacity = st.vis ? '1' : '0';
		node.style.transform = 'translate(' + st.dx + 'px,' + st.dy + 'px) scale(' + (st.vis ? 1 : 0.76) + ')';

		var flame = $('[data-msl-candle-flame]', node);
		var glow = $('[data-msl-candle-glow]', node);
		var smoke = $('[data-msl-candle-smoke]', node);
		var pool = $('[data-msl-candle-pool]', node);

		if (flame) { flame.style.opacity = st.on ? '1' : '0'; }
		if (glow) { glow.style.opacity = st.on ? '1' : '0'; }
		if (pool) { pool.style.opacity = st.on ? '1' : '0'; }
		if (smoke) { smoke.style.opacity = st.on || !st.vis ? '0' : '1'; }
	}

	function relightCandle(index) {
		var st = heroCandles.state[index];

		if (!st) { return; }

		/* New offsets while it is dark. Twelve pixels either way is enough to
		   read as a different place and small enough that the two columns stay
		   columns. */
		st.dx = Math.round((Math.random() * 2 - 1) * 12);
		st.dy = Math.round((Math.random() * 2 - 1) * 12);
		st.on = true;
		st.vis = true;

		paintCandle(index);
	}

	function cycleCandles() {
		/* Only while the hero is what the visitor is looking at. Behind a
		   full-screen panel this is fourteen elements animating for nobody. */
		if (state.screen !== 'home' || !heroCandles.nodes.length) { return; }

		var index = Math.floor(Math.random() * heroCandles.nodes.length);
		var st = heroCandles.state[index];

		if (!st || !st.on || !st.vis) { return; }

		var vanishes = Math.random() < 0.45;

		st.on = false;
		st.vis = !vanishes;

		paintCandle(index);

		window.setTimeout(function () { relightCandle(index); }, vanishes ? 1500 : 1050);
	}

	function bindHeroCandles() {
		heroCandles.nodes = $$('[data-msl-candle]');

		if (!heroCandles.nodes.length) { return; }

		heroCandles.state = heroCandles.nodes.map(function () {
			return { on: true, vis: true, dx: 0, dy: 0 };
		});

		heroCandles.nodes.forEach(function (node, index) {
			node.addEventListener('click', function () {
				var st = heroCandles.state[index];

				if (!st.vis) { return; }

				st.on = !st.on;
				paintCandle(index);
			});
		});

		if (reduceMotion) { return; }

		heroCandles.timer = window.setInterval(cycleCandles, 1500);

		/* The interval belongs to this page and nothing else. Clearing it on the
		   way out is the same care a component takes when it unmounts. */
		window.addEventListener('pagehide', function () {
			window.clearInterval(heroCandles.timer);
			heroCandles.timer = 0;
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
		document.body.classList.add('is-locked');
		renderReferral();

		var focusable = $('.msl-btn, .msl-invite__close', inviteModal);

		if (focusable) { focusable.focus(); }
	}

	function closeInvite() {
		if (!inviteModal || inviteModal.hidden) { return; }

		inviteModal.hidden = true;
		document.body.classList.remove('is-locked');
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
	/* ------------------------------------------------------------------
	 * The Shabbat times: letting a visitor use their own city
	 *
	 * The page arrives with the campaign's city rendered into it. Choosing
	 * another one asks our own server for that city's times — the browser never
	 * talks to hebcal.com — and rewrites the card in place, so the artwork
	 * canvas beside it is not thrown away mid-animation.
	 *
	 * Without the script the same <form> is an ordinary GET and the server
	 * renders the card for the city in the address. Everything here is an
	 * improvement on a page that already works.
	 * --------------------------------------------------------------- */

	var placeKey = 'msl_place';

	function readPlace() {
		try { return window.localStorage.getItem(placeKey) || ''; } catch (e) { return ''; }
	}

	function writePlace(id) {
		try { window.localStorage.setItem(placeKey, String(id)); } catch (e) { /* nothing to do */ }
	}

	/* One value, written in both languages so the toggle keeps working. */
	function setPair(node, pair) {
		if (!node) { return; }

		node.textContent = '';

		['he', 'en'].forEach(function (lang) {
			var span = document.createElement('span');
			span.setAttribute('data-msl-only', lang);
			span.textContent = pair[lang] || '';
			node.appendChild(span);
		});
	}

	function slot(card, name) {
		return card.querySelector('[data-msl-zmanim="' + name + '"]');
	}

	function renderZmanim(card, data) {
		if (!data || !data.names) { return; }

		card.setAttribute('data-msl-place', String(data.place));

		setPair(slot(card, 'hdate'), data.hdate || { he: '', en: '' });
		setPair(slot(card, 'parsha'), data.parsha);

		/* The second day of a festival is a festival abroad and an ordinary
		   Shabbat in Israel, so the portion row's own label belongs to the
		   place and has to move with it. */
		var parshaLabel = card.querySelector('[data-msl-zmanim-label="parsha"]');

		if (parshaLabel) {
			var labelKey = data.festival ? 'zmanim.label_holiday' : 'zmanim.label_parsha';

			parshaLabel.setAttribute('data-msl-i18n', labelKey);
			parshaLabel.textContent = t(labelKey);
		}

		var candles = slot(card, 'candles');
		var havdalah = slot(card, 'havdalah');

		if (candles) { candles.textContent = data.candles || ''; }
		if (havdalah) { havdalah.textContent = data.havdalah || ''; }

		var holiday = slot(card, 'holiday');

		if (holiday) {
			setPair(holiday, data.holiday);
			holiday.hidden = !data.holiday.he;
		}

		var note = slot(card, 'note');

		if (note) {
			note.textContent = '';

			['he', 'en'].forEach(function (lang) {
				var template = tIn(lang, 'zmanim.note');

				if (!template) { return; }

				var span = document.createElement('span');
				span.setAttribute('data-msl-only', lang);
				span.textContent = format(template, [data.names[lang] || '']);
				note.appendChild(span);
			});
		}
	}

	function loadZmanim(card, id, remember) {
		if (!config.rest || !config.rest.zmanim) { return; }

		window.fetch(config.rest.zmanim + '?place=' + encodeURIComponent(id), { credentials: 'same-origin' })
			.then(function (response) { return response.ok ? response.json() : null; })
			.then(function (data) {
				if (!data) { return; }

				renderZmanim(card, data);

				if (remember) { writePlace(id); }

				/* The address should say which city is on screen, so a refresh
				   and a shared link both land on the same card. */
				if (window.history && window.history.replaceState) {
					try {
						var url = new URL(window.location.href);
						url.searchParams.set('msl_place', String(id));
						window.history.replaceState(null, '', url.toString());
					} catch (e) { /* an address we cannot parse is not worth failing over */ }
				}
			})
			.catch(function () { /* the card keeps the city it already shows */ });
	}

	function bindPlaces() {
		var card = $('[data-msl-zmanim-card]');

		if (!card) { return; }

		var picker = $('[data-msl-placepick]', card);
		var select = $('[data-msl-place-select]', card);

		if (picker && select) {
			var apply = function (event) {
				if (event) { event.preventDefault(); }

				var id = parseInt(select.value, 10);

				if (!id || String(id) === card.getAttribute('data-msl-place')) {
					picker.open = false;

					return;
				}

				loadZmanim(card, id, true);
				picker.open = false;
			};

			select.addEventListener('change', apply);

			var form = $('.msl-placepick__form', picker);

			if (form) { form.addEventListener('submit', apply); }
		}

		/* A city chosen on an earlier visit. Not read by the server — that would
		   make the cached page visitor-specific — so it is applied here, and
		   only when the address is not already asking for a particular city. */
		var stored = readPlace();
		var asked = new URLSearchParams(window.location.search).get('msl_place');

		if (!asked && stored && stored !== card.getAttribute('data-msl-place')) {
			if (select) { select.value = stored; }
			loadZmanim(card, stored, false);
		}
	}

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

			/* The state is already in aria-expanded; the label follows it too so
			   the button says what pressing it will do, in the words the editor
			   chose. Below 720px this label is the button's only name. */
			var label = t(open ? 'nav.menu_close' : 'nav.menu_open');

			if (label) { toggle.setAttribute('aria-label', label); }
		};

		toggle.addEventListener('click', function (event) {
			event.stopPropagation();
			setOpen(list.hidden);
		});

		$$('.msl-menu__link', wrap).forEach(function (link) {
			link.addEventListener('click', function (event) {
				setOpen(false);

				var href = link.getAttribute('href') || '';

				/* A menu entry pointing at #invite opens the share window rather
				   than navigating. It is also the way back to it for anyone who
				   closed it once — the automatic opening stays closed for days
				   on purpose, and asking for it should always work. */
				if (/#invite$/.test(href)) {
					event.preventDefault();
					openInvite();

					return;
				}

				/*
				 * A section link on the page it points at scrolls rather than
				 * reloads. The href still carries the full address so the same
				 * entry works from the about page, where it has to navigate.
				 */
				var hash = href.indexOf('#') === -1 ? '' : href.slice(href.indexOf('#'));

				if (hash.length < 2) { return; }

				var target = document.getElementById(hash.slice(1));

				if (!target) { return; }

				event.preventDefault();
				target.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });

				/* The address bar should still say where we are. */
				if (window.history && window.history.replaceState) {
					window.history.replaceState(null, '', hash);
				}
			});
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
	 * The reminder on the way out
	 * --------------------------------------------------------------- */

	/*
	 * Shabbat comes round quickly and this page is easy to leave and forget.
	 * On the way out we offer one reminder, carrying whatever light the visitor
	 * chose so it is waiting for them rather than starting over.
	 *
	 * It opens once. Anyone who signs up, or closes it, does not see it again —
	 * an exit popup that reappears is the reason people distrust exit popups.
	 */
	var remindModal = $('[data-msl-modal="remind"]');
	var remindReturn = null;
	var remindShown = false;

	function remindSeen() {
		try { return window.localStorage.getItem('msl_remind_seen') === '1'; } catch (e) { return false; }
	}

	function rememberRemindSeen() {
		try { window.localStorage.setItem('msl_remind_seen', '1'); } catch (e) { /* nothing to do */ }
	}

	/* The label of whatever they picked, if they picked anything. */
	function chosenLabel() {
		var labels = chosen().map(function (input) {
			if (input.dataset.mslOther === '1') {
				var custom = ($('#msl-custom-label') || {}).value || '';

				return custom.trim() || $('.msl-option__text', input.parentNode).textContent;
			}

			return $('.msl-option__text', input.parentNode).textContent;
		});

		return labels.filter(Boolean).join(' · ');
	}

	function openRemind() {
		if (!remindModal || !remindModal.hidden || remindShown || remindSeen()) { return; }

		/* Never over something the visitor is already doing. */
		if (state.screen !== 'home') { return; }

		var join = $('[data-msl-modal="join"]');
		var invite = $('[data-msl-modal="invite"]');

		if ((join && !join.hidden) || (invite && !invite.hidden)) { return; }

		remindShown = true;
		remindReturn = document.activeElement;

		var label = chosenLabel();
		var chosenBox = $('[data-msl-remind-chosen]');
		var thing = $('[data-msl-remind-thing]');

		if (chosenBox) { chosenBox.hidden = !label; }
		if (thing) { thing.textContent = label; }

		remindModal.hidden = false;
		document.body.classList.add('is-locked');

		var field = $('#msl-remind-email', remindModal);

		if (field) { field.focus(); }
	}

	function closeRemind() {
		if (!remindModal || remindModal.hidden) { return; }

		remindModal.hidden = true;
		document.body.classList.remove('is-locked');
		rememberRemindSeen();

		if (remindReturn && remindReturn.focus) { remindReturn.focus(); }

		remindReturn = null;
	}

	function submitRemind(event) {
		event.preventDefault();

		var email = $('#msl-remind-email');
		var name = $('#msl-remind-name');
		var hp = $('#msl-remind-hp');
		var error = $('#msl-remind-error');
		var button = $('[data-msl-remind-submit]');
		var value = (email.value || '').trim();

		if (error) { error.textContent = ''; }

		if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value)) {
			if (error) { error.textContent = t('auth.remind_err'); }

			email.focus();

			return;
		}

		button.disabled = true;

		var picked = chosen()[0];

		window.fetch(config.rest.remind, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({
				name: (name.value || '').trim(),
				email: value,
				thing: picked ? Number(picked.value) : null,
				custom_label: ($('#msl-custom-label') || {}).value || '',
				lang: state.lang,
				hp: hp ? hp.value : ''
			})
		})
			.then(function (r) { return r.ok ? r.json() : Promise.reject(r); })
			.then(function () {
				rememberRemindSeen();

				var form = $('[data-msl-remind-form]');
				var done = $('[data-msl-remind-done]');
				var chosenBox = $('[data-msl-remind-chosen]');

				if (form) { form.hidden = true; }
				if (chosenBox) { chosenBox.hidden = true; }
				if (done) { done.hidden = false; }
			})
			.catch(function () {
				button.disabled = false;

				if (error) { error.textContent = t('auth.remind_err'); }
			});
	}

	function bindRemind() {
		if (!remindModal || !config.auth || !config.auth.remindOn) { return; }

		$$('[data-msl-close-remind]').forEach(function (node) {
			node.addEventListener('click', closeRemind);
		});

		var form = $('[data-msl-remind-form]');

		if (form) { form.addEventListener('submit', submitRemind); }

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && !remindModal.hidden) { closeRemind(); }
		});

		if (remindSeen()) { return; }

		/*
		 * Leaving, on a desktop, looks like the pointer crossing the top edge
		 * towards the tabs or the address bar.
		 */
		document.addEventListener('mouseout', function (event) {
			if (event.relatedTarget || event.clientY > 4) { return; }

			openRemind();
		});

		/*
		 * A phone has no pointer to leave the window, and the events that do
		 * fire when someone leaves fire too late to show anything. Going quiet
		 * is the only honest signal left, so that is what is used — and it is a
		 * number the campaign can set or switch off.
		 */
		var idle = (config.auth.idle || 0) * 1000;

		if (idle > 0) {
			var timer = 0;
			var restart = function () {
				window.clearTimeout(timer);
				timer = window.setTimeout(openRemind, idle);
			};

			['pointerdown', 'keydown', 'scroll', 'touchstart'].forEach(function (name) {
				window.addEventListener(name, restart, { passive: true });
			});

			restart();
		}
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

		/*
		 * Choosing nothing is allowed too.
		 *
		 * This button used to stay disabled until something was picked, which
		 * made the first step a gate rather than an invitation — and a gate at
		 * the top of a form is where most people leave. Somebody who wants to
		 * add a light without naming what it is still adds a light.
		 */
		var next = $('[data-msl-next="2"]');

		if (next) { next.disabled = false; }
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

	/*
	 * Nothing here is required.
	 *
	 * The campaign wants candles, and a field somebody does not want to answer
	 * is a candle that never gets lit. A blank form still joins — it simply
	 * joins without a name, which the artwork has always known how to show.
	 * What is still checked is the *shape* of what someone did type: an address
	 * with no @ in it is a typo, not a choice, and telling them now is kinder
	 * than a reminder that never arrives.
	 */
	function validate() {
		clearErrors();

		var email = $('#msl-email');
		var phone = $('#msl-phone');

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
			/* Empty everywhere but a group's own page. The server resolves the
			   code itself and ignores one that names a group not taking joins,
			   so this is a hint and never an instruction. */
			group: config.group ? config.group.code : '',
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
		state.target = result.participants;

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

		/* A group's page gets the same moment, because it is the same moment:
		   the candle travels in and lands in the artwork the family is making.
		   What differs is which number grows — the group's here, everybody's on
		   the campaign page — and that is decided by artCount(), so there is
		   one sequence and not two. */
		if (config.group) {
			state.groupCount += 1;
		}

		goto('wow');

		canvasEngine.setState({ count: artCount() - 1 });
		canvasEngine.startWow({
			count: function () {
				state.participants = result.participants;
				state.target = Math.max( state.target, result.participants );
				canvasEngine.setState({ count: artCount() });
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
			/* The personal area ships this button hidden, because copying needs
			   a script and the link itself is already on the page as selectable
			   text. A button that silently does nothing is worse than none. */
			button.hidden = false;
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

	/* ------------------------------------------------------------------
	 * The artwork picker
	 * --------------------------------------------------------------- */

	/*
	 * Draws the picture on every card in "which artwork", on the form that
	 * opens a group and on the one that edits it.
	 *
	 * The cards carry no picture until this runs, and they say so: the figures
	 * are hidden in CSS until the fieldset is marked drawn. With no JavaScript
	 * — or with a canvas the browser will not give a context for — what is left
	 * is the list of names the dropdown used to be, still submitting the same
	 * field. Half-drawn squares would be worse than none.
	 */
	function drawArtThumbs() {
		var picks = document.querySelectorAll('[data-msl-artpick]');

		if (!picks.length || !window.MSLCanvas || !MSLCanvas.thumb) { return; }

		Array.prototype.forEach.call(picks, function (pick) {
			/*
			 * Shown first and taken back afterwards if nothing came of it —
			 * the other way round does not work, and the reason is worth
			 * writing down: a hidden canvas has no width, a canvas with no
			 * width cannot be drawn into, and gating the reveal on a
			 * successful draw therefore never reveals anything at all. Setting
			 * it here also gives the boxes their size before the first
			 * measurement, so nothing is drawn at the wrong scale.
			 */
			pick.setAttribute('data-msl-artpick-drawn', '1');

			var any = false;

			Array.prototype.forEach.call(pick.querySelectorAll('[data-msl-artthumb]'), function (cv) {
				if (MSLCanvas.thumb(cv, cv.getAttribute('data-msl-artthumb')) > 0) { any = true; }
			});

			if (!any) { pick.removeAttribute('data-msl-artpick-drawn'); }
		});
	}

	function bindArtThumbs() {
		if (!document.querySelector('[data-msl-artpick]')) { return; }

		drawArtThumbs();

		/*
		 * The grid reflows with the window and a canvas resized by CSS is a
		 * stretched bitmap, not a redrawn one — the picture has to be laid down
		 * again at the new size. Debounced, because a drag of the window edge
		 * is a hundred of these and each one is ten artworks.
		 */
		var again = null;

		window.addEventListener('resize', function () {
			window.clearTimeout(again);
			again = window.setTimeout(drawArtThumbs, 180);
		});
	}

	function boot() {
		document.body.dataset.mslScreen = 'home';

		state.target = state.participants;

		/* An invite link puts the inviter's code in the address; keep it in a
		   first-party cookie so the attribution survives the whole flow. */
		var invite = inviteCode();

		if (invite) {
			writeCookie(config.cookies.ref, invite, config.cookies.refDays);
		}

		state.refCode = readCookie(config.cookies.mine);

		/*
		 * A signed-in person has a code whether or not they ever filled in the
		 * form, and it is the one the server already rendered into the page.
		 * Taking it here is what makes the count poll for them too — otherwise
		 * the personal link is shown and nothing ever reports back on it.
		 */
		if (config.auth && config.auth.link) {
			var own = config.auth.link.match(/\/join\/([A-Za-z0-9]{6,12})\/?$/);

			if (own) { state.refCode = own[1]; }
		}

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
			target: config.group ? config.group.target : config.campaign.target,
			count: artCount(),
			accent: config.group ? config.group.accent : config.campaign.accent,
			artwork: config.group ? config.group.artwork : config.campaign.artwork,
			motes: config.campaign.lights,
			mapData: config.mapData,
			mapPoints: config.mapPoints,
			still: reduceMotion
		});

		document.documentElement.style.setProperty('--msl-flame', config.campaign.accent);

		bindEvents();
		bindArtView();
		bindMap();
		bindArtThumbs();
		bindMyCandle();
		bindWall();
		bindShare();
		startEntrances();
		bindHeroCandles();
		bindPlaces();
		bindMenu();
		bindAccount();
		bindInvite();
		bindRemind();

		syncOptions();
		applyLanguage();
		renderClosed();

		if (state.refCode) { pollReferral(); }

		probeSession();

		if (invite) { landOnInvite(invite); }

		window.setInterval(renderCountdown, 1000);
		window.setInterval(renderUrgency, 60000);
		window.setInterval(renderAgo, 60000);
		window.setInterval(pollStats, 8000);
		window.setInterval(pollFeed, 15000);
		window.setInterval(walkCounter, 200);
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
