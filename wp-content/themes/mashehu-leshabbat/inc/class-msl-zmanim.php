<?php
/**
 * Shabbat times, the weekly portion and the Hebrew date.
 *
 * From Hebcal (hebcal.com), which is free, needs no key, and is the reference
 * the Jewish web already runs on. Like the Google sign-in this is a deliberate
 * exception to "no requests to another domain", and it is kept to the same
 * shape: the call is made by the server with wp_remote_get(), the answer is
 * cached, and the visitor's browser never talks to hebcal.com at all.
 *
 * Four rules this file exists to keep:
 *
 * 1. One request per place per few hours, never one per page view. Times for a
 *    given week do not change, and hammering a free service that asks you not
 *    to is how a free service stops being free.
 * 2. A failed call must never blank the page. Everything here falls back to the
 *    weekday and time the campaign has configured by hand, which is what the
 *    site ran on before this existed.
 * 3. The answer is data from somewhere else. It is parsed defensively — a
 *    missing key, a changed shape or an HTML error page all have to end in the
 *    fallback rather than a fatal.
 * 4. Both languages come out of the one call. Hebcal localises `title` to the
 *    language asked for and sends `hebrew` beside it whatever was asked, so the
 *    request goes out in English and the language toggle has both halves
 *    without a second call.
 *
 * What is deliberately *not* done here: candle-lighting offsets. Hebcal already
 * knows that Jerusalem lights forty minutes before sunset and Haifa thirty, and
 * applies the local custom from the place id. Sending our own `b` would
 * override that with a worse answer.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Shabbat times from Hebcal, cached.
 */
final class MSL_Zmanim {

	/**
	 * Hebcal's endpoints. Documented at hebcal.com/home/195.
	 */
	private const SHABBAT_URL   = 'https://www.hebcal.com/shabbat';
	private const CONVERTER_URL = 'https://www.hebcal.com/converter';
	private const ZMANIM_URL    = 'https://www.hebcal.com/zmanim';

	/**
	 * Jerusalem, in Hebcal's place ids.
	 */
	public const DEFAULT_PLACE = 281184;

	/**
	 * The query argument a visitor's chosen place travels in.
	 */
	public const QUERY = 'msl_place';

	/**
	 * The places offered in the editor.
	 *
	 * Hebcal takes a GeoNames id. Every id below was checked against the live
	 * endpoint rather than remembered — an id that is off by a digit does not
	 * fail, it quietly returns another town's times, which is the one kind of
	 * wrong this file must not be. Anything not listed is reachable through the
	 * "another place" field.
	 *
	 * `r` groups the list for the picker on the page: Israel first, then the
	 * rest, because that is the order the people this is for read it in.
	 *
	 * @var array<int, array{he: string, en: string, r: string}>
	 */
	public const PLACES = array(
		281184  => array(
			'he' => 'ירושלים',
			'en' => 'Jerusalem',
			'r'  => 'il',
		),
		293397  => array(
			'he' => 'תל אביב',
			'en' => 'Tel Aviv',
			'r'  => 'il',
		),
		294801  => array(
			'he' => 'חיפה',
			'en' => 'Haifa',
			'r'  => 'il',
		),
		295530  => array(
			'he' => 'באר שבע',
			'en' => 'Beersheba',
			'r'  => 'il',
		),
		295514  => array(
			'he' => 'בני ברק',
			'en' => 'Bnei Brak',
			'r'  => 'il',
		),
		293703  => array(
			'he' => 'ראשון לציון',
			'en' => 'Rishon LeZion',
			'r'  => 'il',
		),
		295629  => array(
			'he' => 'אשדוד',
			'en' => 'Ashdod',
			'r'  => 'il',
		),
		295620  => array(
			'he' => 'אשקלון',
			'en' => 'Ashkelon',
			'r'  => 'il',
		),
		294071  => array(
			'he' => 'נתניה',
			'en' => 'Netanya',
			'r'  => 'il',
		),
		294751  => array(
			'he' => 'חולון',
			'en' => 'Holon',
			'r'  => 'il',
		),
		294946  => array(
			'he' => 'חדרה',
			'en' => 'Hadera',
			'r'  => 'il',
		),
		294098  => array(
			'he' => 'נצרת',
			'en' => 'Nazareth',
			'r'  => 'il',
		),
		295721  => array(
			'he' => 'עכו',
			'en' => 'Acre',
			'r'  => 'il',
		),
		293100  => array(
			'he' => 'צפת',
			'en' => 'Safed',
			'r'  => 'il',
		),
		293322  => array(
			'he' => 'טבריה',
			'en' => 'Tiberias',
			'r'  => 'il',
		),
		295277  => array(
			'he' => 'אילת',
			'en' => 'Eilat',
			'r'  => 'il',
		),
		5128581 => array(
			'he' => 'ניו יורק',
			'en' => 'New York',
			'r'  => 'world',
		),
		5368361 => array(
			'he' => 'לוס אנג׳לס',
			'en' => 'Los Angeles',
			'r'  => 'world',
		),
		4164138 => array(
			'he' => 'מיאמי',
			'en' => 'Miami',
			'r'  => 'world',
		),
		6167865 => array(
			'he' => 'טורונטו',
			'en' => 'Toronto',
			'r'  => 'world',
		),
		6077243 => array(
			'he' => 'מונטריאול',
			'en' => 'Montreal',
			'r'  => 'world',
		),
		2643743 => array(
			'he' => 'לונדון',
			'en' => 'London',
			'r'  => 'world',
		),
		2643123 => array(
			'he' => 'מנצ׳סטר',
			'en' => 'Manchester',
			'r'  => 'world',
		),
		2988507 => array(
			'he' => 'פריז',
			'en' => 'Paris',
			'r'  => 'world',
		),
		2803138 => array(
			'he' => 'אנטוורפן',
			'en' => 'Antwerp',
			'r'  => 'world',
		),
		2950159 => array(
			'he' => 'ברלין',
			'en' => 'Berlin',
			'r'  => 'world',
		),
		524901  => array(
			'he' => 'מוסקבה',
			'en' => 'Moscow',
			'r'  => 'world',
		),
		3435910 => array(
			'he' => 'בואנוס איירס',
			'en' => 'Buenos Aires',
			'r'  => 'world',
		),
		2147714 => array(
			'he' => 'סידני',
			'en' => 'Sydney',
			'r'  => 'world',
		),
		2158177 => array(
			'he' => 'מלבורן',
			'en' => 'Melbourne',
			'r'  => 'world',
		),
		993800  => array(
			'he' => 'יוהנסבורג',
			'en' => 'Johannesburg',
			'r'  => 'world',
		),
	);

	/**
	 * How long an answer is kept.
	 *
	 * Six hours: short enough that the week rolls over promptly after Shabbat
	 * goes out, long enough that a busy Friday is a handful of requests rather
	 * than thousands.
	 */
	private const TTL = 6 * HOUR_IN_SECONDS;

	/**
	 * How long a *failure* is kept.
	 *
	 * Deliberately not zero. Without this, an outage at Hebcal turns every page
	 * view into a fresh timeout — the site would answer more slowly precisely
	 * when the thing it is waiting for is down.
	 */
	private const FAIL_TTL = 20 * MINUTE_IN_SECONDS;

	/**
	 * How long one request may hold the right to refresh the cache.
	 *
	 * Longer than the timeout it guards, so a request that dies mid-call cannot
	 * leave the lock behind for longer than it would have taken anyway.
	 */
	private const LOCK_TTL = 30;

	/**
	 * Per-request cache, so one page view asks the cache once.
	 *
	 * @var array<string, mixed>
	 */
	private static array $memo = array();

	/**
	 * Whether the campaign wants times fetched at all.
	 *
	 * @param array<string, mixed> $zmanim Resolved zmanim section.
	 * @return bool
	 */
	public static function enabled( array $zmanim ): bool {
		return 1 === (int) ( $zmanim['auto'] ?? 0 );
	}

	/**
	 * The place id the section is set to.
	 *
	 * @param array<string, mixed> $zmanim Resolved zmanim section.
	 * @return int
	 */
	public static function place( array $zmanim, ?int $override = null ): int {
		if ( null !== $override && isset( self::PLACES[ $override ] ) ) {
			return $override;
		}

		$custom = (int) ( $zmanim['place_id'] ?? 0 );

		if ( $custom > 0 ) {
			return $custom;
		}

		$chosen = (int) ( $zmanim['place'] ?? 0 );

		return isset( self::PLACES[ $chosen ] ) ? $chosen : self::DEFAULT_PLACE;
	}

	/**
	 * Whether visitors may change the place.
	 *
	 * @param array<string, mixed> $zmanim Resolved zmanim section.
	 * @return bool
	 */
	public static function pickable( array $zmanim ): bool {
		return self::enabled( $zmanim ) && 1 === (int) ( $zmanim['pick_on'] ?? 0 );
	}

	/**
	 * The place a visitor asked for, from the address bar.
	 *
	 * **Only a place on the list is honoured.** This is the one value here a
	 * stranger controls, and it decides where the server sends a request and how
	 * many cache entries exist. An allowlist makes both bounded: an id that is
	 * not on it is ignored rather than fetched.
	 *
	 * A query string is also the one visitor-specific thing a page cache varies
	 * on by default, which is why the choice travels in the address rather than
	 * in a cookie — a cookie would hand one visitor's city to everyone behind
	 * the cache, exactly as a language cookie once did here.
	 *
	 * @param array<string, mixed> $zmanim Resolved zmanim section.
	 * @return int
	 */
	public static function requested( array $zmanim ): int {
		if ( ! self::pickable( $zmanim ) ) {
			return self::place( $zmanim );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a public, read-only preference.
		$asked = isset( $_GET[ self::QUERY ] ) ? absint( wp_unslash( $_GET[ self::QUERY ] ) ) : 0;

		return self::place( $zmanim, $asked > 0 ? $asked : null );
	}

	/**
	 * The place's name, in the language being rendered.
	 *
	 * Hebcal's own location title is English only, so a place we named ourselves
	 * answers in Hebrew and anything else falls back to what Hebcal called it.
	 *
	 * @param array<string, mixed> $zmanim Resolved zmanim section.
	 * @param array<string, mixed> $week   Fetched week, for the fallback title.
	 * @return string
	 */
	public static function place_name( array $zmanim, array $week, ?int $override = null ): string {
		return self::place_names( $zmanim, $week, $override )[ MSL_I18N::lang() ];
	}

	/**
	 * The place's name in both languages.
	 *
	 * Both, because the language toggle swaps without a reload and this name is
	 * not a content field the script's dictionary could swap for it.
	 *
	 * @param array<string, mixed> $zmanim Resolved zmanim section.
	 * @param array<string, mixed> $week   Fetched week, for the fallback title.
	 * @return array{he: string, en: string}
	 */
	public static function place_names( array $zmanim, array $week, ?int $override = null ): array {
		$place = self::place( $zmanim, $override );

		if ( isset( self::PLACES[ $place ] ) ) {
			return array(
				'he' => self::PLACES[ $place ]['he'],
				'en' => self::PLACES[ $place ]['en'],
			);
		}

		$title = (string) ( $week['place'] ?? '' );

		return array(
			'he' => $title,
			'en' => $title,
		);
	}

	/**
	 * This week's times, or null when they could not be had.
	 *
	 * @param array<string, mixed> $zmanim   Resolved zmanim section.
	 * @param int|null             $override A place from the list, chosen by the visitor.
	 * @return array<string, mixed>|null
	 */
	public static function week( array $zmanim, ?int $override = null ): ?array {
		if ( ! self::enabled( $zmanim ) ) {
			return null;
		}

		$place = self::place( $zmanim, $override );
		$havd  = max( 0, min( 120, (int) ( $zmanim['havdalah'] ?? 0 ) ) );
		$key   = 'msl_zmanim_' . $place . '_' . $havd;

		if ( array_key_exists( $key, self::$memo ) ) {
			return self::$memo[ $key ];
		}

		$cached = get_transient( $key );

		if ( is_array( $cached ) ) {
			self::$memo[ $key ] = $cached;

			return $cached;
		}

		// A stored failure. Wait it out rather than retrying on every view.
		if ( 'fail' === $cached ) {
			self::$memo[ $key ] = null;

			return null;
		}

		/*
		 * One fetch at a time.
		 *
		 * The cache expiring is the moment the site is least able to afford a
		 * slow call, because every request in flight finds it empty at once. A
		 * campaign that gets its traffic in the hour before Shabbat would send
		 * hundreds of identical calls to a free service in that second. The
		 * first request through takes the lock and pays the wait; the rest fall
		 * back to the configured time for as long as it takes, which is a page
		 * that renders rather than a page that queues.
		 */
		$lock = $key . '_lock';

		if ( false !== get_transient( $lock ) ) {
			self::$memo[ $key ] = null;

			return null;
		}

		set_transient( $lock, 1, self::LOCK_TTL );

		$week = self::fetch( $place, $havd );

		delete_transient( $lock );

		if ( null === $week ) {
			set_transient( $key, 'fail', self::FAIL_TTL );
			self::$memo[ $key ] = null;

			return null;
		}

		set_transient( $key, $week, self::TTL );
		self::$memo[ $key ] = $week;

		return $week;
	}

	/**
	 * Ask Hebcal for the coming Shabbat.
	 *
	 * @param int $place Hebcal place id.
	 * @param int $havd  Havdalah minutes after sunset, or 0 for nightfall.
	 * @return array<string, mixed>|null
	 */
	private static function fetch( int $place, int $havd ): ?array {
		$args = array(
			'cfg'       => 'json',
			'geonameid' => $place,
			// M=on is Hebcal's "havdalah at nightfall"; it and an explicit
			// number of minutes are alternatives, never both.
			'M'         => $havd > 0 ? 'off' : 'on',
			/*
			 * Not `lg=h`, although the site is Hebrew first. Hebcal localises
			 * `title` to the language asked for and always sends `hebrew`
			 * beside it, so asking in Hebrew returns Hebrew twice and the
			 * English half of the site would print vowelled Hebrew. Asking
			 * here is what gets both languages out of one call.
			 */
			'lg'        => 's',
		);

		if ( $havd > 0 ) {
			$args['m'] = $havd;
		}

		$body = self::ask( add_query_arg( $args, self::SHABBAT_URL ) );

		if ( null === $body || ! isset( $body['items'] ) || ! is_array( $body['items'] ) ) {
			return null;
		}

		return self::parse( $body );
	}

	/**
	 * One GET, decoded, or null for anything that is not a JSON object.
	 *
	 * @param string $url Fully built endpoint.
	 * @return array<string, mixed>|null
	 */
	private static function ask( string $url ): ?array {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 6,
				'user-agent' => 'or-leshabbat/' . MSL_VERSION . '; ' . home_url( '/' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		return is_array( $body ) ? $body : null;
	}

	/**
	 * Pull the coming Shabbat out of Hebcal's week.
	 *
	 * The response covers the next several days, which around a festival holds
	 * more than one candle-lighting: the week of Yom Kippur carries Friday's
	 * and the eve of the fast, and taking "the next one" would put the fast's
	 * time under a heading that says Shabbat.
	 *
	 * So the Shabbat is identified first — it is the day the weekly portion is
	 * read — and its candle-lighting is the one on the day before it. Only when
	 * there is no portion at all (a Shabbat swallowed by a festival) does this
	 * fall back to the next lighting of any kind.
	 *
	 * @param array<string, mixed> $body Decoded response.
	 * @return array<string, mixed>|null
	 */
	private static function parse( array $body ): ?array {
		$items = array();

		foreach ( (array) $body['items'] as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['category'], $item['date'] ) ) {
				continue;
			}

			$stamp = strtotime( (string) $item['date'] );

			if ( false === $stamp ) {
				continue;
			}

			$items[] = array(
				'category' => (string) $item['category'],
				'day'      => substr( (string) $item['date'], 0, 10 ),
				'stamp'    => $stamp,
				'he'       => (string) ( $item['hebrew'] ?? '' ),
				'en'       => (string) ( $item['title'] ?? '' ),
			);
		}

		$portion = null;

		foreach ( $items as $item ) {
			if ( 'parashat' === $item['category'] ) {
				$portion = $item;
				break;
			}
		}

		$shabbat = null !== $portion ? $portion['day'] : '';
		$eve     = '' !== $shabbat ? gmdate( 'Y-m-d', (int) strtotime( $shabbat . ' -1 day' ) ) : '';

		$candles  = null;
		$havdalah = null;
		$holiday  = null;

		foreach ( $items as $item ) {
			if ( 'candles' === $item['category'] && null === $candles
				&& ( '' === $eve || $item['day'] === $eve ) ) {
				$candles = $item;
			}

			if ( 'havdalah' === $item['category'] && null === $havdalah
				&& ( '' === $shabbat ? null !== $candles && $item['stamp'] > $candles['stamp'] : $item['day'] === $shabbat ) ) {
				$havdalah = $item;
			}

			// Only a holiday falling on the Shabbat itself belongs on the card:
			// "Shabbat Shuva" does, the eve of the fast three days later does not.
			if ( 'holiday' === $item['category'] && null === $holiday
				&& '' !== $shabbat && $item['day'] === $shabbat ) {
				$holiday = $item;
			}
		}

		if ( null === $candles ) {
			return null;
		}

		return array(
			'candles'    => $candles['stamp'],
			'havdalah'   => null !== $havdalah ? $havdalah['stamp'] : 0,
			'parsha_he'  => null !== $portion ? self::portion_name( $portion['he'], true ) : '',
			'parsha_en'  => null !== $portion ? self::portion_name( $portion['en'], false ) : '',
			'holiday_he' => null !== $holiday ? $holiday['he'] : '',
			'holiday_en' => null !== $holiday ? $holiday['en'] : '',
			'place'      => (string) ( $body['location']['title'] ?? '' ),
			'tzid'       => (string) ( $body['location']['tzid'] ?? '' ),
		);
	}

	/**
	 * Everything the times card shows, ready to print.
	 *
	 * One method rather than four calls from the template, because the same
	 * answer has to come back through the REST route when a visitor picks a
	 * different city. Two code paths building the same card is two cards that
	 * will disagree with each other eventually.
	 *
	 * @param array<string, mixed> $zmanim   Resolved zmanim section.
	 * @param int|null             $override A place from the list, chosen by the visitor.
	 * @return array<string, mixed>|null
	 */
	public static function card( array $zmanim, ?int $override = null ): ?array {
		$week = self::week( $zmanim, $override );

		if ( null === $week ) {
			return null;
		}

		$zone = self::zone( $zmanim, $override );
		$date = self::hebrew_date( $zmanim, $override );

		return array(
			'place'    => self::place( $zmanim, $override ),
			'names'    => self::place_names( $zmanim, $week, $override ),
			'hdate'    => $date,
			'parsha'   => array(
				'he' => (string) $week['parsha_he'],
				'en' => (string) $week['parsha_en'],
			),
			'holiday'  => array(
				'he' => (string) $week['holiday_he'],
				'en' => (string) $week['holiday_en'],
			),
			'candles'  => (string) wp_date( 'H:i', (int) $week['candles'], $zone ),
			'havdalah' => (int) $week['havdalah'] > 0 ? (string) wp_date( 'H:i', (int) $week['havdalah'], $zone ) : '',
		);
	}

	/**
	 * The bare name of a portion.
	 *
	 * Hebcal answers "פרשת האזינו" and "Parashat Ha’azinu", sometimes vowelled.
	 * Every place the name is shown already supplies the word "parashat" — the
	 * countdown says it, the times card labels it — so carrying it through
	 * would print it twice.
	 *
	 * @param string $text   Hebcal's title.
	 * @param bool   $hebrew Whether this is the Hebrew half.
	 * @return string
	 */
	private static function portion_name( string $text, bool $hebrew ): string {
		if ( ! $hebrew ) {
			return trim( (string) preg_replace( '/^\s*Parashat\s+/iu', '', $text ) );
		}

		// Vowel points first: the prefix is only recognisable without them.
		$text = (string) preg_replace( '/[\x{0591}-\x{05C7}]/u', '', $text );

		return trim( (string) preg_replace( '/^\s*פרשת\s+/u', '', $text ) );
	}

	/**
	 * Today's Hebrew date, in both languages.
	 *
	 * The Jewish day turns at sunset, not at midnight, so the civil date alone
	 * gives the wrong answer for the several hours of the evening that matter
	 * most on a site about Shabbat. Sunset for the chosen place is fetched once
	 * a day and decides which side of the line we are on.
	 *
	 * @param array<string, mixed> $zmanim Resolved zmanim section.
	 * @return array{he: string, en: string}|null
	 */
	public static function hebrew_date( array $zmanim, ?int $override = null ): ?array {
		if ( ! self::enabled( $zmanim ) ) {
			return null;
		}

		$place = self::place( $zmanim, $override );
		$zone  = self::zone( $zmanim, $override );
		$today = wp_date( 'Y-m-d', null, $zone );
		$after = self::after_sunset( $place, (string) $today );
		$key   = 'msl_hdate_' . $place . '_' . $today . ( $after ? '_n' : '' );

		if ( array_key_exists( $key, self::$memo ) ) {
			return self::$memo[ $key ];
		}

		$cached = get_transient( $key );

		if ( is_array( $cached ) ) {
			self::$memo[ $key ] = $cached;

			return $cached;
		}

		if ( 'fail' === $cached ) {
			self::$memo[ $key ] = null;

			return null;
		}

		list( $year, $month, $day ) = array_map( 'intval', explode( '-', (string) $today ) );

		$args = array(
			'cfg'    => 'json',
			'gy'     => $year,
			'gm'     => $month,
			'gd'     => $day,
			'g2h'    => 1,
			'strict' => 1,
		);

		if ( $after ) {
			$args['gs'] = 'on';
		}

		$body = self::ask( add_query_arg( $args, self::CONVERTER_URL ) );
		$he   = is_array( $body ) ? (string) ( $body['hebrew'] ?? '' ) : '';

		if ( '' === $he ) {
			set_transient( $key, 'fail', self::FAIL_TTL );
			self::$memo[ $key ] = null;

			return null;
		}

		$date = array(
			'he' => $he,
			// Hebcal has no English sentence for the date, but it does name the
			// month, and "7 Tishrei 5787" is how the English side writes it.
			'en' => trim( sprintf( '%d %s %d', (int) ( $body['hd'] ?? 0 ), (string) ( $body['hm'] ?? '' ), (int) ( $body['hy'] ?? 0 ) ) ),
		);

		set_transient( $key, $date, self::TTL );
		self::$memo[ $key ] = $date;

		return $date;
	}

	/**
	 * Whether the sun has already set on the chosen place today.
	 *
	 * A failed lookup answers "no", which is the civil date — the answer the
	 * site gave before this existed, and wrong by at most one day for at most
	 * a few hours.
	 *
	 * @param int    $place Hebcal place id.
	 * @param string $today Civil date, Y-m-d, at the place.
	 * @return bool
	 */
	private static function after_sunset( int $place, string $today ): bool {
		$key = 'msl_sunset_' . $place . '_' . $today;

		if ( array_key_exists( $key, self::$memo ) ) {
			$sunset = self::$memo[ $key ];
		} else {
			$sunset = get_transient( $key );

			if ( false === $sunset ) {
				$body = self::ask(
					add_query_arg(
						array(
							'cfg'       => 'json',
							'geonameid' => $place,
							'date'      => $today,
						),
						self::ZMANIM_URL
					)
				);

				$raw    = is_array( $body ) ? (string) ( $body['times']['sunset'] ?? '' ) : '';
				$sunset = '' !== $raw ? (int) strtotime( $raw ) : 0;

				set_transient( $key, $sunset, $sunset > 0 ? DAY_IN_SECONDS : self::FAIL_TTL );
			}

			$sunset             = (int) $sunset;
			self::$memo[ $key ] = $sunset;
		}

		return $sunset > 0 && time() >= $sunset;
	}

	/**
	 * The timezone the times are shown in.
	 *
	 * The place is the authority, not the site: a campaign run from Israel that
	 * publishes New York's times has to print them in New York's clock, or the
	 * number means nothing to the person reading it.
	 *
	 * @param array<string, mixed> $zmanim Resolved zmanim section.
	 * @return DateTimeZone
	 */
	public static function zone( array $zmanim, ?int $override = null ): DateTimeZone {
		$week = self::enabled( $zmanim ) ? self::week( $zmanim, $override ) : null;
		$tzid = null !== $week ? (string) $week['tzid'] : '';

		if ( '' !== $tzid ) {
			try {
				return new DateTimeZone( $tzid );
			} catch ( Exception $e ) {
				unset( $e );
			}
		}

		return wp_timezone();
	}
}
