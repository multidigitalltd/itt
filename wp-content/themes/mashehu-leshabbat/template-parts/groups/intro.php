<?php
/**
 * The head of the groups page: what a group is, and the way to open one.
 *
 * @package Mashehu_LeShabbat
 *
 * @var array<string, mixed> $msl_groups Resolved groups content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_open = 1 === (int) ( $msl_groups['open_on'] ?? 0 );

// The answer to a form that was just submitted. Both arrive in the address,
// because the form is an ordinary POST and the page it returns to is cached.
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only display of the redirect this page itself sent.
$msl_new   = isset( $_GET['msl_new'] ) ? sanitize_key( wp_unslash( $_GET['msl_new'] ) ) : '';
$msl_key   = isset( $_GET['msl_key'] ) ? sanitize_key( wp_unslash( $_GET['msl_key'] ) ) : '';
$msl_error = isset( $_GET['msl_error'] ) ? sanitize_key( wp_unslash( $_GET['msl_error'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$msl_made = '' !== $msl_new ? MSL_Groups::by_code( $msl_new ) : null;
?>
<header class="msl-ghead" data-msl-rise>
	<p class="msl-ghead__eyebrow"<?php msl_i18n( 'groups', 'eyebrow' ); ?>><?php msl_the( $msl_groups, 'eyebrow' ); ?></p>
	<h1 class="msl-heading"<?php msl_i18n( 'groups', 'title' ); ?>><?php msl_the( $msl_groups, 'title' ); ?></h1>
	<p class="msl-subheading"<?php msl_i18n( 'groups', 'lead' ); ?>><?php msl_the( $msl_groups, 'lead' ); ?></p>

	<?php if ( $msl_open && null === $msl_made ) : ?>
		<p class="msl-ghead__actions">
			<a class="msl-btn msl-btn--hero" href="#msl-open"<?php msl_i18n( 'groups', 'open_cta' ); ?>><?php msl_the( $msl_groups, 'open_cta' ); ?></a>
		</p>
	<?php endif; ?>
</header>

<?php if ( '' !== $msl_error ) : ?>
	<?php
	// The message the page already holds, chosen by the key the redirect carried.
	$msl_field = array_key_exists( 'err_' . $msl_error . '_he', $msl_groups ) ? 'err_' . $msl_error : 'err_generic';
	?>
	<p class="msl-gnote msl-gnote--bad" role="alert"<?php msl_i18n( 'groups', $msl_field ); ?>><?php msl_the( $msl_groups, $msl_field ); ?></p>
<?php endif; ?>

<?php
if ( null !== $msl_made ) :
	$msl_share  = MSL_Groups::url( (string) $msl_made['code'] );
	$msl_manage = MSL_Groups::manage_url( (string) $msl_made['code'], $msl_key );
	?>
	<section class="msl-gdone" data-msl-rise>
		<h2 class="msl-gdone__title"<?php msl_i18n( 'groups', 'done_title' ); ?>><?php msl_the( $msl_groups, 'done_title' ); ?></h2>
		<p class="msl-gdone__body"<?php msl_i18n( 'groups', 'done_body' ); ?>><?php msl_the( $msl_groups, 'done_body' ); ?></p>

		<?php if ( MSL_Groups::PENDING === $msl_made['status'] ) : ?>
			<p class="msl-gnote"<?php msl_i18n( 'groups', 'done_pending' ); ?>><?php msl_the( $msl_groups, 'done_pending' ); ?></p>
		<?php endif; ?>

		<p class="msl-gdone__label"<?php msl_i18n( 'groups', 'done_link' ); ?>><?php msl_the( $msl_groups, 'done_link' ); ?></p>
		<p class="msl-gdone__link"><a href="<?php echo esc_url( $msl_share ); ?>"><?php echo esc_html( (string) preg_replace( '#^https?://#', '', untrailingslashit( $msl_share ) ) ); ?></a></p>

		<?php if ( '' !== $msl_key && MSL_Groups::owns( $msl_made, $msl_key ) ) : ?>
			<?php
			/*
			 * Shown once, here, and never again: this address is the only thing
			 * that proves whoever holds it opened the group, so it is not put in
			 * a list, not emailed by the theme, and not recoverable from the
			 * public page.
			 */
			?>
			<p class="msl-gdone__label"<?php msl_i18n( 'groups', 'done_manage' ); ?>><?php msl_the( $msl_groups, 'done_manage' ); ?></p>
			<p class="msl-gdone__link msl-gdone__link--key"><a href="<?php echo esc_url( $msl_manage ); ?>"><?php echo esc_html( (string) preg_replace( '#^https?://#', '', untrailingslashit( $msl_manage ) ) ); ?></a></p>
		<?php endif; ?>
	</section>
<?php endif; ?>
