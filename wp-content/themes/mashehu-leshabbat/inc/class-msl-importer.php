<?php
/**
 * One-time page provisioning.
 *
 * Creates the campaign page and writes the approved copy into its own meta, so
 * that straight after activation the content is really there — inside the page,
 * editable in the page editor.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Creates and repairs the theme's pages.
 */
final class MSL_Importer {

	/**
	 * Option holding the created page IDs, keyed by blueprint.
	 */
	public const OPTION = 'msl_pages';

	/**
	 * Page blueprints.
	 */
	private const PAGES = array(
		'home'          => array(
			'title'    => 'משהו לשבת',
			'slug'     => 'mashehu-leshabbat',
			'template' => 'template-msl-home.php',
			'front'    => true,
		),
		'accessibility' => array(
			'title'    => 'הצהרת נגישות',
			'slug'     => 'hatsharat-negishut',
			'template' => '',
			'front'    => false,
		),
	);

	/**
	 * Hook the importer.
	 */
	public static function init(): void {
		add_action( 'after_switch_theme', array( self::class, 'provision' ), 5 );
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_msl_provision', array( self::class, 'handle_request' ) );
	}

	/**
	 * The campaign page ID.
	 *
	 * Falls back to the front page, then to any published page using the
	 * template, so a site whose page was recreated by hand still works.
	 *
	 * @param string $blueprint Blueprint key.
	 * @return int
	 */
	public static function page_id( string $blueprint = 'home' ): int {
		static $memo = array();

		if ( isset( $memo[ $blueprint ] ) ) {
			return $memo[ $blueprint ];
		}

		$pages = get_option( self::OPTION, array() );
		$id    = is_array( $pages ) ? absint( $pages[ $blueprint ] ?? 0 ) : 0;

		if ( 0 === $id || 'publish' !== get_post_status( $id ) ) {
			$id = 'home' === $blueprint ? self::find_home() : 0;
		}

		$memo[ $blueprint ] = $id;

		return $id;
	}

	/**
	 * Locate a campaign page that the option no longer points at.
	 *
	 * @return int
	 */
	private static function find_home(): int {
		$found = get_posts(
			array(
				'post_type'        => 'page',
				'post_status'      => 'publish',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
				'meta_key'         => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => 'template-msl-home.php', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		return isset( $found[0] ) ? (int) $found[0] : 0;
	}

	/**
	 * Create any missing page and seed its content.
	 *
	 * Safe to run more than once: an existing page is never overwritten, only
	 * missing sections are filled in.
	 *
	 * @return array<string, int> Page IDs keyed by blueprint.
	 */
	public static function provision(): array {
		$pages = get_option( self::OPTION, array() );
		$pages = is_array( $pages ) ? $pages : array();

		foreach ( self::PAGES as $blueprint => $definition ) {
			$id = absint( $pages[ $blueprint ] ?? 0 );

			if ( 0 === $id || ! get_post( $id ) instanceof WP_Post ) {
				$id = self::create_page( $definition );
			}

			if ( 0 === $id ) {
				continue;
			}

			if ( '' !== $definition['template'] ) {
				self::seed( $id );
			}

			$pages[ $blueprint ] = $id;
		}

		update_option( self::OPTION, $pages, false );

		self::set_front_page( absint( $pages['home'] ?? 0 ) );

		return $pages;
	}

	/**
	 * Point the site at the campaign page, if nothing else claims the slot.
	 *
	 * The design is a full-viewport application, not one entry in a blog: a
	 * visitor arriving at the root has to land on it. A site that already shows
	 * a static front page is left alone — that was a decision someone made.
	 *
	 * @param int $page_id Campaign page ID.
	 */
	private static function set_front_page( int $page_id ): void {
		if ( 0 === $page_id || 'page' === get_option( 'show_on_front' ) ) {
			return;
		}

		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $page_id );
	}

	/**
	 * Create one page from a blueprint.
	 *
	 * @param array{title: string, slug: string, template: string, front: bool} $definition Blueprint.
	 * @return int Page ID, or 0 on failure.
	 */
	private static function create_page( array $definition ): int {
		$template = '' !== $definition['template'] ? $definition['template'] : 'default';
		$existing = get_page_by_path( $definition['slug'] );

		if ( $existing instanceof WP_Post ) {
			update_post_meta( $existing->ID, '_wp_page_template', $template );

			return $existing->ID;
		}

		$id = wp_insert_post(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_title'     => $definition['title'],
				'post_name'      => $definition['slug'],
				'post_content'   => '' === $definition['template'] ? self::accessibility_statement() : '',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
				'meta_input'     => array( '_wp_page_template' => $template ),
			),
			true
		);

		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	/**
	 * Write any section the page does not already hold.
	 *
	 * @param int  $page_id Page ID.
	 * @param bool $force   Overwrite sections that already exist.
	 */
	public static function seed( int $page_id, bool $force = false ): void {
		foreach ( array_keys( MSL_Fields::all() ) as $section ) {
			$stored = get_post_meta( $page_id, MSL_Meta::key( $section ), true );

			if ( ! $force && is_array( $stored ) && array() !== $stored ) {
				continue;
			}

			MSL_Meta::save( $page_id, $section, MSL_Content::section( $section ) );
		}
	}

	/**
	 * Starting text for the accessibility statement page.
	 *
	 * The technical half is filled in; the three human details cannot be, and are
	 * marked so they cannot be missed before launch.
	 *
	 * @return string
	 */
	private static function accessibility_statement(): string {
		$paragraphs = array(
			'האתר "משהו לשבת" נבנה כך שיהיה נגיש לכל אדם, לרבות אנשים עם מוגבלות, בהתאם לתקן הישראלי ת"י 5568 ולהנחיות WCAG 2.2 ברמה AA.',
			'האתר נבנה ב-HTML סמנטי, עם היררכיית כותרות רציפה, קישור דילוג לתוכן, סימון פוקוס גלוי וניווט מלא במקלדת. כל שדה טופס נושא תווית קבועה, והודעות שגיאה מקושרות לשדה ומוכרזות לקוראי מסך.',
			'שתי הסצנות הגרפיות באתר — היצירה המשותפת וקיר הנרות — מצוירות על גבי canvas ומוסתרות מקוראי מסך. לצד כל אחת מהן מופיע תקציר טקסטואלי שמוסר בדיוק את אותו מידע: כמה נרות דולקים, כמה אחוזים מהיצירה הושלמו, ומאילו מקומות הצטרפו משתתפים.',
			'באתר פועל ווידג׳ט נגישות צף, זמין מכל נקודה בדף: הגדלה והקטנה של הטקסט, ניגודיות גבוהה, ניגודיות כהה, הדגשת קישורים, גופן קריא, ריווח טקסט מוגדר, עצירת אנימציות ואיפוס. הבחירה נשמרת גם לביקורים הבאים.',
			'האתר מכבד את העדפת המערכת prefers-reduced-motion, ומאפשר עצירת אנימציות גם ידנית דרך הווידג׳ט.',
			'[יש להשלים: שם רכז/ת הנגישות, טלפון ודוא"ל ליצירת קשר]',
			'[יש להשלים: הסדרי הנגישות הפיזיים במשרדי הארגון]',
			'[יש להשלים: תאריך הבדיקה האחרונה והגורם הבודק]',
			'נתקלתם בקושי? נשמח לשמוע — נטפל בפנייה ונחזור אליכם.',
		);

		return implode( "\n\n", array_map( static fn( string $text ): string => '<!-- wp:paragraph --><p>' . esc_html( $text ) . '</p><!-- /wp:paragraph -->', $paragraphs ) );
	}

	/* ---------------------------------------------------------------------
	 * Tools screen
	 * ------------------------------------------------------------------ */

	/**
	 * Register the maintenance screen.
	 */
	public static function menu(): void {
		add_management_page(
			__( 'עמודי משהו לשבת', 'mashehu-leshabbat' ),
			__( 'עמודי משהו לשבת', 'mashehu-leshabbat' ),
			'manage_options',
			'msl-pages',
			array( self::class, 'render' )
		);
	}

	/**
	 * Render the maintenance screen.
	 */
	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'אין לך הרשאה לגשת למסך הזה.', 'mashehu-leshabbat' ) );
		}

		$page_id = self::page_id();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'עמודי משהו לשבת', 'mashehu-leshabbat' ); ?></h1>

			<p>
				<?php esc_html_e( 'כל התוכן של הפלטפורמה נמצא בתוך העמוד עצמו ונערך בעורך העמוד — מתחת לעורך יש תיבה נפרדת לכל סקשן בעיצוב. המסך הזה נועד לתחזוקה בלבד.', 'mashehu-leshabbat' ); ?>
			</p>

			<?php if ( 0 !== $page_id ) : ?>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( (string) get_edit_post_link( $page_id ) ); ?>">
						<?php esc_html_e( 'עריכת התוכן של העמוד', 'mashehu-leshabbat' ); ?>
					</a>
					<a class="button" href="<?php echo esc_url( (string) get_permalink( $page_id ) ); ?>">
						<?php esc_html_e( 'צפייה בעמוד', 'mashehu-leshabbat' ); ?>
					</a>
				</p>
			<?php else : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'עמוד הקמפיין לא נמצא. אפשר ליצור אותו מחדש בכפתור שלמטה.', 'mashehu-leshabbat' ); ?></p></div>
			<?php endif; ?>

			<h2><?php esc_html_e( 'פעולות תחזוקה', 'mashehu-leshabbat' ); ?></h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'msl_provision' ); ?>
				<input type="hidden" name="action" value="msl_provision">
				<p>
					<button type="submit" name="mode" value="create" class="button">
						<?php esc_html_e( 'יצירת עמוד שנמחק בטעות', 'mashehu-leshabbat' ); ?>
					</button>
					<span class="description"><?php esc_html_e( 'עמוד קיים לא ישתנה.', 'mashehu-leshabbat' ); ?></span>
				</p>
				<p>
					<button type="submit" name="mode" value="reset" class="button button-link-delete" onclick="return confirm('<?php echo esc_js( __( 'לאפס את כל התוכן של העמוד לתוכן המקורי מהעיצוב? כל שינוי שנעשה בעורך יאבד.', 'mashehu-leshabbat' ) ); ?>');">
						<?php esc_html_e( 'איפוס העמוד לתוכן המקורי מהעיצוב', 'mashehu-leshabbat' ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle a maintenance action.
	 */
	public static function handle_request(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'אין לך הרשאה לבצע את הפעולה הזאת.', 'mashehu-leshabbat' ) );
		}

		check_admin_referer( 'msl_provision' );

		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( (string) $_POST['mode'] ) ) : '';

		if ( 'reset' === $mode ) {
			$page_id = self::page_id();

			if ( 0 !== $page_id ) {
				self::seed( $page_id, true );
			}
		} else {
			self::provision();
		}

		wp_safe_redirect( add_query_arg( 'msl_done', $mode, admin_url( 'tools.php?page=msl-pages' ) ) );
		exit;
	}
}
