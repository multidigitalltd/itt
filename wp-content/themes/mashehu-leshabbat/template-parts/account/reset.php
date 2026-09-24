<?php
/**
 * Setting a new password from a reset link.
 *
 * The token is not checked here, only carried. Checking it on display would
 * mean saying "that link is fine" to anyone holding a guess, and the answer
 * that matters is the one the submission gets.
 *
 * @package Mashehu_LeShabbat
 *
 * @var array<string, mixed> $msl_account Resolved account content.
 * @var string               $msl_token   Raw token from the address.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;
?>
<header class="msl-ahead" data-msl-rise>
	<h1 class="msl-heading"<?php msl_i18n( 'account', 'reset_title' ); ?>><?php msl_the( $msl_account, 'reset_title' ); ?></h1>
</header>

<div class="msl-agrid msl-agrid--one">
	<section class="msl-acard" data-msl-rise>
		<form class="msl-aform" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="msl_account_reset">
			<input type="hidden" name="token" value="<?php echo esc_attr( $msl_token ); ?>">
			<?php wp_nonce_field( 'msl_account' ); ?>

			<p class="msl-field">
				<label class="msl-field__label" for="msl-reset-password"<?php msl_i18n( 'account', 'f_password_new' ); ?>><?php msl_the( $msl_account, 'f_password_new' ); ?></label>
				<input type="password" class="msl-input" id="msl-reset-password" name="password"
					minlength="<?php echo absint( MSL_Account::MIN_PASSWORD ); ?>" autocomplete="new-password" required
					aria-describedby="msl-reset-help">
				<span class="msl-field__help" id="msl-reset-help"<?php msl_i18n( 'account', 'password_help' ); ?>><?php msl_the( $msl_account, 'password_help' ); ?></span>
			</p>

			<button type="submit" class="msl-btn msl-btn--hero msl-btn--wide"<?php msl_i18n( 'account', 'reset_cta' ); ?>><?php msl_the( $msl_account, 'reset_cta' ); ?></button>
		</form>
	</section>
</div>
