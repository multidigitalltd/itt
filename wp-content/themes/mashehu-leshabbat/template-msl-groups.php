<?php
/**
 * Template Name: אור לשבת · קבוצות
 *
 * Group campaigns: the archive, the form that opens one, and a single group's
 * own page.
 *
 * One template serving three views, because they are one place. A group's
 * address is a path under this page rather than a post of its own — a group is
 * a row in a table, not content somebody edits in WordPress, and giving each
 * one a post would put a quarter of a million strangers' text into wp_posts.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_groups = MSL_Meta::get( 'groups' );
$msl_code   = MSL_Groups::requested_code();
$msl_token  = MSL_Groups::requested_token();
$msl_group  = '' !== $msl_code ? MSL_Groups::by_code( $msl_code ) : null;
$msl_single = null !== $msl_group && MSL_Groups::may_view( $msl_group, $msl_token );

get_header();

msl_section( 'chrome' );
?>
<main id="msl-main" class="msl-main msl-main--page">
	<div class="msl-shell">
		<?php
		if ( $msl_single ) {
			require MSL_DIR . 'template-parts/groups/single.php';
		} elseif ( '' !== $msl_code ) {
			require MSL_DIR . 'template-parts/groups/missing.php';
		} else {
			require MSL_DIR . 'template-parts/groups/intro.php';
			require MSL_DIR . 'template-parts/groups/archive.php';
			require MSL_DIR . 'template-parts/groups/create.php';
		}
		?>
	</div>
</main>
<?php
require MSL_DIR . 'template-parts/home/chrome-footer.php';

/*
 * The join window, but only where there is something to join. It is the same
 * partial the campaign page uses, reading the same copy from the campaign page,
 * so the three steps a person walks through are identical in both places.
 */
if ( $msl_single && MSL_Groups::accepts_joins( $msl_group ) ) {
	msl_section( 'join' );

	require MSL_DIR . 'template-parts/home/screen-result.php';
}

require MSL_DIR . 'template-parts/home/a11y-widget.php';

get_footer();
