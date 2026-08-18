<?php
/**
 * Section 04 — the testimonial video gallery.
 *
 * One gallery, not a featured video with a separate strip underneath. The
 * stage shows whichever testimonial is selected and the thumbnails below
 * switch it; the arrows at the top step through the same list, so the large
 * video changes with them rather than only the row beneath it.
 *
 * The lead video is simply the first item. It keeps its own fields in the
 * editor (badge / heading / text / poster / video) so existing content is
 * untouched — the combining happens here, at render time.
 *
 * Thumbnails are a tablist and each stage entry is its tabpanel: the pattern
 * screen readers and keyboards already know, which also gives arrow-key
 * navigation between testimonials for free.
 *
 * Videos are rendered as a poster facade — nothing is loaded from YouTube or
 * Vimeo until the visitor presses play.
 *
 * @package ITT_Landing
 *
 * @var array<string, mixed> $itt Resolved section content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$itt_items = array();

// The lead video, when one has been set up at all.
if ( 0 !== (int) $itt['poster'] || 0 !== (int) $itt['video_file'] || '' !== trim( (string) $itt['video_url'] ) || '' !== trim( (string) $itt['heading'] ) ) {
	$itt_items[] = array(
		'image'      => (int) $itt['poster'],
		'image_alt'  => (string) $itt['poster_alt'],
		'name'       => (string) $itt['heading'],
		'quote'      => (string) $itt['text'],
		'video_file' => (int) $itt['video_file'],
		'video_url'  => (string) $itt['video_url'],
		'fallback'   => 'lead-video-poster.webp',
	);
}

foreach ( (array) $itt['slides'] as $itt_i => $itt_slide ) {
	$itt_items[] = array(
		'image'      => (int) $itt_slide['image'],
		'image_alt'  => (string) $itt_slide['image_alt'],
		'name'       => (string) $itt_slide['name'],
		'quote'      => (string) $itt_slide['quote'],
		'video_file' => (int) $itt_slide['video_file'],
		'video_url'  => (string) $itt_slide['video_url'],
		'fallback'   => 'testimonial-' . ( ( (int) $itt_i % 3 ) + 1 ) . '.webp',
	);
}

if ( array() === $itt_items ) {
	return;
}
?>
<section class="itt-section itt-video" aria-labelledby="itt-video-title">
	<div class="itt-shell itt-shell--narrow itt-vgallery itt-reveal" data-itt-vgallery>

		<div class="itt-vgallery__head">
			<div class="itt-vgallery__intro">
				<p class="itt-tag itt-tag--purple"><?php echo esc_html( (string) $itt['badge'] ); ?></p>
				<h2 class="itt-vgallery__title" id="itt-video-title"><?php echo esc_html( (string) $itt['slider_heading'] ); ?></h2>
			</div>

			<?php if ( count( $itt_items ) > 1 ) : ?>
				<div class="itt-slider__nav">
					<button type="button" class="itt-slider__btn" data-itt-vgallery-step="-1" aria-label="<?php esc_attr_e( 'ההמלצה הקודמת', 'itt-landing' ); ?>" aria-controls="itt-vgallery-tabs">
						<?php itt_chevron( 'prev' ); ?>
					</button>
					<button type="button" class="itt-slider__btn" data-itt-vgallery-step="1" aria-label="<?php esc_attr_e( 'ההמלצה הבאה', 'itt-landing' ); ?>" aria-controls="itt-vgallery-tabs">
						<?php itt_chevron( 'next' ); ?>
					</button>
				</div>
			<?php endif; ?>
		</div>

		<div class="itt-vgallery__stage">
			<?php foreach ( $itt_items as $itt_i => $itt_item ) : ?>
				<?php $itt_src = itt_video_source( $itt_item['video_file'], $itt_item['video_url'] ); ?>
				<div
					class="itt-vstage"
					id="itt-vstage-<?php echo (int) $itt_i; ?>"
					role="tabpanel"
					aria-labelledby="itt-vtab-<?php echo (int) $itt_i; ?>"
					<?php echo 0 === (int) $itt_i ? '' : 'hidden'; ?>
				>
					<div class="itt-vstage__media">
						<?php if ( '' !== $itt_src['type'] ) : ?>
							<button type="button" class="itt-facade" data-itt-video="<?php echo esc_url( $itt_src['src'] ); ?>" data-itt-video-type="<?php echo esc_attr( $itt_src['type'] ); ?>">
								<?php
								itt_image(
									$itt_item['image'],
									$itt_item['fallback'],
									'',
									array(
										'class' => 'itt-facade__poster',
										'sizes' => '(max-width: 900px) 100vw, 60vw',
									)
								);
								?>
								<span class="itt-facade__play" aria-hidden="true">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" focusable="false"><path d="M8 5.5v13l11-6.5z"></path></svg>
								</span>
								<span class="itt-facade__label">
									<?php
									printf(
										/* translators: %s: name of the person in the video. */
										esc_html__( 'הפעלת הסרטון: %s', 'itt-landing' ),
										esc_html( (string) $itt_item['name'] )
									);
									?>
								</span>
							</button>
						<?php else : ?>
							<?php
							itt_image(
								$itt_item['image'],
								$itt_item['fallback'],
								(string) $itt_item['image_alt'],
								array(
									'class' => 'itt-vstage__poster',
									'sizes' => '(max-width: 900px) 100vw, 60vw',
								)
							);
							?>
						<?php endif; ?>
					</div>

					<div class="itt-vstage__copy">
						<p class="itt-vstage__name"><?php echo esc_html( (string) $itt_item['name'] ); ?></p>
						<p class="itt-vstage__quote"><?php echo esc_html( (string) $itt_item['quote'] ); ?></p>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( count( $itt_items ) > 1 ) : ?>
			<div class="itt-vgallery__thumbs" id="itt-vgallery-tabs" role="tablist" aria-label="<?php esc_attr_e( 'בחירת סרטון המלצה', 'itt-landing' ); ?>">
				<?php foreach ( $itt_items as $itt_i => $itt_item ) : ?>
					<button
						type="button"
						class="itt-vthumb<?php echo 0 === (int) $itt_i ? ' is-active' : ''; ?>"
						id="itt-vtab-<?php echo (int) $itt_i; ?>"
						role="tab"
						aria-selected="<?php echo 0 === (int) $itt_i ? 'true' : 'false'; ?>"
						aria-controls="itt-vstage-<?php echo (int) $itt_i; ?>"
						tabindex="<?php echo 0 === (int) $itt_i ? '0' : '-1'; ?>"
					>
						<span class="itt-vthumb__media">
							<?php
							itt_image(
								$itt_item['image'],
								$itt_item['fallback'],
								'',
								array( 'class' => 'itt-vthumb__img' )
							);
							?>
							<span class="itt-vthumb__play" aria-hidden="true">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" focusable="false"><path d="M8 5.5v13l11-6.5z"></path></svg>
							</span>
						</span>
						<span class="itt-vthumb__name"><?php echo esc_html( (string) $itt_item['name'] ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</section>
