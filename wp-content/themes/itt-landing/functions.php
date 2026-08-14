<?php
/**
 * ITT Landing theme bootstrap.
 *
 * @package ITT_Landing
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Theme version. Used to bust asset caches on deploy.
 */
const ITT_VERSION = '1.0.0';

/**
 * Absolute path to the theme directory, with trailing slash.
 */
define( 'ITT_DIR', trailingslashit( get_template_directory() ) );

/**
 * Public URL of the theme directory, with trailing slash.
 */
define( 'ITT_URI', trailingslashit( get_template_directory_uri() ) );

require_once ITT_DIR . 'inc/class-itt-content.php';
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
