<?php
/**
 * ITT Landing theme bootstrap.
 *
 * @package ITT_Landing
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Absolute path to the theme directory, with trailing slash.
 */
define( 'ITT_DIR', trailingslashit( get_template_directory() ) );

/**
 * Theme version, read from the Version header in style.css.
 *
 * Single source of truth: bumping the header is the only place a release
 * number is written. Individual asset URLs do not depend on it — see
 * ITT_Theme::asset_version() — so a forgotten bump can never serve stale CSS.
 */
define( 'ITT_VERSION', ( static function (): string {
	$version = wp_get_theme( get_template() )->get( 'Version' );

	return is_string( $version ) && '' !== $version ? $version : '1.0.0';
} )() );

/**
 * Public URL of the theme directory, with trailing slash.
 */
define( 'ITT_URI', trailingslashit( get_template_directory_uri() ) );

require_once ITT_DIR . 'inc/class-itt-content.php';
require_once ITT_DIR . 'inc/class-itt-content-men.php';
require_once ITT_DIR . 'inc/class-itt-fields.php';
require_once ITT_DIR . 'inc/class-itt-meta.php';
require_once ITT_DIR . 'inc/class-itt-metabox.php';
require_once ITT_DIR . 'inc/class-itt-importer.php';
require_once ITT_DIR . 'inc/class-itt-leads.php';
require_once ITT_DIR . 'inc/itt-template-tags.php';
require_once ITT_DIR . 'inc/class-itt-theme.php';

ITT_Theme::init();
ITT_Meta::init();
ITT_Metabox::init();
ITT_Importer::init();
ITT_Leads::init();
