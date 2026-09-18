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
 * @package Mashehu_LeShabbat
 *
 * @var array<string, mixed> $msl Resolved zmanim content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

if ( 1 !== (int) ( $msl['show'] ?? 0 ) ) {
	return;
}

$msl_week = MSL_Zmanim::week( $msl );

if ( null === $msl_week ) {
	return;
}

$msl_zone  = MSL_Zmanim::zone( $msl );
$msl_date  = MSL_Zmanim::hebrew_date( $msl );
$msl_place = MSL_Zmanim::place_names( $msl, $msl_week );

/**
 * One row, when there is something to put in it.
 *
 * Declared here rather than in the template tags because nothing else needs it:
 * it is the shape of this card and would only be indirection anywhere else.
 *
 * @param string $key   Field key of the row's label.
 * @param string $value The Hebrew value.
 * @param string $value_en The English value, when the two differ.
 */
$msl_row = static function ( string $key, string $value, string $value_en = '' ) use ( $msl ): void {
	if ( '' === $value ) {
		return;
	}
	?>
	<div class="msl-zmanim__row">
		<dt class="msl-zmanim__label"<?php msl_i18n( 'zmanim', $key ); ?>><?php msl_the( $msl, $key ); ?></dt>
		<dd class="msl-zmanim__value">
			<?php if ( '' === $value_en ) : ?>
				<?php echo esc_html( $value ); ?>
			<?php else : ?>
				<?php
				/*
				 * Both halves are printed and CSS hides the one that is not in
				 * play. The language toggle swaps without a reload, and these
				 * two values come from an API rather than from a content field,
				 * so there is nothing for the script's dictionary to swap them
				 * with.
				 */
				?>
				<span data-msl-only="he"><?php echo esc_html( $value ); ?></span>
				<span data-msl-only="en"><?php echo esc_html( $value_en ); ?></span>
			<?php endif; ?>
		</dd>
	</div>
	<?php
};
?>
<section class="msl-zmanim" aria-labelledby="msl-zmanim-title" data-msl-rise>
	<h2 class="msl-zmanim__title" id="msl-zmanim-title"<?php msl_i18n( 'zmanim', 'title' ); ?>><?php msl_the( $msl, 'title' ); ?></h2>

	<dl class="msl-zmanim__list">
		<?php
		if ( null !== $msl_date ) {
			$msl_row( 'label_hdate', $msl_date['he'], $msl_date['en'] );
		}

		$msl_row( 'label_parsha', (string) $msl_week['parsha_he'], (string) $msl_week['parsha_en'] );

		$msl_row(
			'label_candles',
			(string) wp_date( 'H:i', (int) $msl_week['candles'], $msl_zone )
		);

		if ( (int) $msl_week['havdalah'] > 0 ) {
			$msl_row(
				'label_havdalah',
				(string) wp_date( 'H:i', (int) $msl_week['havdalah'], $msl_zone )
			);
		}
		?>
	</dl>

	<?php if ( '' !== (string) $msl_week['holiday_he'] ) : ?>
		<p class="msl-zmanim__holiday">
			<span data-msl-only="he"><?php echo esc_html( (string) $msl_week['holiday_he'] ); ?></span>
			<span data-msl-only="en"><?php echo esc_html( (string) $msl_week['holiday_en'] ); ?></span>
		</p>
	<?php endif; ?>

	<?php if ( '' !== $msl_place['he'] ) : ?>
		<?php
		/*
		 * Written out in both languages for the same reason as the values
		 * above: the place name is filled into the sentence here, so there is
		 * no finished string in the dictionary for the toggle to reach for.
		 */
		?>
		<p class="msl-zmanim__note">
			<?php foreach ( array( 'he', 'en' ) as $msl_lang ) : ?>
				<?php $msl_note = (string) ( $msl[ 'note_' . $msl_lang ] ?? '' ); ?>
				<?php if ( '' !== $msl_note ) : ?>
					<span data-msl-only="<?php echo esc_attr( $msl_lang ); ?>"><?php echo esc_html( sprintf( $msl_note, $msl_place[ $msl_lang ] ) ); ?></span>
				<?php endif; ?>
			<?php endforeach; ?>
		</p>
	<?php endif; ?>
</section>
