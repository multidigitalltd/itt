<?php
/**
 * Section 08b — photo mosaic.
 *
 * @package ITT_Landing
 *
 * @var array<string, mixed> $itt Resolved section content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;
?>
<section class="itt-section itt-section--cream itt-gallery" aria-labelledby="itt-gallery-title">
	<div class="itt-shell">
		<div class="itt-section__head itt-reveal">
			<h2 class="itt-section__title" id="itt-gallery-title"><?php echo esc_html( (string) $itt['heading'] ); ?></h2>
			<p class="itt-section__lead"><?php echo esc_html( (string) $itt['lead'] ); ?></p>
		</div>

		<ul class="itt-gallery__grid itt-reveal">
			<?php foreach ( (array) $itt['images'] as $itt_i => $itt_item ) : ?>
				<li class="itt-gallery__cell">
					<?php
					itt_image(
						(int) $itt_item['image'],
						'gallery-' . ( (int) $itt_i + 1 ) . '.webp',
						(string) $itt_item['alt'],
						array(
							'class' => 'itt-gallery__image',
							'sizes' => 0 === $itt_i ? '(max-width: 900px) 100vw, 66vw' : '(max-width: 600px) 100vw, 33vw',
						)
					);
					?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
