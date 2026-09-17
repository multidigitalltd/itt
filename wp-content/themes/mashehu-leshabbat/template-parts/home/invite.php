<?php
/**
 * The invitation popup.
 *
 * Asks the visitor to carry the campaign to the people they know, and hands
 * them the link that makes that countable. It is rendered into the page rather
 * than fetched, so it is part of the same cached HTML as everything else and
 * costs no request; when and whether it opens is the script's decision.
 *
 * A signed-in person sees their own link straight away. Everyone else sees the
 * reason to want one and the way to get it, which is the only thing sign-in is
 * asked to buy here.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_invite   = MSL_Meta::get( 'auth' );
$msl_referral = MSL_Meta::get( 'referral' );
$msl_person   = MSL_Auth::current();
$msl_own      = MSL_Auth::personal_link();
$msl_shown    = '' !== $msl_own ? (string) preg_replace( '#^https?://#', '', untrailingslashit( $msl_own ) ) : '';
?>
<div class="msl-invite" data-msl-modal="invite" role="dialog" aria-modal="true"
	aria-labelledby="msl-invite-title" hidden>

	<div class="msl-invite__backdrop" data-msl-close-invite aria-hidden="true"></div>

	<div class="msl-invite__card" role="document">
		<button type="button" class="msl-invite__close" data-msl-close-invite
			aria-label="<?php echo esc_attr( msl_t( MSL_Meta::get( 'screens' ), 'close' ) ); ?>">
			<span aria-hidden="true">×</span>
		</button>

		<span class="msl-invite__flame" aria-hidden="true"><?php msl_candle_svg( 2.6, 1.9 ); ?></span>

		<h2 class="msl-invite__title" id="msl-invite-title"<?php msl_i18n( 'auth', 'invite_title' ); ?>><?php msl_the( $msl_invite, 'invite_title' ); ?></h2>

		<p class="msl-invite__body"<?php msl_i18n( 'auth', 'invite_body' ); ?>><?php msl_the( $msl_invite, 'invite_body' ); ?></p>

		<?php if ( null !== $msl_person && '' !== $msl_own ) : ?>
			<p class="msl-invite__link" data-msl-link data-msl-url="<?php echo esc_url( $msl_own ); ?>"><?php echo esc_html( $msl_shown ); ?></p>

			<div class="msl-invite__actions">
				<a class="msl-btn msl-btn--whatsapp" data-msl-whatsapp
					data-msl-template="<?php echo esc_attr( msl_t( $msl_referral, 'wa_message' ) ); ?>"
					href="<?php echo esc_url( 'https://wa.me/?text=' . rawurlencode( sprintf( msl_t( $msl_referral, 'wa_message' ), $msl_own ) ) ); ?>"
					target="_blank" rel="noopener"
					<?php msl_i18n( 'referral', 'wa_send' ); ?>><?php msl_the( $msl_referral, 'wa_send' ); ?></a>

				<button type="button" class="msl-btn msl-btn--dark" data-msl-copy
					data-msl-copied="<?php echo esc_attr( msl_t( $msl_referral, 'copied_btn' ) ); ?>"
					<?php msl_i18n( 'referral', 'copy_btn' ); ?>><?php msl_the( $msl_referral, 'copy_btn' ); ?></button>
			</div>
		<?php elseif ( MSL_Auth::enabled() ) : ?>
			<div class="msl-invite__actions">
				<a class="msl-btn msl-btn--amber msl-btn--wide" href="<?php echo esc_url( MSL_Auth::sign_in_url() ); ?>"
					<?php msl_i18n( 'auth', 'link_locked_cta' ); ?>><?php msl_the( $msl_invite, 'link_locked_cta' ); ?></a>
			</div>
		<?php else : ?>
			<div class="msl-invite__actions">
				<button type="button" class="msl-btn msl-btn--amber msl-btn--wide" data-msl-open-join
					<?php msl_i18n( 'auth', 'invite_cta' ); ?>><?php msl_the( $msl_invite, 'invite_cta' ); ?></button>
			</div>
		<?php endif; ?>

		<button type="button" class="msl-textbtn msl-invite__dismiss" data-msl-close-invite
			<?php msl_i18n( 'auth', 'invite_dismiss' ); ?>><?php msl_the( $msl_invite, 'invite_dismiss' ); ?></button>
	</div>
</div>
