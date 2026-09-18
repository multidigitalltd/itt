<?php
/**
 * A group address that leads nowhere.
 *
 * A link that was mistyped, or a group that was never approved. Either way the
 * honest answer is a real 404 and a way back — not a page that looks like the
 * group exists and is simply empty.
 *
 * @package Mashehu_LeShabbat
 *
 * @var array<string, mixed> $msl_groups Resolved groups content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

status_header( 404 );
nocache_headers();
?>
<section class="msl-gmissing" data-msl-rise>
	<h1 class="msl-heading"<?php msl_i18n( 'groups', 'archive_title' ); ?>><?php msl_the( $msl_groups, 'archive_title' ); ?></h1>
	<p class="msl-subheading"<?php msl_i18n( 'groups', 'state_rejected' ); ?>><?php msl_the( $msl_groups, 'state_rejected' ); ?></p>

	<p class="msl-gmissing__actions">
		<a class="msl-btn msl-btn--amber" href="<?php echo esc_url( MSL_Groups::page_url() ); ?>"
			<?php msl_i18n( 'groups', 'single_back' ); ?>><?php msl_the( $msl_groups, 'single_back' ); ?></a>
	</p>
</section>
