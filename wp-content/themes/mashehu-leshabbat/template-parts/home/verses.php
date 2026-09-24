<?php
/**
 * Verses and sayings in praise of Shabbat.
 *
 * The last thing on the page before it asks again — the campaign has spent the
 * whole page counting candles, and this is where it says what the counting is
 * for, in words older than the campaign.
 *
 * Every quote is editable, because the choice of what a project puts under its
 * own name is not a developer's decision. An empty list removes the section.
 *
 * @package Mashehu_LeShabbat
 *
 * @var array<string, mixed> $msl Resolved verses content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_quotes = array();

foreach ( (array) ( $msl['quotes'] ?? array() ) as $msl_index => $msl_row ) {
	if ( ! is_array( $msl_row ) ) {
		continue;
	}

	$msl_text = msl_t( $msl_row, 'text' );

	if ( '' === trim( $msl_text ) ) {
		continue;
	}

	$msl_quotes[] = array(
		'index'  => (int) $msl_index,
		'text'   => $msl_text,
		'source' => msl_t( $msl_row, 'source' ),
	);
}

if ( array() === $msl_quotes ) {
	return;
}
?>
<section id="msl-verses" class="msl-verses" aria-labelledby="msl-verses-title" data-msl-rise>
	<div class="msl-verses__head">
		<h2 class="msl-heading" id="msl-verses-title"<?php msl_i18n( 'verses', 'title' ); ?>><?php msl_the( $msl, 'title' ); ?></h2>
		<p class="msl-subheading"<?php msl_i18n( 'verses', 'sub' ); ?>><?php msl_the( $msl, 'sub' ); ?></p>
	</div>

	<ul class="msl-verses__list" data-msl-rise-group>
		<?php foreach ( $msl_quotes as $msl_quote ) : ?>
			<li class="msl-quote">
				<?php
				/*
				 * The light above the words is the mark this site has used since
				 * the hero: a single point of light, not a decoration borrowed
				 * from somewhere else. It carries no meaning the quote does not,
				 * so it is hidden from assistive technology.
				 */
				?>
				<span class="msl-quote__light" aria-hidden="true"></span>

				<figure class="msl-quote__figure">
					<blockquote class="msl-quote__text"<?php msl_i18n( 'verses', 'quotes.' . $msl_quote['index'] . '.text' ); ?>><?php echo esc_html( $msl_quote['text'] ); ?></blockquote>

					<?php if ( '' !== $msl_quote['source'] ) : ?>
						<figcaption class="msl-quote__source"<?php msl_i18n( 'verses', 'quotes.' . $msl_quote['index'] . '.source' ); ?>><?php echo esc_html( $msl_quote['source'] ); ?></figcaption>
					<?php endif; ?>
				</figure>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
