<?php
/**
 * Template Name: ITT · דף נחיתה
 * Template Post Type: page
 *
 * The one-page ITT Leader landing page. Every section reads its copy from this
 * page's own meta, so the content is edited on the page itself.
 *
 * @package ITT_Landing
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

get_header();

$itt_chrome = ITT_Meta::get( 'chrome' );

require ITT_DIR . 'template-parts/landing/chrome-header.php';
?>
<main id="itt-main" class="itt-main">
	<?php
	foreach ( array( 'hero', 'jump', 'pain', 'video', 'method', 'skills', 'outcomes', 'voices', 'gallery', 'syllabus', 'envelope', 'doctor', 'fit', 'bonuses', 'process', 'faq', 'form' ) as $itt_key ) {
		itt_section( $itt_key );
	}
	?>
</main>
<?php
require ITT_DIR . 'template-parts/landing/chrome-footer.php';

get_footer();
