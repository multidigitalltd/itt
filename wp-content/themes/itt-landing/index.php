<?php
/**
 * Fallback template.
 *
 * The theme exists to serve the two ITT page templates; anything else gets a
 * plain, readable, accessible rendering rather than an unstyled page.
 *
 * @package ITT_Landing
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="itt-main" class="itt-plain">
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : ?>
			<?php the_post(); ?>
			<article <?php post_class( 'itt-plain__article' ); ?>>
				<h1 class="itt-plain__title"><?php the_title(); ?></h1>
				<div class="itt-plain__content"><?php the_content(); ?></div>
			</article>
		<?php endwhile; ?>
	<?php else : ?>
		<h1 class="itt-plain__title"><?php esc_html_e( 'לא נמצא תוכן', 'itt-landing' ); ?></h1>
		<p><?php esc_html_e( 'הדף שחיפשת אינו קיים.', 'itt-landing' ); ?></p>
	<?php endif; ?>
</main>
<?php
get_footer();
