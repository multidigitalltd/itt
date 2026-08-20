<?php
/**
 * The two navigation cards under the stats.
 *
 * @package Mashehu_LeShabbat
 *
 * @var array<string, mixed> $msl Resolved navcards content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_stats = MSL_Stats::all( (int) get_the_ID() );
$msl_stage = MSL_Meta::get( 'stage' );
?>
<nav class="msl-navcards" aria-label="<?php esc_attr_e( 'מעבר ליצירה ולקיר הנרות', 'mashehu-leshabbat' ); ?>">
	<button type="button" class="msl-navcard" data-msl-goto="art">
		<span class="msl-navcard__thumb">
			<canvas data-msl-canvas="artmini" aria-hidden="true"></canvas>
		</span>
		<span class="msl-navcard__body">
			<span class="msl-navcard__title"<?php msl_i18n( 'navcards', 'art_card' ); ?>><?php msl_the( $msl, 'art_card' ); ?></span>
			<span class="msl-navcard__sub"<?php msl_i18n( 'navcards', 'art_card_sub' ); ?>><?php msl_the( $msl, 'art_card_sub' ); ?></span>
		</span>
		<span class="msl-navcard__chevron" aria-hidden="true">‹</span>
	</button>

	<button type="button" class="msl-navcard" data-msl-goto="wall">
		<span class="msl-navcard__thumb">
			<canvas data-msl-canvas="wallmini" aria-hidden="true"></canvas>
		</span>
		<span class="msl-navcard__body">
			<span class="msl-navcard__title"<?php msl_i18n( 'stage', 'wall' ); ?>><?php msl_the( $msl_stage, 'wall' ); ?></span>
			<span class="msl-navcard__sub">
				<span<?php msl_i18n( 'navcards', 'wall_line1' ); ?>><?php msl_the( $msl, 'wall_line1' ); ?></span>
				<span class="msl-navcard__figure" data-msl-counter><?php echo esc_html( msl_num( $msl_stats['participants'] ) ); ?></span>
				<span<?php msl_i18n( 'navcards', 'wall_line2' ); ?>><?php msl_the( $msl, 'wall_line2' ); ?></span>
			</span>
		</span>
		<span class="msl-navcard__chevron" aria-hidden="true">‹</span>
	</button>
</nav>
