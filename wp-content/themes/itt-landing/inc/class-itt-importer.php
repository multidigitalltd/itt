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
		'thank-you-men' => array(
			'title'    => 'תודה — הפרטים התקבלו · לגברים',
			'slug'     => 'itt-leader-toda-gvarim',
			'template' => 'template-itt-thank-you.php',
			'sections' => 'thank-you',
			'content'  => 'men',
		),
		'accessibility' => array(
			'title'    => 'הצהרת נגישות',
			'slug'     => 'hatsharat-negishut',
			'template' => '',
			'sections' => '',
			'content'  => '',
		),
		'terms'         => array(
			'title'    => 'תקנון ותנאי שימוש',
			'slug'     => 'takanon',
			'template' => '',
			'sections' => '',
			'content'  => '',
		),
		'privacy'       => array(
			'title'    => 'מדיניות פרטיות',
			'slug'     => 'mediniyut-pratiyut',
			'template' => '',
			'sections' => '',
			'content'  => '',
		),
	);

	/**
	 * The legal pages linked from the footer and from the consent checkbox.
	 *
	 * @var array<string, string>
	 */
	private const LEGAL_PAGES = array( 'accessibility', 'terms', 'privacy' );

	/**
	 * Hook the importer.
	 */
	public static function init(): void {
		add_action( 'after_switch_theme', array( self::class, 'provision' ) );
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_itt_provision', array( self::class, 'handle_request' ) );
		add_action( 'admin_post_itt_settings', array( self::class, 'handle_settings' ) );
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
	 * The published legal pages, in the order they should be listed.
	 *
	 * Missing or unpublished pages simply drop out, so the footer never prints
	 * a link to a page that is not there.
	 *
	 * @return int[]
	 */
	public static function legal_pages(): array {
		$ids = array();

		foreach ( self::LEGAL_PAGES as $key ) {
			$id = self::page_id( $key );

			if ( 0 !== $id ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * The thank-you page a given landing page should send visitors to.
	 *
	 * A man who submits the men's form must not land on a page addressed to a
	 * woman, so the men's landing page has its own. Any other page — including
	 * a copy an editor made themselves — falls back to the main thank-you page.
	 *
	 * @param int $landing_id Page the form was submitted from.
	 * @return int Thank-you page ID, or 0 when none exists.
	 */
	public static function thank_you_for( int $landing_id ): int {
		if ( $landing_id > 0 && self::page_id( 'landing-men' ) === $landing_id ) {
			$men = self::page_id( 'thank-you-men' );

			if ( 0 !== $men ) {
				return $men;
			}
		}

		return self::page_id( 'thank-you' );
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
				'post_content'   => self::starter_content( $blueprint['slug'] ),
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
				'meta_input'     => array( '_wp_page_template' => $template ),
			),
			true
		);

		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	/**
	 * The body a provisioned page starts with, if any.
	 *
	 * The two legal pages get a visible placeholder rather than an empty body:
	 * an empty page published at a URL the consent checkbox links to is worse
	 * than one that says out loud it is still waiting for its text.
	 *
	 * @param string $slug Page slug.
	 * @return string Block markup for the page content.
	 */
	private static function starter_content( string $slug ): string {
		if ( 'hatsharat-negishut' === $slug ) {
			return self::accessibility_statement();
		}

		if ( 'takanon' === $slug || 'mediniyut-pratiyut' === $slug ) {
			return '<!-- wp:paragraph --><p>[יש להשלים: יש להדביק כאן את הנוסח המלא. עד שהתוכן יוזן, העמוד מוצג עם ההודעה הזו.]</p><!-- /wp:paragraph -->';
		}

		return '';
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

		echo '<p>' . esc_html__( 'התוכן של כל העמודים נשמר בתוך העמודים עצמם ונערך בעורך העמוד. מכאן אפשר ליצור עמוד חסר מחדש, להחיל את עדכוני התוכן של הגרסה הנוכחית, או להחזיר עמוד לתוכן המקורי מהעיצוב.', 'itt-landing' ) . '</p>';

		printf(
			'<p><strong>%s</strong> %s</p>',
			esc_html__( 'גרסת התבנית:', 'itt-landing' ),
			esc_html( ITT_VERSION )
		);

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

		self::render_leads_status();
		self::render_turnstile_form();

		echo '<h2>' . esc_html__( 'ניהול עמודים', 'itt-landing' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:20px">';
		wp_nonce_field( 'itt_provision' );
		echo '<input type="hidden" name="action" value="itt_provision">';
		submit_button( __( 'יצירת עמודים חסרים', 'itt-landing' ), 'primary', 'create', false );
		echo ' ';
		submit_button( __( 'החלת עדכוני התוכן של הגרסה', 'itt-landing' ), 'secondary', 'migrate', false );
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
	 * Where the leads are, and whether the form can still reach the server.
	 *
	 * The check exists because a blocked submission route looks like nothing at
	 * all from the outside: the visitor sees an error they will not report, and
	 * the office simply stops receiving leads.
	 */
	private static function render_leads_status(): void {
		$summary = ITT_Leads::summary();
		$test    = get_transient( 'itt_lead_selftest' );

		echo '<h2 id="itt-selftest">' . esc_html__( 'פניות מהטופס', 'itt-landing' ) . '</h2>';

		printf(
			'<p>%s <a href="%s"><strong>%s</strong></a>.</p>',
			esc_html__( 'כל פנייה שנשלחת מהטופס נשמרת בתפריט', 'itt-landing' ),
			esc_url( admin_url( 'edit.php?post_type=' . ITT_Leads::POST_TYPE ) ),
			esc_html__( 'פניות ITT', 'itt-landing' )
		);

		printf(
			'<p><strong>%s</strong> %d%s</p>',
			esc_html__( 'פניות שנשמרו:', 'itt-landing' ),
			(int) $summary['count'],
			'' === $summary['last']
				? ''
				: ' · ' . esc_html__( 'האחרונה:', 'itt-landing' ) . ' ' . esc_html( $summary['last'] )
		);

		echo '<p>' . esc_html__( 'הבדיקה שולחת פנייה ריקה בכוונה לכל אחד ממסלולי השליחה ובודקת שהשרת עונה. שום פנייה לא נשמרת בבדיקה.', 'itt-landing' ) . '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'itt_lead_selftest' );
		echo '<input type="hidden" name="action" value="itt_lead_selftest">';
		submit_button( __( 'בדיקת מסלולי השליחה', 'itt-landing' ), 'secondary', 'test', false );
		echo '</form>';

		if ( ! is_array( $test ) ) {
			return;
		}

		echo '<table class="widefat striped" style="max-width:760px;margin-top:16px"><tbody>';

		foreach ( $test as $row ) {
			printf(
				'<tr><th scope="row">%s</th><td>%s %s</td></tr>',
				esc_html( (string) $row['label'] ),
				esc_html( $row['ok'] ? '✔' : '✖' ),
				esc_html( (string) $row['note'] )
			);
		}

		echo '</tbody></table>';
	}

	/**
	 * The Cloudflare Turnstile key fields.
	 *
	 * Kept on this screen rather than in a page's meta boxes: the keys belong
	 * to the site, and the secret must not travel with an exported page.
	 */
	private static function render_turnstile_form(): void {
		$settings = ITT_Settings::all();

		echo '<h2>' . esc_html__( 'הגנה מפני ספאם (Cloudflare Turnstile)', 'itt-landing' ) . '</h2>';

		echo '<p>' . esc_html__( 'כל עוד השדות ריקים הטופס עובד בלי אימות כלל, ולא נשלחת שום בקשה ל-Cloudflare. אחרי מילוי שני המפתחות יוצג אימות מתחת לטופס, והשליחה תיבדק מול Cloudflare בצד השרת.', 'itt-landing' ) . '</p>';

		printf(
			'<p>%s <a href="%s" target="_blank" rel="noopener">%s</a></p>',
			esc_html__( 'את המפתחות מפיקים בחינם בלוח הבקרה של Cloudflare, תחת Turnstile ← Add site:', 'itt-landing' ),
			esc_url( 'https://dash.cloudflare.com/?to=/:account/turnstile' ),
			esc_html__( 'פתיחת Turnstile ב-Cloudflare', 'itt-landing' )
		);

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'itt_settings' );
		echo '<input type="hidden" name="action" value="itt_settings">';

		echo '<table class="form-table" role="presentation"><tbody>';

		printf(
			'<tr><th scope="row"><label for="itt-turnstile-site">%s</label></th><td><input type="text" class="regular-text code" id="itt-turnstile-site" name="turnstile_site_key" value="%s" dir="ltr" autocomplete="off"><p class="description">%s</p></td></tr>',
			esc_html__( 'Site Key', 'itt-landing' ),
			esc_attr( $settings['turnstile_site_key'] ),
			esc_html__( 'מפתח ציבורי — מוטמע בעמוד.', 'itt-landing' )
		);

		printf(
			'<tr><th scope="row"><label for="itt-turnstile-secret">%s</label></th><td><input type="password" class="regular-text code" id="itt-turnstile-secret" name="turnstile_secret_key" value="%s" dir="ltr" autocomplete="off"><p class="description">%s</p></td></tr>',
			esc_html__( 'Secret Key', 'itt-landing' ),
			esc_attr( $settings['turnstile_secret_key'] ),
			esc_html__( 'מפתח סודי — נשמר בשרת בלבד ולעולם לא מוצג בעמוד.', 'itt-landing' )
		);

		echo '</tbody></table>';

		submit_button( __( 'שמירת מפתחות', 'itt-landing' ) );
		echo '</form>';
	}

	/**
	 * Save the Turnstile keys.
	 */
	public static function handle_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'אין לך הרשאה לבצע את הפעולה הזו.', 'itt-landing' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'itt_settings' );

		ITT_Settings::save(
			array(
				'turnstile_site_key'   => wp_unslash( (string) ( $_POST['turnstile_site_key'] ?? '' ) ),
				'turnstile_secret_key' => wp_unslash( (string) ( $_POST['turnstile_secret_key'] ?? '' ) ),
			)
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'       => 'itt-pages',
					'itt-saved'  => 1,
				),
				admin_url( 'tools.php' )
			)
		);
		exit;
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

		// Content updates that ship with a theme version normally apply by
		// themselves on the first page load after the upload. This button is
		// the manual way to ask for them again — after restoring a backup, for
		// instance, or when a cache has kept the old version around.
		if ( isset( $_POST['migrate'] ) ) {
			ITT_Migrations::run();
		}

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
