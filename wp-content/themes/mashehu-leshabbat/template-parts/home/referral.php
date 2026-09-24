<?php
/**
 * The share section: the personal link, and how far it has carried.
 *
 * Before anyone joins there is no personal code yet, so the server renders the
 * campaign's own address and a count of zero; the script swaps in the visitor's
 * real code once they have one. That is what keeps this page cacheable.
 *
 * @package Mashehu_LeShabbat
 *
 * @var array<string, mixed> $msl Resolved referral content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/*
 * A personal link is shown only to somebody who has one.
 *
 * This card used to fall back to the campaign's own address for everybody else
 * — the site's front page, printed under the words "your personal link". That
 * is not a personal link, it carries no attribution, and presenting it as one
 * is worse than showing nothing at all.
 *
 * So there are two states in the markup. A signed-in person gets theirs from
 * the server. Everyone else gets the invitation, and the script swaps in the
 * real thing the moment this browser turns out to have a code — that swap is
 * what keeps the page cacheable, because the cached HTML never contains
 * anybody's code.
 */
$msl_auth  = MSL_Meta::get( 'auth' );
$msl_own   = MSL_Auth::personal_link();
$msl_has   = '' !== $msl_own;
$msl_login = MSL_Auth::enabled();
$msl_link  = $msl_has ? $msl_own : home_url( '/' );
$msl_shown = (string) preg_replace( '#^https?://#', '', untrailingslashit( $msl_link ) );

$msl_milestones = array();

foreach ( (array) $msl['milestones'] as $msl_row ) {
	if ( is_array( $msl_row ) && (int) ( $msl_row['value'] ?? 0 ) > 0 ) {
		$msl_milestones[] = (int) $msl_row['value'];
	}
}

sort( $msl_milestones );

$msl_next = $msl_milestones[0] ?? 0;
?>
<section id="msl-referral" class="msl-referral" aria-labelledby="msl-referral-title" data-msl-rise>
	<div class="msl-referral__head">
		<h2 class="msl-heading" id="msl-referral-title"<?php msl_i18n( 'referral', 'title' ); ?>><?php msl_the( $msl, 'title' ); ?></h2>
		<p class="msl-subheading"<?php msl_i18n( 'referral', 'sub' ); ?>><?php msl_the( $msl, 'sub' ); ?></p>
	</div>

	<div class="msl-referral__grid">
		<div class="msl-linkcard">
			<p class="msl-linkcard__label" id="msl-link-label"<?php msl_i18n( 'referral', 'your_link' ); ?>><?php msl_the( $msl, 'your_link' ); ?></p>

			<div class="msl-linkcard__get" data-msl-link-get<?php echo $msl_has ? ' hidden' : ''; ?>>
				<?php if ( $msl_login ) : ?>
					<p class="msl-linkcard__locked"<?php msl_i18n( 'auth', 'link_locked' ); ?>><?php msl_the( $msl_auth, 'link_locked' ); ?></p>

					<a class="msl-btn msl-btn--amber msl-btn--wide" href="<?php echo esc_url( MSL_Auth::sign_in_url() ); ?>"
						<?php msl_i18n( 'auth', 'link_locked_cta' ); ?>><?php msl_the( $msl_auth, 'link_locked_cta' ); ?></a>
				<?php else : ?>
					<?php
					// No sign-in configured: joining is the only way to get a
					// code, so that is what the button has to offer.
					?>
					<p class="msl-linkcard__locked"<?php msl_i18n( 'auth', 'link_join' ); ?>><?php msl_the( $msl_auth, 'link_join' ); ?></p>

					<button type="button" class="msl-btn msl-btn--amber msl-btn--wide" data-msl-open-join
						<?php msl_i18n( 'auth', 'link_join_cta' ); ?>><?php msl_the( $msl_auth, 'link_join_cta' ); ?></button>
				<?php endif; ?>
			</div>

			<div class="msl-linkcard__ready" data-msl-link-ready<?php echo $msl_has ? '' : ' hidden'; ?>>
			<p class="msl-linkcard__value" data-msl-link
				data-msl-url="<?php echo esc_url( $msl_link ); ?>"
				aria-describedby="msl-link-label"><?php echo esc_html( $msl_shown ); ?></p>

			<div class="msl-linkcard__actions">
				<a class="msl-btn msl-btn--whatsapp" data-msl-whatsapp
					data-msl-template="<?php echo esc_attr( msl_t( $msl, 'wa_message' ) ); ?>"
					data-msl-template-i18n="referral.wa_message"
					href="<?php echo esc_url( 'https://wa.me/?text=' . rawurlencode( sprintf( msl_t( $msl, 'wa_message' ), $msl_link ) ) ); ?>"
					target="_blank" rel="noopener"
					<?php msl_i18n( 'referral', 'wa_send' ); ?>><?php msl_the( $msl, 'wa_send' ); ?></a>

				<div class="msl-linkcard__row">
					<button type="button" class="msl-btn msl-btn--dark" data-msl-copy
						data-msl-copied="<?php echo esc_attr( msl_t( $msl, 'copied_btn' ) ); ?>"
						<?php msl_i18n( 'referral', 'copy_btn' ); ?>><?php msl_the( $msl, 'copy_btn' ); ?></button>

					<button type="button" class="msl-btn msl-btn--dark" data-msl-share
						<?php msl_i18n( 'referral', 'share_more' ); ?>><?php msl_the( $msl, 'share_more' ); ?></button>
				</div>
			</div>
			</div>
		</div>

		<div class="msl-refcount">
			<p class="msl-refcount__value" data-msl-refcount>0</p>
			<p class="msl-refcount__label" id="msl-refcount-label"<?php msl_i18n( 'referral', 'ref_label' ); ?>><?php msl_the( $msl, 'ref_label' ); ?></p>

			<div class="msl-refcount__bar"
				role="progressbar"
				aria-labelledby="msl-refcount-label"
				aria-valuemin="0"
				aria-valuemax="<?php echo esc_attr( (string) max( 1, $msl_next ) ); ?>"
				aria-valuenow="0"
				data-msl-refbar>
				<span class="msl-refcount__fill" style="width:0%"></span>
			</div>

			<p class="msl-refcount__next" data-msl-refnext
				data-msl-template="<?php echo esc_attr( msl_t( $msl, 'next_goal' ) ); ?>"
				<?php msl_i18n( 'referral', 'next_goal' ); ?>>
				<?php printf( esc_html( msl_t( $msl, 'next_goal' ) ), esc_html( msl_num( $msl_next ) ) ); ?>
			</p>

			<?php if ( array() !== $msl_milestones ) : ?>
				<?php // Partnership, not competition: these are shared goals, and there is no public ranking of people anywhere on the site. ?>
				<ul class="msl-milestones" data-msl-milestones>
					<?php foreach ( $msl_milestones as $msl_value ) : ?>
						<li class="msl-milestone" data-msl-milestone="<?php echo esc_attr( (string) $msl_value ); ?>">
							<span class="msl-milestone__mark" aria-hidden="true"></span>
							<span><?php echo esc_html( $msl_value . ' ' . msl_t( $msl, 'friends' ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>
</section>
