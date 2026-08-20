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
		// Where new-lead emails go. Empty means the WordPress admin email —
		// the pre-existing behaviour, so an upgrade changes nothing until the
		// owner fills the field in.
		'lead_email'           => '',
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
	 * Only the keys present in $values are changed. The Tools screen has more
	 * than one form saving into this option, and a form that did not carry a
	 * field must not blank the setting another form manages.
	 *
	 * @param array<string, mixed> $values Raw values, typically from $_POST.
	 */
	public static function save( array $values ): void {
		$clean = self::all();

		foreach ( array_keys( self::DEFAULTS ) as $key ) {
			if ( ! array_key_exists( $key, $values ) ) {
				continue;
			}

			$clean[ $key ] = 'lead_email' === $key
				? self::clean_email_list( (string) $values[ $key ] )
				: sanitize_text_field( (string) $values[ $key ] );
		}

		// Autoloaded: the site key is needed on every landing page render, and
		// the option is a handful of short strings.
		update_option( self::OPTION, $clean, true );
	}

	/**
	 * Keep only real email addresses out of a comma-separated list.
	 *
	 * A typo is dropped rather than stored, so a broken address can never
	 * quietly swallow the lead notifications.
	 *
	 * @param string $raw Raw value as typed.
	 * @return string Valid addresses, comma-separated.
	 */
	private static function clean_email_list( string $raw ): string {
		$valid = array();

		foreach ( explode( ',', $raw ) as $candidate ) {
			$email = sanitize_email( trim( $candidate ) );

			if ( '' !== $email && is_email( $email ) ) {
				$valid[] = $email;
			}
		}

		return implode( ', ', array_unique( $valid ) );
	}

	/**
	 * Everyone a new lead should be emailed to.
	 *
	 * The configured owner addresses when set, the WordPress admin email
	 * otherwise — so notifications always go somewhere.
	 *
	 * @return array<int, string>
	 */
	public static function lead_recipients(): array {
		$configured = array_filter( array_map( 'trim', explode( ',', self::get( 'lead_email' ) ) ) );

		if ( array() !== $configured ) {
			return array_values( $configured );
		}

		$admin = (string) get_option( 'admin_email' );

		return is_email( $admin ) ? array( $admin ) : array();
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
