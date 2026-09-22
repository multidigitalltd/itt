<?php
/**
 * A photograph, turned into candles.
 *
 * Somebody opening a group has a picture of the person it is for. This takes
 * that picture and derives an artwork from it: a grid of heat values, one per
 * cell, that the canvas draws exactly the way it draws the menorah and the
 * star — the same flame sprite, the same flicker, the same filling in
 * proportion to the count. Nothing here draws anything. It produces numbers,
 * and the artwork the visitor sees is the drawing engine reading them.
 *
 * **Two pictures, two decisions.** The cover at the head of the page and the
 * picture the candles are made from are separate fields, because they are
 * separate choices: a family may want the person at the top and an artwork
 * made from something else, or an artwork from a picture they would rather not
 * publish at all. Either, both or neither — a group with none of them is the
 * page it always was.
 *
 * And they are kept differently. The cover is stored, because it is shown. The
 * artwork's source is **not**: it is read, turned into one number per cell, and
 * dropped. What survives of it is a grid nobody can turn back into a face.
 *
 * Three more decisions worth knowing about:
 *
 * **The uploaded bytes are never served.** The file is decoded, resampled and
 * re-encoded into a fresh JPEG that this theme wrote itself, and only that copy
 * is stored. Metadata, colour profiles and anything hidden after the end of the
 * image do not survive a re-encode, which matters when the person uploading is
 * a stranger and the form takes no account to use.
 *
 * **Polarity is decided by the picture, not assumed.** Candles are light, so
 * the bright parts of the photograph become flames — which is right for a face
 * against a dark background and exactly wrong for a dark figure on a white
 * one, where the background would light up and the person would be a hole. So
 * the border of the picture is compared with its middle, and when the border is
 * the brighter of the two the map is inverted. This is one subtraction, and it
 * is the difference between a portrait and a smear.
 *
 * **The grid is small on purpose.** 64x64 is 4,096 numbers, which is about
 * 5KB of base64 and compresses well; it also happens to be more cells than the
 * artwork has candles, so the resolution is never what limits the picture.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Derives a candle artwork from an uploaded image.
 */
final class MSL_Photo {

	/**
	 * Cells per side of the derived grid.
	 */
	public const SIZE = 64;

	/**
	 * The longest side of the stored copy, in pixels.
	 */
	private const STORED = 1400;

	/**
	 * How much of a picture becomes candles.
	 */
	private const LIT_SHARE = 0.20;

	/**
	 * The most an upload may weigh.
	 */
	public const MAX_BYTES = 8 * MB_IN_BYTES;

	/**
	 * What the form accepts.
	 */
	private const TYPES = array( 'image/jpeg', 'image/png', 'image/webp' );

	/**
	 * Is there anything here that can read an image at all?
	 *
	 * GD is present on essentially every host that runs WordPress, because
	 * WordPress itself needs it for thumbnails — but "essentially every" is not
	 * "every", and a group page that fatals because a shared host compiled PHP
	 * without it would be a poor trade for a decoration.
	 *
	 * @return bool
	 */
	public static function available(): bool {
		return function_exists( 'imagecreatefromstring' ) && function_exists( 'imagecopyresampled' );
	}

	/**
	 * The cover: a picture to stand at the head of a group's page.
	 *
	 * @param array<string, mixed> $file  One entry of $_FILES.
	 * @param string               $title What to call the attachment.
	 * @return int|WP_Error Attachment id. An error message is an error key, not prose.
	 */
	public static function cover( array $file, string $title = '' ): int|WP_Error {
		$source = self::open( $file );

		if ( is_wp_error( $source ) ) {
			return $source;
		}

		$clean = self::reencode( $source );

		imagedestroy( $source );

		if ( '' === $clean ) {
			return new WP_Error( 'msl_photo', 'photo_server' );
		}

		$id = self::store( $clean, $title );

		return $id > 0 ? $id : new WP_Error( 'msl_photo', 'photo_server' );
	}

	/**
	 * The artwork: a picture to become candles.
	 *
	 * **Nothing is kept of this one but the numbers.** It is a separate field
	 * from the cover because the two are separate decisions — a family may want
	 * their grandfather at the head of the page and the artwork made from a
	 * different photograph entirely, or an artwork from a picture they would
	 * rather not publish at all. So this end of it stores no file: the picture
	 * is read, turned into one number per cell, and dropped. What survives is a
	 * grid that nobody can turn back into a face.
	 *
	 * @param array<string, mixed> $file One entry of $_FILES.
	 * @return string|WP_Error The grid, base64. An error message is an error key.
	 */
	public static function artwork( array $file ): string|WP_Error {
		$source = self::open( $file );

		if ( is_wp_error( $source ) ) {
			return $source;
		}

		$grid = self::grid( $source );

		imagedestroy( $source );

		// A picture with nothing in it — one flat colour, or a frame of black.
		return '' !== $grid ? $grid : new WP_Error( 'msl_photo', 'photo_flat' );
	}

	/**
	 * Check one upload and decode it, or say why not.
	 *
	 * @param array<string, mixed> $file One entry of $_FILES.
	 * @return GdImage|WP_Error
	 */
	private static function open( array $file ): GdImage|WP_Error {
		/*
		 * Weight first, because it is the refusal a real person actually meets
		 * — a photograph straight off a modern phone — and it deserves to be
		 * told apart from "something went wrong". It reads a number PHP wrote
		 * and touches no file, so nothing is given up by asking it first.
		 */
		if ( (int) ( $file['size'] ?? 0 ) > self::MAX_BYTES ) {
			return new WP_Error( 'msl_photo', 'photo_big' );
		}

		// And then the gate: a path that did not arrive as an upload in this
		// request is not read, whatever it points at.
		if ( ! isset( $file['tmp_name'] ) || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
			return new WP_Error( 'msl_photo', 'photo' );
		}

		$path = (string) $file['tmp_name'];
		$info = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- a file that is not an image is an expected answer here, not an error to report.

		/*
		 * getimagesize() decides by content, not by the name or by what the
		 * browser claimed the type was. Both of those are written by whoever is
		 * uploading.
		 */
		if ( false === $info || ! in_array( (string) ( $info['mime'] ?? '' ), self::TYPES, true ) ) {
			return new WP_Error( 'msl_photo', 'photo_type' );
		}

		if ( ! self::available() ) {
			return new WP_Error( 'msl_photo', 'photo_server' );
		}

		$source = @imagecreatefromstring( (string) file_get_contents( $path ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions -- a local temp file, and a decode failure is a return value here.

		return false !== $source ? $source : new WP_Error( 'msl_photo', 'photo_type' );
	}

	/**
	 * The heat grid, as base64 of one byte per cell.
	 *
	 * @param GdImage $source Decoded image.
	 * @return string
	 */
	private static function grid( GdImage $source ): string {
		$size  = self::SIZE;
		$small = imagecreatetruecolor( $size, $size );

		/*
		 * The middle square of the picture, not the whole of it squashed: a
		 * portrait held upright becomes a face and not a face pulled wide.
		 */
		$w    = imagesx( $source );
		$h    = imagesy( $source );
		$side = min( $w, $h );

		imagecopyresampled(
			$small,
			$source,
			0,
			0,
			(int) ( ( $w - $side ) / 2 ),
			(int) ( ( $h - $side ) / 2 ),
			$size,
			$size,
			$side,
			$side
		);

		$lum    = array();
		$lowest = 255.0;
		$high   = 0.0;

		for ( $y = 0; $y < $size; $y++ ) {
			for ( $x = 0; $x < $size; $x++ ) {
				$rgb = imagecolorat( $small, $x, $y );
				$v   = 0.2126 * ( ( $rgb >> 16 ) & 0xFF ) + 0.7152 * ( ( $rgb >> 8 ) & 0xFF ) + 0.0722 * ( $rgb & 0xFF );

				$lum[ $y * $size + $x ] = $v;
				$lowest                 = min( $lowest, $v );
				$high                   = max( $high, $v );
			}
		}

		imagedestroy( $small );

		if ( $high - $lowest < 1.0 ) {
			// One flat colour. There is no picture in it to light.
			return '';
		}

		if ( self::border_is_brighter( $lum, $size ) ) {
			foreach ( $lum as $i => $v ) {
				$lum[ $i ] = 255.0 - $v;
			}

			$was    = $lowest;
			$lowest = 255.0 - $high;
			$high   = 255.0 - $was;
		}

		/*
		 * Where the dark begins is decided per picture, not once for all of
		 * them. A fixed threshold lights four fifths of an overexposed photo
		 * and a tenth of a dim one, and the artwork's density — how many
		 * candles it has at all — would then depend on the exposure of a
		 * snapshot. So the floor is a percentile: the brightest LIT_SHARE of
		 * the picture becomes candles, whatever its exposure, which is also
		 * what keeps a photograph in the same league as the drawn artworks
		 * (they run 800 to 1,100 cells) rather than three times heavier to
		 * draw on a phone.
		 */
		$sorted = $lum;
		sort( $sorted );

		$floor = (float) $sorted[ min( count( $sorted ) - 1, (int) floor( count( $sorted ) * ( 1 - self::LIT_SHARE ) ) ) ];

		/*
		 * Unless the percentile lands on the brightest value there is, which
		 * happens whenever a large part of the picture is one flat bright
		 * area — a flash, a window, a white wall behind somebody. Then
		 * everything is at or below the floor and the artwork comes out empty.
		 * Halfway up the range always leaves something to light.
		 */
		if ( $high - $floor < 2.0 ) {
			$floor = ( $lowest + $high ) / 2;
		}

		$span = max( 1.0, $high - $floor );
		$bytes = '';

		foreach ( $lum as $v ) {
			// Below the floor there is no candle at all. The shape of a picture
			// lives in what stays dark; an artwork lit a little everywhere is a
			// rectangle of fog.
			$heat = $v <= $floor ? 0.0 : ( ( $v - $floor ) / $span ) ** 0.78;

			$bytes .= chr( (int) round( max( 0.0, min( 1.0, $heat ) ) * 255 ) );
		}

		return base64_encode( $bytes ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- a binary grid on its way into a text column, not obfuscation.
	}

	/**
	 * Is the frame of the picture lighter than its middle?
	 *
	 * @param array<int, float> $lum  Luminance per cell.
	 * @param int               $size Cells per side.
	 * @return bool
	 */
	private static function border_is_brighter( array $lum, int $size ): bool {
		$edge      = 0.0;
		$edge_n    = 0;
		$middle    = 0.0;
		$middle_n  = 0;
		$band      = max( 2, (int) round( $size / 8 ) );
		$inner_min = (int) round( $size * 0.3 );
		$inner_max = (int) round( $size * 0.7 );

		for ( $y = 0; $y < $size; $y++ ) {
			for ( $x = 0; $x < $size; $x++ ) {
				$v = $lum[ $y * $size + $x ];

				if ( $x < $band || $y < $band || $x >= $size - $band || $y >= $size - $band ) {
					$edge += $v;
					++$edge_n;
					continue;
				}

				if ( $x >= $inner_min && $x < $inner_max && $y >= $inner_min && $y < $inner_max ) {
					$middle += $v;
					++$middle_n;
				}
			}
		}

		if ( 0 === $edge_n || 0 === $middle_n ) {
			return false;
		}

		return ( $edge / $edge_n ) > ( $middle / $middle_n ) + 8.0;
	}

	/**
	 * A clean JPEG of our own making, scaled down.
	 *
	 * @param GdImage $source Decoded image.
	 * @return string JPEG bytes, or '' if it could not be written.
	 */
	private static function reencode( GdImage $source ): string {
		$w     = imagesx( $source );
		$h     = imagesy( $source );
		$scale = min( 1.0, self::STORED / max( 1, max( $w, $h ) ) );
		$tw    = max( 1, (int) round( $w * $scale ) );
		$th    = max( 1, (int) round( $h * $scale ) );

		$out = imagecreatetruecolor( $tw, $th );

		// A transparent PNG flattens onto white rather than onto black, which
		// is what every other tool does and therefore what people expect.
		$white = imagecolorallocate( $out, 255, 255, 255 );
		imagefilledrectangle( $out, 0, 0, $tw, $th, $white );
		imagecopyresampled( $out, $source, 0, 0, 0, 0, $tw, $th, $w, $h );

		ob_start();
		imagejpeg( $out, null, 82 );
		$bytes = (string) ob_get_clean();

		imagedestroy( $out );

		return $bytes;
	}

	/**
	 * Put the clean copy in the media library.
	 *
	 * @param string $bytes JPEG bytes.
	 * @param string $title What to call it.
	 * @return int Attachment id, or 0.
	 */
	private static function store( string $bytes, string $title ): int {
		$name   = 'group-' . gmdate( 'Ymd' ) . '-' . wp_generate_password( 8, false, false ) . '.jpg';
		$upload = wp_upload_bits( $name, null, $bytes );

		if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
			return 0;
		}

		$id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => '' !== $title ? $title : __( 'תמונת קבוצה', 'mashehu-leshabbat' ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			),
			(string) $upload['file']
		);

		if ( is_wp_error( $id ) || 0 === (int) $id ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';

		wp_update_attachment_metadata( (int) $id, wp_generate_attachment_metadata( (int) $id, (string) $upload['file'] ) );

		return (int) $id;
	}
}
