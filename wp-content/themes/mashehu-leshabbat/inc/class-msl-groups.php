<?php
/**
 * Group campaigns.
 *
 * A group is a campaign inside the campaign. Someone opens one in honour of a
 * person — for their recovery, their memory, a wedding, a birth — sets a target
 * of acceptances, and collects them from the people they know. The group gets
 * its own page and its own artwork.
 *
 * The rule that shapes everything here: **a light lit inside a group is a light
 * in the main artwork too.** A group join is an ordinary row in the joins table
 * carrying a group id, not a separate kind of thing. So the main counter, the
 * main artwork, the map and the wall all include it without knowing groups
 * exist, and a group's own count is the same rows filtered by one indexed
 * column. Anything else would have meant two sources of truth for the one
 * number this whole campaign is about.
 *
 * Free text written by strangers is published here under the project's name, so
 * a new group waits for approval by default. That default is a switch in the
 * content panel, because how much to trust the people opening groups is a
 * decision about a community and not about software.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Creating, reading and moderating group campaigns.
 */
final class MSL_Groups {

	/**
	 * Query var carrying a group's code.
	 */
	public const QUERY = 'msl_group';

	/**
	 * Query var carrying an owner's token.
	 */
	public const QUERY_TOKEN = 'msl_group_key';

	/**
	 * The path group links live under.
	 */
	public const PATH = 'kvutza';

	/**
	 * Bumped whenever the rewrite rules above change.
	 */
	private const REWRITE_VERSION = '1';

	/**
	 * Option holding the flushed rewrite version.
	 */
	private const REWRITE_OPTION = 'msl_rewrite_version';

	/**
	 * Statuses a group can be in.
	 */
	public const PENDING  = 'pending';
	public const LIVE     = 'live';
	public const CLOSED   = 'closed';
	public const REJECTED = 'rejected';

	/**
	 * Bounds on what an opener may set.
	 */
	public const MIN_TARGET = 5;
	public const MAX_TARGET = 100000;
	public const MAX_STORY  = 600;

	/**
	 * How many groups one address may open in a day.
	 *
	 * Generous for a family, useless for a script.
	 */
	private const RATE_DAY = 3;

	/**
	 * The kinds of dedication a group can carry, by stored index.
	 *
	 * Stored as a number rather than the words, so the words stay editable and
	 * translatable in the content panel without rewriting rows. Index 0 is "no
	 * dedication", which is why the list starts at 1.
	 *
	 * @var array<int, string>
	 */
	public const OCCASIONS = array(
		1 => 'refua',
		2 => 'zechut',
		3 => 'iluy',
		4 => 'kavod',
	);

	/**
	 * Hook the routing.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'add_rewrite' ) );
		add_action( 'init', array( self::class, 'maybe_flush' ), 99 );
		add_action( 'admin_post_nopriv_msl_group', array( self::class, 'handle_form' ) );
		add_action( 'admin_post_msl_group', array( self::class, 'handle_form' ) );
		add_filter( 'query_vars', array( self::class, 'query_vars' ) );
		add_action( 'parse_request', array( self::class, 'route' ) );
		add_filter( 'redirect_canonical', array( self::class, 'keep_url' ) );
		add_action( 'after_switch_theme', array( self::class, 'flush_rewrite' ), 20 );
	}

	/* ---------------------------------------------------------------------
	 * Routing
	 * ------------------------------------------------------------------ */

	/**
	 * Pretty URLs for a group and for an owner's private view.
	 *
	 * Registered on every request, for the same reason the invite rule is: a
	 * rule that only exists after activation disappears the first time another
	 * plugin flushes the rules.
	 */
	public static function add_rewrite(): void {
		add_rewrite_rule(
			'^' . self::PATH . '/([A-Za-z0-9]{6,12})/([A-Za-z0-9]{32})/?$',
			'index.php?' . self::QUERY . '=$matches[1]&' . self::QUERY_TOKEN . '=$matches[2]',
			'top'
		);

		add_rewrite_rule(
			'^' . self::PATH . '/([A-Za-z0-9]{6,12})/?$',
			'index.php?' . self::QUERY . '=$matches[1]',
			'top'
		);
	}

	/**
	 * Register the query vars.
	 *
	 * @param string[] $vars Existing query vars.
	 * @return string[]
	 */
	public static function query_vars( array $vars ): array {
		$vars[] = self::QUERY;
		$vars[] = self::QUERY_TOKEN;

		return $vars;
	}

	/**
	 * Point a group link at the groups page.
	 *
	 * The rule names no page id, for the reason MSL_Joins::route_invite()
	 * records: a baked-in id goes stale the moment the page is recreated, and a
	 * stale rule sends every shared link to the blog index.
	 *
	 * @param WP $wp Current request.
	 */
	public static function route( WP $wp ): void {
		if ( empty( $wp->query_vars[ self::QUERY ] ) ) {
			return;
		}

		$page_id = MSL_Importer::page_id( 'groups' );

		if ( 0 !== $page_id ) {
			$wp->query_vars['page_id'] = $page_id;
		}
	}

	/**
	 * Keep a group link at its own address.
	 *
	 * @param string|false $redirect Where core wants to send the request.
	 * @return string|false
	 */
	public static function keep_url( $redirect ) {
		return '' !== self::requested_code() ? false : $redirect;
	}

	/**
	 * Flush the rules once, on activation.
	 */
	public static function flush_rewrite(): void {
		self::add_rewrite();
		flush_rewrite_rules();
		update_option( self::REWRITE_OPTION, self::REWRITE_VERSION, false );
	}

	/**
	 * Flush once when the rules this theme registers have changed.
	 *
	 * Registering a rule is not enough: WordPress matches requests against a
	 * cached copy in the options table, so a rule added by a theme update does
	 * nothing until something flushes. On a site that is already running, no
	 * activation hook will ever fire again — which would mean every group link
	 * answering 404 until somebody happened to re-save the permalinks screen.
	 *
	 * Guarded by a version so this is one flush per release, not one per
	 * request: flushing is expensive and doing it on every load is a well-known
	 * way to make a site slow.
	 */
	public static function maybe_flush(): void {
		if ( self::REWRITE_VERSION === (string) get_option( self::REWRITE_OPTION, '' ) ) {
			return;
		}

		flush_rewrite_rules();
		update_option( self::REWRITE_OPTION, self::REWRITE_VERSION, false );
	}

	/**
	 * The group code this request is for.
	 *
	 * @return string
	 */
	public static function requested_code(): string {
		$code = sanitize_key( (string) get_query_var( self::QUERY ) );

		return self::is_code( $code ) ? $code : '';
	}

	/**
	 * The owner token this request carries.
	 *
	 * @return string
	 */
	public static function requested_token(): string {
		$token = sanitize_key( (string) get_query_var( self::QUERY_TOKEN ) );

		return 1 === preg_match( '/^[a-z0-9]{32}$/', $token ) ? $token : '';
	}

	/**
	 * Whether a string is shaped like a group code.
	 *
	 * @param string $code Candidate.
	 * @return bool
	 */
	public static function is_code( string $code ): bool {
		return 1 === preg_match( '/^[a-z0-9]{6,12}$/', $code );
	}

	/**
	 * The public address of a group.
	 *
	 * @param string $code Group code.
	 * @return string
	 */
	public static function url( string $code ): string {
		return self::is_code( $code ) ? home_url( '/' . self::PATH . '/' . $code . '/' ) : home_url( '/' );
	}

	/**
	 * The private address that lets an opener manage their group.
	 *
	 * @param string $code  Group code.
	 * @param string $token Owner token.
	 * @return string
	 */
	public static function manage_url( string $code, string $token ): string {
		return self::is_code( $code ) && '' !== $token
			? home_url( '/' . self::PATH . '/' . $code . '/' . $token . '/' )
			: self::url( $code );
	}

	/* ---------------------------------------------------------------------
	 * Reading
	 * ------------------------------------------------------------------ */

	/**
	 * One group by its public code.
	 *
	 * @param string $code Group code.
	 * @return array<string, mixed>|null
	 */
	public static function by_code( string $code ): ?array {
		global $wpdb;

		if ( ! self::is_code( $code ) || ! MSL_DB::ready() ) {
			return null;
		}

		$table = MSL_DB::groups_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE code = %s", $code ), ARRAY_A );

		return is_array( $row ) ? self::shape( $row ) : null;
	}

	/**
	 * One group by its row id.
	 *
	 * @param int $id Row id.
	 * @return array<string, mixed>|null
	 */
	public static function by_id( int $id ): ?array {
		global $wpdb;

		if ( $id <= 0 || ! MSL_DB::ready() ) {
			return null;
		}

		$table = MSL_DB::groups_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );

		return is_array( $row ) ? self::shape( $row ) : null;
	}

	/**
	 * Whether a token is the one that opened this group.
	 *
	 * Compared in constant time. The token is the only thing standing between a
	 * stranger and a group's management view, and a comparison that returns
	 * early leaks how much of a guess was right.
	 *
	 * @param array<string, mixed> $group Group row.
	 * @param string               $token Token from the address.
	 * @return bool
	 */
	public static function owns( array $group, string $token ): bool {
		if ( '' === $token ) {
			return false;
		}

		return hash_equals( (string) $group['owner_token'], $token );
	}

	/**
	 * Whether this request may see a group that is not public yet.
	 *
	 * @param array<string, mixed> $group Group row.
	 * @param string               $token Token from the address.
	 * @return bool
	 */
	public static function may_view( array $group, string $token ): bool {
		if ( self::LIVE === $group['status'] || self::CLOSED === $group['status'] ) {
			return true;
		}

		return self::owns( $group, $token ) || current_user_can( 'edit_pages' );
	}

	/**
	 * How many lights a group has gathered.
	 *
	 * @param int $group_id Group row id.
	 * @return int
	 */
	public static function count_for( int $group_id ): int {
		global $wpdb;

		if ( $group_id <= 0 || ! MSL_DB::ready() ) {
			return 0;
		}

		$key = 'msl_group_count_' . $group_id;
		$hit = get_transient( $key );

		if ( false !== $hit ) {
			return (int) $hit;
		}

		$table = MSL_DB::joins_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE group_id = %d", $group_id ) );

		set_transient( $key, $count, MINUTE_IN_SECONDS );

		return $count;
	}

	/**
	 * Forget a group's cached count.
	 *
	 * @param int $group_id Group row id.
	 */
	public static function flush( int $group_id ): void {
		if ( $group_id > 0 ) {
			delete_transient( 'msl_group_count_' . $group_id );
		}
	}

	/**
	 * The public list of groups, newest first.
	 *
	 * @param int    $page_id Campaign page.
	 * @param int    $limit   Rows to return.
	 * @param int    $offset  Rows to skip.
	 * @param string $status  Status to list.
	 * @return array<int, array<string, mixed>>
	 */
	public static function archive( int $page_id, int $limit = 24, int $offset = 0, string $status = self::LIVE ): array {
		global $wpdb;

		if ( ! MSL_DB::ready() ) {
			return array();
		}

		$table  = MSL_DB::groups_table();
		$limit  = max( 1, min( 100, $limit ) );
		$offset = max( 0, $offset );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE page_id = %d AND status = %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$page_id,
				$status,
				$limit,
				$offset
			),
			ARRAY_A
		);

		$out = array();

		foreach ( (array) $rows as $row ) {
			$group          = self::shape( (array) $row );
			$group['count'] = self::count_for( (int) $group['id'] );
			$out[]          = $group;
		}

		return $out;
	}

	/**
	 * How many groups a campaign has in one status.
	 *
	 * @param int    $page_id Campaign page.
	 * @param string $status  Status to count.
	 * @return int
	 */
	public static function total( int $page_id, string $status = self::LIVE ): int {
		global $wpdb;

		if ( ! MSL_DB::ready() ) {
			return 0;
		}

		$table = MSL_DB::groups_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE page_id = %d AND status = %s", $page_id, $status )
		);
	}

	/**
	 * Groups waiting for a decision.
	 *
	 * @param int $page_id Campaign page.
	 * @return int
	 */
	public static function pending_count( int $page_id ): int {
		return self::total( $page_id, self::PENDING );
	}

	/* ---------------------------------------------------------------------
	 * Writing
	 * ------------------------------------------------------------------ */

	/**
	 * Open a group.
	 *
	 * @param int                  $page_id Campaign page.
	 * @param array<string, mixed> $data    Already-validated values.
	 * @param string               $ip_hash Salted hash of the client address.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function create( int $page_id, array $data, string $ip_hash ): array|WP_Error {
		global $wpdb;

		if ( ! MSL_DB::ready() ) {
			MSL_DB::install();
		}

		if ( ! self::within_rate_limit( $ip_hash ) ) {
			return new WP_Error( 'msl_rate', 'rate' );
		}

		$person = MSL_Auth::current();
		$code   = self::generate_code();
		$token  = bin2hex( random_bytes( 16 ) );
		$email  = (string) $data['owner_email'];
		$auto   = 1 === (int) ( MSL_Meta::get( 'groups', $page_id )['auto_approve'] ?? 0 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- purpose-built table; see MSL_DB.
		$inserted = $wpdb->insert(
			MSL_DB::groups_table(),
			array(
				'uuid'             => wp_generate_uuid4(),
				'page_id'          => $page_id,
				'code'             => $code,
				'owner_token'      => $token,
				'title'            => (string) $data['title'],
				'occasion'         => (int) $data['occasion'],
				'honouree'         => (string) $data['honouree'],
				'story'            => (string) $data['story'],
				'target'           => (int) $data['target'],
				'artwork'          => (string) $data['artwork'],
				'accent'           => (string) $data['accent'],
				'owner_name'       => (string) $data['owner_name'],
				'owner_email'      => '' !== $email ? MSL_Joins::protect( $email ) : null,
				'owner_email_hash' => '' !== $email ? MSL_DB::hash( $email ) : '',
				'ip_hash'          => $ip_hash,
				'person_id'        => null !== $person ? (int) $person['id'] : 0,
				'status'           => $auto ? self::LIVE : self::PENDING,
				'created_at'       => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'msl_insert_failed', 'generic' );
		}

		return array(
			'code'       => $code,
			'status'     => $auto ? self::LIVE : self::PENDING,
			'url'        => self::url( $code ),
			'manage_url' => self::manage_url( $code, $token ),
		);
	}

	/**
	 * Move a group to another status.
	 *
	 * @param int    $id     Group row id.
	 * @param string $status One of the class's status constants.
	 * @return bool
	 */
	public static function set_status( int $id, string $status ): bool {
		global $wpdb;

		if ( ! in_array( $status, array( self::PENDING, self::LIVE, self::CLOSED, self::REJECTED ), true ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$done = $wpdb->update(
			MSL_DB::groups_table(),
			array(
				'status'      => $status,
				'reviewed_by' => get_current_user_id(),
				'reviewed_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);

		return false !== $done;
	}

	/* ---------------------------------------------------------------------
	 * The form
	 * ------------------------------------------------------------------ */

	/**
	 * Handle the opening form.
	 *
	 * An ordinary POST to admin-post.php rather than a REST call from a script.
	 * Opening a group is a once-in-a-campaign action taken by a family member on
	 * whatever device they happen to hold, and a form that silently does nothing
	 * when a script fails to load is the wrong thing to hand them. This way the
	 * page reloads with the answer, and there is exactly one path into the
	 * database instead of two that will drift apart.
	 */
	public static function handle_form(): void {
		$back = self::page_url();

		if ( ! isset( $_POST['msl_group_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['msl_group_nonce'] ) ), 'msl_group' ) ) {
			self::bounce( $back, 'generic' );
		}

		$page_id = MSL_Importer::page_id();
		$copy    = MSL_Meta::get( 'groups', MSL_Importer::page_id( 'groups' ) );

		if ( 1 !== (int) ( $copy['open_on'] ?? 0 ) ) {
			self::bounce( $back, 'closed' );
		}

		// The trap field, and the clock. Both answered before anything is read.
		if ( '' !== trim( (string) ( $_POST['website'] ?? '' ) ) ) {
			self::bounce( $back, 'generic' );
		}

		$clean = self::validate( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validate() sanitises every field.

		if ( is_wp_error( $clean ) ) {
			self::bounce( $back, (string) $clean->get_error_message() );
		}

		$result = self::create( $page_id, (array) $clean, MSL_Joins::client_ip_hash() );

		if ( is_wp_error( $result ) ) {
			self::bounce( $back, (string) $result->get_error_message() );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'msl_new' => $result['code'],
					'msl_key' => self::token_of( (string) $result['code'] ),
				),
				$back
			)
		);

		exit;
	}

	/**
	 * Send the opener back to the form with something to read.
	 *
	 * @param string $back  The groups page.
	 * @param string $error Error key, matching an err_* field.
	 */
	private static function bounce( string $back, string $error ): never {
		wp_safe_redirect( add_query_arg( 'msl_error', $error, $back ) . '#msl-open' );

		exit;
	}

	/**
	 * Validate a submitted group.
	 *
	 * @param array<string, mixed> $post Raw, unslashed submission.
	 * @return array<string, mixed>|WP_Error Error message is an error key, not prose.
	 */
	public static function validate( array $post ): array|WP_Error {
		$title = trim( mb_substr( sanitize_text_field( (string) ( $post['title'] ?? '' ) ), 0, 120 ) );

		// The one genuinely required field. A group with no name cannot be put
		// in a list, shared, or told apart from another in the same family.
		if ( '' === $title ) {
			return new WP_Error( 'msl_invalid', 'title' );
		}

		$raw_email = trim( (string) ( $post['owner_email'] ?? '' ) );
		$email     = sanitize_email( $raw_email );

		if ( '' !== $raw_email && ! is_email( $email ) ) {
			return new WP_Error( 'msl_invalid', 'email' );
		}

		$occasion = (int) ( $post['occasion'] ?? 0 );
		$artwork  = (string) ( $post['artwork'] ?? '' );

		return array(
			'title'       => $title,
			'occasion'    => isset( self::OCCASIONS[ $occasion ] ) ? $occasion : 0,
			'honouree'    => trim( mb_substr( sanitize_text_field( (string) ( $post['honouree'] ?? '' ) ), 0, 120 ) ),
			'story'       => trim( mb_substr( sanitize_textarea_field( (string) ( $post['story'] ?? '' ) ), 0, self::MAX_STORY ) ),
			'target'      => max( self::MIN_TARGET, min( self::MAX_TARGET, (int) ( $post['target'] ?? 0 ) ) ),
			'artwork'     => in_array( $artwork, MSL_Theme::ARTWORKS, true ) ? $artwork : 'rotate',
			'accent'      => MSL_Theme::accent( (string) ( $post['accent'] ?? '' ) ),
			'owner_name'  => trim( mb_substr( sanitize_text_field( (string) ( $post['owner_name'] ?? '' ) ), 0, 80 ) ),
			'owner_email' => $email,
		);
	}

	/**
	 * The groups page address.
	 *
	 * @return string
	 */
	public static function page_url(): string {
		$page = MSL_Importer::page_id( 'groups' );

		return $page > 0 ? (string) get_permalink( $page ) : home_url( '/' );
	}

	/**
	 * The owner token of a group, by code.
	 *
	 * @param string $code Group code.
	 * @return string
	 */
	private static function token_of( string $code ): string {
		$group = self::by_code( $code );

		return null !== $group ? (string) $group['owner_token'] : '';
	}

	/* ---------------------------------------------------------------------
	 * Internals
	 * ------------------------------------------------------------------ */

	/**
	 * A stored row, typed and without the owner's address.
	 *
	 * The encrypted address never leaves this class by accident: shape() is what
	 * every read returns, and it simply does not carry it.
	 *
	 * @param array<string, mixed> $row Raw row.
	 * @return array<string, mixed>
	 */
	private static function shape( array $row ): array {
		return array(
			'id'          => (int) $row['id'],
			'page_id'     => (int) $row['page_id'],
			'code'        => (string) $row['code'],
			'owner_token' => (string) $row['owner_token'],
			'title'       => (string) $row['title'],
			'occasion'    => (int) $row['occasion'],
			'honouree'    => (string) $row['honouree'],
			'story'       => (string) $row['story'],
			'target'      => (int) $row['target'],
			'artwork'     => (string) $row['artwork'],
			'accent'      => (string) $row['accent'],
			'owner_name'  => (string) $row['owner_name'],
			'person_id'   => (int) $row['person_id'],
			'status'      => (string) $row['status'],
			'created_at'  => (string) $row['created_at'],
		);
	}

	/**
	 * The owner's address, for the one place that is allowed to have it.
	 *
	 * @param int $id Group row id.
	 * @return string
	 */
	public static function owner_email( int $id ): string {
		global $wpdb;

		$table = MSL_DB::groups_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$stored = (string) $wpdb->get_var( $wpdb->prepare( "SELECT owner_email FROM {$table} WHERE id = %d", $id ) );

		return '' !== $stored ? MSL_Joins::unprotect( $stored ) : '';
	}

	/**
	 * A code no other group holds.
	 *
	 * @return string
	 */
	private static function generate_code(): string {
		global $wpdb;

		$table = MSL_DB::groups_table();

		for ( $attempt = 0; $attempt < 8; $attempt++ ) {
			$code = strtolower( substr( str_replace( array( '0', 'o', 'l', '1' ), '', bin2hex( random_bytes( 12 ) ) ), 0, 10 ) );
			$code = str_pad( $code, 10, (string) wp_rand( 2, 9 ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$taken = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE code = %s", $code ) );

			if ( 0 === $taken ) {
				return $code;
			}
		}

		return substr( md5( uniqid( '', true ) ), 0, 10 );
	}

	/**
	 * Whether this address has opened too many groups today.
	 *
	 * @param string $ip_hash Salted hash of the client address.
	 * @return bool
	 */
	private static function within_rate_limit( string $ip_hash ): bool {
		global $wpdb;

		if ( '' === $ip_hash ) {
			return true;
		}

		$table = MSL_DB::groups_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$today = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE ip_hash = %s AND created_at > %s",
				$ip_hash,
				gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS )
			)
		);

		return $today < self::RATE_DAY;
	}
}
