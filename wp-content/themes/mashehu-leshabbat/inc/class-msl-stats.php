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

		if ( is_array( $cached ) ) {
			self::$memo[ $page_id ] = $cached;

			return $cached;
		}

		$campaign = MSL_Meta::get( 'campaign', $page_id );
		$seed     = (int) $campaign['seed_count'];
		$target   = max( 1, (int) $campaign['target'] );
		$rows     = self::count_rows( $page_id );

		$participants = $seed + $rows['joins'];

		$stats = array(
			'participants'  => $participants,
			'joins'         => $rows['joins'],
			'seed'          => $seed,
			'target'        => $target,
			'pct'           => (int) min( 100, round( $participants / $target * 100 ) ),
			// The configured figures are the floor: they carry the reach the
			// campaign already had before this site went live.
			'countries'     => max( (int) $campaign['countries'], $rows['countries'] ),
			'cities'        => max( (int) $campaign['cities'], $rows['cities'] ),
			'dedications'   => $rows['dedications'],
			'last10'        => $rows['last10'],
		);

		set_transient( $key, $stats, self::TTL );
		self::$memo[ $page_id ] = $stats;

		return $stats;
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
