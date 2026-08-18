<?php
/**
 * The activity marquee.
 *
 * @package Mashehu_LeShabbat
 *
 * @var array<string, mixed> $msl Resolved marquee content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_rows = msl_marquee_rows( (int) get_the_ID() );

if ( array() === $msl_rows ) {
	return;
}
?>
<section class="msl-marquee" aria-label="<?php esc_attr_e( 'הצטרפויות אחרונות', 'mashehu-leshabbat' ); ?>">
	<?php
	/*
	 * Both the track and each half are max-content, never a percentage: with
	 * percentage widths the two halves overlap and the loop visibly stutters.
	 * The second half is the same list again, and is hidden from assistive
	 * technology so nothing is announced twice.
	 */
	?>
	<div class="msl-marquee__track" data-msl-marquee>
		<?php foreach ( array( false, true ) as $msl_duplicate ) : ?>
			<ul class="msl-marquee__half"<?php echo $msl_duplicate ? ' aria-hidden="true"' : ''; ?>>
				<?php foreach ( $msl_rows as $msl_row ) : ?>
					<li class="msl-marquee__item">
						<span class="msl-marquee__dot" aria-hidden="true"></span>
						<span><?php echo esc_html( $msl_row ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endforeach; ?>
	</div>
</section>
