<?php
/**
 * Section 07 — what graduates leave with.
 *
 * @package ITT_Landing
 *
 * @var array<string, mixed> $itt Resolved section content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;
?>
<section class="itt-section itt-section--gray itt-outcomes" id="outcomes" aria-labelledby="itt-outcomes-title" tabindex="-1">
	<div class="itt-shell">
		<div class="itt-section__head itt-reveal">
			<p class="itt-tag itt-tag--purple-soft"><?php echo esc_html( (string) $itt['badge'] ); ?></p>
			<h2 class="itt-section__title" id="itt-outcomes-title"><?php echo esc_html( (string) $itt['heading'] ); ?></h2>
		</div>

		<ul class="itt-outcomes__cards itt-reveal">
			<?php foreach ( (array) $itt['cards'] as $itt_card ) : ?>
				<li class="itt-outcome itt-outcome--<?php echo esc_attr( (string) $itt_card['variant'] ); ?>">
					<div class="itt-outcome__head">
						<span class="itt-outcome__rule" aria-hidden="true"></span>
						<h3 class="itt-outcome__title"><?php echo esc_html( (string) $itt_card['title'] ); ?></h3>
					</div>

					<p class="itt-outcome__text"><?php echo esc_html( (string) $itt_card['text'] ); ?></p>

					<?php $itt_tags = itt_lines( (string) $itt_card['tags'] ); ?>
					<?php if ( array() !== $itt_tags ) : ?>
						<ul class="itt-outcome__tags">
							<?php foreach ( $itt_tags as $itt_tag ) : ?>
								<li class="itt-pill"><?php echo esc_html( $itt_tag ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
