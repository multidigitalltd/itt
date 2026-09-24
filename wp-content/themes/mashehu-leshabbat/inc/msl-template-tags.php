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
 * Whether the campaign has uploaded a logo of its own.
 *
 * @param array<string, mixed> $chrome Resolved chrome section.
 * @return bool
 */
function msl_has_logo( array $chrome ): bool {
	return (int) ( $chrome['logo'] ?? 0 ) > 0;
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
				/*
				 * Decorative, deliberately. The only place this is printed is
				 * inside the link to the home page, which carries the site's
				 * name as text beside it — visible, or hidden from the screen
				 * once the logo says it. An alt repeating that name makes a
				 * screen reader announce it twice, which is what axe's
				 * image-redundant-alt rule is for.
				 */
				'alt'      => '',
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
			'lat'     => (float) ( $row['lat'] ?? 0 ),
			'lng'     => (float) ( $row['lng'] ?? 0 ),
			'weight'  => (float) ( $row['weight'] ?? 1 ),
			// A point the campaign placed by hand can name its country too.
			'country' => (string) ( $row['country'] ?? '' ),
			'n'       => max( 0, (int) ( $row['n'] ?? 0 ) ),
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
 * What this Shabbat is called, ready to drop into a sentence.
 *
 * Fetched when the times are switched on, which is the whole point of switching
 * them on: nobody has to remember to retype a portion name every Thursday. The
 * hand-typed field stays as the fallback, and as the answer for a campaign that
 * would rather not call out at all.
 *
 * The word "parashat" belongs to the name and not to the sentence around it,
 * because some Shabbatot have no portion. Through Tishrei the coming Shabbat is
 * Sukkot or Simchat Torah, and a countdown built out of "Shabbat Parashat %s"
 * can only either print "Shabbat Parashat Sukkot" or go back to whatever was
 * typed by hand in February. So the name arrives whole — "פרשת משפטים" one week
 * and "סוכות א׳" the next — and the sentence just places it.
 *
 * @param array<string, mixed> $campaign Resolved campaign section.
 * @return string
 */
function msl_parsha( array $campaign ): string {
	$lang    = MSL_I18N::lang();
	$week    = MSL_Zmanim::week( MSL_Meta::get( 'zmanim' ) );
	$portion = null !== $week ? (string) $week[ 'parsha_' . $lang ] : '';

	if ( '' !== $portion ) {
		return msl_parsha_prefix( $lang ) . $portion;
	}

	$holiday = null !== $week ? (string) $week[ 'holiday_' . $lang ] : '';

	if ( '' !== $holiday ) {
		return $holiday;
	}

	return msl_parsha_prefix( $lang ) . msl_t( $campaign, 'parsha' );
}

/**
 * The word that introduces a portion's name, with its trailing space.
 *
 * Not a content field. It is a fixed word in each language, it is the same word
 * every week, and a campaign that edited it to something else would break the
 * only sentence it appears in.
 *
 * @param string $lang Language code.
 * @return string
 */
function msl_parsha_prefix( string $lang ): string {
	return 'he' === $lang ? 'פרשת ' : 'Parashat ';
}

/**
 * The countdown chip text, rendered server-side so the header never flashes empty.
 *
 * @param array<string, mixed> $chrome   Resolved chrome section.
 * @param array<string, mixed> $campaign Resolved campaign section.
 * @return string
 */
function msl_countdown( array $chrome, array $campaign ): string {
	return msl_countdown_text(
		$chrome,
		msl_parsha( $campaign ),
		max( 0, MSL_Theme::candle_lighting( $campaign ) - time() )
	);
}

/**
 * The countdown sentence for a given number of seconds.
 *
 * Split out from msl_countdown() so the phrasing can be checked at every
 * boundary without moving the clock or the calendar — and because the browser
 * renders the same sentence from the same seconds, and the two drifting apart
 * is the kind of thing nobody sees until the last hour before Shabbat.
 *
 * Under a day the unit changes rather than the number growing a colon.
 * "בעוד 06:24:13" is a stopwatch, and nobody reads a stopwatch to work out
 * whether there is still time to get to the shops. Hours until the last hour,
 * then minutes.
 *
 * Hebrew counts one, two and many with three different words, which is why
 * each unit has three lines rather than one: "בעוד 1 ימים" in the header of a
 * Hebrew site is the kind of small wrongness people notice.
 *
 * @param array<string, mixed> $chrome    Resolved chrome section.
 * @param string               $name      What this Shabbat is called.
 * @param int                  $remaining Seconds until candle lighting.
 * @return string
 */
function msl_countdown_text( array $chrome, string $name, int $remaining ): string {
	$days = (int) floor( $remaining / DAY_IN_SECONDS );

	if ( $days > 2 ) {
		return sprintf( msl_t( $chrome, 'countdown_days' ), $name, $days );
	}

	if ( 2 === $days ) {
		return sprintf( msl_t( $chrome, 'countdown_2days' ), $name );
	}

	if ( 1 === $days ) {
		return sprintf( msl_t( $chrome, 'countdown_day' ), $name );
	}

	$hours = (int) floor( $remaining / HOUR_IN_SECONDS );

	if ( $hours > 2 ) {
		return sprintf( msl_t( $chrome, 'countdown_hours' ), $name, $hours );
	}

	if ( 2 === $hours ) {
		return sprintf( msl_t( $chrome, 'countdown_2hours' ), $name );
	}

	if ( 1 === $hours ) {
		return sprintf( msl_t( $chrome, 'countdown_hour' ), $name );
	}

	$minutes = (int) floor( $remaining / MINUTE_IN_SECONDS );

	if ( $minutes > 1 ) {
		return sprintf( msl_t( $chrome, 'countdown_minutes' ), $name, $minutes );
	}

	return sprintf( msl_t( $chrome, 'countdown_minute' ), $name );
}


/**
 * How long ago something happened, as a sentence.
 *
 * The same shape as msl_countdown_text() and for the same reason: Hebrew counts
 * one, two and many in three different words, so "5 minutes ago" and "two
 * minutes ago" cannot be one template with a number in it. Every sentence is a
 * field, so the campaign can soften any of them without touching the code.
 *
 * The page is rendered once and may sit in a cache for a while, so the browser
 * re-derives all of these from the timestamp on the element. This is what the
 * first paint says — and what somebody with JavaScript off keeps seeing, which
 * is why it has to be right rather than merely present.
 *
 * @param array<string, mixed> $copy    Resolved groups content.
 * @param int                  $elapsed Seconds since it happened.
 * @return string
 */
function msl_ago_text( array $copy, int $elapsed ): string {
	$elapsed = max( 0, $elapsed );
	$days    = (int) floor( $elapsed / DAY_IN_SECONDS );

	if ( $days > 2 ) {
		return sprintf( msl_t( $copy, 'ago_days' ), $days );
	}

	if ( 2 === $days ) {
		return msl_t( $copy, 'ago_two_days' );
	}

	if ( 1 === $days ) {
		return msl_t( $copy, 'ago_yesterday' );
	}

	$hours = (int) floor( $elapsed / HOUR_IN_SECONDS );

	if ( $hours > 2 ) {
		return sprintf( msl_t( $copy, 'ago_hours' ), $hours );
	}

	if ( 2 === $hours ) {
		return msl_t( $copy, 'ago_two_hours' );
	}

	if ( 1 === $hours ) {
		return msl_t( $copy, 'ago_hour' );
	}

	$minutes = (int) floor( $elapsed / MINUTE_IN_SECONDS );

	if ( $minutes > 2 ) {
		return sprintf( msl_t( $copy, 'ago_minutes' ), $minutes );
	}

	if ( 2 === $minutes ) {
		return msl_t( $copy, 'ago_two_minutes' );
	}

	if ( 1 === $minutes ) {
		return msl_t( $copy, 'ago_minute' );
	}

	return msl_t( $copy, 'ago_now' );
}

/**
 * Render a multi-line content value as paragraphs.
 *
 * The editor fields are plain textareas on purpose — no editor, no markup to
 * sanitise, no way for a pasted paragraph to arrive carrying a style attribute.
 * A blank line is the only structure they carry, and this is what turns it into
 * structure on the page.
 *
 * @param string $text Raw field value.
 */
function msl_paragraphs( string $text ): void {
	$blocks = preg_split( "/\n\s*\n/", trim( $text ) );

	foreach ( (array) $blocks as $block ) {
		$block = trim( (string) $block );

		if ( '' === $block ) {
			continue;
		}

		printf( '<p>%s</p>', nl2br( esc_html( $block ) ) );
	}
}

/**
 * The site menu, or nothing at all when no links are configured.
 *
 * @param array<string, mixed> $nav Resolved nav content.
 */
function msl_nav_links( array $nav ): void {
	foreach ( msl_nav_rows( $nav ) as $row ) {
		if ( '' !== $row['action'] ) {
			printf(
				'<li class="msl-menu__item"><button type="button" class="msl-menu__link msl-menu__link--action" data-msl-open-%s%s>%s</button></li>',
				esc_attr( $row['action'] ),
				msl_i18n_attr( 'nav', 'links.' . $row['index'] . '.label' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				esc_html( $row['label'] )
			);

			continue;
		}

		printf(
			'<li class="msl-menu__item"><a class="msl-menu__link" href="%s"%s>%s</a></li>',
			esc_url( $row['href'] ),
			msl_i18n_attr( 'nav', 'links.' . $row['index'] . '.label' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_html( $row['label'] )
		);
	}
}

/**
 * The menu, resolved into things that actually exist.
 *
 * A row is dropped when it has no label or when its destination cannot be
 * resolved — a menu entry that leads nowhere is worse than one fewer entry, and
 * this is the function that decides it once for both the header and the check
 * that asks whether there is a menu at all.
 *
 * @param array<string, mixed> $nav Resolved nav section.
 * @return array<int, array{index: int, label: string, href: string, action: string}>
 */
function msl_nav_rows( array $nav ): array {
	$rows = array();

	foreach ( (array) ( $nav['links'] ?? array() ) as $index => $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$label = msl_t( $row, 'label' );

		if ( '' === $label ) {
			continue;
		}

		$resolved = msl_nav_destination( $row );

		if ( '' === $resolved['href'] && '' === $resolved['action'] ) {
			continue;
		}

		$rows[] = array(
			'index'  => (int) $index,
			'label'  => $label,
			'href'   => $resolved['href'],
			'action' => $resolved['action'],
		);
	}

	return $rows;
}

/**
 * Turn one stored menu row into an address or an action.
 *
 * Rows saved before destinations existed carry only a typed address, so those
 * are read the way they were meant: a `#` is an anchor on the campaign page,
 * `#invite` is the share window, and a root-relative path is resolved against
 * the site's own root so it survives an install in a subdirectory.
 *
 * @param array<string, mixed> $row One stored menu row.
 * @return array{href: string, action: string}
 */
function msl_nav_destination( array $row ): array {
	$none   = array(
		'href'   => '',
		'action' => '',
	);
	$url    = trim( (string) ( $row['url'] ?? '' ) );
	$target = (string) ( $row['target'] ?? '' );

	if ( '' === $target ) {
		if ( '#invite' === $url ) {
			$target = 'invite';
		} elseif ( str_starts_with( $url, '#' ) ) {
			return array(
				'href'   => msl_campaign_anchor( ltrim( $url, '#' ) ),
				'action' => '',
			);
		} else {
			$target = 'custom';
		}
	}

	$spec = MSL_Theme::NAV_TARGETS[ $target ] ?? array();

	// An anchor on a page of its own — the form that opens a group — rather
	// than an anchor on the campaign page.
	if ( isset( $spec['anchor'], $spec['page'] ) ) {
		$page = MSL_Importer::page_id( $spec['page'] );

		return $page > 0 ? array(
			'href'   => (string) get_permalink( $page ) . '#' . $spec['anchor'],
			'action' => '',
		) : $none;
	}

	if ( isset( $spec['anchor'] ) ) {
		return array(
			'href'   => msl_campaign_anchor( $spec['anchor'] ),
			'action' => '',
		);
	}

	if ( isset( $spec['action'] ) ) {
		return array(
			'href'   => '',
			'action' => $spec['action'],
		);
	}

	if ( isset( $spec['page'] ) ) {
		// The groups page in the menu means the list of them. With the list
		// switched off the item would land on a page carrying only the form,
		// which is not what its label promised.
		if ( 'groups' === $spec['page'] && ! msl_groups_archive_on() ) {
			return $none;
		}

		$page = MSL_Importer::page_id( $spec['page'] );

		return $page > 0 ? array(
			'href'   => (string) get_permalink( $page ),
			'action' => '',
		) : $none;
	}

	if ( '' === $url ) {
		return $none;
	}

	// A path typed without a domain belongs to this site, and this site may not
	// be at the root of its own domain.
	if ( str_starts_with( $url, '/' ) ) {
		return array(
			'href'   => home_url( $url ),
			'action' => '',
		);
	}

	return array(
		'href'   => $url,
		'action' => '',
	);
}

/**
 * The campaign page itself.
 *
 * Falls back to the site's front page, so a reading page never carries a link
 * to nowhere on an install where the campaign page was renamed or rebuilt.
 *
 * @return string
 */
function msl_campaign_url(): string {
	$page = MSL_Importer::page_id();

	return $page > 0 ? (string) get_permalink( $page ) : home_url( '/' );
}

/**
 * The address of a section of the campaign page.
 *
 * The fragment is appended to the permalink as it comes, with nothing added
 * between them: a pretty permalink already ends in a slash, and a plain one is
 * a query string that a slash would break.
 *
 * @param string $id Element id, without the hash.
 * @return string
 */
function msl_campaign_anchor( string $id ): string {
	if ( '' === $id ) {
		return '';
	}

	$campaign = MSL_Importer::page_id();
	$base     = $campaign > 0 ? (string) get_permalink( $campaign ) : home_url( '/' );

	return $base . '#' . $id;
}

/**
 * The groups page, with an optional anchor on it.
 *
 * Empty when there is no groups page in this install, which is the signal to
 * every caller to render nothing at all rather than a link into the void. The
 * address is built from the page WordPress actually holds, for the reason the
 * menu learned the hard way: a typed "/kvutzot/" is wrong the moment the site
 * lives in a subdirectory, and wrong silently.
 *
 * @param string $anchor Fragment id, without the hash.
 * @return string
 */
/**
 * Does this site have a page that lists all the groups?
 *
 * A campaign can run groups without a public index of them: people open a
 * group, send its address to their own family, and nobody browses a directory
 * of strangers' dedications. That is a switch in the groups box, and when it is
 * off every way in — the menu item, the link in the campaign page's invitation,
 * the way back from a group — goes with it. A button that leads to a list that
 * is not there is worse than no button.
 *
 * @return bool
 */
function msl_groups_archive_on(): bool {
	$page = MSL_Importer::page_id( 'groups' );

	if ( $page < 1 ) {
		return false;
	}

	return 1 === (int) ( MSL_Meta::get( 'groups', $page )['show_archive'] ?? 0 );
}

function msl_groups_url( string $anchor = '' ): string {
	$page = MSL_Importer::page_id( 'groups' );

	if ( $page < 1 ) {
		return '';
	}

	$url = (string) get_permalink( $page );

	return '' !== $anchor ? $url . '#' . $anchor : $url;
}

/**
 * The animated candles in the hero.
 *
 * Fourteen candles in two staggered columns of seven, absolutely positioned
 * inside the hero. Every candle is a different size and carries its own timings,
 * and none of the durations is a multiple of another — identical or harmonic
 * periods would drift into step within a minute and the field would start to
 * pulse as one thing.
 *
 * The anatomy is written as inline styles because every measurement is derived
 * from that candle's own width: a class per candle would be fourteen classes
 * that each apply once. Only the @keyframes live in the stylesheet, since a
 * keyframe cannot be expressed inline.
 *
 * Offsets are always positive and small, so a candle is never pushed outside the
 * hero on a narrow screen where overflow-x is hidden and would clip it.
 *
 * @param int $count How many of the fourteen to place. Zero hides them all.
 */
function msl_hero_candles( int $count ): void {
	$candles = array(
		array( 'start', 34, 2,  22, 86,  4.3, 0.0, 8.3,  0.0 ),
		array( 'start', 6,  15, 17, 62,  5.7, 0.7, 9.7,  1.1 ),
		array( 'start', 52, 27, 25, 97,  3.7, 1.3, 7.9,  2.3 ),
		array( 'start', 18, 40, 19, 71,  6.1, 0.4, 10.3, 0.6 ),
		array( 'start', 44, 53, 27, 104, 4.9, 1.9, 8.7,  3.1 ),
		array( 'start', 10, 67, 15, 54,  3.4, 1.1, 9.1,  1.7 ),
		array( 'start', 38, 80, 21, 81,  6.6, 2.3, 7.5,  2.9 ),
		array( 'end',   28, 7,  24, 92,  5.3, 0.9, 9.4,  0.3 ),
		array( 'end',   58, 20, 18, 66,  3.9, 1.7, 8.1,  2.1 ),
		array( 'end',   4,  33, 26, 101, 6.3, 0.2, 10.1, 1.3 ),
		array( 'end',   46, 46, 16, 58,  4.1, 2.1, 7.7,  3.3 ),
		array( 'end',   14, 60, 23, 89,  5.9, 0.6, 9.9,  0.9 ),
		array( 'end',   62, 73, 20, 76,  3.6, 1.5, 8.9,  2.7 ),
		array( 'end',   24, 86, 15, 50,  6.4, 1.0, 10.4, 1.9 ),
	);

	$count = max( 0, min( count( $candles ), $count ) );

	if ( 0 === $count ) {
		return;
	}

	$n = static fn( float $v ): string => rtrim( rtrim( number_format( $v, 2, '.', '' ), '0' ), '.' );

	$paired = array();

	echo '<div class="msl-hero__candles" aria-hidden="true" data-msl-candles>';

	for ( $i = 0; $i < $count; $i++ ) {
		list( $side, $inset, $top, $w, $h, $sway, $sway_delay, $bob, $bob_delay ) = $candles[ $i ];

		$flame_w = $w * 0.62;
		$flame_h = $w * 1.55;
		$halo_w  = $w * 2.1;
		$halo_h  = $w * 2.6;
		$core_w  = $flame_w * 0.44;
		$core_h  = $flame_h * 0.52;
		$glow_d  = $w * 5.4;
		$pool_w  = $w * 0.78;
		$pool_h  = $w * 0.3;
		$smoke_w = $w * 0.5;
		$smoke_h = $w * 1.1;

		/*
		 * How far in from the edge this candle sits. A narrow screen has no room
		 * for the inner columns — they land on the headline — so the depth is
		 * written out here and the stylesheet drops them by band. Reading it back
		 * off the inline style is not something CSS can do.
		 */
		$depth = $inset <= 20 ? 'outer' : ( $inset <= 40 ? 'mid' : 'inner' );

		/*
		 * One candle on each side is marked, and it is the first of that side
		 * in this list. On a phone those two are the only ones kept, and they
		 * stand either side of the headline the way the whole field does on a
		 * wide screen. Marking them here rather than counting in CSS is what
		 * makes it survive the campaign changing how many candles it shows:
		 * nth-child(10) is the second side's first candle only while the count
		 * happens to be fourteen.
		 */
		$pair = '';

		if ( ! in_array( $side, $paired, true ) ) {
			$paired[] = $side;
			$pair     = ' data-msl-hero-pair="' . $side . '"';
		}

		printf(
			'<span class="msl-hero__candle" data-msl-candle data-msl-depth="%s"%s style="position:absolute;%s:%dpx;top:%d%%;opacity:1;transform:translate(0px,0px) scale(1);transition:opacity 1.1s ease,transform 1.4s cubic-bezier(.2,.8,.2,1);cursor:pointer;">',
			esc_attr( $depth ),
			$pair, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from a literal pair of values just above.
			'start' === $side ? 'inset-inline-start' : 'inset-inline-end',
			(int) $inset,
			(int) $top
		);

		// The bob lives on its own element: the wrapper's transform is the one
		// the script writes, and a keyframe on the same property would win.
		printf(
			'<span style="display:block;animation:msl-candle-bob %ss ease-in-out %ss infinite;">',
			esc_attr( $n( $bob ) ),
			esc_attr( $n( $bob_delay ) )
		);

		echo '<span style="display:flex;flex-direction:column;align-items:center;">';

		// --- the flame block, with the lit marker behind it ------------------
		printf( '<span style="position:relative;display:block;width:%spx;height:%spx;">', esc_attr( $n( $w ) ), esc_attr( $n( $flame_h ) ) );

		printf(
			'<span data-msl-candle-glow style="position:absolute;inset-inline-start:50%%;top:%spx;width:%spx;height:%spx;margin-inline-start:-%spx;border-radius:50%%;background:radial-gradient(circle,oklch(0.93 0.13 82 / 0.42) 0%%,oklch(0.88 0.15 72 / 0.16) 46%%,transparent 76%%);filter:blur(6px);pointer-events:none;opacity:1;transition:opacity 1s ease;animation:msl-candle-glow 3.1s ease-in-out infinite;z-index:0;"></span>',
			esc_attr( $n( $flame_h / 2 - $glow_d / 2 ) ),
			esc_attr( $n( $glow_d ) ),
			esc_attr( $n( $glow_d ) ),
			esc_attr( $n( $glow_d / 2 ) )
		);

		printf(
			'<span data-msl-candle-flame style="position:absolute;inset-inline-start:50%%;bottom:0;width:%spx;height:%spx;margin-inline-start:-%spx;transform-origin:50%% 100%%;opacity:1;transition:opacity 1s ease;animation:msl-candle-sway %ss ease-in-out %ss infinite;z-index:1;">',
			esc_attr( $n( $flame_w ) ),
			esc_attr( $n( $flame_h ) ),
			esc_attr( $n( $flame_w / 2 ) ),
			esc_attr( $n( $sway ) ),
			esc_attr( $n( $sway_delay ) )
		);

		// (a) the wide blurred halo.
		printf(
			'<span style="position:absolute;inset-inline-start:50%%;bottom:-%spx;width:%spx;height:%spx;margin-inline-start:-%spx;border-radius:50%%;background:radial-gradient(circle,oklch(0.86 0.16 66 / 0.55) 0%%,oklch(0.78 0.17 52 / 0.22) 45%%,transparent 72%%);filter:blur(3px);animation:msl-candle-halo %ss ease-in-out infinite;"></span>',
			esc_attr( $n( $halo_h * 0.22 ) ),
			esc_attr( $n( $halo_w ) ),
			esc_attr( $n( $halo_h ) ),
			esc_attr( $n( $halo_w / 2 ) ),
			esc_attr( $n( $sway * 0.71 ) )
		);

		// (b) the flame body.
		echo '<span style="position:absolute;inset:0;border-radius:50% 50% 44% 44% / 66% 66% 34% 34%;background:linear-gradient(to top,oklch(0.62 0.20 34) 0%,oklch(0.78 0.18 58) 34%,oklch(0.90 0.15 82) 68%,oklch(0.985 0.04 92) 100%);animation:msl-candle-flick 1.6s ease-in-out infinite;"></span>';

		// (c) the pale inner core, blue where it meets the wick.
		printf(
			'<span style="position:absolute;inset-inline-start:50%%;bottom:6%%;width:%spx;height:%spx;margin-inline-start:-%spx;border-radius:50%% 50%% 44%% 44%% / 66%% 66%% 34%% 34%%;background:linear-gradient(to top,oklch(0.72 0.12 250 / 0.85) 0%%,oklch(0.95 0.05 96) 42%%,oklch(0.995 0.012 96) 100%%);filter:blur(0.6px);"></span>',
			esc_attr( $n( $core_w ) ),
			esc_attr( $n( $core_h ) ),
			esc_attr( $n( $core_w / 2 ) )
		);

		echo '</span>';

		// The smoke only exists once the flame is out.
		printf(
			'<span data-msl-candle-smoke style="position:absolute;inset-inline-start:50%%;bottom:2px;width:%spx;height:%spx;margin-inline-start:-%spx;border-radius:50%%;background:radial-gradient(circle,oklch(0.62 0.01 80 / 0.42) 0%%,oklch(0.7 0.008 80 / 0.14) 52%%,transparent 78%%);filter:blur(3px);opacity:0;transition:opacity 1s ease;pointer-events:none;"></span>',
			esc_attr( $n( $smoke_w ) ),
			esc_attr( $n( $smoke_h ) ),
			esc_attr( $n( $smoke_w / 2 ) )
		);

		echo '</span>';

		// --- wick ------------------------------------------------------------
		echo '<span style="display:block;width:1.6px;height:6px;border-radius:1px;background:linear-gradient(to bottom,oklch(0.42 0.03 62),oklch(0.22 0.02 58));"></span>';

		// --- wax body, with the lit pool at its top --------------------------
		printf(
			'<span style="position:relative;display:block;width:%spx;height:%spx;border-radius:%spx %spx 4px 4px;background:linear-gradient(90deg,oklch(0.80 0.045 76) 0%%,oklch(0.93 0.025 82) 22%%,oklch(0.985 0.014 88) 50%%,oklch(0.88 0.035 78) 78%%,oklch(0.76 0.05 72) 100%%);box-shadow:0 6px 14px oklch(0.45 0.06 58 / 0.18),inset 0 1px 0 oklch(1 0 0 / 0.75);">',
			esc_attr( $n( $w ) ),
			esc_attr( $n( $h ) ),
			esc_attr( $n( $w * 0.46 ) ),
			esc_attr( $n( $w * 0.46 ) )
		);

		printf(
			'<span data-msl-candle-pool style="position:absolute;inset-inline-start:50%%;top:-%spx;width:%spx;height:%spx;margin-inline-start:-%spx;border-radius:50%%;background:radial-gradient(circle,oklch(0.96 0.10 88 / 0.95) 0%%,oklch(0.86 0.12 74 / 0.5) 58%%,transparent 84%%);opacity:1;transition:opacity 1s ease;pointer-events:none;"></span>',
			esc_attr( $n( $pool_h / 2 ) ),
			esc_attr( $n( $pool_w ) ),
			esc_attr( $n( $pool_h ) ),
			esc_attr( $n( $pool_w / 2 ) )
		);

		echo '</span></span></span></span>';
	}

	echo '</div>';
}

/**
 * Which of the theme's section sets a page carries.
 *
 * @param int $post_id Page ID.
 * @return string Template key, or an empty string for a page outside the theme.
 */
function msl_page_template_key( int $post_id ): string {
	return MSL_Theme::SECTION_SETS[ (string) get_page_template_slug( $post_id ) ] ?? '';
}

/**
 * How far a group has come, as a whole percent.
 *
 * Capped at a hundred. A group that passed its target has not "achieved 140%" —
 * it finished, and the bar should say so rather than run off its own track.
 *
 * @param array<string, mixed> $group Group row carrying count and target.
 * @return int
 */
function msl_group_pct( array $group ): int {
	$target = max( 1, (int) $group['target'] );

	return (int) min( 100, floor( (int) $group['count'] * 100 / $target ) );
}

/**
 * A group's dedication line: "in the merit of" and the name.
 *
 * Empty when the group has no dedication or nobody was named — the two halves
 * only mean something together, and "in the merit of" on its own is worse than
 * a card with one line fewer.
 *
 * @param array<string, mixed> $group Group row.
 * @param array<string, mixed> $copy  Resolved groups section.
 * @return string
 */
function msl_group_dedication( array $group, array $copy ): string {
	$parts = msl_group_dedication_parts( $group, $copy );

	return array() === $parts ? '' : trim( $parts['label'] . ' ' . $parts['name'] );
}

/**
 * The same dedication, as its two halves.
 *
 * The halves are what callers actually need, because they are two different
 * kinds of thing: "in the merit of" is copy, translated and editable in the
 * panel, and the name beside it is a person's name, which is the same in every
 * language. Anything that prints them as one sentence has to concatenate them
 * itself and loses the ability to translate the first half on a language
 * switch — which is exactly what the group card did.
 *
 * @param array<string, mixed> $group Group row.
 * @param array<string, mixed> $copy  Resolved groups section.
 * @return array{key: string, label: string, name: string}|array{} Empty when there is no dedication.
 */
function msl_group_dedication_parts( array $group, array $copy ): array {
	$key  = MSL_Groups::OCCASIONS[ (int) ( $group['occasion'] ?? 0 ) ] ?? '';
	$name = trim( (string) ( $group['honouree'] ?? '' ) );

	if ( '' === $key || '' === $name ) {
		return array();
	}

	return array(
		'key'   => 'occ_' . $key,
		'label' => msl_t( $copy, 'occ_' . $key ),
		'name'  => $name,
	);
}

/**
 * The same two halves, shaped for the browser.
 *
 * The key is a full copy path rather than a bare field name, because that is
 * what the browser's dictionary is keyed by and what its language switch looks
 * things up with. Null — not an empty string — when there is no dedication, so
 * "this candle came through no group" and "this group named nobody" are the
 * same answer to the one question the card asks.
 *
 * @param array<string, mixed> $group Group row, or its two relevant columns.
 * @param array<string, mixed> $copy  Resolved groups section.
 * @return array{key: string, name: string}|null
 */
function msl_group_dedication_pair( array $group, array $copy ): ?array {
	$parts = msl_group_dedication_parts( $group, $copy );

	return array() === $parts ? null : array(
		'key'  => 'groups.' . $parts['key'],
		'name' => $parts['name'],
	);
}

/**
 * A group's dedication as markup, with the translatable half marked as such.
 *
 * Two elements and not one string: the language switch happens in the browser
 * with no reload, and it works by replacing the text of nodes that name a copy
 * key. A dedication printed as one string has no key to name — half of it is a
 * person's name — so it stayed in Hebrew on an English page. Split, the half
 * that is copy carries its key and changes, and the name stays the name.
 *
 * @param array<string, mixed> $group Group row.
 * @param array<string, mixed> $copy  Resolved groups section.
 * @param string               $class Class for the wrapper.
 * @return string Empty when there is no dedication.
 */
function msl_group_dedication_html( array $group, array $copy, string $class = 'msl-ded' ): string {
	$parts = msl_group_dedication_parts( $group, $copy );

	if ( array() === $parts ) {
		return '';
	}

	return sprintf(
		'<span class="%s"><span class="%s__what" data-msl-i18n="groups.%s">%s</span> <span class="%s__who">%s</span></span>',
		esc_attr( $class ),
		esc_attr( $class ),
		esc_attr( $parts['key'] ),
		esc_html( $parts['label'] ),
		esc_attr( $class ),
		esc_html( $parts['name'] )
	);
}
