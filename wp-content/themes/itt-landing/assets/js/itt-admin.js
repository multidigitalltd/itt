/**
 * Page-editor helpers: repeater rows and the media picker.
 *
 * Vanilla JS on top of the core media modal — no jQuery, no extra libraries.
 */
( function () {
	'use strict';

	var strings = window.ittAdmin || {};

	/**
	 * Renumber the name attributes of a repeater's rows after add or remove.
	 *
	 * @param {Element} repeater The repeater container.
	 */
	function reindex( repeater ) {
		var rows = repeater.querySelectorAll( ':scope > .itt-repeater__rows > [data-itt-row]' );

		rows.forEach( function ( row, index ) {
			row.querySelectorAll( '[name]' ).forEach( function ( field ) {
				field.name = field.name.replace( /\[(?:\d+|__i__)\]/, '[' + index + ']' );
			} );
		} );
	}

	/**
	 * Keep a row's collapsed summary in step with its label field.
	 *
	 * @param {Element} row      The row.
	 * @param {string}  labelKey Sub-field used as the summary.
	 */
	function bindSummary( row, labelKey ) {
		var target = row.querySelector( '[name$="[' + labelKey + ']"]' );
		var summary = row.querySelector( '.itt-row__summary' );

		if ( ! target || ! summary ) {
			return;
		}

		target.addEventListener( 'input', function () {
			summary.textContent = target.value || strings.newRow || '';
		} );
	}

	document.querySelectorAll( '[data-itt-repeater]' ).forEach( function ( repeater ) {
		var labelKey = repeater.getAttribute( 'data-label-field' );
		var rows = repeater.querySelector( '.itt-repeater__rows' );
		var template = repeater.querySelector( '.itt-repeater__template' );
		var add = repeater.querySelector( '.itt-repeater__add' );

		repeater.querySelectorAll( '[data-itt-row]' ).forEach( function ( row ) {
			bindSummary( row, labelKey );
		} );

		if ( add && template && rows ) {
			add.addEventListener( 'click', function () {
				var row = template.content.firstElementChild.cloneNode( true );

				rows.appendChild( row );
				reindex( repeater );
				bindSummary( row, labelKey );

				var first = row.querySelector( 'input, textarea, select' );

				if ( first ) {
					first.focus();
				}
			} );
		}

		repeater.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '.itt-row__remove' );

			if ( ! button ) {
				return;
			}

			if ( ! window.confirm( strings.removeRow ) ) {
				return;
			}

			button.closest( '[data-itt-row]' ).remove();
			reindex( repeater );
		} );
	} );

	document.addEventListener( 'click', function ( event ) {
		var pick = event.target.closest( '.itt-image__pick' );
		var clear = event.target.closest( '.itt-image__clear' );

		if ( clear ) {
			var wrapper = clear.closest( '.itt-image' );
			var preview = wrapper.querySelector( '.itt-image__preview' );
			var filename = wrapper.querySelector( '.itt-image__file' );

			wrapper.querySelector( '.itt-image__value' ).value = '0';

			if ( preview ) {
				preview.removeAttribute( 'src' );
				preview.classList.add( 'is-empty' );
			}

			if ( filename ) {
				filename.textContent = strings.noFile;
			}

			return;
		}

		if ( ! pick || ! window.wp || ! window.wp.media ) {
			return;
		}

		event.preventDefault();

		var box = pick.closest( '.itt-image' );
		var mediaType = pick.getAttribute( 'data-media-type' ) || 'image';
		var isVideo = 'video' === mediaType;
		var frame = window.wp.media( {
			title: isVideo ? strings.chooseVideo : strings.chooseImage,
			button: { text: strings.useImage },
			library: { type: mediaType },
			multiple: false
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			var preview = box.querySelector( '.itt-image__preview' );
			var filename = box.querySelector( '.itt-image__file' );

			box.querySelector( '.itt-image__value' ).value = attachment.id;

			if ( filename ) {
				filename.textContent = attachment.filename || attachment.title;
			}

			if ( preview ) {
				preview.src = attachment.sizes && attachment.sizes.medium
					? attachment.sizes.medium.url
					: attachment.url;
				preview.classList.remove( 'is-empty' );
			}
		} );

		frame.open();
	} );
}() );
