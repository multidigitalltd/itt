/*
 * The accessibility widget.
 *
 * Fixed to the viewport and reachable from anywhere on the page at any scroll
 * position, because it is the only way in to these adjustments. The panel is a
 * real dialog — focus is trapped while it is open, Escape closes it, and focus
 * returns to the button that opened it.
 *
 * Every choice is remembered in localStorage and re-applied on the next page,
 * so a visitor sets this once rather than on every screen.
 */

(function () {
	'use strict';

	var root = document.querySelector('[data-msl-a11y]');

	if (!root) { return; }

	var settings = window.mslA11y || {};
	var strings = settings.i18n || {};
	var STORAGE = 'msl-a11y';

	/* Five steps to 160%. Beyond that the layout stops being a layout, and the
	   browser's own zoom is the better tool. */
	var SIZES = [100, 115, 130, 145, 160];

	var MODES = [
		{ key: 'contrast', label: strings.contrast, cls: 'msl-a11y-contrast' },
		{ key: 'dark', label: strings.dark, cls: 'msl-a11y-dark' },
		{ key: 'links', label: strings.links, cls: 'msl-a11y-links' },
		{ key: 'readable', label: strings.readable, cls: 'msl-a11y-readable' },
		{ key: 'spacing', label: strings.spacing, cls: 'msl-a11y-spacing' },
		{ key: 'still', label: strings.stopMotion, cls: 'msl-a11y-still' }
	];

	var toggle = root.querySelector('[data-msl-a11y-toggle]');
	var panel = null;
	var lastFocus = null;

	var stored = {};

	try {
		stored = JSON.parse(window.localStorage.getItem(STORAGE) || '{}') || {};
	} catch (e) {
		stored = {};
	}

	var current = {
		size: typeof stored.size === 'number' ? stored.size : 0,
		modes: stored.modes && typeof stored.modes === 'object' ? stored.modes : {}
	};

	function persist() {
		try {
			window.localStorage.setItem(STORAGE, JSON.stringify(current));
		} catch (e) {
			/* Private browsing refuses to store; the session still works. */
		}
	}

	function apply() {
		var html = document.documentElement;
		var scale = SIZES[current.size] / 100;

		html.style.setProperty('--msl-scale', String(scale));
		html.classList.toggle('msl-a11y-scaled', current.size > 0);

		MODES.forEach(function (mode) {
			html.classList.toggle(mode.cls, !!current.modes[mode.key]);
		});

		if (panel) {
			panel.querySelectorAll('[data-mode]').forEach(function (button) {
				button.setAttribute('aria-pressed', current.modes[button.dataset.mode] ? 'true' : 'false');
			});

			var note = panel.querySelector('[data-size-note]');

			if (note) {
				note.textContent = (strings.textSize || '%d').replace('%d', String(SIZES[current.size]));
			}
		}
	}

	function button(label, className) {
		var el = document.createElement('button');
		el.type = 'button';
		el.className = className;
		el.textContent = label || '';
		return el;
	}

	function build() {
		panel = document.createElement('div');
		panel.className = 'msl-a11y__panel';
		panel.setAttribute('role', 'dialog');
		panel.setAttribute('aria-modal', 'true');
		panel.setAttribute('aria-label', strings.title || '');
		panel.hidden = true;

		var head = document.createElement('div');
		head.className = 'msl-a11y__head';

		var title = document.createElement('p');
		title.className = 'msl-a11y__title';
		title.textContent = strings.title || '';

		var close = button('✕', 'msl-iconbtn');
		close.setAttribute('aria-label', strings.close || '');
		close.addEventListener('click', hide);

		head.appendChild(title);
		head.appendChild(close);
		panel.appendChild(head);

		var sizes = document.createElement('div');
		sizes.className = 'msl-a11y__size';

		var smaller = button('A−', 'msl-a11y__item');
		smaller.setAttribute('aria-label', strings.smaller || '');
		smaller.addEventListener('click', function () {
			current.size = Math.max(0, current.size - 1);
			persist();
			apply();
		});

		var bigger = button('A+', 'msl-a11y__item');
		bigger.setAttribute('aria-label', strings.bigger || '');
		bigger.addEventListener('click', function () {
			current.size = Math.min(SIZES.length - 1, current.size + 1);
			persist();
			apply();
		});

		sizes.appendChild(smaller);
		sizes.appendChild(bigger);
		panel.appendChild(sizes);

		var note = document.createElement('p');
		note.className = 'msl-a11y__note';
		note.setAttribute('data-size-note', '');
		note.setAttribute('aria-live', 'polite');
		panel.appendChild(note);

		MODES.forEach(function (mode) {
			var el = button(mode.label, 'msl-a11y__item');
			el.dataset.mode = mode.key;
			el.setAttribute('aria-pressed', 'false');
			el.addEventListener('click', function () {
				current.modes[mode.key] = !current.modes[mode.key];
				persist();
				apply();
			});
			panel.appendChild(el);
		});

		var reset = button(strings.reset, 'msl-a11y__item');
		reset.addEventListener('click', function () {
			current = { size: 0, modes: {} };
			persist();
			apply();
		});
		panel.appendChild(reset);

		if (settings.statement) {
			var link = document.createElement('a');
			link.className = 'msl-a11y__item';
			link.href = settings.statement;
			link.textContent = strings.statement || '';
			panel.appendChild(link);
		}

		panel.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				event.stopPropagation();
				hide();
				return;
			}

			if (event.key !== 'Tab') { return; }

			var items = Array.prototype.slice.call(panel.querySelectorAll('a[href], button'));

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
		});

		root.appendChild(panel);
	}

	function show() {
		lastFocus = document.activeElement;
		panel.hidden = false;
		toggle.setAttribute('aria-expanded', 'true');
		apply();

		var first = panel.querySelector('button');

		if (first) { first.focus(); }
	}

	function hide() {
		panel.hidden = true;
		toggle.setAttribute('aria-expanded', 'false');

		if (lastFocus && lastFocus.isConnected) {
			lastFocus.focus();
		} else {
			toggle.focus();
		}
	}

	build();
	apply();

	toggle.addEventListener('click', function () {
		if (panel.hidden) { show(); } else { hide(); }
	});

	document.addEventListener('click', function (event) {
		if (panel.hidden) { return; }
		if (root.contains(event.target)) { return; }
		hide();
	});
}());
