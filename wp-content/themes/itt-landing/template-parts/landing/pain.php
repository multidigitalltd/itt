<?php
/**
 * Section 03 — the pain.
 *
 * @package ITT_Landing
 *
 * @var array<string, mixed> $itt Resolved section content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$itt_rules = array( 'red', 'orange', 'gold' );
?>
<section class="itt-section itt-section--gray itt-pain" aria-labelledby="itt-pain-title">
	<div class="itt-shell itt-shell--narrow">
		<div class="itt-section__head itt-reveal">
			<h2 class="itt-section__title" id="itt-pain-title"><?php itt_the_rich( (string) $itt['heading'] ); ?></h2>
			<p class="itt-section__lead"><?php itt_the_rich( (string) $itt['lead'] ); ?></p>
		</div>

		<ul class="itt-pain__cards itt-reveal">
			<?php foreach ( (array) $itt['cards'] as $itt_index => $itt_card ) : ?>
				<li class="itt-card itt-pain__card">
					<span class="itt-rule itt-rule--<?php echo esc_attr( $itt_rules[ $itt_index % 3 ] ); ?>" aria-hidden="true"></span>
					<p><?php echo esc_html( (string) $itt_card['text'] ); ?></p>
				</li>
			<?php endforeach; ?>
		</ul>

		<div class="itt-band itt-band--purple itt-reveal">
			<p class="itt-band__text"><?php echo esc_html( (string) $itt['band_text'] ); ?></p>
			<a class="itt-btn itt-btn--gold" href="#form"><?php echo esc_html( (string) $itt['band_cta'] ); ?></a>
		</div>
	</div>
</section>
