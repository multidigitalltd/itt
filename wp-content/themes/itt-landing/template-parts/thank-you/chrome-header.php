<?php
/**
 * Slim header for the thank-you page.
 *
 * @package ITT_Landing
 *
 * @var array<string, mixed> $itt_chrome Resolved chrome content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;
?>
<header class="itt-header itt-header--slim">
	<div class="itt-shell itt-header__inner">
		<a class="itt-header__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<?php
			itt_image(
				(int) $itt_chrome['logo'],
				'logo-merkaz-yashir.webp',
				(string) $itt_chrome['logo_alt'],
				array(
					'class'   => 'itt-header__logo-img',
					'loading' => 'eager',
				)
			);
			?>
		</a>
		<a class="itt-header__phone" href="<?php echo esc_url( 'tel:' . preg_replace( '/[^\d*]/', '', (string) $itt_chrome['phone'] ) ); ?>">
			<span dir="ltr"><?php echo esc_html( (string) $itt_chrome['phone'] ); ?></span>
		</a>
	</div>
</header>
