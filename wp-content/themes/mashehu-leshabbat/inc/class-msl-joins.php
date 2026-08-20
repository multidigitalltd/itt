<?php
/**
 * Recording and reading participation.
 *
 * Everything a visitor is asked for is here, and nothing else: a first name, a
 * city, a country, up to three chosen things, an optional dedication and an
 * optional reminder contact. No account, no password, no tracking identifier.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the participation tables.
 */
final class MSL_Joins {

	/**
	 * Most things one person may choose.
	 */
	public const MAX_THINGS = 3;

	/**
	 * Cookie carrying the inviter's referral code.
	 */
	public const REF_COOKIE = 'msl_ref';

	/**
	 * Cookie carrying this visitor's own join, so a returning visitor sees their
	 * share card rather than an empty form.
	 */
	public const MINE_COOKIE = 'msl_mine';

	/**
	 * Days the referral attribution survives.
	 */
	private const REF_DAYS = 30;

	/**
	 * Rate limit: joins allowed per hour and per day from one address.
	 */
	private const RATE_HOUR = 5;
	private const RATE_DAY  = 20;

	/**
	 * Hook the referral rewrite rule.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'add_rewrite' ) );
		add_filter( 'query_vars', array( self::class, 'query_vars' ) );
		add_action( 'parse_request', array( self::class, 'route_invite' ) );
		add_filter( 'redirect_canonical', array( self::class, 'keep_invite_url' ) );
		add_action( 'after_switch_theme', array( self::class, 'flush_rewrite' ), 20 );
	}

	/**
	 * Personal invite links: /join/{code} renders the home page.
	 *
	 * Registered on every request because a rewrite rule that only exists after
	 * activation disappears the first time another plugin flushes the rules.
	 */
	public static function add_rewrite(): void {
		add_rewrite_rule( '^join/([A-Za-z0-9]{6,12})/?$', 'index.php?msl_ref=$matches[1]', 'top' );
	}

	/**
	 * Register the referral query var.
	 *
	 * @param string[] $vars Existing query vars.
	 * @return string[]
	 */
	public static function query_vars( array $vars ): array {
		$vars[] = 'msl_ref';

		return $vars;
	}

	/**
	 * Point an invite link at the campaign page.
	 *
	 * The rewrite rule deliberately does not name a page ID. Baking one into a
	 * rewrite rule means the rules have to be flushed every time the page is
	 * recreated, and a stale rule sends every invite link to the blog index —
	 * which is exactly what it did. Resolving the page here happens per request,
	 * so the rules can stay static and still always be right.
	 *
	 * @param WP $wp Current request.
	 */
	public static function route_invite( WP $wp ): void {
		if ( empty( $wp->query_vars['msl_ref'] ) ) {
			return;
		}

		$page_id = MSL_Importer::page_id();

		if ( 0 !== $page_id ) {
			$wp->query_vars['page_id'] = $page_id;
		}
	}

	/**
	 * Flush the rules once, on activation only.
	 */
	public static function flush_rewrite(): void {
		self::add_rewrite();
		flush_rewrite_rules();
	}

	/**
	 * Keep an invite link at its own address.
	 *
	 * Once the request resolves to the campaign page, WordPress's canonical
	 * redirect wants to send it to that page's permalink — which throws away
	 * the code, and with it the attribution, before anything has read it.
	 *
	 * @param string|false $redirect Where core wants to send the request.
	 * @return string|false
	 */
	public static function keep_invite_url( $redirect ) {
		return '' !== self::referrer_code() && '' !== (string) get_query_var( 'msl_ref' ) ? false : $redirect;
	}

	/**
	 * The inviter's code for this request: the pretty URL, then the cookie.
	 *
	 * @return string
	 */
	public static function referrer_code(): string {
		$code = sanitize_key( (string) get_query_var( 'msl_ref' ) );

		if ( '' === $code && isset( $_COOKIE[ self::REF_COOKIE ] ) ) {
			$code = sanitize_key( wp_unslash( (string) $_COOKIE[ self::REF_COOKIE ] ) );
		}

		return self::is_code( $code ) ? $code : '';
	}

	/**
	 * Whether a string is shaped like a referral code.
	 *
	 * @param string $code Candidate.
	 * @return bool
	 */
	public static function is_code( string $code ): bool {
		return 1 === preg_match( '/^[a-z0-9]{6,12}$/', $code );
	}

	/**
	 * Days the referral cookie should live, for the browser to apply.
	 *
	 * @return int
	 */
	public static function ref_days(): int {
		return self::REF_DAYS;
	}

	/**
	 * The public link for a referral code.
	 *
	 * @param string $code Referral code.
	 * @return string
	 */
	public static function share_url( string $code ): string {
		return self::is_code( $code ) ? home_url( '/join/' . $code . '/' ) : home_url( '/' );
	}

	/* ---------------------------------------------------------------------
	 * Writing
	 * ------------------------------------------------------------------ */

	/**
	 * Record one join.
	 *
	 * @param int                  $page_id Page the join was made from.
	 * @param array<string, mixed> $data    Already-validated values.
	 * @param string               $ip_hash Salted hash of the client address.
	 * @return array<string, mixed>|WP_Error Public payload, or an error code the REST layer maps to a message.
	 */
	public static function record( int $page_id, array $data, string $ip_hash ): array|WP_Error {
		global $wpdb;

		if ( ! MSL_DB::ready() ) {
			MSL_DB::install();
		}

		$email_hash = MSL_DB::hash( (string) $data['email'] );
		$phone_hash = MSL_DB::hash( self::normalise_phone( (string) $data['phone'] ) );

		$duplicate = self::find_duplicate( $page_id, $ip_hash, $email_hash, $phone_hash, (string) $data['first_name'], (string) $data['city'] );

		if ( '' !== $duplicate ) {
			return new WP_Error( 'msl_duplicate', $duplicate );
		}

		$code       = self::generate_code();
		$uuid       = wp_generate_uuid4();
		$table      = MSL_DB::joins_table();
		$referrer   = self::is_code( (string) $data['referred_by'] ) ? (string) $data['referred_by'] : '';
		$reminder   = '' !== (string) $data['phone'] || '' !== (string) $data['email'];
		$coordinate = self::locate( (string) $data['city'], (string) $data['country'] );

		/*
		 * Reading the next free position and writing it are two statements, and
		 * on a Friday afternoon two joins land between them constantly. The
		 * position is UNIQUE per campaign (see MSL_DB), so the loser of that race
		 * gets a failed insert rather than a duplicate position — and a duplicate
		 * position is not a cosmetic problem: /pieces is keyed by position, so
		 * one of the two participants would simply vanish from the artwork and
		 * both would watch their candle land on the same spot.
		 *
		 * Re-reading and retrying is enough. The window is a single statement
		 * wide, so even a handful of simultaneous joins settle within a few
		 * attempts, and this needs no lock and no separate sequence table.
		 */
		$inserted = false;
		$piece    = 0;

		for ( $attempt = 0; $attempt < 10 && ! $inserted; $attempt++ ) {
			$piece = MSL_Stats::next_piece_index( $page_id );

			// Errors are expected here — that is the point — so the failed
			// insert must not be reported to the caller as a broken query.
			$suppress = $wpdb->suppress_errors( true );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- purpose-built table; see MSL_DB.
			$inserted = $wpdb->insert(
				$table,
				array(
					'uuid'           => $uuid,
					'page_id'        => $page_id,
					'piece_index'    => $piece,
					'first_name'     => (string) $data['first_name'],
					'city'           => (string) $data['city'],
					'country'        => (string) $data['country'],
					'lat'            => $coordinate[0],
					'lng'            => $coordinate[1],
					'is_anonymous'   => (int) $data['is_anonymous'],
					'lang'           => (string) $data['lang'],
					'referral_code'  => $code,
					'referred_by'    => $referrer,
					'phone_hash'     => $phone_hash,
					'email_hash'     => $email_hash,
					'ip_hash'        => $ip_hash,
					'reminder_optin' => $reminder ? 1 : 0,
					'reminder_phone' => '' !== (string) $data['phone'] ? self::protect( (string) $data['phone'] ) : null,
					'reminder_email' => '' !== (string) $data['email'] ? self::protect( (string) $data['email'] ) : null,
					'created_at'     => current_time( 'mysql', true ),
				),
				array( '%s', '%d', '%d', '%s', '%s', '%s', '%f', '%f', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
			);

			$wpdb->suppress_errors( $suppress );
		}

		if ( ! $inserted ) {
			return new WP_Error( 'msl_insert_failed', 'generic' );
		}

		$join_id = (int) $wpdb->insert_id;

		self::store_things( $join_id, (array) $data['things'], (string) $data['custom_label'] );
		self::store_dedication( $join_id, $page_id, $data['dedication'], (string) $data['dedication_body'] );

		MSL_Stats::flush( $page_id );

		return array(
			'uuid'           => $uuid,
			'referral_code'  => $code,
			'piece_index'    => $piece,
			'participants'   => MSL_Stats::participants( $page_id ),
			'share_url'      => self::share_url( $code ),
			'referral_count' => 0,
		);
	}

	/**
	 * Store the chosen things.
	 *
	 * @param int      $join_id      Join row ID.
	 * @param int[]    $things       Indexes into the campaign's option list.
	 * @param string   $custom_label Free text, when "something else" was chosen.
	 */
	private static function store_things( int $join_id, array $things, string $custom_label ): void {
		global $wpdb;

		foreach ( array_slice( $things, 0, self::MAX_THINGS ) as $index ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				MSL_DB::things_table(),
				array(
					'join_id'      => $join_id,
					'thing_index'  => (int) $index,
					'custom_label' => $custom_label,
				),
				array( '%d', '%d', '%s' )
			);

			// Only the first row carries the free text; the column exists so a
			// custom label can be read without a second join.
			$custom_label = '';
		}
	}

	/**
	 * Store a dedication, always pending review.
	 *
	 * @param int      $join_id Join row ID.
	 * @param int      $page_id Page ID.
	 * @param int|null $kind    Index into the dedication type list.
	 * @param string   $body    Free text.
	 */
	private static function store_dedication( int $join_id, int $page_id, ?int $kind, string $body ): void {
		global $wpdb;

		if ( null === $kind && '' === trim( $body ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			MSL_DB::dedications_table(),
			array(
				'join_id'    => $join_id,
				'page_id'    => $page_id,
				'kind'       => (int) $kind,
				'body'       => $body,
				'status'     => 'pending',
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s' )
		);
	}

	/* ---------------------------------------------------------------------
	 * Abuse controls
	 * ------------------------------------------------------------------ */

	/**
	 * Which duplicate rule, if any, this submission trips.
	 *
	 * A shared household or office address is the normal case, not the abusive
	 * one, so the address alone never blocks: it takes the same address *and*
	 * the same name and city before we call it a repeat. A repeated phone or
	 * email is a hard block, because those are personal.
	 *
	 * @param int    $page_id    Page ID.
	 * @param string $ip_hash    Hashed address.
	 * @param string $email_hash Hashed email.
	 * @param string $phone_hash Hashed phone.
	 * @param string $first_name Submitted first name.
	 * @param string $city       Submitted city.
	 * @return string Empty when the submission is new.
	 */
	private static function find_duplicate( int $page_id, string $ip_hash, string $email_hash, string $phone_hash, string $first_name, string $city ): string {
		global $wpdb;

		$table = MSL_DB::joins_table();

		if ( '' !== $email_hash ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$hit = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE page_id = %d AND email_hash = %s", $page_id, $email_hash ) );

			if ( $hit > 0 ) {
				return 'duplicate';
			}
		}

		if ( '' !== $phone_hash ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$hit = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE page_id = %d AND phone_hash = %s", $page_id, $phone_hash ) );

			if ( $hit > 0 ) {
				return 'duplicate';
			}
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$hit = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE page_id = %d AND ip_hash = %s AND first_name = %s AND city = %s",
				$page_id,
				$ip_hash,
				$first_name,
				$city
			)
		);

		return $hit > 0 ? 'duplicate' : '';
	}

	/**
	 * Whether this address is still inside the rate limit.
	 *
	 * @param string $ip_hash Hashed address.
	 * @param bool   $consume Whether to count this call against the allowance.
	 * @return bool
	 */
	public static function within_rate_limit( string $ip_hash, bool $consume ): bool {
		$windows = array(
			'msl_rl_h_' . $ip_hash => array( self::RATE_HOUR, HOUR_IN_SECONDS ),
			'msl_rl_d_' . $ip_hash => array( self::RATE_DAY, DAY_IN_SECONDS ),
		);

		foreach ( $windows as $key => $window ) {
			if ( (int) get_transient( $key ) >= $window[0] ) {
				return false;
			}
		}

		if ( $consume ) {
			foreach ( $windows as $key => $window ) {
				set_transient( $key, (int) get_transient( $key ) + 1, $window[1] );
			}
		}

		return true;
	}

	/**
	 * Salted hash of the client address.
	 *
	 * @return string
	 */
	public static function client_ip_hash(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) )
			: '';

		return MSL_DB::hash( '' !== $ip ? $ip : 'unknown' );
	}

	/* ---------------------------------------------------------------------
	 * Reading
	 * ------------------------------------------------------------------ */

	/**
	 * How many people joined through one referral code.
	 *
	 * @param string $code Referral code.
	 * @return int
	 */
	public static function referral_count( string $code ): int {
		global $wpdb;

		if ( ! self::is_code( $code ) || ! MSL_DB::ready() ) {
			return 0;
		}

		$key    = 'msl_ref_' . $code;
		$cached = get_transient( $key );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		$table = MSL_DB::joins_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE referred_by = %s", $code ) );

		set_transient( $key, $count, 30 );

		return $count;
	}

	/**
	 * The public activity feed: consented joins, newest first.
	 *
	 * Anonymous rows never appear here, and no row carries anything beyond a
	 * first name, a city and the thing that was chosen.
	 *
	 * @param int                  $page_id Page ID.
	 * @param array<string, mixed> $options Resolved join options, for labels.
	 * @param int                  $limit   Rows to return.
	 * @return array<int, array<string, string>>
	 */
	public static function feed( int $page_id, array $options, int $limit = 40 ): array {
		global $wpdb;

		if ( ! MSL_DB::ready() ) {
			return array();
		}

		$key    = 'msl_feed_' . $page_id . '_' . MSL_I18N::lang();
		$cached = get_transient( $key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$joins  = MSL_DB::joins_table();
		$things = MSL_DB::things_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT j.first_name, j.city, t.thing_index, t.custom_label
				 FROM {$joins} j
				 LEFT JOIN {$things} t ON t.join_id = j.id AND t.thing_index = (
					 SELECT MIN(thing_index) FROM {$things} WHERE join_id = j.id
				 )
				 WHERE j.page_id = %d AND j.is_anonymous = 0 AND j.first_name <> ''
				 ORDER BY j.id DESC
				 LIMIT %d",
				$page_id,
				max( 1, min( 100, $limit ) )
			),
			ARRAY_A
		);

		$feed = array();

		foreach ( (array) $rows as $row ) {
			$thing = self::thing_label( $options, (int) $row['thing_index'], (string) $row['custom_label'] );
			$parts = array_filter( array( (string) $row['first_name'], (string) $row['city'], $thing ) );

			if ( count( $parts ) < 2 ) {
				continue;
			}

			$feed[] = array( 'text' => implode( ' · ', $parts ) );
		}

		set_transient( $key, $feed, 15 );

		return $feed;
	}

	/**
	 * Owner details for a range of pieces, for the artwork and the wall.
	 *
	 * @param int                  $page_id Page ID.
	 * @param array<string, mixed> $options Resolved join options, for labels.
	 * @param int                  $from    First piece index.
	 * @param int                  $to      Last piece index.
	 * @return array<int, array<string, string>> Keyed by piece index.
	 */
	public static function pieces( int $page_id, array $options, int $from, int $to ): array {
		global $wpdb;

		if ( ! MSL_DB::ready() || $to < $from ) {
			return array();
		}

		$joins  = MSL_DB::joins_table();
		$things = MSL_DB::things_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT j.piece_index, j.first_name, j.city, j.is_anonymous, t.thing_index, t.custom_label
				 FROM {$joins} j
				 LEFT JOIN {$things} t ON t.join_id = j.id AND t.thing_index = (
					 SELECT MIN(thing_index) FROM {$things} WHERE join_id = j.id
				 )
				 WHERE j.page_id = %d AND j.piece_index BETWEEN %d AND %d
				 ORDER BY j.piece_index ASC
				 LIMIT 500",
				$page_id,
				$from,
				$to
			),
			ARRAY_A
		);

		$out = array();

		foreach ( (array) $rows as $row ) {
			$anonymous = 1 === (int) $row['is_anonymous'];

			$out[ (int) $row['piece_index'] ] = array(
				'name'  => $anonymous ? '' : (string) $row['first_name'],
				'place' => $anonymous ? '' : (string) $row['city'],
				'thing' => self::thing_label( $options, (int) $row['thing_index'], (string) $row['custom_label'] ),
			);
		}

		return $out;
	}

	/**
	 * Map points from real joins, aggregated by city.
	 *
	 * Anonymous rows are excluded and nothing finer than a city ever leaves the
	 * server, so a single participant can never be located from the map.
	 *
	 * @param int $page_id Page ID.
	 * @return array<int, array<string, float>>
	 */
	public static function map_points( int $page_id ): array {
		global $wpdb;

		if ( ! MSL_DB::ready() ) {
			return array();
		}

		$key    = 'msl_map_' . $page_id;
		$cached = get_transient( $key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$table = MSL_DB::joins_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ROUND(lat, 2) AS lat, ROUND(lng, 2) AS lng, COUNT(*) AS n
				 FROM {$table}
				 WHERE page_id = %d AND is_anonymous = 0 AND lat IS NOT NULL AND lng IS NOT NULL
				 GROUP BY ROUND(lat, 2), ROUND(lng, 2)
				 HAVING n > 0
				 ORDER BY n DESC
				 LIMIT 400",
				$page_id
			),
			ARRAY_A
		);

		$points = array();

		foreach ( (array) $rows as $row ) {
			$points[] = array(
				'lat'    => (float) $row['lat'],
				'lng'    => (float) $row['lng'],
				'weight' => min( 3.0, 0.9 + log( 1 + (int) $row['n'] ) * 0.6 ),
			);
		}

		set_transient( $key, $points, 5 * MINUTE_IN_SECONDS );

		return $points;
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	/**
	 * The display label for a chosen thing.
	 *
	 * @param array<string, mixed> $options      Resolved join section.
	 * @param int                  $index        Index into the option list.
	 * @param string               $custom_label Free text, when the option is "something else".
	 * @return string
	 */
	public static function thing_label( array $options, int $index, string $custom_label ): string {
		$rows = array_values( (array) ( $options['options'] ?? array() ) );
		$row  = $rows[ $index ] ?? null;

		if ( ! is_array( $row ) ) {
			return '';
		}

		if ( 1 === (int) ( $row['is_other'] ?? 0 ) && '' !== trim( $custom_label ) ) {
			return $custom_label;
		}

		return MSL_I18N::value( $row, 'label' );
	}

	/**
	 * A referral code no other row holds.
	 *
	 * @return string
	 */
	private static function generate_code(): string {
		global $wpdb;

		$table = MSL_DB::joins_table();

		for ( $attempt = 0; $attempt < 8; $attempt++ ) {
			$code = strtolower( substr( str_replace( array( '0', 'o', 'l', '1' ), '', bin2hex( random_bytes( 12 ) ) ), 0, 10 ) );
			$code = str_pad( $code, 10, (string) wp_rand( 2, 9 ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$taken = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE referral_code = %s", $code ) );

			if ( 0 === $taken ) {
				return $code;
			}
		}

		return substr( md5( uniqid( '', true ) ), 0, 10 );
	}

	/**
	 * Digits only, so the same number written two ways hashes the same.
	 *
	 * @param string $phone Raw phone.
	 * @return string
	 */
	private static function normalise_phone( string $phone ): string {
		$digits = preg_replace( '/\D+/', '', $phone );
		$digits = is_string( $digits ) ? $digits : '';

		// Israeli numbers are given both as 05… and as +9725…; fold them together.
		if ( str_starts_with( $digits, '972' ) ) {
			$digits = '0' . substr( $digits, 3 );
		}

		return $digits;
	}

	/**
	 * Reversibly protect a reminder destination before storage.
	 *
	 * Reminder contacts have to be readable again to send the reminder, so this
	 * is encryption rather than a hash. Without sodium the value is not stored
	 * at all — silently keeping it in the clear would be worse than losing the
	 * reminder.
	 *
	 * The ciphertext is base64 before it goes anywhere near the database.
	 * Writing the raw bytes through a string placeholder puts arbitrary binary
	 * inside a SQL string literal, which one driver rejected outright and which
	 * any charset conversion would silently corrupt — and a reminder address
	 * that cannot be decrypted again is just a liability sitting in a table.
	 *
	 * @param string $value Raw destination.
	 * @return string|null
	 */
	private static function protect( string $value ): ?string {
		if ( ! function_exists( 'sodium_crypto_secretbox' ) ) {
			return null;
		}

		$key   = substr( hash( 'sha256', MSL_DB::salt() . '|reminder', true ), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

		return base64_encode( $nonce . sodium_crypto_secretbox( $value, $nonce, $key ) );
	}

	/**
	 * Read back a protected reminder destination.
	 *
	 * @param string $stored Stored value, as written by protect().
	 * @return string Empty when the value cannot be read.
	 */
	public static function unprotect( string $stored ): string {
		if ( ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
			return '';
		}

		$raw = base64_decode( $stored, true );

		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}

		$key   = substr( hash( 'sha256', MSL_DB::salt() . '|reminder', true ), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES );
		$nonce = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $nonce, $key );

		return is_string( $plain ) ? $plain : '';
	}

	/**
	 * Coordinates for a city, from the points already configured on the page.
	 *
	 * Deliberately not a geocoder call: a network round trip inside a form
	 * submission is the slowest thing on the page, and the brief only ever needs
	 * city granularity. A city nobody has configured simply has no point on the
	 * map — it still counts in every total.
	 *
	 * @param string $city    Submitted city.
	 * @param string $country Submitted country.
	 * @return array{0: float|null, 1: float|null}
	 */
	private static function locate( string $city, string $country ): array {
		$page_id = MSL_Importer::page_id();

		if ( 0 === $page_id || '' === trim( $city ) ) {
			return array( null, null );
		}

		$needle = self::fold( $city );

		foreach ( (array) ( MSL_Meta::get( 'map', $page_id )['points'] ?? array() ) as $point ) {
			if ( ! is_array( $point ) ) {
				continue;
			}

			if ( self::fold( (string) ( $point['name'] ?? '' ) ) === $needle ) {
				return array( (float) ( $point['lat'] ?? 0 ), (float) ( $point['lng'] ?? 0 ) );
			}
		}

		unset( $country );

		return array( null, null );
	}

	/**
	 * Normalise a place name for comparison.
	 *
	 * @param string $value Raw name.
	 * @return string
	 */
	private static function fold( string $value ): string {
		$value = mb_strtolower( trim( $value ) );

		return (string) preg_replace( '/[\s\'"׳״\-]+/u', '', $value );
	}
}
