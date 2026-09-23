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
		add_action( 'admin_post_msl_zmanim_recheck', array( self::class, 'handle_zmanim_recheck' ) );
		add_action( 'admin_post_msl_send_reminders', array( self::class, 'handle_send_reminders' ) );
		add_action( 'admin_post_msl_copy_rerun', array( self::class, 'handle_copy_rerun' ) );
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
				'meta_value'             => array_keys( MSL_Theme::SECTION_SETS ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		) as $id ) {
			$slug = (string) get_page_template_slug( (int) $id );

			if ( isset( MSL_Theme::SECTION_SETS[ $slug ] ) ) {
				$memo[ (int) $id ] = MSL_Theme::SECTION_SETS[ $slug ];
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

		self::render_login_notice();
		self::render_zmanim_notice( $page_id );
		self::render_reminder_notice( $page_id );
		self::render_copy_notice();

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
	 * Say plainly why the sign-in button is not on the site.
	 *
	 * It is hidden until the campaign has a Google client, and a button that
	 * leads to a Google error page would be worse — but a feature that is built,
	 * switched off and silent is indistinguishable from a feature that was never
	 * built. This is the screen that should say which of the two it is.
	 */
	private static function render_login_notice(): void {
		$auth    = MSL_Meta::get( 'auth', MSL_Importer::page_id() );
		$on      = 1 === (int) ( $auth['login_enabled'] ?? 0 );
		$has_id  = '' !== MSL_Auth::client_id();
		$has_key = '' !== MSL_Auth::client_secret();

		if ( $on && $has_id && $has_key ) {
			printf(
				'<div class="notice notice-success"><p><strong>%s</strong> %s</p></div>',
				esc_html__( 'ההתחברות עם גוגל פעילה.', 'mashehu-leshabbat' ),
				esc_html__( 'כפתור ההתחברות מוצג באתר, וכל מי שמתחבר מקבל קישור אישי קבוע ומעקב אחרי מי שהצטרף דרכו.', 'mashehu-leshabbat' )
			);

			return;
		}

		$missing = array();

		if ( ! $on ) {
			$missing[] = __( 'התיבה "להפעיל התחברות עם גוגל" אינה מסומנת', 'mashehu-leshabbat' );
		}

		if ( ! $has_id ) {
			$missing[] = __( 'חסר Google Client ID', 'mashehu-leshabbat' );
		}

		if ( ! $has_key ) {
			$missing[] = __( 'חסר Google Client Secret', 'mashehu-leshabbat' );
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s</p><p>%s</p><p><code>%s</code></p><p>%s</p></div>',
			esc_html__( 'ההתחברות עם גוגל בנויה אבל אינה פעילה, ולכן כפתור ההתחברות לא מוצג באתר.', 'mashehu-leshabbat' ),
			esc_html( implode( ' · ', $missing ) . '.' ),
			esc_html__( 'ב-Google Cloud › APIs & Services › Credentials יש ליצור OAuth 2.0 Client ID מסוג Web application, ולהוסיף תחת Authorized redirect URIs בדיוק את הכתובת הזאת:', 'mashehu-leshabbat' ),
			esc_html( MSL_Auth::redirect_uri() ),
			esc_html__( 'אחר כך להדביק את שני המפתחות בחלק "12 · התחברות וחשבונות" ולסמן את תיבת ההפעלה.', 'mashehu-leshabbat' )
		);
	}

	/**
	 * Say whether the Shabbat times are actually arriving.
	 *
	 * The same reasoning as the sign-in notice, and a sharper need: this feature
	 * fails silently by design. When hebcal.com cannot be reached the site falls
	 * back to the hand-set day and time and looks completely normal, which is
	 * right for the visitor and useless for whoever has to notice. So the panel
	 * prints the times it actually holds — if the hour on this screen is the
	 * hour on the calendar, the feature is working.
	 */
	private static function render_zmanim_notice( int $page_id ): void {
		/*
		 * The page being edited, not the one the importer happens to remember.
		 *
		 * This used to read MSL_Importer::page_id(), which is the campaign page
		 * that option points at. On an install where the campaign lives on a
		 * page created by hand rather than by the importer, that is a different
		 * page — so the panel described settings nobody was editing, and a
		 * switch turned on here was reported from over there. The front end has
		 * never had the problem, because it reads the page it is rendering.
		 */
		$zmanim = MSL_Meta::get( 'zmanim', $page_id );

		if ( ! MSL_Zmanim::enabled( $zmanim ) ) {
			printf(
				'<div class="notice notice-info"><p><strong>%s</strong> %s</p></div>',
				esc_html__( 'זמני השבת נלקחים מההגדרה הידנית.', 'mashehu-leshabbat' ),
				esc_html__( 'בחלק "01א · זמני שבת ותאריך עברי" אפשר לסמן את המשיכה האוטומטית, ואז כניסת השבת, צאת השבת, שם הפרשה והתאריך העברי יתעדכנו מעצמם מדי שבוע.', 'mashehu-leshabbat' )
			);

			return;
		}

		$week = MSL_Zmanim::week( $zmanim );

		if ( null === $week ) {
			$failure = MSL_Zmanim::last_failure();

			printf(
				'<div class="notice notice-warning"><p><strong>%s</strong> %s</p>%s<p>%s</p></div>',
				esc_html__( 'המשיכה האוטומטית של זמני השבת פעילה, אבל לא התקבלה תשובה מ-hebcal.com.', 'mashehu-leshabbat' ),
				esc_html__( 'האתר ממשיך לעבוד לפי היום והשעה שבחלק 01, והניסיון יחזור מעצמו בעוד כמה דקות.', 'mashehu-leshabbat' ),
				null === $failure ? '' : sprintf(
					'<p><code>%s</code> <span class="description">%s</span></p>',
					esc_html( (string) $failure['reason'] ),
					esc_html(
						sprintf(
							/* translators: %s: how long ago, e.g. "5 minutes". */
							__( 'לפני %s. חסימה של פניות החוצה מהשרת נראית כך; במקרה כזה יש לפנות לחברת האחסון ולבקש לאפשר פניות ל-hebcal.com.', 'mashehu-leshabbat' ),
							human_time_diff( (int) $failure['when'] )
						)
					)
				),
				self::zmanim_recheck_button()
			);

			return;
		}

		$zone = MSL_Zmanim::zone( $zmanim );
		$date = MSL_Zmanim::hebrew_date( $zmanim );

		// The name of the coming Shabbat exactly as the page will print it, so
		// this notice cannot quietly disagree with the site it is describing.
		$portion = (string) $week['parsha_he'];
		$named   = '' !== $portion ? 'פרשת ' . $portion : (string) $week['holiday_he'];

		printf(
			'<div class="notice notice-success"><p><strong>%s</strong> %s</p><p>%s</p></div>',
			esc_html__( 'זמני השבת מתעדכנים מהרשת.', 'mashehu-leshabbat' ),
			esc_html(
				sprintf(
					/* translators: 1: place name, 2: the coming Shabbat's name, 3: candle lighting time, 4: havdalah time, 5: Hebrew date. */
					__( 'לפי %1$s: %2$s, כניסת השבת %3$s, צאת השבת %4$s. היום %5$s.', 'mashehu-leshabbat' ),
					MSL_Zmanim::place_name( $zmanim, $week ),
					'' !== $named ? $named : '—',
					(string) wp_date( 'H:i', (int) $week['candles'], $zone ),
					(int) $week['havdalah'] > 0 ? (string) wp_date( 'H:i', (int) $week['havdalah'], $zone ) : '—',
					null !== $date ? $date['he'] : '—'
				)
			),
			self::zmanim_recheck_button()
		);
	}

	/**
	 * Empty the times cache and go straight back to the panel.
	 *
	 * The next read on the screen we return to does the actual call, so the
	 * notice that greets them is the result of a request made a second ago.
	 */
	public static function handle_zmanim_recheck(): void {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'אין הרשאה.', 'mashehu-leshabbat' ) );
		}

		check_admin_referer( 'msl_zmanim_recheck' );

		MSL_Zmanim::forget( MSL_Meta::get( 'zmanim', MSL_Importer::page_id() ) );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
		exit;
	}

	/**
	 * Run the content updates again, by hand.
	 *
	 * Wording that this theme changes after a page has been saved is brought up
	 * by a one-time migration. "One time" is right and it is also the problem
	 * on the day it did not take: an update copied over an old cache, a pass
	 * that ran before the new files were in place, a revision recorded by a
	 * pass that found nothing. From here it can simply be run again — and it is
	 * safe to run twice, because each migration only rewrites a value that is
	 * still word for word the text it replaces.
	 */
	public static function handle_copy_rerun(): void {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'אין הרשאה.', 'mashehu-leshabbat' ) );
		}

		check_admin_referer( 'msl_copy_rerun' );

		MSL_Content::migrate_again();

		wp_safe_redirect( add_query_arg( 'msl_copy_ran', '1', admin_url( 'admin.php?page=' . self::SLUG ) ) );
		exit;
	}

	/**
	 * Where the stored copy stands against what the theme carries.
	 */
	private static function render_copy_notice(): void {
		$done    = MSL_Content::revision_done();
		$current = MSL_Content::revision_current();

		if ( isset( $_GET['msl_copy_ran'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading a flag to print a notice.
			printf(
				'<div class="notice notice-success"><p>%s</p></div>',
				esc_html__( 'עדכוני התוכן רצו שוב. אם עדיין מופיע נוסח ישן בעמוד — זה מטמון עמודים, וצריך לרוקן אותו.', 'mashehu-leshabbat' )
			);
		}

		printf(
			'<div class="notice notice-info"><p><strong>%s</strong> %s</p><p>%s</p><p>%s</p></div>',
			esc_html__( 'עדכוני תוכן:', 'mashehu-leshabbat' ),
			esc_html(
				$done >= $current
					/* translators: %d: revision number. */
					? sprintf( __( 'העמודים מעודכנים לגרסה %d.', 'mashehu-leshabbat' ), $current )
					/* translators: 1: revision applied, 2: revision available. */
					: sprintf( __( 'העמודים בגרסה %1$d, והתבנית מביאה %2$d.', 'mashehu-leshabbat' ), $done, $current )
			),
			esc_html__( 'כשהתבנית משנה נוסח שכבר נשמר בעמוד, העדכון הזה הוא מה שמחליף אותו — ורק אם הוא עדיין מילה במילה הנוסח הישן. מה שכתבתם בעצמכם נשאר שלכם.', 'mashehu-leshabbat' ),
			sprintf(
				'<a class="button" href="%s">%s</a>',
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=msl_copy_rerun' ), 'msl_copy_rerun' ) ),
				esc_html__( 'להריץ את עדכוני התוכן שוב', 'mashehu-leshabbat' )
			)
		);
	}

	/**
	 * The button that empties the times cache and asks again.
	 *
	 * The cache is what makes this feature cheap to run, and it is also what
	 * makes a wrong setting take six hours to disprove. Whoever is looking at
	 * this screen is looking at it because they want an answer now.
	 *
	 * @return string
	 */
	/**
	 * What the reminder queue is doing.
	 *
	 * This whole feature fails silently by its nature: a letter that is never
	 * written looks exactly like a letter nobody answered. So the panel prints
	 * the numbers rather than leaving anybody to assume them — how many people
	 * are waiting, how many are overdue, and when the job is next due to wake.
	 *
	 * **The overdue count is the one that matters.** WordPress's own cron only
	 * fires when somebody visits the site; on a quiet site it can sleep for
	 * days, and a number that stays high here is that, visible, instead of a
	 * silence nobody could explain.
	 *
	 * @param int $page_id Campaign page.
	 */
	private static function render_reminder_notice( int $page_id ): void {
		if ( ! class_exists( 'MSL_Reminders' ) || ! MSL_DB::ready() ) {
			return;
		}

		$state = MSL_Reminders::status( $page_id );

		if ( 0 === $state['waiting'] && 0 === $state['finished'] ) {
			return;
		}

		$next = $state['next_run'] > 0
			? sprintf(
				/* translators: %s: how long from now, e.g. "12 minutes". */
				__( 'הבדיקה הבאה בעוד %s.', 'mashehu-leshabbat' ),
				human_time_diff( $state['next_run'] )
			)
			: __( 'המשימה המתוזמנת אינה רשומה — כניסה אחת לאתר תרשום אותה מחדש.', 'mashehu-leshabbat' );

		printf(
			'<div class="notice notice-%s"><p><strong>%s</strong> %s</p><p>%s %s</p>%s</div>',
			esc_attr( $state['due'] > 0 ? 'warning' : 'success' ),
			esc_html__( 'תזכורות לפני שבת:', 'mashehu-leshabbat' ),
			esc_html(
				sprintf(
					/* translators: 1: people waiting, 2: people whose undertaking is over. */
					__( '%1$s ממתינים למכתב הבא, %2$s סיימו את הקבלה שלקחו.', 'mashehu-leshabbat' ),
					msl_num( $state['waiting'] ),
					msl_num( $state['finished'] )
				)
			),
			esc_html(
				$state['due'] > 0
					? sprintf(
						/* translators: %s: how many letters are overdue. */
						__( '%s מכתבים היו אמורים לצאת כבר.', 'mashehu-leshabbat' ),
						msl_num( $state['due'] )
					)
					: __( 'אין מכתבים שמחכים.', 'mashehu-leshabbat' )
			),
			esc_html( $next ),
			sprintf(
				'<p>%s <span class="description">%s</span></p>',
				sprintf(
					'<a class="button" href="%s">%s</a>',
					esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=msl_send_reminders' ), 'msl_send_reminders' ) ),
					esc_html__( 'לשלוח עכשיו את מה שממתין', 'mashehu-leshabbat' )
				),
				esc_html__( 'שולח עד 200 מכתבים בלחיצה. שימושי כדי לבדוק שהשרת בכלל מצליח לשלוח מייל.', 'mashehu-leshabbat' )
			)
		);
	}

	/**
	 * Send the waiting letters, by hand.
	 */
	public static function handle_send_reminders(): void {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'אין הרשאה.', 'mashehu-leshabbat' ) );
		}

		check_admin_referer( 'msl_send_reminders' );

		$done = MSL_Reminders::run();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => self::SLUG,
					'msl_sent'      => (int) $done['sent'],
					'msl_closing'   => (int) $done['closing'],
					'msl_skipped'   => (int) $done['skipped'],
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private static function zmanim_recheck_button(): string {
		return sprintf(
			'<a class="button" href="%s">%s</a>',
			esc_url(
				wp_nonce_url(
					admin_url( 'admin-post.php?action=msl_zmanim_recheck' ),
					'msl_zmanim_recheck'
				)
			),
			esc_html__( 'בדיקה מחדש מול hebcal', 'mashehu-leshabbat' )
		);
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
