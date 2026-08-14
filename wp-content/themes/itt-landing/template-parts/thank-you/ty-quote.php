<?php
/**
 * Thank-you 04 — closing quote.
 *
 * @package ITT_Landing
 *
 * @var array<string, mixed> $itt Resolved section content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;
?>
<section class="itt-section itt-section--gray itt-ty-quote">
	<figure class="itt-shell itt-shell--tight itt-ty-quote__inner itt-reveal">
		<span class="itt-ty-quote__mark" aria-hidden="true">”</span>
		<blockquote class="itt-ty-quote__text"><?php echo esc_html( (string) $itt['text'] ); ?></blockquote>
		<figcaption class="itt-ty-quote__author"><?php echo esc_html( (string) $itt['author'] ); ?></figcaption>
	</figure>
</section>
