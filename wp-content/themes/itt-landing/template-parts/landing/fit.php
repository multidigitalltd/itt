<?php
/**
 * Section 12 — who the course fits, and who it does not.
 *
 * @package ITT_Landing
 *
 * @var array<string, mixed> $itt Resolved section content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;
?>
<section class="itt-section itt-fit" aria-labelledby="itt-fit-title">
	<div class="itt-shell">
		<div class="itt-section__head itt-reveal">
			<p class="itt-tag itt-tag--purple-soft"><?php echo esc_html( (string) $itt['badge'] ); ?></p>
			<h2 class="itt-section__title" id="itt-fit-title"><?php echo esc_html( (string) $itt['heading'] ); ?></h2>
		</div>

		<ul class="itt-fit__cards itt-reveal">
			<?php foreach ( (array) $itt['cards'] as $itt_card ) : ?>
				<li class="itt-fit__card itt-fit__card--<?php echo esc_attr( (string) $itt_card['variant'] ); ?>">
					<span class="itt-fit__mark" aria-hidden="true">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" focusable="false"><polyline points="20 6 9 17 4 12"></polyline></svg>
					</span>
					<h3 class="itt-fit__title"><?php echo esc_html( (string) $itt_card['title'] ); ?></h3>
					<p class="itt-fit__text"><?php echo esc_html( (string) $itt_card['text'] ); ?></p>
				</li>
			<?php endforeach; ?>

			<li class="itt-fit__cta">
				<p class="itt-fit__cta-title"><?php echo esc_html( (string) $itt['cta_title'] ); ?></p>
				<p class="itt-fit__cta-text"><?php echo esc_html( (string) $itt['cta_text'] ); ?></p>
				<a class="itt-btn itt-btn--gold" href="#form"><?php echo esc_html( (string) $itt['cta_label'] ); ?></a>
			</li>
		</ul>

		<div class="itt-exclude itt-reveal">
			<h3 class="itt-exclude__title"><?php echo esc_html( (string) $itt['exclude_title'] ); ?></h3>

			<ul class="itt-exclude__list">
				<?php foreach ( (array) $itt['exclude'] as $itt_item ) : ?>
					<li class="itt-exclude__item">
						<span class="itt-exclude__mark" aria-hidden="true">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round" focusable="false"><path d="M6 6l12 12M18 6L6 18"></path></svg>
						</span>
						<span><?php echo esc_html( (string) $itt_item['text'] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</section>
