<?php
/**
 * Section 13 — bonuses, plus the urgency band that follows it.
 *
 * @package ITT_Landing
 *
 * @var array<string, mixed> $itt Resolved section content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$itt_show_values = ! empty( $itt['show_values'] );
?>
<section class="itt-section itt-section--warm itt-bonuses" aria-labelledby="itt-bonuses-title">
	<div class="itt-shell">
		<div class="itt-section__head itt-reveal">
			<p class="itt-tag itt-tag--gold"><?php echo esc_html( (string) $itt['badge'] ); ?></p>
			<h2 class="itt-section__title" id="itt-bonuses-title"><?php itt_the_rich( (string) $itt['heading'] ); ?></h2>

			<?php if ( $itt_show_values ) : ?>
				<p class="itt-bonuses__subtitle"><?php itt_the_rich( (string) $itt['subtitle'] ); ?></p>
			<?php endif; ?>
		</div>

		<ul class="itt-bonuses__cards">
			<?php foreach ( (array) $itt['cards'] as $itt_i => $itt_card ) : ?>
				<li class="itt-bonus itt-rise" data-rise>
					<span class="itt-bonus__ghost" aria-hidden="true"><?php echo esc_html( (string) ( (int) $itt_i + 1 ) ); ?></span>

					<div class="itt-bonus__head">
						<span class="itt-bonus__index" aria-hidden="true"><?php echo esc_html( (string) ( (int) $itt_i + 1 ) ); ?></span>

						<?php if ( $itt_show_values && '' !== trim( (string) $itt_card['value'] ) ) : ?>
							<span class="itt-bonus__value"><?php echo esc_html( (string) $itt_card['value'] ); ?></span>
						<?php endif; ?>
					</div>

					<h3 class="itt-bonus__title"><?php echo esc_html( (string) $itt_card['title'] ); ?></h3>
					<p class="itt-bonus__text"><?php echo esc_html( (string) $itt_card['text'] ); ?></p>
				</li>
			<?php endforeach; ?>
		</ul>

		<div class="itt-band itt-band--purple itt-band--total itt-reveal">
			<div class="itt-band__copy">
				<p class="itt-band__title"><?php itt_the_rich( (string) $itt['total_title'] ); ?></p>
				<p class="itt-band__text"><?php echo esc_html( (string) $itt['total_text'] ); ?></p>
			</div>
			<a class="itt-btn itt-btn--orange" href="#form"><?php echo esc_html( (string) $itt['total_cta'] ); ?></a>
		</div>
	</div>
</section>

<div class="itt-band itt-band--deep">
	<div class="itt-shell itt-shell--narrow itt-band__inner">
		<div class="itt-band__copy">
			<p class="itt-band__title itt-band__title--light"><?php echo esc_html( (string) $itt['urgency_title'] ); ?></p>
			<p class="itt-band__text"><?php echo esc_html( (string) $itt['urgency_text'] ); ?></p>
		</div>
		<a class="itt-btn itt-btn--gold itt-btn--blink" href="#form"><?php echo esc_html( (string) $itt['urgency_cta'] ); ?></a>
	</div>
</div>
