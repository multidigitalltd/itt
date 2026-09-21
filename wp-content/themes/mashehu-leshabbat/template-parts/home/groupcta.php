<?php
/**
 * The invitation to open a group, on the campaign page.
 *
 * The groups feature lives on a page of its own, which means somebody has to go
 * looking for it — and nobody goes looking for a thing they have not been told
 * exists. This is where they are told: on the page they actually landed on,
 * after they have already seen the artwork and the counter, in the place where
 * "carry this further" is what the page is asking of them anyway.
 *
 * It renders nothing at all unless a group can genuinely be opened right now.
 * There is no groups page in an install that never imported one, and the whole
 * feature can be switched off from the groups page's own settings; either way
 * the honest thing to show is nothing, not a button that leads somewhere that
 * will turn the visitor away.
 *
 * The words for the occasions are the groups page's own — the same strings the
 * form's menu is built from — so the reasons offered here and the reasons on
 * the form can never drift apart.
 *
 * @package Mashehu_LeShabbat
 *
 * @var array<string, mixed> $msl Resolved groupcta content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

if ( 1 !== (int) ( $msl['show'] ?? 0 ) ) {
	return;
}

$msl_url = msl_groups_url();

if ( '' === $msl_url ) {
	return;
}

$msl_gc   = MSL_Meta::get( 'groups' );
$msl_open = 1 === (int) ( $msl_gc['open_on'] ?? 0 );

if ( ! $msl_open ) {
	return;
}
?>
<section id="msl-groupcta" class="msl-gcta" aria-labelledby="msl-gcta-title" data-msl-rise>
	<div class="msl-gcta__inner">
		<p class="msl-gcta__eyebrow"<?php msl_i18n( 'groupcta', 'eyebrow' ); ?>><?php msl_the( $msl, 'eyebrow' ); ?></p>

		<h2 class="msl-heading msl-gcta__title" id="msl-gcta-title"<?php msl_i18n( 'groupcta', 'title' ); ?>><?php msl_the( $msl, 'title' ); ?></h2>

		<p class="msl-gcta__lead"<?php msl_i18n( 'groupcta', 'lead' ); ?>><?php msl_the( $msl, 'lead' ); ?></p>

		<?php
		/*
		 * The reasons, as a plain list rather than five buttons. Each one is a
		 * dedication the form can actually record, and tapping one is not a
		 * choice to be made out here — the form asks it properly, with the name
		 * of the person beside it.
		 *
		 * These are their own strings rather than the form's. The form's words
		 * are prefixes that run into a name — "לרפואת" wants "שרה בת רחל" after
		 * it, and on its own it is half a sentence; in English "For the
		 * recovery of" is worse. A chip has to stand by itself, so it says
		 * "לרפואה". The two lists are edited in the same panel, one section
		 * apart, and the fields are named after the same five dedications.
		 */
		?>
		<h3 class="msl-gcta__chips-label" id="msl-gcta-chips"<?php msl_i18n( 'groupcta', 'chips_label' ); ?>><?php msl_the( $msl, 'chips_label' ); ?></h3>

		<ul class="msl-gcta__chips" aria-labelledby="msl-gcta-chips">
			<?php
			foreach ( MSL_Groups::OCCASIONS as $msl_occasion ) :
				$msl_chip = 'chip_' . $msl_occasion;

				if ( '' === trim( msl_t( $msl, $msl_chip ) ) ) {
					continue;
				}
				?>
				<li class="msl-gcta__chip"<?php msl_i18n( 'groupcta', $msl_chip ); ?>><?php msl_the( $msl, $msl_chip ); ?></li>
			<?php endforeach; ?>
		</ul>

		<p class="msl-gcta__actions">
			<a class="msl-btn msl-btn--hero" href="<?php echo esc_url( msl_groups_url( 'msl-open' ) ); ?>"
				<?php msl_i18n( 'groupcta', 'cta' ); ?>><?php msl_the( $msl, 'cta' ); ?></a>

			<?php if ( msl_groups_archive_on() ) : ?>
				<a class="msl-textbtn msl-gcta__all" href="<?php echo esc_url( $msl_url ); ?>"
					<?php msl_i18n( 'groupcta', 'cta_all' ); ?>><?php msl_the( $msl, 'cta_all' ); ?></a>
			<?php endif; ?>
		</p>

		<p class="msl-gcta__note"<?php msl_i18n( 'groupcta', 'note' ); ?>><?php msl_the( $msl, 'note' ); ?></p>
	</div>

	<span class="msl-gcta__flame" aria-hidden="true"><?php msl_candle_svg( 3.1, 2.2 ); ?></span>
</section>
