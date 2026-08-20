<?php
/**
 * Section 16 — closing block and the lead form.
 *
 * The form posts to the REST route by script, so the page itself carries
 * nothing visitor-specific and stays fully cacheable behind LiteSpeed or
 * Cloudflare. Because the submission needs script, a visitor without
 * JavaScript is given the phone and WhatsApp routes instead.
 *
 * data-itt-page carries the page's own ID — not visitor-specific, so it is
 * safe to cache — which lets the endpoint send the men's form to the men's
 * thank-you page.
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

			<?php
			// The form posts to admin-post.php for real. JavaScript intercepts
			// that and sends the same values in the background for a smoother
			// answer, but when the background request cannot get through — a
			// hosting firewall blocking /wp-json/, a broken connection — the
			// browser falls back to this plain POST, and a visitor with no
			// JavaScript at all can still send their details.
			$itt_error = isset( $_GET['itt_error'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['itt_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only message echoed back after a redirect.
			?>
			<form
				class="itt-form__form"
				id="itt-form"
				method="post"
				action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				data-itt-form
				data-itt-page="<?php echo esc_attr( (string) get_queried_object_id() ); ?>"
				novalidate
			>
				<div class="itt-form__errors" data-itt-form-errors role="alert" tabindex="-1" <?php echo '' === $itt_error ? 'hidden' : ''; ?>><?php echo esc_html( $itt_error ); ?></div>

				<input type="hidden" name="action" value="itt_lead">
				<input type="hidden" name="page" value="<?php echo esc_attr( (string) get_queried_object_id() ); ?>">
				<input type="hidden" name="ts" value="<?php echo esc_attr( (string) time() ); ?>">

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

				<?php if ( '' !== trim( (string) $itt['consent_text'] ) ) : ?>
					<p class="itt-field itt-consent">
						<span class="itt-consent__row">
							<input type="checkbox" id="itt-consent" name="consent" value="1" required aria-describedby="itt-consent-error">
							<label for="itt-consent">
								<?php
								echo itt_consent_text( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside.
									(string) $itt['consent_text'],
									(string) $itt['terms_url'],
									(string) $itt['privacy_url']
								);
								?>
							</label>
						</span>
						<span class="itt-field__error" id="itt-consent-error" data-itt-error="consent"></span>
					</p>
				<?php endif; ?>

				<?php if ( ITT_Settings::turnstile_enabled() ) : ?>
					<p class="itt-field itt-turnstile">
						<span
							class="cf-turnstile"
							data-sitekey="<?php echo esc_attr( ITT_Settings::get( 'turnstile_site_key' ) ); ?>"
							data-language="he"
							data-theme="light"
						></span>
						<span class="itt-field__error" data-itt-error="turnstile"></span>
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
