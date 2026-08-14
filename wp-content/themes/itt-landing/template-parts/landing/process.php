<?php
/**
 * Section 14 — the two-step admission process.
 *
 * @package ITT_Landing
 *
 * @var array<string, mixed> $itt Resolved section content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;
?>
<section class="itt-section itt-process" aria-labelledby="itt-process-title">
	<div class="itt-shell itt-shell--narrow">
		<h2 class="itt-section__title itt-section__title--start itt-reveal" id="itt-process-title">
			<?php echo esc_html( (string) $itt['heading'] ); ?>
		</h2>

		<ol class="itt-process__steps itt-reveal">
			<?php foreach ( (array) $itt['steps'] as $itt_i => $itt_step ) : ?>
				<li class="itt-step itt-step--<?php echo esc_attr( (string) ( (int) $itt_i + 1 ) ); ?>">
					<span class="itt-step__number" aria-hidden="true"><?php echo esc_html( (string) ( (int) $itt_i + 1 ) ); ?></span>
					<h3 class="itt-step__title"><?php echo esc_html( (string) $itt_step['title'] ); ?></h3>
					<p class="itt-step__text"><?php echo esc_html( (string) $itt_step['text'] ); ?></p>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>
</section>
