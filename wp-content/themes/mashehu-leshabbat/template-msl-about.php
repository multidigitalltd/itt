<?php
/**
 * Template Name: אור לשבת · על המיזם
 *
 * The story behind the campaign.
 *
 * A reading page rather than an application: no canvas, no counters and nothing
 * that polls. It shares the header and the footer so that a visitor who arrives
 * here from a shared link is one press away from the campaign itself.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_about = MSL_Meta::get( 'about' );
$msl_home  = MSL_Importer::page_id();

get_header();

msl_section( 'chrome' );
?>
<main id="msl-main" class="msl-main msl-main--page">
	<article class="msl-about">
		<header class="msl-about__head" data-msl-rise>
			<p class="msl-about__eyebrow"<?php msl_i18n( 'about', 'eyebrow' ); ?>><?php msl_the( $msl_about, 'eyebrow' ); ?></p>
			<h1 class="msl-about__title"<?php msl_i18n( 'about', 'title' ); ?>><?php msl_the( $msl_about, 'title' ); ?></h1>
			<p class="msl-about__lead"<?php msl_i18n( 'about', 'lead' ); ?>><?php msl_the( $msl_about, 'lead' ); ?></p>
		</header>

		<?php if ( '' !== msl_t( $msl_about, 'verse' ) ) : ?>
			<figure class="msl-verse" data-msl-rise>
				<blockquote class="msl-verse__text"<?php msl_i18n( 'about', 'verse' ); ?>><?php msl_the( $msl_about, 'verse' ); ?></blockquote>
				<figcaption class="msl-verse__source"<?php msl_i18n( 'about', 'verse_source' ); ?>><?php msl_the( $msl_about, 'verse_source' ); ?></figcaption>
			</figure>
		<?php endif; ?>

		<?php
		foreach ( (array) $msl_about['blocks'] as $msl_index => $msl_block ) :
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
					<h2 class="msl-about__heading"<?php msl_i18n( 'about', 'blocks.' . $msl_index . '.title' ); ?>><?php echo esc_html( $msl_heading ); ?></h2>
				<?php endif; ?>

				<div class="msl-about__body"<?php msl_i18n( 'about', 'blocks.' . $msl_index . '.body' ); ?>><?php msl_paragraphs( $msl_body ); ?></div>
			</section>
		<?php endforeach; ?>

		<footer class="msl-about__foot" data-msl-rise>
			<a class="msl-btn msl-btn--amber msl-btn--wide" href="<?php echo esc_url( 0 !== $msl_home ? (string) get_permalink( $msl_home ) : home_url( '/' ) ); ?>"
				<?php msl_i18n( 'about', 'cta' ); ?>><?php msl_the( $msl_about, 'cta' ); ?></a>
		</footer>
	</article>
</main>
<?php
require MSL_DIR . 'template-parts/home/chrome-footer.php';
require MSL_DIR . 'template-parts/home/a11y-widget.php';

get_footer();
