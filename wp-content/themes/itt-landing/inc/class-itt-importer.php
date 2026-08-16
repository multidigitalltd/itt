<?php
/**
 * One-time page provisioning.
 *
 * Creates the landing pages and the thank-you page and writes the approved copy
 * into their own meta, so that straight after activation the content is really
 * there — inside the pages, editable in the page editor.
 *
 * @package ITT_Landing
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Creates and repairs the ITT pages.
 */
final class ITT_Importer {

	/**
	 * Option holding the created page IDs, keyed by template.
	 */
	public const OPTION = 'itt_pages';

	/**
	 * Page blueprints.
	 *
	 * 'sections' is the field-schema key the page's meta boxes follow, and
	 * 'content' names the copy set it is seeded from. The men's page reuses the
	 * landing template and the landing schema — it is the same page with a
	 * different address — so only its seed differs.
	 */
	private const PAGES = array(
		'landing'       => array(
			'title'    => 'הכשרת ITT Leader · מחזור 20',
			'slug'     => 'itt-leader',
			'template' => 'template-itt-landing.php',
			'sections' => 'landing',
			'content'  => 'women',
		),
		'landing-men'   => array(
			'title'    => 'הכשרת ITT Leader · מחזור 20 · לגברים',
			'slug'     => 'itt-leader-gvarim',
			'template' => 'template-itt-landing.php',
			'sections' => 'landing',
			'content'  => 'men',
		),
		'thank-you'     => array(
			'title'    => 'תודה — הפרטים התקבלו',
			'slug'     => 'itt-leader-toda',
			'template' => 'template-itt-thank-you.php',
			'sections' => 'thank-you',
			'content'  => 'women',
		),
		'accessibility' => array(
			'title'    => 'הצהרת נגישות',
			'slug'     => 'hatsharat-negishut',
			'template' => '',
			'sections' => '',
			'content'  => '',
		),
	);

	/**
	 * Hook the importer.
	 */
	public static function init(): void {
		add_action( 'after_switch_theme', array( self::class, 'provision' ) );
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_itt_provision', array( self::class, 'handle_request' ) );
	}

	/**
	 * The page ID for a template key, or 0.
	 *
	 * @param string $template Template key.
	 * @return int
	 */
	public static function page_id( string $template ): int {
		$pages = get_option( self::OPTION, array() );

		$id = is_array( $pages ) ? absint( $pages[ $template ] ?? 0 ) : 0;

		return 'publish' === get_post_status( $id ) ? $id : 0;
	}

	/**
	 * Create any missing page and seed its content.
	 *
	 * Safe to run more than once: an existing page is never overwritten, only
	 * missing sections are filled in.
	 *
	 * @return array<string, int> Page IDs keyed by template.
	 */
	public static function provision(): array {
		$pages = get_option( self::OPTION, array() );
		$pages = is_array( $pages ) ? $pages : array();

		foreach ( self::PAGES as $template => $blueprint ) {
			$id = absint( $pages[ $template ] ?? 0 );

			if ( 0 === $id || ! get_post( $id ) instanceof WP_Post ) {
				$id = self::create_page( $blueprint );
			}

			if ( 0 === $id ) {
				continue;
			}

			self::seed( $id, $template );
			$pages[ $template ] = $id;
		}

		// Not autoloaded on every request: only the form redirect and the admin
		// screen need it, and both are happy to pay for one extra query.
		update_option( self::OPTION, $pages, false );

		self::cross_link( $pages );

		return $pages;
	}

	/**
	 * Create one page from a blueprint.
	 *
	 * @param array{title: string, slug: string, template: string} $blueprint Page blueprint.
	 * @return int Page ID, or 0 on failure.
	 */
	private static function create_page( array $blueprint ): int {
		$template = '' !== $blueprint['template'] ? $blueprint['template'] : 'default';
		$existing = get_page_by_path( $blueprint['slug'] );

		if ( $existing instanceof WP_Post ) {
			update_post_meta( $existing->ID, '_wp_page_template', $template );

			return $existing->ID;
		}

		$id = wp_insert_post(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_title'     => $blueprint['title'],
				'post_name'      => $blueprint['slug'],
				'post_content'   => 'hatsharat-negishut' === $blueprint['slug'] ? self::accessibility_statement() : '',
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
				'meta_input'     => array( '_wp_page_template' => $template ),
			),
			true
		);

		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	/**
	 * Starting text for the accessibility statement page.
	 *
	 * Required by ת"י 5568. The technical parts are filled in from what the
	 * theme actually does; the details that only the client can supply — the
	 * accessibility coordinator and the date of the last audit — are marked
	 * in the text so they are impossible to miss before going live.
	 *
	 * @return string Block markup for the page content.
	 */
	private static function accessibility_statement(): string {
		$blocks = array(
			'<!-- wp:paragraph --><p>מרכז ישיר רואה חשיבות עליונה במתן שירות שוויוני ונגיש לכלל הגולשות והגולשים, ופועל להתאמת האתר לתקן הישראלי ת"י 5568 ולהנחיות WCAG 2.2 ברמה AA.</p><!-- /wp:paragraph -->',
			'<!-- wp:heading --><h2>מה נעשה באתר</h2><!-- /wp:heading -->',
			'<!-- wp:list --><ul>'
				. '<li>מבנה סמנטי מלא עם היררכיית כותרות תקינה וקישור "דילוג לתוכן הראשי".</li>'
				. '<li>הפעלה מלאה במקלדת בלבד, עם סימון פוקוס גלוי בכל רכיב אינטראקטיבי.</li>'
				. '<li>תאימות לקוראי מסך, כולל תיאורי ARIA באקורדיונים, בטאבים ובגלריית הסרטונים.</li>'
				. '<li>יחסי ניגודיות של 4.5:1 לפחות בטקסט רגיל ו-3:1 בטקסט גדול.</li>'
				. '<li>כיבוד ההגדרה "הפחתת תנועה" במערכת ההפעלה, ולחצן ייעודי בתחתית הדף לעצירת כל האנימציות.</li>'
				. '<li>טקסט חלופי לכל תמונה, וסימון תמונות דקורטיביות כך שקוראי מסך ידלגו עליהן.</li>'
				. '<li>סרטונים אינם נטענים או מתנגנים אוטומטית, ונפתחים רק ביוזמת המשתמשת.</li>'
				. '</ul><!-- /wp:list -->',
			'<!-- wp:heading --><h2>הסדרי נגישות בסניף</h2><!-- /wp:heading -->',
			'<!-- wp:paragraph --><p>[יש להשלים: פירוט הסדרי הנגישות הפיזיים במקום הלימודים — חניית נכים, נגישות כניסה, שירותים ומעליות.]</p><!-- /wp:paragraph -->',
			'<!-- wp:heading --><h2>החרגות</h2><!-- /wp:heading -->',
			'<!-- wp:paragraph --><p>[יש להשלים: רכיבים או מסמכים שטרם הונגשו, אם קיימים.]</p><!-- /wp:paragraph -->',
			'<!-- wp:heading --><h2>פנייה בנושא נגישות</h2><!-- /wp:heading -->',
			'<!-- wp:paragraph --><p>נתקלת בבעיית נגישות באתר? נשמח לדעת ולתקן. רכז/ת הנגישות שלנו: <strong>[יש להשלים: שם]</strong>.</p><!-- /wp:paragraph -->',
			'<!-- wp:paragraph --><p>טלפון: *6163 · דוא"ל: office@m-yashir.com</p><!-- /wp:paragraph -->',
			'<!-- wp:paragraph --><p>תאריך עדכון ההצהרה: [יש להשלים: תאריך הבדיקה האחרונה].</p><!-- /wp:paragraph -->',
		);

		return implode( "\n\n", $blocks );
	}

	/**
	 * Write the approved copy into any section the page does not have yet.
	 *
	 * @param int    $post_id  Page ID.
	 * @param string $template Template key.
	 */
	private static function seed( int $post_id, string $template ): void {
		foreach ( ITT_Fields::sections_for( self::PAGES[ $template ]['sections'] ) as $section ) {
			$stored = get_post_meta( $post_id, ITT_Meta::key( $section ), true );

			if ( is_array( $stored ) && array() !== $stored ) {
				continue;
			}

			ITT_Meta::save( $post_id, $section, self::copy( $template, $section ) );
		}
	}

	/**
	 * The approved copy for one section of one page.
	 *
	 * @param string $template Page blueprint key.
	 * @param string $section  Section key.
	 * @return array<string, mixed>
	 */
	private static function copy( string $template, string $section ): array {
		return 'men' === ( self::PAGES[ $template ]['content'] ?? '' )
			? ITT_Content_Men::section( $section )
			: ITT_Content::section( $section );
	}

	/**
	 * Point the two landing pages at each other through the header button.
	 *
	 * Only fills an address that is still empty, so clearing the field in the
	 * editor to hide the button stays a decision the editor can make and keep.
	 *
	 * @param array<string, int> $pages Page IDs keyed by blueprint.
	 */
	private static function cross_link( array $pages ): void {
		$pairs = array(
			'landing'     => 'landing-men',
			'landing-men' => 'landing',
		);

		foreach ( $pairs as $from => $to ) {
			$source = absint( $pages[ $from ] ?? 0 );
			$target = absint( $pages[ $to ] ?? 0 );

			if ( 0 === $source || 0 === $target ) {
				continue;
			}

			$chrome = ITT_Meta::get( 'chrome', $source );

			if ( '' !== trim( (string) ( $chrome['men_url'] ?? '' ) ) ) {
				continue;
			}

			$chrome['men_url'] = (string) get_permalink( $target );

			ITT_Meta::save( $source, 'chrome', $chrome );
		}
	}

	/**
	 * Reset a page back to the approved copy, discarding edits.
	 *
	 * @param string $template Template key.
	 */
	private static function reset( string $template ): void {
		$post_id = self::page_id( $template );

		if ( 0 === $post_id ) {
			return;
		}

		foreach ( ITT_Fields::sections_for( self::PAGES[ $template ]['sections'] ) as $section ) {
			ITT_Meta::save( $post_id, $section, self::copy( $template, $section ) );
		}
	}

	/**
	 * Register the maintenance screen under Tools.
	 *
	 * It is only needed when a page has to be recreated or reset, so it stays
	 * out of the main sidebar. Day-to-day content is edited on the pages.
	 */
	public static function menu(): void {
		add_management_page(
			__( 'עמודי ITT', 'itt-landing' ),
			__( 'עמודי ITT', 'itt-landing' ),
			'manage_options',
			'itt-pages',
			array( self::class, 'screen' )
		);
	}

	/**
	 * Render the Tools screen.
	 */
	public static function screen(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'אין לך הרשאה לגשת לעמוד הזה.', 'itt-landing' ) );
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'עמודי ITT', 'itt-landing' ) . '</h1>';

		echo '<p>' . esc_html__( 'התוכן של כל העמודים נשמר בתוך העמודים עצמם ונערך בעורך העמוד. מכאן אפשר ליצור עמוד חסר מחדש, או להחזיר עמוד לתוכן המקורי מהעיצוב.', 'itt-landing' ) . '</p>';

		echo '<table class="widefat striped" style="max-width:760px"><tbody>';

		foreach ( self::PAGES as $template => $blueprint ) {
			$id = self::page_id( $template );

			printf(
				'<tr><th scope="row">%s</th><td>%s</td></tr>',
				esc_html( $blueprint['title'] ),
				0 === $id
					? esc_html__( 'העמוד לא קיים.', 'itt-landing' )
					: sprintf(
						'<a href="%s">%s</a> · <a href="%s">%s</a>',
						esc_url( (string) get_edit_post_link( $id ) ),
						esc_html__( 'עריכה', 'itt-landing' ),
						esc_url( (string) get_permalink( $id ) ),
						esc_html__( 'צפייה', 'itt-landing' )
					)
			);
		}

		echo '</tbody></table>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:20px">';
		wp_nonce_field( 'itt_provision' );
		echo '<input type="hidden" name="action" value="itt_provision">';
		submit_button( __( 'יצירת עמודים חסרים', 'itt-landing' ), 'primary', 'create', false );
		echo ' ';
		submit_button(
			__( 'איפוס לתוכן המקורי (מוחק עריכות)', 'itt-landing' ),
			'delete',
			'reset',
			false,
			array( 'onclick' => "return confirm('" . esc_js( __( 'הפעולה תחליף את כל התוכן בכל עמודי ITT בתוכן המקורי מהעיצוב. להמשיך?', 'itt-landing' ) ) . "');" )
		);
		echo '</form></div>';
	}

	/**
	 * Handle the Tools form submission.
	 */
	public static function handle_request(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'אין לך הרשאה לבצע את הפעולה הזו.', 'itt-landing' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'itt_provision' );

		$pages = self::provision();

		if ( isset( $_POST['reset'] ) ) {
			foreach ( array_keys( self::PAGES ) as $template ) {
				self::reset( $template );
			}
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'itt-pages',
					'itt-notice' => count( $pages ),
				),
				admin_url( 'tools.php' )
			)
		);
		exit;
	}
}
