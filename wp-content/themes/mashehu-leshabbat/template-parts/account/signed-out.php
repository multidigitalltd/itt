<?php
/**
 * The personal area, to somebody who is not signed in.
 *
 * Sign in, open an account, or ask for a reset link — all three on one page
 * rather than behind links to each other. Somebody arriving here does not yet
 * know which of the three they need, and making them guess before they can see
 * the form is how a person ends up with two accounts.
 *
 * No JavaScript on any of it. Getting back into your own group on a borrowed
 * phone is exactly the moment a form must not depend on a script arriving.
 *
 * @package Mashehu_LeShabbat
 *
 * @var array<string, mixed> $msl_account Resolved account content.
 * @var bool                 $msl_sent    Whether a reset link was just requested.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_open   = 1 === (int) ( $msl_account['open_on'] ?? 0 );
$msl_action = esc_url( admin_url( 'admin-post.php' ) );
?>
<header class="msl-ahead" data-msl-rise>
	<p class="msl-ghead__eyebrow"<?php msl_i18n( 'account', 'eyebrow' ); ?>><?php msl_the( $msl_account, 'eyebrow' ); ?></p>
	<h1 class="msl-heading"<?php msl_i18n( 'account', 'title' ); ?>><?php msl_the( $msl_account, 'title' ); ?></h1>
	<p class="msl-subheading"<?php msl_i18n( 'account', 'lead' ); ?>><?php msl_the( $msl_account, 'lead' ); ?></p>
</header>

<?php if ( $msl_sent ) : ?>
	<p class="msl-gnote" role="status"<?php msl_i18n( 'account', 'forgot_sent' ); ?>><?php msl_the( $msl_account, 'forgot_sent' ); ?></p>
<?php endif; ?>

<div class="msl-agrid">
	<section class="msl-acard" aria-labelledby="msl-account-login" data-msl-rise>
		<h2 class="msl-acard__title" id="msl-account-login"<?php msl_i18n( 'account', 'login_title' ); ?>><?php msl_the( $msl_account, 'login_title' ); ?></h2>

		<form class="msl-aform" method="post" action="<?php echo $msl_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>">
			<input type="hidden" name="action" value="msl_account_login">
			<?php wp_nonce_field( 'msl_account' ); ?>

			<p class="msl-field">
				<label class="msl-field__label" for="msl-login-email"<?php msl_i18n( 'account', 'f_email' ); ?>><?php msl_the( $msl_account, 'f_email' ); ?></label>
				<input type="email" class="msl-input" id="msl-login-email" name="email" autocomplete="username" required>
			</p>

			<p class="msl-field">
				<label class="msl-field__label" for="msl-login-password"<?php msl_i18n( 'account', 'f_password' ); ?>><?php msl_the( $msl_account, 'f_password' ); ?></label>
				<input type="password" class="msl-input" id="msl-login-password" name="password" autocomplete="current-password" required>
			</p>

			<button type="submit" class="msl-btn msl-btn--hero msl-btn--wide"<?php msl_i18n( 'account', 'login_cta' ); ?>><?php msl_the( $msl_account, 'login_cta' ); ?></button>
		</form>

		<?php
		/*
		 * The other door, when the campaign has configured it. Both lead to the
		 * same row in the same table, so somebody who signed in with Google and
		 * later opens a password account on the same address gets told the
		 * address is taken — which is true, and is the right thing to say.
		 */
		if ( MSL_Auth::enabled() ) :
			?>
			<p class="msl-aor"><span<?php msl_i18n( 'auth', 'sign_in' ); ?>><?php msl_the( MSL_Meta::get( 'auth' ), 'sign_in' ); ?></span></p>

			<a class="msl-btn msl-btn--dark msl-btn--wide" href="<?php echo esc_url( MSL_Auth::sign_in_url() ); ?>"
				<?php msl_i18n( 'auth', 'sign_in' ); ?>><?php msl_the( MSL_Meta::get( 'auth' ), 'sign_in' ); ?></a>
		<?php endif; ?>

		<?php
		/*
		 * The reset form, folded away. It is the least used of the three and
		 * the most alarming to look at, and <details> costs no script.
		 */
		?>
		<details class="msl-aforgot">
			<summary class="msl-aforgot__toggle"<?php msl_i18n( 'account', 'forgot_cta' ); ?>><?php msl_the( $msl_account, 'forgot_cta' ); ?></summary>

			<p class="msl-aforgot__lead"<?php msl_i18n( 'account', 'forgot_lead' ); ?>><?php msl_the( $msl_account, 'forgot_lead' ); ?></p>

			<form class="msl-aform" method="post" action="<?php echo $msl_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>">
				<input type="hidden" name="action" value="msl_account_forgot">
				<?php wp_nonce_field( 'msl_account' ); ?>

				<p class="msl-field">
					<label class="msl-field__label" for="msl-forgot-email"<?php msl_i18n( 'account', 'f_email' ); ?>><?php msl_the( $msl_account, 'f_email' ); ?></label>
					<input type="email" class="msl-input" id="msl-forgot-email" name="email" autocomplete="username" required>
				</p>

				<button type="submit" class="msl-btn msl-btn--dark msl-btn--wide"<?php msl_i18n( 'account', 'forgot_send' ); ?>><?php msl_the( $msl_account, 'forgot_send' ); ?></button>
			</form>
		</details>
	</section>

	<?php if ( $msl_open ) : ?>
		<section class="msl-acard msl-acard--soft" aria-labelledby="msl-account-register" data-msl-rise>
			<h2 class="msl-acard__title" id="msl-account-register"<?php msl_i18n( 'account', 'register_title' ); ?>><?php msl_the( $msl_account, 'register_title' ); ?></h2>
			<p class="msl-acard__lead"<?php msl_i18n( 'account', 'register_lead' ); ?>><?php msl_the( $msl_account, 'register_lead' ); ?></p>

			<form class="msl-aform" method="post" action="<?php echo $msl_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>">
				<input type="hidden" name="action" value="msl_account_register">
				<?php wp_nonce_field( 'msl_account' ); ?>

				<p class="msl-field">
					<label class="msl-field__label" for="msl-reg-name"<?php msl_i18n( 'account', 'f_name' ); ?>><?php msl_the( $msl_account, 'f_name' ); ?></label>
					<input type="text" class="msl-input" id="msl-reg-name" name="name" maxlength="120" autocomplete="name">
				</p>

				<p class="msl-field">
					<label class="msl-field__label" for="msl-reg-email"<?php msl_i18n( 'account', 'f_email' ); ?>><?php msl_the( $msl_account, 'f_email' ); ?></label>
					<input type="email" class="msl-input" id="msl-reg-email" name="email" autocomplete="username" required>
				</p>

				<p class="msl-field">
					<label class="msl-field__label" for="msl-reg-password"<?php msl_i18n( 'account', 'f_password_new' ); ?>><?php msl_the( $msl_account, 'f_password_new' ); ?></label>
					<input type="password" class="msl-input" id="msl-reg-password" name="password"
						minlength="<?php echo absint( MSL_Account::MIN_PASSWORD ); ?>" autocomplete="new-password" required
						aria-describedby="msl-reg-help">
					<span class="msl-field__help" id="msl-reg-help"<?php msl_i18n( 'account', 'password_help' ); ?>><?php msl_the( $msl_account, 'password_help' ); ?></span>
				</p>

				<button type="submit" class="msl-btn msl-btn--amber msl-btn--wide"<?php msl_i18n( 'account', 'register_cta' ); ?>><?php msl_the( $msl_account, 'register_cta' ); ?></button>
			</form>
		</section>
	<?php endif; ?>
</div>
