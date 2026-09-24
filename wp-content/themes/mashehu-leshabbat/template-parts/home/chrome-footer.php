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

/**
 * Where a legal link goes: the address that was typed, or the page the theme
 * made for it.
 *
 * The two pages exist from the first admin visit, so leaving the field empty
 * used to mean the site had an accessibility statement and a privacy policy
 * that nothing on it linked to — which for both of them is the same as not
 * having one. A typed address still wins: a campaign that keeps its policy
 * somewhere else means it.
 *
 * @param string $typed     The address from the content panel.
 * @param string $blueprint The page the theme creates for it.
 * @return string
 */
$msl_legal = static function ( string $typed, string $blueprint ): string {
	if ( '' !== trim( $typed ) ) {
		return trim( $typed );
	}

	$page = MSL_Importer::page_id( $blueprint );

	return $page > 0 ? (string) get_permalink( $page ) : '';
};

$msl_links = array_filter(
	array(
		$msl_legal( (string) $msl_chrome['accessibility_url'], 'accessibility' ) => __( 'הצהרת נגישות', 'mashehu-leshabbat' ),
		(string) $msl_chrome['terms_url']                                        => __( 'תנאי שימוש', 'mashehu-leshabbat' ),
		$msl_legal( (string) $msl_chrome['privacy_url'], 'privacy' )             => __( 'מדיניות פרטיות', 'mashehu-leshabbat' ),
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
