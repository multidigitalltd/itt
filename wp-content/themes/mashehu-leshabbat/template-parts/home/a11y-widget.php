<?php
/**
 * The floating accessibility widget.
 *
 * Required by the house standard on every page the theme renders, and the only
 * way in to the adjustments — so it is fixed to the viewport and reachable from
 * anywhere on the page at any scroll position.
 *
 * The panel itself is built by the script, because it is inert markup until
 * someone opens it; what has to exist in the HTML is the button that opens it,
 * so the widget works the moment the page paints.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;
?>
<div class="msl-a11y" data-msl-a11y>
	<button type="button" class="msl-a11y__toggle" data-msl-a11y-toggle
		aria-expanded="false"
		aria-label="<?php esc_attr_e( 'תפריט נגישות', 'mashehu-leshabbat' ); ?>">
		<svg viewBox="0 0 24 24" width="26" height="26" aria-hidden="true" focusable="false">
			<circle cx="12" cy="12" r="11" fill="none" stroke="currentColor" stroke-width="1.6"></circle>
			<circle cx="12" cy="6.2" r="1.7" fill="currentColor"></circle>
			<path d="M5.6 9.1h12.8M12 9.6v4.2M12 13.8l-2.6 5.4M12 13.8l2.6 5.4"
				fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"></path>
		</svg>
	</button>
</div>
