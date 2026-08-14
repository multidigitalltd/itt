<?php
/**
 * Section 05 — what the ITT method is.
 *
 * @package ITT_Landing
 *
 * @var array<string, mixed> $itt Resolved section content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;
?>
<section class="itt-section itt-section--gray itt-method" aria-labelledby="itt-method-title">
	<div class="itt-shell">
		<div class="itt-section__head itt-reveal">
			<h2 class="itt-section__title" id="itt-method-title"><?php echo esc_html( (string) $itt['heading'] ); ?></h2>
			<p class="itt-section__lead"><?php echo esc_html( (string) $itt['lead'] ); ?></p>
			<p class="itt-section__lead"><?php itt_the_rich( (string) $itt['lead_2'] ); ?></p>
		</div>

		<div class="itt-equation itt-reveal">
			<div class="itt-equation__box">
				<span class="itt-equation__number"><?php echo esc_html( (string) $itt['equation_number'] ); ?></span>
				<span class="itt-equation__label"><?php echo esc_html( (string) $itt['equation_label'] ); ?></span>
			</div>

			<span class="itt-equation__op" aria-hidden="true">+</span>

			<div class="itt-equation__box">
				<span class="itt-equation__title"><?php echo esc_html( (string) $itt['equation_mid'] ); ?></span>
				<span class="itt-equation__sub"><?php echo esc_html( (string) $itt['equation_mid_sub'] ); ?></span>
			</div>

			<span class="itt-equation__op" aria-hidden="true">=</span>

			<div class="itt-equation__box itt-equation__box--result itt-pulse">
				<span class="itt-equation__title"><?php echo esc_html( (string) $itt['equation_result'] ); ?></span>
				<span class="itt-equation__sub"><?php echo esc_html( (string) $itt['equation_result_sub'] ); ?></span>
			</div>
		</div>

		<div class="itt-protocol itt-reveal">
			<div class="itt-protocol__head">
				<p class="itt-tag itt-tag--gold-outline"><?php echo esc_html( (string) $itt['box_badge'] ); ?></p>
				<p class="itt-protocol__title"><?php echo esc_html( (string) $itt['box_heading'] ); ?></p>
			</div>

			<ul class="itt-protocol__list">
				<?php foreach ( (array) $itt['tools'] as $itt_tool ) : ?>
					<li class="itt-protocol__item">
						<span class="itt-dot itt-dot--<?php echo esc_attr( (string) $itt_tool['dot'] ); ?>" aria-hidden="true"></span>
						<span<?php echo empty( $itt_tool['ltr'] ) ? '' : ' dir="ltr"'; ?>><?php echo esc_html( (string) $itt_tool['text'] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</section>
