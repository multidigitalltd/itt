<?php
/**
 * Floating accessibility widget.
 *
 * Fixed to the viewport, so the controls are reachable at any scroll position —
 * ת"י 5568 expects the accessibility menu to be available from every point on
 * the page, not parked in the footer.
 *
 * The panel is a real dialog: focus is trapped while it is open, ESC closes it,
 * and focus returns to the trigger. Every option is a toggle button carrying
 * aria-pressed, and the whole thing works from the keyboard alone.
 *
 * @package ITT_Landing
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$itt_a11y_page = ITT_Importer::page_id( 'accessibility' );

/**
 * Toggle definitions: key => label. The key doubles as the body class suffix
 * and the localStorage flag, so adding one is a single line here plus CSS.
 */
$itt_a11y_toggles = array(
	'contrast'  => __( 'ניגודיות גבוהה', 'itt-landing' ),
	'dark'      => __( 'ניגודיות כהה', 'itt-landing' ),
	'links'     => __( 'הדגשת קישורים', 'itt-landing' ),
	'readable'  => __( 'גופן קריא', 'itt-landing' ),
	'spacing'   => __( 'ריווח טקסט מוגדל', 'itt-landing' ),
	'no-motion' => __( 'עצירת אנימציות', 'itt-landing' ),
);
?>
<div class="itt-a11y" data-itt-a11y>
	<button
		type="button"
		class="itt-a11y__toggle"
		data-itt-a11y-open
		aria-expanded="false"
		aria-controls="itt-a11y-panel"
	>
		<svg width="26" height="26" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
			<circle cx="12" cy="4" r="2"></circle>
			<path d="M20 7.5c-2.6.8-5.2 1.2-8 1.2s-5.4-.4-8-1.2a1 1 0 1 0-.6 1.9c1.9.6 3.9 1 5.9 1.2v2.2L7.1 20a1 1 0 0 0 1.8.9L12 15l3.1 5.9a1 1 0 0 0 1.8-.9l-2.2-7.2v-2.2c2-.2 4-.6 5.9-1.2a1 1 0 1 0-.6-1.9z"></path>
		</svg>
		<span class="screen-reader-text"><?php esc_html_e( 'תפריט נגישות', 'itt-landing' ); ?></span>
	</button>

	<div
		class="itt-a11y__panel"
		id="itt-a11y-panel"
		role="dialog"
		aria-modal="false"
		aria-labelledby="itt-a11y-title"
		hidden
	>
		<div class="itt-a11y__head">
			<h2 class="itt-a11y__title" id="itt-a11y-title"><?php esc_html_e( 'התאמות נגישות', 'itt-landing' ); ?></h2>
			<button type="button" class="itt-a11y__close" data-itt-a11y-close>
				<span aria-hidden="true">✕</span>
				<span class="screen-reader-text"><?php esc_html_e( 'סגירת תפריט הנגישות', 'itt-landing' ); ?></span>
			</button>
		</div>

		<div class="itt-a11y__group">
			<p class="itt-a11y__label" id="itt-a11y-font"><?php esc_html_e( 'גודל טקסט', 'itt-landing' ); ?></p>
			<div class="itt-a11y__row" role="group" aria-labelledby="itt-a11y-font">
				<button type="button" class="itt-a11y__step" data-itt-a11y-font="down">
					<span aria-hidden="true">−</span>
					<span class="screen-reader-text"><?php esc_html_e( 'הקטנת הטקסט', 'itt-landing' ); ?></span>
				</button>
				<span class="itt-a11y__value" data-itt-a11y-font-value aria-live="polite">100%</span>
				<button type="button" class="itt-a11y__step" data-itt-a11y-font="up">
					<span aria-hidden="true">+</span>
					<span class="screen-reader-text"><?php esc_html_e( 'הגדלת הטקסט', 'itt-landing' ); ?></span>
				</button>
			</div>
		</div>

		<ul class="itt-a11y__options">
			<?php foreach ( $itt_a11y_toggles as $itt_key => $itt_label ) : ?>
				<li>
					<button type="button" class="itt-a11y__option" data-itt-a11y-toggle="<?php echo esc_attr( $itt_key ); ?>" aria-pressed="false">
						<span class="itt-a11y__check" aria-hidden="true"></span>
						<?php echo esc_html( $itt_label ); ?>
					</button>
				</li>
			<?php endforeach; ?>
		</ul>

		<div class="itt-a11y__foot">
			<button type="button" class="itt-a11y__reset" data-itt-a11y-reset>
				<?php esc_html_e( 'איפוס ההתאמות', 'itt-landing' ); ?>
			</button>

			<?php if ( $itt_a11y_page > 0 ) : ?>
				<a class="itt-a11y__statement" href="<?php echo esc_url( (string) get_permalink( $itt_a11y_page ) ); ?>">
					<?php echo esc_html( get_the_title( $itt_a11y_page ) ); ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
</div>
