<?php
/**
 * Language resolution and the bilingual dictionary.
 *
 * The page is one document with roughly 130 strings and several sentences that
 * interpolate numbers at different positions per language, so a multilingual
 * plugin would fight the single-page model rather than help it. Instead every
 * copy field is stored as a Hebrew/English pair on the page itself, and this
 * class turns those pairs into (a) the value rendered server-side and (b) a flat
 * dictionary handed to the browser, so the header toggle swaps language with no
 * reload and no second request.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Bilingual helpers.
 */
final class MSL_I18N {

	/**
	 * Languages the theme ships.
	 */
	public const LANGS = array( 'he', 'en' );

	/**
	 * Cookie remembering the visitor's choice.
	 */
	public const COOKIE = 'msl_lang';

	/**
	 * Resolved language for this request.
	 *
	 * @var string|null
	 */
	private static ?string $lang = null;

	/**
	 * The language to render: query string, then cookie, then the site locale.
	 *
	 * The query string wins so a shared link can pin a language, and the choice
	 * is persisted by the browser rather than here — writing a cookie from PHP
	 * would make the HTML uncacheable for the sake of one attribute.
	 *
	 * @return string 'he' or 'en'.
	 */
	public static function lang(): string {
		if ( null !== self::$lang ) {
			return self::$lang;
		}

		$requested = '';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a display preference, not a state change.
		if ( isset( $_GET['lang'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$requested = sanitize_key( wp_unslash( (string) $_GET['lang'] ) );
		} elseif ( isset( $_COOKIE[ self::COOKIE ] ) ) {
			$requested = sanitize_key( wp_unslash( (string) $_COOKIE[ self::COOKIE ] ) );
		}

		if ( in_array( $requested, self::LANGS, true ) ) {
			self::$lang = $requested;

			return self::$lang;
		}

		/*
		 * Hebrew, always, until the visitor says otherwise. Deriving this from
		 * the site locale looked tidier but meant a WordPress installed in
		 * en_US — which is most of them, before anyone changes it — served the
		 * whole campaign in English, including a brand name the client has
		 * explicitly not signed off on.
		 */
		self::$lang = 'he';

		return self::$lang;
	}

	/**
	 * Text direction for the rendered language.
	 *
	 * @return string 'rtl' or 'ltr'.
	 */
	public static function dir(): string {
		return 'he' === self::lang() ? 'rtl' : 'ltr';
	}

	/**
	 * The BCP-47 tag for the rendered language.
	 *
	 * @return string
	 */
	public static function locale(): string {
		return 'he' === self::lang() ? 'he-IL' : 'en-US';
	}

	/**
	 * One value from a resolved section, in the language being rendered.
	 *
	 * Falls back to the other language rather than to an empty string: a missing
	 * English translation should show the Hebrew, not a hole in the layout.
	 *
	 * @param array<string, mixed> $section Resolved section content.
	 * @param string               $key     Base key, without the language suffix.
	 * @return string
	 */
	public static function value( array $section, string $key ): string {
		$lang  = self::lang();
		$other = 'he' === $lang ? 'en' : 'he';

		$primary = (string) ( $section[ $key . '_' . $lang ] ?? '' );

		if ( '' !== trim( $primary ) ) {
			return $primary;
		}

		return (string) ( $section[ $key . '_' . $other ] ?? '' );
	}

	/**
	 * Both halves of one pair, keyed by language.
	 *
	 * @param array<string, mixed> $section Resolved section content.
	 * @param string               $key     Base key, without the language suffix.
	 * @return array<string, string>
	 */
	public static function pair( array $section, string $key ): array {
		$out = array();

		foreach ( self::LANGS as $lang ) {
			$other        = 'he' === $lang ? 'en' : 'he';
			$value        = (string) ( $section[ $key . '_' . $lang ] ?? '' );
			$out[ $lang ] = '' !== trim( $value ) ? $value : (string) ( $section[ $key . '_' . $other ] ?? '' );
		}

		return $out;
	}

	/**
	 * The whole page's copy, flattened to `section.key` in both languages.
	 *
	 * Only paired fields are included — numbers, images and toggles are not copy
	 * and have nothing to swap. Repeater rows are emitted as `section.field.N.key`
	 * so the browser can address a single row without shipping the array twice.
	 *
	 * @param int $post_id Page being rendered.
	 * @return array<string, array<string, string>>
	 */
	public static function dictionary( int $post_id ): array {
		$dict = array(
			'he' => array(),
			'en' => array(),
		);

		foreach ( MSL_Fields::all() as $section => $definition ) {
			$values = MSL_Meta::get( $section, $post_id );

			foreach ( $definition['fields'] as $field ) {
				if ( 'repeater' === $field['type'] ) {
					self::collect_repeater( $dict, $section, $field, (array) ( $values[ $field['key'] ] ?? array() ) );
					continue;
				}

				self::collect( $dict, $section, (string) $field['key'], $values );
			}
		}

		return $dict;
	}

	/**
	 * Add one paired field to the dictionary, if it is a pair at all.
	 *
	 * @param array<string, array<string, string>> $dict    Dictionary being built, by reference.
	 * @param string                               $prefix  Key prefix.
	 * @param string                               $key     Field key, with its language suffix.
	 * @param array<string, mixed>                 $values  Resolved values the key belongs to.
	 */
	private static function collect( array &$dict, string $prefix, string $key, array $values ): void {
		if ( ! str_ends_with( $key, '_he' ) ) {
			return;
		}

		$base = substr( $key, 0, -3 );
		$pair = self::pair( $values, $base );

		foreach ( self::LANGS as $lang ) {
			$dict[ $lang ][ $prefix . '.' . $base ] = $pair[ $lang ];
		}
	}

	/**
	 * Add the paired sub-fields of every repeater row.
	 *
	 * @param array<string, array<string, string>> $dict  Dictionary being built, by reference.
	 * @param string                               $prefix Section key.
	 * @param array<string, mixed>                 $field Repeater definition.
	 * @param array<int, mixed>                    $rows  Stored rows.
	 */
	private static function collect_repeater( array &$dict, string $prefix, array $field, array $rows ): void {
		foreach ( array_values( $rows ) as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			foreach ( (array) $field['fields'] as $sub ) {
				self::collect( $dict, $prefix . '.' . $field['key'] . '.' . $index, (string) $sub['key'], $row );
			}
		}
	}
}
