<?php
/**
 * One group's page, as a crowdfunding page.
 *
 * Which is what it is: somebody is asking people they know for a specific
 * number of a specific thing, on behalf of a named person, and the page has to
 * do what those pages do — say how far along it is, who has already said yes,
 * and make the one action impossible to miss. The earlier version put the
 * numbers in a quiet row under the artwork and left the button below the fold
 * on a phone.
 *
 * The artwork is the same canvas engine the campaign page uses, initialised
 * with this group's own count, target, shape and colour — a second drawing
 * routine for "the small artwork" would be a second thing to keep true. It
 * fills in proportion: nothing lit at zero, every piece lit at the target.
 *
 * A group that is still waiting for approval is **joinable**. Approval is the
 * campaign reading a stranger's text before it is published under the project's
 * name; it is not a gate on whether that person's family may light candles, and
 * the link is already in twenty relatives' hands by then. See
 * MSL_Groups::accepts_joins().
 *
 * @package Mashehu_LeShabbat
 *
 * @var array<string, mixed> $msl_groups Resolved groups content.
 * @var array<string, mixed> $msl_group  The group being shown.
 * @var string               $msl_token  Owner token from the address, if any.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_group['count'] = MSL_Groups::lights( $msl_group );

$msl_pct   = msl_group_pct( $msl_group );
$msl_ded   = msl_group_dedication( $msl_group, $msl_groups );
$msl_owner = MSL_Groups::owns( $msl_group, $msl_token );
$msl_open  = MSL_Groups::accepts_joins( $msl_group );
$msl_wait  = MSL_Groups::PENDING === $msl_group['status'];
$msl_share = MSL_Groups::url( (string) $msl_group['code'] );
/*
 * The picture, if it is still there. A row keeps the id of an attachment that
 * somebody may since have deleted in Media — which is a legitimate way to take
 * a photograph off a group — and an <img> pointing at nothing is worse than no
 * picture at all.
 */
$msl_cover = (int) ( $msl_group['photo_id'] ?? 0 );
$msl_cover = $msl_cover > 0 && wp_attachment_is_image( $msl_cover ) ? $msl_cover : 0;
$msl_wa    = sprintf( msl_t( $msl_groups, 'wa_message' ), $msl_share );
/*
 * Who is listed: the people who really joined, and after them the names the
 * campaign listed itself. Real joins come first because they are real; the
 * listed names fill in behind them, which is what makes a group that has just
 * opened look like a group rather than like an error.
 *
 * A campaign can turn the whole list off in the groups box, and then it is not
 * read at all rather than read and hidden — the rows are nobody's business if
 * they are not going on the page, and a hidden list is one CSS mistake away
 * from being a visible one.
 */
$msl_show_people = 1 === (int) ( $msl_groups['show_people'] ?? 0 );

$msl_feed = ! $msl_show_people ? array() : array_merge(
	MSL_Joins::group_feed( (int) $msl_group['id'] ),
	/*
	 * The names the campaign listed itself carry no time, and none is invented
	 * for them: a made-up "four minutes ago" next to a real one is the kind of
	 * detail that, once noticed, makes a visitor doubt the number too.
	 */
	array_map(
		static fn( array $row ): array => $row + array( 'when' => 0 ),
		MSL_Groups::seed_people( $msl_group )
	)
);
$msl_state = array(
	MSL_Groups::PENDING  => 'state_pending',
	MSL_Groups::CLOSED   => 'state_closed',
	MSL_Groups::REJECTED => 'state_rejected',
);
?>
<article class="msl-gfund" data-msl-group="<?php echo esc_attr( (string) $msl_group['code'] ); ?>">

	<?php
	/*
	 * The picture, where one was uploaded. It is the first thing on the page
	 * because it is the reason the page is about a person rather than about a
	 * number — and it is entirely optional: a group without one reads exactly
	 * as it did before, with nothing left behind where it would have been.
	 *
	 * Width and height are printed so the rest of the page does not jump when
	 * it loads, and it is eager rather than lazy for the same reason: it is
	 * the topmost thing on the screen, and lazy-loading what is already in
	 * view only delays it.
	 */
	?>
	<?php if ( $msl_cover > 0 ) : ?>
		<figure class="msl-gcover" data-msl-rise>
			<?php
			echo wp_get_attachment_image(
				$msl_cover,
				'large',
				false,
				array(
					'class'   => 'msl-gcover__img',
					'alt'     => esc_attr( msl_t( $msl_groups, 'photo_alt' ) ),
					'loading' => 'eager',
				)
			);
			?>
		</figure>
	<?php endif; ?>


	<?php if ( isset( $msl_state[ $msl_group['status'] ] ) ) : ?>
		<p class="msl-gnote" role="status"<?php msl_i18n( 'groups', $msl_state[ $msl_group['status'] ] ); ?>><?php msl_the( $msl_groups, $msl_state[ $msl_group['status'] ] ); ?></p>
	<?php endif; ?>

	<div class="msl-gfund__grid">

		<?php
		/*
		 * Decorative: every number the artwork encodes is written beside it in
		 * words, so nothing here is the only way to learn anything.
		 */
		?>
		<div class="msl-gfund__art" data-msl-rise>
			<canvas class="msl-gfund__canvas" data-msl-canvas="hero" aria-hidden="true"></canvas>

			<?php
			/*
			 * The way into the artwork itself. A real button and not the canvas:
			 * the canvas is `aria-hidden`, and a decorative surface that turns
			 * out to be the only door is a door a keyboard cannot find.
			 */
			?>
			<button type="button" class="msl-btn msl-btn--light msl-gfund__open" data-msl-goto="art"
				<?php msl_i18n( 'groups', 'single_open_art' ); ?>><?php msl_the( $msl_groups, 'single_open_art' ); ?></button>
		</div>

		<div class="msl-gfund__panel" data-msl-rise>
			<?php if ( '' !== $msl_ded ) : ?>
				<p class="msl-gfund__ded"><?php echo esc_html( $msl_ded ); ?></p>
			<?php endif; ?>

			<h1 class="msl-heading msl-gfund__title"><?php echo esc_html( (string) $msl_group['title'] ); ?></h1>

			<?php if ( '' !== trim( (string) $msl_group['owner_name'] ) ) : ?>
				<p class="msl-gfund__by">
					<span<?php msl_i18n( 'groups', 'single_opened_by' ); ?>><?php msl_the( $msl_groups, 'single_opened_by' ); ?></span>
					<strong><?php echo esc_html( (string) $msl_group['owner_name'] ); ?></strong>
				</p>
			<?php endif; ?>

			<p class="msl-gfund__raised">
				<span class="msl-gfund__big" data-msl-group-count><?php echo esc_html( msl_num( (int) $msl_group['count'] ) ); ?></span>
				<span class="msl-gfund__unit"<?php msl_i18n( 'groups', 'single_lights' ); ?>><?php msl_the( $msl_groups, 'single_lights' ); ?></span>
			</p>

			<div class="msl-gfund__track" role="progressbar" aria-valuemin="0" aria-valuemax="100"
				aria-valuenow="<?php echo esc_attr( (string) $msl_pct ); ?>"
				aria-label="<?php echo esc_attr( msl_t( $msl_groups, 'single_lights' ) ); ?>"
				data-msl-group-progress>
				<span class="msl-gfund__fill" style="width:<?php echo esc_attr( (string) $msl_pct ); ?>%"></span>
			</div>

			<dl class="msl-gfund__stats">
				<div class="msl-gfund__stat">
					<dt<?php msl_i18n( 'groups', 'single_pct' ); ?>><?php msl_the( $msl_groups, 'single_pct' ); ?></dt>
					<dd><span data-msl-group-pct><?php echo esc_html( msl_num( $msl_pct ) ); ?></span>%</dd>
				</div>
				<div class="msl-gfund__stat">
					<dt<?php msl_i18n( 'groups', 'single_target' ); ?>><?php msl_the( $msl_groups, 'single_target' ); ?></dt>
					<dd><?php echo esc_html( msl_num( (int) $msl_group['target'] ) ); ?></dd>
				</div>
			</dl>

			<div class="msl-gfund__actions">
				<?php if ( $msl_open ) : ?>
					<button type="button" class="msl-btn msl-btn--hero msl-gfund__join" data-msl-open-join
						<?php msl_i18n( 'groups', 'single_cta' ); ?>><?php msl_the( $msl_groups, 'single_cta' ); ?></button>
				<?php endif; ?>

				<a class="msl-btn msl-btn--whatsapp" data-msl-whatsapp
					data-msl-template="<?php echo esc_attr( msl_t( $msl_groups, 'wa_message' ) ); ?>"
					href="<?php echo esc_url( 'https://wa.me/?text=' . rawurlencode( $msl_wa ) ); ?>"
					target="_blank" rel="noopener"
					<?php msl_i18n( 'groups', 'single_share' ); ?>><?php msl_the( $msl_groups, 'single_share' ); ?></a>
			</div>

			<?php if ( $msl_wait ) : ?>
				<p class="msl-gfund__wait"<?php msl_i18n( 'groups', 'single_pending_join' ); ?>><?php msl_the( $msl_groups, 'single_pending_join' ); ?></p>
			<?php endif; ?>

			<p class="msl-gfund__also"<?php msl_i18n( 'groups', 'single_also' ); ?>><?php msl_the( $msl_groups, 'single_also' ); ?></p>
		</div>
	</div>

	<?php if ( '' !== trim( (string) $msl_group['story'] ) ) : ?>
		<section class="msl-gfund__story" data-msl-rise>
			<?php msl_paragraphs( (string) $msl_group['story'] ); ?>
		</section>
	<?php endif; ?>

	<?php
	/*
	 * Why the list looks the way it does, to the people who can change it.
	 * Printed whether the list is on or off, because "off" is one of the
	 * answers — and it is the one that cannot be told apart from "empty" by
	 * looking at the page.
	 */
	require MSL_DIR . 'template-parts/groups/people-notice.php';
	?>

	<?php if ( $msl_show_people ) : ?>
	<section class="msl-gfund__people" data-msl-rise aria-labelledby="msl-gfund-people">
		<h2 class="msl-gfund__peoplehead" id="msl-gfund-people"<?php msl_i18n( 'groups', 'single_supporters' ); ?>><?php msl_the( $msl_groups, 'single_supporters' ); ?></h2>

		<?php if ( array() === $msl_feed ) : ?>
			<p class="msl-gfund__empty"<?php msl_i18n( 'groups', 'single_first' ); ?>><?php msl_the( $msl_groups, 'single_first' ); ?></p>
		<?php else : ?>
			<ul class="msl-gfund__list">
				<?php foreach ( $msl_feed as $msl_person ) : ?>
					<li class="msl-gfund__person<?php echo $msl_person['anon'] ? ' msl-gfund__person--anon' : ''; ?>">
						<span class="msl-gfund__spark" aria-hidden="true"></span>

						<span class="msl-gfund__who">
							<?php if ( $msl_person['anon'] ) : ?>
								<span class="msl-gfund__name"<?php msl_i18n( 'groups', 'single_anon' ); ?>><?php msl_the( $msl_groups, 'single_anon' ); ?></span>
							<?php else : ?>
								<span class="msl-gfund__name"><?php echo esc_html( $msl_person['name'] ); ?></span>
							<?php endif; ?>

							<?php if ( '' !== $msl_person['city'] ) : ?>
								<span class="msl-gfund__city"><?php echo esc_html( $msl_person['city'] ); ?></span>
							<?php endif; ?>
						</span>

						<?php
						/*
						 * When. Written as a real <time> carrying the moment in
						 * UTC, so the browser can re-derive the sentence as the
						 * minutes pass and after the page has sat in a cache,
						 * and so a screen reader is handed a date rather than a
						 * phrase that has drifted.
						 */
						?>
						<?php if ( (int) $msl_person['when'] > 0 ) : ?>
							<time class="msl-gfund__when" data-msl-ago
								datetime="<?php echo esc_attr( gmdate( 'c', (int) $msl_person['when'] ) ); ?>"><?php
								echo esc_html( msl_ago_text( $msl_groups, time() - (int) $msl_person['when'] ) );
							?></time>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
	<?php endif; ?>

	<?php if ( $msl_owner ) : ?>
		<?php
		/*
		 * The owner's own view. It carries the private address in the page it is
		 * already on, so someone who reached their group from a bookmark can
		 * copy the link again without it ever appearing on the public page.
		 */
		?>
		<section class="msl-gowner" data-msl-rise>
			<p class="msl-gdone__label"<?php msl_i18n( 'groups', 'done_link' ); ?>><?php msl_the( $msl_groups, 'done_link' ); ?></p>
			<p class="msl-gdone__link"><a href="<?php echo esc_url( $msl_share ); ?>"><?php echo esc_html( (string) preg_replace( '#^https?://#', '', untrailingslashit( $msl_share ) ) ); ?></a></p>
		</section>
	<?php endif; ?>

	<?php
	/*
	 * The way out. It is the list of groups where there is one, and the
	 * campaign page where there is not — somebody who arrived here from a
	 * family message and read the whole page should be one press away from
	 * adding a light of their own, not at a dead end.
	 */
	?>
	<p class="msl-gfund__back">
		<?php if ( msl_groups_archive_on() ) : ?>
			<a href="<?php echo esc_url( MSL_Groups::page_url() ); ?>"<?php msl_i18n( 'groups', 'single_back' ); ?>><?php msl_the( $msl_groups, 'single_back' ); ?></a>
		<?php else : ?>
			<a href="<?php echo esc_url( msl_campaign_url() ); ?>"<?php msl_i18n( 'groups', 'back_home' ); ?>><?php msl_the( $msl_groups, 'back_home' ); ?></a>
		<?php endif; ?>
	</p>

	<?php if ( $msl_open ) : ?>
		<?php
		/*
		 * The one action, kept within thumb reach on a phone. On a page this
		 * long the button otherwise sits above a screenful of story and a list
		 * of names, and the moment somebody decides is the moment they are
		 * reading those names.
		 */
		?>
		<div class="msl-gfund__bar" data-msl-gfund-bar>
			<p class="msl-gfund__barfig">
				<span data-msl-group-count><?php echo esc_html( msl_num( (int) $msl_group['count'] ) ); ?></span>
				<span class="msl-gfund__barsep" aria-hidden="true">/</span>
				<span><?php echo esc_html( msl_num( (int) $msl_group['target'] ) ); ?></span>
			</p>

			<button type="button" class="msl-btn msl-btn--hero msl-gfund__barbtn" data-msl-open-join
				<?php msl_i18n( 'groups', 'single_cta' ); ?>><?php msl_the( $msl_groups, 'single_cta' ); ?></button>
		</div>
	<?php endif; ?>
</article>
