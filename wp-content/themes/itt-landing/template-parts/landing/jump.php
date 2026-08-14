<?php
/**
 * Section 02 — quick anchor navigation.
 *
 * @package ITT_Landing
 *
 * @var array<string, mixed> $itt Resolved section content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;
?>
<nav class="itt-jump" aria-labelledby="itt-jump-title">
	<div class="itt-shell itt-jump__inner itt-reveal">
		<h2 class="itt-jump__title" id="itt-jump-title"><?php echo esc_html( (string) $itt['heading'] ); ?></h2>

		<ul class="itt-jump__list">
			<?php foreach ( (array) $itt['links'] as $itt_link ) : ?>
				<li>
					<a class="itt-chip itt-chip--<?php echo esc_attr( (string) $itt_link['variant'] ); ?>" href="#<?php echo esc_attr( (string) $itt_link['anchor'] ); ?>">
						<?php echo esc_html( (string) $itt_link['text'] ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</nav>
