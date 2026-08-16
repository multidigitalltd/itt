<?php
/**
 * Content migrations for pages that already exist.
 *
 * The theme's copy lives in each page's own meta, which is the point — the
 * content is really in the page and the client owns it. The cost is that
 * changing a default in ITT_Content only reaches pages created afterwards; a
 * site that has been live since an earlier version keeps what it has.
 *
 * So a design change that has to reach existing pages gets a migration here.
 * Each one is:
 *
 *   - idempotent — safe to run twice, because it checks the current state
 *     rather than assuming it;
 *   - surgical — it changes the one thing it is about and leaves every other
 *     edit the client has made alone. This is not "reset to the original
 *     content", which is a separate and deliberately destructive button.
 *
 * They run on theme activation and on theme update, once per version.
 *
 * @package ITT_Landing
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Applies content migrations to pages that already exist.
 */
final class ITT_Migrations {

	/**
	 * Option recording the version whose migrations have already run.
	 */
	private const OPTION = 'itt_migrated_version';

	/**
	 * Hook the migrations.
	 */
	public static function init(): void {
		add_action( 'after_switch_theme', array( self::class, 'run' ) );
		add_action( 'admin_init', array( self::class, 'maybe_run' ) );
	}

	/**
	 * Run the migrations when the theme version has moved on.
	 *
	 * Checked in the admin rather than on the front end: an update uploaded by
	 * FTP or by the theme uploader does not fire after_switch_theme, and the
	 * next admin page load is the first reliable moment afterwards.
	 */
	public static function maybe_run(): void {
		if ( get_option( self::OPTION ) === ITT_VERSION ) {
			return;
		}

		self::run();
	}

	/**
	 * Apply every migration, then record the version.
	 */
	public static function run(): void {
		foreach ( self::landing_pages() as $post_id ) {
			self::envelope_seminars_card( $post_id );
		}

		update_option( self::OPTION, ITT_VERSION, false );
	}

	/**
	 * Every page using the landing template.
	 *
	 * Found by template rather than by the importer's stored IDs, so a page the
	 * client duplicated themselves is migrated too.
	 *
	 * @return int[]
	 */
	private static function landing_pages(): array {
		return get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => 'any',
				'numberposts'    => 50,
				'fields'         => 'ids',
				'meta_key'       => '_wp_page_template', // phpcs:ignore WordPress.DB.SlowMetaQuery.slow_db_query -- runs once per version, in the admin.
				'meta_value'     => 'template-itt-landing.php', // phpcs:ignore WordPress.DB.SlowMetaQuery.slow_db_query -- as above.
				'no_found_rows'  => true,
				'cache_results'  => false,
			)
		);
	}

	/**
	 * 1.6.0 — lead the envelope with a gold "סמינרים עשירים" card.
	 *
	 * Two changes in one pass, because they are one design decision: gold
	 * should mark the first card, so the card that used to hold it moves to
	 * purple. A page that already has the new card, or whose editor has since
	 * chosen their own colour for "ליווי בזום", is left exactly as it is.
	 *
	 * @param int $post_id Landing page ID.
	 */
	private static function envelope_seminars_card( int $post_id ): void {
		$envelope = ITT_Meta::get( 'envelope', $post_id );
		$cards    = array_values( (array) ( $envelope['cards'] ?? array() ) );

		if ( array() === $cards ) {
			return;
		}

		$badges = array_column( $cards, 'badge' );

		if ( in_array( 'סמינרים עשירים', $badges, true ) ) {
			return;
		}

		foreach ( $cards as $index => $card ) {
			if ( 'ליווי בזום' === ( $card['badge'] ?? '' ) && 'gold' === ( $card['variant'] ?? '' ) ) {
				$cards[ $index ]['variant'] = 'purple';
			}
		}

		array_unshift(
			$cards,
			array(
				'badge'   => 'סמינרים עשירים',
				'text'    => 'החומר מועבר פרונטלי ע״י המאסטר ד״ר אמיר קוליק וצוות מנוסה ובכיר. כל יום גדוש בלמידה, התנסות אישית וכלים יישומיים.',
				'variant' => 'gold',
			)
		);

		$envelope['cards'] = $cards;

		ITT_Meta::save( $post_id, 'envelope', $envelope );
	}
}
