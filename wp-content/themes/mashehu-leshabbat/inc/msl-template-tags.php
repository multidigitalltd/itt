<?php
/**
 * Template helpers shared by the section partials.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * One value from a section, in the language being rendered.
 *
 * @param array<string, mixed> $section Resolved section content.
 * @param string               $key     Base key, without the language suffix.
 * @return string
 */
function msl_t( array $section, string $key ): string {
	return MSL_I18N::value( $section, $key );
}

/**
 * Echo one value, escaped.
 *
 * @param array<string, mixed> $section Resolved section content.
 * @param string               $key     Base key, without the language suffix.
 */
function msl_the( array $section, string $key ): void {
	echo esc_html( MSL_I18N::value( $section, $key ) );
}

/**
 * The `data-msl-i18n` attribute that lets the browser re-render a node when the
 * visitor flips the language.
 *
 * @param string $section Section key.
 * @param string $key     Base field key.
 * @return string
 */
function msl_i18n_attr( string $section, string $key ): string {
	return ' data-msl-i18n="' . esc_attr( $section . '.' . $key ) . '"';
}

/**
 * Echo the i18n attribute.
 *
 * @param string $section Section key.
 * @param string $key     Base field key.
 */
function msl_i18n( string $section, string $key ): void {
	// Built entirely from literals and already escaped by msl_i18n_attr().
	echo msl_i18n_attr( $section, $key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

/**
 * Render one section partial with its resolved content in scope.
 *
 * @param string $section Section key, matching both the partial name and the meta key.
 */
function msl_section( string $section ): void {
	$msl = MSL_Meta::get( $section );

	require MSL_DIR . 'template-parts/home/' . str_replace( '_', '-', $section ) . '.php';
}

/**
 * A number formatted the way every figure in the design is formatted.
 *
 * @param int $value Raw number.
 * @return string
 */
function msl_num( int $value ): string {
	return number_format_i18n( $value );
}

/**
 * The Shabbat candle used in the hero eyebrow and the brand mark.
 *
 * Purely decorative — it repeats the meaning of the words beside it — so it is
 * hidden from assistive technology rather than given a label nobody needs.
 *
 * @param float $glow_duration Seconds for the outer glow flicker.
 * @param float $flame_duration Seconds for the inner flame flicker.
 * @param float $delay         Seconds of offset, so two candles never flicker in step.
 */
function msl_candle_svg( float $glow_duration, float $flame_duration, float $delay = 0.0 ): void {
	$style = static fn( float $duration, float $offset ): string => sprintf(
		'animation-duration:%.2fs;animation-delay:%.2fs',
		$duration,
		$offset
	);
	?>
	<svg class="msl-candle" viewBox="0 0 34 78" width="30" height="70" aria-hidden="true" focusable="false">
		<ellipse class="msl-candle__glow" cx="17" cy="9" rx="4.6" ry="9" style="<?php echo esc_attr( $style( $glow_duration, $delay ) ); ?>"></ellipse>
		<ellipse class="msl-candle__flame" cx="17" cy="11" rx="2.3" ry="5.6" style="<?php echo esc_attr( $style( $flame_duration, $delay * 0.66 ) ); ?>"></ellipse>
		<rect class="msl-candle__wick" x="16.4" y="15" width="1.2" height="4" rx="0.6"></rect>
		<rect class="msl-candle__body" x="12" y="19" width="10" height="24" rx="2.5"></rect>
		<rect class="msl-candle__highlight" x="12" y="19" width="3" height="24" rx="1.5"></rect>
		<rect class="msl-candle__cup" x="10.5" y="41" width="13" height="6" rx="3"></rect>
		<rect class="msl-candle__stem" x="14.5" y="46" width="5" height="18" rx="2.5"></rect>
		<rect class="msl-candle__stem-hl" x="15.6" y="46" width="1.6" height="18" rx="0.8"></rect>
		<ellipse class="msl-candle__knop" cx="17" cy="55" rx="4.4" ry="3.4"></ellipse>
		<path class="msl-candle__foot" d="M6 74 Q6 65 17 65 Q28 65 28 74 Z"></path>
		<rect class="msl-candle__base" x="4.5" y="73" width="25" height="4.5" rx="2.2"></rect>
	</svg>
	<?php
}

/**
 * The brand mark: the configured logo, or the theme's own light-dot glyph.
 *
 * @param array<string, mixed> $chrome Resolved chrome section.
 * @param int                  $size   Rendered size in pixels.
 */
function msl_logo( array $chrome, int $size = 28 ): void {
	$logo_id = (int) ( $chrome['logo'] ?? 0 );

	if ( $logo_id > 0 ) {
		echo wp_get_attachment_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core escapes its own attributes.
			$logo_id,
			'medium',
			false,
			array(
				'class'    => 'msl-logo__image',
				'alt'      => msl_t( $chrome, 'brand' ),
				'loading'  => 'eager',
				'decoding' => 'async',
			)
		);

		return;
	}
	?>
	<span class="msl-logo__mark" style="--msl-mark-size:<?php echo absint( $size ); ?>px" aria-hidden="true">
		<span class="msl-logo__dot"></span>
	</span>
	<?php
}

/**
 * Map points: the ones configured on the page, plus the ones real joins added.
 *
 * @param int $page_id Page ID.
 * @return array<int, array<string, float>>
 */
function msl_map_points( int $page_id ): array {
	$points = array();

	foreach ( (array) ( MSL_Meta::get( 'map', $page_id )['points'] ?? array() ) as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$points[] = array(
			'lat'    => (float) ( $row['lat'] ?? 0 ),
			'lng'    => (float) ( $row['lng'] ?? 0 ),
			'weight' => (float) ( $row['weight'] ?? 1 ),
		);
	}

	foreach ( MSL_Joins::map_points( $page_id ) as $row ) {
		$points[] = $row;
	}

	return $points;
}

/**
 * The activity rows for the marquee: real joins first, seed copy filling in.
 *
 * The design needs enough items for the track to be wider than the viewport; a
 * quiet first hour must not leave a half-empty pill scrolling past.
 *
 * @param int $page_id Page ID.
 * @param int $minimum Rows the track needs to look continuous.
 * @return array<int, string>
 */
function msl_marquee_rows( int $page_id, int $minimum = 12 ): array {
	$rows = array();

	foreach ( MSL_Joins::feed( $page_id, MSL_Meta::get( 'join', $page_id ) ) as $entry ) {
		$rows[] = (string) $entry['text'];
	}

	if ( count( $rows ) >= $minimum ) {
		return array_slice( $rows, 0, 40 );
	}

	foreach ( (array) ( MSL_Meta::get( 'marquee', $page_id )['rows'] ?? array() ) as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$text = MSL_I18N::value( $row, 'text' );

		if ( '' !== trim( $text ) ) {
			$rows[] = $text;
		}
	}

	return array_slice( $rows, 0, 40 );
}

/**
 * The urgency line under the closing heading.
 *
 * @param array<string, mixed> $closing  Resolved closing section.
 * @param array<string, mixed> $campaign Resolved campaign section.
 * @return string
 */
function msl_urgency( array $closing, array $campaign ): string {
	if ( 1 === (int) $campaign['closed'] ) {
		return msl_t( $closing, 'closed_note' );
	}

	$hours = ( MSL_Theme::candle_lighting( $campaign ) - time() ) / HOUR_IN_SECONDS;

	if ( $hours < 12 ) {
		return sprintf( msl_t( $closing, 'urgency_soon' ), max( 1, (int) round( $hours ) ) );
	}

	return msl_t( $closing, 'urgency_default' );
}

/**
 * The countdown chip text, rendered server-side so the header never flashes empty.
 *
 * @param array<string, mixed> $chrome   Resolved chrome section.
 * @param array<string, mixed> $campaign Resolved campaign section.
 * @return string
 */
function msl_countdown( array $chrome, array $campaign ): string {
	$parsha    = msl_t( $campaign, 'parsha' );
	$remaining = max( 0, MSL_Theme::candle_lighting( $campaign ) - time() );
	$days      = (int) floor( $remaining / DAY_IN_SECONDS );

	if ( $days > 0 ) {
		return sprintf( msl_t( $chrome, 'countdown_days' ), $parsha, $days );
	}

	$clock = sprintf(
		'%02d:%02d:%02d',
		(int) floor( $remaining % DAY_IN_SECONDS / HOUR_IN_SECONDS ),
		(int) floor( $remaining % HOUR_IN_SECONDS / MINUTE_IN_SECONDS ),
		$remaining % MINUTE_IN_SECONDS
	);

	return sprintf( msl_t( $chrome, 'countdown_clock' ), $parsha, $clock );
}
