<?php
/**
 * The REST surface.
 *
 * The page HTML carries no nonce and no per-visitor value, so it stays fully
 * cacheable in front of LiteSpeed or Cloudflare. The browser pulls a fresh nonce
 * from an uncached endpoint the moment before it submits, exactly as the house
 * pattern does for lead forms.
 *
 * Nothing here ever returns a row id, a hash, a reminder destination, or the
 * name of anyone who asked to stay anonymous.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Routes under msl/v1.
 */
final class MSL_REST {

	/**
	 * REST namespace.
	 */
	public const NAMESPACE = 'msl/v1';

	/**
	 * Shortest believable time to fill the three-step flow, in seconds.
	 *
	 * A real person picks something, reads the dedication step and types a name
	 * and a city. Under this, it was not a person.
	 */
	/*
	 * A human takes a moment; a script does not. Now that no field is required
	 * the honest fast path is genuinely fast — three taps and done — so this is
	 * the floor a real person can still clear, not a guess at how long filling
	 * a form ought to take.
	 */
	private const MIN_FILL_SECONDS = 1.2;

	/**
	 * Register the routes.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );

		// This site has no participant accounts and no author archives to serve.
		add_filter( 'rest_endpoints', array( self::class, 'remove_user_endpoints' ) );
		add_filter( 'xmlrpc_enabled', '__return_false' );
	}

	/**
	 * Register every route.
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/nonce',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
				'callback'            => static fn(): WP_REST_Response => self::uncached(
					new WP_REST_Response( array( 'nonce' => wp_create_nonce( 'wp_rest' ) ) )
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
				'callback'            => array( self::class, 'stats' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/feed',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
				'callback'            => array( self::class, 'feed' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/pieces',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
				'callback'            => array( self::class, 'pieces' ),
				'args'                => array(
					'from' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
					'to'   => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/zmanim',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
				'callback'            => array( self::class, 'zmanim' ),
				'args'                => array(
					'place' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/remind',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => '__return_true',
				'callback'            => array( self::class, 'remind' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/referral/(?P<code>[a-z0-9]{6,12})',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
				'callback'            => array( self::class, 'referral' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/session',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
				'callback'            => array( self::class, 'session' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/join',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => '__return_true',
				'callback'            => array( self::class, 'join' ),
			)
		);
	}

	/**
	 * Drop the core user routes.
	 *
	 * @param array<string, mixed> $endpoints Registered endpoints.
	 * @return array<string, mixed>
	 */
	public static function remove_user_endpoints( array $endpoints ): array {
		unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );

		return $endpoints;
	}

	/* ---------------------------------------------------------------------
	 * Read routes
	 * ------------------------------------------------------------------ */

	/**
	 * The Shabbat times of one place.
	 *
	 * Here so a visitor can read their own city's times without the browser ever
	 * talking to hebcal.com, and without reloading a page whose artwork canvas
	 * is mid-animation. The answer is built by the same method the page itself
	 * renders from, so the two cannot drift apart.
	 *
	 * A place that is not on the list is refused rather than fetched: this
	 * argument comes from a stranger, and it decides where the server sends a
	 * request.
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response
	 */
	public static function zmanim( WP_REST_Request $request ): WP_REST_Response {
		$zmanim = MSL_Meta::get( 'zmanim', MSL_Importer::page_id() );
		$place  = (int) $request->get_param( 'place' );

		if ( ! MSL_Zmanim::pickable( $zmanim ) || ! isset( MSL_Zmanim::PLACES[ $place ] ) ) {
			return new WP_REST_Response( array( 'message' => 'unknown place' ), 400 );
		}

		$card = MSL_Zmanim::card( $zmanim, $place );

		if ( null === $card ) {
			return new WP_REST_Response( array( 'message' => 'unavailable' ), 503 );
		}

		return new WP_REST_Response( $card );
	}

	/**
	 * Live counters.
	 *
	 * @return WP_REST_Response
	 */
	public static function stats(): WP_REST_Response {
		$page_id = MSL_Importer::page_id();
		$stats   = MSL_Stats::all( $page_id );

		return new WP_REST_Response(
			array(
				'participants' => $stats['participants'],
				'countries'    => $stats['countries'],
				'cities'       => $stats['cities'],
				'dedications'  => $stats['dedications'],
				'pct'          => $stats['pct'],
				'last10'       => $stats['last10'],
				'closed'       => 1 === (int) MSL_Meta::get( 'campaign', $page_id )['closed'],
			)
		);
	}

	/**
	 * The activity feed.
	 *
	 * @return WP_REST_Response
	 */
	public static function feed(): WP_REST_Response {
		$page_id = MSL_Importer::page_id();

		return new WP_REST_Response(
			array( 'rows' => MSL_Joins::feed( $page_id, MSL_Meta::get( 'join', $page_id ) ) )
		);
	}

	/**
	 * Owner details for a window of pieces.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function pieces( WP_REST_Request $request ): WP_REST_Response {
		$page_id = MSL_Importer::page_id();
		$to      = (int) $request->get_param( 'to' );
		// Bounded at 501 positions, trimmed from the older end so that the most
		// recent joins in the window are the ones that survive the trim.
		$from    = max( (int) $request->get_param( 'from' ), $to - 500 );

		return new WP_REST_Response(
			array( 'pieces' => MSL_Joins::pieces( $page_id, MSL_Meta::get( 'join', $page_id ), $from, $to ) )
		);
	}

	/**
	 * Sign somebody up for a reminder before Shabbat.
	 *
	 * Deliberately not a join. The person asking has not added a candle, and
	 * writing them into the joins table to make one number bigger would make
	 * that number a lie. The two live in separate tables for exactly that reason.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function remind( WP_REST_Request $request ): WP_REST_Response {
		$page_id = MSL_Importer::page_id();
		$join    = MSL_Meta::get( 'join', $page_id );

		// The same two cheap gates the join flow uses, and for the same reason.
		if ( '' !== trim( (string) $request->get_param( 'hp' ) ) ) {
			return self::error( 'generic', $join, 400 );
		}

		$ip_hash = MSL_Joins::client_ip_hash();

		if ( ! MSL_Joins::within_rate_limit( $ip_hash, false ) ) {
			return self::error( 'rate', $join, 429 );
		}

		$email = sanitize_email( (string) $request->get_param( 'email' ) );

		if ( '' === $email || ! is_email( $email ) ) {
			return self::error( 'email', $join, 422 );
		}

		$options = array_values( (array) ( $join['options'] ?? array() ) );
		$thing   = $request->get_param( 'thing' );
		$thing   = null === $thing || '' === $thing ? null : (int) $thing;

		if ( null !== $thing && ! isset( $options[ $thing ] ) ) {
			$thing = null;
		}

		$ok = MSL_Joins::remind(
			$page_id,
			array(
				'name'         => sanitize_text_field( (string) $request->get_param( 'name' ) ),
				'email'        => $email,
				'thing_index'  => $thing,
				'custom_label' => sanitize_text_field( (string) $request->get_param( 'custom_label' ) ),
				'lang'         => sanitize_key( (string) $request->get_param( 'lang' ) ) === 'en' ? 'en' : 'he',
			)
		);

		if ( ! $ok ) {
			return self::error( 'generic', $join, 500 );
		}

		MSL_Joins::within_rate_limit( $ip_hash, true );

		return self::uncached( new WP_REST_Response( array( 'ok' => true ), 201 ) );
	}

	/**
	 * How many people one code has brought in.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function referral( WP_REST_Request $request ): WP_REST_Response {
		$code    = (string) $request->get_param( 'code' );
		$count   = MSL_Joins::referral_count( $code );
		$page_id = MSL_Importer::page_id();
		$next    = self::next_milestone( $page_id, $count );

		return new WP_REST_Response(
			array(
				'count' => $count,
				'next'  => $next,
				// So that "my candle" still works on a device that kept the
				// code cookie but lost the rest of the join.
				'piece' => MSL_Joins::piece_for_code( $page_id, $code ),
				// For somebody arriving on this code's shared link: whose light
				// they are being shown. Empty when that person joined without
				// a name.
				'name'  => MSL_Joins::name_for_code( $page_id, $code ),
			)
		);
	}

	/**
	 * Whether this browser is signed in, asked somewhere a cache cannot answer.
	 *
	 * The personal area declares itself uncacheable in the three ways the
	 * caches that matter obey, and on a host that ignores all three a signed-in
	 * person is handed the signed-out copy of the page and told to sign in
	 * again — which they then do, and get the same stored page back. The page
	 * asks here whether that is what happened, because this answer is computed
	 * per request from the cookie and no page cache stands in front of it.
	 *
	 * @return WP_REST_Response
	 */
	public static function session(): WP_REST_Response {
		nocache_headers();

		return new WP_REST_Response( array( 'signedIn' => null !== MSL_Auth::current() ) );
	}

	/**
	 * The first milestone above a given count.
	 *
	 * @param int $page_id Page ID.
	 * @param int $count   Current referral count.
	 * @return int
	 */
	public static function next_milestone( int $page_id, int $count ): int {
		$values = array();

		foreach ( (array) ( MSL_Meta::get( 'referral', $page_id )['milestones'] ?? array() ) as $row ) {
			if ( is_array( $row ) && (int) ( $row['value'] ?? 0 ) > 0 ) {
				$values[] = (int) $row['value'];
			}
		}

		if ( array() === $values ) {
			return 0;
		}

		sort( $values );

		foreach ( $values as $value ) {
			if ( $value > $count ) {
				return $value;
			}
		}

		return (int) end( $values );
	}

	/* ---------------------------------------------------------------------
	 * The join route
	 * ------------------------------------------------------------------ */

	/**
	 * Record a join.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function join( WP_REST_Request $request ): WP_REST_Response {
		$page_id = MSL_Importer::page_id();
		$join    = MSL_Meta::get( 'join', $page_id );

		// The honeypot must stay empty and the flow must have taken a human
		// amount of time. Both are answered before anything touches the database.
		if ( '' !== trim( (string) $request->get_param( 'hp' ) ) ) {
			return self::error( 'generic', $join, 400 );
		}

		$elapsed = (float) $request->get_param( 'elapsed' );

		if ( $elapsed > 0 && $elapsed < self::MIN_FILL_SECONDS ) {
			return self::error( 'generic', $join, 400 );
		}

		$nonce = (string) $request->get_param( 'nonce' );

		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return self::error( 'generic', $join, 403 );
		}

		if ( 1 === (int) MSL_Meta::get( 'campaign', $page_id )['closed'] ) {
			return self::error( 'closed', $join, 410 );
		}

		$ip_hash = MSL_Joins::client_ip_hash();

		if ( ! MSL_Joins::within_rate_limit( $ip_hash, false ) ) {
			return self::error( 'rate', $join, 429 );
		}

		$clean = self::validate( $request, $join );

		if ( is_wp_error( $clean ) ) {
			return self::error( (string) $clean->get_error_message(), $join, 422 );
		}

		$result = MSL_Joins::record( $page_id, $clean, $ip_hash );

		if ( is_wp_error( $result ) ) {
			return self::error( (string) $result->get_error_message(), $join, 'msl_duplicate' === $result->get_error_code() ? 409 : 500 );
		}

		MSL_Joins::within_rate_limit( $ip_hash, true );

		$result['next_milestone'] = self::next_milestone( $page_id, 0 );
		$result['ref_days']       = MSL_Joins::ref_days();

		return self::uncached( new WP_REST_Response( $result, 201 ) );
	}

	/**
	 * Validate and normalise the submitted values.
	 *
	 * @param WP_REST_Request      $request Request.
	 * @param array<string, mixed> $join    Resolved join section, for the option list.
	 * @return array<string, mixed>|WP_Error Error message is an error key, not prose.
	 */
	private static function validate( WP_REST_Request $request, array $join ): array|WP_Error {
		$options = array_values( (array) ( $join['options'] ?? array() ) );

		$things = array();

		foreach ( (array) $request->get_param( 'things' ) as $raw ) {
			$index = (int) $raw;

			if ( isset( $options[ $index ] ) && ! in_array( $index, $things, true ) ) {
				$things[] = $index;
			}
		}

		$things = array_slice( $things, 0, MSL_Joins::MAX_THINGS );

		$custom_label = '';

		foreach ( $things as $index ) {
			if ( 1 === (int) ( $options[ $index ]['is_other'] ?? 0 ) ) {
				$custom_label = mb_substr( sanitize_text_field( (string) $request->get_param( 'custom_label' ) ), 0, 140 );
			}
		}

		$first_name = mb_substr( sanitize_text_field( (string) $request->get_param( 'first_name' ) ), 0, 80 );
		$city       = mb_substr( sanitize_text_field( (string) $request->get_param( 'city' ) ), 0, 120 );
		$country    = mb_substr( sanitize_text_field( (string) $request->get_param( 'country' ) ), 0, 120 );

		/*
		 * Nothing above is required. The campaign counts people, and a field
		 * somebody did not want to answer is a person who never joined — so a
		 * blank form is a valid join, and it joins anonymously.
		 *
		 * The shape checks below still stand: they only ever run on something
		 * the visitor actually typed, and they catch a typo rather than refuse
		 * an answer.
		 */
		$first_name = trim( $first_name );
		$city       = trim( $city );
		$country    = trim( $country );

		$email = sanitize_email( (string) $request->get_param( 'email' ) );
		$phone = sanitize_text_field( (string) $request->get_param( 'phone' ) );

		if ( '' !== trim( (string) $request->get_param( 'email' ) ) && ! is_email( $email ) ) {
			return new WP_Error( 'msl_invalid', 'email' );
		}

		if ( '' !== trim( $phone ) && strlen( (string) preg_replace( '/\D+/', '', $phone ) ) < 9 ) {
			return new WP_Error( 'msl_invalid', 'phone' );
		}

		$dedication_kind = $request->get_param( 'dedication' );
		$dedication_kind = null === $dedication_kind || '' === $dedication_kind ? null : (int) $dedication_kind;
		$ded_types       = array_values( (array) ( $join['ded_types'] ?? array() ) );

		if ( null !== $dedication_kind && ! isset( $ded_types[ $dedication_kind ] ) ) {
			$dedication_kind = null;
		}

		$lang     = sanitize_key( (string) $request->get_param( 'lang' ) );
		$group_id = self::group_id( (string) $request->get_param( 'group' ) );

		/*
		 * A candle lit through a group is lit for what that group was opened
		 * for, and for nothing else. The form on a group's page therefore has
		 * no dedication controls in it at all — but the form is not what makes
		 * that true. This is: whatever arrives in these two fields is dropped
		 * for a join that names a group with a dedication of its own, so the
		 * answer is the group's however the request was made.
		 *
		 * Nothing is written in its place. The group already holds the
		 * dedication, the join already holds the group, and a copy on every
		 * one of a group's rows is the same sentence stored two hundred times
		 * and a second thing to keep true when the family edits it.
		 */
		if ( $group_id > 0 && class_exists( 'MSL_Groups' ) ) {
			$group = MSL_Groups::by_id( $group_id );

			if ( null !== $group && '' !== msl_group_dedication( $group, MSL_Meta::get( 'groups', MSL_Importer::page_id( 'groups' ) ) ) ) {
				$dedication_kind = null;
				$request->set_param( 'dedication_body', '' );
			}
		}

		return array(
			'things'          => $things,
			'custom_label'    => $custom_label,
			'first_name'      => $first_name,
			'city'            => $city,
			'country'         => $country,
			'email'           => $email,
			'phone'           => $phone,
			// Asked for, or simply not given: a join with no name on it is an
			// anonymous join, and the feed and the artwork both already know
			// what to do with one.
			'is_anonymous'    => (int) ( (bool) $request->get_param( 'is_anonymous' ) || '' === $first_name ),
			'lang'            => in_array( $lang, MSL_I18N::LANGS, true ) ? $lang : 'he',
			'referred_by'     => sanitize_key( (string) $request->get_param( 'referred_by' ) ),
			/*
			 * How many Shabbatot the undertaking is for. Anything that is not
			 * one of the offered lengths becomes none — a number arriving from
			 * outside the form must not be able to sign somebody up for a
			 * year's correspondence.
			 */
			'reminder_weeks'  => (int) $request->get_param( 'reminder_weeks' ),
			'dedication'      => $dedication_kind,
			'dedication_body' => mb_substr( sanitize_textarea_field( (string) $request->get_param( 'dedication_body' ) ), 0, 280 ),
			'group_id'        => $group_id,
		);
	}

	/**
	 * The group a join belongs to, if it names one that is open.
	 *
	 * Resolved from the code rather than trusted as an id, and only for a group
	 * that is actually taking joins — a code for a group still awaiting approval
	 * or already closed counts for the main artwork and for nothing else, which
	 * is the honest outcome: the person did light a candle.
	 *
	 * @param string $code Submitted group code.
	 * @return int
	 */
	private static function group_id( string $code ): int {
		if ( '' === $code ) {
			return 0;
		}

		$group = MSL_Groups::by_code( sanitize_key( $code ) );

		// Pending counts. A light lit in a group that is still waiting for its
		// text to be read is still a light, and dropping the id here would have
		// recorded the join against the campaign with the group silently lost.
		return null !== $group && MSL_Groups::accepts_joins( $group ) ? (int) $group['id'] : 0;
	}

	/**
	 * A refusal carrying the message the page already holds, in both languages.
	 *
	 * The visitor never sees a raw WordPress error: the copy is the editable
	 * string from the page, so it is on-brand and translated.
	 *
	 * @param string               $key    Error key, matching an err_* field.
	 * @param array<string, mixed> $join   Resolved join section.
	 * @param int                  $status HTTP status.
	 * @return WP_REST_Response
	 */
	private static function error( string $key, array $join, int $status ): WP_REST_Response {
		$field = 'err_' . ( in_array( $key, array( 'email', 'phone', 'duplicate', 'rate', 'closed' ), true ) ? $key : 'generic' );

		return self::uncached(
			new WP_REST_Response(
				array(
					'error'   => $key,
					'message' => MSL_I18N::pair( $join, $field ),
				),
				$status
			)
		);
	}

	/**
	 * Mark a response as never cacheable.
	 *
	 * A page cache in front of the site would otherwise happily serve one
	 * visitor's nonce, or one visitor's join result, to the next visitor.
	 *
	 * @param WP_REST_Response $response Response.
	 * @return WP_REST_Response
	 */
	private static function uncached( WP_REST_Response $response ): WP_REST_Response {
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );

		return $response;
	}
}
