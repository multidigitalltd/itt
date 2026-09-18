<?php
/**
 * The form that opens a group.
 *
 * An ordinary POST to admin-post.php. Opening a group is a once-in-a-campaign
 * action taken by a family member on whatever device they happen to hold, and a
 * form that silently does nothing when a script fails to load is the wrong
 * thing to hand them. There is no JavaScript on this form at all.
 *
 * Only the name is required — the same principle the join form settled on. A
 * field somebody did not want to answer should not be the reason a group never
 * gets opened.
 *
 * @package Mashehu_LeShabbat
 *
 * @var array<string, mixed> $msl_groups Resolved groups content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

if ( 1 !== (int) ( $msl_groups['open_on'] ?? 0 ) ) {
	return;
}

$msl_campaign = MSL_Meta::get( 'campaign' );
$msl_shapes   = array(
	'rotate'    => 'סבב שבועי',
	'candles'   => 'נרות שבת',
	'star'      => 'מגן דוד',
	'menorah'   => 'מנורה',
	'tablets'   => 'לוחות הברית',
	'kiddush'   => 'כוס קידוש',
	'jerusalem' => 'ירושלים',
	'israel'    => 'מפת ישראל',
	'light'     => 'נקודת אור',
);
?>
<section class="msl-gform" id="msl-open" aria-labelledby="msl-gform-title" data-msl-rise>
	<h2 class="msl-gform__title" id="msl-gform-title"<?php msl_i18n( 'groups', 'form_title' ); ?>><?php msl_the( $msl_groups, 'form_title' ); ?></h2>
	<p class="msl-gform__lead"<?php msl_i18n( 'groups', 'form_lead' ); ?>><?php msl_the( $msl_groups, 'form_lead' ); ?></p>

	<form class="msl-gform__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="msl_group">
		<?php wp_nonce_field( 'msl_group', 'msl_group_nonce' ); ?>

		<p class="msl-field">
			<label class="msl-field__label" for="msl-g-title"<?php msl_i18n( 'groups', 'f_title' ); ?>><?php msl_the( $msl_groups, 'f_title' ); ?></label>
			<input type="text" class="msl-input" id="msl-g-title" name="title" maxlength="120" required
				aria-describedby="msl-g-title-help">
			<span class="msl-field__help" id="msl-g-title-help"<?php msl_i18n( 'groups', 'f_title_help' ); ?>><?php msl_the( $msl_groups, 'f_title_help' ); ?></span>
		</p>

		<div class="msl-gform__row">
			<p class="msl-field">
				<label class="msl-field__label" for="msl-g-occasion"<?php msl_i18n( 'groups', 'f_occasion' ); ?>><?php msl_the( $msl_groups, 'f_occasion' ); ?></label>
				<select class="msl-input" id="msl-g-occasion" name="occasion">
					<option value="0"><?php msl_the( $msl_groups, 'occ_none' ); ?></option>
					<?php foreach ( MSL_Groups::OCCASIONS as $msl_index => $msl_key ) : ?>
						<option value="<?php echo esc_attr( (string) $msl_index ); ?>"><?php msl_the( $msl_groups, 'occ_' . $msl_key ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>

			<p class="msl-field">
				<label class="msl-field__label" for="msl-g-honouree"<?php msl_i18n( 'groups', 'f_honouree' ); ?>><?php msl_the( $msl_groups, 'f_honouree' ); ?></label>
				<input type="text" class="msl-input" id="msl-g-honouree" name="honouree" maxlength="120">
			</p>
		</div>

		<p class="msl-field">
			<label class="msl-field__label" for="msl-g-story"<?php msl_i18n( 'groups', 'f_story' ); ?>><?php msl_the( $msl_groups, 'f_story' ); ?></label>
			<textarea class="msl-input msl-input--area" id="msl-g-story" name="story" rows="4"
				maxlength="<?php echo esc_attr( (string) MSL_Groups::MAX_STORY ); ?>"></textarea>
		</p>

		<div class="msl-gform__row">
			<p class="msl-field">
				<label class="msl-field__label" for="msl-g-target"<?php msl_i18n( 'groups', 'f_target' ); ?>><?php msl_the( $msl_groups, 'f_target' ); ?></label>
				<input type="number" class="msl-input" id="msl-g-target" name="target" inputmode="numeric"
					min="<?php echo esc_attr( (string) MSL_Groups::MIN_TARGET ); ?>"
					max="<?php echo esc_attr( (string) MSL_Groups::MAX_TARGET ); ?>"
					value="36" aria-describedby="msl-g-target-help">
				<span class="msl-field__help" id="msl-g-target-help"<?php msl_i18n( 'groups', 'f_target_help' ); ?>><?php msl_the( $msl_groups, 'f_target_help' ); ?></span>
			</p>

			<p class="msl-field">
				<label class="msl-field__label" for="msl-g-artwork"<?php msl_i18n( 'groups', 'f_artwork' ); ?>><?php msl_the( $msl_groups, 'f_artwork' ); ?></label>
				<select class="msl-input" id="msl-g-artwork" name="artwork">
					<?php foreach ( $msl_shapes as $msl_value => $msl_label ) : ?>
						<option value="<?php echo esc_attr( $msl_value ); ?>"><?php echo esc_html( $msl_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
		</div>

		<div class="msl-gform__row">
			<p class="msl-field">
				<label class="msl-field__label" for="msl-g-owner"<?php msl_i18n( 'groups', 'f_owner' ); ?>><?php msl_the( $msl_groups, 'f_owner' ); ?></label>
				<input type="text" class="msl-input" id="msl-g-owner" name="owner_name" maxlength="80" autocomplete="given-name">
			</p>

			<p class="msl-field">
				<label class="msl-field__label" for="msl-g-email"<?php msl_i18n( 'groups', 'f_email' ); ?>><?php msl_the( $msl_groups, 'f_email' ); ?></label>
				<input type="email" class="msl-input" id="msl-g-email" name="owner_email" maxlength="255"
					autocomplete="email" inputmode="email" aria-describedby="msl-g-email-help">
				<span class="msl-field__help" id="msl-g-email-help"<?php msl_i18n( 'groups', 'f_email_help' ); ?>><?php msl_the( $msl_groups, 'f_email_help' ); ?></span>
			</p>
		</div>

		<input type="hidden" name="accent" value="<?php echo esc_attr( MSL_Theme::accent( (string) $msl_campaign['accent'] ) ); ?>">

		<?php // The same trap as every other form here: a field no person sees. ?>
		<p class="msl-honeypot" aria-hidden="true">
			<label for="msl-g-hp">Website</label>
			<input type="text" id="msl-g-hp" name="website" tabindex="-1" autocomplete="off">
		</p>

		<p class="msl-gform__actions">
			<button type="submit" class="msl-btn msl-btn--amber msl-btn--wide"
				<?php msl_i18n( 'groups', 'submit' ); ?>><?php msl_the( $msl_groups, 'submit' ); ?></button>
		</p>
	</form>
</section>
