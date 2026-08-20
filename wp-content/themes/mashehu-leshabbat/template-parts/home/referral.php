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

$msl_link  = home_url( '/' );
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
<section class="msl-referral" aria-labelledby="msl-referral-title">
	<div class="msl-referral__head">
		<h2 class="msl-heading" id="msl-referral-title"<?php msl_i18n( 'referral', 'title' ); ?>><?php msl_the( $msl, 'title' ); ?></h2>
		<p class="msl-subheading"<?php msl_i18n( 'referral', 'sub' ); ?>><?php msl_the( $msl, 'sub' ); ?></p>
	</div>

	<div class="msl-referral__grid">
		<div class="msl-linkcard">
			<p class="msl-linkcard__label" id="msl-link-label"<?php msl_i18n( 'referral', 'your_link' ); ?>><?php msl_the( $msl, 'your_link' ); ?></p>

			<p class="msl-linkcard__value" data-msl-link
				data-msl-url="<?php echo esc_url( $msl_link ); ?>"
				aria-describedby="msl-link-label"><?php echo esc_html( $msl_shown ); ?></p>

			<div class="msl-linkcard__actions">
				<a class="msl-btn msl-btn--whatsapp" data-msl-whatsapp
					data-msl-template="<?php echo esc_attr( msl_t( $msl, 'wa_message' ) ); ?>"
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
