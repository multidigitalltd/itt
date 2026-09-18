<?php
/**
 * The reminder window.
 *
 * Offered on the way out: one reminder before Shabbat, carrying the light the
 * visitor chose. It asks for an address and, optionally, a name — nothing else,
 * because nothing else is needed to send one email.
 *
 * Signing up here is deliberately *not* a join. The person has not added a
 * candle, and writing them into the count to make a number bigger would make
 * that number untrue. See MSL_DB::reminders_table().
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_remind  = MSL_Meta::get( 'auth' );
$msl_screens = MSL_Meta::get( 'screens' );

if ( 1 !== (int) ( $msl_remind['remind_on'] ?? 0 ) ) {
	return;
}
?>
<div class="msl-invite msl-invite--remind" data-msl-modal="remind" role="dialog" aria-modal="true"
	aria-labelledby="msl-remind-title" hidden>

	<div class="msl-invite__backdrop" data-msl-close-remind aria-hidden="true"></div>

	<div class="msl-invite__card" role="document">
		<button type="button" class="msl-invite__close" data-msl-close-remind
			aria-label="<?php echo esc_attr( msl_t( $msl_screens, 'close' ) ); ?>">
			<span aria-hidden="true">×</span>
		</button>

		<span class="msl-invite__flame" aria-hidden="true"><?php msl_candle_svg( 2.6, 1.9 ); ?></span>

		<h2 class="msl-invite__title" id="msl-remind-title"<?php msl_i18n( 'auth', 'remind_title' ); ?>><?php msl_the( $msl_remind, 'remind_title' ); ?></h2>

		<p class="msl-invite__body"<?php msl_i18n( 'auth', 'remind_body' ); ?>><?php msl_the( $msl_remind, 'remind_body' ); ?></p>

		<?php
		/*
		 * Only shown once there is a light to name. The script fills it in from
		 * whatever the visitor chose, and leaves it hidden if they chose nothing
		 * — "the light you chose:" followed by a blank is worse than silence.
		 */
		?>
		<p class="msl-remindbox__chosen" data-msl-remind-chosen hidden>
			<span<?php msl_i18n( 'auth', 'remind_with' ); ?>><?php msl_the( $msl_remind, 'remind_with' ); ?></span>
			<strong data-msl-remind-thing></strong>
		</p>

		<form class="msl-remindbox" data-msl-remind-form novalidate>
			<p class="msl-field">
				<label class="msl-field__label" for="msl-remind-name"<?php msl_i18n( 'auth', 'remind_name' ); ?>><?php msl_the( $msl_remind, 'remind_name' ); ?></label>
				<input type="text" class="msl-input" id="msl-remind-name" name="name"
					maxlength="80" autocomplete="given-name">
			</p>

			<p class="msl-field">
				<label class="msl-field__label" for="msl-remind-email"<?php msl_i18n( 'auth', 'remind_email' ); ?>><?php msl_the( $msl_remind, 'remind_email' ); ?></label>
				<input type="email" class="msl-input" id="msl-remind-email" name="email"
					maxlength="255" autocomplete="email" inputmode="email"
					aria-describedby="msl-remind-error">
				<span class="msl-field__error" id="msl-remind-error" role="alert"></span>
			</p>

			<?php // Same trap as the join form: a field no person ever sees. ?>
			<p class="msl-honeypot" aria-hidden="true">
				<label for="msl-remind-hp">Website</label>
				<input type="text" id="msl-remind-hp" name="website" tabindex="-1" autocomplete="off">
			</p>

			<div class="msl-invite__actions">
				<button type="submit" class="msl-btn msl-btn--amber msl-btn--wide" data-msl-remind-submit
					<?php msl_i18n( 'auth', 'remind_cta' ); ?>><?php msl_the( $msl_remind, 'remind_cta' ); ?></button>
			</div>
		</form>

		<p class="msl-remindbox__done" data-msl-remind-done hidden
			<?php msl_i18n( 'auth', 'remind_done' ); ?>><?php msl_the( $msl_remind, 'remind_done' ); ?></p>

		<button type="button" class="msl-textbtn msl-invite__dismiss" data-msl-close-remind
			<?php msl_i18n( 'auth', 'remind_dismiss' ); ?>><?php msl_the( $msl_remind, 'remind_dismiss' ); ?></button>
	</div>
</div>
