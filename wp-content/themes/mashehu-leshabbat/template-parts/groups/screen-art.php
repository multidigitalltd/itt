<?php
/**
 * A group's own artwork, full screen.
 *
 * The group page already draws this artwork small, behind the numbers. That is
 * enough to show that something is filling up and not enough to look at: the
 * whole promise of a group is that the family can see the thing they are making
 * together, and a 340px canvas under a progress bar is not that thing.
 *
 * It is the campaign's artwork screen, with the campaign's numbers swapped for
 * this group's — the same panel markup, the same canvas key, and therefore the
 * same zoom, the same pan and the same pinch on a phone, none of which is
 * written twice. The engine was already initialised with the group's shape,
 * accent and target on this page; this only gives it somewhere big to draw.
 *
 * @package Mashehu_LeShabbat
 *
 * @var array<string, mixed> $msl_groups Resolved groups content.
 * @var array<string, mixed> $msl_group  The group being shown.
 * @var int                  $msl_pct    How far along it is.
 * @var bool                 $msl_open   Whether it is taking joins.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_screens = MSL_Meta::get( 'screens' );
?>
<div class="msl-screen msl-screen--art" data-msl-screen-panel="art" role="dialog" aria-modal="true"
	aria-labelledby="msl-gart-title" hidden>

	<canvas class="msl-screen__canvas" data-msl-canvas="artview" data-msl-art-surface aria-hidden="true"></canvas>

	<div class="msl-screen__top">
		<button type="button" class="msl-btn msl-btn--light msl-back" data-msl-goto="home"
			<?php msl_i18n( 'screens', 'back' ); ?>><?php msl_the( $msl_screens, 'back' ); ?></button>

		<div class="msl-screen__heading">
			<h2 class="msl-screen__title" id="msl-gart-title"><?php echo esc_html( (string) $msl_group['title'] ); ?></h2>
			<p class="msl-screen__sub">
				<span class="msl-screen__figure"><span data-msl-group-pct><?php echo esc_html( msl_num( $msl_pct ) ); ?></span>%</span>
				<span<?php msl_i18n( 'groups', 'single_pct' ); ?>><?php msl_the( $msl_groups, 'single_pct' ); ?></span>
				<span aria-hidden="true">·</span>
				<span data-msl-group-count><?php echo esc_html( msl_num( (int) $msl_group['count'] ) ); ?></span>
				<span<?php msl_i18n( 'groups', 'single_lights' ); ?>><?php msl_the( $msl_groups, 'single_lights' ); ?></span>
			</p>
		</div>

		<div class="msl-screen__spacer" aria-hidden="true"></div>
	</div>

	<div class="msl-zoom">
		<button type="button" class="msl-zoom__btn" data-msl-zoom="in"
			aria-label="<?php echo esc_attr( msl_t( $msl_screens, 'zoom_in' ) ); ?>"><span aria-hidden="true">+</span></button>
		<button type="button" class="msl-zoom__btn" data-msl-zoom="out"
			aria-label="<?php echo esc_attr( msl_t( $msl_screens, 'zoom_out' ) ); ?>"><span aria-hidden="true">−</span></button>
		<p class="msl-zoom__level" data-msl-zoom-level aria-live="polite">×1</p>
	</div>

	<div class="msl-screen__bottom">
		<?php
		/*
		 * Tapping a candle here answers with what this group was opened for.
		 * It does not name the person who lit it, and it does not ask the
		 * server who that was: in a group's artwork the answer is the same for
		 * every candle, and it is already on the page. Naming the person would
		 * mean a second, group-scoped way of resolving a cell to a join — the
		 * campaign's maps a cell to a position in the campaign's own artwork,
		 * which on this page points at the wrong people entirely.
		 */
		?>
		<div class="msl-pick" data-msl-art-pick hidden>
			<span class="msl-pick__avatar" aria-hidden="true"></span>
			<span class="msl-pick__body">
				<span class="msl-pick__name" data-msl-pick-name></span>
				<span class="msl-pick__sub" data-msl-pick-sub></span>
				<span class="msl-pick__ded" data-msl-pick-ded hidden></span>
			</span>
		</div>

		<p class="msl-hint" data-msl-art-hint aria-live="polite"<?php msl_i18n( 'screens', 'art_hint_zoom' ); ?>><?php msl_the( $msl_screens, 'art_hint_zoom' ); ?></p>

		<?php if ( $msl_open ) : ?>
			<button type="button" class="msl-btn msl-btn--amber msl-btn--wide" data-msl-open-join
				<?php msl_i18n( 'groups', 'single_cta' ); ?>><?php msl_the( $msl_groups, 'single_cta' ); ?></button>
		<?php endif; ?>
	</div>
</div>
