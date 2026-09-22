<?php
/**
 * Why the list of who has already lit one looks the way it does.
 *
 * Said only to somebody who can change it, and said on the page itself, because
 * this is the question that cannot be answered from the code: "I still do not
 * see the participants" has four possible causes and they look identical from
 * the outside — the switch is off, nobody has joined yet, the names were asked
 * to stay private, or a page cache is serving a copy made before any of this.
 *
 * So the page states what it actually found on this render: the switch as it
 * was read, how many rows came back, and how many names the campaign listed
 * itself. Two minutes of reading beats an afternoon of guessing at a server
 * nobody here can open.
 *
 * Printing it makes this render personal, so the page takes itself out of the
 * cache while it is here.
 *
 * @package Mashehu_LeShabbat
 *
 * @var array<string, mixed>            $msl_group       The group being shown.
 * @var bool                            $msl_show_people Whether the list is on.
 * @var array<int, array<string, mixed>> $msl_feed        What will be listed.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() || ! current_user_can( 'edit_pages' ) ) {
	return;
}

$msl_real   = MSL_Groups::count_for( (int) $msl_group['id'] );
$msl_listed = count( MSL_Groups::seed_people( $msl_group ) );

// Nothing to explain: the list is on and has something on it.
if ( $msl_show_people && array() !== $msl_feed ) {
	return;
}

defined( 'DONOTCACHEPAGE' ) || define( 'DONOTCACHEPAGE', true );
nocache_headers();

$msl_panel = admin_url( 'admin.php?page=msl-content&msl_page=' . MSL_Importer::page_id( 'groups' ) );
?>
<div class="msl-gstaff" role="status">
	<p class="msl-gstaff__title"><?php esc_html_e( 'רואים רק אתם — על רשימת המשתתפים', 'mashehu-leshabbat' ); ?></p>

	<?php if ( ! $msl_show_people ) : ?>
		<p class="msl-gstaff__line"><?php esc_html_e( 'הרשימה כבויה בהגדרות, ולכן היא אינה מוצגת לאף אחד — לא השמות ולא מתי הדליקו. המספר, פס ההתקדמות והיצירה ממשיכים כרגיל.', 'mashehu-leshabbat' ); ?></p>
		<p class="msl-gstaff__line"><a class="msl-gstaff__link" href="<?php echo esc_url( $msl_panel ); ?>"><?php esc_html_e( 'להדליק אותה: עריכת התוכן ← עמוד הקבוצות ← "הצגת רשימת המשתתפים בעמוד הקבוצה"', 'mashehu-leshabbat' ); ?></a></p>
	<?php else : ?>
		<p class="msl-gstaff__line">
			<?php
			printf(
				esc_html(
					/* translators: 1: joins on this group, 2: names the campaign listed itself. */
					__( 'הרשימה דלוקה, ואין לה מה להציג: לקבוצה הזאת יש %1$d הצטרפויות ו-%2$d שמות שהוקלדו בניהול.', 'mashehu-leshabbat' )
				),
				absint( $msl_real ),
				absint( $msl_listed )
			);
			?>
		</p>

		<?php if ( $msl_real > 0 ) : ?>
			<p class="msl-gstaff__line"><?php esc_html_e( 'ההצטרפויות קיימות אבל כולן ביקשו להישאר בלי שם, ושם אינו מוצג נגד בקשה. מי שמצטרף בלי לסמן "בעילום שם" יופיע כאן מיד.', 'mashehu-leshabbat' ); ?></p>
		<?php else : ?>
			<p class="msl-gstaff__line"><?php esc_html_e( 'אפשר להקליד שמות לקבוצה במסך "קבוצות" בניהול, כדי שקבוצה שרק נפתחה לא תיראה ריקה.', 'mashehu-leshabbat' ); ?></p>
		<?php endif; ?>
	<?php endif; ?>

	<p class="msl-gstaff__line"><?php esc_html_e( 'אם הרשימה מופיעה לכם כאן אבל לא בגלישה רגילה — זה מטמון עמודים שמגיש עותק ישן, וצריך לרוקן אותו.', 'mashehu-leshabbat' ); ?></p>
</div>
