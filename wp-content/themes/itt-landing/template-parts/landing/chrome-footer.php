<?php
/**
 * Site footer, and — on the landing page only — the fixed bottom CTA bar.
 *
 * @package ITT_Landing
 *
 * @var array<string, mixed> $itt_chrome     Resolved chrome content.
 * @var bool                 $itt_show_sticky Whether to render the fixed CTA bar.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$itt_show_sticky ??= is_page_template( 'template-itt-landing.php' );
?>
<footer class="itt-footer">
	<div class="itt-shell itt-footer__inner">
		<p class="itt-footer__tagline"><?php echo esc_html( (string) $itt_chrome['footer_tagline'] ); ?></p>

		<ul class="itt-footer__contact">
			<li>
				<a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^\d*]/', '', (string) $itt_chrome['phone'] ) ); ?>" dir="ltr">
					<?php echo esc_html( (string) $itt_chrome['phone'] ); ?>
				</a>
			</li>
			<li>
				<a href="<?php echo esc_url( (string) $itt_chrome['whatsapp'] ); ?>" dir="ltr" rel="noopener">
					<?php echo esc_html( (string) $itt_chrome['whatsapp_label'] ); ?>
				</a>
			</li>
			<li>
				<a href="<?php echo esc_url( 'mailto:' . $itt_chrome['email'] ); ?>" dir="ltr">
					<?php echo esc_html( (string) $itt_chrome['email'] ); ?>
				</a>
			</li>
		</ul>

		<?php
		$itt_a11y = ITT_Importer::page_id( 'accessibility' );

		if ( ! has_nav_menu( 'itt_legal' ) && $itt_a11y > 0 ) :
			// No legal menu assigned yet: still surface the accessibility
			// statement, which ת"י 5568 requires to be reachable from every page.
			?>
			<nav class="itt-footer__legal" aria-label="<?php esc_attr_e( 'קישורים משפטיים', 'itt-landing' ); ?>">
				<ul class="itt-footer__legal-list">
					<li>
						<a href="<?php echo esc_url( (string) get_permalink( $itt_a11y ) ); ?>">
							<?php echo esc_html( get_the_title( $itt_a11y ) ); ?>
						</a>
					</li>
				</ul>
			</nav>
			<?php
		endif;

		if ( has_nav_menu( 'itt_legal' ) ) {
			wp_nav_menu(
				array(
					'theme_location' => 'itt_legal',
					'container'      => 'nav',
					'container_class' => 'itt-footer__legal',
					'menu_class'     => 'itt-footer__legal-list',
					'depth'          => 1,
					'fallback_cb'    => false,
				)
			);
		}
		?>

		<?php if ( '' !== trim( (string) ( $itt_chrome['credit_text'] ?? '' ) ) ) : ?>
			<p class="itt-footer__credit">
				<?php if ( '' !== trim( (string) ( $itt_chrome['credit_url'] ?? '' ) ) ) : ?>
					<a href="<?php echo esc_url( (string) $itt_chrome['credit_url'] ); ?>" target="_blank" rel="noopener">
						<?php echo esc_html( (string) $itt_chrome['credit_text'] ); ?>
						<span class="screen-reader-text"><?php esc_html_e( '(נפתח בחלון חדש)', 'itt-landing' ); ?></span>
					</a>
				<?php else : ?>
					<?php echo esc_html( (string) $itt_chrome['credit_text'] ); ?>
				<?php endif; ?>
			</p>
		<?php endif; ?>
	</div>
</footer>

<?php if ( $itt_show_sticky ) : ?>
	<aside class="itt-sticky" aria-label="<?php esc_attr_e( 'פעולות מהירות', 'itt-landing' ); ?>">
		<div class="itt-shell itt-sticky__inner">
			<p class="itt-sticky__text"><?php echo esc_html( (string) $itt_chrome['sticky_text'] ); ?></p>
			<div class="itt-sticky__actions">
				<a class="itt-btn itt-btn--ghost-cyan itt-sticky__wa" href="<?php echo esc_url( (string) $itt_chrome['whatsapp'] ); ?>" rel="noopener">
					<?php itt_icon( 'whatsapp' ); ?>
					<span class="itt-sticky__wa-label"><?php echo esc_html( (string) $itt_chrome['sticky_whatsapp'] ); ?></span>
				</a>
				<a class="itt-btn itt-btn--orange itt-sticky__cta" href="#form">
					<?php echo esc_html( (string) $itt_chrome['sticky_cta'] ); ?>
				</a>
			</div>
		</div>
	</aside>
<?php endif; ?>

<?php require ITT_DIR . 'template-parts/a11y-widget.php'; ?>
