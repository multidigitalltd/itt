<?php
/**
 * Thank-you 03 — links back into the landing page.
 *
 * @package ITT_Landing
 *
 * @var array<string, mixed> $itt Resolved section content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$itt_landing = ITT_Importer::page_id( 'landing' );
$itt_base    = $itt_landing > 0 ? (string) get_permalink( $itt_landing ) : home_url( '/' );
?>
<section class="itt-section itt-ty-links" aria-labelledby="itt-ty-links-title">
	<div class="itt-shell itt-shell--narrow">
		<div class="itt-section__head itt-reveal">
			<h2 class="itt-section__title" id="itt-ty-links-title"><?php echo esc_html( (string) $itt['heading'] ); ?></h2>
			<p class="itt-section__lead"><?php echo esc_html( (string) $itt['lead'] ); ?></p>
		</div>

		<ul class="itt-ty-links__cards itt-reveal">
			<?php
			foreach ( (array) $itt['cards'] as $itt_card ) :
				$itt_url = (string) $itt_card['url'];

				// A bare anchor is resolved against the landing page, so the card
				// keeps working from the thank-you page.
				if ( str_starts_with( $itt_url, '#' ) ) {
					$itt_url = $itt_base . $itt_url;
				}
				?>
				<li>
					<a class="itt-ty-link itt-ty-link--<?php echo esc_attr( (string) $itt_card['variant'] ); ?>" href="<?php echo esc_url( $itt_url ); ?>">
						<span class="itt-ty-link__title"><?php echo esc_html( (string) $itt_card['title'] ); ?></span>
						<span class="itt-ty-link__text"><?php echo esc_html( (string) $itt_card['text'] ); ?></span>
						<span class="itt-ty-link__cta">
							<?php echo esc_html( (string) $itt_card['cta'] ); ?>
							<span aria-hidden="true">←</span>
						</span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>
