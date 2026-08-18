<?php
/**
 * "משהו לשבת" theme bootstrap.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Absolute path to the theme directory, with trailing slash.
 */
define( 'MSL_DIR', trailingslashit( get_template_directory() ) );

/**
 * Theme version, read from the Version header in style.css.
 *
 * Single source of truth: bumping the header is the only place a release number
 * is written. Individual asset URLs do not depend on it — see
 * MSL_Theme::asset_version() — so a forgotten bump can never serve stale CSS.
 */
define( 'MSL_VERSION', ( static function (): string {
	$version = wp_get_theme( get_template() )->get( 'Version' );

	return is_string( $version ) && '' !== $version ? $version : '1.0.0';
} )() );

/**
 * Public URL of the theme directory, with trailing slash.
 */
define( 'MSL_URI', trailingslashit( get_template_directory_uri() ) );

require_once MSL_DIR . 'inc/class-msl-content.php';
require_once MSL_DIR . 'inc/class-msl-fields.php';
require_once MSL_DIR . 'inc/class-msl-meta.php';
require_once MSL_DIR . 'inc/class-msl-metabox.php';
require_once MSL_DIR . 'inc/class-msl-importer.php';
require_once MSL_DIR . 'inc/class-msl-i18n.php';
require_once MSL_DIR . 'inc/class-msl-db.php';
require_once MSL_DIR . 'inc/class-msl-joins.php';
require_once MSL_DIR . 'inc/class-msl-stats.php';
require_once MSL_DIR . 'inc/class-msl-rest.php';
require_once MSL_DIR . 'inc/class-msl-admin.php';
require_once MSL_DIR . 'inc/msl-template-tags.php';
require_once MSL_DIR . 'inc/class-msl-theme.php';

MSL_Theme::init();
MSL_Meta::init();
MSL_Metabox::init();
MSL_Importer::init();
MSL_DB::init();
MSL_Joins::init();
MSL_REST::init();
MSL_Admin::init();
