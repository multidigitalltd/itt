<?php
/**
 * The hero: halo, points of light, eyebrow, headline, call to action, counter.
 *
 * @package Mashehu_LeShabbat
 *
 * @var array<string, mixed> $msl Resolved hero content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_stats = MSL_Stats::all( (int) get_the_ID() );
?>
<section class="msl-hero" aria-labelledby="msl-hero-title">
	<canvas class="msl-hero__halo" data-msl-canvas="halo" aria-hidden="true"></canvas>

	<?php
	/*
	 * Decorative: the points of light carry nothing the headline does not, so
	 * the canvas is hidden from assistive technology rather than described.
	 *
	 * They drift in from every edge and rise, kindling as they come. One canvas
	 * in the shared render loop, not two dozen animated elements — and it pauses
	 * itself the moment the hero scrolls out of view.
	 */
	?>
	<canvas class="msl-hero__motes" data-msl-canvas="motes" aria-hidden="true"></canvas>

	<div class="msl-hero__content">
		<p class="msl-eyebrow">
			<span class="msl-eyebrow__rule" aria-hidden="true"></span>
			<?php msl_candle_svg( 2.6, 1.9 ); ?>
			<span class="msl-eyebrow__label"<?php msl_i18n( 'hero', 'eyebrow' ); ?>><?php msl_the( $msl, 'eyebrow' ); ?></span>
			<?php msl_candle_svg( 2.9, 2.2, 0.6 ); ?>
			<span class="msl-eyebrow__rule" aria-hidden="true"></span>
		</p>

		<h1 class="msl-hero__title" id="msl-hero-title">
			<span<?php msl_i18n( 'hero', 'h1a' ); ?>><?php msl_the( $msl, 'h1a' ); ?></span>
			<span class="msl-hero__title-accent"<?php msl_i18n( 'hero', 'h1b' ); ?>><?php msl_the( $msl, 'h1b' ); ?></span>
		</h1>

		<p class="msl-hero__sub"<?php msl_i18n( 'hero', 'sub' ); ?>><?php msl_the( $msl, 'sub' ); ?></p>

		<div class="msl-hero__actions">
			<button type="button" class="msl-btn msl-btn--hero" data-msl-open-join
				<?php msl_i18n( 'chrome', 'cta' ); ?>><?php msl_the( MSL_Meta::get( 'chrome' ), 'cta' ); ?></button>

			<p class="msl-counterchip">
				<span class="msl-counterchip__value" data-msl-counter><?php echo esc_html( msl_num( $msl_stats['participants'] ) ); ?></span>
				<span class="msl-counterchip__label"<?php msl_i18n( 'hero', 'already' ); ?>><?php msl_the( $msl, 'already' ); ?></span>
			</p>
		</div>
	</div>
</section>
