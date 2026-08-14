<?php
/**
 * Template helpers shared by the section partials.
 *
 * @package ITT_Landing
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Intrinsic size of every image bundled with the theme.
 *
 * Printing width/height on the fallback images keeps CLS at zero without a
 * filesystem read per image on every render.
 */
const ITT_IMAGE_SIZES = array(
	'hero-papercut.webp'       => array( 1122, 1402 ),
	'logo-merkaz-yashir.webp'  => array( 1111, 451 ),
	'lead-video-poster.webp'   => array( 1040, 586 ),
	'testimonial-1.webp'       => array( 696, 392 ),
	'testimonial-2.webp'       => array( 696, 385 ),
	'testimonial-3.webp'       => array( 696, 382 ),
	'gallery-1.webp'           => array( 1200, 800 ),
	'gallery-2.webp'           => array( 766, 510 ),
	'gallery-3.webp'           => array( 766, 510 ),
	'gallery-4.webp'           => array( 766, 510 ),
	'gallery-5.webp'           => array( 766, 510 ),
	'gallery-6.webp'           => array( 766, 510 ),
	'dr-amir-kulik.webp'       => array( 872, 581 ),
);

/**
 * Convert the inline emphasis tokens into safe markup.
 *
 * The text is escaped first and only the known tokens are turned back into
 * elements, so an editor can never inject HTML through a content field.
 *
 * @param string $text Raw field value.
 * @return string Escaped HTML, safe to echo.
 */
function itt_rich( string $text ): string {
	$html  = esc_html( $text );
	$index = 0;

	$html = (string) preg_replace_callback(
		'/\[hl:(gold|cyan|orange)\](.+?)\[\/hl\]/u',
		static function ( array $m ) use ( &$index ): string {
			return sprintf(
				'<span class="itt-hl itt-hl--%s" data-hl="%d">%s</span>',
				$m[1],
				$index++,
				$m[2]
			);
		},
		$html
	);

	$html = (string) preg_replace(
		array(
			'/\[pop\](.+?)\[\/pop\]/u',
			'/\[blink\](.+?)\[\/blink\]/u',
			'/\[value\](.+?)\[\/value\]/u',
			'/\[gold\](.+?)\[\/gold\]/u',
		),
		array(
			'<span class="itt-pop" data-pop>$1</span>',
			'<span class="itt-blink">$1</span>',
			'<span class="itt-pop itt-blink" data-pop>$1</span>',
			'<span class="itt-gold">$1</span>',
		),
		$html
	);

	return (string) preg_replace_callback(
		'/\[count:(\d+)(,comma)?\]/u',
		static function ( array $m ): string {
			$value = (int) $m[1];

			// The final number is the rendered text: without JS the visitor and
			// the screen reader still see the real figure, and the script only
			// animates towards a value that is already correct.
			return sprintf(
				'<span class="itt-count" data-count="%d"%s>%s</span>',
				$value,
				isset( $m[2] ) && '' !== $m[2] ? ' data-count-comma="1"' : '',
				esc_html( isset( $m[2] ) && '' !== $m[2] ? number_format_i18n( $value ) : (string) $value )
			);
		},
		$html
	);
}

/**
 * Echo the rich-text markup for a field.
 *
 * @param string $text Raw field value.
 */
function itt_the_rich( string $text ): void {
	echo itt_rich( $text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- itt_rich() escapes.
}

/**
 * Split a newline-separated field into a trimmed list.
 *
 * @param string $value Raw field value.
 * @return string[]
 */
function itt_lines( string $value ): array {
	$lines = preg_split( '/\R/u', $value ) ?: array();

	return array_values( array_filter( array_map( 'trim', $lines ), static fn( string $line ): bool => '' !== $line ) );
}

/**
 * Render an image, from the media library when set and from the theme otherwise.
 *
 * @param int                  $attachment_id Attachment ID, 0 to use the bundled file.
 * @param string               $fallback      File name inside assets/img/.
 * @param string               $alt           Alternative text. Empty marks the image decorative.
 * @param array<string, mixed> $args          class, sizes, loading, fetchpriority.
 */
function itt_image( int $attachment_id, string $fallback, string $alt, array $args = array() ): void {
	$args = wp_parse_args(
		$args,
		array(
			'class'         => '',
			'sizes'         => '',
			'loading'       => 'lazy',
			'fetchpriority' => '',
		)
	);

	$attr = array( 'class' => $args['class'] );

	if ( 'eager' === $args['loading'] ) {
		$attr['loading']  = 'eager';
		$attr['decoding'] = 'async';
	} else {
		$attr['loading']  = 'lazy';
		$attr['decoding'] = 'async';
	}

	if ( '' !== $args['fetchpriority'] ) {
		$attr['fetchpriority'] = $args['fetchpriority'];
	}

	if ( '' !== $args['sizes'] ) {
		$attr['sizes'] = $args['sizes'];
	}

	if ( $attachment_id > 0 && wp_attachment_is_image( $attachment_id ) ) {
		$attr['alt'] = $alt;

		echo wp_get_attachment_image( $attachment_id, 'large', false, $attr ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core escapes.
		return;
	}

	if ( '' === $fallback || ! file_exists( ITT_DIR . 'assets/img/' . $fallback ) ) {
		// A row added beyond the bundled set simply renders no image rather than
		// a broken one; the editor picks a picture from the media library.
		return;
	}

	$size = ITT_IMAGE_SIZES[ $fallback ] ?? array( 0, 0 );

	printf(
		'<img src="%s" alt="%s" class="%s" loading="%s" decoding="async"%s%s%s>',
		esc_url( ITT_URI . 'assets/img/' . $fallback ),
		esc_attr( $alt ),
		esc_attr( (string) $args['class'] ),
		esc_attr( (string) $attr['loading'] ),
		$size[0] > 0 ? sprintf( ' width="%d" height="%d"', $size[0], $size[1] ) : '',
		'' !== $args['fetchpriority'] ? sprintf( ' fetchpriority="%s"', esc_attr( (string) $args['fetchpriority'] ) ) : '',
		'' === $alt ? ' aria-hidden="true"' : ''
	);
}

/**
 * Render one of the small inline icons used in the hero fact strip.
 *
 * @param string $name Icon key.
 */
function itt_icon( string $name ): void {
	$paths = array(
		'calendar' => '<rect x="3" y="5" width="18" height="16" rx="3"></rect><path d="M3 10h18M8 3v4M16 3v4"></path><circle cx="12" cy="15" r="1.6" fill="currentColor" stroke="none"></circle>',
		'pin'      => '<path d="M12 21s7-6.1 7-11a7 7 0 1 0-14 0c0 4.9 7 11 7 11z"></path><circle cx="12" cy="10" r="2.6"></circle>',
		'cap'      => '<path d="M3 8.5 12 4l9 4.5-9 4.5-9-4.5z"></path><path d="M7 11v4.2c0 1.6 2.2 2.8 5 2.8s5-1.2 5-2.8V11"></path><path d="M20.5 10v5"></path>',
	);

	if ( ! isset( $paths[ $name ] ) ) {
		return;
	}

	printf(
		'<svg class="itt-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
		$paths[ $name ] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup from the map above.
	);
}

/**
 * Render a chevron used by the slider navigation.
 *
 * @param string $direction 'prev' or 'next'.
 */
function itt_chevron( string $direction ): void {
	// In RTL the left-pointing chevron means "previous".
	$points = 'prev' === $direction ? '15 18 9 12 15 6' : '9 18 15 12 9 6';

	printf(
		'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="%s"></polyline></svg>',
		esc_attr( $points )
	);
}

/**
 * Resolve the playable source for a video slot.
 *
 * A file uploaded to the media library wins over an external link, so the
 * client can publish testimonials without depending on YouTube at all.
 *
 * @param int    $attachment_id Video attachment ID, 0 when none.
 * @param string $url           YouTube or Vimeo watch URL.
 * @return array{type: string, src: string} type is 'file', 'embed' or '' when nothing is set.
 */
function itt_video_source( int $attachment_id, string $url ): array {
	if ( $attachment_id > 0 ) {
		$src  = (string) wp_get_attachment_url( $attachment_id );
		$mime = (string) get_post_mime_type( $attachment_id );

		if ( '' !== $src && str_starts_with( $mime, 'video/' ) ) {
			return array(
				'type' => 'file',
				'src'  => $src,
			);
		}
	}

	$embed = itt_video_embed_url( $url );

	return '' !== $embed
		? array(
			'type' => 'embed',
			'src'  => $embed,
		)
		: array(
			'type' => '',
			'src'  => '',
		);
}

/**
 * Turn a YouTube or Vimeo watch URL into its privacy-friendly embed URL.
 *
 * Videos are never embedded on load: the partials render a poster facade and
 * this URL is only used once the visitor asks to play.
 *
 * @param string $url Watch URL.
 * @return string Embed URL, or an empty string when the host is not supported.
 */
function itt_video_embed_url( string $url ): string {
	if ( '' === $url ) {
		return '';
	}

	$host = (string) wp_parse_url( $url, PHP_URL_HOST );

	if ( str_contains( $host, 'youtube.com' ) || str_contains( $host, 'youtu.be' ) ) {
		$id = str_contains( $host, 'youtu.be' )
			? trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' )
			: '';

		if ( '' === $id ) {
			parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
			$id = (string) ( $query['v'] ?? '' );
		}

		return '' === $id
			? ''
			: 'https://www.youtube-nocookie.com/embed/' . rawurlencode( $id ) . '?autoplay=1&rel=0';
	}

	if ( str_contains( $host, 'vimeo.com' ) ) {
		$id = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );

		return '' === $id ? '' : 'https://player.vimeo.com/video/' . rawurlencode( $id ) . '?autoplay=1';
	}

	return '';
}

/**
 * Load a landing-page section partial with its resolved content.
 *
 * @param string $section Section key, matching both the partial name and the meta key.
 * @param string $folder  Partial folder under template-parts/.
 */
function itt_section( string $section, string $folder = 'landing' ): void {
	$itt = ITT_Meta::get( $section );

	require ITT_DIR . 'template-parts/' . $folder . '/' . str_replace( '_', '-', $section ) . '.php';
}
