<?php
/**
 * One group's page.
 *
 * Its own artwork, its own count against its own target, and the one button
 * that matters. The artwork is the same canvas engine the campaign page uses,
 * initialised with this group's numbers and shape — a second drawing routine
 * for "the small artwork" would be a second thing to keep true.
 *
 * @package Mashehu_LeShabbat
 *
 * @var array<string, mixed> $msl_groups Resolved groups content.
 * @var array<string, mixed> $msl_group  The group being shown.
 * @var string               $msl_token  Owner token from the address, if any.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_group['count'] = MSL_Groups::count_for( (int) $msl_group['id'] );

$msl_pct    = msl_group_pct( $msl_group );
$msl_ded    = msl_group_dedication( $msl_group, $msl_groups );
$msl_owner  = MSL_Groups::owns( $msl_group, $msl_token );
$msl_live   = MSL_Groups::LIVE === $msl_group['status'];
$msl_share  = MSL_Groups::url( (string) $msl_group['code'] );
$msl_wa     = sprintf( msl_t( $msl_groups, 'wa_message' ), $msl_share );
$msl_state  = array(
	MSL_Groups::PENDING  => 'state_pending',
	MSL_Groups::CLOSED   => 'state_closed',
	MSL_Groups::REJECTED => 'state_rejected',
);
?>
<article class="msl-group" data-msl-group="<?php echo esc_attr( (string) $msl_group['code'] ); ?>">

	<?php if ( isset( $msl_state[ $msl_group['status'] ] ) ) : ?>
		<p class="msl-gnote" role="status"<?php msl_i18n( 'groups', $msl_state[ $msl_group['status'] ] ); ?>><?php msl_the( $msl_groups, $msl_state[ $msl_group['status'] ] ); ?></p>
	<?php endif; ?>

	<header class="msl-group__head" data-msl-rise>
		<?php if ( '' !== $msl_ded ) : ?>
			<p class="msl-group__ded"><?php echo esc_html( $msl_ded ); ?></p>
		<?php endif; ?>

		<h1 class="msl-heading"><?php echo esc_html( (string) $msl_group['title'] ); ?></h1>

		<?php if ( '' !== trim( (string) $msl_group['story'] ) ) : ?>
			<div class="msl-group__story"><?php msl_paragraphs( (string) $msl_group['story'] ); ?></div>
		<?php endif; ?>

		<?php if ( '' !== trim( (string) $msl_group['owner_name'] ) ) : ?>
			<p class="msl-group__by">
				<span<?php msl_i18n( 'groups', 'single_opened_by' ); ?>><?php msl_the( $msl_groups, 'single_opened_by' ); ?></span>
				<strong><?php echo esc_html( (string) $msl_group['owner_name'] ); ?></strong>
			</p>
		<?php endif; ?>
	</header>

	<div class="msl-group__stage" data-msl-rise>
		<?php
		/*
		 * Decorative: every number the artwork encodes is written beside it in
		 * words, so nothing here is the only way to learn anything.
		 */
		?>
		<canvas class="msl-group__canvas" data-msl-canvas="hero" aria-hidden="true"></canvas>

		<div class="msl-group__figures">
			<p class="msl-group__count">
				<span class="msl-group__value" data-msl-group-count><?php echo esc_html( msl_num( (int) $msl_group['count'] ) ); ?></span>
				<span class="msl-group__label"<?php msl_i18n( 'groups', 'single_lights' ); ?>><?php msl_the( $msl_groups, 'single_lights' ); ?></span>
			</p>

			<p class="msl-group__target">
				<span class="msl-group__label"<?php msl_i18n( 'groups', 'single_target' ); ?>><?php msl_the( $msl_groups, 'single_target' ); ?></span>
				<span class="msl-group__value"><?php echo esc_html( msl_num( (int) $msl_group['target'] ) ); ?></span>
			</p>
		</div>

		<div class="msl-group__track" role="progressbar" aria-valuemin="0" aria-valuemax="100"
			aria-valuenow="<?php echo esc_attr( (string) $msl_pct ); ?>"
			aria-label="<?php echo esc_attr( msl_t( $msl_groups, 'single_lights' ) ); ?>"
			data-msl-group-progress>
			<span class="msl-group__fill" style="width:<?php echo esc_attr( (string) $msl_pct ); ?>%"></span>
		</div>

		<div class="msl-group__actions">
			<?php if ( $msl_live ) : ?>
				<button type="button" class="msl-btn msl-btn--hero" data-msl-open-join
					<?php msl_i18n( 'groups', 'single_cta' ); ?>><?php msl_the( $msl_groups, 'single_cta' ); ?></button>
			<?php endif; ?>

			<a class="msl-btn msl-btn--whatsapp" data-msl-whatsapp
				data-msl-template="<?php echo esc_attr( msl_t( $msl_groups, 'wa_message' ) ); ?>"
				href="<?php echo esc_url( 'https://wa.me/?text=' . rawurlencode( $msl_wa ) ); ?>"
				target="_blank" rel="noopener"
				<?php msl_i18n( 'groups', 'single_share' ); ?>><?php msl_the( $msl_groups, 'single_share' ); ?></a>
		</div>

		<p class="msl-group__also"<?php msl_i18n( 'groups', 'single_also' ); ?>><?php msl_the( $msl_groups, 'single_also' ); ?></p>
	</div>

	<?php if ( $msl_owner ) : ?>
		<?php
		/*
		 * The owner's own view. It carries the private address in the page it is
		 * already on, so someone who reached their group from a bookmark can
		 * copy the link again without it ever appearing on the public page.
		 */
		?>
		<section class="msl-gowner" data-msl-rise>
			<p class="msl-gdone__label"<?php msl_i18n( 'groups', 'done_link' ); ?>><?php msl_the( $msl_groups, 'done_link' ); ?></p>
			<p class="msl-gdone__link"><a href="<?php echo esc_url( $msl_share ); ?>"><?php echo esc_html( (string) preg_replace( '#^https?://#', '', untrailingslashit( $msl_share ) ) ); ?></a></p>
		</section>
	<?php endif; ?>

	<p class="msl-group__back">
		<a href="<?php echo esc_url( MSL_Groups::page_url() ); ?>"<?php msl_i18n( 'groups', 'single_back' ); ?>><?php msl_the( $msl_groups, 'single_back' ); ?></a>
	</p>
</article>
