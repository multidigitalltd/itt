<?php
/**
 * Template Name: ITT · עמוד תודה
 * Template Post Type: page
 *
 * Shown after the lead form is submitted.
 *
 * @package ITT_Landing
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

get_header();

$itt_chrome = ITT_Meta::get( 'chrome', ITT_Importer::page_id( 'landing' ) );

require ITT_DIR . 'template-parts/thank-you/chrome-header.php';
?>
<main id="itt-main" class="itt-main">
	<?php
	foreach ( array( 'ty_hero', 'ty_steps', 'ty_links', 'ty_quote' ) as $itt_key ) {
		itt_section( $itt_key, 'thank-you' );
	}
	?>
</main>
<?php
require ITT_DIR . 'template-parts/landing/chrome-footer.php';

get_footer();
