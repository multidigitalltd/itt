<?php
/**
 * Lead capture: storage, REST endpoint and admin listing.
 *
 * The form posts to a REST route rather than to the page itself, so the landing
 * page stays fully cacheable by LiteSpeed / Cloudflare: nothing per-visitor is
 * ever printed into the HTML.
 *
 * The route carries no WordPress nonce at all, and the browser posts with
 * credentials omitted. A nonce here bought nothing — the endpoint is public by
 * design, so anyone who wanted one could fetch it — while costing real leads:
 * with an auth cookie present and a nonce that a page cache or a switched
 * session had made stale, WordPress rejected the request with "Cookie check
 * failed" before this class ever saw it. Abuse is handled by measures that do
 * not depend on session state: a honeypot, a minimum fill time, a per-IP rate
 * limit, and Cloudflare Turnstile when its keys are configured.
 *
 * @package ITT_Landing
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Handles submissions from the "בדיקת התאמה" form.
 */
final class ITT_Leads {

	/**
	 * REST namespace used by the landing form.
	 */
	public const REST_NAMESPACE = 'itt/v1';

	/**
	 * Custom post type storing the leads.
	 */
	public const POST_TYPE = 'itt_lead';

	/**
	 * Maximum submissions accepted from one IP within the window.
	 */
	private const RATE_LIMIT = 5;

	/**
	 * Rate-limit window, in seconds.
	 */
	private const RATE_WINDOW = HOUR_IN_SECONDS;

	/**
	 * Minimum seconds between rendering the form and submitting it.
	 */
	private const MIN_FILL_SECONDS = 3;

	/**
	 * Hook the lead pipeline.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'register_post_type' ) );
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
		add_action( 'after_switch_theme', array( self::class, 'grant_caps' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( self::class, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( self::class, 'column' ), 10, 2 );
		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( self::class, 'register_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( self::class, 'save_meta' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( self::class, 'status_filter' ) );
		add_action( 'pre_get_posts', array( self::class, 'apply_status_filter' ) );
	}

	/**
	 * Register the lead post type.
	 *
	 * Leads are private, not public, not searchable and not exported to REST;
	 * they exist only so the office can read them in wp-admin.
	 */
	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'פניות ITT', 'itt-landing' ),
					'singular_name' => __( 'פנייה', 'itt-landing' ),
					'menu_name'     => __( 'פניות ITT', 'itt-landing' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'menu_icon'           => 'dashicons-phone',
				'menu_position'       => 26,
				'supports'            => array( 'title' ),
				'capability_type'     => array( 'itt_lead', 'itt_leads' ),
				'map_meta_cap'        => true,
				'capabilities'        => array(
					'create_posts' => 'do_not_allow',
				),
			)
		);
	}

	/**
	 * Give administrators access to the leads screen.
	 *
	 * Granted per capability rather than per role check, so the client can move
	 * the capability to an office role later without touching the code.
	 */
	public static function grant_caps(): void {
		$role = get_role( 'administrator' );

		if ( ! $role instanceof WP_Role ) {
			return;
		}

		foreach ( array( 'edit_itt_leads', 'edit_others_itt_leads', 'edit_private_itt_leads', 'edit_published_itt_leads', 'read_private_itt_leads', 'delete_itt_leads', 'delete_others_itt_leads', 'delete_private_itt_leads', 'delete_published_itt_leads' ) as $cap ) {
			$role->add_cap( $cap );
		}
	}

	/**
	 * Register the public submission route.
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/lead',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'submit' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'name'    => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'phone'   => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'email'   => array(
						'required'          => false,
						'type'              => 'string',
						// Sanitised to text here on purpose: sanitize_email() would
						// silently turn a typo into an empty string and the visitor
						// would never learn their address was dropped. It is
						// validated below and only stored once it is a real address.
						'sanitize_callback' => 'sanitize_text_field',
					),
					'message' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'consent' => array(
						'required'          => false,
						'type'              => 'boolean',
						'sanitize_callback' => static fn( $value ): bool => (bool) $value,
					),
					'hp'      => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'ts'        => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'turnstile' => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'page'      => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Ask Cloudflare whether a Turnstile token is genuine.
	 *
	 * Returns true when Turnstile is not configured, so a site without keys
	 * behaves exactly as it did before. A network failure talking to Cloudflare
	 * is treated as a pass rather than a block: losing a real lead because
	 * their API was briefly unreachable costs more than the spam it would have
	 * stopped, and the honeypot, fill-time and rate limit still apply.
	 *
	 * @param string $token Token submitted by the browser.
	 * @param string $ip    Visitor IP, may be empty.
	 * @return bool
	 */
	private static function turnstile_passes( string $token, string $ip ): bool {
		if ( ! ITT_Settings::turnstile_enabled() ) {
			return true;
		}

		if ( '' === $token ) {
			return false;
		}

		$body = array(
			'secret'   => ITT_Settings::get( 'turnstile_secret_key' ),
			'response' => $token,
		);

		if ( '' !== $ip ) {
			$body['remoteip'] = $ip;
		}

		$response = wp_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			array(
				'timeout' => 8,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return true;
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		return is_array( $data ) && ! empty( $data['success'] );
	}

	/**
	 * Store a submitted lead.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function submit( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		// Which landing page this came from, so the men's form can send men to
		// the men's thank-you page.
		$landing_id = absint( $request->get_param( 'page' ) );

		// Silently accept and drop bot submissions: an honest error message would
		// only tell the bot how to try again.
		if ( '' !== (string) $request->get_param( 'hp' ) ) {
			return self::success( $landing_id );
		}

		$ts = absint( $request->get_param( 'ts' ) );

		if ( $ts > 0 && ( time() - $ts ) < self::MIN_FILL_SECONDS ) {
			return self::success( $landing_id );
		}

		if ( ! self::turnstile_passes( (string) $request->get_param( 'turnstile' ), self::client_ip() ) ) {
			return new WP_Error(
				'itt_turnstile_failed',
				__( 'אימות האבטחה נכשל. אפשר לרענן את העמוד ולנסות שוב, או לכתוב לנו בוואטסאפ.', 'itt-landing' ),
				array(
					'status' => 400,
					'fields' => array( 'turnstile' ),
				)
			);
		}

		if ( ! self::within_rate_limit( false ) ) {
			return new WP_Error(
				'itt_rate_limited',
				__( 'קיבלנו כמה פניות מהמכשיר הזה. אפשר לנסות שוב מאוחר יותר או לכתוב לנו בוואטסאפ.', 'itt-landing' ),
				array( 'status' => 429 )
			);
		}

		$name  = trim( (string) $request->get_param( 'name' ) );
		$phone = trim( (string) $request->get_param( 'phone' ) );
		$email = trim( (string) $request->get_param( 'email' ) );

		$errors = self::validate( $name, $phone, $email, (bool) $request->get_param( 'consent' ) );

		if ( array() !== $errors ) {
			return new WP_Error(
				'itt_invalid',
				implode( ' ', $errors ),
				array(
					'status' => 400,
					'fields' => array_keys( $errors ),
				)
			);
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'private',
				'post_title'  => $name,
				'meta_input'  => array(
					'_itt_phone'   => $phone,
					'_itt_email'   => sanitize_email( $email ),
					'_itt_message' => (string) $request->get_param( 'message' ),
					'_itt_source'  => esc_url_raw( (string) $request->get_header( 'referer' ) ),
					// Proof of consent, kept with the lead for the privacy record.
					'_itt_consent' => 1,
					'_itt_consent_at' => gmdate( 'c' ),
				),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return new WP_Error(
				'itt_store_failed',
				__( 'השליחה נכשלה. אפשר לנסות שוב או ליצור קשר בוואטסאפ.', 'itt-landing' ),
				array( 'status' => 500 )
			);
		}

		// The budget is spent only on a lead that was actually stored, so a
		// visitor who mistypes their phone a few times is never locked out.
		self::within_rate_limit( true );

		self::notify( $name, $phone, $email, (string) $request->get_param( 'message' ) );

		return self::success( $landing_id );
	}

	/**
	 * Validate the submitted values server-side.
	 *
	 * The browser runs the same checks for a faster response, but they are never
	 * trusted: this is the check that counts.
	 *
	 * @param string $name    Full name.
	 * @param string $phone   Phone number.
	 * @param string $email   Email address, optional.
	 * @param bool   $consent Whether the terms box was ticked.
	 * @return array<string, string> Field key => message.
	 */
	private static function validate( string $name, string $phone, string $email, bool $consent ): array {
		$errors = array();

		if ( mb_strlen( $name ) < 2 ) {
			$errors['name'] = __( 'נא למלא שם מלא.', 'itt-landing' );
		}

		if ( strlen( (string) preg_replace( '/\D/', '', $phone ) ) < 9 ) {
			$errors['phone'] = __( 'נא למלא מספר טלפון תקין.', 'itt-landing' );
		}

		if ( '' !== $email && ! is_email( $email ) ) {
			$errors['email'] = __( 'נא למלא כתובת אימייל תקינה.', 'itt-landing' );
		}

		// Consent is checked here and not only in the browser: a submission that
		// skips the box must not become a stored lead under any circumstances.
		if ( ! $consent ) {
			$errors['consent'] = __( 'יש לאשר את תנאי השימוש ומדיניות הפרטיות.', 'itt-landing' );
		}

		return $errors;
	}

	/**
	 * The response returned for every accepted submission.
	 *
	 * @return WP_REST_Response
	 */
	private static function success( int $landing_id = 0 ): WP_REST_Response {
		$thank_you = ITT_Importer::thank_you_for( $landing_id );

		return new WP_REST_Response(
			array(
				'ok'       => true,
				'redirect' => $thank_you > 0 ? get_permalink( $thank_you ) : '',
			)
		);
	}

	/**
	 * The visitor's IP address, or an empty string when it cannot be read.
	 *
	 * @return string
	 */
	private static function client_ip(): string {
		return isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) )
			: '';
	}

	/**
	 * Whether this client is still inside the submission budget.
	 *
	 * The IP is hashed before it becomes part of a transient key, so no raw
	 * address is written to the options table.
	 *
	 * @param bool $consume Whether to count this call against the budget.
	 * @return bool True while the client may still submit.
	 */
	private static function within_rate_limit( bool $consume ): bool {
		$ip = self::client_ip();

		if ( '' === $ip ) {
			return true;
		}

		$key   = 'itt_rl_' . hash( 'sha256', $ip . wp_salt() );
		$count = (int) get_transient( $key );

		if ( $count >= self::RATE_LIMIT ) {
			return false;
		}

		if ( $consume ) {
			set_transient( $key, $count + 1, self::RATE_WINDOW );
		}

		return true;
	}

	/**
	 * Email the office about a new lead.
	 *
	 * @param string $name    Full name.
	 * @param string $phone   Phone number.
	 * @param string $email   Email address.
	 * @param string $message Free-text message.
	 */
	private static function notify( string $name, string $phone, string $email, string $message ): void {
		$to = apply_filters( 'itt_lead_notification_email', get_option( 'admin_email' ) );

		if ( ! is_string( $to ) || ! is_email( $to ) ) {
			return;
		}

		$lines = array(
			__( 'התקבלה פנייה חדשה מדף הנחיתה של ITT Leader.', 'itt-landing' ),
			'',
			__( 'שם:', 'itt-landing' ) . ' ' . $name,
			__( 'טלפון:', 'itt-landing' ) . ' ' . $phone,
			__( 'אימייל:', 'itt-landing' ) . ' ' . ( '' !== $email ? $email : '—' ),
		);

		if ( '' !== $message ) {
			$lines[] = __( 'הודעה:', 'itt-landing' ) . ' ' . $message;
		}

		wp_mail(
			$to,
			sprintf(
				/* translators: %s: lead full name. */
				__( 'פנייה חדשה מדף ITT Leader — %s', 'itt-landing' ),
				$name
			),
			implode( "\n", $lines )
		);
	}

	/**
	 * The handling stages a lead moves through.
	 *
	 * Deliberately short. A stage list long enough to describe every case is a
	 * list nobody keeps up to date, and this is a follow-up tracker rather than
	 * a sales pipeline.
	 *
	 * @return array<string, string>
	 */
	public static function statuses(): array {
		return array(
			'new'       => __( 'חדש', 'itt-landing' ),
			'contacted' => __( 'יצרנו קשר', 'itt-landing' ),
			'scheduled' => __( 'נקבעה שיחת התאמה', 'itt-landing' ),
			'enrolled'  => __( 'נרשם/ה', 'itt-landing' ),
			'declined'  => __( 'לא רלוונטי', 'itt-landing' ),
		);
	}

	/**
	 * Add the handling, phone and email columns to the leads list.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public static function columns( array $columns ): array {
		$date = $columns['date'] ?? '';
		unset( $columns['date'] );

		$columns['itt_phone']  = __( 'טלפון', 'itt-landing' );
		$columns['itt_email']  = __( 'אימייל', 'itt-landing' );
		$columns['itt_status'] = __( 'טיפול', 'itt-landing' );

		if ( '' !== $date ) {
			$columns['date'] = $date;
		}

		return $columns;
	}

	/**
	 * Render a custom column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Lead ID.
	 */
	public static function column( string $column, int $post_id ): void {
		if ( 'itt_status' === $column ) {
			$statuses = self::statuses();
			$current  = (string) get_post_meta( $post_id, '_itt_status', true );
			$current  = isset( $statuses[ $current ] ) ? $current : 'new';

			printf(
				'<span class="itt-lead-status itt-lead-status--%s">%s</span>',
				esc_attr( $current ),
				esc_html( $statuses[ $current ] )
			);

			return;
		}

		if ( ! in_array( $column, array( 'itt_phone', 'itt_email' ), true ) ) {
			return;
		}

		$value = (string) get_post_meta( $post_id, '_itt_' . str_replace( 'itt_', '', $column ), true );

		echo '' !== $value ? esc_html( $value ) : '—';
	}

	/**
	 * A dropdown above the leads list for filtering by handling stage.
	 *
	 * @param string $post_type Post type of the current list table.
	 */
	public static function status_filter( string $post_type ): void {
		if ( self::POST_TYPE !== $post_type ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		$selected = isset( $_GET['itt_status'] ) ? sanitize_key( wp_unslash( (string) $_GET['itt_status'] ) ) : '';

		echo '<select name="itt_status"><option value="">' . esc_html__( 'כל שלבי הטיפול', 'itt-landing' ) . '</option>';

		foreach ( self::statuses() as $key => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $key ),
				selected( $selected, $key, false ),
				esc_html( $label )
			);
		}

		echo '</select>';
	}

	/**
	 * Apply the handling-stage filter to the leads list query.
	 *
	 * Leads saved before the field existed have no _itt_status row, so "חדש"
	 * also matches a missing value rather than hiding the oldest leads.
	 *
	 * @param WP_Query $query Current query.
	 */
	public static function apply_status_filter( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || self::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		$status = isset( $_GET['itt_status'] ) ? sanitize_key( wp_unslash( (string) $_GET['itt_status'] ) ) : '';

		if ( '' === $status || ! isset( self::statuses()[ $status ] ) ) {
			return;
		}

		$clause = array(
			'key'   => '_itt_status',
			'value' => $status,
		);

		$query->set(
			'meta_query',
			'new' === $status
				? array(
					'relation' => 'OR',
					$clause,
					array(
						'key'     => '_itt_status',
						'compare' => 'NOT EXISTS',
					),
				)
				: array( $clause )
		);
	}

	/**
	 * Register the lead detail and handling boxes.
	 */
	public static function register_meta_boxes(): void {
		add_meta_box(
			'itt-lead-details',
			__( 'פרטי הפנייה', 'itt-landing' ),
			array( self::class, 'render_details' ),
			self::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'itt-lead-handling',
			__( 'טיפול בפנייה', 'itt-landing' ),
			array( self::class, 'render_handling' ),
			self::POST_TYPE,
			'side',
			'high'
		);
	}

	/**
	 * Show everything the visitor submitted.
	 *
	 * The lead post type only supports a title, so without this box the editor
	 * screen showed the name and nothing else — the phone number, the message
	 * and the consent record were all stored but invisible.
	 *
	 * @param WP_Post $post Lead being viewed.
	 */
	public static function render_details( WP_Post $post ): void {
		$phone = (string) get_post_meta( $post->ID, '_itt_phone', true );
		$email = (string) get_post_meta( $post->ID, '_itt_email', true );

		$rows = array(
			__( 'שם מלא', 'itt-landing' ) => esc_html( $post->post_title ),
			__( 'טלפון', 'itt-landing' )  => '' !== $phone
				? '<a href="' . esc_url( 'tel:' . preg_replace( '/[^\d+]/', '', $phone ) ) . '" dir="ltr">' . esc_html( $phone ) . '</a>'
				: '—',
			__( 'אימייל', 'itt-landing' ) => '' !== $email
				? '<a href="' . esc_url( 'mailto:' . $email ) . '" dir="ltr">' . esc_html( $email ) . '</a>'
				: '—',
			__( 'הודעה', 'itt-landing' )  => nl2br( esc_html( (string) get_post_meta( $post->ID, '_itt_message', true ) ) ) ?: '—',
			__( 'נשלח בתאריך', 'itt-landing' ) => esc_html( (string) get_the_date( 'j בF Y, H:i', $post ) ),
			__( 'נשלח מהעמוד', 'itt-landing' ) => self::source_link( (string) get_post_meta( $post->ID, '_itt_source', true ) ),
			__( 'אישור תנאים ופרטיות', 'itt-landing' ) => self::consent_line( $post->ID ),
		);

		echo '<table class="widefat striped"><tbody>';

		foreach ( $rows as $label => $value ) {
			printf(
				'<tr><th scope="row" style="width:200px">%s</th><td>%s</td></tr>',
				esc_html( (string) $label ),
				wp_kses_post( (string) $value )
			);
		}

		echo '</tbody></table>';
	}

	/**
	 * The consent record, as stored with the lead.
	 *
	 * @param int $post_id Lead ID.
	 * @return string HTML.
	 */
	private static function consent_line( int $post_id ): string {
		if ( empty( get_post_meta( $post_id, '_itt_consent', true ) ) ) {
			return esc_html__( 'לא נרשם אישור', 'itt-landing' );
		}

		$at = (string) get_post_meta( $post_id, '_itt_consent_at', true );

		if ( '' === $at ) {
			return esc_html__( 'אושר', 'itt-landing' );
		}

		return sprintf(
			/* translators: %s: date and time the consent was given. */
			esc_html__( 'אושר · %s', 'itt-landing' ),
			esc_html( (string) mysql2date( 'j בF Y, H:i', get_date_from_gmt( gmdate( 'Y-m-d H:i:s', (int) strtotime( $at ) ) ) ) )
		);
	}

	/**
	 * The page the lead was submitted from, as a link.
	 *
	 * @param string $source Stored referer.
	 * @return string HTML.
	 */
	private static function source_link( string $source ): string {
		if ( '' === $source ) {
			return '—';
		}

		return '<a href="' . esc_url( $source ) . '" rel="noopener">' . esc_html( $source ) . '</a>';
	}

	/**
	 * The handling box: stage plus a free-text follow-up note.
	 *
	 * @param WP_Post $post Lead being edited.
	 */
	public static function render_handling( WP_Post $post ): void {
		$statuses = self::statuses();
		$current  = (string) get_post_meta( $post->ID, '_itt_status', true );
		$current  = isset( $statuses[ $current ] ) ? $current : 'new';

		wp_nonce_field( 'itt_lead_handling', 'itt_lead_handling_nonce' );

		echo '<p><label for="itt-lead-status"><strong>' . esc_html__( 'שלב הטיפול', 'itt-landing' ) . '</strong></label><br>';
		echo '<select id="itt-lead-status" name="itt_status" style="width:100%">';

		foreach ( $statuses as $key => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $key ),
				selected( $current, $key, false ),
				esc_html( $label )
			);
		}

		echo '</select></p>';

		printf(
			'<p><label for="itt-lead-note"><strong>%s</strong></label><br><textarea id="itt-lead-note" name="itt_note" rows="6" style="width:100%%">%s</textarea></p>',
			esc_html__( 'הערות טיפול', 'itt-landing' ),
			esc_textarea( (string) get_post_meta( $post->ID, '_itt_note', true ) )
		);

		$handled_at = (string) get_post_meta( $post->ID, '_itt_status_at', true );

		if ( '' !== $handled_at ) {
			printf(
				'<p class="description">%s</p>',
				sprintf(
					/* translators: %s: date and time of the last status change. */
					esc_html__( 'עודכן לאחרונה: %s', 'itt-landing' ),
					esc_html( (string) mysql2date( 'j בF Y, H:i', get_date_from_gmt( gmdate( 'Y-m-d H:i:s', (int) strtotime( $handled_at ) ) ) ) )
				)
			);
		}
	}

	/**
	 * Persist the handling box.
	 *
	 * @param int     $post_id Lead ID.
	 * @param WP_Post $post    Lead being saved.
	 */
	public static function save_meta( int $post_id, WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! isset( $_POST['itt_lead_handling_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( (string) $_POST['itt_lead_handling_nonce'] ) ), 'itt_lead_handling' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$statuses = self::statuses();
		$status   = isset( $_POST['itt_status'] ) ? sanitize_key( wp_unslash( (string) $_POST['itt_status'] ) ) : 'new';
		$status   = isset( $statuses[ $status ] ) ? $status : 'new';

		// Only stamped when the stage actually moves, so the timestamp answers
		// "when did this last progress" rather than "when was this last saved".
		if ( $status !== (string) get_post_meta( $post_id, '_itt_status', true ) ) {
			update_post_meta( $post_id, '_itt_status_at', gmdate( 'c' ) );
		}

		update_post_meta( $post_id, '_itt_status', $status );
		update_post_meta(
			$post_id,
			'_itt_note',
			sanitize_textarea_field( wp_unslash( (string) ( $_POST['itt_note'] ?? '' ) ) )
		);
	}
}
