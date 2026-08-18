<?php
/**
 * Reading, writing and sanitising the per-page content meta.
 *
 * Each section is stored as one post meta row on the page itself, so the copy
 * genuinely lives inside the page: export the page and the content travels with
 * it, revisions are per page, and there is no external field plugin involved.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Meta storage layer for the page content.
 */
final class MSL_Meta {

	/**
	 * Prefix for every meta key written by the theme.
	 */
	private const PREFIX = '_msl_';

	/**
	 * Per-request cache of resolved sections, keyed "postid:section".
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static array $cache = array();

	/**
	 * Register the meta keys.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ) );
	}

	/**
	 * The meta key for a section.
	 *
	 * @param string $section Section key.
	 * @return string
	 */
	public static function key( string $section ): string {
		return self::PREFIX . $section;
	}

	/**
	 * Register every section as protected post meta on pages.
	 *
	 * The keys stay out of the REST API: they are edited through the meta boxes,
	 * and exposing an arbitrarily nested array would need a schema per section
	 * for no practical gain.
	 */
	public static function register(): void {
		foreach ( array_keys( MSL_Fields::all() ) as $section ) {
			register_post_meta(
				'page',
				self::key( $section ),
				array(
					'type'              => 'array',
					'single'            => true,
					'show_in_rest'      => false,
					'sanitize_callback' => static fn( $value ): array => self::sanitize( $section, is_array( $value ) ? $value : array() ),
					'auth_callback'     => static fn( bool $allowed, string $meta_key, int $post_id ): bool => current_user_can( 'edit_post', $post_id ),
				)
			);
		}
	}

	/**
	 * Resolved content for a section: stored values, with defaults filling gaps.
	 *
	 * @param string   $section Section key.
	 * @param int|null $post_id Page ID. Defaults to the queried object.
	 * @return array<string, mixed>
	 */
	public static function get( string $section, ?int $post_id = null ): array {
		$post_id ??= (int) get_the_ID();
		$cache_key = $post_id . ':' . $section;

		if ( isset( self::$cache[ $cache_key ] ) ) {
			return self::$cache[ $cache_key ];
		}

		$defaults = MSL_Content::section( $section );
		$stored   = $post_id > 0 ? get_post_meta( $post_id, self::key( $section ), true ) : '';
		$stored   = is_array( $stored ) ? $stored : array();

		// Keys the page has actually saved win; the rest fall back to the
		// approved copy, which also covers fields added by a later theme update.
		$resolved = array_merge( $defaults, array_intersect_key( $stored, $defaults ) );

		self::$cache[ $cache_key ] = $resolved;

		return $resolved;
	}

	/**
	 * Store a section, running it through sanitisation first.
	 *
	 * @param int                  $post_id Page ID.
	 * @param string               $section Section key.
	 * @param array<string, mixed> $value   Raw submitted values.
	 */
	public static function save( int $post_id, string $section, array $value ): void {
		update_post_meta( $post_id, self::key( $section ), self::sanitize( $section, $value ) );
		unset( self::$cache[ $post_id . ':' . $section ] );
	}

	/**
	 * Sanitise a whole section against its schema.
	 *
	 * Anything not described in the schema is dropped, so a crafted request
	 * cannot smuggle extra keys into the stored array.
	 *
	 * @param string               $section Section key.
	 * @param array<string, mixed> $raw     Raw values.
	 * @return array<string, mixed>
	 */
	public static function sanitize( string $section, array $raw ): array {
		return self::sanitize_fields( MSL_Fields::fields( $section ), $raw );
	}

	/**
	 * Sanitise a list of values against a list of field definitions.
	 *
	 * @param array<int, array<string, mixed>> $fields Field definitions.
	 * @param array<string, mixed>             $raw    Raw values.
	 * @return array<string, mixed>
	 */
	private static function sanitize_fields( array $fields, array $raw ): array {
		$clean = array();

		foreach ( $fields as $field ) {
			$key           = (string) $field['key'];
			$clean[ $key ] = self::sanitize_value( $field, $raw[ $key ] ?? null );
		}

		return $clean;
	}

	/**
	 * Sanitise one value according to its field type.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $value Raw value.
	 * @return mixed
	 */
	private static function sanitize_value( array $field, mixed $value ): mixed {
		return match ( $field['type'] ) {
			'repeater' => self::sanitize_repeater( $field, $value ),
			'textarea' => sanitize_textarea_field( (string) ( is_scalar( $value ) ? $value : '' ) ),
			'url'      => esc_url_raw( (string) ( is_scalar( $value ) ? $value : '' ), array( 'http', 'https', 'mailto', 'tel' ) ),
			'email'    => sanitize_email( (string) ( is_scalar( $value ) ? $value : '' ) ),
			'image'    => absint( is_scalar( $value ) ? $value : 0 ),
			'checkbox' => empty( $value ) ? 0 : 1,
			'select'   => self::sanitize_select( $field, $value ),
			'number'   => self::sanitize_number( $field, $value ),
			'decimal'  => self::sanitize_decimal( $field, $value ),
			default    => sanitize_text_field( (string) ( is_scalar( $value ) ? $value : '' ) ),
		};
	}

	/**
	 * Clamp a whole number to the range declared in the schema.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $value Raw value.
	 * @return int
	 */
	private static function sanitize_number( array $field, mixed $value ): int {
		$min = (int) ( $field['min'] ?? 0 );
		$max = (int) ( $field['max'] ?? PHP_INT_MAX );

		return max( $min, min( $max, (int) ( is_scalar( $value ) ? $value : 0 ) ) );
	}

	/**
	 * Clamp a decimal to the range declared in the schema.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $value Raw value.
	 * @return float
	 */
	private static function sanitize_decimal( array $field, mixed $value ): float {
		$min = (float) ( $field['min'] ?? -PHP_FLOAT_MAX );
		$max = (float) ( $field['max'] ?? PHP_FLOAT_MAX );

		return max( $min, min( $max, (float) ( is_scalar( $value ) ? $value : 0 ) ) );
	}

	/**
	 * Constrain a select to the options declared in the schema.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $value Raw value.
	 * @return string
	 */
	private static function sanitize_select( array $field, mixed $value ): string {
		$options = array_keys( (array) ( $field['options'] ?? array() ) );
		$value   = sanitize_key( (string) ( is_scalar( $value ) ? $value : '' ) );

		if ( in_array( $value, $options, true ) ) {
			return $value;
		}

		return (string) ( $options[0] ?? '' );
	}

	/**
	 * Sanitise repeater rows, dropping rows that are entirely empty.
	 *
	 * @param array<string, mixed> $field Field definition.
	 * @param mixed                $value Raw rows.
	 * @return array<int, array<string, mixed>>
	 */
	private static function sanitize_repeater( array $field, mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$rows = array();

		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$clean = self::sanitize_fields( (array) $field['fields'], $row );

			if ( self::row_has_content( (array) $field['fields'], $clean ) ) {
				$rows[] = $clean;
			}
		}

		return $rows;
	}

	/**
	 * Whether a repeater row carries any content worth storing.
	 *
	 * Selects and checkboxes always resolve to a value, so they are ignored here:
	 * pressing "add row" and saving without typing anything must not leave a
	 * ghost entry on the page.
	 *
	 * @param array<int, array<string, mixed>> $fields Sub-field definitions.
	 * @param array<string, mixed>             $row    Sanitised row.
	 * @return bool
	 */
	private static function row_has_content( array $fields, array $row ): bool {
		foreach ( $fields as $field ) {
			if ( in_array( $field['type'], array( 'select', 'checkbox' ), true ) ) {
				continue;
			}

			$value = $row[ $field['key'] ] ?? '';

			if ( is_string( $value ) && '' !== trim( $value ) ) {
				return true;
			}

			if ( ( is_int( $value ) || is_float( $value ) ) && 0.0 !== (float) $value ) {
				return true;
			}
		}

		return false;
	}
}
