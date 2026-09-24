/*
 * The page editor.
 *
 * Two jobs: the media picker on image fields, and the repeaters. Repeater rows
 * are cloned from a <template> the PHP already rendered, so the field markup
 * exists in exactly one place.
 */

(function () {
	'use strict';

	var strings = window.mslAdmin || {};

	/* --- Media --------------------------------------------------------- */

	document.addEventListener('click', function (event) {
		var pick = event.target.closest('.msl-image__pick');

		if (pick) {
			event.preventDefault();
			openPicker(pick.closest('.msl-image'));
			return;
		}

		var clear = event.target.closest('.msl-image__clear');

		if (clear) {
			event.preventDefault();
			var wrap = clear.closest('.msl-image');
			wrap.querySelector('.msl-image__value').value = '0';
			var preview = wrap.querySelector('.msl-image__preview');
			preview.src = '';
			preview.classList.add('is-empty');
		}
	});

	function openPicker(wrap) {
		if (!window.wp || !window.wp.media) { return; }

		var frame = window.wp.media({
			title: strings.chooseImage || '',
			button: { text: strings.useImage || '' },
			library: { type: 'image' },
			multiple: false
		});

		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			var url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;

			wrap.querySelector('.msl-image__value').value = attachment.id;

			var preview = wrap.querySelector('.msl-image__preview');
			preview.src = url;
			preview.classList.remove('is-empty');
		});

		frame.open();
	}

	/* --- Repeaters ----------------------------------------------------- */

	document.addEventListener('click', function (event) {
		var add = event.target.closest('.msl-repeater__add');

		if (add) {
			event.preventDefault();
			addRow(add.closest('[data-msl-repeater]'));
			return;
		}

		var remove = event.target.closest('.msl-row__remove');

		if (remove) {
			event.preventDefault();

			if (!window.confirm(strings.removeRow || '')) { return; }

			var repeater = remove.closest('[data-msl-repeater]');
			remove.closest('[data-msl-row]').remove();
			reindex(repeater);
		}
	});

	function addRow(repeater) {
		if (!repeater) { return; }

		var template = repeater.querySelector('.msl-repeater__template');
		var rows = repeater.querySelector('.msl-repeater__rows');
		var clone = template.content.cloneNode(true);

		rows.appendChild(clone);
		reindex(repeater);

		var added = rows.lastElementChild;
		var first = added.querySelector('input, textarea, select');

		if (first) { first.focus(); }
	}

	/*
	 * Row indexes live in the input names, so removing a row from the middle
	 * would otherwise leave a gap that PHP reads as an empty row. Renumbering
	 * on every change keeps the submitted array dense.
	 */
	function reindex(repeater) {
		if (!repeater) { return; }

		var labelField = repeater.dataset.labelField;

		repeater.querySelectorAll('[data-msl-row]').forEach(function (row, index) {
			row.querySelectorAll('[name]').forEach(function (field) {
				field.name = field.name.replace(/\[(?:__i__|\d+)\]/, '[' + index + ']');

				if (field.id) {
					field.id = field.id.replace(/-(?:__i__|\d+)-/, '-' + index + '-');
				}
			});

			row.querySelectorAll('label[for]').forEach(function (label) {
				label.htmlFor = label.htmlFor.replace(/-(?:__i__|\d+)-/, '-' + index + '-');
			});

			var summary = row.querySelector('.msl-row__summary');
			var source = row.querySelector('[name$="[' + labelField + ']"]');

			if (summary && source) {
				var update = function () {
					summary.textContent = source.value.trim() || strings.newRow || '';
				};

				if (!source.dataset.mslBound) {
					source.dataset.mslBound = '1';
					source.addEventListener('input', update);
				}

				update();
			}
		});
	}

	document.querySelectorAll('[data-msl-repeater]').forEach(reindex);
}());

/*
 * The content panel.
 *
 * Every section is already in the DOM, because they are one form and one save.
 * So switching sections, searching and filtering by language are all the same
 * operation: decide which fields are shown. Nothing here fetches, and nothing
 * here is lost by switching away from a section mid-edit.
 */

(function () {
	'use strict';

	var panel = document.querySelector('[data-msl-panel-form]');

	if (!panel) { return; }

	var strings = window.mslAdmin || {};
	var sections = Array.prototype.slice.call(panel.querySelectorAll('[data-msl-section]'));
	var tabs = Array.prototype.slice.call(panel.querySelectorAll('[data-msl-tab]'));
	var openInput = panel.querySelector('[data-msl-open]');
	var search = document.querySelector('[data-msl-search]');
	var hits = document.querySelector('[data-msl-hits]');
	var dirtyFlag = panel.querySelector('[data-msl-dirty]');
	var lang = 'all';
	var dirty = false;

	/* --- Which fields a filter leaves standing ------------------------- */

	/* The haystack for one field: its label, its help text and whatever is
	   currently typed into it — searching for a string you can see on the page
	   is the way anyone actually looks for where it is edited. */
	function haystack(field) {
		var text = field.textContent || '';

		field.querySelectorAll('input, textarea, select').forEach(function (el) {
			if (el.type === 'hidden') { return; }
			text += ' ' + (el.value || '');

			if (el.tagName === 'SELECT' && el.selectedOptions[0]) {
				text += ' ' + el.selectedOptions[0].textContent;
			}
		});

		return text.toLowerCase();
	}

	function fieldLang(field) {
		var named = field.querySelector('[name]');
		var name = named ? named.getAttribute('name') : '';

		if (/_en\]$/.test(name)) { return 'en'; }
		if (/_he\]$/.test(name)) { return 'he'; }

		return 'both';
	}

	/* Returns how many fields survived in this section. */
	function filterSection(section, needle) {
		var kept = 0;

		section.querySelectorAll('.msl-field').forEach(function (field) {
			var fl = fieldLang(field);
			var show = (lang === 'all' || fl === 'both' || fl === lang) &&
				(needle === '' || haystack(field).indexOf(needle) !== -1);

			field.classList.toggle('is-hidden', !show);

			if (show) { kept++; }
		});

		var empty = section.querySelector('[data-msl-empty]');

		if (empty) { empty.hidden = kept !== 0; }

		return kept;
	}

	function apply() {
		var needle = search ? search.value.trim().toLowerCase() : '';
		var total = 0;

		sections.forEach(function (section, i) {
			var kept = filterSection(section, needle);
			var tab = tabs[i];
			var badge = tab ? tab.querySelector('[data-msl-tab-hits]') : null;

			total += kept;

			if (!tab) { return; }

			/* With a search running the tab count is what tells you which other
			   section the word you are looking for lives in. */
			tab.classList.toggle('is-empty', needle !== '' && kept === 0);

			if (badge) {
				badge.hidden = needle === '';
				badge.textContent = String(kept);
			}
		});

		if (hits) {
			hits.textContent = needle === ''
				? ''
				: (total === 0 ? (strings.noMatch || '') : total + '');
		}
	}

	/* --- Sections ------------------------------------------------------ */

	function openSection(key) {
		sections.forEach(function (section) {
			var on = section.dataset.mslSection === key;
			section.hidden = !on;
			section.classList.toggle('is-active', on);
		});

		tabs.forEach(function (tab) {
			var on = tab.dataset.mslTab === key;
			tab.classList.toggle('is-active', on);
			tab.setAttribute('aria-current', on ? 'true' : 'false');
		});

		if (openInput) { openInput.value = key; }
	}

	tabs.forEach(function (tab) {
		tab.addEventListener('click', function () {
			openSection(tab.dataset.mslTab);
			window.scrollTo({ top: 0, behavior: 'smooth' });
		});
	});

	/* A save redirects back to #msl-section-<key>, so a round trip lands on the
	   section that was open rather than at the top of the list. */
	if (window.location.hash.indexOf('#msl-section-') === 0) {
		openSection(window.location.hash.replace('#msl-section-', ''));
	}

	/* --- Search and language ------------------------------------------- */

	if (search) {
		search.addEventListener('input', apply);
		search.addEventListener('keydown', function (event) {
			// Enter in a search box must not submit four hundred fields.
			if (event.key === 'Enter') { event.preventDefault(); }
		});
	}

	document.querySelectorAll('[data-msl-lang]').forEach(function (button) {
		button.addEventListener('click', function () {
			lang = button.dataset.mslLang;

			document.querySelectorAll('[data-msl-lang]').forEach(function (other) {
				other.classList.toggle('button-primary', other === button);
			});

			apply();
		});
	});

	/* --- Leaving with unsaved work ------------------------------------- */

	panel.addEventListener('input', function () {
		dirty = true;

		if (dirtyFlag) { dirtyFlag.hidden = false; }
	});

	panel.addEventListener('submit', function () { dirty = false; });

	window.addEventListener('beforeunload', function (event) {
		if (!dirty) { return; }

		event.preventDefault();
		event.returnValue = strings.unsaved || '';

		return event.returnValue;
	});

	apply();
}());
