<?php
/**
 * Section 15 — FAQ accordion.
 *
 * Built from native <details>, with FAQPage structured data emitted alongside.
 *
 * @package ITT_Landing
 *
 * @var array<string, mixed> $itt Resolved section content.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$itt_items = (array) $itt['items'];
?>
<section class="itt-section itt-section--gray itt-faq" aria-labelledby="itt-faq-title">
	<div class="itt-shell itt-shell--faq">
		<h2 class="itt-section__title itt-section__title--start itt-reveal" id="itt-faq-title">
			<?php echo esc_html( (string) $itt['heading'] ); ?>
		</h2>

		<ul class="itt-faq__list itt-reveal" data-itt-accordion>
			<?php foreach ( $itt_items as $itt_i => $itt_item ) : ?>
				<li class="itt-faq__item">
					<details class="itt-faq__details"<?php echo 0 === $itt_i ? ' open' : ''; ?>>
						<summary class="itt-faq__summary">
							<span class="itt-faq__question"><?php echo esc_html( (string) $itt_item['question'] ); ?></span>
							<span class="itt-faq__sign" aria-hidden="true"></span>
						</summary>
						<div class="itt-faq__answer">
							<p><?php echo esc_html( (string) $itt_item['answer'] ); ?></p>
						</div>
					</details>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>

<?php
if ( array() !== $itt_items ) :
	$itt_schema = array(
		'@context'   => 'https://schema.org',
		'@type'      => 'FAQPage',
		'mainEntity' => array_map(
			static fn( array $item ): array => array(
				'@type'          => 'Question',
				'name'           => wp_strip_all_tags( (string) $item['question'] ),
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => wp_strip_all_tags( (string) $item['answer'] ),
				),
			),
			$itt_items
		),
	);
	?>
	<script type="application/ld+json">
		<?php echo wp_json_encode( $itt_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); ?>
	</script>
	<?php
endif;
