<?php
/**
 * The letters that go out before Shabbat.
 *
 * Somebody takes something small on — and says how long for. Two Shabbatot, or
 * four, or one. On the Thursday before each of those Shabbatot a letter goes to
 * them; on the Thursday after the last one, a different letter goes, asking
 * whether they would like to take something on again and light another candle.
 *
 * **Until this, nothing sent anything.** The addresses were collected, stored
 * and exportable, and every one of them sat there. This is the part that sends.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Schedules and sends the weekly reminders.
 */
final class MSL_Reminders {

	/**
	 * The recurring job.
	 */
	private const HOOK = 'msl_send_reminders';

	/**
	 * How many letters one run will send.
	 *
	 * A campaign with a quarter of a million participants cannot mail all of
	 * them inside one PHP request, and a run that dies halfway through would
	 * otherwise leave no record of where it got to. Each letter advances its
	 * own row as it is sent, so the next run simply carries on from there.
	 */
	private const BATCH = 200;

	/**
	 * The lengths a person can choose, in Shabbatot.
	 *
	 * @var array<int, int>
	 */
	public const WEEKS = array( 1, 2, 4, 8 );

	/**
	 * Hook the job up.
	 */
	public static function init(): void {
		add_action( self::HOOK, array( self::class, 'run' ) );
		add_action( 'init', array( self::class, 'schedule' ) );

		// A theme that has been switched away from should not keep waking the
		// site up every hour to mail on behalf of a campaign nobody can see.
		add_action( 'switch_theme', array( self::class, 'unschedule' ) );
	}

	/**
	 * Keep the hourly job registered.
	 *
	 * Hourly rather than weekly, and it decides for itself whether anything is
	 * due. WordPress's cron only fires when somebody visits the site, so a
	 * weekly event on a quiet site can be missed by days — and a reminder for
	 * Shabbat that arrives on Sunday is worse than none. Checking every hour and
	 * sending what is due is what makes a late run harmless.
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', self::HOOK );
		}
	}

	/**
	 * Stop the job when the theme is switched away.
	 */
	public static function unschedule(): void {
		$next = wp_next_scheduled( self::HOOK );

		if ( false !== $next ) {
			wp_unschedule_event( $next, self::HOOK );
		}
	}

	/* ---------------------------------------------------------------------
	 * When a letter is due
	 * ------------------------------------------------------------------ */

	/**
	 * The moment the letter for a given Shabbat should go out.
	 *
	 * Thursday, at nine in the morning, in the site's own timezone — which is
	 * what the campaign asked for and also the last day on which "before
	 * Shabbat" still leaves somebody time to do anything about it.
	 *
	 * @param int $shabbat Candle-lighting for the Shabbat in question.
	 * @return int
	 */
	public static function send_time( int $shabbat ): int {
		$zone = wp_timezone();
		$day  = ( new DateTimeImmutable( '@' . $shabbat ) )->setTimezone( $zone );

		// Back up to the Thursday before it. A Shabbat whose candle-lighting is
		// on a Friday is two days back; the modulo keeps this true whatever
		// weekday the campaign has set its fallback to.
		$back = ( (int) $day->format( 'w' ) - 4 + 7 ) % 7;
		$back = 0 === $back ? 7 : $back;

		return $day->modify( '-' . $back . ' days' )->setTime( 9, 0, 0 )->getTimestamp();
	}

	/**
	 * When the first letter for a join is due.
	 *
	 * @param int $campaign_page Campaign page id.
	 * @return int|null Null when the person asked for nothing.
	 */
	public static function first_due( int $campaign_page ): ?int {
		$shabbat = MSL_Theme::candle_lighting( MSL_Meta::get( 'campaign', $campaign_page ) );
		$due     = self::send_time( $shabbat );

		/*
		 * Somebody who lights a candle on Friday afternoon has already missed
		 * this week's Thursday. Their first letter belongs to the Shabbat after
		 * it, not to a moment that has already gone by — otherwise the job
		 * would fire it a second later and the "reminder" would arrive after
		 * the Shabbat it was for.
		 */
		if ( $due <= time() ) {
			$due += WEEK_IN_SECONDS;
		}

		return $due;
	}

	/* ---------------------------------------------------------------------
	 * The run
	 * ------------------------------------------------------------------ */

	/**
	 * Send everything that is due.
	 *
	 * @return array{sent: int, closing: int, skipped: int} What the run did.
	 */
	public static function run(): array {
		global $wpdb;

		$done = array(
			'sent'    => 0,
			'closing' => 0,
			'skipped' => 0,
		);

		if ( ! MSL_DB::ready() ) {
			return $done;
		}

		$table = MSL_DB::joins_table();
		$now   = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, page_id, first_name, reminder_email, reminder_weeks, reminders_sent, remind_at, lang, referral_code
				 FROM {$table}
				 WHERE remind_at IS NOT NULL AND remind_at <= %s AND reminder_email IS NOT NULL
				 ORDER BY remind_at ASC
				 LIMIT %d",
				$now,
				self::BATCH
			),
			ARRAY_A
		);

		foreach ( (array) $rows as $row ) {
			$done = self::one( $row, $done );
		}

		return $done;
	}

	/**
	 * One person's letter.
	 *
	 * @param array<string, mixed>                       $row  Join row.
	 * @param array{sent: int, closing: int, skipped: int} $done Tally so far.
	 * @return array{sent: int, closing: int, skipped: int}
	 */
	private static function one( array $row, array $done ): array {
		$weeks = (int) $row['reminder_weeks'];
		$sent  = (int) $row['reminders_sent'];
		$due   = (int) strtotime( (string) $row['remind_at'] . ' UTC' );

		// Past the closing letter there is nothing left to send, and the row
		// stops being asked about.
		if ( $weeks <= 0 || $sent > $weeks ) {
			self::stop( (int) $row['id'] );
			++$done['skipped'];

			return $done;
		}

		/*
		 * A run that is days late must not post a letter into Shabbat itself.
		 * Past candle-lighting the week is simply given up and the next one
		 * takes its place — the person is not owed two letters because the
		 * server was asleep, and one arriving on Shabbat is the one outcome
		 * this campaign cannot have.
		 */
		if ( time() >= MSL_Theme::candle_lighting( MSL_Meta::get( 'campaign', (int) $row['page_id'] ) ) && time() > $due + 2 * DAY_IN_SECONDS ) {
			self::advance( (int) $row['id'], $sent, $due + WEEK_IN_SECONDS );
			++$done['skipped'];

			return $done;
		}

		$email = MSL_Joins::unprotect( (string) $row['reminder_email'] );

		if ( '' === $email || ! is_email( $email ) ) {
			self::stop( (int) $row['id'] );
			++$done['skipped'];

			return $done;
		}

		$closing = $sent >= $weeks;

		if ( self::send( $email, $row, $closing ) ) {
			++$done[ $closing ? 'closing' : 'sent' ];
		} else {
			++$done['skipped'];
		}

		/*
		 * The row advances whether or not the mail went out. A host that cannot
		 * send would otherwise leave every row due for ever, and the next run —
		 * and the one after that — would try the same few hundred addresses
		 * again instead of ever reaching anybody else.
		 */
		self::advance( (int) $row['id'], $sent + 1, $closing ? null : $due + WEEK_IN_SECONDS );

		return $done;
	}

	/**
	 * Write one letter.
	 *
	 * @param string               $email   Where to.
	 * @param array<string, mixed> $row     Join row.
	 * @param bool                 $closing Is this the one that asks for another?
	 * @return bool
	 */
	private static function send( string $email, array $row, bool $closing ): bool {
		$page = (int) $row['page_id'];
		$copy = MSL_Meta::get( 'join', $page );
		$lang = in_array( (string) $row['lang'], MSL_I18N::LANGS, true ) ? (string) $row['lang'] : 'he';
		$key  = $closing ? 'again' : 'weekly';

		$name = trim( (string) $row['first_name'] );
		$link = home_url( '/' );

		if ( class_exists( 'MSL_Importer' ) ) {
			$id = MSL_Importer::page_id();

			if ( $id > 0 ) {
				$link = (string) get_permalink( $id );
			}
		}

		$subject = (string) ( $copy[ 'mail_' . $key . '_subject_' . $lang ] ?? '' );
		$body    = (string) ( $copy[ 'mail_' . $key . '_body_' . $lang ] ?? '' );

		if ( '' === $subject || '' === $body ) {
			return false;
		}

		/*
		 * Two placeholders, in this order: the name, and the way back. A letter
		 * to somebody who joined without giving a name still has to read as a
		 * sentence, so the greeting falls back to the campaign's own wording
		 * rather than leaving a hole where a name should be.
		 */
		$body = sprintf(
			$body,
			'' !== $name ? $name : (string) ( $copy[ 'mail_friend_' . $lang ] ?? '' ),
			$link
		);

		return (bool) wp_mail( $email, wp_specialchars_decode( $subject, ENT_QUOTES ), $body );
	}

	/* ---------------------------------------------------------------------
	 * Moving a row along
	 * ------------------------------------------------------------------ */

	/**
	 * Record a letter and set the next one.
	 *
	 * @param int      $id   Join row id.
	 * @param int      $sent How many have now gone.
	 * @param int|null $next When the next is due, or null for never again.
	 */
	private static function advance( int $id, int $sent, ?int $next ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			MSL_DB::joins_table(),
			array(
				'reminders_sent' => $sent,
				'remind_at'      => null === $next ? null : gmdate( 'Y-m-d H:i:s', $next ),
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Take a row out of the queue for good.
	 *
	 * @param int $id Join row id.
	 */
	private static function stop( int $id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			MSL_DB::joins_table(),
			array( 'remind_at' => null ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/* ---------------------------------------------------------------------
	 * What the dashboard shows
	 * ------------------------------------------------------------------ */

	/**
	 * The state of the queue, for the panel.
	 *
	 * The whole feature fails silently by its nature — a letter that is never
	 * written looks exactly like a letter nobody replied to. So the panel is
	 * told the numbers instead of being left to assume them.
	 *
	 * @param int $page_id Campaign page.
	 * @return array{waiting: int, due: int, finished: int, next_run: int}
	 */
	public static function status( int $page_id ): array {
		global $wpdb;

		$out = array(
			'waiting'  => 0,
			'due'      => 0,
			'finished' => 0,
			'next_run' => (int) ( wp_next_scheduled( self::HOOK ) ?: 0 ),
		);

		if ( ! MSL_DB::ready() ) {
			return $out;
		}

		$table = MSL_DB::joins_table();
		$now   = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out['waiting'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE page_id = %d AND remind_at IS NOT NULL", $page_id ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out['due'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE page_id = %d AND remind_at IS NOT NULL AND remind_at <= %s", $page_id, $now ) );
		/*
		 * Finished means letters were actually written, not merely that nothing
		 * is due. Somebody who chose two Shabbatot and left no address has
		 * nothing due either, and counting them as finished told the panel that
		 * people had been written to who never had been.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out['finished'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE page_id = %d AND reminders_sent > 0 AND remind_at IS NULL", $page_id ) );

		return $out;
	}
}
