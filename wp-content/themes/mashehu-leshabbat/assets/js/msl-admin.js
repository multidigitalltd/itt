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
