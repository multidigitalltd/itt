<?php
/**
 * Signing in with Google.
 *
 * This is the one place the site talks to a domain that is not its own, and it
 * is a deliberate exception rather than an oversight: there is no way to offer
 * Google sign-in without Google. It is kept as small as that exception can be —
 * the authorization-code flow, run entirely server to server with
 * wp_remote_post(). No Google JavaScript SDK is loaded, no script from
 * accounts.google.com reaches the visitor, and the button is our own markup. A
 * visitor who never signs in never makes a request to Google at all.
 *
 * The identity lives in the theme's own table and not in wp_users. See
 * MSL_DB::people_table() for why.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Google sign-in and the participant session.
 */
final class MSL_Auth {

	/**
	 * Google's endpoints.
	 */
	private const AUTH_URL     = 'https://accounts.google.com/o/oauth2/v2/auth';
	private const TOKEN_URL    = 'https://oauth2.googleapis.com/token';
	private const USERINFO_URL = 'https://openidconnect.googleapis.com/v1/userinfo';

	/**
	 * The session cookie, and how long a sign-in lasts.
	 */
	public const COOKIE = 'msl_session';
	private const LIFETIME = 60 * DAY_IN_SECONDS;

	/**
	 * Cookie holding the anti-forgery state while the visitor is at Google.
	 */
	private const STATE_COOKIE = 'msl_oauth_state';

	/**
	 * Per-request cache of the signed-in person.
	 *
	 * @var array<string, mixed>|null|false
	 */
	private static array|null|false $person = false;

	/**
	 * Hook the flow.
	 */
	public static function init(): void {
		add_action( 'admin_post_nopriv_msl_google', array( self::class, 'start' ) );
		add_action( 'admin_post_msl_google', array( self::class, 'start' ) );
		add_action( 'admin_post_nopriv_msl_google_callback', array( self::class, 'callback' ) );
		add_action( 'admin_post_msl_google_callback', array( self::class, 'callback' ) );
		add_action( 'admin_post_nopriv_msl_signout', array( self::class, 'sign_out' ) );
		add_action( 'admin_post_msl_signout', array( self::class, 'sign_out' ) );
	}

	/* ---------------------------------------------------------------------
	 * Configuration
	 * ------------------------------------------------------------------ */

	/**
	 * The OAuth client id.
	 *
	 * A constant in wp-config.php wins over the panel field. Credentials belong
	 * outside the database when the site can manage it: a database dump then
	 * carries no way to impersonate the site to Google.
	 *
	 * @return string
	 */
	public static function client_id(): string {
		if ( defined( 'MSL_GOOGLE_CLIENT_ID' ) && '' !== (string) MSL_GOOGLE_CLIENT_ID ) {
			return (string) MSL_GOOGLE_CLIENT_ID;
		}

		return trim( (string) ( MSL_Meta::get( 'auth', MSL_Importer::page_id() )['google_client_id'] ?? '' ) );
	}

	/**
	 * The OAuth client secret.
	 *
	 * @return string
	 */
	public static function client_secret(): string {
		if ( defined( 'MSL_GOOGLE_CLIENT_SECRET' ) && '' !== (string) MSL_GOOGLE_CLIENT_SECRET ) {
			return (string) MSL_GOOGLE_CLIENT_SECRET;
		}

		return trim( (string) ( MSL_Meta::get( 'auth', MSL_Importer::page_id() )['google_client_secret'] ?? '' ) );
	}

	/**
	 * Whether sign-in is switched on and actually configured.
	 *
	 * Both halves matter. A button that leads to a Google error page is worse
	 * than no button, so an unconfigured client hides it.
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		$auth = MSL_Meta::get( 'auth', MSL_Importer::page_id() );

		return 1 === (int) ( $auth['login_enabled'] ?? 0 )
			&& '' !== self::client_id()
			&& '' !== self::client_secret();
	}

	/**
	 * Where Google sends the visitor back.
	 *
	 * This exact string has to be listed as an authorised redirect URI on the
	 * OAuth client, character for character.
	 *
	 * @return string
	 */
	public static function redirect_uri(): string {
		return admin_url( 'admin-post.php?action=msl_google_callback' );
	}

	/**
	 * The address of the sign-in button.
	 *
	 * @param string $back Where to return the visitor afterwards.
	 * @return string
	 */
	public static function sign_in_url( string $back = '' ): string {
		return add_query_arg(
			array(
				'action' => 'msl_google',
				'back'   => rawurlencode( '' !== $back ? $back : self::current_url() ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * The address of the sign-out link.
	 *
	 * @return string
	 */
	public static function sign_out_url(): string {
		return add_query_arg(
			array(
				'action'   => 'msl_signout',
				'back'     => rawurlencode( self::current_url() ),
				'_wpnonce' => wp_create_nonce( 'msl_signout' ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * The page being viewed, for returning to after the round trip.
	 *
	 * @return string
	 */
	private static function current_url(): string {
		$page = MSL_Importer::page_id();

		return 0 !== $page ? (string) get_permalink( $page ) : home_url( '/' );
	}

	/* ---------------------------------------------------------------------
	 * The flow
	 * ------------------------------------------------------------------ */

	/**
	 * Step one: send the visitor to Google.
	 */
	public static function start(): void {
		if ( ! self::enabled() ) {
			self::bounce( self::safe_back(), 'off' );
		}

		$state = wp_generate_password( 32, false );

		// The state is held in a cookie rather than a transient so that a
		// page cache, a load balancer or a flushed object cache cannot lose it
		// between the two halves of the round trip.
		setcookie(
			self::STATE_COOKIE,
			$state . '|' . rawurlencode( self::safe_back() ),
			array(
				'expires'  => time() + 15 * MINUTE_IN_SECONDS,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);

		wp_redirect(
			add_query_arg(
				array(
					'client_id'     => rawurlencode( self::client_id() ),
					'redirect_uri'  => rawurlencode( self::redirect_uri() ),
					'response_type' => 'code',
					'scope'         => rawurlencode( 'openid email profile' ),
					'state'         => rawurlencode( $state ),
					// The visitor picks an account every time rather than being
					// silently signed in as whoever the browser last used.
					'prompt'        => 'select_account',
				),
				self::AUTH_URL
			)
		);

		exit;
	}

	/**
	 * Step two: Google sends the visitor back with a code.
	 */
	public static function callback(): void {
		$cookie = isset( $_COOKIE[ self::STATE_COOKIE ] )
			? sanitize_text_field( wp_unslash( (string) $_COOKIE[ self::STATE_COOKIE ] ) )
			: '';

		self::clear_state_cookie();

		$parts    = explode( '|', $cookie, 2 );
		$expected = $parts[0] ?? '';
		$back     = isset( $parts[1] ) ? rawurldecode( $parts[1] ) : self::current_url();
		$back     = self::safe_url( $back );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the OAuth state parameter is the anti-forgery token here.
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['state'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['code'] ) ) : '';

		if ( '' === $expected || '' === $state || ! hash_equals( $expected, $state ) ) {
			self::bounce( $back, 'state' );
		}

		if ( '' === $code ) {
			// The visitor pressed cancel on Google's screen, which is not an
			// error worth shouting about.
			self::bounce( $back, 'cancelled' );
		}

		$profile = self::exchange( $code );

		if ( null === $profile ) {
			self::bounce( $back, 'failed' );
		}

		$person = self::upsert( $profile );

		if ( null === $person ) {
			self::bounce( $back, 'failed' );
		}

		self::sign_in( (int) $person['id'] );
		self::bounce( $back, '' );
	}

	/**
	 * Trade the code for a profile.
	 *
	 * The profile is read from Google's userinfo endpoint over the token rather
	 * than by decoding the id_token locally: verifying a signed JWT properly
	 * means fetching and caching Google's signing keys and implementing RS256
	 * verification, and a server-to-server call over TLS to the issuer answers
	 * the same question with none of that.
	 *
	 * @param string $code Authorization code.
	 * @return array<string, string>|null
	 */
	private static function exchange( string $code ): ?array {
		$token = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 15,
				'body'    => array(
					'code'          => $code,
					'client_id'     => self::client_id(),
					'client_secret' => self::client_secret(),
					'redirect_uri'  => self::redirect_uri(),
					'grant_type'    => 'authorization_code',
				),
			)
		);

		if ( is_wp_error( $token ) || 200 !== wp_remote_retrieve_response_code( $token ) ) {
			return null;
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $token ), true );
		$access  = is_array( $decoded ) ? (string) ( $decoded['access_token'] ?? '' ) : '';

		if ( '' === $access ) {
			return null;
		}

		$info = wp_remote_get(
			self::USERINFO_URL,
			array(
				'timeout' => 15,
				'headers' => array( 'Authorization' => 'Bearer ' . $access ),
			)
		);

		if ( is_wp_error( $info ) || 200 !== wp_remote_retrieve_response_code( $info ) ) {
			return null;
		}

		$profile = json_decode( (string) wp_remote_retrieve_body( $info ), true );

		if ( ! is_array( $profile ) || '' === (string) ( $profile['sub'] ?? '' ) ) {
			return null;
		}

		return array(
			'sub'     => (string) $profile['sub'],
			'email'   => (string) ( $profile['email'] ?? '' ),
			'name'    => (string) ( $profile['given_name'] ?? $profile['name'] ?? '' ),
			'picture' => (string) ( $profile['picture'] ?? '' ),
		);
	}

	/* ---------------------------------------------------------------------
	 * People
	 * ------------------------------------------------------------------ */

	/**
	 * Find the person behind a Google profile, creating them on first sign-in.
	 *
	 * A returning person keeps the referral code they already had — the link is
	 * the thing they have been sending to other people, and it has to survive
	 * signing out and back in, or every sign-in would quietly orphan the
	 * invitations already in circulation.
	 *
	 * @param array<string, string> $profile Normalised Google profile.
	 * @return array<string, mixed>|null
	 */
	private static function upsert( array $profile ): ?array {
		global $wpdb;

		if ( ! MSL_DB::ready() ) {
			MSL_DB::install();
		}

		$table = MSL_DB::people_table();
		$now   = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$existing = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE provider = %s AND provider_id = %s", 'google', $profile['sub'] ),
			ARRAY_A
		);

		if ( is_array( $existing ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$table,
				array(
					'display_name'  => mb_substr( $profile['name'], 0, 120 ),
					'avatar_url'    => esc_url_raw( $profile['picture'] ),
					'last_login_at' => $now,
				),
				array( 'id' => (int) $existing['id'] )
			);

			return $existing;
		}

		/*
		 * A person who joined on this device before ever signing in already has
		 * a code, and it is already in circulation. Adopting it is what makes
		 * signing in feel like claiming the account they were already using
		 * rather than starting a second one beside it.
		 */
		$adopted = self::adoptable_code();
		$code    = '' !== $adopted ? $adopted : MSL_Joins::generate_code();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->insert(
			$table,
			array(
				'uuid'          => wp_generate_uuid4(),
				'provider'      => 'google',
				'provider_id'   => $profile['sub'],
				'display_name'  => mb_substr( $profile['name'], 0, 120 ),
				'email_hash'    => '' !== $profile['email'] ? MSL_DB::hash( strtolower( $profile['email'] ) ) : '',
				'avatar_url'    => esc_url_raw( $profile['picture'] ),
				'referral_code' => $code,
				'lang'          => MSL_I18N::lang(),
				'created_at'    => $now,
				'last_login_at' => $now,
			)
		);

		if ( ! $inserted ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $wpdb->insert_id ), ARRAY_A );
	}

	/**
	 * A referral code this browser already owns and that no person holds yet.
	 *
	 * @return string
	 */
	public static function adoptable_code(): string {
		global $wpdb;

		$mine = isset( $_COOKIE[ MSL_Joins::MINE_COOKIE ] )
			? sanitize_key( wp_unslash( (string) $_COOKIE[ MSL_Joins::MINE_COOKIE ] ) )
			: '';

		if ( ! MSL_Joins::is_code( $mine ) ) {
			return '';
		}

		$table = MSL_DB::people_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$taken = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE referral_code = %s", $mine ) );

		return 0 === $taken ? $mine : '';
	}

	/* ---------------------------------------------------------------------
	 * The session
	 * ------------------------------------------------------------------ */

	/**
	 * Sign a person in on this browser.
	 *
	 * The cookie carries the person's id, an expiry and a signature over both,
	 * so it can be verified without a session table and cannot be edited into
	 * somebody else's id.
	 *
	 * The signature also covers a secret stored on the person's own row. That
	 * is what makes "change my password" mean something: without it a cookie
	 * that leaked stays valid for its full sixty days no matter what the person
	 * does about it afterwards, and there is no session table to delete from.
	 * Rotating the row's key invalidates every cookie ever issued for them.
	 *
	 * @param int $person_id Person row id.
	 */
	public static function sign_in( int $person_id ): void {
		$expires = time() + self::LIFETIME;
		$value   = $person_id . '|' . $expires . '|' . self::signature( $person_id, $expires, self::session_key( $person_id ) );

		setcookie(
			self::COOKIE,
			$value,
			array(
				'expires'  => $expires,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);

		$_COOKIE[ self::COOKIE ] = $value;
		self::$person            = false;
	}

	/**
	 * The signature over a session.
	 *
	 * @param int    $person_id Person row id.
	 * @param int    $expires   Expiry timestamp.
	 * @param string $key       The person's own session key.
	 * @return string
	 */
	private static function signature( int $person_id, int $expires, string $key ): string {
		return hash_hmac( 'sha256', $person_id . '|' . $expires . '|' . $key, MSL_DB::salt() . '|session' );
	}

	/**
	 * The person's session key, minted on first use.
	 *
	 * @param int $person_id Person row id.
	 * @return string
	 */
	private static function session_key( int $person_id ): string {
		global $wpdb;

		$table = MSL_DB::people_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$key = (string) $wpdb->get_var( $wpdb->prepare( "SELECT session_key FROM {$table} WHERE id = %d", $person_id ) );

		if ( '' !== $key ) {
			return $key;
		}

		$key = bin2hex( random_bytes( 16 ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( $table, array( 'session_key' => $key ), array( 'id' => $person_id ), array( '%s' ), array( '%d' ) );

		return $key;
	}

	/**
	 * Rotate a person's session key, ending every session they have open.
	 *
	 * @param int $person_id Person row id.
	 */
	public static function end_all_sessions( int $person_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			MSL_DB::people_table(),
			array( 'session_key' => bin2hex( random_bytes( 16 ) ) ),
			array( 'id' => $person_id ),
			array( '%s' ),
			array( '%d' )
		);

		self::$person = false;
	}

	/**
	 * End the session.
	 */
	public static function sign_out(): void {
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ) : '';

		if ( '' !== $nonce && wp_verify_nonce( $nonce, 'msl_signout' ) ) {
			setcookie(
				self::COOKIE,
				'',
				array(
					'expires'  => time() - DAY_IN_SECONDS,
					'path'     => '/',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);

			unset( $_COOKIE[ self::COOKIE ] );
			self::$person = false;
		}

		self::bounce( self::safe_back(), '' );
	}

	/**
	 * The person signed in on this request, if any.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function current(): ?array {
		if ( false !== self::$person ) {
			return self::$person;
		}

		self::$person = null;

		$raw = isset( $_COOKIE[ self::COOKIE ] )
			? sanitize_text_field( wp_unslash( (string) $_COOKIE[ self::COOKIE ] ) )
			: '';

		$parts = explode( '|', $raw );

		if ( 3 !== count( $parts ) ) {
			return null;
		}

		$id      = (int) $parts[0];
		$expires = (int) $parts[1];

		if ( $id < 1 || $expires < time() || ! MSL_DB::ready() ) {
			return null;
		}

		global $wpdb;
		$table = MSL_DB::people_table();

		/*
		 * The row is read before the signature is checked, because the key the
		 * signature is made with lives on it. A row that does not exist and a
		 * signature that does not match end the same way, so the reordering
		 * tells an attacker nothing it did not already know; what it costs is
		 * one query on a forged cookie, and what it buys is a session that a
		 * password change can actually end.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		if ( ! is_array( $row ) ) {
			return null;
		}

		if ( ! hash_equals( self::signature( $id, $expires, (string) $row['session_key'] ), (string) $parts[2] ) ) {
			return null;
		}

		self::$person = $row;

		return self::$person;
	}

	/**
	 * The signed-in person's personal invite link, or an empty string.
	 *
	 * @return string
	 */
	public static function personal_link(): string {
		$person = self::current();

		return null === $person ? '' : MSL_Joins::share_url( (string) $person['referral_code'] );
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	/**
	 * The requested return address, restricted to this site.
	 *
	 * @return string
	 */
	private static function safe_back(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$back = isset( $_GET['back'] ) ? rawurldecode( sanitize_text_field( wp_unslash( (string) $_GET['back'] ) ) ) : '';

		return self::safe_url( $back );
	}

	/**
	 * Keep a redirect on this site.
	 *
	 * @param string $url Candidate.
	 * @return string
	 */
	private static function safe_url( string $url ): string {
		$safe = wp_validate_redirect( $url, '' );

		return '' !== $safe ? $safe : self::current_url();
	}

	/**
	 * Send the visitor back, optionally carrying a reason the sign-in failed.
	 *
	 * @param string $back   Return address.
	 * @param string $reason Empty on success.
	 */
	private static function bounce( string $back, string $reason ): void {
		wp_safe_redirect( '' === $reason ? $back : add_query_arg( 'msl_auth', $reason, $back ) );

		exit;
	}

	/**
	 * Forget the anti-forgery cookie.
	 */
	private static function clear_state_cookie(): void {
		setcookie(
			self::STATE_COOKIE,
			'',
			array(
				'expires'  => time() - DAY_IN_SECONDS,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}
}
