<?php
/**
 * Why the archive is empty, said to the people who can do something about it.
 *
 * Only an editor sees this, and only when there is something to say. Printing
 * it means this one render is not the same for everybody, so the page takes
 * itself out of the cache while it is here — otherwise a page cache could
 * store the editor's copy and hand a moderation notice to a visitor.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() || ! current_user_can( 'edit_pages' ) ) {
	return;
}

$msl_census = MSL_Groups::census( MSL_Importer::page_id() );

/*
 * With the list switched off there is no "does not appear here" to explain —
 * approval then decides nothing a visitor can see, because nothing lists the
 * groups at all. The stranded-rows warning still stands, because that one is
 * about rows that are lost whatever the site shows.
 */
$msl_waiting = msl_groups_archive_on() ? $msl_census['pending'] : 0;

if ( 0 === $msl_waiting && 0 === $msl_census['elsewhere'] ) {
	return;
}

defined( 'DONOTCACHEPAGE' ) || define( 'DONOTCACHEPAGE', true );
nocache_headers();

$msl_admin = admin_url( 'admin.php?page=msl-groups&status=pending' );
?>
<div class="msl-gstaff" role="status">
	<p class="msl-gstaff__title"><?php esc_html_e( 'רואים רק אתם — הודעה לצוות האתר', 'mashehu-leshabbat' ); ?></p>

	<?php if ( $msl_waiting > 0 ) : ?>
		<p class="msl-gstaff__line">
			<?php
			printf(
				esc_html(
					/* translators: %d: number of groups awaiting approval. */
					_n(
						'קבוצה אחת ממתינה לאישור ולכן אינה מופיעה ברשימה כאן. מי שפתח אותה ומי שקיבל ממנו את הקישור כן רואים אותה ויכולים להצטרף.',
						'%d קבוצות ממתינות לאישור ולכן אינן מופיעות ברשימה כאן. מי שפתח אותן ומי שקיבל מהם את הקישור כן רואים אותן ויכולים להצטרף.',
						$msl_waiting,
						'mashehu-leshabbat'
					)
				),
				absint( $msl_waiting )
			);
			?>
		</p>
		<p class="msl-gstaff__line"><?php esc_html_e( 'כדי שיופיעו כאן צריך לאשר אותן. אפשר גם לבחור שכל קבוצה חדשה תעלה לאוויר מיד, בעריכת עמוד הקבוצות ← "קבוצות".', 'mashehu-leshabbat' ); ?></p>
		<p class="msl-gstaff__line"><a class="msl-gstaff__link" href="<?php echo esc_url( $msl_admin ); ?>"><?php esc_html_e( 'למסך אישור הקבוצות', 'mashehu-leshabbat' ); ?></a></p>
	<?php endif; ?>

	<?php if ( $msl_census['elsewhere'] > 0 ) : ?>
		<p class="msl-gstaff__line">
			<?php
			printf(
				esc_html(
					/* translators: %d: number of groups stored against another page. */
					__( '%d קבוצות שמורות תחת עמוד קמפיין אחר ולא ייראו כאן לעולם. זה קורה כשעמוד הקמפיין הוחלף אחרי שנפתחו. כדאי לפנות לתמיכה.', 'mashehu-leshabbat' )
				),
				absint( $msl_census['elsewhere'] )
			);
			?>
		</p>
	<?php endif; ?>
</div>
