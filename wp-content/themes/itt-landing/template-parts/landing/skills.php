<?php
/**
 * Section 06 — capabilities plus the rotating quote.
 *
 * @package ITT_Landing
 *
 * @var array<string, mixed> $itt Resolved section content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$itt_quotes = (array) $itt['quotes'];
?>
<section class="itt-section itt-skills" aria-labelledby="itt-skills-title">
	<div class="itt-shell">
		<h2 class="itt-section__title itt-section__title--start itt-reveal" id="itt-skills-title">
			<?php echo esc_html( (string) $itt['heading'] ); ?>
		</h2>

		<ul class="itt-skills__cards itt-reveal">
			<?php foreach ( (array) $itt['cards'] as $itt_card ) : ?>
				<li class="itt-badge-card itt-badge-card--<?php echo esc_attr( (string) $itt_card['variant'] ); ?>">
					<span class="itt-badge-card__badge"><?php echo esc_html( (string) $itt_card['badge'] ); ?></span>
					<p class="itt-badge-card__text"><?php echo esc_html( (string) $itt_card['text'] ); ?></p>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( array() !== $itt_quotes ) : ?>
			<div class="itt-quotes itt-reveal" data-itt-quotes>
				<div class="itt-quotes__viewport" aria-live="polite" aria-atomic="true">
					<?php foreach ( $itt_quotes as $itt_i => $itt_quote ) : ?>
						<figure class="itt-quotes__item" data-itt-quote<?php echo 0 === $itt_i ? '' : ' hidden'; ?>>
							<blockquote class="itt-quotes__text"><?php echo esc_html( (string) $itt_quote['text'] ); ?></blockquote>
							<figcaption class="itt-quotes__author"><?php echo esc_html( (string) $itt_quote['author'] ); ?></figcaption>
						</figure>
					<?php endforeach; ?>
				</div>

				<?php if ( count( $itt_quotes ) > 1 ) : ?>
					<div class="itt-quotes__nav">
						<button type="button" class="itt-quotes__btn" data-itt-quote-nav="prev" aria-label="<?php esc_attr_e( 'הציטוט הקודם', 'itt-landing' ); ?>">
							<?php itt_chevron( 'prev' ); ?>
						</button>
						<button type="button" class="itt-quotes__btn" data-itt-quote-nav="next" aria-label="<?php esc_attr_e( 'הציטוט הבא', 'itt-landing' ); ?>">
							<?php itt_chevron( 'next' ); ?>
						</button>
					</div>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
