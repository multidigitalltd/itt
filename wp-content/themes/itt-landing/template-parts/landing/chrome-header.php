<?php
/**
 * Announcement bar and sticky site header.
 *
 * @package ITT_Landing
 *
 * @var array<string, mixed> $itt_chrome Resolved chrome content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;
?>
<aside class="itt-topbar" aria-label="<?php esc_attr_e( 'הודעת הרשמה', 'itt-landing' ); ?>">
	<div class="itt-shell itt-topbar__inner">
		<span class="itt-topbar__dot" aria-hidden="true"></span>
		<p class="itt-topbar__text"><?php echo esc_html( (string) $itt_chrome['topbar_text'] ); ?></p>
		<a class="itt-topbar__link" href="#form"><?php echo esc_html( (string) $itt_chrome['topbar_cta'] ); ?></a>
	</div>
</aside>

<header class="itt-header">
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

		<div class="itt-header__actions">
			<span class="itt-header__note"><?php echo esc_html( (string) $itt_chrome['header_note'] ); ?></span>
			<a class="itt-header__phone" href="<?php echo esc_url( 'tel:' . preg_replace( '/[^\d*]/', '', (string) $itt_chrome['phone'] ) ); ?>">
				<span dir="ltr"><?php echo esc_html( (string) $itt_chrome['phone'] ); ?></span>
			</a>
			<?php if ( '' !== trim( (string) ( $itt_chrome['men_url'] ?? '' ) ) ) : ?>
				<a class="itt-header__men" href="<?php echo esc_url( (string) $itt_chrome['men_url'] ); ?>">
					<?php echo esc_html( (string) $itt_chrome['men_text'] ); ?>
				</a>
			<?php endif; ?>

			<a class="itt-btn itt-btn--orange itt-header__cta" href="#form">
				<?php echo esc_html( (string) $itt_chrome['header_cta'] ); ?>
				<span aria-hidden="true">←</span>
			</a>
		</div>
	</div>
</header>
