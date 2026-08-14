<?php
/**
 * Thank-you 01 — confirmation.
 *
 * @package ITT_Landing
 *
 * @var array<string, mixed> $itt Resolved section content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;
?>
<section class="itt-ty-hero" aria-labelledby="itt-ty-title">
	<div class="itt-shell itt-shell--tight itt-ty-hero__inner">
		<span class="itt-ty-hero__check" aria-hidden="true">
			<svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" focusable="false"><polyline points="20 6 9 17 4 12"></polyline></svg>
		</span>

		<h1 class="itt-ty-hero__title" id="itt-ty-title"><?php echo esc_html( (string) $itt['heading'] ); ?></h1>
		<p class="itt-ty-hero__lead"><?php echo esc_html( (string) $itt['lead'] ); ?></p>
		<p class="itt-ty-hero__note"><?php echo esc_html( (string) $itt['note'] ); ?></p>
	</div>
</section>
