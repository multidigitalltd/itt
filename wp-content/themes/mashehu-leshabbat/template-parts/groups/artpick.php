<?php
/**
 * "Which artwork" — with a picture of each one.
 *
 * It used to be a dropdown of names. "כוס קידוש" and "לוחות הברית" are perfectly
 * clear as words and tell you nothing whatsoever about what the group's page
 * will look like, which is the only thing the person choosing actually wants to
 * know. So every name now carries the artwork itself beside it.
 *
 * The pictures are drawn by the canvas engine from the same mask the real
 * artwork is built from — not saved as image files and not drawn a second way.
 * A thumbnail kept separately is a second answer to "what does a menorah look
 * like here", and the first time one of them is edited the menu starts lying
 * about what it is offering.
 *
 * **Real radios under the cards.** With no JavaScript the pictures are hidden
 * and what is left is a labelled list of names — the dropdown's own content,
 * still submitting the same field. That matters more here than anywhere: this
 * form is filled in once, by a family member, on whatever device they happen to
 * be holding. Keyboard and screen reader come free for the same reason.
 *
 * @package Mashehu_LeShabbat
 *
 * @var array<string, mixed> $msl_copy   Resolved section holding f_artwork.
 * @var string               $msl_chosen The value currently set.
 * @var string               $msl_id     Prefix for element ids on this page.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_shapes = MSL_Theme::ARTWORK_LABELS;
$msl_chosen = in_array( (string) ( $msl_chosen ?? '' ), array_keys( $msl_shapes ), true ) ? (string) $msl_chosen : 'rotate';
$msl_id     = (string) ( $msl_id ?? 'msl-art' );

// What the rotation is showing this week, so its card is a picture and not a
// blank square. The note under the name says that is what it is.
$msl_week = MSL_Theme::artwork_kind( array( 'artwork' => 'rotate' ) );
?>
<fieldset class="msl-field msl-artpick" data-msl-artpick>
	<legend class="msl-field__label"<?php msl_i18n( 'groups', 'f_artwork' ); ?>><?php msl_the( $msl_copy, 'f_artwork' ); ?></legend>

	<div class="msl-artpick__grid">
		<?php foreach ( $msl_shapes as $msl_value => $msl_label ) : ?>
			<?php $msl_draw = 'rotate' === $msl_value ? $msl_week : $msl_value; ?>
			<label class="msl-artpick__item">
				<input type="radio" class="msl-artpick__input" name="artwork"
					id="<?php echo esc_attr( $msl_id . '-' . $msl_value ); ?>"
					value="<?php echo esc_attr( $msl_value ); ?>"
					<?php checked( $msl_value, $msl_chosen ); ?>>
				<span class="msl-artpick__card">
					<?php
					/*
					 * Decorative: the name beside it is the answer, and a
					 * canvas has no text for a screen reader to read anyway.
					 */
					?>
					<span class="msl-artpick__fig" aria-hidden="true">
						<canvas class="msl-artpick__canvas" data-msl-artthumb="<?php echo esc_attr( $msl_draw ); ?>"></canvas>
					</span>
					<span class="msl-artpick__name"><?php echo esc_html( $msl_label ); ?></span>
					<?php
					/*
					 * The rotation's card shows this week's artwork, so it
					 * says so — otherwise it reads as a promise that this
					 * particular shape is what you are choosing. The name is
					 * deliberately not printed here: the picture above it is
					 * that name, and a name in this sentence would be one
					 * more Hebrew string that the language switch cannot
					 * translate.
					 */
					?>
					<?php if ( 'rotate' === $msl_value ) : ?>
						<span class="msl-artpick__note"<?php msl_i18n( 'groups', 'f_artwork_week' ); ?>><?php msl_the( $msl_copy, 'f_artwork_week' ); ?></span>
					<?php endif; ?>
				</span>
			</label>
		<?php endforeach; ?>
	</div>

	<span class="msl-field__help"<?php msl_i18n( 'groups', 'f_artwork_help' ); ?>><?php msl_the( $msl_copy, 'f_artwork_help' ); ?></span>
</fieldset>
