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
			self::show_email_field( $post_id );
			self::video_gallery_heading( $post_id );
			self::quotes_into_voices( $post_id );
			self::companion_course_line( $post_id );
		}

		update_option( self::OPTION, ITT_VERSION, false );
	}

	/**
	 * 1.6.4 — turn the email field on for pages created before it defaulted on.
	 *
	 * The office needs a written channel as well as a phone number. Only runs
	 * where the flag is still off, and only once: an editor who turns it back
	 * off afterwards keeps that choice, because migrations are recorded per
	 * version and this one will not run again.
	 *
	 * @param int $post_id Landing page ID.
	 */
	private static function show_email_field( int $post_id ): void {
		$form = ITT_Meta::get( 'form', $post_id );

		if ( ! empty( $form['show_email'] ) ) {
			return;
		}

		$form['show_email'] = 1;

		if ( '' === trim( (string) ( $form['label_email'] ?? '' ) ) ) {
			$form['label_email'] = 'אימייל';
		}

		ITT_Meta::save( $post_id, 'form', $form );
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
	 * 1.7.2 — the companion-course link becomes a phone line.
	 *
	 * "לדף גברים" was a button across to the other landing page; it is now a
	 * sentence naming the phone number instead. Only the two old default labels
	 * are replaced — a label the editor has written themselves is left alone,
	 * and the address the button used to carry is dropped by the schema.
	 *
	 * @param int $post_id Landing page ID.
	 */
	private static function companion_course_line( int $post_id ): void {
		$replacements = array(
			'לדף גברים' => 'לפרטים על קורס גברים חייגו *6163',
			'לדף נשים'  => 'לפרטים על קורס נשים חייגו *6163',
		);

		$chrome = ITT_Meta::get( 'chrome', $post_id );
		$label  = trim( (string) ( $chrome['men_text'] ?? '' ) );

		if ( ! isset( $replacements[ $label ] ) ) {
			return;
		}

		$chrome['men_text'] = $replacements[ $label ];

		ITT_Meta::save( $post_id, 'chrome', $chrome );
	}

	/**
	 * 1.7.0 — fold the rotating quote box into the written testimonials.
	 *
	 * The purple box under section 06 showed one quote at a time behind its own
	 * pair of arrows, which is a second testimonial gallery a few screens above
	 * the real one. Its quotes move down to join the written testimonials and
	 * the box is retired.
	 *
	 * The two shapes differ — a quote has no title, a testimonial card does —
	 * so a moved quote arrives with an empty title, which the card template now
	 * renders without. Colour variants are dealt round so the added cards do
	 * not all land in the same one.
	 *
	 * @param int $post_id Landing page ID.
	 */
	private static function quotes_into_voices( int $post_id ): void {
		// Read the raw row, not ITT_Meta::get(): that intersects the stored
		// value with the current schema, and 'quotes' has just been removed
		// from it — so the very data this migration exists to move would be
		// invisible to it.
		$stored = get_post_meta( $post_id, ITT_Meta::key( 'skills' ), true );
		$stored = is_array( $stored ) ? $stored : array();
		$quotes = array_values( (array) ( $stored['quotes'] ?? array() ) );

		if ( array() === $quotes ) {
			return;
		}

		$voices   = ITT_Meta::get( 'voices', $post_id );
		$cards    = array_values( (array) ( $voices['cards'] ?? array() ) );
		$existing = array_column( $cards, 'text' );
		$palette  = array( 'gray', 'cyan', 'orange', 'purple', 'gold' );

		foreach ( $quotes as $quote ) {
			$text = (string) ( $quote['text'] ?? '' );

			// Idempotent, and safe if a quote was already copied down by hand.
			if ( '' === trim( $text ) || in_array( $text, $existing, true ) ) {
				continue;
			}

			$cards[]    = array(
				'title'   => '',
				'text'    => $text,
				'author'  => (string) ( $quote['author'] ?? '' ),
				'variant' => $palette[ count( $cards ) % count( $palette ) ],
			);
			$existing[] = $text;
		}

		$voices['cards'] = $cards;
		ITT_Meta::save( $post_id, 'voices', $voices );

		// Saving through ITT_Meta drops 'quotes' on its way out, because the
		// key no longer exists in the schema — which is exactly the retirement
		// this migration is completing.
		ITT_Meta::save( $post_id, 'skills', ITT_Meta::get( 'skills', $post_id ) );
	}

	/**
	 * 1.7.0 — retitle the video heading now that it heads the whole gallery.
	 *
	 * The lead video and the strip below it became one gallery, so the strip's
	 * old title ("עוד סרטוני המלצות…" — *more* testimonials) now sits above the
	 * first one and reads wrong. Replaced only where it is still the old
	 * default; a heading the editor has written themselves is left alone.
	 *
	 * @param int $post_id Landing page ID.
	 */
	private static function video_gallery_heading( int $post_id ): void {
		$stale = array(
			'עוד סרטוני המלצות מתלמידות' => 'סרטוני המלצות מהבוגרות',
			'עוד סרטוני המלצות מתלמידים' => 'סרטוני המלצות מהבוגרים',
		);

		$video   = ITT_Meta::get( 'video', $post_id );
		$heading = trim( (string) ( $video['slider_heading'] ?? '' ) );

		if ( ! isset( $stale[ $heading ] ) ) {
			return;
		}

		$video['slider_heading'] = $stale[ $heading ];

		ITT_Meta::save( $post_id, 'video', $video );
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
