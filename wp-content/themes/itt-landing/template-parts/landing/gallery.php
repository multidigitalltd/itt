<?php
/**
 * Section 08b — photo gallery.
 *
 * One large image with a thumbnail strip under it and arrows above, browsing
 * the same way the testimonial gallery does: picking a thumbnail or stepping
 * with the arrows changes what is in the large frame.
 *
 * It shares the tablist pattern and the same script as the video gallery —
 * thumbnails are tabs, each large image its tabpanel — so keyboard behaviour
 * is identical in both and there is one controller to maintain.
 *
 * @package ITT_Landing
 *
 * @var array<string, mixed> $itt Resolved section content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$itt_images = array_values( (array) $itt['images'] );

if ( array() === $itt_images ) {
	return;
}

$itt_many = count( $itt_images ) > 1;
?>
<section class="itt-section itt-section--cream itt-gallery" aria-labelledby="itt-gallery-title">
	<div class="itt-shell itt-pgallery itt-reveal" data-itt-gallery>

		<div class="itt-vgallery__head">
			<div class="itt-vgallery__intro">
				<h2 class="itt-section__title" id="itt-gallery-title"><?php echo esc_html( (string) $itt['heading'] ); ?></h2>
				<p class="itt-section__lead"><?php echo esc_html( (string) $itt['lead'] ); ?></p>
			</div>

			<?php if ( $itt_many ) : ?>
				<div class="itt-vgallery__nav">
					<button type="button" class="itt-slider__btn" data-itt-gallery-step="-1" aria-label="<?php esc_attr_e( 'התמונה הקודמת', 'itt-landing' ); ?>" aria-controls="itt-pgallery-tabs">
						<?php itt_chevron( 'prev' ); ?>
					</button>
					<button type="button" class="itt-slider__btn" data-itt-gallery-step="1" aria-label="<?php esc_attr_e( 'התמונה הבאה', 'itt-landing' ); ?>" aria-controls="itt-pgallery-tabs">
						<?php itt_chevron( 'next' ); ?>
					</button>
				</div>
			<?php endif; ?>
		</div>

		<div class="itt-pgallery__stage">
			<?php foreach ( $itt_images as $itt_i => $itt_item ) : ?>
				<div
					class="itt-pstage"
					id="itt-pstage-<?php echo (int) $itt_i; ?>"
					<?php if ( $itt_many ) : ?>
						role="tabpanel"
						aria-labelledby="itt-ptab-<?php echo (int) $itt_i; ?>"
					<?php endif; ?>
					<?php echo 0 === (int) $itt_i ? '' : 'hidden'; ?>
				>
					<?php
					itt_image(
						(int) $itt_item['image'],
						'gallery-' . ( (int) $itt_i + 1 ) . '.webp',
						(string) $itt_item['alt'],
						array(
							'class'   => 'itt-pstage__image',
							'sizes'   => '(max-width: 900px) 100vw, 1080px',
							'loading' => 0 === (int) $itt_i ? 'eager' : 'lazy',
						)
					);
					?>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( $itt_many ) : ?>
			<div class="itt-pgallery__thumbs" id="itt-pgallery-tabs" role="tablist" aria-label="<?php esc_attr_e( 'בחירת תמונה', 'itt-landing' ); ?>">
				<?php foreach ( $itt_images as $itt_i => $itt_item ) : ?>
					<?php
					$itt_alt = trim( (string) $itt_item['alt'] );

					/* translators: %d: position of the image in the gallery. */
					$itt_label = '' !== $itt_alt ? $itt_alt : sprintf( __( 'תמונה %d', 'itt-landing' ), (int) $itt_i + 1 );
					?>
					<button
						type="button"
						class="itt-pthumb<?php echo 0 === (int) $itt_i ? ' is-active' : ''; ?>"
						id="itt-ptab-<?php echo (int) $itt_i; ?>"
						role="tab"
						aria-selected="<?php echo 0 === (int) $itt_i ? 'true' : 'false'; ?>"
						aria-controls="itt-pstage-<?php echo (int) $itt_i; ?>"
						tabindex="<?php echo 0 === (int) $itt_i ? '0' : '-1'; ?>"
					>
						<?php
						// Decorative here: the button's accessible name comes from
						// the label below, so a screen reader hears the picture
						// described once rather than twice.
						itt_image(
							(int) $itt_item['image'],
							'gallery-' . ( (int) $itt_i + 1 ) . '.webp',
							'',
							array( 'class' => 'itt-pthumb__img' )
						);
						?>
						<span class="screen-reader-text"><?php echo esc_html( $itt_label ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
