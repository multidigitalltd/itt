<?php
/**
 * The closing call to action.
 *
 * @package Mashehu_LeShabbat
 *
 * @var array<string, mixed> $msl Resolved closing content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_campaign = MSL_Meta::get( 'campaign' );
$msl_chrome   = MSL_Meta::get( 'chrome' );
$msl_hero     = MSL_Meta::get( 'hero' );
$msl_stats    = MSL_Stats::all( (int) get_the_ID() );
$msl_closed   = 1 === (int) $msl_campaign['closed'];
?>
<section class="msl-closing" aria-labelledby="msl-closing-title">
	<div class="msl-closing__inner">
		<h2 class="msl-closing__title" id="msl-closing-title"<?php msl_i18n( 'closing', 'title' ); ?>><?php msl_the( $msl, 'title' ); ?></h2>

		<p class="msl-closing__urgency" data-msl-urgency><?php echo esc_html( msl_urgency( $msl, $msl_campaign ) ); ?></p>

		<?php if ( ! $msl_closed ) : ?>
			<button type="button" class="msl-btn msl-btn--hero msl-btn--on-warm" data-msl-open-join
				<?php msl_i18n( 'chrome', 'cta' ); ?>><?php msl_the( $msl_chrome, 'cta' ); ?></button>
		<?php endif; ?>

		<p class="msl-closing__counter">
			<span data-msl-counter><?php echo esc_html( msl_num( $msl_stats['participants'] ) ); ?></span>
			<span<?php msl_i18n( 'hero', 'already' ); ?>><?php msl_the( $msl_hero, 'already' ); ?></span>
		</p>
	</div>
</section>
