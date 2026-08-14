<?php
/**
 * Page-editor UI for the ITT content.
 *
 * Renders one meta box per design section, built from ITT_Fields, so the whole
 * page copy is edited on the page itself with core WordPress fields only — no
 * field plugin, no page builder.
 *
 * @package ITT_Landing
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Meta boxes for the landing and thank-you templates.
 */
final class ITT_Metabox {

	/**
	 * Nonce action and field name.
	 */
	private const NONCE_ACTION = 'itt_save_content';
	private const NONCE_NAME   = 'itt_content_nonce';

	/**
	 * Hook the editor UI.
	 */
	public static function init(): void {
		add_action( 'add_meta_boxes_page', array( self::class, 'register' ) );
		add_action( 'save_post_page', array( self::class, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
	}

	/**
	 * The ITT template key used by a page, or an empty string.
	 *
	 * @param WP_Post $post Page being edited.
	 * @return string
	 */
	private static function template_key( WP_Post $post ): string {
		return match ( (string) get_page_template_slug( $post ) ) {
			'template-itt-landing.php'   => 'landing',
			'template-itt-thank-you.php' => 'thank-you',
			default                      => '',
		};
	}

	/**
	 * Register a meta box per section of the page's template.
	 *
	 * @param WP_Post $post Page being edited.
	 */
	public static function register( WP_Post $post ): void {
		$template = self::template_key( $post );

		if ( '' === $template ) {
			return;
		}

		foreach ( ITT_Fields::sections_for( $template ) as $section ) {
			add_meta_box(
				'itt-section-' . $section,
				'ITT · ' . ITT_Fields::all()[ $section ]['label'],
				array( self::class, 'render' ),
				'page',
				'normal',
				'default',
				array( 'section' => $section )
			);
		}
	}

	/**
	 * Enqueue the editor assets, only on a page using an ITT template.
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

		wp_enqueue_style( 'itt-admin', ITT_URI . 'assets/css/itt-admin.css', array(), ITT_VERSION );
		wp_enqueue_script( 'itt-admin', ITT_URI . 'assets/js/itt-admin.js', array(), ITT_VERSION, true );
		wp_localize_script(
			'itt-admin',
			'ittAdmin',
			array(
				'chooseImage' => __( 'בחירת תמונה', 'itt-landing' ),
				'chooseVideo' => __( 'בחירת וידאו', 'itt-landing' ),
				'useImage'    => __( 'שימוש בקובץ', 'itt-landing' ),
				'noFile'      => __( 'לא נבחר קובץ', 'itt-landing' ),
				'removeRow'   => __( 'האם למחוק את השורה?', 'itt-landing' ),
				'newRow'      => __( 'שורה חדשה', 'itt-landing' ),
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

		$values = ITT_Meta::get( $section, $post->ID );

		echo '<div class="itt-fields">';

		foreach ( ITT_Fields::fields( $section ) as $field ) {
			self::render_field( $field, $values[ $field['key'] ] ?? '', 'itt[' . $section . ']' );
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

		echo '<p class="itt-field itt-field--' . esc_attr( $field['type'] ) . '">';

		if ( in_array( $field['type'], array( 'image', 'video' ), true ) ) {
			// The stored value is a hidden input, so a plain caption is the honest label here.
			printf( '<span class="itt-label"><strong>%s</strong></span>', esc_html( $field['label'] ) );
		} elseif ( 'checkbox' !== $field['type'] ) {
			printf( '<label for="%s"><strong>%s</strong></label>', esc_attr( $id ), esc_html( $field['label'] ) );
		}

		switch ( $field['type'] ) {
			case 'textarea':
			case 'rich':
			case 'lines':
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
					'<label class="itt-checkbox"><input type="checkbox" id="%s" name="%s" value="1"%s> %s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( 1, (int) $value, false ),
					esc_html( $field['label'] )
				);
				break;

			case 'select':
				printf( '<select id="%s" name="%s">', esc_attr( $id ), esc_attr( $name ) );
				foreach ( (array) ( $field['options'] ?? array() ) as $option => $label ) {
					printf(
						'<option value="%s"%s>%s</option>',
						esc_attr( $option ),
						selected( $option, (string) $value, false ),
						esc_html( $label )
					);
				}
				echo '</select>';
				break;

			case 'image':
			case 'video':
				self::render_media( $id, $name, absint( $value ), (string) $field['type'] );
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
			printf( '<span class="itt-help description">%s</span>', esc_html( $field['help'] ) );
		}

		echo '</p>';
	}

	/**
	 * Render the media picker for an image or video field.
	 *
	 * @param string $id    Input id.
	 * @param string $name  Input name.
	 * @param int    $value Attachment ID.
	 * @param string $type  'image' or 'video'.
	 */
	private static function render_media( string $id, string $name, int $value, string $type ): void {
		$is_video = 'video' === $type;
		$thumb    = $value > 0 && ! $is_video ? (string) wp_get_attachment_image_url( $value, 'medium' ) : '';
		$filename = $value > 0 ? basename( (string) get_attached_file( $value ) ) : '';

		echo '<span class="itt-image">';

		if ( $is_video ) {
			printf(
				'<span class="itt-image__file">%s</span>',
				esc_html( '' !== $filename ? $filename : __( 'לא נבחר קובץ', 'itt-landing' ) )
			);
		} else {
			printf(
				'<img src="%s" alt="" class="itt-image__preview%s">',
				esc_url( $thumb ),
				'' === $thumb ? ' is-empty' : ''
			);
		}

		printf(
			'<input type="hidden" id="%s" name="%s" value="%d" class="itt-image__value">',
			esc_attr( $id ),
			esc_attr( $name ),
			$value
		);
		printf(
			'<button type="button" class="button itt-image__pick" data-media-type="%s">%s</button> <button type="button" class="button-link itt-image__clear">%s</button>',
			esc_attr( $type ),
			esc_html( $is_video ? __( 'בחירת וידאו', 'itt-landing' ) : __( 'בחירת תמונה', 'itt-landing' ) ),
			esc_html__( 'איפוס', 'itt-landing' )
		);
		echo '</span>';
	}

	/**
	 * Render a repeater: existing rows plus a hidden template row for the JS.
	 *
	 * @param array<string, mixed>            $field Field definition.
	 * @param array<int, array<string, mixed>> $rows  Current rows.
	 * @param string                          $name  Name prefix.
	 */
	private static function render_repeater( array $field, array $rows, string $name ): void {
		printf(
			'<div class="itt-repeater" data-itt-repeater data-label-field="%s"><h4 class="itt-repeater__title">%s</h4><div class="itt-repeater__rows">',
			esc_attr( (string) $field['label_field'] ),
			esc_html( $field['label'] )
		);

		foreach ( array_values( $rows ) as $index => $row ) {
			self::render_repeater_row( $field, is_array( $row ) ? $row : array(), $name, (string) $index );
		}

		echo '</div>';

		echo '<template class="itt-repeater__template">';
		self::render_repeater_row( $field, array(), $name, '__i__' );
		echo '</template>';

		printf(
			'<button type="button" class="button itt-repeater__add">%s</button></div>',
			esc_html__( '+ הוספת שורה', 'itt-landing' )
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

		echo '<div class="itt-row" data-itt-row>';
		printf(
			'<div class="itt-row__head"><span class="itt-row__summary">%s</span><button type="button" class="button-link itt-row__remove" aria-label="%s">%s</button></div>',
			esc_html( '' !== $summary ? wp_html_excerpt( $summary, 70, '…' ) : __( 'שורה חדשה', 'itt-landing' ) ),
			esc_attr__( 'מחיקת השורה', 'itt-landing' ),
			esc_html__( 'מחיקה', 'itt-landing' )
		);
		echo '<div class="itt-row__body">';

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
			'image', 'video', 'checkbox' => 0,
			'select'            => (string) ( array_key_first( (array) ( $field['options'] ?? array() ) ) ?? '' ),
			default             => '',
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

		$template = self::template_key( $post );

		if ( '' === $template ) {
			return;
		}

		// Values are sanitised per field by ITT_Meta::sanitize() before storage.
		$submitted = isset( $_POST['itt'] ) && is_array( $_POST['itt'] )
			? wp_unslash( $_POST['itt'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: array();

		foreach ( ITT_Fields::sections_for( $template ) as $section ) {
			$values = $submitted[ $section ] ?? null;

			if ( ! is_array( $values ) ) {
				continue;
			}

			ITT_Meta::save( $post_id, $section, $values );
		}
	}
}
