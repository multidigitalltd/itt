<?php
/**
 * "This Shabbat" — four stat blocks and the progress card.
 *
 * @package Mashehu_LeShabbat
 *
 * @var array<string, mixed> $msl Resolved stats content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_stats = MSL_Stats::all( (int) get_the_ID() );

$msl_blocks = array(
	array(
		'variant' => 'ink',
		'value'   => msl_num( $msl_stats['participants'] ),
		'key'     => 'stat_people',
		'live'    => 'counter',
	),
	array(
		'variant' => 'gold',
		'value'   => msl_num( $msl_stats['countries'] ),
		'key'     => 'stat_countries',
		'live'    => 'countries',
	),
	array(
		'variant' => 'plain',
		'value'   => msl_num( $msl_stats['cities'] ),
		'key'     => 'stat_cities',
		'live'    => 'cities',
	),
	array(
		'variant' => 'orange',
		'value'   => msl_num( $msl_stats['dedications'] ),
		'key'     => 'stat_dedications',
		'live'    => 'dedications',
	),
);
?>
<section class="msl-stats" aria-labelledby="msl-stats-title">
	<h2 class="msl-heading" id="msl-stats-title"<?php msl_i18n( 'stats', 'title' ); ?>><?php msl_the( $msl, 'title' ); ?></h2>
	<p class="msl-subheading"<?php msl_i18n( 'stats', 'sub' ); ?>><?php msl_the( $msl, 'sub' ); ?></p>

	<?php
	/*
	 * The counters change while the visitor is on the page, so the whole group
	 * is a polite live region rather than four separate ones — four independent
	 * announcements a second apart would be unusable.
	 */
	?>
	<ul class="msl-stats__grid" aria-live="polite" aria-atomic="false">
		<?php foreach ( $msl_blocks as $msl_block ) : ?>
			<li class="msl-stat msl-stat--<?php echo esc_attr( $msl_block['variant'] ); ?>">
				<span class="msl-stat__value" data-msl-<?php echo esc_attr( $msl_block['live'] ); ?>><?php echo esc_html( $msl_block['value'] ); ?></span>
				<span class="msl-stat__label"<?php msl_i18n( 'stats', $msl_block['key'] ); ?>><?php msl_the( $msl, $msl_block['key'] ); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>

	<div class="msl-progress">
		<div class="msl-progress__head">
			<p class="msl-progress__label" id="msl-progress-label"<?php msl_i18n( 'stats', 'completed_label' ); ?>><?php msl_the( $msl, 'completed_label' ); ?></p>
			<p class="msl-progress__value"><span data-msl-pct><?php echo esc_html( (string) $msl_stats['pct'] ); ?></span>%</p>
		</div>

		<div class="msl-progress__track"
			role="progressbar"
			aria-labelledby="msl-progress-label"
			aria-valuemin="0"
			aria-valuemax="100"
			aria-valuenow="<?php echo esc_attr( (string) $msl_stats['pct'] ); ?>"
			data-msl-progress>
			<span class="msl-progress__fill" style="width:<?php echo esc_attr( (string) $msl_stats['pct'] ); ?>%"></span>
		</div>

		<p class="msl-progress__note" data-msl-last10
			data-msl-template="<?php echo esc_attr( msl_t( $msl, 'last10' ) ); ?>"
			<?php msl_i18n( 'stats', 'last10' ); ?>>
			<?php printf( esc_html( msl_t( $msl, 'last10' ) ), esc_html( msl_num( $msl_stats['last10'] ) ) ); ?>
		</p>
	</div>
</section>
