<?php
/**
 * Every group that is open, newest first.
 *
 * @package Mashehu_LeShabbat
 *
 * @var array<string, mixed> $msl_groups Resolved groups content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

if ( ! msl_groups_archive_on() ) {
	return;
}

$msl_rows = MSL_Groups::archive( MSL_Importer::page_id(), 48 );

require MSL_DIR . 'template-parts/groups/pending-notice.php';
?>
<section class="msl-garchive" id="msl-groups" aria-labelledby="msl-garchive-title" data-msl-rise>
	<h2 class="msl-garchive__title" id="msl-garchive-title"<?php msl_i18n( 'groups', 'archive_title' ); ?>><?php msl_the( $msl_groups, 'archive_title' ); ?></h2>

	<?php if ( array() === $msl_rows ) : ?>
		<p class="msl-garchive__empty"<?php msl_i18n( 'groups', 'empty' ); ?>><?php msl_the( $msl_groups, 'empty' ); ?></p>
	<?php else : ?>
		<ul class="msl-garchive__list" data-msl-rise-group>
			<?php
			foreach ( $msl_rows as $msl_row ) :
				$msl_pct = msl_group_pct( $msl_row );
				?>
				<li class="msl-gcard">
					<a class="msl-gcard__link" href="<?php echo esc_url( MSL_Groups::url( (string) $msl_row['code'] ) ); ?>">
						<span class="msl-gcard__light" aria-hidden="true"></span>

						<span class="msl-gcard__title"><?php echo esc_html( (string) $msl_row['title'] ); ?></span>

						<?php if ( '' !== msl_group_dedication( $msl_row, $msl_groups ) ) : ?>
							<span class="msl-gcard__ded"><?php echo msl_group_dedication_html( $msl_row, $msl_groups, 'msl-ded' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside. ?></span>
						<?php endif; ?>

						<span class="msl-gcard__bar" aria-hidden="true">
							<span class="msl-gcard__fill" style="width:<?php echo esc_attr( (string) $msl_pct ); ?>%"></span>
						</span>

						<span class="msl-gcard__meta">
							<?php
							printf(
								esc_html( msl_t( $msl_groups, 'card_progress' ) ),
								esc_html( msl_num( (int) $msl_row['count'] ) ),
								esc_html( msl_num( (int) $msl_row['target'] ) )
							);
							?>
						</span>

						<span class="msl-gcard__cta"<?php msl_i18n( 'groups', 'card_cta' ); ?>><?php msl_the( $msl_groups, 'card_cta' ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
