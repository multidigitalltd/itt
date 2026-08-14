<?php
/**
 * Section 08 — written testimonials.
 *
 * @package ITT_Landing
 *
 * @var array<string, mixed> $itt Resolved section content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;
?>
<section class="itt-section itt-voices" id="voices" aria-labelledby="itt-voices-title" tabindex="-1">
	<div class="itt-shell">
		<h2 class="itt-section__title itt-section__title--start itt-reveal" id="itt-voices-title">
			<?php itt_the_rich( (string) $itt['heading'] ); ?>
		</h2>

		<ul class="itt-voices__cards itt-reveal">
			<?php foreach ( (array) $itt['cards'] as $itt_card ) : ?>
				<li>
					<figure class="itt-voice">
						<p class="itt-voice__title itt-voice__title--<?php echo esc_attr( (string) $itt_card['variant'] ); ?>">
							<?php echo esc_html( (string) $itt_card['title'] ); ?>
						</p>
						<blockquote class="itt-voice__text"><?php echo esc_html( (string) $itt_card['text'] ); ?></blockquote>
						<figcaption class="itt-voice__author"><?php echo esc_html( (string) $itt_card['author'] ); ?></figcaption>
					</figure>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
