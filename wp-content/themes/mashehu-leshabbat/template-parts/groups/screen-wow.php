<?php
/**
 * The moment after joining a group: the candle travels in and lands.
 *
 * The campaign page has had this since the first version, and a group's page
 * went straight from the form to the share card — which meant the one thing a
 * person came here to see, their own light appearing, happened on the main
 * page and not in the group their family opened.
 *
 * Same panel, same canvas key, same sequence. What differs is the number: here
 * it is the group's, because the artwork on this screen is the group's. The
 * campaign's own counter is untouched and still counts everybody, which is the
 * truth on both pages — a light lit in a group is a light in the main artwork.
 *
 * @package Mashehu_LeShabbat
 *
 * @var array<string, mixed> $msl_group The group being shown.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_screens = MSL_Meta::get( 'screens' );
?>
<div class="msl-screen msl-screen--wow" data-msl-screen-panel="wow" data-msl-focus-self
	role="dialog" aria-modal="true" aria-labelledby="msl-gwow-title" hidden>

	<canvas class="msl-screen__canvas" data-msl-canvas="wow" aria-hidden="true"></canvas>

	<div class="msl-wow__count" data-msl-wow-count>
		<p class="msl-wow__number" data-msl-group-count><?php echo esc_html( msl_num( (int) $msl_group['count'] ) ); ?></p>
		<p class="msl-wow__label"<?php msl_i18n( 'screens', 'wow_parts' ); ?>><?php msl_the( $msl_screens, 'wow_parts' ); ?></p>
	</div>

	<div class="msl-wow__text" data-msl-wow-text>
		<h2 class="msl-wow__title" id="msl-gwow-title"<?php msl_i18n( 'screens', 'wow_title' ); ?>><?php msl_the( $msl_screens, 'wow_title' ); ?></h2>

		<p class="msl-wow__body">
			<span<?php msl_i18n( 'screens', 'wow_line1' ); ?>><?php msl_the( $msl_screens, 'wow_line1' ); ?></span>
			<span class="msl-wow__figure" data-msl-group-count-minus-one><?php echo esc_html( msl_num( max( 0, (int) $msl_group['count'] - 1 ) ) ); ?></span>
			<span<?php msl_i18n( 'screens', 'wow_line2' ); ?>><?php msl_the( $msl_screens, 'wow_line2' ); ?></span>
		</p>

		<button type="button" class="msl-btn msl-btn--light msl-btn--hero" data-msl-goto="result"
			<?php msl_i18n( 'screens', 'wow_cta' ); ?>><?php msl_the( $msl_screens, 'wow_cta' ); ?></button>
	</div>
</div>
