<?php
/**
 * Section 09 — syllabus with semester tabs and a lesson accordion.
 *
 * The accordion is built from native <details> elements, so it is keyboard
 * operable and screen-reader friendly before a single line of JavaScript runs;
 * the script only adds the "one open at a time" behaviour from the design.
 *
 * @package ITT_Landing
 *
 * @var array<string, mixed> $itt Resolved section content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$itt_tabs = array(
	'a' => array(
		'label'   => (string) $itt['tab_a'],
		'lessons' => (array) $itt['lessons_a'],
	),
	'b' => array(
		'label'   => (string) $itt['tab_b'],
		'lessons' => (array) $itt['lessons_b'],
	),
);
?>
<section class="itt-section itt-syllabus" id="syllabus" aria-labelledby="itt-syllabus-title" tabindex="-1">
	<noscript>
		<style>.itt-syllabus__panel[hidden]{display:block}.itt-syllabus__tabs{display:none}</style>
	</noscript>

	<div class="itt-shell itt-shell--narrow">
		<div class="itt-section__head itt-reveal">
			<p class="itt-tag itt-tag--gold"><?php echo esc_html( (string) $itt['badge'] ); ?></p>
			<h2 class="itt-section__title itt-section__title--light" id="itt-syllabus-title"><?php itt_the_rich( (string) $itt['heading'] ); ?></h2>

			<?php if ( ! empty( $itt['stats'] ) ) : ?>
				<ul class="itt-stats">
					<?php foreach ( (array) $itt['stats'] as $itt_stat ) : ?>
						<?php $itt_number = trim( (string) $itt_stat['number'] ); ?>
						<li class="itt-stats__item">
							<span class="itt-stats__number<?php echo ctype_digit( $itt_number ) ? ' itt-count' : ''; ?>"<?php echo ctype_digit( $itt_number ) ? ' data-count="' . esc_attr( $itt_number ) . '"' : ''; ?>>
								<?php echo esc_html( $itt_number ); ?>
							</span>
							<span class="itt-stats__label"><?php echo esc_html( (string) $itt_stat['label'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>

		<div class="itt-syllabus__tabs" role="tablist" aria-label="<?php esc_attr_e( 'בחירת סמסטר', 'itt-landing' ); ?>">
			<?php foreach ( $itt_tabs as $itt_key => $itt_tab ) : ?>
				<button
					type="button"
					role="tab"
					id="itt-tab-<?php echo esc_attr( $itt_key ); ?>"
					class="itt-tab<?php echo 'a' === $itt_key ? ' is-active' : ''; ?>"
					aria-controls="itt-panel-<?php echo esc_attr( $itt_key ); ?>"
					aria-selected="<?php echo 'a' === $itt_key ? 'true' : 'false'; ?>"
					tabindex="<?php echo 'a' === $itt_key ? '0' : '-1'; ?>"
				><?php echo esc_html( $itt_tab['label'] ); ?></button>
			<?php endforeach; ?>
		</div>

		<?php foreach ( $itt_tabs as $itt_key => $itt_tab ) : ?>
			<div
				class="itt-syllabus__panel"
				id="itt-panel-<?php echo esc_attr( $itt_key ); ?>"
				role="tabpanel"
				aria-labelledby="itt-tab-<?php echo esc_attr( $itt_key ); ?>"
				tabindex="0"
				<?php echo 'a' === $itt_key ? '' : 'hidden'; ?>
			>
				<h3 class="screen-reader-text"><?php echo esc_html( $itt_tab['label'] ); ?></h3>

				<ul class="itt-lessons" data-itt-accordion>
					<?php foreach ( $itt_tab['lessons'] as $itt_lesson ) : ?>
						<li class="itt-lessons__item">
							<details class="itt-lesson">
								<summary class="itt-lesson__summary">
									<span class="itt-lesson__title"><?php echo esc_html( (string) $itt_lesson['title'] ); ?></span>
									<span class="itt-lesson__sign" aria-hidden="true"></span>
								</summary>

								<?php $itt_bullets = itt_lines( (string) $itt_lesson['bullets'] ); ?>
								<?php if ( array() !== $itt_bullets ) : ?>
									<ul class="itt-lesson__bullets">
										<?php foreach ( $itt_bullets as $itt_bullet ) : ?>
											<li><?php echo esc_html( $itt_bullet ); ?></li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</details>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endforeach; ?>

		<div class="itt-syllabus__bonus itt-reveal">
			<h3 class="itt-syllabus__bonus-title"><?php echo esc_html( (string) $itt['bonus_title'] ); ?></h3>
			<ul class="itt-syllabus__bonus-list">
				<?php foreach ( itt_lines( (string) $itt['bonus_lines'] ) as $itt_line ) : ?>
					<li><?php echo esc_html( $itt_line ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</section>

<div class="itt-band itt-band--cyan">
	<div class="itt-shell itt-shell--narrow itt-band__inner">
		<div class="itt-band__copy">
			<p class="itt-band__title"><?php echo esc_html( (string) $itt['band_title'] ); ?></p>
			<p class="itt-band__text"><?php echo esc_html( (string) $itt['band_text'] ); ?></p>
		</div>
		<a class="itt-btn itt-btn--purple" href="#form"><?php echo esc_html( (string) $itt['band_cta'] ); ?></a>
	</div>
</div>
