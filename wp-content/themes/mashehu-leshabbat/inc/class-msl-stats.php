<?php
/**
 * Live counters, cached.
 *
 * The page itself must stay fully cacheable, so the server-rendered numbers are
 * a seed and the browser refreshes them from /stats. Every aggregate here is a
 * covered count against an indexed column and is held in a transient, so a busy
 * Friday costs one query per window rather than one per visitor.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Aggregates for one campaign page.
 */
final class MSL_Stats {

	/**
	 * How long the aggregate bundle is held.
	 */
	private const TTL = 10;

	/**
	 * Per-request memo, so one render never counts twice.
	 *
	 * @var array<int, array<string, int>>
	 */
	private static array $memo = array();

	/**
	 * Every number the page shows, for one page.
	 *
	 * @param int $page_id Page ID.
	 * @return array<string, int>
	 */
	public static function all( int $page_id ): array {
		if ( isset( self::$memo[ $page_id ] ) ) {
			return self::$memo[ $page_id ];
		}

		$key    = 'msl_stats_' . $page_id;
		$cached = get_transient( $key );
		$rows   = is_array( $cached ) ? $cached : self::count_rows( $page_id );

		if ( ! is_array( $cached ) ) {
			set_transient( $key, $rows, self::TTL );
		}

		$campaign = MSL_Meta::get( 'campaign', $page_id );
		$seed     = (int) $campaign['seed_count'];
		$target   = max( 1, (int) $campaign['target'] );

		/*
		 * The display figures are added here and not inside the cache.
		 *
		 * What the database holds is cached for ten seconds, because counting
		 * a quarter of a million rows on every page view is the one query that
		 * would actually hurt. The opening figures are arithmetic on the clock
		 * — they cost nothing and they have to move, which a cached copy of
		 * them would stop them doing.
		 */
		$accrued = self::demo_accrued( $campaign );

		$participants = $seed + $accrued + $rows['joins'];

		$stats = array(
			'participants' => $participants,
			'joins'        => $rows['joins'],
			'seed'         => $seed + $accrued,
			'target'       => $target,
			'pct'          => (int) min( 100, round( $participants / $target * 100 ) ),
			// The configured figures are the floor: they carry the reach the
			// campaign already had before this site went live.
			'countries'    => max( (int) $campaign['countries'], $rows['countries'] ),
			'cities'       => max( (int) $campaign['cities'], $rows['cities'] ),
			'dedications'  => $rows['dedications'],
			'last10'       => $rows['last10'] + self::demo_recent( $campaign ),
		);

		self::$memo[ $page_id ] = $stats;

		return $stats;
	}

	/*
	 * ---------------------------------------------------------------------
	 * The opening pace
	 *
	 * A campaign that has not launched shows a counter that never moves and
	 * the line "0 people joined in the last ten minutes", which reads as a
	 * broken page rather than a quiet one. The campaign can set a pace — how
	 * many joins an hour to show — and both numbers are derived from it and
	 * from the clock.
	 *
	 * Two rules this obeys, and they are the same two the opening count obeys:
	 *
	 * 1. **It never becomes a person.** Nothing here writes to the joins table.
	 *    The export, the map's real points, the dedication queue and every
	 *    per-person number are rows, and rows are people. This is arithmetic
	 *    laid over the top of them, and it can be set back to zero in one
	 *    field with nothing to clean up.
	 * 2. **Everyone sees the same number at the same moment.** It is a pure
	 *    function of the clock, not a random draw — a random one would differ
	 *    between two people looking together, and would be frozen at whatever
	 *    value a page cache happened to catch.
	 * ------------------------------------------------------------------ */

	/**
	 * How many the pace has added since it started.
	 *
	 * @param array<string, mixed> $campaign Resolved campaign section.
	 * @return int
	 */
	private static function demo_accrued( array $campaign ): int {
		$rate = self::demo_rate( $campaign );

		if ( $rate <= 0 ) {
			return 0;
		}

		$elapsed = time() - self::demo_start( $campaign );

		return $elapsed <= 0 ? 0 : (int) floor( $elapsed / HOUR_IN_SECONDS * $rate );
	}

	/**
	 * How many the pace shows for the last ten minutes.
	 *
	 * A tenth of an hour of the pace, varied by up to half either way so that
	 * the line reads as a pulse and not as a division. The variation is drawn
	 * from the ten-minute slot itself, so it holds still for that slot and then
	 * changes — the same for every visitor, all of them at once.
	 *
	 * @param array<string, mixed> $campaign Resolved campaign section.
	 * @return int
	 */
	private static function demo_recent( array $campaign ): int {
		$rate = self::demo_rate( $campaign );

		if ( $rate <= 0 || time() < self::demo_start( $campaign ) ) {
			return 0;
		}

		$slot  = (int) floor( time() / ( 10 * MINUTE_IN_SECONDS ) );
		$noise = ( crc32( 'msl-pace-' . $slot ) % 1000 ) / 1000;

		return (int) max( 0, round( $rate / 6 * ( 0.5 + $noise ) ) );
	}

	/**
	 * The configured pace, per hour.
	 *
	 * @param array<string, mixed> $campaign Resolved campaign section.
	 * @return int
	 */
	private static function demo_rate( array $campaign ): int {
		return max( 0, min( 100000, (int) ( $campaign['demo_rate'] ?? 0 ) ) );
	}

	/**
	 * The moment the pace starts counting from.
	 *
	 * An empty field means the start of today, in the site's own timezone, so
	 * a campaign that switches the pace on sees a number that starts small and
	 * grows rather than one that arrives already enormous.
	 *
	 * @param array<string, mixed> $campaign Resolved campaign section.
	 * @return int
	 */
	private static function demo_start( array $campaign ): int {
		$set = trim( (string) ( $campaign['demo_from'] ?? '' ) );

		if ( '' === $set ) {
			$today = (string) wp_date( 'Y-m-d' );
			$set   = $today;
		}

		try {
			$when = new DateTimeImmutable( $set . ' 00:00:00', wp_timezone() );
		} catch ( Exception $e ) {
			return time();
		}

		return $when->getTimestamp();
	}

	/**
	 * The participant count only.
	 *
	 * @param int $page_id Page ID.
	 * @return int
	 */
	public static function participants( int $page_id ): int {
		return self::all( $page_id )['participants'];
	}

	/**
	 * The next free position in the artwork.
	 *
	 * Positions are handed out in order rather than by row id, so the artwork
	 * fills in the same order it was joined even after rows are removed.
	 *
	 * @param int $page_id Page ID.
	 * @return int
	 */
	public static function next_piece_index( int $page_id ): int {
		global $wpdb;

		if ( ! MSL_DB::ready() ) {
			return 0;
		}

		$table = MSL_DB::joins_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$max = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(piece_index) FROM {$table} WHERE page_id = %d", $page_id ) );

		$seed = (int) MSL_Meta::get( 'campaign', $page_id )['seed_count'];

		return null === $max ? $seed : (int) $max + 1;
	}

	/**
	 * Drop the cached bundle for one page.
	 *
	 * @param int $page_id Page ID.
	 */
	public static function flush( int $page_id ): void {
		delete_transient( 'msl_stats_' . $page_id );
		delete_transient( 'msl_map_' . $page_id );

		foreach ( MSL_I18N::LANGS as $lang ) {
			delete_transient( 'msl_feed_' . $page_id . '_' . $lang );
		}

		unset( self::$memo[ $page_id ] );
	}

	/**
	 * Count the participation rows.
	 *
	 * @param int $page_id Page ID.
	 * @return array<string, int>
	 */
	private static function count_rows( int $page_id ): array {
		global $wpdb;

		$empty = array(
			'joins'       => 0,
			'countries'   => 0,
			'cities'      => 0,
			'dedications' => 0,
			'last10'      => 0,
		);

		if ( ! MSL_DB::ready() ) {
			return $empty;
		}

		$joins = MSL_DB::joins_table();
		$deds  = MSL_DB::dedications_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS joins,
				        COUNT(DISTINCT NULLIF(country, '')) AS countries,
				        COUNT(DISTINCT NULLIF(city, '')) AS cities,
				        SUM(created_at >= %s) AS last10
				 FROM {$joins} WHERE page_id = %d",
				gmdate( 'Y-m-d H:i:s', time() - 10 * MINUTE_IN_SECONDS ),
				$page_id
			),
			ARRAY_A
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$dedications = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$deds} WHERE page_id = %d AND status = %s", $page_id, 'approved' )
		);

		if ( ! is_array( $row ) ) {
			return $empty;
		}

		return array(
			'joins'       => (int) $row['joins'],
			'countries'   => (int) $row['countries'],
			'cities'      => (int) $row['cities'],
			'dedications' => $dedications,
			'last10'      => (int) $row['last10'],
		);
	}
}
