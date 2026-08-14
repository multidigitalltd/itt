<?php
/**
 * Section 04 — lead testimonial video and the testimonial slider.
 *
 * Videos are rendered as a poster facade: nothing is loaded from YouTube or
 * Vimeo until the visitor presses play.
 *
 * @package ITT_Landing
 *
 * @var array<string, mixed> $itt Resolved section content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$itt_source = itt_video_source( (int) $itt['video_file'], (string) $itt['video_url'] );
?>
<section class="itt-section itt-video" aria-labelledby="itt-video-title">
	<div class="itt-shell itt-shell--narrow itt-video__lead itt-reveal">
		<div class="itt-video__media">
			<?php if ( '' !== $itt_source['type'] ) : ?>
				<button type="button" class="itt-facade" data-itt-video="<?php echo esc_url( $itt_source['src'] ); ?>" data-itt-video-type="<?php echo esc_attr( $itt_source['type'] ); ?>">
					<?php
					itt_image(
						(int) $itt['poster'],
						'lead-video-poster.webp',
						'',
						array(
							'class' => 'itt-facade__poster',
							'sizes' => '(max-width: 900px) 100vw, 50vw',
						)
					);
					?>
					<span class="itt-facade__play" aria-hidden="true">
						<svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" focusable="false"><path d="M8 5.5v13l11-6.5z"></path></svg>
					</span>
					<span class="itt-facade__label">
						<?php
						printf(
							/* translators: %s: video title. */
							esc_html__( 'הפעלת הסרטון: %s', 'itt-landing' ),
							esc_html( (string) $itt['heading'] )
						);
						?>
					</span>
				</button>
			<?php else : ?>
				<?php
				itt_image(
					(int) $itt['poster'],
					'lead-video-poster.webp',
					(string) $itt['poster_alt'],
					array(
						'class' => 'itt-video__poster',
						'sizes' => '(max-width: 900px) 100vw, 50vw',
					)
				);
				?>
			<?php endif; ?>
		</div>

		<div class="itt-video__copy">
			<p class="itt-tag itt-tag--purple"><?php echo esc_html( (string) $itt['badge'] ); ?></p>
			<h2 class="itt-video__title" id="itt-video-title"><?php echo esc_html( (string) $itt['heading'] ); ?></h2>
			<p class="itt-video__text"><?php echo esc_html( (string) $itt['text'] ); ?></p>
		</div>
	</div>

	<?php if ( ! empty( $itt['slides'] ) ) : ?>
		<div class="itt-shell itt-shell--narrow itt-slider itt-reveal" data-itt-slider>
			<div class="itt-slider__head">
				<h3 class="itt-slider__title" id="itt-slider-title"><?php echo esc_html( (string) $itt['slider_heading'] ); ?></h3>

				<div class="itt-slider__nav">
					<button type="button" class="itt-slider__btn" data-itt-slide="prev" aria-label="<?php esc_attr_e( 'המלצות קודמות', 'itt-landing' ); ?>" aria-controls="itt-slider-track">
						<?php itt_chevron( 'prev' ); ?>
					</button>
					<button type="button" class="itt-slider__btn" data-itt-slide="next" aria-label="<?php esc_attr_e( 'המלצות הבאות', 'itt-landing' ); ?>" aria-controls="itt-slider-track">
						<?php itt_chevron( 'next' ); ?>
					</button>
				</div>
			</div>

			<div class="itt-slider__viewport" id="itt-slider-track" tabindex="0" role="group" aria-labelledby="itt-slider-title">
			<ul class="itt-slider__track">
				<?php foreach ( (array) $itt['slides'] as $itt_i => $itt_slide ) : ?>
					<?php $itt_slide_source = itt_video_source( (int) $itt_slide['video_file'], (string) $itt_slide['video_url'] ); ?>
					<li class="itt-slide">
						<div class="itt-slide__media">
							<?php if ( '' !== $itt_slide_source['type'] ) : ?>
								<button type="button" class="itt-facade" data-itt-video="<?php echo esc_url( $itt_slide_source['src'] ); ?>" data-itt-video-type="<?php echo esc_attr( $itt_slide_source['type'] ); ?>">
									<?php
									itt_image(
										(int) $itt_slide['image'],
										'testimonial-' . ( ( (int) $itt_i % 3 ) + 1 ) . '.webp',
										'',
										array( 'class' => 'itt-facade__poster' )
									);
									?>
									<span class="itt-facade__play" aria-hidden="true">
										<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" focusable="false"><path d="M8 5.5v13l11-6.5z"></path></svg>
									</span>
									<span class="itt-facade__label">
										<?php
										printf(
											/* translators: %s: student name. */
											esc_html__( 'הפעלת ההמלצה של %s', 'itt-landing' ),
											esc_html( (string) $itt_slide['name'] )
										);
										?>
									</span>
								</button>
							<?php else : ?>
								<?php
								itt_image(
									(int) $itt_slide['image'],
									'testimonial-' . ( ( (int) $itt_i % 3 ) + 1 ) . '.webp',
									(string) $itt_slide['image_alt'],
									array( 'class' => 'itt-slide__poster' )
								);
								?>
							<?php endif; ?>
						</div>

						<p class="itt-slide__name"><?php echo esc_html( (string) $itt_slide['name'] ); ?></p>
						<p class="itt-slide__quote"><?php echo esc_html( (string) $itt_slide['quote'] ); ?></p>
					</li>
				<?php endforeach; ?>
			</ul>
			</div>
		</div>
	<?php endif; ?>
</section>
