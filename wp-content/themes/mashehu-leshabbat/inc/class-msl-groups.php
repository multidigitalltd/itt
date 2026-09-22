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
 * approval exists — but it arrives switched off, and a new group is live the
 * moment it is opened. A group is opened by somebody who wants their family
 * lighting candles tonight, and a queue only the site's owner can clear holds
 * up exactly that. Both directions are one switch in the content panel,
 * because how much to trust the people opening groups is a decision about a
 * community and not about software.
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
	 * Option recording that the demo groups have been put up.
	 */
	private const SEED_OPTION = 'msl_groups_seeded';

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
	 * The artwork value that means "the picture this group uploaded".
	 */
	public const PHOTO_ART = 'photo';

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
		5 => 'zivug',
	);

	/**
	 * Hook the routing.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'add_rewrite' ) );
		add_action( 'init', array( self::class, 'maybe_flush' ), 99 );
		add_action( 'admin_post_nopriv_msl_group', array( self::class, 'handle_form' ) );
		add_action( 'admin_post_msl_group', array( self::class, 'handle_form' ) );
		add_action( 'admin_post_nopriv_msl_group_owner_save', array( self::class, 'handle_owner_save' ) );
		add_action( 'admin_post_msl_group_owner_save', array( self::class, 'handle_owner_save' ) );
		add_filter( 'query_vars', array( self::class, 'query_vars' ) );
		add_action( 'parse_request', array( self::class, 'route' ) );
		add_filter( 'redirect_canonical', array( self::class, 'keep_url' ) );
		add_action( 'after_switch_theme', array( self::class, 'flush_rewrite' ), 20 );
		add_action( 'admin_init', array( self::class, 'maybe_seed' ), 20 );
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
	 * Whether a group can still take a light.
	 *
	 * Live and pending both can, and that is the whole point of the pending
	 * state: approval is the campaign reading a stranger's *text* before it is
	 * published under the project's name, not a gate on whether the person's
	 * family may light candles. A group's link works the moment it is opened —
	 * which is the moment its opener sends it to twenty relatives — and the
	 * lights they add are rows in the joins table either way.
	 *
	 * This exists because three places used to decide it separately and one of
	 * them disagreed: the page printed the group's own join button only when
	 * live, the template loaded the join window only when live, and the header's
	 * join button was printed always. On a pending group that left one visible
	 * button, in the header, wired to a window that was not on the page — so it
	 * did nothing at all, and every group a visitor opens starts pending.
	 *
	 * Closed and rejected cannot: one is over and the other was refused.
	 *
	 * @param array<string, mixed> $group Shaped group.
	 * @return bool
	 */
	public static function accepts_joins( array $group ): bool {
		return in_array( (string) $group['status'], array( self::LIVE, self::PENDING ), true );
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
	 * The marker that makes a listed participant anonymous.
	 *
	 * A single hyphen where the name would be. It reads as a blank in the
	 * field, it is one keystroke, and it cannot collide with a real name the
	 * way a word like "anonymous" could.
	 */
	public const ANON = '-';

	/**
	 * The people a group lists, from the campaign's own list.
	 *
	 * Display only, and kept in a column rather than as rows in the joins
	 * table, for the same reason the opening count is: a name typed here is a
	 * presentation decision that can be changed or taken back, and a join is a
	 * person. Nothing here reaches the main counter, the artwork, the wall or
	 * the map — all of which count rows.
	 *
	 * One per line. `שם | עיר`, or just a name, and a line whose name is a
	 * single hyphen is somebody who asked not to be named.
	 *
	 * @param array<string, mixed> $group Shaped group.
	 * @return array<int, array{name: string, city: string, anon: bool}>
	 */
	public static function seed_people( array $group ): array {
		$raw  = trim( (string) ( $group['seed_names'] ?? '' ) );
		$out  = array();

		if ( '' === $raw ) {
			return $out;
		}

		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( (string) $line );

			if ( '' === $line ) {
				continue;
			}

			$parts = array_map( 'trim', explode( '|', $line, 2 ) );
			$name  = $parts[0];
			$city  = $parts[1] ?? '';

			$out[] = array(
				'name' => self::ANON === $name ? '' : $name,
				'city' => $city,
				'anon' => self::ANON === $name || '' === $name,
			);
		}

		return $out;
	}

	/**
	 * Tidy a submitted list of display names.
	 *
	 * Blank lines go, every line is trimmed, tags are stripped, and the whole
	 * thing is capped — a list nobody will scroll past is not worth storing,
	 * and this column is written by the campaign rather than by a stranger so
	 * the cap is generosity and not defence.
	 *
	 * @param string $raw Submitted text.
	 * @return string
	 */
	private static function clean_seed_names( string $raw ): string {
		$lines = array();

		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( sanitize_text_field( (string) $line ) );

			if ( '' !== $line ) {
				$lines[] = mb_substr( $line, 0, 160 );
			}

			if ( count( $lines ) >= 200 ) {
				break;
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * The number of lights a group shows.
	 *
	 * The real joins plus the opening count the campaign set for it. A brand
	 * new campaign has an archive of groups at zero, and a group at zero reads
	 * as one nobody joined rather than one nobody has found yet — the same
	 * reason the main counter has an opening figure of its own.
	 *
	 * The two numbers are kept apart rather than added into the joins table: an
	 * opening figure is a display decision that can be changed or taken back,
	 * and a join is a person. Nothing about a group's opening count reaches the
	 * main counter, the artwork or the map, all of which count rows — so a
	 * figure set here can never be mistaken for a participant.
	 *
	 * @param array<string, mixed> $group Shaped group.
	 * @return int
	 */
	public static function lights( array $group ): int {
		return max( 0, (int) ( $group['seed_count'] ?? 0 ) ) + self::count_for( (int) $group['id'] );
	}

	/**
	 * Forget a group's cached count.
	 *
	 * @param int $group_id Group row id.
	 */
	public static function flush( int $group_id ): void {
		if ( $group_id > 0 ) {
			delete_transient( 'msl_group_count_' . $group_id );
			delete_transient( 'msl_gfeed_' . $group_id );
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
			$group['count'] = self::lights( $group );
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

	/**
	 * What the table holds, for an editor looking at an archive that is empty.
	 *
	 * The archive lists what is live, which is right — a stranger's text is not
	 * published before somebody has read it. But from the page itself the two
	 * reasons it can be empty look identical: nobody has opened a group, or
	 * four people have and all four are waiting for approval. The second one
	 * has an editor believing the feature is broken while the fix is a button
	 * two clicks away, so the page says which it is, to the people who can act
	 * on it.
	 *
	 * The count of rows belonging to another page is here for the third reason,
	 * the one that is a genuine fault: groups written against a campaign page
	 * that is no longer the campaign page. Nothing in the theme moves them, so
	 * if that number is ever anything but zero it wants saying out loud rather
	 * than leaving as an archive that is quietly, permanently empty.
	 *
	 * @param int $page_id Campaign page.
	 * @return array<string, int>
	 */
	public static function census( int $page_id ): array {
		global $wpdb;

		$out = array(
			'live'      => 0,
			'pending'   => 0,
			'closed'    => 0,
			'rejected'  => 0,
			'elsewhere' => 0,
		);

		if ( ! MSL_DB::ready() ) {
			return $out;
		}

		foreach ( array( self::LIVE, self::PENDING, self::CLOSED, self::REJECTED ) as $status ) {
			$out[ $status ] = self::total( $page_id, $status );
		}

		$table = MSL_DB::groups_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$everything = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$here = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE page_id = %d", $page_id )
		);

		$out['elsewhere'] = max( 0, $everything - $here );

		return $out;
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
	/**
	 * Does a new group go straight to live?
	 *
	 * Read from the **groups page**, not from whichever page is passed around
	 * here. The settings for groups are saved on the page that carries the
	 * groups template, and the pages differ on every normal install — asking
	 * the campaign page for them returns the shipped defaults and the switch in
	 * the panel does nothing at all. That was true of this read until 1.29.0,
	 * quietly, in the one direction nobody would notice: approval stayed on
	 * however the switch was set.
	 *
	 * @return bool
	 */
	private static function auto_approves(): bool {
		$page = MSL_Importer::page_id( 'groups' );

		return 1 === (int) ( MSL_Meta::get( 'groups', $page > 0 ? $page : null )['auto_approve'] ?? 0 );
	}

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
		$auto   = self::auto_approves();

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
				/*
				 * The picture, and the candles derived from it. Both arrive
				 * from the form handler after the file has been decoded,
				 * re-encoded and stored — never from the submitted fields, so
				 * a request that simply names an attachment id gets nowhere.
				 */
				'photo_id'         => max( 0, (int) ( $data['photo_id'] ?? 0 ) ),
				'photo_art'        => (string) ( $data['photo_art'] ?? '' ),
				'status'           => $auto ? self::LIVE : self::PENDING,
				'created_at'       => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
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

		$clean = (array) $clean;

		/*
		 * The pictures, if any came. They are taken after validation and before
		 * the row is written, so a group is never opened with half a picture:
		 * if a file is refused, the person is sent back to the form with the
		 * reason and nothing at all has been created.
		 *
		 * Two fields, because they are two decisions. The cover stands at the
		 * head of the page and is stored; the other becomes the candles and is
		 * not stored at all, only the numbers derived from it.
		 *
		 * Nothing from the request decides what is kept. The bytes are decoded
		 * and re-encoded by MSL_Photo, and what lands in the row is an id of our
		 * own making plus a grid of numbers.
		 */
		$cover = self::uploaded( 'msl_photo' );

		if ( null !== $cover ) {
			$id = MSL_Photo::cover( $cover, (string) $clean['title'] );

			if ( is_wp_error( $id ) ) {
				self::bounce( $back, (string) $id->get_error_message() );
			}

			$clean['photo_id'] = (int) $id;
		}

		$art = self::uploaded( 'msl_art_photo' );

		if ( null !== $art ) {
			$grid = MSL_Photo::artwork( $art );

			if ( is_wp_error( $grid ) ) {
				self::bounce( $back, (string) $grid->get_error_message() );
			}

			// Sending a picture to be made into candles is the whole of saying
			// so; there is no second box to tick.
			$clean['photo_art'] = (string) $grid;
			$clean['artwork']   = self::PHOTO_ART;
		}

		if ( self::PHOTO_ART === ( $clean['artwork'] ?? '' ) && '' === ( $clean['photo_art'] ?? '' ) ) {
			$clean['artwork'] = 'rotate';
		}

		$result = self::create( $page_id, $clean, MSL_Joins::client_ip_hash() );

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
	 * One uploaded file from the request, or null where none was sent.
	 *
	 * Two values are taken and no more: the temporary path PHP wrote itself,
	 * which MSL_Photo checks with is_uploaded_file(), and the size on disk. The
	 * submitted name and the claimed type are not read at all — what the file
	 * is gets decided by looking inside it.
	 *
	 * @param string $field The file field's name.
	 * @return array{tmp_name:string, size:int}|null
	 */
	private static function uploaded( string $field ): ?array {
		if ( ! isset( $_FILES[ $field ] ) ) {
			return null;
		}

		$error = (int) ( $_FILES[ $field ]['error'] ?? UPLOAD_ERR_NO_FILE );

		if ( UPLOAD_ERR_NO_FILE === $error ) {
			return null;
		}

		if ( UPLOAD_ERR_OK !== $error ) {
			// By far the commonest of these is a photograph straight off a
			// phone meeting the server's own upload limit.
			self::bounce( self::page_url(), 'photo_big' );
		}

		return array(
			'tmp_name' => (string) $_FILES[ $field ]['tmp_name'], // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- a path PHP wrote; validated with is_uploaded_file().
			'size'     => (int) $_FILES[ $field ]['size'],
		);
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
			// 'photo' is a shape like any other here, and means "the picture
			// this group uploaded". A group that asks for it without sending
			// one is left with a grid of nothing, and the canvas falls back to
			// the candles — which is why this does not need to know whether a
			// file arrived.
			'artwork'     => in_array( $artwork, MSL_Theme::ARTWORKS, true ) || self::PHOTO_ART === $artwork ? $artwork : 'rotate',
			'accent'      => MSL_Theme::accent( (string) ( $post['accent'] ?? '' ) ),
			'owner_name'  => trim( mb_substr( sanitize_text_field( (string) ( $post['owner_name'] ?? '' ) ), 0, 80 ) ),
			'owner_email' => $email,
			// Only the dashboard ever submits these; the public form has no such
			// fields, and a value smuggled into that request lands on nothing
			// because create() and owner_save() do not read the keys.
			'seed_count'  => max( 0, min( self::MAX_TARGET, (int) ( $post['seed_count'] ?? 0 ) ) ),
			'seed_names'  => self::clean_seed_names( (string) ( $post['seed_names'] ?? '' ) ),
		);
	}

	/* ---------------------------------------------------------------------
	 * The campaign's own hand on the groups
	 * ------------------------------------------------------------------ */

	/**
	 * Every group on a campaign, whatever its status, newest first.
	 *
	 * For the admin screen only. The public archive asks for one status at a
	 * time on purpose, because a rejected group is not content and a pending
	 * one is not public yet.
	 *
	 * @param int $page_id Campaign page.
	 * @param int $limit   Rows to return.
	 * @return array<int, array<string, mixed>>
	 */
	public static function every( int $page_id, int $limit = 200 ): array {
		global $wpdb;

		if ( ! MSL_DB::ready() ) {
			return array();
		}

		$table = MSL_DB::groups_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE page_id = %d ORDER BY created_at DESC LIMIT %d", $page_id, max( 1, min( 500, $limit ) ) ),
			ARRAY_A
		);

		$out = array();

		foreach ( (array) $rows as $row ) {
			$group          = self::shape( (array) $row );
			$group['count'] = self::lights( $group );
			$out[]          = $group;
		}

		return $out;
	}

	/**
	 * Save an edit made by the person who opened the group.
	 *
	 * Ownership is proved by the session, not by the code in the form: the code
	 * is public — it is in the address of a page anyone can open — so it says
	 * which group and nothing more.
	 */
	public static function handle_owner_save(): void {
		check_admin_referer( 'msl_group_owner_save' );

		$person = MSL_Auth::current();
		$back   = MSL_Account::page_url();

		if ( null === $person ) {
			wp_safe_redirect( $back );
			exit;
		}

		$code  = isset( $_POST['code'] ) ? sanitize_key( wp_unslash( (string) $_POST['code'] ) ) : '';
		$group = '' !== $code ? self::by_code( $code ) : null;

		if ( null === $group || (int) $group['person_id'] !== (int) $person['id'] ) {
			wp_safe_redirect( $back );
			exit;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validate() sanitises every field it keeps.
		$data = self::validate( wp_unslash( $_POST ) );

		if ( is_wp_error( $data ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'msl_manage' => $code,
						'msl_error'  => $data->get_error_message(),
					),
					$back
				)
			);
			exit;
		}

		$was = (string) $group['status'];

		self::owner_save( $group, $data );

		$now      = self::by_code( $code );
		$requeued = null !== $now && self::LIVE === $was && self::PENDING === (string) $now['status'];

		wp_safe_redirect(
			add_query_arg(
				array_filter(
					array(
						'msl_manage'   => $code,
						'msl_saved'    => '1',
						'msl_requeued' => $requeued ? '1' : null,
					)
				),
				$back
			)
		);
		exit;
	}

	/**
	 * The groups one person opened, newest first.
	 *
	 * Rejected ones are left out. A group that was refused is not a thing to
	 * hand somebody a management screen for, and the refusal is a conversation
	 * for the campaign to have rather than a row in a list.
	 *
	 * @param int $person_id Person row id.
	 * @return array<int, array<string, mixed>>
	 */
	public static function for_person( int $person_id ): array {
		global $wpdb;

		if ( $person_id <= 0 || ! MSL_DB::ready() ) {
			return array();
		}

		$table = MSL_DB::groups_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE person_id = %d AND status <> %s ORDER BY created_at DESC LIMIT 50",
				$person_id,
				self::REJECTED
			),
			ARRAY_A
		);

		$out = array();

		foreach ( (array) $rows as $row ) {
			$group          = self::shape( (array) $row );
			$group['count'] = self::lights( $group );
			$out[]          = $group;
		}

		return $out;
	}

	/**
	 * Let the person who opened a group change what it says.
	 *
	 * Only the fields that are theirs: the name, the dedication, the few words,
	 * the target and the look. Not the code, not the opening count, not the
	 * status — those belong to the campaign, and an edit screen that could
	 * reach them would be a way around every decision the campaign has made
	 * about this group.
	 *
	 * **Editing the visible text sends an approved group back for approval**,
	 * when approval is switched on. Approval exists because a stranger's words
	 * are published under the project's name; if those words can be rewritten
	 * freely after the reading, the reading was theatre. Changing the target or
	 * the colour does not, because nobody needs to re-read a number.
	 *
	 * @param array<string, mixed> $group Group being edited, already owned.
	 * @param array<string, mixed> $data  Validated fields.
	 * @return bool
	 */
	public static function owner_save( array $group, array $data ): bool {
		global $wpdb;

		$id = (int) $group['id'];

		if ( $id <= 0 || ! MSL_DB::ready() ) {
			return false;
		}

		$fields = array(
			'title'    => (string) $data['title'],
			'occasion' => (int) $data['occasion'],
			'honouree' => (string) $data['honouree'],
			'story'    => (string) $data['story'],
			'target'   => (int) $data['target'],
			'artwork'  => (string) $data['artwork'],
			'accent'   => (string) $data['accent'],
		);

		$formats = array( '%s', '%d', '%s', '%s', '%d', '%s', '%s' );

		$rewrote = (string) $group['title'] !== $fields['title']
			|| (string) $group['honouree'] !== $fields['honouree']
			|| (string) $group['story'] !== $fields['story']
			|| (int) $group['occasion'] !== $fields['occasion'];

		$auto = self::auto_approves();

		if ( $rewrote && ! $auto && self::LIVE === (string) $group['status'] ) {
			$fields['status'] = self::PENDING;
			$formats[]        = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$updated = $wpdb->update( MSL_DB::groups_table(), $fields, array( 'id' => $id ), $formats, array( '%d' ) );

		self::flush( $id );

		return false !== $updated;
	}

	/**
	 * Open a group from the dashboard.
	 *
	 * The same row as one a visitor opens, with three differences that follow
	 * from who is doing it: there is no rate limit, because the limit exists to
	 * stop a script and not the person who owns the site; no owner address is
	 * stored, because nobody needs a private link to a group the campaign runs
	 * from the dashboard; and it goes straight to live, because approval is the
	 * campaign reading a stranger's text and this text is its own.
	 *
	 * @param int                  $page_id Campaign page.
	 * @param array<string, mixed> $data    Validated fields.
	 * @param bool                 $demo    Whether to mark it a demo group.
	 * @return array<string, mixed>|WP_Error
	 */
	public static function admin_create( int $page_id, array $data, bool $demo = false ): array|WP_Error {
		global $wpdb;

		if ( ! MSL_DB::ready() ) {
			MSL_DB::install();
		}

		$code  = self::generate_code();
		$token = bin2hex( random_bytes( 16 ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- purpose-built table; see MSL_DB.
		$inserted = $wpdb->insert(
			MSL_DB::groups_table(),
			array(
				'uuid'        => wp_generate_uuid4(),
				'page_id'     => $page_id,
				'code'        => $code,
				'owner_token' => $token,
				'title'       => (string) $data['title'],
				'occasion'    => (int) $data['occasion'],
				'honouree'    => (string) $data['honouree'],
				'story'       => (string) $data['story'],
				'target'      => (int) $data['target'],
				'artwork'     => (string) $data['artwork'],
				'accent'      => (string) $data['accent'],
				'owner_name'  => (string) $data['owner_name'],
				// Written out rather than left to the column defaults: a group
				// opened from the dashboard has no opener to rate-limit and no
				// signed-in person behind it, and saying so is clearer than a
				// row whose shape depends on what the schema happens to default.
				'ip_hash'     => '',
				'person_id'   => 0,
				'seed_count'  => max( 0, (int) ( $data['seed_count'] ?? 0 ) ),
				'seed_names'  => (string) ( $data['seed_names'] ?? '' ),
				'is_demo'     => $demo ? 1 : 0,
				'status'      => self::LIVE,
				'created_at'  => current_time( 'mysql', true ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'msl_insert_failed', 'generic' );
		}

		return array(
			'id'     => (int) $wpdb->insert_id,
			'code'   => $code,
			'url'    => self::url( $code ),
			'status' => self::LIVE,
		);
	}

	/**
	 * Rewrite a group's editable fields from the dashboard.
	 *
	 * The code, the owner token, the opener's address and the created date are
	 * not in the list: they are the group's identity and its provenance, not
	 * its content, and an edit screen that could change them would be a way to
	 * quietly turn one person's group into another's.
	 *
	 * @param int                  $id   Group row id.
	 * @param array<string, mixed> $data Validated fields.
	 * @return bool
	 */
	public static function admin_save( int $id, array $data ): bool {
		global $wpdb;

		if ( $id <= 0 || ! MSL_DB::ready() ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- purpose-built table; see MSL_DB.
		$updated = $wpdb->update(
			MSL_DB::groups_table(),
			array(
				'title'      => (string) $data['title'],
				'occasion'   => (int) $data['occasion'],
				'honouree'   => (string) $data['honouree'],
				'story'      => (string) $data['story'],
				'target'     => (int) $data['target'],
				'artwork'    => (string) $data['artwork'],
				'accent'     => (string) $data['accent'],
				'owner_name' => (string) $data['owner_name'],
				'seed_count' => max( 0, (int) ( $data['seed_count'] ?? 0 ) ),
				'seed_names' => (string) ( $data['seed_names'] ?? '' ),
			),
			array( 'id' => $id ),
			array( '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);

		self::flush( $id );

		return false !== $updated;
	}

	/**
	 * Remove a group's row.
	 *
	 * Offered only for a demo group. A group a real person opened is closed or
	 * rejected, never deleted: its joins are still rows in the joins table and
	 * still lights in the main artwork, and dropping the row they point at
	 * would leave them counted but homeless.
	 *
	 * @param int $id Group row id.
	 * @return bool
	 */
	public static function delete_demo( int $id ): bool {
		global $wpdb;

		$group = self::by_id( $id );

		if ( null === $group || true !== $group['is_demo'] ) {
			return false;
		}

		if ( self::count_for( $id ) > 0 ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- purpose-built table; see MSL_DB.
		$deleted = $wpdb->delete( MSL_DB::groups_table(), array( 'id' => $id ), array( '%d' ) );

		self::flush( $id );

		return false !== $deleted;
	}

	/**
	 * Put a handful of groups on the page, once, so the archive is not empty.
	 *
	 * A campaign about people joining something opens with an archive that says
	 * "no groups yet", which is the least persuasive sentence on the site. These
	 * three are real rows, editable and deletable from the groups screen like
	 * any other, marked as demos so the campaign can tell at a glance which are
	 * hers and which came from strangers.
	 *
	 * Seeded once, by an option, and never re-seeded: a campaign that deletes
	 * them meant to.
	 */
	public static function maybe_seed(): void {
		if ( '' !== (string) get_option( self::SEED_OPTION, '' ) ) {
			return;
		}

		$page_id = MSL_Importer::page_id();

		if ( $page_id < 1 || ! MSL_DB::ready() ) {
			return;
		}

		// Never seed onto a campaign that already has groups of its own.
		if ( self::total( $page_id, self::LIVE ) > 0 || self::total( $page_id, self::PENDING ) > 0 ) {
			update_option( self::SEED_OPTION, 'skipped', false );

			return;
		}

		foreach ( self::demo_rows() as $row ) {
			self::admin_create( $page_id, $row, true );
		}

		update_option( self::SEED_OPTION, 'done', false );
	}

	/**
	 * The groups the seeder puts up.
	 *
	 * Deliberately unremarkable: a recovery, a wedding and a memory, the three
	 * reasons a family actually opens one of these. No real names — "משפחת לוי"
	 * is the Hebrew equivalent of a placeholder surname — because a demo group
	 * carrying a real person's name is a thing somebody would have to notice
	 * before launch, and they would notice it last.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function demo_rows(): array {
		return array(
			array(
				'title'      => 'קבוצת משפחת לוי',
				'occasion'   => 1,
				'honouree'   => 'שרה בת רחל',
				'story'      => 'פתחנו את הקבוצה הזאת כדי לאסוף קבלות לשבת לרפואתה השלמה. כל קבלה, קטנה ככל שתהיה, היא נר נוסף.',
				'target'     => 180,
				'artwork'    => 'candles',
				'accent'     => '#FFB25C',
				'owner_name' => 'משפחת לוי',
				'seed_count' => 64,
				'seed_names' => "רחל | ירושלים\nמשה | בני ברק\n-\nתמר | פתח תקווה\nאליהו | חיפה\n- | תל אביב\nנעמי | מודיעין עילית\nיוסף | בית שמש\nשירה\n-\nאברהם | אשדוד\nחנה | נתניה",
			),
			array(
				'title'      => 'לכבוד החתונה',
				'occasion'   => 4,
				'honouree'   => 'דני ומיכל',
				'story'      => 'במקום עוד מתנה — קבלה אחת לשבת. שיהיה להם בית של אור.',
				'target'     => 120,
				'artwork'    => 'star',
				'accent'     => '#FFD374',
				'owner_name' => 'החברים',
				'seed_count' => 41,
				'seed_names' => "דוד | תל אביב\nמיכל | רעננה\n-\nאיתי | גבעתיים\nנועה | חיפה\nיעל\n- | ירושלים\nאורי | כפר סבא\nטליה | רמת גן",
			),
			array(
				'title'      => 'לעילוי נשמת סבא',
				'occasion'   => 3,
				'honouree'   => 'יוסף בן אברהם',
				'story'      => 'סבא הדליק נרות כל חייו. אספנו את המשפחה כדי שימשיכו להידלק.',
				'target'     => 250,
				'artwork'    => 'menorah',
				'accent'     => '#E8A05C',
				'owner_name' => 'הנכדים',
				'seed_count' => 96,
				'seed_names' => "שמואל | ירושלים\n-\nלאה | בני ברק\nיצחק | בית שמש\nמרים | אלעד\n- | מודיעין עילית\nדוב | ירושלים\nאסתר\nפנחס | אשדוד\n-\nרבקה | פתח תקווה",
			),
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
			'seed_count'  => (int) ( $row['seed_count'] ?? 0 ),
			'seed_names'  => (string) ( $row['seed_names'] ?? '' ),
			'photo_id'    => (int) ( $row['photo_id'] ?? 0 ),
			'photo_art'   => (string) ( $row['photo_art'] ?? '' ),
			'is_demo'     => 1 === (int) ( $row['is_demo'] ?? 0 ),
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
