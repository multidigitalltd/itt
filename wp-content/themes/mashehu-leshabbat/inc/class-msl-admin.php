<?php
/**
 * The admin screens.
 *
 * Three things the client actually needs on a Friday: how the campaign is doing,
 * a queue for approving dedications, and an export. Every list paginates with
 * LIMIT/OFFSET against an indexed column — a table with a quarter of a million
 * rows will hang any unbounded query — and the export streams rather than
 * building the whole file in memory.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Admin menu, overview, moderation and export.
 */
final class MSL_Admin {

	/**
	 * Capability required for every screen here.
	 */
	private const CAP = 'manage_options';

	/**
	 * Rows per page in the list views.
	 */
	private const PER_PAGE = 50;

	/**
	 * Hook the admin.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_msl_moderate', array( self::class, 'handle_moderation' ) );
		add_action( 'admin_post_msl_export', array( self::class, 'handle_export' ) );
	}

	/**
	 * Register the menu.
	 */
	public static function menu(): void {
		add_menu_page(
			__( 'משהו לשבת', 'mashehu-leshabbat' ),
			__( 'משהו לשבת', 'mashehu-leshabbat' ),
			self::CAP,
			'msl-overview',
			array( self::class, 'render_overview' ),
			'dashicons-visibility',
			26
		);

		add_submenu_page(
			'msl-overview',
			__( 'סקירה', 'mashehu-leshabbat' ),
			__( 'סקירה', 'mashehu-leshabbat' ),
			self::CAP,
			'msl-overview',
			array( self::class, 'render_overview' )
		);

		add_submenu_page(
			'msl-overview',
			__( 'הצטרפויות', 'mashehu-leshabbat' ),
			__( 'הצטרפויות', 'mashehu-leshabbat' ),
			self::CAP,
			'msl-joins',
			array( self::class, 'render_joins' )
		);

		add_submenu_page(
			'msl-overview',
			__( 'הקדשות לאישור', 'mashehu-leshabbat' ),
			self::moderation_label(),
			self::CAP,
			'msl-moderation',
			array( self::class, 'render_moderation' )
		);
	}

	/**
	 * The moderation menu label, carrying the pending count as a bubble.
	 *
	 * @return string
	 */
	private static function moderation_label(): string {
		$pending = self::pending_count();

		if ( 0 === $pending ) {
			return __( 'הקדשות לאישור', 'mashehu-leshabbat' );
		}

		return sprintf(
			'%s <span class="awaiting-mod"><span class="pending-count">%d</span></span>',
			__( 'הקדשות לאישור', 'mashehu-leshabbat' ),
			$pending
		);
	}

	/**
	 * How many dedications are waiting for review.
	 *
	 * @return int
	 */
	private static function pending_count(): int {
		global $wpdb;

		if ( ! MSL_DB::ready() ) {
			return 0;
		}

		$table = MSL_DB::dedications_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", 'pending' ) );
	}

	/* ---------------------------------------------------------------------
	 * Overview
	 * ------------------------------------------------------------------ */

	/**
	 * Render the live overview.
	 */
	public static function render_overview(): void {
		self::guard();

		$page_id = MSL_Importer::page_id();
		$stats   = MSL_Stats::all( $page_id );
		$cards   = array(
			__( 'משתתפים', 'mashehu-leshabbat' )        => msl_num( $stats['participants'] ),
			__( 'הצטרפויות באתר', 'mashehu-leshabbat' ) => msl_num( $stats['joins'] ),
			__( 'מדינות', 'mashehu-leshabbat' )         => msl_num( $stats['countries'] ),
			__( 'ערים', 'mashehu-leshabbat' )           => msl_num( $stats['cities'] ),
			__( 'הקדשות מאושרות', 'mashehu-leshabbat' ) => msl_num( $stats['dedications'] ),
			__( 'אחוז השלמה', 'mashehu-leshabbat' )     => $stats['pct'] . '%',
			__( 'ב-10 הדקות האחרונות', 'mashehu-leshabbat' ) => msl_num( $stats['last10'] ),
		);
		?>
		<div class="wrap msl-admin">
			<h1><?php esc_html_e( 'משהו לשבת — סקירה', 'mashehu-leshabbat' ); ?></h1>

			<?php if ( 0 === $page_id ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'עמוד הקמפיין לא נמצא. אפשר ליצור אותו מחדש ב"כלים ← עמודי משהו לשבת".', 'mashehu-leshabbat' ); ?></p></div>
			<?php endif; ?>

			<div class="msl-admin__cards">
				<?php foreach ( $cards as $label => $value ) : ?>
					<div class="msl-admin__card">
						<strong><?php echo esc_html( (string) $value ); ?></strong>
						<span><?php echo esc_html( (string) $label ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>

			<p>
				<?php if ( 0 !== $page_id ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( (string) get_edit_post_link( $page_id ) ); ?>">
						<?php esc_html_e( 'עריכת תוכן הקמפיין', 'mashehu-leshabbat' ); ?>
					</a>
				<?php endif; ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=msl-joins' ) ); ?>">
					<?php esc_html_e( 'רשימת ההצטרפויות', 'mashehu-leshabbat' ); ?>
				</a>
			</p>

			<h2><?php esc_html_e( 'התפלגות לפי מדינה', 'mashehu-leshabbat' ); ?></h2>
			<?php self::render_breakdown( $page_id, 'country' ); ?>

			<h2><?php esc_html_e( 'התפלגות לפי עיר', 'mashehu-leshabbat' ); ?></h2>
			<?php self::render_breakdown( $page_id, 'city' ); ?>
		</div>
		<?php
	}

	/**
	 * A top-20 breakdown table.
	 *
	 * @param int    $page_id Page ID.
	 * @param string $column  Indexed column to group by: 'country' or 'city'.
	 */
	private static function render_breakdown( int $page_id, string $column ): void {
		global $wpdb;

		if ( ! MSL_DB::ready() || ! in_array( $column, array( 'country', 'city' ), true ) ) {
			return;
		}

		$table = MSL_DB::joins_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $column is checked against a literal allowlist above.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT {$column} AS label, COUNT(*) AS n FROM {$table}
				 WHERE page_id = %d AND {$column} <> ''
				 GROUP BY {$column} ORDER BY n DESC LIMIT 20",
				$page_id
			),
			ARRAY_A
		);

		if ( array() === (array) $rows ) {
			echo '<p>' . esc_html__( 'אין עדיין נתונים.', 'mashehu-leshabbat' ) . '</p>';

			return;
		}
		?>
		<table class="widefat striped">
			<tbody>
			<?php foreach ( (array) $rows as $row ) : ?>
				<tr>
					<td><?php echo esc_html( (string) $row['label'] ); ?></td>
					<td style="width:8rem"><?php echo esc_html( msl_num( (int) $row['n'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Joins
	 * ------------------------------------------------------------------ */

	/**
	 * Render the paginated join list.
	 */
	public static function render_joins(): void {
		global $wpdb;

		self::guard();

		$page_id = MSL_Importer::page_id();
		$paged   = max( 1, (int) ( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$offset  = ( $paged - 1 ) * self::PER_PAGE;
		$rows    = array();
		$total   = 0;

		if ( MSL_DB::ready() ) {
			$table = MSL_DB::joins_table();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE page_id = %d", $page_id ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT piece_index, first_name, city, country, is_anonymous, lang, referral_code, referred_by, reminder_optin, created_at
					 FROM {$table} WHERE page_id = %d ORDER BY id DESC LIMIT %d OFFSET %d",
					$page_id,
					self::PER_PAGE,
					$offset
				),
				ARRAY_A
			);
		}

		$pages = (int) ceil( $total / self::PER_PAGE );
		?>
		<div class="wrap msl-admin">
			<h1><?php esc_html_e( 'הצטרפויות', 'mashehu-leshabbat' ); ?></h1>

			<p>
				<?php
				printf(
					/* translators: %s: number of joins. */
					esc_html__( 'סך הכול %s הצטרפויות שנרשמו באתר.', 'mashehu-leshabbat' ),
					esc_html( msl_num( $total ) )
				);
				?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'msl_export' ); ?>
				<input type="hidden" name="action" value="msl_export">
				<p>
					<button type="submit" class="button"><?php esc_html_e( 'ייצוא CSV', 'mashehu-leshabbat' ); ?></button>
					<span class="description"><?php esc_html_e( 'הייצוא כולל שם ועיר רק של מי שלא ביקש עילום שם, ולעולם לא טלפון, מייל או כתובת IP.', 'mashehu-leshabbat' ); ?></span>
				</p>
			</form>

			<table class="widefat striped">
				<thead>
				<tr>
					<th><?php esc_html_e( 'מיקום ביצירה', 'mashehu-leshabbat' ); ?></th>
					<th><?php esc_html_e( 'שם', 'mashehu-leshabbat' ); ?></th>
					<th><?php esc_html_e( 'עיר', 'mashehu-leshabbat' ); ?></th>
					<th><?php esc_html_e( 'מדינה', 'mashehu-leshabbat' ); ?></th>
					<th><?php esc_html_e( 'שפה', 'mashehu-leshabbat' ); ?></th>
					<th><?php esc_html_e( 'הוזמן על ידי', 'mashehu-leshabbat' ); ?></th>
					<th><?php esc_html_e( 'תזכורת', 'mashehu-leshabbat' ); ?></th>
					<th><?php esc_html_e( 'מועד', 'mashehu-leshabbat' ); ?></th>
				</tr>
				</thead>
				<tbody>
				<?php if ( array() === $rows ) : ?>
					<tr><td colspan="8"><?php esc_html_e( 'אין עדיין הצטרפויות.', 'mashehu-leshabbat' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $row ) : ?>
					<?php $anon = 1 === (int) $row['is_anonymous']; ?>
					<tr>
						<td><?php echo esc_html( msl_num( (int) $row['piece_index'] ) ); ?></td>
						<td><?php echo esc_html( $anon ? __( 'בעילום שם', 'mashehu-leshabbat' ) : (string) $row['first_name'] ); ?></td>
						<td><?php echo esc_html( (string) $row['city'] ); ?></td>
						<td><?php echo esc_html( (string) $row['country'] ); ?></td>
						<td><?php echo esc_html( (string) $row['lang'] ); ?></td>
						<td><?php echo esc_html( '' !== (string) $row['referred_by'] ? (string) $row['referred_by'] : '—' ); ?></td>
						<td><?php echo esc_html( 1 === (int) $row['reminder_optin'] ? __( 'כן', 'mashehu-leshabbat' ) : '—' ); ?></td>
						<td><?php echo esc_html( (string) $row['created_at'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $pages > 1 ) : ?>
				<div class="tablenav"><div class="tablenav-pages">
					<?php
					echo wp_kses_post(
						(string) paginate_links(
							array(
								'base'      => add_query_arg( 'paged', '%#%' ),
								'format'    => '',
								'current'   => $paged,
								'total'     => $pages,
								'prev_text' => '‹',
								'next_text' => '›',
							)
						)
					);
					?>
				</div></div>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Moderation
	 * ------------------------------------------------------------------ */

	/**
	 * Render the dedication queue.
	 */
	public static function render_moderation(): void {
		global $wpdb;

		self::guard();

		$page_id = MSL_Importer::page_id();
		$types   = array_values( (array) ( MSL_Meta::get( 'join', $page_id )['ded_types'] ?? array() ) );
		$rows    = array();

		if ( MSL_DB::ready() ) {
			$deds  = MSL_DB::dedications_table();
			$joins = MSL_DB::joins_table();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT d.id, d.kind, d.body, d.status, d.created_at, j.first_name, j.city, j.is_anonymous
					 FROM {$deds} d
					 LEFT JOIN {$joins} j ON j.id = d.join_id
					 WHERE d.page_id = %d AND d.status = %s
					 ORDER BY d.id ASC LIMIT %d",
					$page_id,
					'pending',
					self::PER_PAGE
				),
				ARRAY_A
			);
		}
		?>
		<div class="wrap msl-admin">
			<h1><?php esc_html_e( 'הקדשות לאישור', 'mashehu-leshabbat' ); ?></h1>

			<p><?php esc_html_e( 'הקדשה לא מוצגת באתר לפני אישור. דחייה משאירה את ההצטרפות עצמה על כנה — רק ההקדשה לא תוצג.', 'mashehu-leshabbat' ); ?></p>

			<?php if ( array() === $rows ) : ?>
				<p><strong><?php esc_html_e( 'אין הקדשות שממתינות לאישור.', 'mashehu-leshabbat' ); ?></strong></p>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
					<tr>
						<th><?php esc_html_e( 'סוג', 'mashehu-leshabbat' ); ?></th>
						<th><?php esc_html_e( 'ההקדשה', 'mashehu-leshabbat' ); ?></th>
						<th><?php esc_html_e( 'מי הוסיף', 'mashehu-leshabbat' ); ?></th>
						<th><?php esc_html_e( 'מועד', 'mashehu-leshabbat' ); ?></th>
						<th><?php esc_html_e( 'פעולה', 'mashehu-leshabbat' ); ?></th>
					</tr>
					</thead>
					<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<?php
						$kind = $types[ (int) $row['kind'] ] ?? array();
						$who  = 1 === (int) $row['is_anonymous']
							? __( 'בעילום שם', 'mashehu-leshabbat' )
							: trim( (string) $row['first_name'] . ' · ' . (string) $row['city'], ' ·' );
						?>
						<tr>
							<td><?php echo esc_html( is_array( $kind ) ? MSL_I18N::value( $kind, 'label' ) : '—' ); ?></td>
							<td><?php echo esc_html( (string) $row['body'] ); ?></td>
							<td><?php echo esc_html( $who ); ?></td>
							<td><?php echo esc_html( (string) $row['created_at'] ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
									<?php wp_nonce_field( 'msl_moderate' ); ?>
									<input type="hidden" name="action" value="msl_moderate">
									<input type="hidden" name="id" value="<?php echo absint( $row['id'] ); ?>">
									<button type="submit" name="decision" value="approved" class="button button-primary"><?php esc_html_e( 'אישור', 'mashehu-leshabbat' ); ?></button>
									<button type="submit" name="decision" value="rejected" class="button"><?php esc_html_e( 'דחייה', 'mashehu-leshabbat' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Record a moderation decision.
	 */
	public static function handle_moderation(): void {
		global $wpdb;

		self::guard();
		check_admin_referer( 'msl_moderate' );

		$id       = absint( $_POST['id'] ?? 0 );
		$decision = sanitize_key( wp_unslash( (string) ( $_POST['decision'] ?? '' ) ) );

		if ( $id > 0 && in_array( $decision, array( 'approved', 'rejected' ), true ) && MSL_DB::ready() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				MSL_DB::dedications_table(),
				array(
					'status'      => $decision,
					'reviewed_by' => get_current_user_id(),
					'reviewed_at' => current_time( 'mysql', true ),
				),
				array( 'id' => $id ),
				array( '%s', '%d', '%s' ),
				array( '%d' )
			);

			MSL_Stats::flush( MSL_Importer::page_id() );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=msl-moderation' ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Export
	 * ------------------------------------------------------------------ */

	/**
	 * Stream the consented rows as CSV.
	 *
	 * Chunked deliberately: a full in-memory query over a campaign table would
	 * exhaust the memory limit long before it finished.
	 */
	public static function handle_export(): void {
		global $wpdb;

		self::guard();
		check_admin_referer( 'msl_export' );

		if ( ! MSL_DB::ready() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=msl-joins' ) );
			exit;
		}

		$page_id = MSL_Importer::page_id();
		$table   = MSL_DB::joins_table();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=mashehu-leshabbat-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );

		if ( false === $out ) {
			exit;
		}

		// A BOM, so Excel opens the Hebrew columns as UTF-8 rather than as mojibake.
		fwrite( $out, "\xEF\xBB\xBF" );

		/*
		 * The escape character is passed explicitly, and empty. PHP 8.4 deprecates
		 * relying on the default, and the deprecation notice is printed straight
		 * into the response — that is, into the middle of the CSV the client just
		 * downloaded. Empty is also the correct answer on its own: PHP's backslash
		 * escaping is not part of RFC 4180 and confuses every spreadsheet that
		 * meets it.
		 */
		fputcsv( $out, array( 'piece_index', 'first_name', 'city', 'country', 'lang', 'referral_code', 'referred_by', 'created_at' ), ',', '"', '' );

		$offset = 0;

		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = (array) $wpdb->get_results(
				$wpdb->prepare(
					"SELECT piece_index, first_name, city, country, is_anonymous, lang, referral_code, referred_by, created_at
					 FROM {$table} WHERE page_id = %d ORDER BY id ASC LIMIT 500 OFFSET %d",
					$page_id,
					$offset
				),
				ARRAY_A
			);

			foreach ( $rows as $row ) {
				$anonymous = 1 === (int) $row['is_anonymous'];

				fputcsv(
					$out,
					array(
						$row['piece_index'],
						$anonymous ? '' : $row['first_name'],
						$anonymous ? '' : $row['city'],
						$row['country'],
						$row['lang'],
						$row['referral_code'],
						$row['referred_by'],
						$row['created_at'],
					),
					',',
					'"',
					''
				);
			}

			$offset += 500;
		} while ( count( $rows ) === 500 );

		fclose( $out );
		exit;
	}

	/**
	 * Refuse anyone without the capability.
	 */
	private static function guard(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'אין לך הרשאה לגשת למסך הזה.', 'mashehu-leshabbat' ) );
		}
	}
}
