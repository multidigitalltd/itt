<?php
/**
 * Template Name: אור לשבת · מדיניות פרטיות
 *
 * What the site collects, what it does not, and who to write to.
 *
 * A reading page, like "about": it borrows that page's layout rather than
 * inventing one, because a policy nobody can read is not a policy. Every word
 * on it is editable in the content panel — the technical parts are true of the
 * theme as it ships, and the rest belongs to whoever runs the campaign.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_privacy = MSL_Meta::get( 'privacy' );
$msl_home    = MSL_Importer::page_id();
$msl_mail    = trim( (string) $msl_privacy['contact_email'] );

get_header();

msl_section( 'chrome' );
?>
<main id="msl-main" class="msl-main msl-main--page">
	<article class="msl-about">
		<header class="msl-about__head" data-msl-rise>
			<p class="msl-about__eyebrow"<?php msl_i18n( 'privacy', 'eyebrow' ); ?>><?php msl_the( $msl_privacy, 'eyebrow' ); ?></p>
			<h1 class="msl-about__title"<?php msl_i18n( 'privacy', 'title' ); ?>><?php msl_the( $msl_privacy, 'title' ); ?></h1>
			<p class="msl-about__lead"<?php msl_i18n( 'privacy', 'lead' ); ?>><?php msl_the( $msl_privacy, 'lead' ); ?></p>

			<?php if ( '' !== msl_t( $msl_privacy, 'updated' ) ) : ?>
				<p class="msl-about__meta"<?php msl_i18n( 'privacy', 'updated' ); ?>><?php msl_the( $msl_privacy, 'updated' ); ?></p>
			<?php endif; ?>
		</header>

		<?php
		foreach ( (array) $msl_privacy['blocks'] as $msl_index => $msl_block ) :
			if ( ! is_array( $msl_block ) ) {
				continue;
			}

			$msl_heading = msl_t( $msl_block, 'title' );
			$msl_body    = msl_t( $msl_block, 'body' );

			if ( '' === $msl_heading && '' === $msl_body ) {
				continue;
			}
			?>
			<section class="msl-about__block" data-msl-rise>
				<?php if ( '' !== $msl_heading ) : ?>
					<h2 class="msl-about__heading"<?php msl_i18n( 'privacy', 'blocks.' . $msl_index . '.title' ); ?>><?php echo esc_html( $msl_heading ); ?></h2>
				<?php endif; ?>

				<div class="msl-about__body"<?php msl_i18n( 'privacy', 'blocks.' . $msl_index . '.body' ); ?>><?php msl_paragraphs( $msl_body ); ?></div>
			</section>
		<?php endforeach; ?>

		<section class="msl-about__block" data-msl-rise>
			<h2 class="msl-about__heading"<?php msl_i18n( 'privacy', 'contact_title' ); ?>><?php msl_the( $msl_privacy, 'contact_title' ); ?></h2>

			<div class="msl-about__body">
				<p<?php msl_i18n( 'privacy', 'contact_lead' ); ?>><?php msl_the( $msl_privacy, 'contact_lead' ); ?></p>

				<p>
					<?php if ( '' !== (string) $msl_privacy['contact_name'] ) : ?>
						<span class="msl-about__contactname"><?php echo esc_html( (string) $msl_privacy['contact_name'] ); ?></span>
					<?php endif; ?>

					<?php if ( '' !== $msl_mail ) : ?>
						<?php
						/*
						 * A mailto link and not a plain address: the person
						 * reading this is being asked to write, and making
						 * that one press is the difference between a right
						 * that exists and a right that is exercised.
						 */
						?>
						<a href="<?php echo esc_url( 'mailto:' . $msl_mail ); ?>"><?php echo esc_html( $msl_mail ); ?></a>
					<?php endif; ?>
				</p>
			</div>
		</section>

		<footer class="msl-about__foot" data-msl-rise>
			<a class="msl-btn msl-btn--amber msl-btn--wide" href="<?php echo esc_url( 0 !== $msl_home ? (string) get_permalink( $msl_home ) : home_url( '/' ) ); ?>"
				<?php msl_i18n( 'privacy', 'cta' ); ?>><?php msl_the( $msl_privacy, 'cta' ); ?></a>
		</footer>
	</article>
</main>
<?php
require MSL_DIR . 'template-parts/home/chrome-footer.php';
require MSL_DIR . 'template-parts/home/a11y-widget.php';

get_footer();
