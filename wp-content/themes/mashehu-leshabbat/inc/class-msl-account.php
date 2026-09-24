<?php
/**
 * Accounts with an address and a password.
 *
 * The campaign already had an identity layer — the `msl_people` table, with a
 * referral code on each row and group ownership pointing at it — but the only
 * way into it was Google, which needs credentials the campaign does not have
 * yet and which some of the people this is for do not use. This is the second
 * door into the same room: same table, same session, same referral code, same
 * ownership. A password account is a row whose `provider` is 'password', and
 * whose `provider_id` is the hash of the address, so the unique index that
 * already stopped two Google accounts sharing an identity stops two accounts
 * sharing an address without a second index.
 *
 * Deliberately **not** `wp_users`. A participant is not a person who can log
 * into WordPress: giving each one a subscriber account would put a quarter of
 * a million rows beside the two or three people who actually edit the site,
 * hand every one of them a wp-admin session, and make "delete a participant"
 * mean something different from what it says.
 *
 * What the password gets stored as is WordPress's own hash — `wp_hash_password()`
 * is bcrypt on modern WordPress and phpass before it, and it is rehashed on
 * sign-in when the format moves on. Nothing here invents any cryptography.
 *
 * The address is kept twice: as a salted hash, which is what lookups and the
 * unique index use, and encrypted, which is what a password-reset mail needs.
 * The plaintext never leaves this class.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Registration, sign-in and password recovery.
 */
final class MSL_Account {

	/**
	 * The provider name a password account carries.
	 */
	public const PROVIDER = 'password';

	/**
	 * The shortest password accepted.
	 *
	 * Eight, and no composition rules. A rule that demands a capital and a
	 * digit buys very little and costs a great deal — it is the reason people
	 * write the password on the inside of a drawer — and length is the part
	 * that actually matters.
	 */
	public const MIN_PASSWORD = 8;

	/**
	 * How long a reset link is good for.
	 */
	private const RESET_TTL = 2 * HOUR_IN_SECONDS;

	/**
	 * Failed sign-ins allowed, per address and per IP, in the window.
	 */
	private const TRIES      = 8;
	private const TRY_WINDOW = 15 * MINUTE_IN_SECONDS;

	/**
	 * Hook the form handlers.
	 *
	 * All of them are plain POSTs to admin-post.php. The personal area has to
	 * work on a borrowed phone with a bad connection, and a sign-in form that
	 * does nothing when a script fails to load is the wrong thing to hand
	 * somebody who is trying to get back into their own group.
	 */
	public static function init(): void {
		foreach ( array( 'register', 'login', 'forgot', 'reset', 'password' ) as $action ) {
			add_action( 'admin_post_nopriv_msl_account_' . $action, array( self::class, 'handle_' . $action ) );
			add_action( 'admin_post_msl_account_' . $action, array( self::class, 'handle_' . $action ) );
		}
	}

	/* ---------------------------------------------------------------------
	 * Reading
	 * ------------------------------------------------------------------ */

	/**
	 * The password account for an address, or null.
	 *
	 * @param string $email Raw address.
	 * @return array<string, mixed>|null
	 */
	public static function by_email( string $email ): ?array {
		global $wpdb;

		$hash = MSL_DB::hash( $email );

		if ( '' === $hash || ! MSL_DB::ready() ) {
			return null;
		}

		$table = MSL_DB::people_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE provider = %s AND provider_id = %s", self::PROVIDER, $hash ),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Whether the signed-in person can be asked for a password at all.
	 *
	 * Somebody who came in through Google has no password to change, and
	 * offering them the form would be offering them a way to lock themselves
	 * out of an account they get into another way.
	 *
	 * @param array<string, mixed>|null $person Signed-in person.
	 * @return bool
	 */
	public static function has_password( ?array $person ): bool {
		return null !== $person && '' !== (string) ( $person['password_hash'] ?? '' );
	}

	/* ---------------------------------------------------------------------
	 * Writing
	 * ------------------------------------------------------------------ */

	/**
	 * Open an account.
	 *
	 * @param string $email    Raw address.
	 * @param string $password Raw password.
	 * @param string $name     Display name.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function register( string $email, string $password, string $name ): array|WP_Error {
		global $wpdb;

		if ( ! MSL_DB::ready() ) {
			MSL_DB::install();
		}

		$email = sanitize_email( trim( $email ) );

		if ( '' === $email || ! is_email( $email ) ) {
			return new WP_Error( 'msl_account', 'email' );
		}

		if ( mb_strlen( $password ) < self::MIN_PASSWORD ) {
			return new WP_Error( 'msl_account', 'short' );
		}

		if ( null !== self::by_email( $email ) ) {
			return new WP_Error( 'msl_account', 'taken' );
		}

		$hash = MSL_DB::hash( $email );

		/*
		 * A person who joined on this device before ever opening an account
		 * already has a referral code, and it is already in circulation. Taking
		 * it over is what makes registering feel like claiming the account they
		 * were already using rather than starting a second one beside it.
		 */
		$adopted = MSL_Auth::adoptable_code();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->insert(
			MSL_DB::people_table(),
			array(
				'uuid'          => wp_generate_uuid4(),
				'provider'      => self::PROVIDER,
				'provider_id'   => $hash,
				'display_name'  => trim( mb_substr( sanitize_text_field( $name ), 0, 120 ) ),
				'email_hash'    => $hash,
				'email'         => MSL_Joins::protect( $email ),
				'password_hash' => wp_hash_password( $password ),
				'session_key'   => bin2hex( random_bytes( 16 ) ),
				'referral_code' => '' !== $adopted ? $adopted : MSL_Joins::generate_code(),
				'lang'          => MSL_I18N::lang(),
				'created_at'    => current_time( 'mysql', true ),
				'last_login_at' => current_time( 'mysql', true ),
			)
		);

		if ( ! $inserted ) {
			return new WP_Error( 'msl_account', 'generic' );
		}

		$id = (int) $wpdb->insert_id;

		self::claim_groups( $id, $hash );
		MSL_Auth::sign_in( $id );

		return array( 'id' => $id );
	}

	/**
	 * Sign in with an address and a password.
	 *
	 * @param string $email    Raw address.
	 * @param string $password Raw password.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function login( string $email, string $password ): array|WP_Error {
		global $wpdb;

		$email = sanitize_email( trim( $email ) );

		if ( ! self::within_try_limit( $email ) ) {
			return new WP_Error( 'msl_account', 'rate' );
		}

		$person = '' !== $email ? self::by_email( $email ) : null;

		/*
		 * One message for "no such address" and for "wrong password", and the
		 * hashing runs either way. Two different answers turn this form into a
		 * way of asking whether a given person has an account here, which on a
		 * site about religious observance is not a harmless thing to answer.
		 */
		$stored = null !== $person ? (string) $person['password_hash'] : '';
		$ok     = '' !== $stored
			? wp_check_password( $password, $stored, (int) $person['id'] )
			: (bool) wp_check_password( $password, '$P$Bnothingmatchesthisplaceholderhash000', 0 );

		if ( null === $person || ! $ok ) {
			self::count_try( $email );

			return new WP_Error( 'msl_account', 'credentials' );
		}

		self::clear_tries( $email );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			MSL_DB::people_table(),
			array( 'last_login_at' => current_time( 'mysql', true ) ),
			array( 'id' => (int) $person['id'] ),
			array( '%s' ),
			array( '%d' )
		);

		self::claim_groups( (int) $person['id'], (string) $person['email_hash'] );
		MSL_Auth::sign_in( (int) $person['id'] );

		return $person;
	}

	/**
	 * Change a password for somebody who is already signed in.
	 *
	 * @param array<string, mixed> $person  Signed-in person.
	 * @param string               $current Their current password.
	 * @param string               $next    The new one.
	 * @return true|WP_Error
	 */
	public static function change_password( array $person, string $current, string $next ): bool|WP_Error {
		global $wpdb;

		if ( ! self::has_password( $person ) ) {
			return new WP_Error( 'msl_account', 'generic' );
		}

		if ( ! wp_check_password( $current, (string) $person['password_hash'], (int) $person['id'] ) ) {
			return new WP_Error( 'msl_account', 'credentials' );
		}

		if ( mb_strlen( $next ) < self::MIN_PASSWORD ) {
			return new WP_Error( 'msl_account', 'short' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			MSL_DB::people_table(),
			array(
				'password_hash' => wp_hash_password( $next ),
				'session_key'   => bin2hex( random_bytes( 16 ) ),
			),
			array( 'id' => (int) $person['id'] ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		// Every other browser is signed out; this one is signed back in, or the
		// person would be thrown out by their own password change.
		MSL_Auth::sign_in( (int) $person['id'] );

		return true;
	}

	/* ---------------------------------------------------------------------
	 * Forgotten passwords
	 * ------------------------------------------------------------------ */

	/**
	 * Send a reset link, if there is an account to send it to.
	 *
	 * Always reports success to the caller. Whether an address has an account
	 * here is not a question this form answers — see login().
	 *
	 * @param string               $email Raw address.
	 * @param array<string, mixed> $copy  Resolved account copy, for the mail.
	 */
	public static function request_reset( string $email, array $copy ): void {
		global $wpdb;

		$email  = sanitize_email( trim( $email ) );
		$person = '' !== $email ? self::by_email( $email ) : null;

		if ( null === $person ) {
			return;
		}

		$token = bin2hex( random_bytes( 24 ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			MSL_DB::people_table(),
			array(
				'reset_hash'    => hash( 'sha256', $token ),
				'reset_expires' => gmdate( 'Y-m-d H:i:s', time() + self::RESET_TTL ),
			),
			array( 'id' => (int) $person['id'] ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		$link = add_query_arg(
			array(
				'msl_reset' => $token,
				'lang'      => MSL_I18N::lang(),
			),
			self::page_url()
		);

		wp_mail(
			$email,
			wp_specialchars_decode( msl_t( $copy, 'mail_subject' ), ENT_QUOTES ),
			sprintf( msl_t( $copy, 'mail_body' ), $link )
		);
	}

	/**
	 * Finish a reset: set the new password and sign the person in.
	 *
	 * @param string $token    Raw token from the link.
	 * @param string $password The new password.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function reset( string $token, string $password ): array|WP_Error {
		global $wpdb;

		if ( '' === $token || ! MSL_DB::ready() ) {
			return new WP_Error( 'msl_account', 'token' );
		}

		if ( mb_strlen( $password ) < self::MIN_PASSWORD ) {
			return new WP_Error( 'msl_account', 'short' );
		}

		$table = MSL_DB::people_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$person = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE reset_hash = %s", hash( 'sha256', $token ) ),
			ARRAY_A
		);

		if ( ! is_array( $person ) || strtotime( (string) $person['reset_expires'] . ' UTC' ) < time() ) {
			return new WP_Error( 'msl_account', 'token' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$table,
			array(
				'password_hash' => wp_hash_password( $password ),
				'session_key'   => bin2hex( random_bytes( 16 ) ),
				'reset_hash'    => '',
				'reset_expires' => null,
			),
			array( 'id' => (int) $person['id'] ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		MSL_Auth::sign_in( (int) $person['id'] );

		return $person;
	}

	/* ---------------------------------------------------------------------
	 * The forms
	 *
	 * Every one of them is the same shape: check the nonce, read the fields,
	 * call the method above, and bounce back to the personal area carrying
	 * either an error key or nothing. The key is turned into a sentence by the
	 * page, out of the page's own copy, so every message a visitor sees is one
	 * the campaign can edit and is in the language they are reading.
	 * ------------------------------------------------------------------ */

	/**
	 * Open an account.
	 */
	public static function handle_register(): void {
		check_admin_referer( 'msl_account' );

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- each value is sanitised by the method it is handed to.
		$result = self::register(
			(string) wp_unslash( $_POST['email'] ?? '' ),
			(string) wp_unslash( $_POST['password'] ?? '' ),
			(string) wp_unslash( $_POST['name'] ?? '' )
		);
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput

		self::bounce( $result, 'register' );
	}

	/**
	 * Sign in.
	 */
	public static function handle_login(): void {
		check_admin_referer( 'msl_account' );

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- sanitised downstream.
		$result = self::login(
			(string) wp_unslash( $_POST['email'] ?? '' ),
			(string) wp_unslash( $_POST['password'] ?? '' )
		);
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput

		self::bounce( $result, 'login' );
	}

	/**
	 * Ask for a reset link.
	 */
	public static function handle_forgot(): void {
		check_admin_referer( 'msl_account' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitised downstream.
		$email = (string) wp_unslash( $_POST['email'] ?? '' );

		self::request_reset( $email, MSL_Meta::get( 'account', MSL_Importer::page_id( 'account' ) ) );

		// Always the same answer, sent or not: see request_reset().
		wp_safe_redirect( add_query_arg( 'msl_sent', '1', self::page_url() ) );
		exit;
	}

	/**
	 * Set a new password from a reset link.
	 */
	public static function handle_reset(): void {
		check_admin_referer( 'msl_account' );

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- sanitised downstream.
		$result = self::reset(
			sanitize_text_field( (string) wp_unslash( $_POST['token'] ?? '' ) ),
			(string) wp_unslash( $_POST['password'] ?? '' )
		);
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'msl_reset' => sanitize_text_field( (string) wp_unslash( $_POST['token'] ?? '' ) ),
						'msl_error' => $result->get_error_message(),
					),
					self::page_url()
				)
			);
			exit;
		}

		wp_safe_redirect( self::page_url() );
		exit;
	}

	/**
	 * Change a password from inside the personal area.
	 */
	public static function handle_password(): void {
		check_admin_referer( 'msl_account' );

		$person = MSL_Auth::current();

		if ( null === $person ) {
			wp_safe_redirect( self::page_url() );
			exit;
		}

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- sanitised downstream.
		$result = self::change_password(
			$person,
			(string) wp_unslash( $_POST['current'] ?? '' ),
			(string) wp_unslash( $_POST['password'] ?? '' )
		);
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput

		if ( is_wp_error( $result ) ) {
			self::bounce( $result, 'password' );
		}

		wp_safe_redirect( add_query_arg( 'msl_saved', '1', self::page_url( 'msl-account-password' ) ) );
		exit;
	}

	/**
	 * Back to the personal area, carrying the outcome in the address.
	 *
	 * @param array<string, mixed>|bool|WP_Error $result Whatever the method returned.
	 * @param string                             $form   Which form was submitted.
	 */
	private static function bounce( array|bool|WP_Error $result, string $form ): void {
		$args = array();

		if ( is_wp_error( $result ) ) {
			$args = array(
				'msl_error' => $result->get_error_message(),
				'msl_form'  => $form,
			);
		}

		wp_safe_redirect( add_query_arg( $args, self::page_url() ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Internals
	 * ------------------------------------------------------------------ */

	/**
	 * Attach any group opened with this address to this account.
	 *
	 * Somebody opens a group from a link in a message, giving their address on
	 * the form, and opens an account a week later when they want to see how it
	 * is doing. The group is theirs; the only thing that was missing is a row
	 * saying so. Matching on the stored hash of the address is exactly as
	 * strong as the address itself, which is what the private link in their
	 * inbox already proves.
	 *
	 * @param int    $person_id Person row id.
	 * @param string $hash      Hashed address.
	 */
	private static function claim_groups( int $person_id, string $hash ): void {
		global $wpdb;

		if ( '' === $hash || ! MSL_DB::ready() ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			MSL_DB::groups_table(),
			array( 'person_id' => $person_id ),
			array(
				'owner_email_hash' => $hash,
				'person_id'        => 0,
			),
			array( '%d' ),
			array( '%s', '%d' )
		);
	}

	/**
	 * Whether this address and this browser may try again.
	 *
	 * Counted per address and per IP together: per address alone lets one
	 * machine work through a list of addresses, and per IP alone lets a
	 * household share a limit they did not spend.
	 *
	 * @param string $email Raw address.
	 * @return bool
	 */
	private static function within_try_limit( string $email ): bool {
		foreach ( self::try_keys( $email ) as $key ) {
			if ( (int) get_transient( $key ) >= self::TRIES ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Record a failed attempt.
	 *
	 * @param string $email Raw address.
	 */
	private static function count_try( string $email ): void {
		foreach ( self::try_keys( $email ) as $key ) {
			set_transient( $key, (int) get_transient( $key ) + 1, self::TRY_WINDOW );
		}
	}

	/**
	 * Forget the failures after a sign-in that worked.
	 *
	 * @param string $email Raw address.
	 */
	private static function clear_tries( string $email ): void {
		foreach ( self::try_keys( $email ) as $key ) {
			delete_transient( $key );
		}
	}

	/**
	 * The two counters an attempt spends.
	 *
	 * @param string $email Raw address.
	 * @return array<int, string>
	 */
	private static function try_keys( string $email ): array {
		return array(
			'msl_try_a_' . substr( MSL_DB::hash( $email ), 0, 32 ),
			'msl_try_i_' . substr( MSL_Joins::client_ip_hash(), 0, 32 ),
		);
	}

	/**
	 * Whether there is a personal area to send anybody to.
	 *
	 * An install that never imported the page has none, and a header link into
	 * a 404 is worse than no header link.
	 *
	 * @return bool
	 */
	public static function available(): bool {
		return MSL_Importer::page_id( 'account' ) > 0;
	}

	/**
	 * The personal area's address.
	 *
	 * @param string $anchor Fragment, without the hash.
	 * @return string
	 */
	public static function page_url( string $anchor = '' ): string {
		$page = MSL_Importer::page_id( 'account' );
		$url  = $page > 0 ? (string) get_permalink( $page ) : home_url( '/' );

		return '' !== $anchor ? $url . '#' . $anchor : $url;
	}
}
