<?php
/**
 * Fallback template.
 *
 * The theme exists to serve one campaign template; anything else — the
 * accessibility statement, a privacy page — gets a plain, readable, accessible
 * rendering rather than an unstyled page.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="msl-main" class="msl-plain">
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : ?>
			<?php the_post(); ?>
			<article <?php post_class( 'msl-plain__article' ); ?>>
				<h1 class="msl-plain__title"><?php the_title(); ?></h1>
				<div class="msl-plain__content"><?php the_content(); ?></div>
			</article>
		<?php endwhile; ?>
	<?php else : ?>
		<h1 class="msl-plain__title"><?php esc_html_e( 'לא נמצא תוכן', 'mashehu-leshabbat' ); ?></h1>
		<p><?php esc_html_e( 'הדף שחיפשת אינו קיים.', 'mashehu-leshabbat' ); ?></p>
	<?php endif; ?>
</main>
<?php
get_footer();
