<?php
/**
 * The world map.
 *
 * The land is drawn from a bundled ring set and projected in the browser, so the
 * page pulls no third-party script and makes no request to a tile server.
 *
 * @package Mashehu_LeShabbat
 *
 * @var array<string, mixed> $msl Resolved map content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_countries = (int) MSL_Meta::get( 'campaign' )['countries'];
?>
<section class="msl-map" aria-labelledby="msl-map-title">
	<div class="msl-map__head">
		<h2 class="msl-heading" id="msl-map-title"<?php msl_i18n( 'map', 'title' ); ?>><?php msl_the( $msl, 'title' ); ?></h2>
		<p class="msl-subheading" data-msl-map-sub
			data-msl-template="<?php echo esc_attr( msl_t( $msl, 'sub' ) ); ?>"
			<?php msl_i18n( 'map', 'sub' ); ?>>
			<?php printf( esc_html( msl_t( $msl, 'sub' ) ), esc_html( msl_num( $msl_countries ) ) ); ?>
		</p>
	</div>

	<div class="msl-map__frame">
		<canvas class="msl-map__canvas" data-msl-canvas="map" aria-hidden="true"></canvas>
		<?php // The canvas carries no text, so the same information is given here in words. ?>
		<p class="msl-visually-hidden"<?php msl_i18n( 'map', 'summary' ); ?>><?php msl_the( $msl, 'summary' ); ?></p>
	</div>
</section>
