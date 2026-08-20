<?php
/**
 * The share card.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_screens  = MSL_Meta::get( 'screens' );
$msl_referral = MSL_Meta::get( 'referral' );
$msl_chrome   = MSL_Meta::get( 'chrome' );
?>
<div class="msl-screen msl-screen--result" data-msl-screen-panel="result" role="dialog" aria-modal="true"
	aria-labelledby="msl-result-title" hidden>

	<div class="msl-result">
		<div class="msl-card">
			<div class="msl-card__art">
				<canvas data-msl-canvas="card" aria-hidden="true"></canvas>
			</div>

			<div class="msl-card__body">
				<h2 class="msl-card__title" id="msl-result-title"<?php msl_i18n( 'screens', 'res_title' ); ?>><?php msl_the( $msl_screens, 'res_title' ); ?></h2>

				<div class="msl-card__thing">
					<p class="msl-card__label"<?php msl_i18n( 'screens', 'my_thing_label' ); ?>><?php msl_the( $msl_screens, 'my_thing_label' ); ?></p>
					<p class="msl-card__value" data-msl-my-thing></p>
					<p class="msl-card__dedication" data-msl-my-dedication hidden></p>
				</div>

				<p class="msl-card__count">
					<span<?php msl_i18n( 'screens', 'we_are' ); ?>><?php msl_the( $msl_screens, 'we_are' ); ?></span>
					<span class="msl-card__figure" data-msl-counter>0</span>
				</p>

				<p class="msl-card__ask"<?php msl_i18n( 'screens', 'ask_others' ); ?>><?php msl_the( $msl_screens, 'ask_others' ); ?></p>

				<p class="msl-card__brand">
					<span class="msl-logo__mark msl-logo__mark--small" aria-hidden="true"><span class="msl-logo__dot"></span></span>
					<span<?php msl_i18n( 'chrome', 'brand' ); ?>><?php msl_the( $msl_chrome, 'brand' ); ?></span>
				</p>
			</div>
		</div>

		<div class="msl-result__actions">
			<a class="msl-btn msl-btn--whatsapp" data-msl-whatsapp
				data-msl-template="<?php echo esc_attr( msl_t( $msl_referral, 'wa_message' ) ); ?>"
				href="<?php echo esc_url( 'https://wa.me/?text=' . rawurlencode( sprintf( msl_t( $msl_referral, 'wa_message' ), home_url( '/' ) ) ) ); ?>"
				target="_blank" rel="noopener"
				<?php msl_i18n( 'referral', 'wa_send' ); ?>><?php msl_the( $msl_referral, 'wa_send' ); ?></a>

			<div class="msl-result__row">
				<button type="button" class="msl-btn msl-btn--soft" data-msl-copy
					data-msl-copied="<?php echo esc_attr( msl_t( $msl_referral, 'copied_btn' ) ); ?>"
					<?php msl_i18n( 'referral', 'copy_btn' ); ?>><?php msl_the( $msl_referral, 'copy_btn' ); ?></button>

				<button type="button" class="msl-btn msl-btn--soft" data-msl-share
					<?php msl_i18n( 'referral', 'share_more' ); ?>><?php msl_the( $msl_referral, 'share_more' ); ?></button>
			</div>
		</div>

		<div class="msl-result__link">
			<p class="msl-result__link-label" id="msl-result-link-label"<?php msl_i18n( 'referral', 'your_link' ); ?>><?php msl_the( $msl_referral, 'your_link' ); ?></p>
			<p class="msl-result__link-value" data-msl-link
				data-msl-url="<?php echo esc_url( home_url( '/' ) ); ?>"
				aria-describedby="msl-result-link-label"><?php echo esc_html( (string) preg_replace( '#^https?://#', '', untrailingslashit( home_url( '/' ) ) ) ); ?></p>

			<p class="msl-result__refcount" id="msl-result-ref-label">
				<span data-msl-refcount>0</span>
				<span<?php msl_i18n( 'referral', 'ref_label' ); ?>><?php msl_the( $msl_referral, 'ref_label' ); ?></span>
			</p>

			<div class="msl-result__bar" role="progressbar" aria-labelledby="msl-result-ref-label"
				aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-msl-refbar>
				<span class="msl-result__fill" style="width:0%"></span>
			</div>

			<p class="msl-result__next" data-msl-refnext
				data-msl-template="<?php echo esc_attr( msl_t( $msl_referral, 'next_goal' ) ); ?>"
				<?php msl_i18n( 'referral', 'next_goal' ); ?>></p>
		</div>

		<button type="button" class="msl-btn msl-btn--block msl-btn--ink" data-msl-goto="home"
			<?php msl_i18n( 'screens', 'back_home' ); ?>><?php msl_the( $msl_screens, 'back_home' ); ?></button>
	</div>
</div>
