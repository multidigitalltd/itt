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
<?php
/**
 * The announcement bar.
 *
 * On a phone the notice is a single scrolling line rather than three wrapped
 * ones, so the bar costs ~40px of the first screen instead of ~95px. The
 * marquee is one track plus an aria-hidden copy of itself: the copy is what
 * makes the loop seamless, and hiding it keeps a screen reader from reading
 * the notice twice.
 *
 * WCAG 2.2.2 (Pause, Stop, Hide) applies to motion that runs for more than
 * five seconds. The accessibility widget's "עצירת אנימציות" switch stops it,
 * and prefers-reduced-motion stops it without being asked; in both cases the
 * bar falls back to the static wrapped layout.
 */
?>
<aside class="itt-topbar" aria-label="<?php esc_attr_e( 'הודעת הרשמה', 'itt-landing' ); ?>">
	<div class="itt-shell itt-topbar__inner">
		<div class="itt-topbar__track">
			<span class="itt-topbar__dot" aria-hidden="true"></span>
			<p class="itt-topbar__text"><?php echo esc_html( (string) $itt_chrome['topbar_text'] ); ?></p>
			<a class="itt-topbar__link" href="#form"><?php echo esc_html( (string) $itt_chrome['topbar_cta'] ); ?></a>
		</div>

		<div class="itt-topbar__track itt-topbar__track--clone" aria-hidden="true">
			<span class="itt-topbar__dot"></span>
			<p class="itt-topbar__text"><?php echo esc_html( (string) $itt_chrome['topbar_text'] ); ?></p>
			<span class="itt-topbar__link"><?php echo esc_html( (string) $itt_chrome['topbar_cta'] ); ?></span>
		</div>
	</div>
</aside>

<header class="itt-header">
	<div class="itt-shell itt-header__inner">
		<a class="itt-header__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<?php
			/**
			 * Three places, most specific first: this page's own logo field,
			 * then the site logo from עיצוב → התאמה אישית → זהות האתר, then
			 * the file shipped with the theme. Most sites will only ever use
			 * the Customizer one.
			 */
			$itt_logo_id = (int) $itt_chrome['logo'];

			if ( 0 === $itt_logo_id ) {
				$itt_logo_id = (int) get_theme_mod( 'custom_logo' );
			}

			itt_image(
				$itt_logo_id,
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
