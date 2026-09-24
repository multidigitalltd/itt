<?php
/**
 * The personal area, to somebody who is signed in.
 *
 * Three things, in the order they are wanted: the link, the groups, the
 * account itself. Nothing here is fetched by script — the page is never cached
 * (see the template), so the server can simply render this person's own
 * numbers, and the area works with JavaScript switched off entirely.
 *
 * @package Mashehu_LeShabbat
 *
 * @var array<string, mixed> $msl_account Resolved account content.
 * @var array<string, mixed> $msl_person  The signed-in person.
 * @var bool                 $msl_saved   Whether a password was just changed.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_link   = MSL_Joins::share_url( (string) $msl_person['referral_code'] );
$msl_shown  = (string) preg_replace( '#^https?://#', '', untrailingslashit( $msl_link ) );
$msl_count  = MSL_Joins::referral_count( (string) $msl_person['referral_code'] );
$msl_mine   = MSL_Groups::for_person( (int) $msl_person['id'] );
$msl_groups = MSL_Meta::get( 'groups', MSL_Importer::page_id( 'groups' ) );
$msl_name   = trim( (string) $msl_person['display_name'] );
?>
<header class="msl-ahead" data-msl-rise>
	<p class="msl-ghead__eyebrow"><?php echo esc_html( sprintf( msl_t( $msl_account, 'hello' ), '' !== $msl_name ? $msl_name : '' ) ); ?></p>
	<h1 class="msl-heading"<?php msl_i18n( 'account', 'title' ); ?>><?php msl_the( $msl_account, 'title' ); ?></h1>

	<p class="msl-ahead__out">
		<a href="<?php echo esc_url( MSL_Auth::sign_out_url() ); ?>"<?php msl_i18n( 'account', 'signout' ); ?>><?php msl_the( $msl_account, 'signout' ); ?></a>
	</p>
</header>

<section class="msl-acard msl-acard--link" aria-labelledby="msl-account-link" data-msl-rise>
	<h2 class="msl-acard__title" id="msl-account-link"<?php msl_i18n( 'account', 'link_title' ); ?>><?php msl_the( $msl_account, 'link_title' ); ?></h2>
	<p class="msl-acard__lead"<?php msl_i18n( 'account', 'link_lead' ); ?>><?php msl_the( $msl_account, 'link_lead' ); ?></p>

	<p class="msl-alink" data-msl-link data-msl-url="<?php echo esc_url( $msl_link ); ?>"><?php echo esc_html( $msl_shown ); ?></p>

	<p class="msl-acount">
		<span class="msl-acount__value"><?php echo esc_html( msl_num( $msl_count ) ); ?></span>
		<span class="msl-acount__label"<?php msl_i18n( 'account', 'link_count' ); ?>><?php msl_the( $msl_account, 'link_count' ); ?></span>
	</p>

	<?php
	/*
	 * Copying needs a script, so the button only appears when one is running —
	 * but the link itself is above it as selectable text, which is how it works
	 * without. A button that silently does nothing is worse than no button.
	 */
	?>
	<div class="msl-acard__actions">
		<button type="button" class="msl-btn msl-btn--dark" data-msl-copy hidden
			data-msl-copied="<?php echo esc_attr( msl_t( $msl_account, 'copied' ) ); ?>"
			<?php msl_i18n( 'account', 'copy_cta' ); ?>><?php msl_the( $msl_account, 'copy_cta' ); ?></button>
	</div>
</section>

<section class="msl-asection" aria-labelledby="msl-account-groups" data-msl-rise>
	<div class="msl-asection__head">
		<h2 class="msl-asection__title" id="msl-account-groups"<?php msl_i18n( 'account', 'groups_title' ); ?>><?php msl_the( $msl_account, 'groups_title' ); ?></h2>

		<?php if ( '' !== msl_groups_url() ) : ?>
			<a class="msl-btn msl-btn--amber" href="<?php echo esc_url( msl_groups_url( 'msl-open' ) ); ?>"
				<?php msl_i18n( 'account', 'groups_open' ); ?>><?php msl_the( $msl_account, 'groups_open' ); ?></a>
		<?php endif; ?>
	</div>

	<?php if ( array() === $msl_mine ) : ?>
		<p class="msl-asection__empty"<?php msl_i18n( 'account', 'groups_empty' ); ?>><?php msl_the( $msl_account, 'groups_empty' ); ?></p>
	<?php else : ?>
		<ul class="msl-alist">
			<?php
			foreach ( $msl_mine as $msl_group ) :
				$msl_pct = msl_group_pct( $msl_group );
				$msl_ded = msl_group_dedication( $msl_group, $msl_groups );
				?>
				<li class="msl-arow">
					<div class="msl-arow__body">
						<p class="msl-arow__title"><?php echo esc_html( (string) $msl_group['title'] ); ?></p>

						<?php if ( '' !== $msl_ded ) : ?>
							<p class="msl-arow__ded"><?php echo esc_html( $msl_ded ); ?></p>
						<?php endif; ?>

						<p class="msl-arow__figure">
							<?php
							echo esc_html(
								sprintf(
									msl_t( $msl_account, 'group_progress' ),
									msl_num( (int) $msl_group['count'] ),
									msl_num( (int) $msl_group['target'] )
								)
							);
							?>
						</p>

						<div class="msl-arow__track" role="progressbar" aria-valuemin="0" aria-valuemax="100"
							aria-valuenow="<?php echo esc_attr( (string) $msl_pct ); ?>"
							aria-label="<?php echo esc_attr( (string) $msl_group['title'] ); ?>">
							<span class="msl-arow__fill" style="width:<?php echo esc_attr( (string) $msl_pct ); ?>%"></span>
						</div>

						<?php if ( MSL_Groups::PENDING === $msl_group['status'] ) : ?>
							<p class="msl-arow__state"<?php msl_i18n( 'groups', 'state_pending' ); ?>><?php msl_the( $msl_groups, 'state_pending' ); ?></p>
						<?php elseif ( MSL_Groups::CLOSED === $msl_group['status'] ) : ?>
							<p class="msl-arow__state"<?php msl_i18n( 'groups', 'state_closed' ); ?>><?php msl_the( $msl_groups, 'state_closed' ); ?></p>
						<?php endif; ?>
					</div>

					<div class="msl-arow__actions">
						<a class="msl-btn msl-btn--dark" href="<?php echo esc_url( add_query_arg( 'msl_manage', (string) $msl_group['code'], MSL_Account::page_url() ) ); ?>"
							<?php msl_i18n( 'account', 'group_manage' ); ?>><?php msl_the( $msl_account, 'group_manage' ); ?></a>

						<a class="msl-textbtn" href="<?php echo esc_url( MSL_Groups::url( (string) $msl_group['code'] ) ); ?>"
							<?php msl_i18n( 'account', 'group_view' ); ?>><?php msl_the( $msl_account, 'group_view' ); ?></a>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>

<?php if ( MSL_Account::has_password( $msl_person ) ) : ?>
	<section class="msl-asection" id="msl-account-password" aria-labelledby="msl-account-password-title" data-msl-rise>
		<h2 class="msl-asection__title" id="msl-account-password-title"<?php msl_i18n( 'account', 'password_title' ); ?>><?php msl_the( $msl_account, 'password_title' ); ?></h2>

		<?php if ( $msl_saved ) : ?>
			<p class="msl-gnote" role="status"<?php msl_i18n( 'account', 'password_saved' ); ?>><?php msl_the( $msl_account, 'password_saved' ); ?></p>
		<?php endif; ?>

		<form class="msl-aform msl-aform--inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="msl_account_password">
			<?php wp_nonce_field( 'msl_account' ); ?>

			<p class="msl-field">
				<label class="msl-field__label" for="msl-pw-current"<?php msl_i18n( 'account', 'f_password_current' ); ?>><?php msl_the( $msl_account, 'f_password_current' ); ?></label>
				<input type="password" class="msl-input" id="msl-pw-current" name="current" autocomplete="current-password" required>
			</p>

			<p class="msl-field">
				<label class="msl-field__label" for="msl-pw-new"<?php msl_i18n( 'account', 'f_password_new' ); ?>><?php msl_the( $msl_account, 'f_password_new' ); ?></label>
				<input type="password" class="msl-input" id="msl-pw-new" name="password"
					minlength="<?php echo absint( MSL_Account::MIN_PASSWORD ); ?>" autocomplete="new-password" required>
			</p>

			<button type="submit" class="msl-btn msl-btn--dark"<?php msl_i18n( 'account', 'password_cta' ); ?>><?php msl_the( $msl_account, 'password_cta' ); ?></button>
		</form>
	</section>
<?php endif; ?>
