<?php
/**
 * The candle wall: one candle per participant.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_screens = MSL_Meta::get( 'screens' );
$msl_stage   = MSL_Meta::get( 'stage' );
$msl_stats   = MSL_Stats::all( (int) get_the_ID() );
?>
<div class="msl-screen msl-screen--wall" data-msl-screen-panel="wall" role="dialog" aria-modal="true"
	aria-labelledby="msl-wall-title" hidden>

	<canvas class="msl-screen__canvas" data-msl-canvas="wall" data-msl-wall-surface aria-hidden="true"></canvas>

	<div class="msl-screen__top">
		<button type="button" class="msl-btn msl-btn--light msl-back" data-msl-goto="home"
			<?php msl_i18n( 'screens', 'back' ); ?>><?php msl_the( $msl_screens, 'back' ); ?></button>

		<div class="msl-screen__heading">
			<h2 class="msl-visually-hidden" id="msl-wall-title"<?php msl_i18n( 'stage', 'wall' ); ?>><?php msl_the( $msl_stage, 'wall' ); ?></h2>
			<p class="msl-screen__count" data-msl-counter><?php echo esc_html( msl_num( $msl_stats['participants'] ) ); ?></p>
			<p class="msl-screen__sub"<?php msl_i18n( 'stage', 'wall_count' ); ?>><?php msl_the( $msl_stage, 'wall_count' ); ?></p>
		</div>

		<div class="msl-screen__spacer" aria-hidden="true"></div>
	</div>

	<div class="msl-screen__bottom">
		<div class="msl-pick msl-pick--stack" data-msl-wall-pick hidden>
			<span class="msl-pick__name" data-msl-pick-name></span>
			<span class="msl-pick__sub" data-msl-pick-sub></span>
		</div>

		<p class="msl-hint" data-msl-wall-hint aria-live="polite"<?php msl_i18n( 'screens', 'wall_hint' ); ?>><?php msl_the( $msl_screens, 'wall_hint' ); ?></p>

		<button type="button" class="msl-btn msl-btn--amber msl-btn--wide" data-msl-open-join
			<?php msl_i18n( 'screens', 'light_mine' ); ?>><?php msl_the( $msl_screens, 'light_mine' ); ?></button>
	</div>
</div>
