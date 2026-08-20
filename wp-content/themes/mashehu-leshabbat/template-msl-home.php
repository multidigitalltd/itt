<?php
/**
 * Template Name: משהו לשבת · עמוד הקמפיין
 * Template Post Type: page
 *
 * The whole application, in one document. Every screen is rendered into the DOM
 * at once and switched with a `data-msl-screen` attribute on <body> — no page
 * reloads and no view fetching, because the artwork canvas has to stay mounted
 * while the camera pulls back at the end of the join.
 *
 * Every section reads its copy from this page's own meta, so the content is
 * edited on the page itself.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

get_header();

msl_section( 'chrome' );
?>
<main id="msl-main" class="msl-main">
	<div class="msl-shell">
		<?php
		foreach ( array( 'hero', 'stage', 'marquee', 'stats', 'navcards', 'map', 'referral', 'closing' ) as $msl_key ) {
			msl_section( $msl_key );
		}
		?>
	</div>
</main>
<?php
require MSL_DIR . 'template-parts/home/chrome-footer.php';

msl_section( 'join' );

foreach ( array( 'screen-art', 'screen-wall', 'screen-wow', 'screen-result' ) as $msl_screen ) {
	require MSL_DIR . 'template-parts/home/' . $msl_screen . '.php';
}

require MSL_DIR . 'template-parts/home/a11y-widget.php';

get_footer();
