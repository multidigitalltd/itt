<?php
/**
 * The sticky header.
 *
 * @package Mashehu_LeShabbat
 *
 * @var array<string, mixed> $msl Resolved chrome content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_campaign = MSL_Meta::get( 'campaign' );
$msl_auth     = MSL_Meta::get( 'auth' );
$msl_person   = MSL_Auth::current();
$msl_nav      = MSL_Meta::get( 'nav', MSL_Importer::page_id() );

// Asked of the same function that prints them, so the button can never appear
// over a list that turned out to have nothing in it.
$msl_has_nav  = array() !== msl_nav_rows( $msl_nav );
?>
<header class="msl-header">
	<div class="msl-header__inner">
		<a class="msl-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php msl_logo( $msl ); ?>
			<span class="msl-brand__word"<?php msl_i18n( 'chrome', 'brand' ); ?>><?php msl_the( $msl, 'brand' ); ?></span>
		</a>

		<?php if ( $msl_has_nav ) : ?>
			<nav class="msl-menu" data-msl-menu aria-label="<?php echo esc_attr( msl_t( $msl_nav, 'menu_open' ) ); ?>">
				<button type="button" class="msl-menu__toggle" data-msl-menu-toggle
					aria-expanded="false" aria-controls="msl-menu-list">
					<span class="msl-menu__bars" aria-hidden="true"></span>
					<span class="msl-menu__word"<?php msl_i18n( 'nav', 'menu_open' ); ?>><?php msl_the( $msl_nav, 'menu_open' ); ?></span>
				</button>

				<ul class="msl-menu__list" id="msl-menu-list" hidden>
					<?php msl_nav_links( $msl_nav ); ?>
				</ul>
			</nav>
		<?php endif; ?>

		<div class="msl-header__actions">
			<button type="button" class="msl-langtoggle" data-msl-lang-toggle
				aria-label="<?php esc_attr_e( 'החלפת שפת האתר', 'mashehu-leshabbat' ); ?>">
				<span data-msl-lang-label><?php echo esc_html( (string) $msl[ 'lang_btn_' . MSL_I18N::lang() ] ); ?></span>
			</button>

			<p class="msl-countdown">
				<span class="msl-countdown__dot" aria-hidden="true"></span>
				<span class="msl-countdown__text" data-msl-countdown><?php echo esc_html( msl_countdown( $msl, $msl_campaign ) ); ?></span>
			</p>

			<?php if ( MSL_Auth::enabled() ) : ?>
				<?php if ( null !== $msl_person ) : ?>
					<div class="msl-account" data-msl-account>
						<button type="button" class="msl-account__btn" data-msl-account-toggle
							aria-expanded="false" aria-controls="msl-account-menu">
							<?php if ( '' !== (string) $msl_person['avatar_url'] ) : ?>
								<img class="msl-account__avatar" src="<?php echo esc_url( (string) $msl_person['avatar_url'] ); ?>"
									alt="" width="28" height="28" loading="lazy" referrerpolicy="no-referrer">
							<?php else : ?>
								<span class="msl-account__avatar msl-account__avatar--blank" aria-hidden="true"></span>
							<?php endif; ?>
							<span class="msl-account__name"><?php echo esc_html( (string) $msl_person['display_name'] ); ?></span>
						</button>

						<div class="msl-account__menu" id="msl-account-menu" hidden>
							<p class="msl-account__as">
								<span<?php msl_i18n( 'auth', 'signed_in_as' ); ?>><?php msl_the( $msl_auth, 'signed_in_as' ); ?></span>
								<strong><?php echo esc_html( (string) $msl_person['display_name'] ); ?></strong>
							</p>
							<button type="button" class="msl-account__item" data-msl-open-invite
								<?php msl_i18n( 'auth', 'invite_cta' ); ?>><?php msl_the( $msl_auth, 'invite_cta' ); ?></button>
							<a class="msl-account__item" href="<?php echo esc_url( MSL_Auth::sign_out_url() ); ?>"
								<?php msl_i18n( 'auth', 'sign_out' ); ?>><?php msl_the( $msl_auth, 'sign_out' ); ?></a>
						</div>
					</div>
				<?php else : ?>
					<a class="msl-btn msl-btn--quiet msl-header__signin" href="<?php echo esc_url( MSL_Auth::sign_in_url() ); ?>"
						<?php msl_i18n( 'auth', 'sign_in' ); ?>><?php msl_the( $msl_auth, 'sign_in' ); ?></a>
				<?php endif; ?>
			<?php endif; ?>

			<button type="button" class="msl-btn msl-btn--ink msl-header__cta" data-msl-open-join
				<?php msl_i18n( 'chrome', 'cta' ); ?>><?php msl_the( $msl, 'cta' ); ?></button>
		</div>
	</div>
</header>
