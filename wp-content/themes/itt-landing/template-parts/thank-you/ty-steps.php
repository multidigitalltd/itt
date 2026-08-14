<?php
/**
 * Thank-you 02 — what happens next.
 *
 * @package ITT_Landing
 *
 * @var array<string, mixed> $itt Resolved section content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;
?>
<section class="itt-section itt-section--gray itt-ty-steps" aria-labelledby="itt-ty-steps-title">
	<div class="itt-shell itt-shell--narrow">
		<h2 class="itt-section__title itt-reveal" id="itt-ty-steps-title"><?php echo esc_html( (string) $itt['heading'] ); ?></h2>

		<ol class="itt-ty-steps__list itt-reveal">
			<?php foreach ( (array) $itt['steps'] as $itt_i => $itt_step ) : ?>
				<li class="itt-ty-step itt-ty-step--<?php echo esc_attr( (string) ( (int) $itt_i + 1 ) ); ?>">
					<span class="itt-ty-step__number" aria-hidden="true"><?php echo esc_html( (string) ( (int) $itt_i + 1 ) ); ?></span>
					<h3 class="itt-ty-step__title"><?php echo esc_html( (string) $itt_step['title'] ); ?></h3>
					<p class="itt-ty-step__text"><?php echo esc_html( (string) $itt_step['text'] ); ?></p>
				</li>
			<?php endforeach; ?>
		</ol>

		<p class="itt-ty-steps__tip itt-reveal"><?php echo esc_html( (string) $itt['tip'] ); ?></p>
	</div>
</section>
