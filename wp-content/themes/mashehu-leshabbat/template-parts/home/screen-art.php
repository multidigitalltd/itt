<?php
/**
 * The artwork viewer.
 *
 * Kept in the DOM rather than fetched, because the canvas has to stay mounted
 * through the whole join sequence.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_screens = MSL_Meta::get( 'screens' );
$msl_stage   = MSL_Meta::get( 'stage' );
$msl_stats   = MSL_Stats::all( (int) get_the_ID() );
?>
<div class="msl-screen msl-screen--art" data-msl-screen-panel="art" role="dialog" aria-modal="true"
	aria-labelledby="msl-art-title" hidden>

	<canvas class="msl-screen__canvas" data-msl-canvas="artview" data-msl-art-surface aria-hidden="true"></canvas>

	<div class="msl-screen__top">
		<button type="button" class="msl-btn msl-btn--light msl-back" data-msl-goto="home"
			<?php msl_i18n( 'screens', 'back' ); ?>><?php msl_the( $msl_screens, 'back' ); ?></button>

		<div class="msl-screen__heading">
			<h2 class="msl-screen__title" id="msl-art-title"<?php msl_i18n( 'stage', 'art_title' ); ?>><?php msl_the( $msl_stage, 'art_title' ); ?></h2>
			<p class="msl-screen__sub">
				<span class="msl-screen__figure"><span data-msl-pct><?php echo esc_html( (string) $msl_stats['pct'] ); ?></span>%</span>
				<span<?php msl_i18n( 'stage', 'completed_short' ); ?>><?php msl_the( $msl_stage, 'completed_short' ); ?></span>
				<span aria-hidden="true">·</span>
				<span data-msl-counter><?php echo esc_html( msl_num( $msl_stats['participants'] ) ); ?></span>
				<span<?php msl_i18n( 'screens', 'candles_word' ); ?>><?php msl_the( $msl_screens, 'candles_word' ); ?></span>
			</p>
		</div>

		<div class="msl-screen__spacer" aria-hidden="true"></div>
	</div>

	<div class="msl-zoom">
		<button type="button" class="msl-zoom__btn" data-msl-zoom="in"
			aria-label="<?php echo esc_attr( msl_t( $msl_screens, 'zoom_in' ) ); ?>"><span aria-hidden="true">+</span></button>
		<button type="button" class="msl-zoom__btn" data-msl-zoom="out"
			aria-label="<?php echo esc_attr( msl_t( $msl_screens, 'zoom_out' ) ); ?>"><span aria-hidden="true">−</span></button>
		<p class="msl-zoom__level" data-msl-zoom-level aria-live="polite">×1</p>
	</div>

	<div class="msl-screen__bottom">
		<div class="msl-pick" data-msl-art-pick hidden>
			<span class="msl-pick__avatar" aria-hidden="true"></span>
			<span class="msl-pick__body">
				<span class="msl-pick__name" data-msl-pick-name></span>
				<span class="msl-pick__sub" data-msl-pick-sub></span>
			</span>
		</div>

		<p class="msl-hint" data-msl-art-hint aria-live="polite"<?php msl_i18n( 'screens', 'art_hint_zoom' ); ?>><?php msl_the( $msl_screens, 'art_hint_zoom' ); ?></p>
	</div>
</div>
