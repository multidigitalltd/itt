<?php
/**
 * The hero: halo, collage, eyebrow, headline, call to action, live counter.
 *
 * @package Mashehu_LeShabbat
 *
 * @var array<string, mixed> $msl Resolved hero content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_stats = MSL_Stats::all( (int) get_the_ID() );

/*
 * Eight tiles in four non-colliding vertical bands per side, all anchored from
 * the top so a band can never overlap the one below it. The offsets are the
 * design's, and they only work because the headline is capped at 660px — that
 * cap is what creates the gutter these live in.
 */
$msl_tiles = array(
	array( 'side' => 'start', 'top' => 0,   'inset' => -44, 'w' => 104, 'rotate' => -7,  'radius' => 22, 'bob' => 7.5,  'delay' => 0 ),
	array( 'side' => 'start', 'top' => 128, 'inset' => -20, 'w' => 92,  'rotate' => 6,   'radius' => 20, 'bob' => 8.6,  'delay' => 0.9 ),
	array( 'side' => 'start', 'top' => 256, 'inset' => -44, 'w' => 104, 'rotate' => 9,   'radius' => 22, 'bob' => 9.4,  'delay' => 1.7 ),
	array( 'side' => 'start', 'top' => 384, 'inset' => -20, 'w' => 92,  'rotate' => -11, 'radius' => 20, 'bob' => 10.2, 'delay' => 2.4 ),
	array( 'side' => 'end',   'top' => 12,  'inset' => -44, 'w' => 104, 'rotate' => 7,   'radius' => 22, 'bob' => 8.1,  'delay' => 0.4 ),
	array( 'side' => 'end',   'top' => 140, 'inset' => -20, 'w' => 92,  'rotate' => -6,  'radius' => 20, 'bob' => 9.0,  'delay' => 1.2 ),
	array( 'side' => 'end',   'top' => 268, 'inset' => -44, 'w' => 104, 'rotate' => -10, 'radius' => 22, 'bob' => 7.9,  'delay' => 2.0 ),
	array( 'side' => 'end',   'top' => 396, 'inset' => -20, 'w' => 92,  'rotate' => -8,  'radius' => 20, 'bob' => 9.8,  'delay' => 2.8 ),
);

$msl_images = array_values(
	array_filter(
		(array) $msl['collage'],
		static fn( $row ): bool => is_array( $row ) && (int) ( $row['image'] ?? 0 ) > 0
	)
);
?>
<section class="msl-hero" aria-labelledby="msl-hero-title">
	<canvas class="msl-hero__halo" data-msl-canvas="halo" aria-hidden="true"></canvas>

	<?php if ( array() !== $msl_images ) : ?>
		<?php
		/*
		 * Decorative: the tiles carry no information the headline does not, so
		 * they are hidden from assistive technology rather than read out eight
		 * times. Each tile holds two stacked layers that cross-fade, so a pool
		 * larger than eight cycles through the positions over time.
		 */
		?>
		<div class="msl-collage" aria-hidden="true" data-msl-collage>
			<?php foreach ( $msl_tiles as $msl_i => $msl_tile ) : ?>
				<div class="msl-collage__tile msl-collage__tile--<?php echo esc_attr( $msl_tile['side'] ); ?>"
					data-msl-tile="<?php echo esc_attr( (string) $msl_i ); ?>"
					style="
						--msl-tile-top:<?php echo esc_attr( (string) $msl_tile['top'] ); ?>px;
						--msl-tile-inset:<?php echo esc_attr( (string) $msl_tile['inset'] ); ?>px;
						--msl-tile-w:<?php echo esc_attr( (string) $msl_tile['w'] ); ?>px;
						--msl-tile-rotate:<?php echo esc_attr( (string) $msl_tile['rotate'] ); ?>deg;
						--msl-tile-radius:<?php echo esc_attr( (string) $msl_tile['radius'] ); ?>px;
						--msl-tile-bob:<?php echo esc_attr( (string) $msl_tile['bob'] ); ?>s;
						--msl-tile-delay:<?php echo esc_attr( (string) $msl_tile['delay'] ); ?>s;">
					<div class="msl-collage__inner">
						<?php foreach ( array( 0, 1 ) as $msl_layer ) : ?>
							<?php
							$msl_row = $msl_images[ ( $msl_i * 2 + $msl_layer ) % count( $msl_images ) ];
							?>
							<div class="msl-collage__layer<?php echo 0 === $msl_layer ? ' is-on' : ''; ?>" data-msl-layer="<?php echo esc_attr( (string) $msl_layer ); ?>">
								<?php
								echo wp_get_attachment_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core escapes its own attributes.
									(int) $msl_row['image'],
									'medium',
									false,
									array(
										'alt'      => '',
										'loading'  => 'lazy',
										'decoding' => 'async',
									)
								);
								?>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

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
