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
	private const SCHEMA_VERSION = '11';

	/**
	 * Option holding the installed schema version.
	 */
	private const OPTION = 'msl_db_version';

	/**
	 * Option holding what the last install could not put right.
	 *
	 * Written on every install, emptied when there is nothing wrong, and read
	 * by the panel — the only way anyone running the site can see that the
	 * database is the reason a candle would not light.
	 */
	private const MISSING_OPTION = 'msl_db_missing';

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
	 * The people table: one row per signed-in participant.
	 *
	 * Deliberately not wp_users. A campaign that hopes for hundreds of thousands
	 * of participants would put every one of them on the site's login screen and
	 * in its user list, and a participant is not an author, an editor or anyone
	 * who should be able to reach wp-admin at all. This table holds what signing
	 * in is actually for here — a stable identity and a personal link — and
	 * nothing else.
	 *
	 * @return string
	 */
	public static function people_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'msl_people';
	}

	/**
	 * People who asked to be reminded before Shabbat.
	 *
	 * Kept apart from the joins on purpose. Asking for a reminder is not adding
	 * a candle, and folding the two together would make the counter — the one
	 * number this whole campaign is about — quietly untrue.
	 *
	 * @return string
	 */
	public static function reminders_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'msl_reminders';
	}

	/**
	 * Group campaigns: one row per group someone opened.
	 *
	 * A group is a campaign inside the campaign — a person collects acceptances
	 * from the people they know, in honour of someone. It is a table of its own
	 * rather than a flag on the joins, because a group exists before anyone has
	 * joined it and carries things a join has no room for: a target, a story, a
	 * moderation state and an owner.
	 *
	 * @return string
	 */
	public static function groups_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'msl_groups';
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
	 * The tables, as they should be.
	 *
	 * @return array<int, string> One CREATE TABLE statement per table.
	 */
	private static function schema(): array {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();
		$joins   = self::joins_table();
		$things  = self::things_table();
		$deds    = self::dedications_table();
		$people  = self::people_table();
		$remind  = self::reminders_table();
		$groups  = self::groups_table();

		/*
		 * Nothing below may carry a comment inside the statement, in any of the
		 * forms SQL accepts. dbDelta does not parse SQL. It cuts what it is
		 * given into statements at every semicolon and then reads each line of
		 * what is left as a column definition — so one semicolon inside a
		 * comment ends the CREATE TABLE there, and every column below it is
		 * simply never seen. A new site is unharmed, because a new site gets
		 * the statement itself and MySQL reads comments properly; the damage
		 * falls only on a site that already has the table, where the missing
		 * columns are never added and nothing says so.
		 *
		 * That is what happened to reminder_weeks, reminders_sent and remind_at
		 * in 1.44.0: one semicolon, in a sentence, in a comment, and every
		 * candle lit on an upgraded site failed on a column that was not there.
		 * Explanations belong out here instead.
		 *
		 * The reminder columns: reminder_weeks is how long the undertaking is
		 * for; reminders_sent counts the letters gone out and runs one past it,
		 * that last increment being the letter asking whether they would like to
		 * take something on again — one counter rather than two to keep in step.
		 * remind_at is when the next letter is due, so a job that runs late
		 * still sends rather than skipping the week it missed.
		 */

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
				reminder_weeks SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				reminders_sent SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				remind_at DATETIME NULL,
				group_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY uuid (uuid),
				UNIQUE KEY referral_code (referral_code),
				KEY page_created (page_id, created_at),
				UNIQUE KEY page_piece (page_id, piece_index),
				KEY referred_by (referred_by),
				KEY page_country (page_id, country(40)),
				KEY page_city (page_id, city(40)),
				KEY due (remind_at),
				KEY dedup_ip (page_id, ip_hash, created_at),
				KEY dedup_email (page_id, email_hash),
				KEY dedup_phone (page_id, phone_hash),
				KEY group_created (group_id, created_at)
			) {$charset};",
			"CREATE TABLE {$things} (
				join_id BIGINT UNSIGNED NOT NULL,
				thing_index SMALLINT UNSIGNED NOT NULL,
				custom_label VARCHAR(140) NOT NULL DEFAULT '',
				PRIMARY KEY  (join_id, thing_index),
				KEY thing_index (thing_index)
			) {$charset};",
			"CREATE TABLE {$people} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				uuid CHAR(36) NOT NULL,
				provider VARCHAR(16) NOT NULL DEFAULT 'google',
				provider_id VARCHAR(191) NOT NULL,
				display_name VARCHAR(120) NOT NULL DEFAULT '',
				email_hash CHAR(64) NOT NULL DEFAULT '',
				email VARCHAR(255) NULL,
				password_hash VARCHAR(255) NOT NULL DEFAULT '',
				session_key CHAR(32) NOT NULL DEFAULT '',
				reset_hash CHAR(64) NOT NULL DEFAULT '',
				reset_expires DATETIME NULL,
				avatar_url VARCHAR(255) NOT NULL DEFAULT '',
				referral_code CHAR(12) NOT NULL,
				lang CHAR(2) NOT NULL DEFAULT 'he',
				created_at DATETIME NOT NULL,
				last_login_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY uuid (uuid),
				UNIQUE KEY provider_identity (provider, provider_id),
				UNIQUE KEY referral_code (referral_code),
				KEY email_hash (email_hash),
				KEY reset_hash (reset_hash)
			) {$charset};",
			"CREATE TABLE {$remind} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				page_id BIGINT UNSIGNED NOT NULL,
				name VARCHAR(80) NOT NULL DEFAULT '',
				email VARCHAR(255) NOT NULL,
				email_hash CHAR(64) NOT NULL,
				thing_index SMALLINT UNSIGNED NULL,
				custom_label VARCHAR(140) NOT NULL DEFAULT '',
				lang CHAR(2) NOT NULL DEFAULT 'he',
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY page_email (page_id, email_hash),
				KEY page_created (page_id, created_at)
			) {$charset};",
			"CREATE TABLE {$groups} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				uuid CHAR(36) NOT NULL,
				page_id BIGINT UNSIGNED NOT NULL,
				code CHAR(12) NOT NULL,
				owner_token CHAR(32) NOT NULL,
				title VARCHAR(120) NOT NULL DEFAULT '',
				occasion SMALLINT UNSIGNED NOT NULL DEFAULT 0,
				honouree VARCHAR(120) NOT NULL DEFAULT '',
				story VARCHAR(600) NOT NULL DEFAULT '',
				target INT UNSIGNED NOT NULL DEFAULT 0,
				artwork VARCHAR(16) NOT NULL DEFAULT 'rotate',
				accent CHAR(7) NOT NULL DEFAULT '',
				owner_name VARCHAR(80) NOT NULL DEFAULT '',
				owner_email VARCHAR(255) NULL,
				owner_email_hash CHAR(64) NOT NULL DEFAULT '',
				ip_hash CHAR(64) NOT NULL DEFAULT '',
				person_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				seed_count INT UNSIGNED NOT NULL DEFAULT 0,
				seed_names TEXT NULL,
				photo_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
				is_demo TINYINT(1) NOT NULL DEFAULT 0,
				status VARCHAR(12) NOT NULL DEFAULT 'pending',
				reviewed_by BIGINT UNSIGNED NULL,
				reviewed_at DATETIME NULL,
				created_at DATETIME NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY uuid (uuid),
				UNIQUE KEY code (code),
				KEY page_status (page_id, status, created_at),
				KEY owner_email_hash (owner_email_hash),
				KEY person_id (person_id),
				KEY opener_rate (ip_hash, created_at)
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

		return $sql;
	}

	/**
	 * Create or upgrade the tables, and prove it worked.
	 *
	 * dbDelta is trusted to do the work and then checked, because it has been
	 * wrong here before and a schema that is wrong is not a cosmetic fault: the
	 * insert that lights a candle names every column, so one missing column is
	 * every candle on the site refusing to light. Anything dbDelta did not
	 * manage is added directly, and the version is recorded only once the
	 * tables actually match — a site left half-upgraded tries again on the next
	 * admin screen instead of believing itself finished.
	 */
	public static function install(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$schema = self::schema();

		foreach ( $schema as $statement ) {
			dbDelta( $statement );
		}

		self::repair( $schema );

		$missing = self::missing( $schema );

		update_option( self::MISSING_OPTION, $missing, false );

		if ( array() !== $missing ) {
			return;
		}

		update_option( self::OPTION, self::SCHEMA_VERSION, false );
	}

	/**
	 * Add, directly, any column the schema asks for that the table has not got.
	 *
	 * @param array<int, string> $schema Statements from schema().
	 */
	private static function repair( array $schema ): void {
		global $wpdb;

		foreach ( self::wanted( $schema ) as $table => $columns ) {
			$have = self::present( $table );

			if ( array() === $have ) {
				// No table at all, or no answer from the database. Creating it
				// is dbDelta's job and adding columns to a table that is not
				// there would only produce a second kind of error.
				continue;
			}

			foreach ( $columns as $name => $definition ) {
				if ( in_array( $name, $have, true ) ) {
					continue;
				}

				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- identifiers and a definition, both from the schema above; neither can carry anything a visitor typed.
				$wpdb->query( "ALTER TABLE {$table} ADD COLUMN {$definition}" );
			}
		}
	}

	/**
	 * Columns the schema asks for that the database has not got.
	 *
	 * @param array<int, string>|null $schema Statements, or null to read them.
	 * @return array<int, string> "table.column", ready to be shown to a human.
	 */
	public static function missing( ?array $schema = null ): array {
		$schema  = null === $schema ? self::schema() : $schema;
		$missing = array();

		foreach ( self::wanted( $schema ) as $table => $columns ) {
			$have = self::present( $table );

			if ( array() === $have ) {
				$missing[] = $table;
				continue;
			}

			foreach ( array_keys( $columns ) as $name ) {
				if ( ! in_array( $name, $have, true ) ) {
					$missing[] = $table . '.' . $name;
				}
			}
		}

		return $missing;
	}

	/**
	 * The columns each statement asks for.
	 *
	 * Read from the statements themselves rather than listed a second time, so
	 * a column added to the schema is covered by this the moment it is written.
	 *
	 * @param array<int, string> $schema Statements from schema().
	 * @return array<string, array<string, string>> Table => column => definition.
	 */
	private static function wanted( array $schema ): array {
		$wanted = array();

		foreach ( $schema as $statement ) {
			if ( ! preg_match( '/CREATE TABLE\s+(\S+)\s*\((.*)\)[^)]*$/s', $statement, $found ) ) {
				continue;
			}

			$table             = $found[1];
			$wanted[ $table ] = array();

			foreach ( explode( "\n", $found[2] ) as $line ) {
				$line = trim( $line, " \t\r\n," );

				if ( ! preg_match( '/^([a-z_][a-z0-9_]*)\s+(.+)$/i', $line, $field ) ) {
					continue;
				}

				// PRIMARY KEY, UNIQUE KEY, KEY: shaped like a column and not one.
				if ( in_array( strtolower( $field[1] ), array( 'primary', 'unique', 'key', 'index', 'fulltext', 'spatial', 'constraint', 'foreign' ), true ) ) {
					continue;
				}

				$wanted[ $table ][ strtolower( $field[1] ) ] = $line;
			}
		}

		return $wanted;
	}

	/**
	 * The columns a table actually has.
	 *
	 * @param string $table Table name.
	 * @return array<int, string> Lowercased column names; empty when there is no such table.
	 */
	private static function present( string $table ): array {
		global $wpdb;

		$suppress = $wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- table name from the schema above; SHOW COLUMNS takes no placeholders.
		$columns  = $wpdb->get_col( "SHOW COLUMNS FROM {$table}" );
		$wpdb->suppress_errors( $suppress );

		return array_map( 'strtolower', array_map( 'strval', (array) $columns ) );
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
