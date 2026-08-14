<?php
/**
 * Section 01 — hero.
 *
 * @package ITT_Landing
 *
 * @var array<string, mixed> $itt Resolved section content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$itt_chrome = ITT_Meta::get( 'chrome' );
?>
<section class="itt-hero" aria-labelledby="itt-hero-title">
	<div class="itt-hero__birds" aria-hidden="true">
		<?php for ( $itt_bird = 1; $itt_bird <= 4; $itt_bird++ ) : ?>
			<span class="itt-bird itt-bird--<?php echo (int) $itt_bird; ?>">
				<svg width="30" height="14" viewBox="0 0 30 14" focusable="false">
					<path d="M1 11C6 3 10 2 15 8c5-6 9-5 14 3-5-3-9-2-14 2-5-4-9-5-14-2z" fill="#2B1A4A"></path>
				</svg>
			</span>
		<?php endfor; ?>
	</div>

	<div class="itt-hero__inner itt-reveal">
		<div class="itt-hero__copy">
			<p class="itt-hero__badge">
				<span class="itt-hero__badge-rule" aria-hidden="true"></span>
				<span class="itt-blink-soft"><?php echo esc_html( (string) $itt['badge'] ); ?></span>
			</p>

			<h1 class="itt-hero__title" id="itt-hero-title"><?php itt_the_rich( (string) $itt['heading'] ); ?></h1>

			<p class="itt-hero__lead"><?php echo esc_html( (string) $itt['lead'] ); ?></p>

			<div class="itt-hero__actions">
				<a class="itt-btn itt-btn--orange itt-btn--lg" href="#form"><?php echo esc_html( (string) $itt['cta_primary'] ); ?></a>
				<a class="itt-btn itt-btn--outline itt-btn--lg" href="<?php echo esc_url( (string) $itt_chrome['whatsapp'] ); ?>" rel="noopener">
					<?php echo esc_html( (string) $itt['cta_whatsapp'] ); ?>
				</a>
			</div>

			<p class="itt-hero__note"><?php echo esc_html( (string) $itt['note'] ); ?></p>
		</div>

		<div class="itt-hero__media">
			<?php
			itt_image(
				(int) $itt['image'],
				'hero-papercut.webp',
				(string) $itt['image_alt'],
				array(
					'class'         => 'itt-hero__image',
					'loading'       => 'eager',
					'fetchpriority' => 'high',
					'sizes'         => '(max-width: 900px) 100vw, 45vw',
				)
			);
			?>
		</div>
	</div>

	<?php if ( ! empty( $itt['facts'] ) ) : ?>
		<div class="itt-hero__facts itt-reveal">
			<ul class="itt-facts">
				<?php foreach ( (array) $itt['facts'] as $itt_fact ) : ?>
					<li class="itt-facts__item">
						<span class="itt-facts__icon itt-facts__icon--<?php echo esc_attr( (string) $itt_fact['icon'] ); ?>">
							<?php itt_icon( (string) $itt_fact['icon'] ); ?>
						</span>
						<span class="itt-facts__text">
							<span class="itt-facts__label"><?php echo esc_html( (string) $itt_fact['label'] ); ?></span>
							<span class="itt-facts__value"><?php echo esc_html( (string) $itt_fact['value'] ); ?></span>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
</section>
