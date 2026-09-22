<?php
/**
 * Managing one group, from inside the personal area.
 *
 * Ownership is checked here and nowhere else in this view: a code in the
 * address is a guess until the row says this person opened it.
 *
 * The fields are the ones that belong to whoever opened the group. Not the
 * code, not the opening count, not the status — those are the campaign's, and
 * a form that could reach them would be a way round every decision the
 * campaign has made about this group.
 *
 * @package Mashehu_LeShabbat
 *
 * @var array<string, mixed> $msl_account Resolved account content.
 * @var array<string, mixed> $msl_person  The signed-in person.
 * @var string               $msl_manage  Group code from the address.
 * @var bool                 $msl_saved   Whether the group was just saved.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

$msl_group = MSL_Groups::by_code( $msl_manage );

if ( null === $msl_group || (int) $msl_group['person_id'] !== (int) $msl_person['id'] ) {
	require MSL_DIR . 'template-parts/account/dashboard.php';

	return;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display of the redirect this page itself sent.
$msl_requeued = isset( $_GET['msl_requeued'] );

$msl_copy   = MSL_Meta::get( 'groups', MSL_Importer::page_id( 'groups' ) );
?>
<header class="msl-ahead" data-msl-rise>
	<h1 class="msl-heading"<?php msl_i18n( 'account', 'manage_title' ); ?>><?php msl_the( $msl_account, 'manage_title' ); ?></h1>

	<p class="msl-ahead__out">
		<a href="<?php echo esc_url( MSL_Account::page_url() ); ?>"<?php msl_i18n( 'account', 'manage_back' ); ?>><?php msl_the( $msl_account, 'manage_back' ); ?></a>
	</p>
</header>

<?php if ( $msl_requeued ) : ?>
	<p class="msl-gnote" role="status"<?php msl_i18n( 'account', 'manage_requeued' ); ?>><?php msl_the( $msl_account, 'manage_requeued' ); ?></p>
<?php elseif ( $msl_saved ) : ?>
	<p class="msl-gnote" role="status"<?php msl_i18n( 'account', 'manage_saved' ); ?>><?php msl_the( $msl_account, 'manage_saved' ); ?></p>
<?php endif; ?>

<div class="msl-agrid msl-agrid--one">
	<section class="msl-acard" data-msl-rise>
		<form class="msl-aform" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="msl_group_owner_save">
			<input type="hidden" name="code" value="<?php echo esc_attr( (string) $msl_group['code'] ); ?>">
			<?php wp_nonce_field( 'msl_group_owner_save' ); ?>

			<p class="msl-field">
				<label class="msl-field__label" for="msl-m-title"<?php msl_i18n( 'groups', 'f_title' ); ?>><?php msl_the( $msl_copy, 'f_title' ); ?></label>
				<input type="text" class="msl-input" id="msl-m-title" name="title" maxlength="120" required
					value="<?php echo esc_attr( (string) $msl_group['title'] ); ?>">
			</p>

			<div class="msl-gform__row">
				<p class="msl-field">
					<label class="msl-field__label" for="msl-m-occasion"<?php msl_i18n( 'groups', 'f_occasion' ); ?>><?php msl_the( $msl_copy, 'f_occasion' ); ?></label>
					<select class="msl-input" id="msl-m-occasion" name="occasion">
						<option value="0"<?php selected( 0, (int) $msl_group['occasion'] ); ?>><?php msl_the( $msl_copy, 'occ_none' ); ?></option>
						<?php foreach ( MSL_Groups::OCCASIONS as $msl_index => $msl_key ) : ?>
							<option value="<?php echo esc_attr( (string) $msl_index ); ?>"<?php selected( $msl_index, (int) $msl_group['occasion'] ); ?>>
								<?php msl_the( $msl_copy, 'occ_' . $msl_key ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>

				<p class="msl-field">
					<label class="msl-field__label" for="msl-m-honouree"<?php msl_i18n( 'groups', 'f_honouree' ); ?>><?php msl_the( $msl_copy, 'f_honouree' ); ?></label>
					<input type="text" class="msl-input" id="msl-m-honouree" name="honouree" maxlength="120"
						value="<?php echo esc_attr( (string) $msl_group['honouree'] ); ?>">
				</p>
			</div>

			<p class="msl-field">
				<label class="msl-field__label" for="msl-m-story"<?php msl_i18n( 'groups', 'f_story' ); ?>><?php msl_the( $msl_copy, 'f_story' ); ?></label>
				<textarea class="msl-input" id="msl-m-story" name="story" rows="4"
					maxlength="<?php echo absint( MSL_Groups::MAX_STORY ); ?>"><?php echo esc_textarea( (string) $msl_group['story'] ); ?></textarea>
			</p>

			<div class="msl-gform__row">
				<p class="msl-field">
					<label class="msl-field__label" for="msl-m-target"<?php msl_i18n( 'groups', 'f_target' ); ?>><?php msl_the( $msl_copy, 'f_target' ); ?></label>
					<input type="number" class="msl-input" id="msl-m-target" name="target"
						min="<?php echo absint( MSL_Groups::MIN_TARGET ); ?>" max="<?php echo absint( MSL_Groups::MAX_TARGET ); ?>"
						value="<?php echo absint( $msl_group['target'] ); ?>">
				</p>

			</div>

			<?php
			// The same picker the public form uses.
			$msl_chosen = (string) $msl_group['artwork'];
			$msl_id     = 'msl-m-art';
			require MSL_DIR . 'template-parts/groups/artpick.php';
			?>

			<button type="submit" class="msl-btn msl-btn--hero msl-btn--wide"<?php msl_i18n( 'account', 'manage_save' ); ?>><?php msl_the( $msl_account, 'manage_save' ); ?></button>
		</form>
	</section>
</div>
