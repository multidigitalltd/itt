<?php
/**
 * Section 16 — closing block and the lead form.
 *
 * The form posts to the REST route with a nonce fetched at submit time, so the
 * page itself carries nothing visitor-specific and stays fully cacheable behind
 * LiteSpeed or Cloudflare. Because the nonce can only be fetched by script, a
 * visitor without JavaScript is given the phone and WhatsApp routes instead.
 *
 * @package ITT_Landing
 *
 * @var array<string, mixed> $itt Resolved section content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$itt_chrome  = ITT_Meta::get( 'chrome' );
$itt_bullets = itt_lines( (string) $itt['bullets'] );
?>
<section class="itt-section itt-form" id="form" aria-labelledby="itt-form-title" tabindex="-1">
	<div class="itt-shell itt-shell--narrow itt-form__inner">
		<div class="itt-form__copy itt-reveal">
			<h2 class="itt-section__title itt-section__title--light itt-section__title--start" id="itt-form-title">
				<?php itt_the_rich( (string) $itt['heading'] ); ?>
			</h2>

			<p class="itt-form__lead"><?php echo esc_html( (string) $itt['lead'] ); ?></p>

			<?php if ( array() !== $itt_bullets ) : ?>
				<ul class="itt-form__bullets">
					<?php foreach ( $itt_bullets as $itt_bullet ) : ?>
						<li><?php echo esc_html( $itt_bullet ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<ul class="itt-form__contact">
				<li>
					<a class="itt-btn itt-btn--ghost-cyan" href="<?php echo esc_url( (string) $itt_chrome['whatsapp'] ); ?>" rel="noopener">
						<?php esc_html_e( 'וואטסאפ', 'itt-landing' ); ?> ·
						<span dir="ltr"><?php echo esc_html( (string) $itt_chrome['whatsapp_label'] ); ?></span>
					</a>
				</li>
				<li>
					<?php esc_html_e( 'או בטלפון', 'itt-landing' ); ?>
					<a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^\d*]/', '', (string) $itt_chrome['phone'] ) ); ?>" dir="ltr">
						<?php echo esc_html( (string) $itt_chrome['phone'] ); ?>
					</a>
				</li>
				<li>
					<?php esc_html_e( 'במייל', 'itt-landing' ); ?>
					<a href="<?php echo esc_url( 'mailto:' . $itt_chrome['email'] ); ?>" dir="ltr">
						<?php echo esc_html( (string) $itt_chrome['email'] ); ?>
					</a>
				</li>
			</ul>
		</div>

		<div class="itt-form__card itt-reveal">
			<h3 class="itt-form__card-title"><?php echo esc_html( (string) $itt['form_title'] ); ?></h3>
			<p class="itt-form__card-subtitle"><?php echo esc_html( (string) $itt['form_subtitle'] ); ?></p>

			<noscript>
				<p class="itt-form__noscript">
					<?php esc_html_e( 'שליחת הטופס דורשת JavaScript. אפשר לפנות אלינו בטלפון או בוואטסאפ ונחזור אלייך:', 'itt-landing' ); ?>
					<a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^\d*]/', '', (string) $itt_chrome['phone'] ) ); ?>" dir="ltr"><?php echo esc_html( (string) $itt_chrome['phone'] ); ?></a>
					·
					<a href="<?php echo esc_url( (string) $itt_chrome['whatsapp'] ); ?>" rel="noopener" dir="ltr"><?php echo esc_html( (string) $itt_chrome['whatsapp_label'] ); ?></a>
				</p>
			</noscript>

			<form class="itt-form__form" data-itt-form novalidate>
				<div class="itt-form__errors" data-itt-form-errors role="alert" tabindex="-1" hidden></div>

				<p class="itt-field">
					<label for="itt-name"><?php echo esc_html( (string) $itt['label_name'] ); ?></label>
					<input type="text" id="itt-name" name="name" autocomplete="name" required aria-describedby="itt-name-error">
					<span class="itt-field__error" id="itt-name-error" data-itt-error="name"></span>
				</p>

				<p class="itt-field">
					<label for="itt-phone"><?php echo esc_html( (string) $itt['label_phone'] ); ?></label>
					<input type="tel" id="itt-phone" name="phone" inputmode="tel" autocomplete="tel" required aria-describedby="itt-phone-error">
					<span class="itt-field__error" id="itt-phone-error" data-itt-error="phone"></span>
				</p>

				<?php if ( ! empty( $itt['show_email'] ) ) : ?>
					<p class="itt-field">
						<label for="itt-email"><?php echo esc_html( (string) $itt['label_email'] ); ?></label>
						<input type="email" id="itt-email" name="email" autocomplete="email" aria-describedby="itt-email-error">
						<span class="itt-field__error" id="itt-email-error" data-itt-error="email"></span>
					</p>
				<?php endif; ?>

				<?php if ( ! empty( $itt['show_message'] ) ) : ?>
					<p class="itt-field">
						<label for="itt-message"><?php echo esc_html( (string) $itt['label_message'] ); ?></label>
						<textarea id="itt-message" name="message" rows="3"></textarea>
					</p>
				<?php endif; ?>

				<p class="itt-form__honeypot" aria-hidden="true">
					<label for="itt-website"><?php esc_html_e( 'לא למלא', 'itt-landing' ); ?></label>
					<input type="text" id="itt-website" name="hp" tabindex="-1" autocomplete="off">
				</p>

				<button type="submit" class="itt-btn itt-btn--orange itt-btn--block" data-itt-submit>
					<?php echo esc_html( (string) $itt['submit'] ); ?>
				</button>

				<p class="itt-form__privacy"><?php echo esc_html( (string) $itt['privacy'] ); ?></p>
			</form>
		</div>
	</div>
</section>
