<?php
/**
 * The split stage: the artwork on one side, the candle wall on the other.
 *
 * @package Mashehu_LeShabbat
 *
 * @var array<string, mixed> $msl Resolved stage content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_stats   = MSL_Stats::all( (int) get_the_ID() );
$msl_screens = MSL_Meta::get( 'screens' );

$msl_summary = sprintf(
	msl_t( $msl_screens, 'art_summary' ),
	msl_num( $msl_stats['participants'] ),
	$msl_stats['pct']
);
?>
<section class="msl-stage" aria-labelledby="msl-stage-title">
	<h2 class="msl-visually-hidden" id="msl-stage-title"<?php msl_i18n( 'stage', 'art_title' ); ?>><?php msl_the( $msl, 'art_title' ); ?></h2>

	<div class="msl-panel msl-panel--art">
		<?php
		/*
		 * The canvas is decoration to a screen reader — the same numbers are in
		 * the summary right after it, and in the overlay text above it.
		 */
		?>
		<canvas class="msl-panel__canvas" data-msl-canvas="hero" aria-hidden="true"></canvas>
		<?php // The scrim has to be the first thing after the canvas, so every overlay below paints on top of it. ?>
		<div class="msl-panel__scrim" aria-hidden="true"></div>

		<p class="msl-visually-hidden" data-msl-art-summary><?php echo esc_html( $msl_summary ); ?></p>

		<div class="msl-panel__lead">
			<p class="msl-panel__title"<?php msl_i18n( 'stage', 'art_title' ); ?>><?php msl_the( $msl, 'art_title' ); ?></p>
			<p class="msl-panel__sub">
				<span class="msl-panel__figure" data-msl-counter><?php echo esc_html( msl_num( $msl_stats['participants'] ) ); ?></span>
				<span<?php msl_i18n( 'stage', 'candles_now' ); ?>><?php msl_the( $msl, 'candles_now' ); ?></span>
			</p>
		</div>

		<p class="msl-panel__pct">
			<span class="msl-panel__pct-value"><span data-msl-pct><?php echo esc_html( (string) $msl_stats['pct'] ); ?></span>%</span>
			<span class="msl-panel__pct-label"<?php msl_i18n( 'stage', 'completed_short' ); ?>><?php msl_the( $msl, 'completed_short' ); ?></span>
		</p>

		<div class="msl-panel__action">
			<button type="button" class="msl-btn msl-btn--light" data-msl-goto="art"
				<?php msl_i18n( 'stage', 'enter_art' ); ?>><?php msl_the( $msl, 'enter_art' ); ?></button>
		</div>
	</div>

	<div class="msl-panel msl-panel--wall">
		<canvas class="msl-panel__canvas" data-msl-canvas="wallpanel" aria-hidden="true"></canvas>
		<div class="msl-panel__scrim" aria-hidden="true"></div>

		<div class="msl-panel__lead msl-panel__lead--end">
			<p class="msl-panel__title"<?php msl_i18n( 'stage', 'wall' ); ?>><?php msl_the( $msl, 'wall' ); ?></p>
			<p class="msl-panel__sub"<?php msl_i18n( 'stage', 'wall_count' ); ?>><?php msl_the( $msl, 'wall_count' ); ?></p>
		</div>

		<div class="msl-panel__action">
			<button type="button" class="msl-btn msl-btn--ghost" data-msl-goto="wall"
				<?php msl_i18n( 'stage', 'wall' ); ?>><?php msl_the( $msl, 'wall' ); ?></button>
		</div>
	</div>
</section>
