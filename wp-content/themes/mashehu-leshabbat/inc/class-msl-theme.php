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
	 * The theme's other pages: the story, and the group campaigns.
	 */
	public const TEMPLATE_ABOUT  = 'template-msl-about.php';
	public const TEMPLATE_GROUPS = 'template-msl-groups.php';

	/**
	 * Every template that is part of the design and needs the design's assets.
	 *
	 * @var array<int, string>
	 */
	public const TEMPLATES = array( self::TEMPLATE, self::TEMPLATE_ABOUT, self::TEMPLATE_GROUPS );

	/**
	 * Each template against the set of content sections it carries.
	 *
	 * One map, read by the template tag that answers "which page is this?" and
	 * by the content panel that decides which boxes to show. It used to be two
	 * maps in two files, which is a pair that drifts: a template added to one
	 * and forgotten in the other is a page the panel cannot edit.
	 *
	 * @var array<string, string>
	 */
	public const SECTION_SETS = array(
		self::TEMPLATE        => 'home',
		self::TEMPLATE_ABOUT  => 'about',
		self::TEMPLATE_GROUPS => 'groups',
	);

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
	 * Whether this request renders any page belonging to the design.
	 *
	 * The stylesheet used to be tied to the campaign template alone, because for
	 * a long time that was the only template there was. Adding a second one gave
	 * it the fallback sheet instead — the about page shared the header, the
	 * footer and every class name with the site, and arrived with none of the
	 * CSS that draws them. It is the assets a page needs that decide this, not
	 * which template happens to be first.
	 *
	 * @return bool
	 */
	public static function is_theme_page(): bool {
		static $is = null;

		if ( null !== $is ) {
			return $is;
		}

		$is = is_singular() && in_array( (string) get_page_template_slug( get_queried_object_id() ), self::TEMPLATES, true );

		return $is;
	}

	/**
	 * Enqueue styles and scripts, conditionally.
	 *
	 * Nothing is enqueued site-wide: a page that does not use the campaign
	 * template loads none of its CSS or JS.
	 */
	public static function enqueue(): void {
		if ( ! self::is_theme_page() ) {
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
	 * The artworks the weekly rotation draws from, in order.
	 *
	 * @var array<int, string>
	 */
	public const ARTWORKS = array( 'candles', 'star', 'menorah', 'tablets', 'kiddush', 'jerusalem', 'israel', 'light' );

	/**
	 * The shapes as a person picks them, the weekly rotation included.
	 *
	 * ARTWORKS is the list of shapes that can be drawn; this is the list of
	 * answers to "which artwork", and "a different one every week" is one of
	 * them. Two menus offering the same choice — the form a visitor fills in
	 * and the one in the dashboard — read it from here so they cannot end up
	 * offering different sets.
	 *
	 * @var array<string, string>
	 */
	public const ARTWORK_LABELS = array(
		'rotate'    => 'סבב שבועי',
		'candles'   => 'נרות שבת',
		'star'      => 'מגן דוד',
		'menorah'   => 'מנורה',
		'tablets'   => 'לוחות הברית',
		'kiddush'   => 'כוס קידוש',
		'jerusalem' => 'ירושלים',
		'israel'    => 'מפת ישראל',
		'light'     => 'נקודת אור',
	);

	/**
	 * Where a menu item can point.
	 *
	 * The menu used to take a typed address, and that is why it led nowhere. A
	 * person filling in a field cannot know that the artwork's anchor is spelled
	 * `#msl-stage`, and a typed `/about/` is wrong the moment WordPress lives in
	 * a subdirectory or the permalinks are not pretty — which is exactly what it
	 * was doing. So the editor picks a destination by name and the address is
	 * worked out here, from the pages this install actually has.
	 *
	 * `anchor` is a section of the campaign page, `page` is one of the theme's
	 * pages, `action` opens a window instead of navigating, and `custom` is the
	 * escape hatch for an address that is genuinely outside the site.
	 *
	 * An anchor added here needs a matching line in the `scroll-margin` rule in
	 * msl-app.css, or the section it names stops behind the sticky header.
	 *
	 * @var array<string, array<string, string>>
	 */
	public const NAV_TARGETS = array(
		'stage'    => array(
			'label'  => 'היצירה וקיר הנרות',
			'anchor' => 'msl-stage',
		),
		'zmanim'   => array(
			'label'  => 'זמני השבת',
			'anchor' => 'msl-zmanim',
		),
		'stats'    => array(
			'label'  => 'השבת הזאת',
			'anchor' => 'msl-stats',
		),
		'map'      => array(
			'label'  => 'המפה',
			'anchor' => 'msl-map',
		),
		'referral' => array(
			'label'  => 'סקשן השיתוף',
			'anchor' => 'msl-referral',
		),
		'verses'   => array(
			'label'  => 'פסוקים ומאמרי חכמים',
			'anchor' => 'msl-verses',
		),
		'groupcta' => array(
			'label'  => 'ההזמנה לפתוח קבוצה',
			'anchor' => 'msl-groupcta',
		),
		'home'     => array(
			'label' => 'עמוד הקמפיין',
			'page'  => 'home',
		),
		'about'    => array(
			'label' => 'עמוד "על המיזם"',
			'page'  => 'about',
		),
		'groups'   => array(
			'label' => 'עמוד הקבוצות',
			'page'  => 'groups',
		),
		'join'     => array(
			'label'  => 'פתיחת חלון ההצטרפות',
			'action' => 'join',
		),
		'invite'   => array(
			'label'  => 'פתיחת חלון הקישור האישי',
			'action' => 'invite',
		),
		'custom'   => array(
			'label'  => 'כתובת אחרת — מהשדה שמתחת',
			'custom' => 'url',
		),
	);

	/**
	 * Which artwork this Shabbat is made of.
	 *
	 * The rotation is *derived* from the week number rather than stored and
	 * advanced by a job: there is no cron to miss, a week that nobody visits
	 * does not shift the order, and every visitor sees the same artwork however
	 * long the page has been sitting in a cache. Picking a shape by name in the
	 * editor pins that shape until it is set back to the rotation.
	 *
	 * The week is taken from the Shabbat the artwork belongs to, not from the
	 * moment of the request, and a Shabbat keeps its own artwork while it is in:
	 * the next one starts when Shabbat goes out, which is what the page says it
	 * does. Keying it off the request instead would swap the shape on screen at
	 * candle-lighting, halfway through the Shabbat it was made for.
	 *
	 * @param array<string, mixed> $campaign Resolved campaign section.
	 * @return string
	 */
	public static function artwork_kind( array $campaign ): string {
		$chosen = (string) ( $campaign['artwork'] ?? 'rotate' );

		if ( in_array( $chosen, self::ARTWORKS, true ) ) {
			return $chosen;
		}

		$next = self::candle_lighting( $campaign );
		$last = $next - WEEK_IN_SECONDS;

		// Shabbat runs a little over 25 hours from candle-lighting to nightfall
		// at the long end of the year, which is the figure to be generous with:
		// erring late holds the right artwork, erring early swaps it too soon.
		$shabbat = time() < $last + 25 * HOUR_IN_SECONDS ? $last : $next;
		$week    = (int) wp_date( 'W', $shabbat );

		return self::ARTWORKS[ ( $week - 1 ) % count( self::ARTWORKS ) ];
	}

	/**
	 * The moment this week's artwork closes, as a UTC timestamp.
	 *
	 * Hebcal answers this when it is switched on, and it answers it better than
	 * a fixed weekly time can: the hour moves with the season, and around a
	 * festival the week does not always end where the calendar says.
	 *
	 * The contract is that the value is always in the future — the countdown,
	 * the urgency line and the weekly artwork rotation all read it that way —
	 * so a fetched time that has already passed (we are inside Shabbat, and the
	 * cache has not yet rolled over to the next one) hands back to the manual
	 * setting, which rolls itself forward by a week.
	 *
	 * @param array<string, mixed> $campaign Resolved campaign section.
	 * @return int
	 */
	public static function candle_lighting( array $campaign ): int {
		$week = MSL_Zmanim::week( MSL_Meta::get( 'zmanim' ) );

		if ( null !== $week && (int) $week['candles'] > time() ) {
			return (int) $week['candles'];
		}

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
	 * The group this request is looking at, or null.
	 *
	 * The campaign's own numbers stay exactly where they are in the payload —
	 * the header counter on a group page still counts everybody. This is the
	 * separate set the group's artwork and progress bar read, so neither number
	 * is ever asked to be the other.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function group_data(): ?array {
		if ( ! class_exists( 'MSL_Groups' ) ) {
			return null;
		}

		$code  = MSL_Groups::requested_code();
		$group = '' !== $code ? MSL_Groups::by_code( $code ) : null;

		if ( null === $group ) {
			return null;
		}

		return array(
			'code'    => (string) $group['code'],
			'target'  => max( 1, (int) $group['target'] ),
			'count'   => MSL_Groups::lights( $group ),
			'artwork' => self::artwork_kind(
				array(
					'artwork'     => (string) $group['artwork'],
					'candle_day'  => '5',
					'candle_time' => '19:12',
				)
			),
			'accent'  => self::accent( (string) $group['accent'] ),
			'live'    => MSL_Groups::LIVE === $group['status'],
		);
	}

	/**
	 * What this Shabbat is called, in both languages, when it was fetched.
	 *
	 * Whole names, the word "parashat" included, for the reason msl_parsha()
	 * gives: a Shabbat inside a festival has no portion, and the countdown has
	 * to be able to say "Sukkot I" in the same slot that usually says "Parashat
	 * Mishpatim". The browser builds the same sentence the server does, so it
	 * has to be handed the same piece.
	 *
	 * Empty when the times are switched off or the fetch failed, which is the
	 * browser's signal to fall back to the content field exactly as the server
	 * already has.
	 *
	 * @return array<string, string>
	 */
	private static function parsha_pair(): array {
		$week = MSL_Zmanim::week( MSL_Meta::get( 'zmanim' ) );

		if ( null === $week ) {
			return array();
		}

		$pair = array();

		foreach ( array( 'he', 'en' ) as $lang ) {
			$portion = (string) $week[ 'parsha_' . $lang ];
			$holiday = (string) $week[ 'holiday_' . $lang ];

			if ( '' !== $portion ) {
				$pair[ $lang ] = msl_parsha_prefix( $lang ) . $portion;
			} elseif ( '' !== $holiday ) {
				$pair[ $lang ] = $holiday;
			}
		}

		return 2 === count( $pair ) ? $pair : array();
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
		/*
		 * The campaign's numbers live on the campaign page. The header runs on
		 * the about page too — the countdown, the counter, the join button — so
		 * reading them off whatever page is being viewed would give that page
		 * its own empty statistics.
		 */
		$page_id  = self::is_campaign() ? (int) get_the_ID() : MSL_Importer::page_id();
		$campaign = MSL_Meta::get( 'campaign', $page_id );
		$stats    = MSL_Stats::all( $page_id );
		$join     = MSL_Meta::get( 'join', $page_id );
		$stage    = MSL_Meta::get( 'stage', $page_id );
		$hero     = MSL_Meta::get( 'hero', $page_id );
		$auth     = MSL_Meta::get( 'auth', $page_id );

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
				'remind'   => esc_url_raw( rest_url( MSL_REST::NAMESPACE . '/remind' ) ),
				'zmanim'   => esc_url_raw( rest_url( MSL_REST::NAMESPACE . '/zmanim' ) ),
				'nonce'    => esc_url_raw( rest_url( MSL_REST::NAMESPACE . '/nonce' ) ),
			),
			'campaign'  => array(
				'target'         => (int) $campaign['target'],
				'artwork'        => self::artwork_kind( $campaign ),
				'accent'         => self::accent( (string) $campaign['accent'] ),
				'candleLighting' => self::candle_lighting( $campaign ),
				'closed'         => 1 === (int) $campaign['closed'],
				'maxThings'      => MSL_Joins::MAX_THINGS,
				'demoNames'      => 1 === (int) ( $stage['demo_names'] ?? 0 ),
				/*
				 * The fetched portion name, in both languages. The countdown is
				 * re-rendered every second by the browser, so without this the
				 * header would show the live name from the server for one tick
				 * and then quietly replace it with the hand-typed one.
				 */
				'parsha'         => self::parsha_pair(),
				'lights'         => max( 0, min( 80, (int) ( $hero['light_count'] ?? 0 ) ) ),
			),
			'stats'     => array(
				'participants' => $stats['participants'],
				'countries'    => $stats['countries'],
				'cities'       => $stats['cities'],
				'dedications'  => $stats['dedications'],
				'pct'          => $stats['pct'],
				'last10'       => $stats['last10'],
			),
			'group'     => self::group_data(),
			'options'   => $options,
			'lang'      => MSL_I18N::lang(),
			'langs'     => MSL_I18N::LANGS,
			'cookies'   => array(
				'lang'    => MSL_I18N::COOKIE,
				'ref'     => MSL_Joins::REF_COOKIE,
				'mine'    => MSL_Joins::MINE_COOKIE,
				'piece'   => MSL_Joins::PIECE_COOKIE,
				'refDays' => MSL_Joins::ref_days(),
			),
			'auth'      => array(
				'enabled'  => MSL_Auth::enabled(),
				'signedIn' => null !== MSL_Auth::current(),
				'link'     => esc_url_raw( MSL_Auth::personal_link() ),
				'delay'    => max( 0, (int) ( $auth['invite_delay'] ?? 0 ) ),
				'days'     => max( 0, (int) ( $auth['invite_days'] ?? 0 ) ),
				'remindOn' => 1 === (int) ( $auth['remind_on'] ?? 0 ),
				'idle'     => max( 0, (int) ( $auth['remind_idle'] ?? 0 ) ),
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
			'he' === MSL_I18N::lang() ? '%s יהודים כבר הוסיפו אור לשבת הקרובה.' : '%s people have already added their light for this Shabbat.',
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
		if ( ! self::is_theme_page() ) {
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
		/*
		 * Every design page carries this, not only the campaign. A very large
		 * part of the stylesheet is scoped under `.msl-page` — the element
		 * reset, the locked-scroll rule and every rule that has to outrank it —
		 * so a page without the class gets the markup of the site and almost
		 * none of its styling. That is precisely what happened to the about
		 * page: same header, same class names, no `.msl-page` to match on.
		 */
		if ( self::is_theme_page() ) {
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
		if ( ! self::is_theme_page() ) {
			return $output;
		}

		return sprintf( 'dir="%s" lang="%s"', esc_attr( MSL_I18N::dir() ), esc_attr( MSL_I18N::locale() ) );
	}
}
