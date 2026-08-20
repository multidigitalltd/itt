<?php
/**
 * Custom tables for participation rows.
 *
 * A single campaign can reach a quarter of a million rows in one week. That is
 * the one place this theme departs from the house pattern of storing everything
 * in posts and post meta: `wp_posts` plus `wp_postmeta` would need five rows and
 * five index writes per join, and every aggregate would become a meta_query.
 * Three narrow, indexed tables cost one insert and answer every count the page
 * needs with a covered index.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Schema creation and upgrades.
 */
final class MSL_DB {

	/**
	 * Bumped whenever the schema below changes.
	 */
	private const SCHEMA_VERSION = '2';

	/**
	 * Option holding the installed schema version.
	 */
	private const OPTION = 'msl_db_version';

	/**
	 * Hook the installer.
	 */
	public static function init(): void {
		add_action( 'after_switch_theme', array( self::class, 'install' ) );
		add_action( 'admin_init', array( self::class, 'maybe_upgrade' ) );
	}

	/**
	 * Table name for joins.
	 *
	 * @return string
	 */
	public static function joins_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'msl_joins';
	}

	/**
	 * Table name for the chosen commitment types of each join.
	 *
	 * @return string
	 */
	public static function things_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'msl_join_things';
	}

	/**
	 * Table name for dedications.
	 *
	 * @return string
	 */
	public static function dedications_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'msl_dedications';
	}

	/**
	 * Whether the tables exist and are current.
	 *
	 * @return bool
	 */
	public static function ready(): bool {
		return self::SCHEMA_VERSION === (string) get_option( self::OPTION, '' );
	}

	/**
	 * Create or upgrade the tables when the recorded version is behind.
	 */
	public static function maybe_upgrade(): void {
		if ( ! self::ready() ) {
			self::install();
		}
	}

	/**
	 * Run dbDelta over the schema.
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$joins   = self::joins_table();
		$things  = self::things_table();
		$deds    = self::dedications_table();

		// Note the deliberate omissions: no full name, no address, no free-text
		// beyond the dedication, and no raw phone/email/IP — only salted hashes,
		// which are enough for dedup and rate limiting and useless to a thief.
		//
		// page_piece is UNIQUE rather than a plain key. Two joins arriving at the
		// same moment can both read the same free position before either has
		// written, and a duplicate position makes one of the two participants
		// disappear from the artwork — /pieces is keyed by position, so the
		// second row silently replaces the first. The uniqueness is what turns
		// that race into a failed insert that MSL_Joins::record() can retry.
		$sql = array(
			"CREATE TABLE {$joins} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				uuid CHAR(36) NOT NULL,
				page_id BIGINT UNSIGNED NOT NULL,
				piece_index INT UNSIGNED NOT NULL,
				first_name VARCHAR(80) NOT NULL DEFAULT '',
				city VARCHAR(120) NOT NULL DEFAULT '',
				country VARCHAR(120) NOT NULL DEFAULT '',
				lat DECIMAL(9,6) NULL,
				lng DECIMAL(9,6) NULL,
				is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
				lang CHAR(2) NOT NULL DEFAULT 'he',
				referral_code CHAR(12) NOT NULL,
				referred_by CHAR(12) NOT NULL DEFAULT '',
				phone_hash CHAR(64) NOT NULL DEFAULT '',
				email_hash CHAR(64) NOT NULL DEFAULT '',
				ip_hash CHAR(64) NOT NULL DEFAULT '',
				reminder_optin TINYINT(1) NOT NULL DEFAULT 0,
				reminder_phone VARCHAR(255) NULL,
				reminder_email VARCHAR(255) NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY uuid (uuid),
				UNIQUE KEY referral_code (referral_code),
				KEY page_created (page_id, created_at),
				UNIQUE KEY page_piece (page_id, piece_index),
				KEY referred_by (referred_by),
				KEY page_country (page_id, country(40)),
				KEY page_city (page_id, city(40)),
				KEY dedup_ip (page_id, ip_hash, created_at),
				KEY dedup_email (page_id, email_hash),
				KEY dedup_phone (page_id, phone_hash)
			) {$charset};",
			"CREATE TABLE {$things} (
				join_id BIGINT UNSIGNED NOT NULL,
				thing_index SMALLINT UNSIGNED NOT NULL,
				custom_label VARCHAR(140) NOT NULL DEFAULT '',
				PRIMARY KEY  (join_id, thing_index),
				KEY thing_index (thing_index)
			) {$charset};",
			"CREATE TABLE {$deds} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				join_id BIGINT UNSIGNED NOT NULL,
				page_id BIGINT UNSIGNED NOT NULL,
				kind SMALLINT UNSIGNED NOT NULL,
				body VARCHAR(280) NOT NULL DEFAULT '',
				status VARCHAR(12) NOT NULL DEFAULT 'pending',
				reviewed_by BIGINT UNSIGNED NULL,
				reviewed_at DATETIME NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				KEY join_id (join_id),
				KEY page_status (page_id, status, created_at)
			) {$charset};",
		);

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		update_option( self::OPTION, self::SCHEMA_VERSION, false );
	}

	/**
	 * Salt used before anything identifying reaches the database.
	 *
	 * Prefers a constant defined in wp-config.php, so a database dump on its own
	 * cannot be walked back to an IP or a phone number. Falls back to the core
	 * auth salt, which lives in wp-config.php too.
	 *
	 * @return string
	 */
	public static function salt(): string {
		if ( defined( 'MSL_HASH_SALT' ) && is_string( MSL_HASH_SALT ) && '' !== MSL_HASH_SALT ) {
			return MSL_HASH_SALT;
		}

		return wp_salt( 'auth' );
	}

	/**
	 * Salted one-way hash of an identifying value.
	 *
	 * @param string $value Raw value; an empty value hashes to an empty string.
	 * @return string
	 */
	public static function hash( string $value ): string {
		$value = trim( $value );

		return '' === $value ? '' : hash( 'sha256', self::salt() . '|' . mb_strtolower( $value ) );
	}
}
