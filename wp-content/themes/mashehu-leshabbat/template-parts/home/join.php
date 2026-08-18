<?php
/**
 * The three-step join flow, as a real dialog.
 *
 * All three steps live in the DOM at once and are toggled with `hidden`, which
 * is what lets the flow move without a reload while the artwork canvas behind it
 * stays mounted.
 *
 * The choices are real checkboxes and radios underneath the design's cards and
 * chips. That is the whole reason the keyboard and a screen reader work here
 * without a line of ARIA: the semantics come from the controls, and the CSS only
 * changes how they look.
 *
 * @package Mashehu_LeShabbat
 *
 * @var array<string, mixed> $msl Resolved join content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_options   = array_values( array_filter( (array) $msl['options'], 'is_array' ) );
$msl_ded_types = array_values( array_filter( (array) $msl['ded_types'], 'is_array' ) );
$msl_steps     = 3;
?>
<div class="msl-modal" data-msl-modal="join" hidden>
	<div class="msl-modal__backdrop" data-msl-dismiss></div>

	<div class="msl-modal__sheet" role="dialog" aria-modal="true" aria-labelledby="msl-join-title" data-msl-sheet>
		<form class="msl-join" data-msl-join novalidate>
			<div class="msl-join__head">
				<button type="button" class="msl-iconbtn" data-msl-back
					aria-label="<?php echo esc_attr( msl_t( MSL_Meta::get( 'screens' ), 'back' ) ); ?>">
					<span aria-hidden="true">‹</span>
				</button>

				<ol class="msl-steps" data-msl-steps>
					<?php for ( $msl_i = 1; $msl_i <= $msl_steps; $msl_i++ ) : ?>
						<li class="msl-steps__segment"<?php echo 1 === $msl_i ? ' data-msl-current="true"' : ''; ?>>
							<span class="msl-visually-hidden"><?php printf( esc_html( msl_t( $msl, 'step_label' ) ), esc_html( (string) $msl_i ), esc_html( (string) $msl_steps ) ); ?></span>
						</li>
					<?php endfor; ?>
				</ol>

				<button type="button" class="msl-iconbtn msl-iconbtn--end" data-msl-dismiss
					aria-label="<?php echo esc_attr( msl_t( MSL_Meta::get( 'screens' ), 'close' ) ); ?>">
					<span aria-hidden="true">✕</span>
				</button>
			</div>

			<div class="msl-join__body">

				<?php /* ---------- Step 1 ---------- */ ?>
				<section class="msl-step" data-msl-step="1">
					<h2 class="msl-step__title" id="msl-join-title"<?php msl_i18n( 'join', 'pick_title' ); ?>><?php msl_the( $msl, 'pick_title' ); ?></h2>
					<p class="msl-step__sub" id="msl-pick-sub"<?php msl_i18n( 'join', 'pick_sub' ); ?>><?php msl_the( $msl, 'pick_sub' ); ?></p>

					<fieldset class="msl-options" aria-describedby="msl-pick-sub">
						<legend class="msl-visually-hidden"<?php msl_i18n( 'join', 'pick_title' ); ?>><?php msl_the( $msl, 'pick_title' ); ?></legend>

						<?php foreach ( $msl_options as $msl_index => $msl_option ) : ?>
							<?php $msl_is_other = 1 === (int) ( $msl_option['is_other'] ?? 0 ); ?>
							<div class="msl-option<?php echo $msl_is_other ? ' msl-option--wide' : ''; ?>">
								<input type="checkbox"
									class="msl-option__input"
									id="msl-option-<?php echo esc_attr( (string) $msl_index ); ?>"
									name="things[]"
									value="<?php echo esc_attr( (string) $msl_index ); ?>"
									data-msl-option
									<?php echo $msl_is_other ? 'data-msl-other="1"' : ''; ?>>
								<label class="msl-option__label" for="msl-option-<?php echo esc_attr( (string) $msl_index ); ?>">
									<span class="msl-option__dot" aria-hidden="true"></span>
									<span class="msl-option__text"<?php msl_i18n( 'join.options.' . $msl_index, 'label' ); ?>><?php msl_the( $msl_option, 'label' ); ?></span>
								</label>
							</div>
						<?php endforeach; ?>
					</fieldset>

					<p class="msl-field msl-field--other" data-msl-other-field hidden>
						<label class="msl-field__label" for="msl-custom-label"<?php msl_i18n( 'join', 'other_ph' ); ?>><?php msl_the( $msl, 'other_ph' ); ?></label>
						<input type="text" class="msl-input" id="msl-custom-label" name="custom_label" maxlength="140">
					</p>
				</section>

				<?php /* ---------- Step 2 ---------- */ ?>
				<section class="msl-step" data-msl-step="2" hidden>
					<h2 class="msl-step__title"<?php msl_i18n( 'join', 'ded_title' ); ?>><?php msl_the( $msl, 'ded_title' ); ?></h2>
					<p class="msl-step__sub" id="msl-ded-sub"<?php msl_i18n( 'join', 'ded_sub' ); ?>><?php msl_the( $msl, 'ded_sub' ); ?></p>

					<fieldset class="msl-chips" aria-describedby="msl-ded-sub">
						<legend class="msl-visually-hidden"<?php msl_i18n( 'join', 'ded_title' ); ?>><?php msl_the( $msl, 'ded_title' ); ?></legend>

						<?php foreach ( $msl_ded_types as $msl_index => $msl_type ) : ?>
							<div class="msl-chip">
								<input type="radio"
									class="msl-chip__input"
									id="msl-ded-<?php echo esc_attr( (string) $msl_index ); ?>"
									name="dedication"
									value="<?php echo esc_attr( (string) $msl_index ); ?>"
									data-msl-dedication>
								<label class="msl-chip__label" for="msl-ded-<?php echo esc_attr( (string) $msl_index ); ?>"
									<?php msl_i18n( 'join.ded_types.' . $msl_index, 'label' ); ?>><?php msl_the( $msl_type, 'label' ); ?></label>
							</div>
						<?php endforeach; ?>
					</fieldset>

					<p class="msl-field">
						<label class="msl-field__label" for="msl-dedication-body"<?php msl_i18n( 'join', 'ded_field_label' ); ?>><?php msl_the( $msl, 'ded_field_label' ); ?></label>
						<textarea class="msl-input msl-input--area" id="msl-dedication-body" name="dedication_body"
							rows="3" maxlength="280"
							placeholder="<?php echo esc_attr( msl_t( $msl, 'ded_ph' ) ); ?>"
							aria-describedby="msl-ded-note"></textarea>
					</p>

					<p class="msl-note" id="msl-ded-note"<?php msl_i18n( 'join', 'ded_note' ); ?>><?php msl_the( $msl, 'ded_note' ); ?></p>
				</section>

				<?php /* ---------- Step 3 ---------- */ ?>
				<section class="msl-step" data-msl-step="3" hidden>
					<h2 class="msl-step__title"<?php msl_i18n( 'join', 'det_title' ); ?>><?php msl_the( $msl, 'det_title' ); ?></h2>
					<p class="msl-step__sub"<?php msl_i18n( 'join', 'det_sub' ); ?>><?php msl_the( $msl, 'det_sub' ); ?></p>

					<p class="msl-field">
						<label class="msl-field__label" for="msl-first-name"<?php msl_i18n( 'join', 'ph_name' ); ?>><?php msl_the( $msl, 'ph_name' ); ?></label>
						<input type="text" class="msl-input" id="msl-first-name" name="first_name"
							maxlength="80" autocomplete="given-name" required
							aria-describedby="msl-error-first-name">
						<span class="msl-field__error" id="msl-error-first-name" role="alert"></span>
					</p>

					<div class="msl-field__pair">
						<p class="msl-field">
							<label class="msl-field__label" for="msl-city"<?php msl_i18n( 'join', 'ph_city' ); ?>><?php msl_the( $msl, 'ph_city' ); ?></label>
							<input type="text" class="msl-input" id="msl-city" name="city"
								maxlength="120" autocomplete="address-level2" required
								aria-describedby="msl-error-city">
							<span class="msl-field__error" id="msl-error-city" role="alert"></span>
						</p>

						<p class="msl-field">
							<label class="msl-field__label" for="msl-country"<?php msl_i18n( 'join', 'ph_country' ); ?>><?php msl_the( $msl, 'ph_country' ); ?></label>
							<input type="text" class="msl-input" id="msl-country" name="country"
								maxlength="120" autocomplete="country-name">
						</p>
					</div>

					<div class="msl-remind">
						<h3 class="msl-remind__title"<?php msl_i18n( 'join', 'remind_title' ); ?>><?php msl_the( $msl, 'remind_title' ); ?></h3>
						<p class="msl-remind__sub" id="msl-remind-sub"<?php msl_i18n( 'join', 'remind_sub' ); ?>><?php msl_the( $msl, 'remind_sub' ); ?></p>

						<div class="msl-field__pair">
							<p class="msl-field">
								<label class="msl-field__label" for="msl-phone"<?php msl_i18n( 'join', 'ph_phone' ); ?>><?php msl_the( $msl, 'ph_phone' ); ?></label>
								<input type="tel" class="msl-input" id="msl-phone" name="phone"
									autocomplete="tel" aria-describedby="msl-remind-sub msl-error-phone">
								<span class="msl-field__error" id="msl-error-phone" role="alert"></span>
							</p>

							<p class="msl-field">
								<label class="msl-field__label" for="msl-email"<?php msl_i18n( 'join', 'ph_email' ); ?>><?php msl_the( $msl, 'ph_email' ); ?></label>
								<input type="email" class="msl-input" id="msl-email" name="email"
									autocomplete="email" aria-describedby="msl-remind-sub msl-error-email">
								<span class="msl-field__error" id="msl-error-email" role="alert"></span>
							</p>
						</div>
					</div>

					<div class="msl-anon">
						<input type="checkbox" class="msl-anon__input" id="msl-anon" name="is_anonymous" value="1">
						<label class="msl-anon__label" for="msl-anon">
							<span class="msl-anon__box" aria-hidden="true"></span>
							<span<?php msl_i18n( 'join', 'anon_label' ); ?>><?php msl_the( $msl, 'anon_label' ); ?></span>
						</label>
					</div>

					<p class="msl-note"<?php msl_i18n( 'join', 'privacy_note' ); ?>><?php msl_the( $msl, 'privacy_note' ); ?></p>
				</section>

				<?php
				/*
				 * The honeypot: a real field with a real label, moved off-screen
				 * and kept out of the tab order. A person never sees it and a
				 * script fills it in.
				 */
				?>
				<p class="msl-honeypot" aria-hidden="true">
					<label for="msl-hp"><?php esc_html_e( 'להשאיר ריק', 'mashehu-leshabbat' ); ?></label>
					<input type="text" id="msl-hp" name="hp" tabindex="-1" autocomplete="off">
				</p>

				<p class="msl-formerror" data-msl-form-error role="alert"></p>
			</div>

			<div class="msl-join__foot">
				<button type="button" class="msl-btn msl-btn--block msl-btn--ink" data-msl-next="2" disabled
					<?php msl_i18n( 'join', 'pick_cta' ); ?>><?php msl_the( $msl, 'pick_cta' ); ?></button>

				<div class="msl-join__foot-group" hidden data-msl-foot="2">
					<button type="button" class="msl-btn msl-btn--block msl-btn--ink" data-msl-next="3"
						<?php msl_i18n( 'join', 'ded_cta' ); ?>><?php msl_the( $msl, 'ded_cta' ); ?></button>
					<button type="button" class="msl-textbtn" data-msl-skip
						<?php msl_i18n( 'join', 'skip' ); ?>><?php msl_the( $msl, 'skip' ); ?></button>
				</div>

				<button type="submit" class="msl-btn msl-btn--block msl-btn--ink" hidden data-msl-submit
					data-msl-sending="<?php echo esc_attr( msl_t( $msl, 'sending' ) ); ?>"
					<?php msl_i18n( 'join', 'submit_cta' ); ?>><?php msl_the( $msl, 'submit_cta' ); ?></button>
			</div>
		</form>
	</div>
</div>
