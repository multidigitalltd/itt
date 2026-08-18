<?php
/**
 * Theme setup and conditional asset loading.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Registers theme supports and enqueues assets only where they are needed.
 */
final class MSL_Theme {

	/**
	 * The one page template this theme exists to serve.
	 */
	public const TEMPLATE = 'template-msl-home.php';

	/**
	 * Font files shipped in assets/fonts/, keyed by family and weight.
	 *
	 * Atlas and Gloria are licensed families supplied by the client and are not
	 * distributed with the theme; see assets/fonts/README.md.
	 */
	private const FONT_FILES = array(
		'atlas-regular.woff2',
		'atlas-black.woff2',
		'gloria-bold.woff2',
	);

	/**
	 * Hook the theme into WordPress.
	 */
	public static function init(): void {
		add_action( 'after_setup_theme', array( self::class, 'setup' ) );
		add_action( 'init', array( self::class, 'drop_emoji_support' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'wp_head', array( self::class, 'preload_fonts' ), 1 );
		add_action( 'wp_head', array( self::class, 'social_tags' ), 4 );
		add_filter( 'body_class', array( self::class, 'body_class' ) );
		add_filter( 'language_attributes', array( self::class, 'language_attributes' ) );
	}

	/**
	 * Declare theme supports.
	 */
	public static function setup(): void {
		load_theme_textdomain( 'mashehu-leshabbat', MSL_DIR . 'languages' );

		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support(
			'html5',
			array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
		);
	}

	/**
	 * Stop core's emoji detection from loading.
	 *
	 * The share-card copy contains a heart, which is enough for WordPress to
	 * fetch a replacement glyph from s.w.org on every page view. That is a
	 * third-party request this site does not make, and every browser the site
	 * targets draws the character itself.
	 */
	public static function drop_emoji_support(): void {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

		add_filter(
			'tiny_mce_plugins',
			static fn( array $plugins ): array => array_diff( $plugins, array( 'wpemoji' ) )
		);
	}

	/**
	 * Cache-busting version for one theme asset.
	 *
	 * Derived from the file's own modification time rather than the theme
	 * version, so editing a stylesheet changes its URL by itself. Relying on a
	 * hand-bumped constant means one forgotten edit serves stale CSS to every
	 * visitor and to LiteSpeed until the cache is purged by hand.
	 *
	 * @param string $relative Path inside the theme.
	 * @return string
	 */
	public static function asset_version( string $relative ): string {
		$mtime = @filemtime( MSL_DIR . $relative ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a missing file falls back below.

		return false !== $mtime ? MSL_VERSION . '.' . $mtime : MSL_VERSION;
	}

	/**
	 * Whether the current request renders the campaign template.
	 *
	 * @return bool
	 */
	public static function is_campaign(): bool {
		static $is = null;

		if ( null !== $is ) {
			return $is;
		}

		$is = is_singular() && self::TEMPLATE === (string) get_page_template_slug( get_queried_object_id() );

		return $is;
	}

	/**
	 * Enqueue styles and scripts, conditionally.
	 *
	 * Nothing is enqueued site-wide: a page that does not use the campaign
	 * template loads none of its CSS or JS.
	 */
	public static function enqueue(): void {
		if ( ! self::is_campaign() ) {
			// Everything outside the campaign template gets a single small
			// stylesheet so a stray page is still readable and accessible.
			wp_enqueue_style( 'msl-plain', MSL_URI . 'assets/css/msl-plain.css', array(), self::asset_version( 'assets/css/msl-plain.css' ) );
			wp_enqueue_script( 'msl-a11y', MSL_URI . 'assets/js/msl-a11y.js', array(), self::asset_version( 'assets/js/msl-a11y.js' ), true );
			wp_script_add_data( 'msl-a11y', 'strategy', 'defer' );
			wp_localize_script( 'msl-a11y', 'mslA11y', self::a11y_data() );

			return;
		}

		// The markup is fully custom; the block library stylesheet is dead weight.
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'global-styles' );
		wp_dequeue_style( 'classic-theme-styles' );

		// NOTE: no `rtl => replace` style data. The stylesheet is written with
		// logical properties and mirrors on its own, so there is no msl-app-rtl.css —
		// declaring one would make every Hebrew-locale site swap to a file that
		// does not exist.
		wp_enqueue_style( 'msl-app', MSL_URI . 'assets/css/msl-app.css', array(), self::asset_version( 'assets/css/msl-app.css' ) );

		wp_enqueue_script( 'msl-canvas', MSL_URI . 'assets/js/msl-canvas.js', array(), self::asset_version( 'assets/js/msl-canvas.js' ), true );
		wp_script_add_data( 'msl-canvas', 'strategy', 'defer' );

		wp_enqueue_script( 'msl-app', MSL_URI . 'assets/js/msl-app.js', array( 'msl-canvas' ), self::asset_version( 'assets/js/msl-app.js' ), true );
		wp_script_add_data( 'msl-app', 'strategy', 'defer' );
		wp_localize_script( 'msl-app', 'MSL', self::script_data() );

		wp_enqueue_script( 'msl-a11y', MSL_URI . 'assets/js/msl-a11y.js', array(), self::asset_version( 'assets/js/msl-a11y.js' ), true );
		wp_script_add_data( 'msl-a11y', 'strategy', 'defer' );
		wp_localize_script( 'msl-a11y', 'mslA11y', self::a11y_data() );
	}

	/**
	 * The moment this week's artwork closes, as a UTC timestamp.
	 *
	 * @param array<string, mixed> $campaign Resolved campaign section.
	 * @return int
	 */
	public static function candle_lighting( array $campaign ): int {
		$weekday = (int) $campaign['candle_day'];
		$time    = (string) $campaign['candle_time'];
		$parts   = explode( ':', $time );
		$hour    = isset( $parts[0] ) ? max( 0, min( 23, (int) $parts[0] ) ) : 19;
		$minute  = isset( $parts[1] ) ? max( 0, min( 59, (int) $parts[1] ) ) : 12;

		$zone = wp_timezone();
		$now  = new DateTimeImmutable( 'now', $zone );

		// PHP counts Sunday as 0 exactly as the design's JS does, so the stored
		// weekday needs no translation.
		$ahead  = ( $weekday - (int) $now->format( 'w' ) + 7 ) % 7;
		$target = $now->modify( '+' . $ahead . ' days' )->setTime( $hour, $minute, 0 );

		if ( $target <= $now ) {
			$target = $target->modify( '+7 days' );
		}

		return $target->getTimestamp();
	}

	/**
	 * Data handed to the front-end script.
	 *
	 * Everything here is either page content or a cached aggregate, so the HTML
	 * stays identical for every visitor and remains fully cacheable. The one
	 * per-visitor value — the referral code the visitor arrived with — is read
	 * by the browser from its own cookie, not printed here.
	 *
	 * @return array<string, mixed>
	 */
	private static function script_data(): array {
		$page_id  = (int) get_the_ID();
		$campaign = MSL_Meta::get( 'campaign', $page_id );
		$stats    = MSL_Stats::all( $page_id );
		$join     = MSL_Meta::get( 'join', $page_id );

		$options = array();

		foreach ( array_values( (array) ( $join['options'] ?? array() ) ) as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$options[] = array(
				'index'   => $index,
				'isOther' => 1 === (int) ( $row['is_other'] ?? 0 ),
			);
		}

		return array(
			'rest'      => array(
				'stats'    => esc_url_raw( rest_url( MSL_REST::NAMESPACE . '/stats' ) ),
				'feed'     => esc_url_raw( rest_url( MSL_REST::NAMESPACE . '/feed' ) ),
				'pieces'   => esc_url_raw( rest_url( MSL_REST::NAMESPACE . '/pieces' ) ),
				'referral' => esc_url_raw( rest_url( MSL_REST::NAMESPACE . '/referral' ) ),
				'join'     => esc_url_raw( rest_url( MSL_REST::NAMESPACE . '/join' ) ),
				'nonce'    => esc_url_raw( rest_url( MSL_REST::NAMESPACE . '/nonce' ) ),
			),
			'campaign'  => array(
				'target'         => (int) $campaign['target'],
				'artwork'        => (string) $campaign['artwork'],
				'accent'         => self::accent( (string) $campaign['accent'] ),
				'candleLighting' => self::candle_lighting( $campaign ),
				'closed'         => 1 === (int) $campaign['closed'],
				'maxThings'      => MSL_Joins::MAX_THINGS,
			),
			'stats'     => array(
				'participants' => $stats['participants'],
				'countries'    => $stats['countries'],
				'cities'       => $stats['cities'],
				'dedications'  => $stats['dedications'],
				'pct'          => $stats['pct'],
				'last10'       => $stats['last10'],
			),
			'options'   => $options,
			'lang'      => MSL_I18N::lang(),
			'langs'     => MSL_I18N::LANGS,
			'cookies'   => array(
				'lang'    => MSL_I18N::COOKIE,
				'ref'     => MSL_Joins::REF_COOKIE,
				'mine'    => MSL_Joins::MINE_COOKIE,
				'refDays' => MSL_Joins::ref_days(),
			),
			'joinBase'  => esc_url_raw( home_url( '/join/' ) ),
			'mapData'   => esc_url_raw( MSL_URI . 'assets/data/world-land.json?v=' . rawurlencode( self::asset_version( 'assets/data/world-land.json' ) ) ),
			'mapPoints' => msl_map_points( $page_id ),
			'i18n'      => MSL_I18N::dictionary( $page_id ),
		);
	}

	/**
	 * Strings the accessibility widget needs, in the rendered language.
	 *
	 * @return array<string, mixed>
	 */
	private static function a11y_data(): array {
		$page_id = MSL_Importer::page_id();
		$chrome  = MSL_Meta::get( 'chrome', $page_id );

		return array(
			'statement' => (string) $chrome['accessibility_url'],
			'i18n'      => array(
				'open'        => __( 'תפריט נגישות', 'mashehu-leshabbat' ),
				'title'       => __( 'התאמות נגישות', 'mashehu-leshabbat' ),
				'close'       => __( 'סגירה', 'mashehu-leshabbat' ),
				'bigger'      => __( 'הגדלת טקסט', 'mashehu-leshabbat' ),
				'smaller'     => __( 'הקטנת טקסט', 'mashehu-leshabbat' ),
				'contrast'    => __( 'ניגודיות גבוהה', 'mashehu-leshabbat' ),
				'dark'        => __( 'ניגודיות כהה', 'mashehu-leshabbat' ),
				'links'       => __( 'הדגשת קישורים', 'mashehu-leshabbat' ),
				'readable'    => __( 'גופן קריא', 'mashehu-leshabbat' ),
				'spacing'     => __( 'ריווח טקסט מוגדל', 'mashehu-leshabbat' ),
				'stopMotion'  => __( 'עצירת אנימציות', 'mashehu-leshabbat' ),
				'reset'       => __( 'איפוס ההתאמות', 'mashehu-leshabbat' ),
				'statement'   => __( 'הצהרת נגישות', 'mashehu-leshabbat' ),
				'textSize'    => __( 'גודל הטקסט: %d אחוז', 'mashehu-leshabbat' ),
			),
		);
	}

	/**
	 * A validated accent colour.
	 *
	 * @param string $value Stored value.
	 * @return string
	 */
	public static function accent( string $value ): string {
		$value = trim( $value );

		return 1 === preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ? strtoupper( $value ) : '#FFB25C';
	}

	/**
	 * Social tags for the campaign page, including personal invite links.
	 *
	 * WhatsApp never runs JavaScript, so a shared personal link has to arrive
	 * with its card already rendered by the server.
	 */
	public static function social_tags(): void {
		if ( ! self::is_campaign() ) {
			return;
		}

		$page_id = (int) get_the_ID();
		$chrome  = MSL_Meta::get( 'chrome', $page_id );
		$closing = MSL_Meta::get( 'closing', $page_id );
		$stats   = MSL_Stats::all( $page_id );

		$title = sprintf(
			/* translators: %s: participant count. */
			'he' === MSL_I18N::lang() ? '%s יהודים כבר הוסיפו משהו לשבת הקרובה.' : '%s people have already added something for this Shabbat.',
			number_format_i18n( $stats['participants'] )
		);

		$tags = array(
			'og:type'              => 'website',
			'og:site_name'         => MSL_I18N::value( $chrome, 'brand' ),
			'og:title'             => $title,
			'og:description'       => MSL_I18N::value( $closing, 'title' ),
			'og:url'               => '' !== MSL_Joins::referrer_code()
				? MSL_Joins::share_url( MSL_Joins::referrer_code() )
				: (string) get_permalink( $page_id ),
			'og:locale'            => 'he' === MSL_I18N::lang() ? 'he_IL' : 'en_US',
			'og:locale:alternate'  => 'he' === MSL_I18N::lang() ? 'en_US' : 'he_IL',
			'twitter:card'         => 'summary_large_image',
		);

		$image = (int) $chrome['logo'] > 0 ? (string) wp_get_attachment_image_url( (int) $chrome['logo'], 'full' ) : '';

		if ( '' !== $image ) {
			$tags['og:image'] = $image;
		}

		foreach ( $tags as $property => $content ) {
			printf(
				'<meta property="%s" content="%s">' . "\n",
				esc_attr( $property ),
				esc_attr( $content )
			);
		}
	}

	/**
	 * Preload the font files that are actually present on disk.
	 *
	 * Atlas and Gloria are licensed and are not distributed with the theme. When
	 * the files have not been installed yet we skip the preload rather than emit
	 * a 404 that would cost a round trip on every page view.
	 */
	public static function preload_fonts(): void {
		if ( ! self::is_campaign() ) {
			return;
		}

		foreach ( self::FONT_FILES as $file ) {
			if ( ! file_exists( MSL_DIR . 'assets/fonts/' . $file ) ) {
				continue;
			}

			printf(
				'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
				esc_url( MSL_URI . 'assets/fonts/' . $file )
			);
		}
	}

	/**
	 * Add a template-specific body class so CSS can scope safely.
	 *
	 * @param string[] $classes Existing body classes.
	 * @return string[]
	 */
	public static function body_class( array $classes ): array {
		if ( self::is_campaign() ) {
			$classes[] = 'msl-page';
			$classes[] = 'msl-page--' . MSL_I18N::lang();
		}

		return $classes;
	}

	/**
	 * Render the campaign page in the visitor's chosen language and direction.
	 *
	 * @param string $output Existing language attributes.
	 * @return string
	 */
	public static function language_attributes( string $output ): string {
		if ( ! self::is_campaign() ) {
			return $output;
		}

		return sprintf( 'dir="%s" lang="%s"', esc_attr( MSL_I18N::dir() ), esc_attr( MSL_I18N::locale() ) );
	}
}
