<?php
/**
 * The picture a group is opened with.
 *
 * One upload, one job: the cover that stands at the head of the group's page.
 * A second field used to stand beside it, from which the candle artwork was
 * derived; the campaign asked for that to go, and the drawing code went with
 * it rather than staying behind as scenery.
 *
 * **The uploaded bytes are never served.** The file is decoded, resampled and
 * re-encoded into a fresh JPEG that this theme wrote itself, and only that copy
 * is stored. Metadata, colour profiles and anything hidden after the end of the
 * image do not survive a re-encode, which matters when the person uploading is
 * a stranger and the form takes no account to use.
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
	 * The longest side of the stored copy, in pixels.
	 */
	private const STORED = 1400;

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
