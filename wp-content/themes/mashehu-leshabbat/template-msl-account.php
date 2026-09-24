<?php
/**
 * Template Name: אור לשבת · האיזור האישי
 * Template Post Type: page
 *
 * The personal area: sign in with an address and a password, and behind it the
 * personal link, the groups this person opened, and the way to open another.
 *
 * **This page is never cached, and must never be.** Everything on it belongs to
 * one person — their link, their groups, their name — and a full-page cache
 * that kept a copy would hand the first visitor's account to the second. The
 * rest of the site is built to be cacheable to the byte, which is exactly why
 * the one page that cannot be says so loudly rather than hoping.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/*
 * Three ways of saying the same thing, because the caches that matter obey
 * different ones: the constant is what most WordPress page caches look for, the
 * headers are what a reverse proxy and the browser look at.
 */
if ( ! defined( 'DONOTCACHEPAGE' ) ) {
	define( 'DONOTCACHEPAGE', true );
}

if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
	define( 'DONOTCACHEOBJECT', true );
}

nocache_headers();

$msl_account = MSL_Meta::get( 'account' );
$msl_person  = MSL_Auth::current();

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only display of the redirect this page itself sent.
$msl_error = isset( $_GET['msl_error'] ) ? sanitize_key( wp_unslash( (string) $_GET['msl_error'] ) ) : '';
$msl_form  = isset( $_GET['msl_form'] ) ? sanitize_key( wp_unslash( (string) $_GET['msl_form'] ) ) : '';
$msl_sent  = isset( $_GET['msl_sent'] );
$msl_saved = isset( $_GET['msl_saved'] );
$msl_token = isset( $_GET['msl_reset'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['msl_reset'] ) ) : '';
$msl_manage = isset( $_GET['msl_manage'] ) ? sanitize_key( wp_unslash( (string) $_GET['msl_manage'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

get_header();

msl_section( 'chrome' );
?>
<main id="msl-main" class="msl-main msl-main--page">
	<div class="msl-shell">
		<?php
		if ( '' !== $msl_error ) {
			$msl_key = array_key_exists( 'err_' . $msl_error . '_he', $msl_account ) ? 'err_' . $msl_error : 'err_generic';

			printf(
				'<p class="msl-gnote msl-gnote--bad" role="alert"%s>%s</p>',
				msl_i18n_attr( 'account', $msl_key ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from literals and escaped inside.
				esc_html( msl_t( $msl_account, $msl_key ) )
			);
		}

		if ( null !== $msl_person ) {
			require MSL_DIR . 'template-parts/account/' . ( '' !== $msl_manage ? 'manage.php' : 'dashboard.php' );
		} elseif ( '' !== $msl_token ) {
			require MSL_DIR . 'template-parts/account/reset.php';
		} else {
			require MSL_DIR . 'template-parts/account/signed-out.php';
		}
		?>
	</div>
</main>
<?php
require MSL_DIR . 'template-parts/home/chrome-footer.php';
require MSL_DIR . 'template-parts/home/a11y-widget.php';

get_footer();
