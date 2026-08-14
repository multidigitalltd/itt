<?php
/**
 * Lead capture: storage, REST endpoint and admin listing.
 *
 * The form posts to a REST route rather than to the page itself, so the landing
 * page stays fully cacheable by LiteSpeed / Cloudflare: no per-visitor nonce is
 * ever printed into the HTML.
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
	 * Register the two public routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/token',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'token' ),
				'permission_callback' => '__return_true',
			)
		);

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
					'hp'      => array(
						'required'          => false,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'ts'      => array(
						'required'          => false,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Hand the browser a fresh REST nonce and the current time.
	 *
	 * Explicitly uncached so a page cache in front of WordPress cannot serve a
	 * stale nonce to a later visitor.
	 *
	 * @return WP_REST_Response
	 */
	public static function token(): WP_REST_Response {
		$response = new WP_REST_Response(
			array(
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'ts'    => time(),
			)
		);

		$response->header( 'Cache-Control', 'no-store, max-age=0' );

		return $response;
	}

	/**
	 * Store a submitted lead.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function submit( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		// Silently accept and drop bot submissions: an honest error message would
		// only tell the bot how to try again.
		if ( '' !== (string) $request->get_param( 'hp' ) ) {
			return self::success();
		}

		$ts = absint( $request->get_param( 'ts' ) );

		if ( $ts > 0 && ( time() - $ts ) < self::MIN_FILL_SECONDS ) {
			return self::success();
		}

		if ( ! self::within_rate_limit() ) {
			return new WP_Error(
				'itt_rate_limited',
				__( 'קיבלנו כמה פניות מהמכשיר הזה. אפשר לנסות שוב מאוחר יותר או לכתוב לנו בוואטסאפ.', 'itt-landing' ),
				array( 'status' => 429 )
			);
		}

		$name  = trim( (string) $request->get_param( 'name' ) );
		$phone = trim( (string) $request->get_param( 'phone' ) );
		$email = trim( (string) $request->get_param( 'email' ) );

		$errors = self::validate( $name, $phone, $email );

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

		self::notify( $name, $phone, $email, (string) $request->get_param( 'message' ) );

		return self::success();
	}

	/**
	 * Validate the submitted values server-side.
	 *
	 * The browser runs the same checks for a faster response, but they are never
	 * trusted: this is the check that counts.
	 *
	 * @param string $name  Full name.
	 * @param string $phone Phone number.
	 * @param string $email Email address, optional.
	 * @return array<string, string> Field key => message.
	 */
	private static function validate( string $name, string $phone, string $email ): array {
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

		return $errors;
	}

	/**
	 * The response returned for every accepted submission.
	 *
	 * @return WP_REST_Response
	 */
	private static function success(): WP_REST_Response {
		$thank_you = ITT_Importer::page_id( 'thank-you' );

		return new WP_REST_Response(
			array(
				'ok'       => true,
				'redirect' => $thank_you > 0 ? get_permalink( $thank_you ) : '',
			)
		);
	}

	/**
	 * Whether this client is still inside the submission budget.
	 *
	 * The IP is hashed before it becomes part of a transient key, so no raw
	 * address is written to the options table.
	 */
	private static function within_rate_limit(): bool {
		$ip = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) )
			: '';

		if ( '' === $ip ) {
			return true;
		}

		$key   = 'itt_rl_' . hash( 'sha256', $ip . wp_salt() );
		$count = (int) get_transient( $key );

		if ( $count >= self::RATE_LIMIT ) {
			return false;
		}

		set_transient( $key, $count + 1, self::RATE_WINDOW );

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
	 * Add phone and email columns to the leads list.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public static function columns( array $columns ): array {
		$date = $columns['date'] ?? '';
		unset( $columns['date'] );

		$columns['itt_phone'] = __( 'טלפון', 'itt-landing' );
		$columns['itt_email'] = __( 'אימייל', 'itt-landing' );

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
		if ( ! in_array( $column, array( 'itt_phone', 'itt_email' ), true ) ) {
			return;
		}

		$value = (string) get_post_meta( $post_id, '_itt_' . str_replace( 'itt_', '', $column ), true );

		echo '' !== $value ? esc_html( $value ) : '—';
	}
}
