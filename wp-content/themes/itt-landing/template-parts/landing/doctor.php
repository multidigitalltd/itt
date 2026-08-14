<?php
/**
 * Section 11 — founder bio.
 *
 * @package ITT_Landing
 *
 * @var array<string, mixed> $itt Resolved section content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;
?>
<section class="itt-section itt-section--gray itt-doctor" aria-labelledby="itt-doctor-title">
	<div class="itt-shell itt-doctor__inner itt-reveal">
		<div class="itt-doctor__media">
			<?php
			itt_image(
				(int) $itt['image'],
				'dr-amir-kulik.webp',
				(string) $itt['image_alt'],
				array(
					'class' => 'itt-doctor__image',
					'sizes' => '(max-width: 900px) 100vw, 40vw',
				)
			);
			?>
		</div>

		<div class="itt-doctor__copy">
			<h2 class="itt-doctor__title" id="itt-doctor-title"><?php echo esc_html( (string) $itt['heading'] ); ?></h2>
			<p class="itt-doctor__text"><?php echo esc_html( (string) $itt['text'] ); ?></p>
			<p class="itt-doctor__text"><?php echo esc_html( (string) $itt['text_2'] ); ?></p>

			<?php if ( ! empty( $itt['stats'] ) ) : ?>
				<ul class="itt-doctor__stats">
					<?php foreach ( (array) $itt['stats'] as $itt_stat ) : ?>
						<li class="itt-stat itt-stat--<?php echo esc_attr( (string) $itt_stat['variant'] ); ?>">
							<span class="itt-stat__number"><?php echo esc_html( (string) $itt_stat['number'] ); ?></span>
							<span class="itt-stat__label"><?php echo esc_html( (string) $itt_stat['label'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>
</section>
