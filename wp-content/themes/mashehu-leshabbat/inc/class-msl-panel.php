<?php
/**
 * The content panel.
 *
 * One screen holding every word, image and list on the campaign page. The
 * fields, their labels, their order and their sanitising all come from
 * MSL_Fields exactly as the page editor's boxes did — this is a different way
 * into the same schema and the same post meta, not a second copy of it.
 *
 * Three things make four hundred fields workable, and they are the reason this
 * screen exists at all: a section at a time instead of one endless column, a
 * search that says which sections a word appears in, and a language filter,
 * because every string here is a Hebrew/English pair and half of them are not
 * what you came to edit.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * The campaign content panel.
 */
final class MSL_Panel {

	/**
	 * Capability required to edit the campaign content.
	 */
	private const CAP = 'edit_pages';

	/**
	 * Menu slug.
	 */
	public const SLUG = 'msl-content';

	/**
	 * Hook the panel.
	 */
	public static function init(): void {
		// After MSL_Admin's default priority, never before it. See menu().
		add_action( 'admin_menu', array( self::class, 'menu' ), 11 );
		add_action( 'admin_menu', array( self::class, 'move_first' ), 12 );
		add_action( 'admin_post_msl_save_content', array( self::class, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
	}

	/**
	 * The hook suffix WordPress gave this screen.
	 *
	 * @var string
	 */
	private static string $hook = '';

	/**
	 * Add the panel to the campaign menu.
	 *
	 * This has to run *after* the parent menu exists, and the reason is not
	 * cosmetic. add_submenu_page() derives the callback's hook name from
	 * $admin_page_hooks[ $parent ], which add_menu_page() is what fills in. Ask
	 * for the submenu first and that lookup misses, so the callback is hooked as
	 * "admin_page_msl-content" — while admin.php, which recomputes the name once
	 * every menu is registered, goes looking for "<parent>_page_msl-content".
	 * The two never meet, the callback never runs, and the screen dies with
	 * "Invalid plugin page." Registering first to sort first cost the whole
	 * screen.
	 */
	public static function menu(): void {
		$hook = add_submenu_page(
			'msl-overview',
			__( 'תוכן העמוד', 'mashehu-leshabbat' ),
			__( 'תוכן העמוד', 'mashehu-leshabbat' ),
			self::CAP,
			self::SLUG,
			array( self::class, 'render' )
		);

		self::$hook = is_string( $hook ) ? $hook : '';
	}

	/**
	 * Put the panel at the top of the campaign menu.
	 *
	 * Editing the content is the daily job, so it belongs above the reports.
	 * Moving the entry afterwards is the safe way to get that: the ordering is
	 * presentation, and it must not decide when the page is registered.
	 */
	public static function move_first(): void {
		global $submenu;

		if ( empty( $submenu['msl-overview'] ) || ! is_array( $submenu['msl-overview'] ) ) {
			return;
		}

		foreach ( $submenu['msl-overview'] as $index => $item ) {
			if ( isset( $item[2] ) && self::SLUG === $item[2] ) {
				array_unshift( $submenu['msl-overview'], $item );
				unset( $submenu['msl-overview'][ $index + 1 ] );
				$submenu['msl-overview'] = array_values( $submenu['msl-overview'] );

				return;
			}
		}
	}

	/**
	 * Load the field assets on this screen only.
	 *
	 * Compared against the hook WordPress actually handed back, rather than a
	 * guess at how it spells the parent's title — which is the same assumption
	 * that broke the screen once already.
	 *
	 * @param string $hook Current admin screen hook.
	 */
	public static function assets( string $hook ): void {
		if ( '' === self::$hook || $hook !== self::$hook ) {
			return;
		}

		MSL_Metabox::enqueue();
	}

	/**
	 * The page whose content the panel edits.
	 *
	 * @return int
	 */
	private static function page_id(): int {
		$requested = isset( $_GET['msl_page'] ) ? absint( $_GET['msl_page'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $requested > 0 && isset( self::theme_pages()[ $requested ] ) ) {
			return $requested;
		}

		return MSL_Importer::page_id();
	}

	/**
	 * The theme's page templates, by the section template key they carry.
	 *
	 * @var array<string, string>
	 */
	private const TEMPLATES = array(
		'template-msl-home.php'  => 'home',
		'template-msl-about.php' => 'about',
	);

	/**
	 * Every page the panel can edit, as page id => section template key.
	 *
	 * Normally one page per template. A site that has built a second campaign
	 * page needs to be able to say which one it is editing rather than silently
	 * editing the first.
	 *
	 * @return array<int, string>
	 */
	private static function theme_pages(): array {
		static $memo = null;

		if ( null !== $memo ) {
			return $memo;
		}

		$memo = array();

		foreach ( get_posts(
			array(
				'post_type'              => 'page',
				'post_status'            => array( 'publish', 'draft', 'private' ),
				'numberposts'            => 40,
				'fields'                 => 'ids',
				'meta_key'               => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_compare'           => 'IN',
				'meta_value'             => array_keys( self::TEMPLATES ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		) as $id ) {
			$slug = (string) get_page_template_slug( (int) $id );

			if ( isset( self::TEMPLATES[ $slug ] ) ) {
				$memo[ (int) $id ] = self::TEMPLATES[ $slug ];
			}
		}

		return $memo;
	}

	/**
	 * Which set of sections a page carries.
	 *
	 * @param int $page_id Page id.
	 * @return string
	 */
	private static function template_key( int $page_id ): string {
		return self::theme_pages()[ $page_id ] ?? 'home';
	}

	/**
	 * How many form inputs this screen submits.
	 *
	 * PHP silently truncates a POST body at max_input_vars, which on a form this
	 * size means fields quietly reverting to their defaults with no error
	 * anywhere. Counting them is cheap; finding out the other way is not.
	 *
	 * @param int $post_id Page being edited.
	 * @return int
	 */
	private static function input_count( int $post_id ): int {
		$count = 0;

		foreach ( MSL_Fields::sections_for( self::template_key( $post_id ) ) as $section ) {
			$values = MSL_Meta::get( $section, $post_id );

			foreach ( MSL_Fields::fields( $section ) as $field ) {
				if ( 'repeater' !== $field['type'] ) {
					++$count;
					continue;
				}

				$rows = is_array( $values[ $field['key'] ] ?? null ) ? count( $values[ $field['key'] ] ) : 0;
				// One template row is rendered too, and its inputs are disabled
				// but still counted here so the headroom is honest.
				$count += ( $rows + 1 ) * max( 1, count( (array) $field['fields'] ) );
			}
		}

		return $count;
	}

	/* ---------------------------------------------------------------------
	 * Rendering
	 * ------------------------------------------------------------------ */

	/**
	 * The panel.
	 */
	public static function render(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'אין לך הרשאה לערוך את תוכן העמוד.', 'mashehu-leshabbat' ) );
		}

		$page_id  = self::page_id();
		$sections = MSL_Fields::sections_for( self::template_key( $page_id ) );

		echo '<div class="wrap msl-panel">';
		printf( '<h1 class="wp-heading-inline">%s</h1>', esc_html__( 'תוכן העמוד', 'mashehu-leshabbat' ) );

		if ( 0 === $page_id ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div></div>',
				esc_html__( 'לא נמצא עמוד שמשתמש בתבנית הקמפיין. יש ליצור עמוד ולבחור בתבנית "אור לשבת · עמוד הקמפיין".', 'mashehu-leshabbat' )
			);

			return;
		}

		self::render_notices( $page_id );
		self::render_toolbar( $page_id );

		printf(
			'<form method="post" action="%s" class="msl-panel__form" data-msl-panel-form>',
			esc_url( admin_url( 'admin-post.php' ) )
		);

		wp_nonce_field( MSL_Metabox::NONCE_ACTION, MSL_Metabox::NONCE_NAME );
		printf( '<input type="hidden" name="action" value="%s">', 'msl_save_content' );
		printf( '<input type="hidden" name="msl_page" value="%d">', (int) $page_id );
		printf(
			'<input type="hidden" name="msl_open" value="%s" data-msl-open>',
			esc_attr( (string) ( $sections[0] ?? '' ) )
		);

		echo '<div class="msl-panel__body">';
		self::render_nav( $sections );

		echo '<div class="msl-panel__main">';

		foreach ( $sections as $index => $section ) {
			printf(
				'<section class="msl-panel__section%s" id="msl-section-%s" data-msl-section="%s" aria-labelledby="msl-section-%s-title"%s>',
				0 === $index ? ' is-active' : '',
				esc_attr( $section ),
				esc_attr( $section ),
				esc_attr( $section ),
				0 === $index ? '' : ' hidden'
			);

			printf(
				'<h2 class="msl-panel__title" id="msl-section-%s-title">%s</h2>',
				esc_attr( $section ),
				esc_html( (string) MSL_Fields::all()[ $section ]['label'] )
			);

			MSL_Metabox::render_section_fields( $section, $page_id );

			printf(
				'<p class="msl-panel__empty" data-msl-empty hidden>%s</p>',
				esc_html__( 'אין שדה בחלק הזה שמתאים לחיפוש.', 'mashehu-leshabbat' )
			);

			echo '</section>';
		}

		echo '</div></div>';

		printf(
			'<div class="msl-panel__save"><button type="submit" class="button button-primary button-hero">%s</button><span class="msl-panel__saved" data-msl-dirty hidden>%s</span></div>',
			esc_html__( 'שמירת כל התוכן', 'mashehu-leshabbat' ),
			esc_html__( 'יש שינויים שלא נשמרו', 'mashehu-leshabbat' )
		);

		echo '</form></div>';
	}

	/**
	 * Result and capacity notices.
	 *
	 * @param int $page_id Page being edited.
	 */
	private static function render_notices( int $page_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['msl_saved'] ) ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html__( 'התוכן נשמר.', 'mashehu-leshabbat' )
			);
		}

		$inputs = self::input_count( $page_id );
		$limit  = (int) ini_get( 'max_input_vars' );

		// A little headroom, because every repeater row the client adds before
		// saving counts too.
		if ( $limit > 0 && $inputs + 200 > $limit ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: number of form inputs, 2: the max_input_vars limit. */
						__( 'בעמוד הזה %1$d שדות, והשרת מקבל לכל היותר %2$d ערכים בשמירה אחת (max_input_vars). שמירה עלולה לקצץ שדות בשקט. יש להעלות את הערך ב-php.ini לפני שמוסיפים עוד שורות לרשימות.', 'mashehu-leshabbat' ),
						$inputs,
						$limit
					)
				)
			);
		}
	}

	/**
	 * Page picker, search, language filter and the link to the live page.
	 *
	 * @param int $page_id Page being edited.
	 */
	private static function render_toolbar( int $page_id ): void {
		echo '<div class="msl-panel__bar">';

		printf(
			'<p class="msl-panel__search"><label class="screen-reader-text" for="msl-search">%s</label>
			<input type="search" id="msl-search" class="regular-text" placeholder="%s" data-msl-search autocomplete="off">
			<span class="msl-panel__hits" data-msl-hits aria-live="polite"></span></p>',
			esc_html__( 'חיפוש שדה', 'mashehu-leshabbat' ),
			esc_attr__( 'חיפוש שדה או טקסט…', 'mashehu-leshabbat' )
		);

		echo '<p class="msl-panel__langs">';
		printf( '<span class="msl-panel__langs-label">%s</span>', esc_html__( 'שפה:', 'mashehu-leshabbat' ) );

		$langs = array(
			'all' => __( 'הכול', 'mashehu-leshabbat' ),
			'he'  => __( 'עברית', 'mashehu-leshabbat' ),
			'en'  => __( 'English', 'mashehu-leshabbat' ),
		);

		foreach ( $langs as $value => $label ) {
			printf(
				'<button type="button" class="button button-small%s" data-msl-lang="%s">%s</button>',
				'all' === $value ? ' button-primary' : '',
				esc_attr( $value ),
				esc_html( $label )
			);
		}

		echo '</p>';

		$pages = array_keys( self::theme_pages() );

		if ( count( $pages ) > 1 ) {
			echo '<p class="msl-panel__pages">';
			printf( '<span class="msl-panel__langs-label">%s</span>', esc_html__( 'עמוד:', 'mashehu-leshabbat' ) );

			foreach ( $pages as $id ) {
				printf(
					'<a class="button button-small%s" href="%s">%s</a>',
					$id === $page_id ? ' button-primary' : '',
					esc_url( admin_url( 'admin.php?page=' . self::SLUG . '&msl_page=' . $id ) ),
					esc_html( get_the_title( $id ) )
				);
			}

			echo '</p>';
		}

		printf(
			'<p class="msl-panel__links"><a href="%s">%s</a> · <a href="%s">%s</a></p>',
			esc_url( (string) get_permalink( $page_id ) ),
			esc_html__( 'צפייה בעמוד', 'mashehu-leshabbat' ),
			esc_url( (string) get_edit_post_link( $page_id, 'raw' ) ),
			esc_html__( 'הגדרות העמוד', 'mashehu-leshabbat' )
		);

		echo '</div>';
	}

	/**
	 * The section list.
	 *
	 * @param string[] $sections Section keys in schema order.
	 */
	private static function render_nav( array $sections ): void {
		echo '<nav class="msl-panel__nav" aria-label="' . esc_attr__( 'חלקי העמוד', 'mashehu-leshabbat' ) . '"><ul>';

		foreach ( $sections as $index => $section ) {
			printf(
				'<li><button type="button" class="msl-panel__tab%s" data-msl-tab="%s" aria-current="%s">
				<span class="msl-panel__tab-label">%s</span>
				<span class="msl-panel__tab-hits" data-msl-tab-hits hidden></span></button></li>',
				0 === $index ? ' is-active' : '',
				esc_attr( $section ),
				0 === $index ? 'true' : 'false',
				esc_html( (string) MSL_Fields::all()[ $section ]['label'] )
			);
		}

		echo '</ul></nav>';
	}

	/* ---------------------------------------------------------------------
	 * Saving
	 * ------------------------------------------------------------------ */

	/**
	 * Persist the panel's submission.
	 *
	 * The payload has the same shape the meta boxes posted, so this walks the
	 * same sections through the same MSL_Meta::save() and the same per-field
	 * sanitisers. Nothing is trusted from the form beyond which page it names,
	 * and that has to be a page the visitor may edit and that uses the campaign
	 * template.
	 */
	public static function handle_save(): void {
		$nonce = isset( $_POST[ MSL_Metabox::NONCE_NAME ] )
			? sanitize_text_field( wp_unslash( (string) $_POST[ MSL_Metabox::NONCE_NAME ] ) )
			: '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, MSL_Metabox::NONCE_ACTION ) ) {
			wp_die( esc_html__( 'פג תוקף הטופס. יש לרענן את העמוד ולנסות שוב.', 'mashehu-leshabbat' ), '', array( 'response' => 403 ) );
		}

		$page_id = isset( $_POST['msl_page'] ) ? absint( $_POST['msl_page'] ) : 0;

		if ( 0 === $page_id || ! isset( self::theme_pages()[ $page_id ] ) ) {
			wp_die( esc_html__( 'העמוד המבוקש אינו עמוד קמפיין.', 'mashehu-leshabbat' ), '', array( 'response' => 400 ) );
		}

		if ( ! current_user_can( 'edit_post', $page_id ) ) {
			wp_die( esc_html__( 'אין לך הרשאה לערוך את העמוד הזה.', 'mashehu-leshabbat' ), '', array( 'response' => 403 ) );
		}

		// Values are sanitised per field by MSL_Meta::sanitize() before storage.
		$submitted = isset( $_POST['msl'] ) && is_array( $_POST['msl'] )
			? wp_unslash( $_POST['msl'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: array();

		foreach ( MSL_Fields::sections_for( self::template_key( $page_id ) ) as $section ) {
			$values = $submitted[ $section ] ?? null;

			if ( ! is_array( $values ) ) {
				continue;
			}

			MSL_Meta::save( $page_id, $section, $values );
		}

		MSL_Stats::flush( $page_id );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => self::SLUG,
					'msl_page'  => $page_id,
					'msl_saved' => 1,
				),
				admin_url( 'admin.php' )
			) . '#' . self::submitted_section()
		);

		exit;
	}

	/**
	 * Which section the visitor had open, so the redirect lands back on it.
	 *
	 * @return string
	 */
	private static function submitted_section(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$section = isset( $_POST['msl_open'] ) ? sanitize_key( wp_unslash( (string) $_POST['msl_open'] ) ) : '';

		return '' !== $section && array_key_exists( $section, MSL_Fields::all() ) ? 'msl-section-' . $section : '';
	}
}
