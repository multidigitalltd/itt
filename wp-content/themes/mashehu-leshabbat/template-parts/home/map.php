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
<section id="msl-map" class="msl-map" aria-labelledby="msl-map-title" data-msl-rise>
	<div class="msl-map__head">
		<h2 class="msl-heading" id="msl-map-title"<?php msl_i18n( 'map', 'title' ); ?>><?php msl_the( $msl, 'title' ); ?></h2>
		<p class="msl-subheading" data-msl-map-sub
			data-msl-template="<?php echo esc_attr( msl_t( $msl, 'sub' ) ); ?>"
			<?php msl_i18n( 'map', 'sub' ); ?>>
			<?php printf( esc_html( msl_t( $msl, 'sub' ) ), esc_html( msl_num( $msl_countries ) ) ); ?>
		</p>
	</div>

	<div class="msl-map__frame" data-msl-map-frame>
		<canvas class="msl-map__canvas" data-msl-canvas="map" data-msl-map-surface aria-hidden="true"></canvas>

		<?php
		/*
		 * The same two buttons the artwork has, in the same place, because a
		 * person who has zoomed one of them already knows how this one works.
		 * They are also the whole of the keyboard story here: dragging a canvas
		 * is a mouse and a finger, and neither is a keyboard.
		 */
		?>
		<div class="msl-zoom msl-zoom--map">
			<button type="button" class="msl-zoom__btn" data-msl-map-zoom="in"
				aria-label="<?php echo esc_attr( msl_t( $msl, 'zoom_in' ) ); ?>"><span aria-hidden="true">+</span></button>
			<button type="button" class="msl-zoom__btn" data-msl-map-zoom="out"
				aria-label="<?php echo esc_attr( msl_t( $msl, 'zoom_out' ) ); ?>"><span aria-hidden="true">−</span></button>
			<p class="msl-zoom__level" data-msl-map-level aria-live="polite">×1</p>
		</div>

		<?php
		/*
		 * What is under the light somebody picked: the country, and how many
		 * candles are lit in it. Filled in by the browser from the map data, so
		 * the page itself stays the same for everybody and cacheable.
		 */
		?>
		<div class="msl-mappick" data-msl-map-pick hidden>
			<span class="msl-mappick__spark" aria-hidden="true"></span>
			<span class="msl-mappick__body">
				<span class="msl-mappick__place" data-msl-map-place></span>
				<span class="msl-mappick__count" data-msl-map-count
					data-msl-template="<?php echo esc_attr( msl_t( $msl, 'pick_count' ) ); ?>"
					data-msl-template-i18n="map.pick_count"></span>
			</span>
		</div>

		<p class="msl-hint msl-hint--map" data-msl-map-hint aria-live="polite"<?php msl_i18n( 'map', 'hint' ); ?>><?php msl_the( $msl, 'hint' ); ?></p>

		<?php // The canvas carries no text, so the same information is given here in words. ?>
		<p class="msl-visually-hidden"<?php msl_i18n( 'map', 'summary' ); ?>><?php msl_the( $msl, 'summary' ); ?></p>
	</div>
</section>
