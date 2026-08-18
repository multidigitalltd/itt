<?php
/**
 * The footer.
 *
 * Deliberately quiet: the design ends on the closing call to action, and the
 * only things that belong after it are the legal links and the credit.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_chrome = MSL_Meta::get( 'chrome' );

$msl_links = array_filter(
	array(
		(string) $msl_chrome['accessibility_url'] => __( 'הצהרת נגישות', 'mashehu-leshabbat' ),
		(string) $msl_chrome['terms_url']         => __( 'תנאי שימוש', 'mashehu-leshabbat' ),
		(string) $msl_chrome['privacy_url']       => __( 'מדיניות פרטיות', 'mashehu-leshabbat' ),
	),
	static fn( string $label, string $url ): bool => '' !== trim( $url ),
	ARRAY_FILTER_USE_BOTH
);
?>
<footer class="msl-footer">
	<div class="msl-footer__inner">
		<?php if ( array() !== $msl_links ) : ?>
			<nav class="msl-footer__links" aria-label="<?php esc_attr_e( 'קישורים משפטיים', 'mashehu-leshabbat' ); ?>">
				<?php foreach ( $msl_links as $msl_url => $msl_label ) : ?>
					<a href="<?php echo esc_url( $msl_url ); ?>"><?php echo esc_html( $msl_label ); ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>

		<?php if ( '' !== trim( (string) $msl_chrome['credit_text'] ) ) : ?>
			<p class="msl-footer__credit">
				<?php if ( '' !== trim( (string) $msl_chrome['credit_url'] ) ) : ?>
					<a href="<?php echo esc_url( (string) $msl_chrome['credit_url'] ); ?>" rel="noopener"><?php echo esc_html( (string) $msl_chrome['credit_text'] ); ?></a>
				<?php else : ?>
					<?php echo esc_html( (string) $msl_chrome['credit_text'] ); ?>
				<?php endif; ?>
			</p>
		<?php endif; ?>
	</div>
</footer>
