<?php
/**
 * Section 08 — written testimonials.
 *
 * Up to three testimonials sit in the design's grid. Beyond that the section
 * turns into the same slider the video testimonials use, so the page does not
 * grow a fourth row of very tall cards.
 *
 * Each quote is clamped to five lines with a "read more" toggle. The clamp is
 * applied by script, so a visitor without JavaScript reads the full text and
 * never sees a button that would do nothing.
 *
 * @package ITT_Landing
 *
 * @var array<string, mixed> $itt Resolved section content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$itt_cards  = array_values( (array) $itt['cards'] );
$itt_slider = count( $itt_cards ) > 3;

/**
 * Render one testimonial.
 *
 * @param array<string, mixed> $card  Card content.
 * @param int                  $index Zero-based position, used for the ids.
 */
$itt_render_voice = static function ( array $card, int $index ): void {
	$text_id = 'itt-voice-text-' . $index;
	?>
	<figure class="itt-voice">
		<?php if ( '' !== trim( (string) $card['title'] ) ) : ?>
			<p class="itt-voice__title itt-voice__title--<?php echo esc_attr( (string) $card['variant'] ); ?>">
				<?php echo esc_html( (string) $card['title'] ); ?>
			</p>
		<?php endif; ?>

		<blockquote class="itt-voice__text" id="<?php echo esc_attr( $text_id ); ?>">
			<?php echo esc_html( (string) $card['text'] ); ?>
		</blockquote>

		<button
			type="button"
			class="itt-voice__more"
			data-itt-more
			aria-controls="<?php echo esc_attr( $text_id ); ?>"
			aria-expanded="false"
			hidden
		>
			<span data-itt-more-label><?php esc_html_e( 'קרא עוד', 'itt-landing' ); ?></span>
			<span class="screen-reader-text">
				<?php
				printf(
					/* translators: %s: name of the person quoted. */
					esc_html__( 'מתוך העדות של %s', 'itt-landing' ),
					esc_html( (string) $card['author'] )
				);
				?>
			</span>
		</button>

		<figcaption class="itt-voice__author"><?php echo esc_html( (string) $card['author'] ); ?></figcaption>
	</figure>
	<?php
};
?>
<section class="itt-section itt-voices" id="voices" aria-labelledby="itt-voices-title" tabindex="-1">
	<div class="itt-shell">
		<?php if ( $itt_slider ) : ?>

			<div class="itt-slider itt-voices__slider itt-reveal" data-itt-slider>
				<div class="itt-slider__head">
					<h2 class="itt-section__title itt-voices__title" id="itt-voices-title">
						<?php itt_the_rich( (string) $itt['heading'] ); ?>
					</h2>

					<div class="itt-slider__nav">
						<button type="button" class="itt-slider__btn" data-itt-slide="prev" aria-label="<?php esc_attr_e( 'עדויות קודמות', 'itt-landing' ); ?>" aria-controls="itt-voices-track">
							<?php itt_chevron( 'prev' ); ?>
						</button>
						<button type="button" class="itt-slider__btn" data-itt-slide="next" aria-label="<?php esc_attr_e( 'עדויות הבאות', 'itt-landing' ); ?>" aria-controls="itt-voices-track">
							<?php itt_chevron( 'next' ); ?>
						</button>
					</div>
				</div>

				<div class="itt-slider__viewport" id="itt-voices-track" tabindex="0" role="group" aria-labelledby="itt-voices-title">
					<ul class="itt-slider__track">
						<?php foreach ( $itt_cards as $itt_i => $itt_card ) : ?>
							<li class="itt-slide itt-voices__slide">
								<?php $itt_render_voice( $itt_card, (int) $itt_i ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>

		<?php else : ?>

			<h2 class="itt-section__title itt-section__title--start itt-reveal" id="itt-voices-title">
				<?php itt_the_rich( (string) $itt['heading'] ); ?>
			</h2>

			<ul class="itt-voices__cards itt-reveal">
				<?php foreach ( $itt_cards as $itt_i => $itt_card ) : ?>
					<li><?php $itt_render_voice( $itt_card, (int) $itt_i ); ?></li>
				<?php endforeach; ?>
			</ul>

		<?php endif; ?>
	</div>
</section>
