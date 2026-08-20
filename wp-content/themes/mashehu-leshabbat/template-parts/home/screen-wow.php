<?php
/**
 * The moment after joining: the new candle travels in and lands in the artwork.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_screens = MSL_Meta::get( 'screens' );
?>
<?php
/*
 * Focus lands on the panel rather than on its button: for the first three
 * seconds the copy has not arrived yet, and sending focus to a control nobody
 * can see is worse than sending it nowhere.
 */
?>
<div class="msl-screen msl-screen--wow" data-msl-screen-panel="wow" data-msl-focus-self
	role="dialog" aria-modal="true" aria-labelledby="msl-wow-title" hidden>

	<canvas class="msl-screen__canvas" data-msl-canvas="wow" aria-hidden="true"></canvas>

	<div class="msl-wow__count" data-msl-wow-count>
		<p class="msl-wow__number" data-msl-counter>0</p>
		<p class="msl-wow__label"<?php msl_i18n( 'screens', 'wow_parts' ); ?>><?php msl_the( $msl_screens, 'wow_parts' ); ?></p>
	</div>

	<div class="msl-wow__text" data-msl-wow-text>
		<h2 class="msl-wow__title" id="msl-wow-title"<?php msl_i18n( 'screens', 'wow_title' ); ?>><?php msl_the( $msl_screens, 'wow_title' ); ?></h2>

		<p class="msl-wow__body">
			<span<?php msl_i18n( 'screens', 'wow_line1' ); ?>><?php msl_the( $msl_screens, 'wow_line1' ); ?></span>
			<span class="msl-wow__figure" data-msl-counter-minus-one>0</span>
			<span<?php msl_i18n( 'screens', 'wow_line2' ); ?>><?php msl_the( $msl_screens, 'wow_line2' ); ?></span>
		</p>

		<button type="button" class="msl-btn msl-btn--light msl-btn--hero" data-msl-goto="result"
			<?php msl_i18n( 'screens', 'wow_cta' ); ?>><?php msl_the( $msl_screens, 'wow_cta' ); ?></button>
	</div>
</div>
