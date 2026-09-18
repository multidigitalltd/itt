<?php
/**
 * The Shabbat times card.
 *
 * Four facts, in the order a person wants them: which day it is on the Hebrew
 * calendar, which portion is read, when Shabbat comes in, and when it goes out.
 * All four arrive from Hebcal once every few hours and are rendered into the
 * page, so the card costs the visitor nothing and works with the script off.
 *
 * The card disappears entirely when the fetch fails. A times panel showing
 * dashes is worse than no panel: the rest of the page still works, and the
 * countdown quietly falls back to the hand-set time.
 *
 * A visitor can put their own city in it. That choice travels in the address
 * rather than in a cookie — see MSL_Zmanim::requested() — and it changes this
 * card only: the countdown and the artwork stay the campaign's, because every
 * participant is completing the same artwork by the same moment.
 *
 * @package Mashehu_LeShabbat
 *
 * @var array<string, mixed> $msl Resolved zmanim content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

if ( 1 !== (int) ( $msl['show'] ?? 0 ) ) {
	return;
}

$msl_place = MSL_Zmanim::requested( $msl );
$msl_card  = MSL_Zmanim::card( $msl, $msl_place );

if ( null === $msl_card ) {
	return;
}

$msl_pick = MSL_Zmanim::pickable( $msl );

/**
 * One value, printed in whichever language is on screen.
 *
 * These come from an API rather than from a content field, so there is nothing
 * in the script's dictionary for the language toggle to swap them with. Both
 * halves are printed and CSS shows the one in play.
 *
 * @param array{he: string, en: string} $pair Both languages of one value.
 */
$msl_both = static function ( array $pair ): void {
	?>
	<span data-msl-only="he"><?php echo esc_html( $pair['he'] ); ?></span>
	<span data-msl-only="en"><?php echo esc_html( $pair['en'] ); ?></span>
	<?php
};

/**
 * One row of the card, when there is something to put in it.
 *
 * @param string                       $key   Field key of the row's label.
 * @param array{he: string, en: string}|string $value Both languages, or one plain string.
 * @param string                       $slot  Name the script updates this row by.
 */
$msl_row = static function ( string $key, $value, string $slot ) use ( $msl, $msl_both ): void {
	if ( is_array( $value ) && '' === $value['he'] ) {
		return;
	}

	if ( is_string( $value ) && '' === $value ) {
		return;
	}
	?>
	<div class="msl-zmanim__row">
		<dt class="msl-zmanim__label"<?php msl_i18n( 'zmanim', $key ); ?>><?php msl_the( $msl, $key ); ?></dt>
		<dd class="msl-zmanim__value" data-msl-zmanim="<?php echo esc_attr( $slot ); ?>">
			<?php
			if ( is_array( $value ) ) {
				$msl_both( $value );
			} else {
				echo esc_html( $value );
			}
			?>
		</dd>
	</div>
	<?php
};
?>
<section class="msl-zmanim" id="msl-zmanim" aria-labelledby="msl-zmanim-title" data-msl-rise
	data-msl-zmanim-card data-msl-place="<?php echo esc_attr( (string) $msl_card['place'] ); ?>">

	<div class="msl-zmanim__head">
		<h2 class="msl-zmanim__title" id="msl-zmanim-title"<?php msl_i18n( 'zmanim', 'title' ); ?>><?php msl_the( $msl, 'title' ); ?></h2>

		<?php if ( $msl_pick ) : ?>
			<?php
			/*
			 * A <details> rather than a button and a panel: it opens and closes
			 * on its own, is a disclosure to a screen reader without a line of
			 * ARIA, and still works when the script does not run — in which case
			 * the form below is an ordinary GET that the server answers.
			 */
			?>
			<details class="msl-placepick" data-msl-placepick>
				<summary class="msl-placepick__toggle"<?php msl_i18n( 'zmanim', 'pick_cta' ); ?>><?php msl_the( $msl, 'pick_cta' ); ?></summary>

				<form class="msl-placepick__form" method="get" action="<?php echo esc_url( msl_campaign_anchor( 'msl-zmanim' ) ); ?>">
					<label class="msl-placepick__label" for="msl-place"<?php msl_i18n( 'zmanim', 'pick_label' ); ?>><?php msl_the( $msl, 'pick_label' ); ?></label>

					<select class="msl-placepick__select" id="msl-place" name="<?php echo esc_attr( MSL_Zmanim::QUERY ); ?>" data-msl-place-select>
						<?php
						foreach ( array( 'il', 'world' ) as $msl_region ) {
							printf( '<optgroup label="%s">', esc_attr( msl_t( $msl, 'pick_' . $msl_region ) ) );

							foreach ( MSL_Zmanim::PLACES as $msl_id => $msl_names ) {
								if ( $msl_region !== $msl_names['r'] ) {
									continue;
								}

								printf(
									'<option value="%d"%s>%s</option>',
									(int) $msl_id,
									selected( $msl_id, $msl_card['place'], false ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core returns a literal.
									esc_html( $msl_names[ MSL_I18N::lang() ] )
								);
							}

							echo '</optgroup>';
						}
						?>
					</select>

					<?php
					// A shared link that pins a language keeps it across the jump.
					if ( isset( $_GET['lang'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended
						?>
						<input type="hidden" name="lang" value="<?php echo esc_attr( MSL_I18N::lang() ); ?>">
					<?php endif; ?>

					<button type="submit" class="msl-btn msl-btn--amber msl-placepick__apply"
						<?php msl_i18n( 'zmanim', 'pick_apply' ); ?>><?php msl_the( $msl, 'pick_apply' ); ?></button>
				</form>
			</details>
		<?php endif; ?>
	</div>

	<dl class="msl-zmanim__list">
		<?php
		if ( null !== $msl_card['hdate'] ) {
			$msl_row( 'label_hdate', $msl_card['hdate'], 'hdate' );
		}

		$msl_row( 'label_parsha', $msl_card['parsha'], 'parsha' );
		$msl_row( 'label_candles', (string) $msl_card['candles'], 'candles' );
		$msl_row( 'label_havdalah', (string) $msl_card['havdalah'], 'havdalah' );
		?>
	</dl>

	<p class="msl-zmanim__holiday" data-msl-zmanim="holiday"<?php echo '' === $msl_card['holiday']['he'] ? ' hidden' : ''; ?>>
		<?php $msl_both( $msl_card['holiday'] ); ?>
	</p>

	<p class="msl-zmanim__note" data-msl-zmanim="note">
		<?php
		foreach ( array( 'he', 'en' ) as $msl_lang ) :
			$msl_note = (string) ( $msl[ 'note_' . $msl_lang ] ?? '' );

			if ( '' === $msl_note ) {
				continue;
			}
			?>
			<span data-msl-only="<?php echo esc_attr( $msl_lang ); ?>"><?php echo esc_html( sprintf( $msl_note, $msl_card['names'][ $msl_lang ] ) ); ?></span>
		<?php endforeach; ?>
	</p>
</section>
