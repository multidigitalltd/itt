<?php
/**
 * Theme setup and conditional asset loading.
 *
 * @package ITT_Landing
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Registers theme supports and enqueues assets only where they are needed.
 */
final class ITT_Theme {

	/**
	 * Page template file names handled by this theme.
	 */
	private const TEMPLATES = array(
		'landing'    => 'template-itt-landing.php',
		'thank-you'  => 'template-itt-thank-you.php',
	);

	/**
	 * Font weights shipped in assets/fonts/, keyed by CSS font-weight.
	 */
	private const FONT_FILES = array(
		400 => 'ploni-regular.woff2',
		600 => 'ploni-demibold.woff2',
		700 => 'ploni-bold.woff2',
	);

	/**
	 * Hook the theme into WordPress.
	 */
	public static function init(): void {
		add_action( 'after_setup_theme', array( self::class, 'setup' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue' ) );
		add_action( 'wp_head', array( self::class, 'preload_fonts' ), 1 );
		add_filter( 'body_class', array( self::class, 'body_class' ) );
		add_filter( 'language_attributes', array( self::class, 'language_attributes' ) );
	}

	/**
	 * Declare theme supports.
	 */
	public static function setup(): void {
		load_theme_textdomain( 'itt-landing', ITT_DIR . 'languages' );

		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support(
			'html5',
			array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
		);

		register_nav_menus(
			array(
				'itt_legal' => __( 'קישורי תחתית (נגישות ותקנון)', 'itt-landing' ),
			)
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
	 * One stat() per enqueued file, served from PHP's realpath cache after the
	 * first request. Falls back to the theme version if the file is unreadable.
	 *
	 * @param string $relative Path inside the theme, e.g. 'assets/css/itt-base.css'.
	 * @return string Version string for wp_enqueue_*().
	 */
	public static function asset_version( string $relative ): string {
		$mtime = @filemtime( ITT_DIR . $relative ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a missing file falls back below.

		return false !== $mtime ? ITT_VERSION . '.' . $mtime : ITT_VERSION;
	}

	/**
	 * Returns the template key of the current singular view, or an empty string.
	 *
	 * Resolved once per request; used by both asset loading and body classes.
	 */
	public static function current_template(): string {
		static $key = null;

		if ( null !== $key ) {
			return $key;
		}

		$key = '';

		if ( is_singular() ) {
			$file = (string) get_page_template_slug( get_queried_object_id() );
			$key  = (string) array_search( $file, self::TEMPLATES, true );
		}

		return $key;
	}

	/**
	 * Enqueue styles and scripts, conditionally per template.
	 *
	 * Nothing is enqueued site-wide: a page that does not use one of the ITT
	 * templates loads none of the landing CSS or JS.
	 */
	public static function enqueue(): void {
		$template = self::current_template();

		if ( '' === $template ) {
			// Everything outside the two ITT templates gets a single small
			// stylesheet so a stray page is still readable and accessible.
			wp_enqueue_style( 'itt-plain', ITT_URI . 'assets/css/itt-plain.css', array(), self::asset_version( 'assets/css/itt-plain.css' ) );

			return;
		}

		// The landing markup is fully custom; the block library stylesheet is dead weight here.
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'global-styles' );
		wp_dequeue_style( 'classic-theme-styles' );

		// NOTE: no `rtl => replace` style data here. The stylesheet is written
		// RTL-native with logical properties, so there is no itt-base-rtl.css —
		// declaring one would make every Hebrew-locale site swap to a file that
		// does not exist and lose the whole base layer.
		wp_enqueue_style( 'itt-base', ITT_URI . 'assets/css/itt-base.css', array(), self::asset_version( 'assets/css/itt-base.css' ) );

		if ( 'landing' === $template ) {
			wp_enqueue_style( 'itt-landing', ITT_URI . 'assets/css/itt-landing.css', array( 'itt-base' ), self::asset_version( 'assets/css/itt-landing.css' ) );

			wp_enqueue_script( 'itt-landing', ITT_URI . 'assets/js/itt-landing.js', array(), self::asset_version( 'assets/js/itt-landing.js' ), true );
			wp_script_add_data( 'itt-landing', 'strategy', 'defer' );
			wp_localize_script( 'itt-landing', 'ittLanding', self::script_data() );
		}

		if ( 'thank-you' === $template ) {
			wp_enqueue_style( 'itt-thank-you', ITT_URI . 'assets/css/itt-thank-you.css', array( 'itt-base' ), self::asset_version( 'assets/css/itt-thank-you.css' ) );
		}

		// Accessibility widget + motion layer. Loaded on both templates because
		// the widget must be reachable from every page the theme renders.
		wp_enqueue_script( 'itt-a11y', ITT_URI . 'assets/js/itt-a11y.js', array(), self::asset_version( 'assets/js/itt-a11y.js' ), true );
		wp_script_add_data( 'itt-a11y', 'strategy', 'defer' );
	}

	/**
	 * Data handed to the landing script.
	 *
	 * The REST nonce is intentionally *not* printed here: a full-page cache
	 * (LiteSpeed / Cloudflare) would serve a stale one. The script fetches a
	 * fresh nonce from an uncached endpoint right before submitting.
	 *
	 * @return array<string, mixed>
	 */
	private static function script_data(): array {
		return array(
			'nonceUrl'  => esc_url_raw( rest_url( ITT_Leads::REST_NAMESPACE . '/token' ) ),
			'submitUrl' => esc_url_raw( rest_url( ITT_Leads::REST_NAMESPACE . '/lead' ) ),
			'i18n'      => array(
				'nameRequired'  => __( 'נא למלא שם מלא.', 'itt-landing' ),
				'phoneInvalid'  => __( 'נא למלא מספר טלפון תקין (לפחות 9 ספרות).', 'itt-landing' ),
				'emailInvalid'  => __( 'נא למלא כתובת אימייל תקינה.', 'itt-landing' ),
				'sending'       => __( 'שולח…', 'itt-landing' ),
				'success'       => __( 'הפרטים התקבלו — נחזור אלייך בהקדם.', 'itt-landing' ),
				'genericError'  => __( 'השליחה נכשלה. אפשר לנסות שוב או ליצור קשר בוואטסאפ.', 'itt-landing' ),
				'errorsHeading' => __( 'לא הצלחנו לשלוח את הטופס:', 'itt-landing' ),
				'readMore'      => __( 'קרא עוד', 'itt-landing' ),
				'readLess'      => __( 'הצג פחות', 'itt-landing' ),
			),
		);
	}

	/**
	 * Preload the font files that are actually present on disk.
	 *
	 * Ploni is a licensed family and is not distributed with the theme. When the
	 * files have not been installed yet we skip the preload rather than emit a
	 * 404 that would cost a round trip on every page view.
	 */
	public static function preload_fonts(): void {
		if ( '' === self::current_template() ) {
			return;
		}

		foreach ( array( 400, 700 ) as $weight ) {
			$file = self::FONT_FILES[ $weight ];

			if ( ! file_exists( ITT_DIR . 'assets/fonts/' . $file ) ) {
				continue;
			}

			printf(
				'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
				esc_url( ITT_URI . 'assets/fonts/' . $file )
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
		$template = self::current_template();

		if ( '' !== $template ) {
			$classes[] = 'itt-page';
			$classes[] = 'itt-page--' . $template;
		}

		return $classes;
	}

	/**
	 * Force RTL/Hebrew on the ITT templates even when the site locale differs.
	 *
	 * @param string $output Existing language attributes.
	 * @return string
	 */
	public static function language_attributes( string $output ): string {
		if ( '' === self::current_template() || is_rtl() ) {
			return $output;
		}

		return 'dir="rtl" lang="he-IL"';
	}
}
