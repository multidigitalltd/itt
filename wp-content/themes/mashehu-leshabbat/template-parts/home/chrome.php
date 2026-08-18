<?php
/**
 * The sticky header.
 *
 * @package Mashehu_LeShabbat
 *
 * @var array<string, mixed> $msl Resolved chrome content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_campaign = MSL_Meta::get( 'campaign' );
?>
<header class="msl-header">
	<div class="msl-header__inner">
		<a class="msl-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php msl_logo( $msl ); ?>
			<span class="msl-brand__word"<?php msl_i18n( 'chrome', 'brand' ); ?>><?php msl_the( $msl, 'brand' ); ?></span>
		</a>

		<div class="msl-header__actions">
			<button type="button" class="msl-langtoggle" data-msl-lang-toggle
				aria-label="<?php esc_attr_e( 'החלפת שפת האתר', 'mashehu-leshabbat' ); ?>">
				<span data-msl-lang-label><?php echo esc_html( (string) $msl[ 'lang_btn_' . MSL_I18N::lang() ] ); ?></span>
			</button>

			<p class="msl-countdown">
				<span class="msl-countdown__dot" aria-hidden="true"></span>
				<span class="msl-countdown__text" data-msl-countdown><?php echo esc_html( msl_countdown( $msl, $msl_campaign ) ); ?></span>
			</p>

			<button type="button" class="msl-btn msl-btn--ink msl-header__cta" data-msl-open-join
				<?php msl_i18n( 'chrome', 'cta' ); ?>><?php msl_the( $msl, 'cta' ); ?></button>
		</div>
	</div>
</header>
