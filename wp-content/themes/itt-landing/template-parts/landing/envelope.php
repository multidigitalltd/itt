<?php
/**
 * Section 10 — the support envelope.
 *
 * @package ITT_Landing
 *
 * @var array<string, mixed> $itt Resolved section content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;
?>
<section class="itt-section itt-envelope" aria-labelledby="itt-envelope-title">
	<div class="itt-shell">
		<h2 class="itt-section__title itt-section__title--start itt-reveal" id="itt-envelope-title">
			<?php itt_the_rich( (string) $itt['heading'] ); ?>
		</h2>

		<ul class="itt-envelope__cards itt-reveal">
			<?php foreach ( (array) $itt['cards'] as $itt_card ) : ?>
				<li class="itt-badge-card itt-badge-card--<?php echo esc_attr( (string) $itt_card['variant'] ); ?>">
					<span class="itt-badge-card__badge"><?php echo esc_html( (string) $itt_card['badge'] ); ?></span>
					<p class="itt-badge-card__text"><?php echo esc_html( (string) $itt_card['text'] ); ?></p>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
