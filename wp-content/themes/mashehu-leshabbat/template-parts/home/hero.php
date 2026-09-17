<?php
/**
 * The hero: halo, candle field, eyebrow, headline, call to action, live counter.
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
	 * Decorative: the candles carry nothing the headline does not, so they are
	 * hidden from assistive technology rather than read out two dozen times.
	 *
	 * They live in the gutters either side of the headline. The gutter exists
	 * because the headline is capped at 660px, and that cap is what makes room
	 * for them. Each one lights, burns and goes out on its own rhythm, and the
	 * script moves a candle to a new place while it is dark — so the field keeps
	 * shifting rather than pulsing on the spot.
	 */
	msl_hero_candles( (int) $msl[ 'candle_count' ] );
	?>

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
