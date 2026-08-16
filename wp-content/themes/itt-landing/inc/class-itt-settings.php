<?php
/**
 * Site-wide theme settings that are not page content.
 *
 * The Cloudflare Turnstile keys belong to the site, not to any one page, and
 * the secret key must never travel with an exported page — so they live in a
 * single option rather than in post meta like everything else the theme
 * stores. They are edited on the Tools → "עמודי ITT" screen.
 *
 * @package ITT_Landing
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Read/write access to the theme's site-wide settings.
 */
final class ITT_Settings {

	/**
	 * Option name holding every site-wide setting.
	 */
	private const OPTION = 'itt_settings';

	/**
	 * Defaults, which also define the shape of the option.
	 *
	 * @var array<string, string>
	 */
	private const DEFAULTS = array(
		'turnstile_site_key'   => '',
		'turnstile_secret_key' => '',
	);

	/**
	 * Every setting, with defaults filling any gap.
	 *
	 * @return array<string, string>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		return array_map( 'strval', array_merge( self::DEFAULTS, array_intersect_key( $stored, self::DEFAULTS ) ) );
	}

	/**
	 * One setting.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	public static function get( string $key ): string {
		return self::all()[ $key ] ?? '';
	}

	/**
	 * Write the settings, dropping unknown keys.
	 *
	 * @param array<string, mixed> $values Raw values, typically from $_POST.
	 */
	public static function save( array $values ): void {
		$clean = array();

		foreach ( array_keys( self::DEFAULTS ) as $key ) {
			$clean[ $key ] = sanitize_text_field( (string) ( $values[ $key ] ?? '' ) );
		}

		// Autoloaded: the site key is needed on every landing page render, and
		// the pair is two short strings.
		update_option( self::OPTION, $clean, true );
	}

	/**
	 * Whether Turnstile is configured well enough to be used.
	 *
	 * Both keys are required. A site key without a secret would show visitors a
	 * challenge that is never checked — worse than no challenge at all, because
	 * it looks like protection.
	 */
	public static function turnstile_enabled(): bool {
		$settings = self::all();

		return '' !== $settings['turnstile_site_key'] && '' !== $settings['turnstile_secret_key'];
	}
}
