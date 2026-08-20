<?php
/**
 * Page-editor UI for the campaign content.
 *
 * Renders one meta box per design section, built from MSL_Fields, so the whole
 * page copy is edited on the page itself with core WordPress fields only — no
 * field plugin, no page builder.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Meta boxes for the campaign template.
 */
final class MSL_Metabox {

	/**
	 * Nonce action and field name.
	 */
	private const NONCE_ACTION = 'msl_save_content';
	private const NONCE_NAME   = 'msl_content_nonce';

	/**
	 * Hook the editor UI.
	 */
	public static function init(): void {
		add_action( 'add_meta_boxes_page', array( self::class, 'register' ) );
		add_action( 'save_post_page', array( self::class, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
		add_filter( 'use_block_editor_for_post', array( self::class, 'classic_editor' ), 10, 2 );
	}

	/**
	 * Edit the campaign page in the classic editor.
	 *
	 * The page has no block content at all — its body is empty on purpose and
	 * every word lives in the twelve section boxes below it. The block editor
	 * folds classic meta boxes into a short drawer pinned to the bottom of the
	 * screen, which turns four hundred fields into a letterbox. The classic
	 * screen gives them the whole page, which is where the work actually
	 * happens. Every other page on the site is untouched.
	 *
	 * @param bool    $use  Whether core intends to use the block editor.
	 * @param WP_Post $post Post being edited.
	 * @return bool
	 */
	public static function classic_editor( bool $use, WP_Post $post ): bool {
		return '' === self::template_key( $post ) ? $use : false;
	}

	/**
	 * The template key used by a page, or an empty string.
	 *
	 * @param WP_Post $post Page being edited.
	 * @return string
	 */
	private static function template_key( WP_Post $post ): string {
		return 'template-msl-home.php' === (string) get_page_template_slug( $post ) ? 'home' : '';
	}

	/**
	 * Register a meta box per section.
	 *
	 * @param WP_Post $post Page being edited.
	 */
	public static function register( WP_Post $post ): void {
		$template = self::template_key( $post );

		if ( '' === $template ) {
			return;
		}

		foreach ( MSL_Fields::sections_for( $template ) as $section ) {
			add_meta_box(
				'msl-section-' . $section,
				MSL_Fields::all()[ $section ]['label'],
				array( self::class, 'render' ),
				'page',
				'normal',
				'default',
				array( 'section' => $section )
			);
		}
	}

	/**
	 * Enqueue the editor assets, only on a page using the campaign template.
	 *
	 * @param string $hook Current admin screen hook.
	 */
	public static function assets( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$post = get_post();

		if ( ! $post instanceof WP_Post || '' === self::template_key( $post ) ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style( 'msl-admin', MSL_URI . 'assets/css/msl-admin.css', array(), MSL_Theme::asset_version( 'assets/css/msl-admin.css' ) );
		wp_enqueue_script( 'msl-admin', MSL_URI . 'assets/js/msl-admin.js', array(), MSL_Theme::asset_version( 'assets/js/msl-admin.js' ), true );
		wp_localize_script(
			'msl-admin',
			'mslAdmin',
			array(
				'chooseImage' => __( 'בחירת תמונה', 'mashehu-leshabbat' ),
				'useImage'    => __( 'שימוש בתמונה', 'mashehu-leshabbat' ),
				'removeRow'   => __( 'האם למחוק את השורה?', 'mashehu-leshabbat' ),
				'newRow'      => __( 'שורה חדשה', 'mashehu-leshabbat' ),
			)
		);
	}

	/**
	 * Render one section meta box.
	 *
	 * @param WP_Post              $post Page being edited.
	 * @param array<string, mixed> $args Callback args carrying the section key.
	 */
	public static function render( WP_Post $post, array $args ): void {
		$section = (string) ( $args['args']['section'] ?? '' );

		if ( '' === $section ) {
			return;
		}

		static $nonce_printed = false;

		if ( ! $nonce_printed ) {
			wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
			$nonce_printed = true;
		}

		$values = MSL_Meta::get( $section, $post->ID );

		echo '<div class="msl-fields">';

		foreach ( MSL_Fields::fields( $section ) as $field ) {
			self::render_field( $field, $values[ $field['key'] ] ?? '', 'msl[' . $section . ']' );
		}

		echo '</div>';
	}

	/**
	 * Render a single field.
	 *
	 * @param array<string, mixed> $field  Field definition.
	 * @param mixed                $value  Current value.
	 * @param string               $prefix Name prefix for the input.
	 */
	private static function render_field( array $field, mixed $value, string $prefix ): void {
		$name = $prefix . '[' . $field['key'] . ']';
		$id   = sanitize_html_class( str_replace( array( '[', ']' ), array( '-', '' ), $name ) );

		if ( 'repeater' === $field['type'] ) {
			self::render_repeater( $field, is_array( $value ) ? $value : array(), $name );
			return;
		}

		$is_english = str_ends_with( (string) $field['key'], '_en' );

		printf(
			'<p class="msl-field msl-field--%s%s">',
			esc_attr( (string) $field['type'] ),
			$is_english ? ' msl-field--en' : ''
		);

		if ( 'image' === $field['type'] ) {
			// The stored value is a hidden input, so a plain caption is the honest label here.
			printf( '<span class="msl-label"><strong>%s</strong></span>', esc_html( (string) $field['label'] ) );
		} elseif ( 'checkbox' !== $field['type'] ) {
			printf( '<label for="%s"><strong>%s</strong></label>', esc_attr( $id ), esc_html( (string) $field['label'] ) );
		}

		switch ( $field['type'] ) {
			case 'textarea':
				printf(
					'<textarea id="%s" name="%s" rows="%d" class="widefat">%s</textarea>',
					esc_attr( $id ),
					esc_attr( $name ),
					absint( $field['rows'] ?? 3 ),
					esc_textarea( (string) $value )
				);
				break;

			case 'checkbox':
				printf(
					'<label class="msl-checkbox"><input type="checkbox" id="%s" name="%s" value="1"%s> %s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( 1, (int) $value, false ),
					esc_html( (string) $field['label'] )
				);
				break;

			case 'select':
				printf( '<select id="%s" name="%s">', esc_attr( $id ), esc_attr( $name ) );
				foreach ( (array) ( $field['options'] ?? array() ) as $option => $label ) {
					printf(
						'<option value="%s"%s>%s</option>',
						esc_attr( (string) $option ),
						selected( (string) $option, (string) $value, false ),
						esc_html( (string) $label )
					);
				}
				echo '</select>';
				break;

			case 'number':
			case 'decimal':
				printf(
					'<input type="number" id="%s" name="%s" value="%s" min="%s" max="%s" step="%s" class="msl-input-number">',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					esc_attr( (string) ( $field['min'] ?? '' ) ),
					esc_attr( (string) ( $field['max'] ?? '' ) ),
					'decimal' === $field['type'] ? 'any' : '1'
				);
				break;

			case 'image':
				self::render_media( $id, $name, absint( $value ) );
				break;

			default:
				printf(
					'<input type="%s" id="%s" name="%s" value="%s" class="widefat">',
					esc_attr( 'url' === $field['type'] ? 'url' : ( 'email' === $field['type'] ? 'email' : 'text' ) ),
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
		}

		if ( ! empty( $field['help'] ) ) {
			printf( '<span class="msl-help description">%s</span>', esc_html( (string) $field['help'] ) );
		}

		echo '</p>';
	}

	/**
	 * Render the media picker for an image field.
	 *
	 * @param string $id    Input id.
	 * @param string $name  Input name.
	 * @param int    $value Attachment ID.
	 */
	private static function render_media( string $id, string $name, int $value ): void {
		$thumb = $value > 0 ? (string) wp_get_attachment_image_url( $value, 'medium' ) : '';

		echo '<span class="msl-image">';
		printf(
			'<img src="%s" alt="" class="msl-image__preview%s">',
			esc_url( $thumb ),
			'' === $thumb ? ' is-empty' : ''
		);
		printf(
			'<input type="hidden" id="%s" name="%s" value="%d" class="msl-image__value">',
			esc_attr( $id ),
			esc_attr( $name ),
			$value
		);
		printf(
			'<button type="button" class="button msl-image__pick">%s</button> <button type="button" class="button-link msl-image__clear">%s</button>',
			esc_html__( 'בחירת תמונה', 'mashehu-leshabbat' ),
			esc_html__( 'איפוס', 'mashehu-leshabbat' )
		);
		echo '</span>';
	}

	/**
	 * Render a repeater: existing rows plus a hidden template row for the JS.
	 *
	 * @param array<string, mixed>             $field Field definition.
	 * @param array<int, array<string, mixed>> $rows  Current rows.
	 * @param string                           $name  Name prefix.
	 */
	private static function render_repeater( array $field, array $rows, string $name ): void {
		printf(
			'<div class="msl-repeater" data-msl-repeater data-label-field="%s"><h4 class="msl-repeater__title">%s</h4>',
			esc_attr( (string) $field['label_field'] ),
			esc_html( (string) $field['label'] )
		);

		if ( ! empty( $field['help'] ) ) {
			printf( '<p class="msl-help description">%s</p>', esc_html( (string) $field['help'] ) );
		}

		echo '<div class="msl-repeater__rows">';

		foreach ( array_values( $rows ) as $index => $row ) {
			self::render_repeater_row( $field, is_array( $row ) ? $row : array(), $name, (string) $index );
		}

		echo '</div>';

		echo '<template class="msl-repeater__template">';
		self::render_repeater_row( $field, array(), $name, '__i__' );
		echo '</template>';

		printf(
			'<button type="button" class="button msl-repeater__add">%s</button></div>',
			esc_html__( '+ הוספת שורה', 'mashehu-leshabbat' )
		);
	}

	/**
	 * Render one repeater row.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param array<string, mixed> $row   Row values.
	 * @param string               $name  Name prefix.
	 * @param string               $index Row index, or the JS placeholder.
	 */
	private static function render_repeater_row( array $field, array $row, string $name, string $index ): void {
		$summary = (string) ( $row[ $field['label_field'] ] ?? '' );

		echo '<div class="msl-row" data-msl-row>';
		printf(
			'<div class="msl-row__head"><span class="msl-row__summary">%s</span><button type="button" class="button-link msl-row__remove" aria-label="%s">%s</button></div>',
			esc_html( '' !== $summary ? wp_html_excerpt( $summary, 70, '…' ) : __( 'שורה חדשה', 'mashehu-leshabbat' ) ),
			esc_attr__( 'מחיקת השורה', 'mashehu-leshabbat' ),
			esc_html__( 'מחיקה', 'mashehu-leshabbat' )
		);
		echo '<div class="msl-row__body">';

		foreach ( (array) $field['fields'] as $sub ) {
			self::render_field( $sub, $row[ $sub['key'] ] ?? self::sub_default( $sub ), $name . '[' . $index . ']' );
		}

		echo '</div></div>';
	}

	/**
	 * Sensible starting value for a brand-new repeater row.
	 *
	 * @param array<string, mixed> $field Sub-field definition.
	 * @return mixed
	 */
	private static function sub_default( array $field ): mixed {
		return match ( $field['type'] ) {
			'image', 'checkbox', 'number', 'decimal' => 0,
			'select' => (string) ( array_key_first( (array) ( $field['options'] ?? array() ) ) ?? '' ),
			default  => '',
		};
	}

	/**
	 * Persist the submitted sections.
	 *
	 * @param int     $post_id Page ID.
	 * @param WP_Post $post    Page object.
	 */
	public static function save( int $post_id, WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$nonce = isset( $_POST[ self::NONCE_NAME ] )
			? sanitize_text_field( wp_unslash( (string) $_POST[ self::NONCE_NAME ] ) )
			: '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( '' === self::template_key( $post ) ) {
			return;
		}

		// Values are sanitised per field by MSL_Meta::sanitize() before storage.
		$submitted = isset( $_POST['msl'] ) && is_array( $_POST['msl'] )
			? wp_unslash( $_POST['msl'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: array();

		foreach ( MSL_Fields::sections_for( 'home' ) as $section ) {
			$values = $submitted[ $section ] ?? null;

			if ( ! is_array( $values ) ) {
				continue;
			}

			MSL_Meta::save( $post_id, $section, $values );
		}

		MSL_Stats::flush( $post_id );
	}
}
