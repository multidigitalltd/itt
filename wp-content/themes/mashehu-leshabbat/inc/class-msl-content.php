<?php
/**
 * The approved copy from the design handoff.
 *
 * This is the seed written into a page's own meta when the page is created, and
 * the fallback for any field a page has not saved — including fields added by a
 * later theme update. It is not read at render time once a page has content of
 * its own.
 *
 * Every string here comes from the v3 desktop reference and its dictionary.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Default content, keyed by section.
 */
final class MSL_Content {

	/**
	 * Cached copy.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private static ?array $content = null;

	/**
	 * Option holding the copy revision a site has been brought up to.
	 */
	private const REVISION_OPTION = 'msl_copy_revision';

	/**
	 * The current copy revision.
	 */
	private const REVISION = 3;

	/**
	 * The display pace this theme ships with.
	 */
	public const DEMO_RATE = 150;

	/**
	 * Hook the one-time copy migration.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'maybe_migrate' ), 98 );
	}

	/**
	 * Bring stored copy up to the current revision, once per site.
	 *
	 * A default that changes reaches a page that has never been saved for free,
	 * because unsaved keys fall through to this file. A page that *has* been
	 * saved holds every key, so it keeps the old wording for ever — and when
	 * the change is not cosmetic that is a page left quietly broken by an
	 * update it accepted.
	 *
	 * Revision 1 took the word "parashat" out of the four countdown sentences,
	 * because the name that fills them now carries it and some weeks have no
	 * portion to put there at all. A page still holding the old sentence would
	 * read "Shabbat Parashat Parashat Mishpatim", and through Tishrei "Shabbat
	 * Parashat Sukkot I".
	 *
	 * Only a value that still matches the old default exactly is rewritten.
	 * Anything the campaign has edited is theirs, and is left alone even though
	 * that means it keeps the doubled word; overwriting somebody's own sentence
	 * to fix our own is the worse of the two.
	 */
	public static function maybe_migrate(): void {
		$done = (int) get_option( self::REVISION_OPTION, 0 );

		if ( $done >= self::REVISION ) {
			return;
		}

		if ( $done < 1 ) {
			self::retire_parashat_wording();
		}

		if ( $done < 2 ) {
			self::retire_silent_pace();
		}

		if ( $done < 3 ) {
			self::add_personal_area_to_menus();
		}

		update_option( self::REVISION_OPTION, self::REVISION, false );
	}

	/**
	 * Revision 1 — the doubled word in the countdown.
	 */
	private static function retire_parashat_wording(): void {
		$replacements = array(
			'countdown_days_he'  => array( 'השבת פרשת %1$s בעוד %2$d ימים', 'השבת %1$s בעוד %2$d ימים' ),
			'countdown_days_en'  => array( 'Shabbat Parashat %1$s in %2$d days', 'Shabbat %1$s in %2$d days' ),
			'countdown_day_he'   => array( 'השבת פרשת %s בעוד יום', 'השבת %s בעוד יום' ),
			'countdown_day_en'   => array( 'Shabbat Parashat %s tomorrow', 'Shabbat %s tomorrow' ),
			'countdown_2days_he' => array( 'השבת פרשת %s בעוד יומיים', 'השבת %s בעוד יומיים' ),
			'countdown_2days_en' => array( 'Shabbat Parashat %s in two days', 'Shabbat %s in two days' ),
			// countdown_clock is not here: the field was retired in the same
			// release, replaced by hours and minutes. A page still holding the
			// old value simply holds a key nothing reads, and the next save
			// drops it, because sanitisation keeps only what the schema names.
		);

		$key = MSL_Meta::key( 'chrome' );

		$pages = get_posts(
			array(
				'post_type'        => 'page',
				'post_status'      => 'any',
				'numberposts'      => 200,
				'fields'           => 'ids',
				'meta_key'         => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'suppress_filters' => false,
			)
		);

		foreach ( $pages as $page_id ) {
			$stored = get_post_meta( (int) $page_id, $key, true );

			if ( ! is_array( $stored ) ) {
				continue;
			}

			$changed = false;

			foreach ( $replacements as $field => $pair ) {
				if ( isset( $stored[ $field ] ) && $pair[0] === $stored[ $field ] ) {
					$stored[ $field ] = $pair[1];
					$changed          = true;
				}
			}

			if ( $changed ) {
				update_post_meta( (int) $page_id, $key, $stored );
			}
		}
	}

	/**
	 * Revision 2 — the display pace that shipped switched off.
	 *
	 * The pace arrived in 1.21.0 defaulting to zero, on the reasoning that a
	 * display figure should be asked for rather than assumed. That was the
	 * wrong call: the campaign that asked for it opened the page, read "0
	 * people joined in the last ten minutes", and had no way to know that the
	 * fix was a field in section 01. A number whose whole purpose is to stop
	 * the page reading as broken has to arrive working.
	 *
	 * Only a stored zero is rewritten, and only once. A zero can only have been
	 * written by a page saved during the few hours that release was current,
	 * where it is this file's old default and not a decision; and a campaign
	 * that switches the pace off after this runs keeps it off, because the
	 * revision has already been recorded.
	 */
	private static function retire_silent_pace(): void {
		$key = MSL_Meta::key( 'campaign' );

		$pages = get_posts(
			array(
				'post_type'        => 'page',
				'post_status'      => 'any',
				'numberposts'      => 200,
				'fields'           => 'ids',
				'meta_key'         => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'suppress_filters' => false,
			)
		);

		foreach ( $pages as $page_id ) {
			$stored = get_post_meta( (int) $page_id, $key, true );

			if ( ! is_array( $stored ) || ! isset( $stored['demo_rate'] ) ) {
				continue;
			}

			if ( 0 !== (int) $stored['demo_rate'] ) {
				continue;
			}

			$stored['demo_rate'] = self::DEMO_RATE;

			update_post_meta( (int) $page_id, $key, $stored );
		}
	}

	/**
	 * Revision 3 — the personal area is missing from menus saved before it existed.
	 *
	 * A menu is a list, and a list that a page has saved is kept exactly as it
	 * was saved: new default items reach a page that never had its menu edited
	 * and nobody else. So a site set up before the personal area existed has a
	 * personal area with no way in except typing its address.
	 *
	 * The item is appended, not inserted, and only where nothing already points
	 * at that page — somebody who removed it on purpose and then edits the menu
	 * again keeps their decision, because this runs once.
	 */
	private static function add_personal_area_to_menus(): void {
		$key = MSL_Meta::key( 'nav' );

		$pages = get_posts(
			array(
				'post_type'        => 'page',
				'post_status'      => 'any',
				'numberposts'      => 200,
				'fields'           => 'ids',
				'meta_key'         => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'suppress_filters' => false,
			)
		);

		foreach ( $pages as $page_id ) {
			$stored = get_post_meta( (int) $page_id, $key, true );

			if ( ! is_array( $stored ) || ! isset( $stored['links'] ) || ! is_array( $stored['links'] ) ) {
				continue;
			}

			foreach ( $stored['links'] as $link ) {
				if ( is_array( $link ) && 'account' === ( $link['target'] ?? '' ) ) {
					continue 2;
				}
			}

			$stored['links'][] = array(
				'label_he' => 'האיזור האישי',
				'label_en' => 'My area',
				'target'   => 'account',
				'url'      => '',
			);

			update_post_meta( (int) $page_id, $key, $stored );
		}
	}

	/**
	 * Defaults for one section.
	 *
	 * @param string $section Section key.
	 * @return array<string, mixed>
	 */
	public static function section( string $section ): array {
		self::$content ??= self::build();

		return self::$content[ $section ] ?? array();
	}

	/**
	 * Build the copy.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function build(): array {
		return array(
			'chrome'   => array(
				'brand_he'          => 'אור לשבת',
				'brand_en'          => 'Light for Shabbat',
				'logo'              => 0,
				'cta_he'            => 'גם אני מוסיף אור',
				'cta_en'            => 'I’m adding my light too',
				'lang_btn_he'       => 'EN',
				'lang_btn_en'       => 'עב',
				'countdown_days_he' => 'השבת %1$s בעוד %2$d ימים',
				'countdown_days_en' => 'Shabbat %1$s in %2$d days',
				'countdown_day_he'  => 'השבת %s בעוד יום',
				'countdown_day_en'  => 'Shabbat %s tomorrow',
				'countdown_2days_he' => 'השבת %s בעוד יומיים',
				'countdown_2days_en' => 'Shabbat %s in two days',
				'countdown_hours_he' => 'השבת %1$s בעוד %2$d שעות',
				'countdown_hours_en' => 'Shabbat %1$s in %2$d hours',
				'countdown_2hours_he' => 'השבת %s בעוד שעתיים',
				'countdown_2hours_en' => 'Shabbat %s in two hours',
				'countdown_hour_he'  => 'השבת %s בעוד שעה',
				'countdown_hour_en'  => 'Shabbat %s in an hour',
				'countdown_minutes_he' => 'השבת %1$s בעוד %2$d דקות',
				'countdown_minutes_en' => 'Shabbat %1$s in %2$d minutes',
				'countdown_minute_he' => 'השבת %s עוד רגע',
				'countdown_minute_en' => 'Shabbat %s any minute now',
				'credit_text'       => 'uxui & dev by multi digital',
				'credit_url'        => 'https://m-d.co.il/',
				'accessibility_url' => '',
				'terms_url'         => '',
				'privacy_url'       => '',
			),
			'campaign' => array(
				'parsha_he'        => 'משפטים',
				'parsha_en'        => 'Mishpatim',
				'target'           => 172000,
				'seed_count'       => 127438,
				'demo_rate'        => self::DEMO_RATE,
				'demo_from'        => '',
				'artwork'          => 'rotate',
				'accent'           => '#FFB25C',
				'candle_day'       => '5',
				'candle_time'      => '19:12',
				'countries'        => 42,
				'cities'           => 386,
				'dedications_seed' => 8600,
				'closed'           => 0,
			),
			'zmanim'   => array(
				'auto'              => 1,
				'place'             => '281184',
				'place_id'          => 0,
				'havdalah'          => 0,
				'show'              => 1,
				'pick_on'           => 1,
				'title_he'          => 'זמני השבת',
				'title_en'          => 'Shabbat times',
				'label_hdate_he'    => 'התאריך העברי',
				'label_hdate_en'    => 'Hebrew date',
				'label_parsha_he'   => 'פרשת השבוע',
				'label_parsha_en'   => 'Weekly portion',
				'label_holiday_he'  => 'שבת של חג',
				'label_holiday_en'  => 'Festival Shabbat',
				'label_candles_he'  => 'כניסת השבת',
				'label_candles_en'  => 'Candle lighting',
				'label_havdalah_he' => 'צאת השבת',
				'label_havdalah_en' => 'Havdalah',
				'note_he'           => 'הזמנים לפי %s, ומתעדכנים מדי שבוע.',
				'note_en'           => 'Times for %s, updated every week.',
				'pick_cta_he'       => 'שינוי מיקום',
				'pick_cta_en'       => 'Change location',
				'pick_label_he'     => 'בחירת עיר',
				'pick_label_en'     => 'Choose a city',
				'pick_apply_he'     => 'הצגת הזמנים',
				'pick_apply_en'     => 'Show the times',
				'pick_il_he'        => 'בארץ',
				'pick_il_en'        => 'Israel',
				'pick_world_he'     => 'בעולם',
				'pick_world_en'     => 'Around the world',
			),
			'hero'     => array(
				'eyebrow_he' => 'יצירה אחת. כל השבוע. כל העולם.',
				'eyebrow_en' => 'One artwork. Every week. The whole world.',
				'h1a_he'     => 'כל אחד מוסיף אור קטן.',
				'h1a_en'     => 'Everyone adds a little light.',
				'h1b_he'     => 'ביחד יוצרים אור גדול.',
				'h1b_en'     => 'Together we make a great light.',
				'sub_he'     => 'השבת, אלפי יהודים בארץ ובעולם בוחרים להוסיף אור אחד לכבוד השבת. מה האור שלך?',
				'sub_en'     => 'This Shabbat, thousands of Jews in Israel and around the world are choosing one light to add. What is your light?',
				'already_he' => 'כבר בפנים',
				'already_en' => 'already in',
				'light_count' => 14,
			),
			'stage'    => array(
				'art_title_he'       => 'היצירה של השבת הזאת',
				'art_title_en'       => 'This Shabbat’s artwork',
				'candles_now_he'     => 'נרות דולקים עכשיו',
				'candles_now_en'     => 'candles lit right now',
				'completed_short_he' => 'הושלמה',
				'completed_short_en' => 'complete',
				'enter_art_he'       => 'להיכנס לתוך היצירה',
				'enter_art_en'       => 'Step inside the artwork',
				'wall_he'            => 'קיר הנרות',
				'wall_en'            => 'The candle wall',
				'wall_count_he'      => 'נרות דולקים לשבת הזאת',
				'wall_count_en'      => 'candles lit for this Shabbat',
				'demo_names'          => 1,
				'demo_first_names_he' => "שרה\nדוד\nרחל\nמשה\nאסתר\nיוסף\nמרים\nאברהם\nחנה\nיעקב\nלאה\nשמואל\nרבקה\nאליהו\nנעמי\nיצחק\nתמר\nדניאל\nמיכל\nנתן\nאיילת\nעמית\nשירה\nיונתן\nנועה\nאורי\nטליה\nגלעד\nיעל\nעידו",
				'demo_first_names_en' => "Sarah\nDavid\nRachel\nMoshe\nEsther\nYosef\nMiriam\nAvraham\nHannah\nYaakov\nLeah\nShmuel\nRivka\nEliyahu\nNaomi\nYitzhak\nTamar\nDaniel\nMichal\nNatan\nAyelet\nAmit\nShira\nYonatan\nNoa\nOri\nTalia\nGilad\nYael\nIdo",
				'demo_cities_he'      => "ירושלים\nתל אביב\nחיפה\nבאר שבע\nנתניה\nאשדוד\nרעננה\nמודיעין\nצפת\nטבריה\nרחובות\nכפר סבא\nהרצליה\nבית שמש\nעפולה\nאילת\nניו יורק\nלונדון\nפריז\nטורונטו\nמלבורן\nבואנוס איירס\nיוהנסבורג\nמוסקבה",
				'demo_cities_en'      => "Jerusalem\nTel Aviv\nHaifa\nBeer Sheva\nNetanya\nAshdod\nRaanana\nModiin\nTzfat\nTiberias\nRehovot\nKfar Saba\nHerzliya\nBeit Shemesh\nAfula\nEilat\nNew York\nLondon\nParis\nToronto\nMelbourne\nBuenos Aires\nJohannesburg\nMoscow",
			),
			'marquee'  => array(
				'rows' => array(
					array(
						'text_he' => 'דוד · ירושלים · קידוש',
						'text_en' => 'David · Jerusalem · Kiddush',
					),
					array(
						'text_he' => 'רחל · ניו יורק · סעודת שבת',
						'text_en' => 'Rachel · New York · Shabbat dinner',
					),
					array(
						'text_he' => 'יונתן · תל אביב · זמן עם המשפחה',
						'text_en' => 'Yonatan · Tel Aviv · Time with family',
					),
					array(
						'text_he' => 'משפחה מלונדון · הכנסת אורחים',
						'text_en' => 'A family from London · Hosting guests',
					),
					array(
						'text_he' => 'שרה · אנטוורפן · הדלקת נרות',
						'text_en' => 'Sarah · Antwerp · Lighting candles',
					),
					array(
						'text_he' => 'מיכאל · מיאמי · סעודת שבת',
						'text_en' => 'Michael · Miami · Shabbat dinner',
					),
					array(
						'text_he' => 'אנונימי · פריז · מעשה חסד',
						'text_en' => 'Anonymous · Paris · An act of kindness',
					),
					array(
						'text_he' => 'נועה · בואנוס איירס · לימוד',
						'text_en' => 'Noa · Buenos Aires · Learning',
					),
					array(
						'text_he' => 'אבי · בני ברק · הכנה לכבוד שבת',
						'text_en' => 'Avi · Bnei Brak · Preparing for Shabbat',
					),
					array(
						'text_he' => 'ארי · טורונטו · הדלקת נרות',
						'text_en' => 'Ari · Toronto · Lighting candles',
					),
					array(
						'text_he' => 'רבקה · מלבורן · זמן עם המשפחה',
						'text_en' => 'Rivka · Melbourne · Time with family',
					),
					array(
						'text_he' => 'יוסף · לוס אנג׳לס · קידוש',
						'text_en' => 'Yosef · Los Angeles · Kiddush',
					),
				),
			),
			'stats'    => array(
				'title_he'            => 'השבת הזאת',
				'title_en'            => 'This Shabbat',
				'sub_he'              => 'הכוח המשותף של כולנו, מתעדכן בזמן אמת.',
				'sub_en'              => 'Our shared strength, updating in real time.',
				'stat_people_he'      => 'אנשים הוסיפו אור',
				'stat_people_en'      => 'people added light',
				'stat_countries_he'   => 'מדינות',
				'stat_countries_en'   => 'countries',
				'stat_cities_he'      => 'ערים',
				'stat_cities_en'      => 'cities',
				'stat_dedications_he' => 'הקדשות',
				'stat_dedications_en' => 'dedications',
				'completed_label_he'  => 'מהיצירה של השבת הזאת כבר הושלמה',
				'completed_label_en'  => 'of this Shabbat’s artwork is complete',
				'last10_he'           => 'ב-10 הדקות האחרונות הצטרפו %d אנשים.',
				'last10_en'           => '%d people joined in the last 10 minutes.',
			),
			'navcards' => array(
				'art_card_he'     => 'היצירה של השבת',
				'art_card_en'     => 'This week’s artwork',
				'art_card_sub_he' => 'תתקרב וגלה שכל חלקיק הוא נר של אדם אחד — ושל מי הוא.',
				'art_card_sub_en' => 'Zoom in and see that every particle is one person’s candle — and whose it is.',
				'wall_line1_he'   => 'כל נר הוא אדם אחד.',
				'wall_line1_en'   => 'Every candle is one person.',
				'wall_line2_he'   => 'דולקים עכשיו.',
				'wall_line2_en'   => 'lit right now.',
			),
			'map'      => array(
				'title_he'   => 'השבת מחברת עולם שלם.',
				'title_en'   => 'Shabbat connects a whole world.',
				'sub_he'     => 'יהודים מ-%d מדינות כבר הוסיפו אור לשבת הקרובה. כל נקודת אור על המפה היא מקום שממנו מישהו הצטרף.',
				'sub_en'     => 'Jews from %d countries have already added their light for this Shabbat. Every point of light is a place someone joined from.',
				'summary_he' => 'מפת עולם שעליה מסומנות הערים שמהן הצטרפו משתתפים, ובהן ירושלים, תל אביב, ניו יורק, לונדון, פריז, אנטוורפן, מיאמי, לוס אנג׳לס, טורונטו, בואנוס איירס ומלבורן.',
				'summary_en' => 'A world map marking the cities participants joined from, among them Jerusalem, Tel Aviv, New York, London, Paris, Antwerp, Miami, Los Angeles, Toronto, Buenos Aires and Melbourne.',
				'points'     => self::map_points(),
			),
			'referral' => array(
				'title_he'      => 'תביא את מי שאתה אוהב.',
				'title_en'      => 'Bring the people you love.',
				'sub_he'        => 'לכל אחד יש קישור אישי. כל מי שמצטרף דרכו מוסיף נר ליצירה — ואתה רואה בדיוק כמה אור הבאת.',
				'sub_en'        => 'Everyone gets a personal link. Everyone who joins through it adds a candle — and you see exactly how much light you brought.',
				'your_link_he'  => 'הקישור האישי שלך',
				'your_link_en'  => 'Your personal link',
				'wa_send_he'    => 'שליחה ב-WhatsApp',
				'wa_send_en'    => 'Send on WhatsApp',
				'wa_message_he' => 'הוספתי אור לשבת הקרובה. מה האור שלך? %s',
				'wa_message_en' => 'I added my light for this Shabbat. What is your light? %s',
				'share_more_he' => 'שיתוף נוסף',
				'share_more_en' => 'More ways to share',
				'copy_btn_he'   => 'העתקת קישור',
				'copy_btn_en'   => 'Copy link',
				'copied_btn_he' => 'הועתק ✓',
				'copied_btn_en' => 'Copied ✓',
				'ref_label_he'  => 'אנשים הצטרפו בזכותך לשבת הזאת',
				'ref_label_en'  => 'people joined this Shabbat because of you',
				'next_goal_he'  => 'היעד הבא: %d אנשים',
				'next_goal_en'  => 'Next milestone: %d people',
				'friends_he'    => 'חברים',
				'friends_en'    => 'friends',
				'milestones'    => array(
					array( 'value' => 5 ),
					array( 'value' => 10 ),
					array( 'value' => 50 ),
					array( 'value' => 100 ),
				),
			),
			'closing'  => array(
				'title_he'           => 'מה האור שלך לשבת הקרובה?',
				'title_en'           => 'What is your light for this Shabbat?',
				'urgency_soon_he'    => 'נותרו עוד %d שעות להצטרף ליצירה של השבת הזאת.',
				'urgency_soon_en'    => '%d hours left to join this Shabbat’s artwork.',
				'urgency_default_he' => 'כשהשבת נכנסת, היצירה נסגרת ונשמרת בארכיון. מיד אחריה מתחילה היצירה של השבת הבאה.',
				'urgency_default_en' => 'When Shabbat begins the artwork closes and is saved to the archive. Right after it, next Shabbat’s artwork starts.',
				'closed_note_he'     => 'השבת נכנסה והיצירה נסגרה. היצירה של השבת הבאה תיפתח מיד אחרי צאת השבת.',
				'closed_note_en'     => 'Shabbat has begun and the artwork is closed. Next Shabbat’s artwork opens right after Shabbat ends.',
			),
			'join'     => array(
				'pick_title_he'      => 'אז... מה האור שלך לשבת?',
				'pick_title_en'      => 'So… what is your light?',
				'pick_sub_he'        => 'אפשר לבחור עד שלושה. כל אור נחשב.',
				'pick_sub_en'        => 'Choose up to three. Every light counts.',
				'options'            => self::options(),
				'other_ph_he'        => 'מה האור שלך?',
				'other_ph_en'        => 'What is your light?',
				'pick_cta_he'        => 'זה האור שלי',
				'pick_cta_en'        => 'That’s my light',
				'ded_title_he'       => 'רוצה להקדיש את זה למישהו?',
				'ded_title_en'       => 'Want to dedicate it to someone?',
				'ded_sub_he'         => 'אופציונלי לגמרי.',
				'ded_sub_en'         => 'Completely optional.',
				'ded_types'          => array(
					array(
						'label_he' => 'לרפואה',
						'label_en' => 'For healing',
					),
					array(
						'label_he' => 'להצלחה',
						'label_en' => 'For success',
					),
					array(
						'label_he' => 'לעילוי נשמת',
						'label_en' => 'In memory of',
					),
					array(
						'label_he' => 'לזכות',
						'label_en' => 'In the merit of',
					),
					array(
						'label_he' => 'הקדשה אישית',
						'label_en' => 'A personal dedication',
					),
				),
				'ded_ph_he'          => 'שם או משפט קצר',
				'ded_ph_en'          => 'A name or a short line',
				'ded_field_label_he' => 'ההקדשה שלך',
				'ded_field_label_en' => 'Your dedication',
				'ded_note_he'        => 'ההקדשות עוברות בדיקה לפני הצגה באתר.',
				'ded_note_en'        => 'Dedications are reviewed before they appear on the site.',
				'ded_cta_he'         => 'ממשיכים',
				'ded_cta_en'         => 'Continue',
				'skip_he'            => 'לדלג',
				'skip_en'            => 'Skip',
				'det_title_he'       => 'מאיפה מגיע האור שלך?',
				'det_title_en'       => 'Where does your light come from?',
				'det_sub_he'         => 'רק כדי לשים את הנקודה שלך על המפה.',
				'det_sub_en'         => 'Just so we can place your point on the map.',
				'det_optional_he'    => '',
				'det_optional_en'    => '',
				'ph_name_he'         => 'שם פרטי',
				'ph_name_en'         => 'First name',
				'ph_city_he'         => 'עיר',
				'ph_city_en'         => 'City',
				'ph_country_he'      => 'מדינה',
				'ph_country_en'      => 'Country',
				'remind_title_he'    => 'רוצים תזכורת לפני שבת?',
				'remind_title_en'    => 'Want a reminder before Shabbat?',
				'remind_sub_he'      => 'השאירו טלפון או מייל ונשלח תזכורת אחת לפני כניסת השבת. לא חובה.',
				'remind_sub_en'      => 'Leave a phone or email and we’ll send one reminder before Shabbat begins. Not required.',
				'ph_phone_he'        => 'טלפון (לא חובה)',
				'ph_phone_en'        => 'Phone (optional)',
				'ph_email_he'        => 'אימייל (לא חובה)',
				'ph_email_en'        => 'Email (optional)',
				'anon_label_he'      => 'להציג אותי בעילום שם',
				'anon_label_en'      => 'Show me anonymously',
				'privacy_note_he'    => '',
				'privacy_note_en'    => '',
				'submit_cta_he'      => 'אני מצטרף',
				'submit_cta_en'      => 'I’m joining',
				'step_label_he'      => 'שלב %1$d מתוך %2$d',
				'step_label_en'      => 'Step %1$d of %2$d',
				'err_email_he'       => 'נא למלא כתובת אימייל תקינה.',
				'err_email_en'       => 'Please enter a valid email address.',
				'err_phone_he'       => 'נא למלא מספר טלפון תקין.',
				'err_phone_en'       => 'Please enter a valid phone number.',
				'err_generic_he'     => 'השליחה נכשלה. אפשר לנסות שוב בעוד רגע.',
				'err_generic_en'     => 'Something went wrong. Please try again in a moment.',
				'err_duplicate_he'   => 'כבר הוספת אור לשבת הזאת.',
				'err_duplicate_en'   => 'You have already added your light for this Shabbat.',
				'err_rate_he'        => 'התקבלו יותר מדי בקשות מהחיבור הזה. אפשר לנסות שוב עוד שעה.',
				'err_rate_en'        => 'Too many requests from this connection. Please try again in an hour.',
				'err_closed_he'      => 'השבת נכנסה והיצירה של השבוע נסגרה.',
				'err_closed_en'      => 'Shabbat has begun and this week’s artwork is closed.',
				'sending_he'         => 'שולח…',
				'sending_en'         => 'Sending…',
			),
			'groupcta'          => array(
				'show'          => 1,
				'eyebrow_he'    => 'קבוצות לשבת',
				'eyebrow_en'    => 'Shabbat groups',
				'title_he'      => 'לפתוח קבוצה לכבוד מישהו',
				'title_en'      => 'Open a group in someone’s honour',
				'lead_he'       => 'אפשר לפתוח קבוצה משלכם, לקבוע יעד של קבלות ולאסוף אותן מהמשפחה ומהחברים. לקבוצה יש יצירה משלה, עמוד משלה וכתובת לשיתוף.',
				'lead_en'       => 'Open a group of your own, set a target of acceptances and gather them from family and friends. A group has its own artwork, its own page and its own link to share.',
				'chips_label_he' => 'לכבוד מה',
				'chips_label_en' => 'What it is for',
				'chip_refua_he' => 'לרפואה',
				'chip_refua_en' => 'A recovery',
				'chip_zechut_he' => 'לזכות',
				'chip_zechut_en' => 'A merit',
				'chip_iluy_he'  => 'לעילוי נשמה',
				'chip_iluy_en'  => 'A memory',
				'chip_kavod_he' => 'לכבוד שמחה',
				'chip_kavod_en' => 'A celebration',
				'chip_zivug_he' => 'לזיווג',
				'chip_zivug_en' => 'A match',
				'note_he'       => 'כל נר שנדלק בקבוצה נספר גם ביצירה הכללית.',
				'note_en'       => 'Every candle lit in a group counts in the main artwork too.',
				'cta_he'        => 'לפתיחת קבוצה',
				'cta_en'        => 'Open a group',
				'cta_all_he'    => 'לכל הקבוצות',
				'cta_all_en'    => 'See all the groups',
			),
			'verses'            => array(
				'title_he' => 'על מעלת השבת',
				'title_en' => 'In praise of Shabbat',
				'sub_he'   => 'מה שנאמר עליה לפנינו — בתורה, בנביאים, ובדברי חכמים שחיו ממנה.',
				'sub_en'   => 'What was said of it before us — in the Torah, the Prophets, and the words of the sages who lived by it.',
				'quotes'   => array(
					array(
						'text_he'   => 'וַיְבָרֶךְ אֱלֹהִים אֶת־יוֹם הַשְּׁבִיעִי וַיְקַדֵּשׁ אֹתוֹ',
						'text_en'   => 'And God blessed the seventh day and made it holy',
						'source_he' => 'בראשית ב׳, ג׳',
						'source_en' => 'Genesis 2:3',
					),
					array(
						'text_he'   => 'זָכוֹר אֶת־יוֹם הַשַּׁבָּת לְקַדְּשׁוֹ',
						'text_en'   => 'Remember the Sabbath day, to keep it holy',
						'source_he' => 'שמות כ׳, ח׳',
						'source_en' => 'Exodus 20:8',
					),
					array(
						'text_he'   => 'וְקָרָאתָ לַשַּׁבָּת עֹנֶג, לִקְדוֹשׁ ה׳ מְכֻבָּד',
						'text_en'   => 'And you shall call the Sabbath a delight, the holy day of the Lord honoured',
						'source_he' => 'ישעיהו נ״ח, י״ג',
						'source_en' => 'Isaiah 58:13',
					),
					array(
						'text_he'   => 'מתנה טובה יש לי בבית גנזי, ושבת שמה',
						'text_en'   => 'I have a precious gift in My treasury, and its name is Shabbat',
						'source_he' => 'תלמוד בבלי, שבת י׳ ע״ב',
						'source_en' => 'Talmud Bavli, Shabbat 10b',
					),
					array(
						'text_he'   => 'אלמלי משמרין ישראל שתי שבתות כהלכתן — מיד נגאלין',
						'text_en'   => 'Were Israel to keep two Shabbatot properly, they would be redeemed at once',
						'source_he' => 'תלמוד בבלי, שבת קי״ח ע״ב',
						'source_en' => 'Talmud Bavli, Shabbat 118b',
					),
					array(
						'text_he'   => 'יותר משישראל שמרו את השבת — שמרה השבת אותם',
						'text_en'   => 'More than the Jewish people have kept Shabbat, Shabbat has kept them',
						'source_he' => 'אחד העם',
						'source_en' => 'Ahad Ha’am',
					),
				),
			),
			'nav'               => array(
				'links'          => array(
					array( 'label_he' => 'היצירה', 'label_en' => 'The artwork', 'target' => 'stage', 'url' => '' ),
					array( 'label_he' => 'זמני השבת', 'label_en' => 'Shabbat times', 'target' => 'zmanim', 'url' => '' ),
					array( 'label_he' => 'השבת הזאת', 'label_en' => 'This Shabbat', 'target' => 'stats', 'url' => '' ),
					array( 'label_he' => 'המפה', 'label_en' => 'The map', 'target' => 'map', 'url' => '' ),
					array( 'label_he' => 'הקישור האישי שלי', 'label_en' => 'My personal link', 'target' => 'invite', 'url' => '' ),
					array( 'label_he' => 'קבוצות', 'label_en' => 'Groups', 'target' => 'groups', 'url' => '' ),
					array( 'label_he' => 'האיזור האישי', 'label_en' => 'My area', 'target' => 'account', 'url' => '' ),
					array( 'label_he' => 'על המיזם', 'label_en' => 'About', 'target' => 'about', 'url' => '' ),
				),
				'menu_open_he'   => 'תפריט',
				'menu_open_en'   => 'Menu',
				'menu_close_he'  => 'סגירת התפריט',
				'menu_close_en'  => 'Close menu',
			),
			'account'           => array(
				'open_on'        => 1,
				'eyebrow_he'     => 'האיזור האישי',
				'eyebrow_en'     => 'Your area',
				'title_he'       => 'האיזור האישי שלי',
				'title_en'       => 'My personal area',
				'lead_he'        => 'כאן נמצאים הקישור האישי שלכם, הקבוצות שפתחתם, וכל מה שצריך כדי לנהל אותן.',
				'lead_en'        => 'Your personal link, the groups you opened, and everything needed to look after them.',

				'stale_note_he'  => 'נראה שהעמוד הזה הוגש מזיכרון מטמון, ולכן הוא מציג מסך כניסה למרות שאתם מחוברים. ניסינו לטעון אותו מחדש. אם זה חוזר — יש להחריג את עמוד האיזור האישי ממטמון העמודים של האתר, או לנקות את המטמון.',
				'stale_note_en'  => 'This page looks like it came from a cache, which is why it is showing a sign-in screen although you are signed in. We tried loading it again. If it keeps happening, exclude the personal area page from the site’s page cache, or clear the cache.',
				'login_title_he' => 'כניסה',
				'login_title_en' => 'Sign in',
				'f_email_he'     => 'כתובת מייל',
				'f_email_en'     => 'Email address',
				'f_password_he'  => 'סיסמה',
				'f_password_en'  => 'Password',
				'login_cta_he'   => 'כניסה',
				'login_cta_en'   => 'Sign in',
				'forgot_cta_he'  => 'שכחתי סיסמה',
				'forgot_cta_en'  => 'I forgot my password',

				'register_title_he' => 'פתיחת חשבון',
				'register_title_en' => 'Open an account',
				'register_lead_he'  => 'חשבון נפתח בכתובת מייל ובסיסמה, ומאפשר לפתוח קבוצה, לנהל אותה ולעקוב אחרי הקישור האישי.',
				'register_lead_en'  => 'An account takes an address and a password, and lets you open a group, look after it and follow your personal link.',
				'f_name_he'         => 'השם שלי',
				'f_name_en'         => 'My name',
				'f_password_new_he' => 'סיסמה חדשה',
				'f_password_new_en' => 'New password',
				'password_help_he'  => 'לפחות שמונה תווים.',
				'password_help_en'  => 'At least eight characters.',
				'register_cta_he'   => 'פתיחת חשבון',
				'register_cta_en'   => 'Open an account',

				'forgot_title_he' => 'שחזור סיסמה',
				'forgot_title_en' => 'Reset your password',
				'forgot_lead_he'  => 'נשלח קישור לקביעת סיסמה חדשה. הקישור תקף לשעתיים.',
				'forgot_lead_en'  => 'We will send a link for setting a new password. It is good for two hours.',
				'forgot_send_he'  => 'שליחת הקישור',
				'forgot_send_en'  => 'Send the link',
				'forgot_sent_he'  => 'אם יש חשבון בכתובת הזאת, נשלח אליה קישור לקביעת סיסמה חדשה.',
				'forgot_sent_en'  => 'If there is an account at that address, a link for setting a new password is on its way to it.',
				'mail_subject_he' => 'קביעת סיסמה חדשה — אור לשבת',
				'mail_subject_en' => 'Set a new password — Or LeShabbat',
				'mail_body_he'    => "לקביעת סיסמה חדשה יש להיכנס לקישור הזה:\n\n%s\n\nהקישור תקף לשעתיים. אם לא ביקשתם אותו, אפשר להתעלם מההודעה — הסיסמה הקיימת נשארת כפי שהיא.",
				'mail_body_en'    => "To set a new password, open this link:\n\n%s\n\nIt is good for two hours. If you did not ask for it you can ignore this message — your existing password stays as it is.",

				'reset_title_he' => 'קביעת סיסמה חדשה',
				'reset_title_en' => 'Set a new password',
				'reset_cta_he'   => 'שמירת הסיסמה',
				'reset_cta_en'   => 'Save the password',

				'hello_he'      => 'שלום %s',
				'hello_en'      => 'Hello %s',
				'signout_he'    => 'התנתקות',
				'signout_en'    => 'Sign out',
				'link_title_he' => 'הקישור האישי שלי',
				'link_title_en' => 'My personal link',
				'link_lead_he'  => 'כל מי שמצטרף דרך הקישור הזה נזקף לזכותכם.',
				'link_lead_en'  => 'Everyone who joins through this link is counted to you.',
				'link_count_he' => 'הצטרפו דרך הקישור שלי',
				'link_count_en' => 'joined through my link',
				'copy_cta_he'   => 'העתקת הקישור',
				'copy_cta_en'   => 'Copy the link',
				'copied_he'     => 'הועתק',
				'copied_en'     => 'Copied',

				'groups_title_he'   => 'הקבוצות שלי',
				'groups_title_en'   => 'My groups',
				'groups_empty_he'   => 'עוד לא פתחתם קבוצה. אפשר לפתוח אחת עכשיו.',
				'groups_empty_en'   => 'You have not opened a group yet. You can open one now.',
				'groups_open_he'    => 'פתיחת קבוצה',
				'groups_open_en'    => 'Open a group',
				'group_manage_he'   => 'ניהול',
				'group_manage_en'   => 'Manage',
				'group_view_he'     => 'לעמוד הקבוצה',
				'group_view_en'     => 'Open the group page',
				'group_progress_he' => '%1$s מתוך %2$s נרות',
				'group_progress_en' => '%1$s of %2$s candles',
				'manage_title_he'   => 'ניהול הקבוצה',
				'manage_title_en'   => 'Manage the group',
				'manage_save_he'    => 'שמירה',
				'manage_save_en'    => 'Save',
				'manage_saved_he'   => 'השינויים נשמרו.',
				'manage_saved_en'   => 'Your changes are saved.',
				'manage_requeued_he' => 'השינויים נשמרו. מכיוון שהטקסט שונה, הקבוצה חוזרת לאישור — הקישור ממשיך לעבוד וההצטרפויות נספרות כרגיל.',
				'manage_requeued_en' => 'Your changes are saved. Because the text changed, the group goes back for approval — the link keeps working and joins are counted as usual.',
				'manage_back_he'    => 'חזרה לאיזור האישי',
				'manage_back_en'    => 'Back to my area',

				'password_title_he'      => 'החלפת סיסמה',
				'password_title_en'      => 'Change your password',
				'f_password_current_he'  => 'הסיסמה הנוכחית',
				'f_password_current_en'  => 'Current password',
				'password_cta_he'        => 'החלפת הסיסמה',
				'password_cta_en'        => 'Change the password',
				'password_saved_he'      => 'הסיסמה הוחלפה. כל מכשיר אחר שהיה מחובר נותק.',
				'password_saved_en'      => 'Your password is changed. Every other device that was signed in has been signed out.',

				'err_generic_he'     => 'משהו השתבש. אפשר לנסות שוב.',
				'err_generic_en'     => 'Something went wrong. Please try again.',
				'err_email_he'       => 'כתובת המייל אינה תקינה.',
				'err_email_en'       => 'That email address is not valid.',
				'err_short_he'       => 'הסיסמה קצרה מדי — לפחות שמונה תווים.',
				'err_short_en'       => 'That password is too short — at least eight characters.',
				'err_taken_he'       => 'כבר יש חשבון בכתובת הזאת. אפשר להתחבר, או לשחזר סיסמה.',
				'err_taken_en'       => 'There is already an account at that address. You can sign in, or reset the password.',
				'err_credentials_he' => 'הכתובת או הסיסמה אינן נכונות.',
				'err_credentials_en' => 'That address or password is not right.',
				'err_rate_he'        => 'היו יותר מדי ניסיונות. אפשר לנסות שוב בעוד רבע שעה.',
				'err_rate_en'        => 'Too many attempts. Please try again in a quarter of an hour.',
				'err_token_he'       => 'הקישור לשחזור פג או שאינו תקין. אפשר לבקש קישור חדש.',
				'err_token_en'       => 'That reset link has expired or is not valid. You can ask for a new one.',
			),
			'groups'            => array(
				'open_on'          => 1,
				'auto_approve'     => 0,
				'show_archive'     => 0,
				'show_people'      => 0,
				'eyebrow_he'       => 'קבוצות לשבת',
				'eyebrow_en'       => 'Shabbat groups',
				'title_he'         => 'לפתוח קבוצה, ולהדליק ביחד',
				'title_en'         => 'Open a group, and light it together',
				'lead_he'          => 'אפשר לפתוח קבוצה משלכם — לרפואת מישהו, לזכותו, לעילוי נשמה או לכבוד שמחה — לקבוע יעד של קבלות, ולאסוף אותן מהמשפחה ומהחברים. לקבוצה יש עמוד משלה ויצירה משלה, וכל אור שנדלק בה נכנס גם ליצירה הגדולה של כולם.',
				'lead_en'          => 'You can open a group of your own — for someone’s recovery, in their merit, in their memory or in honour of a celebration — set a target of acceptances, and gather them from family and friends. A group has its own page and its own artwork, and every light lit in it joins the great artwork of everyone.',
				'open_cta_he'      => 'פתיחת קבוצה',
				'open_cta_en'      => 'Open a group',
				'archive_title_he' => 'הקבוצות שנפתחו',
				'archive_title_en' => 'The groups that were opened',
				'empty_he'         => 'עוד לא נפתחה קבוצה. אתם מוזמנים להיות הראשונים.',
				'empty_en'         => 'No group has been opened yet. You are welcome to be the first.',
				'card_progress_he' => '%1$s מתוך %2$s',
				'card_progress_en' => '%1$s of %2$s',
				'card_cta_he'      => 'לקבוצה',
				'card_cta_en'      => 'Open',

				'form_title_he'    => 'פתיחת קבוצה',
				'form_title_en'    => 'Open a group',
				'form_lead_he'     => 'כמה שורות, ויש לכם עמוד לשלוח למשפחה. אפשר למלא רק את שם הקבוצה ולהמשיך — כל השאר לא חובה.',
				'form_lead_en'     => 'A few lines, and you have a page to send to the family. You can fill in only the group’s name and carry on — everything else is optional.',
				'f_title_he'       => 'שם הקבוצה',
				'f_title_en'       => 'The group’s name',
				'f_title_help_he'  => 'למשל: משפחת כהן, חברות של נועה, שכונת רמות.',
				'f_title_help_en'  => 'For example: the Cohen family, Noa’s friends, the Ramot neighbourhood.',
				'f_occasion_he'    => 'לכבוד מה',
				'f_occasion_en'    => 'The occasion',
				'f_honouree_he'    => 'שם האדם',
				'f_honouree_en'    => 'The person’s name',
				'f_story_he'       => 'כמה מילים לקבוצה',
				'f_story_en'       => 'A few words for the group',
				'f_target_he'      => 'כמה נרות אתם מכוונים אליהם',
				'f_target_en'      => 'How many candles are you aiming for',
				'f_target_help_he' => 'אפשר לשנות אחר כך. יעד קטן שמושג שווה יותר מיעד גדול שנשאר פתוח.',
				'f_target_help_en' => 'You can change it later. A small target reached is worth more than a large one left open.',
				'f_artwork_he'     => 'צורת היצירה של הקבוצה',
				'f_artwork_en'     => 'The group’s artwork',
				'f_owner_he'       => 'השם שלכם',
				'f_owner_en'       => 'Your name',
				'f_email_he'       => 'המייל שלכם',
				'f_email_en'       => 'Your email',
				'f_email_help_he'  => 'רק כדי שנוכל לשלוח לכם את הקישור לניהול הקבוצה. לא מוצג לאף אחד.',
				'f_email_help_en'  => 'Only so we can send you the link that manages the group. It is shown to nobody.',
				'submit_he'        => 'פתיחת הקבוצה',
				'submit_en'        => 'Open the group',

				'occ_none_he'      => 'בלי הקדשה',
				'occ_none_en'      => 'No dedication',
				'occ_refua_he'     => 'לרפואת',
				'occ_refua_en'     => 'For the recovery of',
				'occ_zechut_he'    => 'לזכות',
				'occ_zechut_en'    => 'In the merit of',
				'occ_iluy_he'      => 'לעילוי נשמת',
				'occ_iluy_en'      => 'In memory of',
				'occ_kavod_he'     => 'לכבוד',
				'occ_kavod_en'     => 'In honour of',
				'occ_zivug_he'     => 'לזיווג',
				'occ_zivug_en'     => 'For a match for',

				'done_title_he'    => 'הקבוצה נפתחה',
				'done_title_en'    => 'The group is open',
				'done_body_he'     => 'זה הקישור לשלוח למשפחה ולחברים. כל מי שנכנס דרכו ומדליק נר — נספר לקבוצה שלכם, וגם ליצירה של כולם.',
				'done_body_en'     => 'This is the link to send to family and friends. Everyone who comes through it and lights a candle counts for your group, and for everyone’s artwork too.',
				'done_pending_he'  => 'הקבוצה תופיע ברשימה הציבורית אחרי אישור קצר. הקישור כבר עובד, ואפשר לשלוח אותו עכשיו.',
				'done_pending_en'  => 'The group will appear in the public list after a short review. The link already works, and you can send it now.',
				'done_link_he'     => 'הקישור לקבוצה',
				'done_link_en'     => 'The group’s link',
				'done_manage_he'   => 'הקישור הפרטי שלכם לניהול — כדאי לשמור אותו',
				'done_manage_en'   => 'Your private management link — keep it',

				'single_lights_he' => 'נרות בקבוצה',
				'single_lights_en' => 'candles in the group',
				'single_pct_he'    => 'מהיעד',
				'single_pct_en'    => 'of the goal',
				'single_anon_he'   => 'בעילום שם',
				'single_anon_en'   => 'Anonymous',
				'single_supporters_he' => 'מי כבר הדליק',
				'single_supporters_en' => 'Who has already lit one',
				'single_first_he'  => 'עוד אף אחד לא הדליק כאן נר. אפשר להיות הראשונים.',
				'single_first_en'  => 'Nobody has lit a candle here yet. You can be the first.',
				'single_pending_join_he' => 'הקבוצה ממתינה לאישור הטקסט שבה, אבל אפשר להצטרף כבר עכשיו — כל נר שנדלק נספר.',
				'single_pending_join_en' => 'The group is waiting for its text to be approved, but you can join right now — every candle lit is counted.',
				'single_target_he' => 'היעד',
				'single_target_en' => 'The target',
				'single_cta_he'    => 'להדליק נר לקבוצה',
				'single_cta_en'    => 'Light a candle for the group',
				'single_share_he'  => 'שיתוף הקבוצה',
				'single_share_en'  => 'Share the group',
				'single_opened_by_he' => 'נפתחה על ידי',
				'single_opened_by_en' => 'Opened by',
				'single_also_he'   => 'כל נר שנדלק כאן נספר גם ליצירה הגדולה של כל המשתתפים.',
				'single_also_en'   => 'Every candle lit here counts for the great artwork of all the participants too.',
				'back_home_he'     => 'חזרה לעמוד הקמפיין',
				'back_home_en'     => 'Back to the campaign',
				'single_back_he'   => 'לכל הקבוצות',
				'single_back_en'   => 'All the groups',
				'wa_message_he'    => 'פתחנו קבוצה לכבוד שבת. מצטרפים? %s',
				'wa_message_en'    => 'We opened a Shabbat group. Will you join? %s',

				'state_pending_he'  => 'הקבוצה ממתינה לאישור ועדיין אינה מופיעה ברשימה הציבורית.',
				'state_pending_en'  => 'This group is awaiting review and does not appear in the public list yet.',
				'state_closed_he'   => 'הקבוצה נסגרה. הנרות שנדלקו בה נשארו ביצירה.',
				'state_closed_en'   => 'This group is closed. The candles lit in it remain in the artwork.',
				'state_rejected_he' => 'הקבוצה לא אושרה לפרסום.',
				'state_rejected_en' => 'This group was not approved for publication.',

				'err_generic_he'   => 'משהו השתבש. אפשר לנסות שוב.',
				'err_generic_en'   => 'Something went wrong. Please try again.',
				'err_title_he'     => 'צריך שם לקבוצה — רק כדי שאפשר יהיה לזהות אותה.',
				'err_title_en'     => 'The group needs a name — just so it can be told apart.',
				'err_email_he'     => 'כתובת המייל לא נראית תקינה.',
				'err_email_en'     => 'That email address does not look right.',
				'err_rate_he'      => 'נפתחו כבר כמה קבוצות מהמחשב הזה היום. אפשר לנסות מחר.',
				'err_rate_en'      => 'A few groups have already been opened from this device today. Please try tomorrow.',
				'err_closed_he'    => 'פתיחת קבוצות חדשות סגורה כרגע.',
				'err_closed_en'    => 'Opening new groups is closed at the moment.',
			),
			'privacy'           => array(
				'eyebrow_he'       => 'מדיניות פרטיות',
				'eyebrow_en'       => 'Privacy policy',
				'title_he'         => 'מה נשמר כאן, ומה לא',
				'title_en'         => 'What is kept here, and what is not',
				'lead_he'          => 'האתר הזה מבקש מכם מעט מאוד, ושומר עוד פחות. הדף הזה מפרט בדיוק מה נשמר כשמצטרפים, מה נמחק או מוצפן מיד, ומה אף פעם לא נאסף.',
				'lead_en'          => 'This site asks very little of you, and keeps less. This page sets out exactly what is stored when you join, what is hashed or encrypted at once, and what is never collected at all.',
				'updated_he'       => 'עודכן לאחרונה: ספטמבר 2026',
				'updated_en'       => 'Last updated: September 2026',
				'blocks'           => array(
					array(
						'title_he' => 'מה נשמר כשמצטרפים',
						'title_en' => 'What is stored when you join',
						'body_he'  => 'כשמוסיפים אור נשמרים: השם הפרטי שהוקלד (או "אנונימי", אם בחרתם כך), העיר והמדינה, הדברים שבחרתם לעשות לכבוד השבת, שפת האתר שבה הצטרפתם, והתאריך והשעה.

אם כתבתם הקדשה — היא נשמרת כפי שנכתבה, ומוצגת באתר רק אחרי שאדם מהצוות קרא אותה ואישר.

העיר שהוקלדה מתורגמת לנקודה על המפה מתוך רשימת ערים שהוגדרה מראש באתר. האתר אינו מבקש ואינו מקבל את המיקום של המכשיר שלכם, והנקודות שמוצגות על המפה מעוגלות ומאוחדות כך שאי אפשר להגיע מהן לאדם מסוים.',
						'body_en'  => 'When you add a light we store: the first name you typed (or “anonymous”, if you chose that), the city and country, the things you chose to do in honour of Shabbat, the site language you joined in, and the date and time.

If you wrote a dedication, it is stored as written, and appears on the site only after a member of the team has read and approved it.

The city you type is turned into a point on the map from a list of cities configured in advance on the site. The site does not ask for and does not receive your device’s location, and the points shown on the map are rounded and grouped so that no individual can be reached through them.',
					),
					array(
						'title_he' => 'טלפון, דוא"ל וכתובת IP — נשמרים כטביעה בלבד',
						'title_en' => 'Phone, email and IP address — kept only as a fingerprint',
						'body_he'  => 'אם השארתם טלפון או דוא"ל, ואת כתובת ה-IP שממנה הגעתם, האתר אינו שומר בשום מקום בצורה קריאה. הוא שומר טביעה חד-כיוונית שלהם (hash עם מלח): מחרוזת שאפשר להשוות אליה, ואי אפשר לחזור ממנה אל הפרט המקורי.

הטביעות משמשות לשני דברים בלבד: לזהות שאותו אדם כבר הצטרף, ולמנוע הצפה אוטומטית של הטופס. הן חסרות ערך לכל שימוש אחר, וגם למי שיגיע אליהן.',
						'body_en'  => 'If you left a phone number or an email address, and the IP address you arrived from, the site does not store any of them in readable form. It stores a one-way, salted fingerprint: a string that can be compared against, and that cannot be turned back into the original detail.

These fingerprints are used for two things only: to recognise that the same person has already joined, and to stop the form being flooded automatically. They are worthless for anything else, including to anyone who reaches them.',
					),
					array(
						'title_he' => 'תזכורת לפני שבת',
						'title_en' => 'A reminder before Shabbat',
						'body_he'  => 'מי שביקש תזכורת לפני השבת — ורק מי שביקש — משאיר כתובת דוא"ל שנשמרת מוצפנת, ומשמשת אך ורק לשליחת אותה תזכורת. היא אינה נמכרת, אינה מושכרת ואינה מועברת לאף גורם.

אפשר לבקש הסרה בכל רגע בפנייה לכתובת שבתחתית הדף, והכתובת נמחקת.',
						'body_en'  => 'Anyone who asked for a reminder before Shabbat — and only those who asked — leaves an email address, which is stored encrypted and used solely to send that reminder. It is not sold, rented or passed to anyone.

You can ask to be removed at any moment by writing to the address at the foot of this page, and the address is deleted.',
					),
					array(
						'title_he' => 'קבוצות',
						'title_en' => 'Groups',
						'body_he'  => 'מי שפותח קבוצה מקליד את שם הקבוצה, את שם האדם שלכבודו היא נפתחה ואת הטקסט שלה. הטקסט הזה מוצג בעמוד ציבורי, ולכן הוא נקרא ומאושר לפני הפרסום — ולכן גם כדאי לא לכתוב בו פרטים רפואיים או אישיים שאינכם רוצים שיהיו גלויים.

מי שמצטרף לקבוצה נספר בה, והצגת שמות המצטרפים בעמוד הקבוצה היא הגדרה של האתר שאפשר לכבות ולהדליק.',
						'body_en'  => 'Whoever opens a group types the group’s name, the name of the person it was opened for, and its text. That text appears on a public page, so it is read and approved before it is published — and for the same reason it is better not to write medical or personal details in it that you would not want to be visible.

Anyone who joins a group is counted in it, and whether the names of those who joined are shown on the group’s page is a site setting that can be switched on or off.',
					),
					array(
						'title_he' => 'עוגיות',
						'title_en' => 'Cookies',
						'body_he'  => 'האתר שומר במכשיר שלכם כמה עוגיות משלו בלבד: השפה שבחרתם, קוד ההזמנה של מי ששיתף אתכם, סימון של הנר שלכם כדי שנוכל להראות לכם אותו כשתחזרו, ואם התחברתם לאיזור האישי — עוגיית התחברות חתומה.

אין באתר עוגיות של פרסום, של רשתות חברתיות או של מדידת התנהגות, ואין בו כלי אנליטיקה כלל.',
						'body_en'  => 'The site stores a few cookies of its own on your device and nothing else: the language you chose, the invitation code of whoever shared it with you, a marker for your own candle so we can show it to you when you return, and, if you signed in to the personal area, a signed sign-in cookie.

There are no advertising, social-network or behavioural cookies on this site, and no analytics tools of any kind.',
					),
					array(
						'title_he' => 'מה לא יוצא מהאתר',
						'title_en' => 'What never leaves the site',
						'body_he'  => 'הדפדפן שלכם אינו פונה לשום דומיין חיצוני בזמן הגלישה כאן: הגופנים, הגרפיקה והמפה מוגשים מהאתר עצמו.

לשרת עצמו יש שתי פניות החוצה, ואף אחת מהן אינה כוללת פרט שלכם: זמני כניסת השבת נמשכים מ-hebcal.com, ומי שבוחר להתחבר עם חשבון גוגל — פנייה לגוגל מתבצעת בשבילו, לבקשתו, כדי לאמת את הכתובת.',
						'body_en'  => 'Your browser does not contact any external domain while you are here: the fonts, the graphics and the map are all served from this site itself.

The server makes two outbound calls, and neither carries any detail of yours: Shabbat candle-lighting times are fetched from hebcal.com, and for anyone who chooses to sign in with a Google account, a call is made to Google on their behalf, at their request, to verify the address.',
					),
					array(
						'title_he' => 'מי רואה, וכמה זמן זה נשמר',
						'title_en' => 'Who sees it, and how long it is kept',
						'body_he'  => 'את הנתונים רואים רק בעלי ההרשאות שמנהלים את האתר. המידע אינו נמכר, אינו מושכר ואינו מועבר לצד שלישי.

הצטרפויות נשמרות כל עוד המיזם פעיל, כי הן הנרות שביצירה ואי אפשר למחוק אותן בלי למחוק אור שמישהו הדליק. פרט אישי — כתובת לתזכורת, שם, הקדשה — נמחק לבקשתכם.',
						'body_en'  => 'Only the people with permissions who run the site see the data. It is not sold, not rented and not passed to any third party.

Joins are kept for as long as the project runs, because they are the candles in the artwork and they cannot be deleted without deleting a light somebody lit. A personal detail — a reminder address, a name, a dedication — is deleted at your request.',
					),
					array(
						'title_he' => 'הזכויות שלכם',
						'title_en' => 'Your rights',
						'body_he'  => 'אתם רשאים לדעת איזה מידע נשמר עליכם, לתקן אותו, ולבקש שיימחק. פנייה אחת לכתובת שלמטה מספיקה, ונטפל בה.',
						'body_en'  => 'You are entitled to know what information is held about you, to correct it, and to ask for it to be deleted. One message to the address below is enough, and we will deal with it.',
					),
					array(
						'title_he' => 'שינויים במדיניות',
						'title_en' => 'Changes to this policy',
						'body_he'  => 'אם המדיניות תשתנה, הנוסח המעודכן יופיע בדף הזה ותאריך העדכון שבראשו ישתנה בהתאם.',
						'body_en'  => 'If this policy changes, the updated text will appear on this page and the date at the top of it will change accordingly.',
					),
				),
				'contact_title_he' => 'יצירת קשר',
				'contact_title_en' => 'Contact',
				'contact_lead_he'  => 'לכל שאלה בנושא פרטיות, ולכל בקשה לעיין במידע, לתקן אותו או למחוק אותו:',
				'contact_lead_en'  => 'For any question about privacy, and for any request to see, correct or delete information:',
				'contact_name'     => 'רבקה',
				'contact_email'    => 'motionklik@gmail.com',
				'cta_he'           => 'חזרה לעמוד הקמפיין',
				'cta_en'           => 'Back to the campaign',
			),
			'about'             => array(
				'eyebrow_he'      => 'על המיזם',
				'eyebrow_en'      => 'About the project',
				'title_he'        => 'שבת אחת, עם אחד',
				'title_en'        => 'One Shabbat, one people',
				'lead_he'         => 'המיזם הזה קם כדי לאחד את עם ישראל סביב השבת. לא סביב דעה, לא סביב זרם ולא סביב מה שמפריד — סביב היום האחד שהוא של כולנו, ושכל אחד ואחת יכולים להביא אליו משהו משלהם.',
				'lead_en'         => 'This project was founded to bring the people of Israel together around Shabbat. Not around an opinion, not around a movement, and not around anything that divides — around the one day that belongs to all of us, and that each of us can bring something of our own to.',
				'verse_he'        => 'וְשָׁמְרוּ בְנֵי־יִשְׂרָאֵל אֶת־הַשַּׁבָּת, לַעֲשׂוֹת אֶת־הַשַּׁבָּת לְדֹרֹתָם בְּרִית עוֹלָם',
				'verse_en'        => 'And the children of Israel shall keep the Shabbat, to make the Shabbat throughout their generations an everlasting covenant',
				'verse_source_he' => 'שמות ל״א, ט״ז',
				'verse_source_en' => 'Exodus 31:16',
				'blocks'          => array(
					array(
						'title_he' => 'השבת היא מקור הברכה',
						'title_en' => 'Shabbat is the source of blessing',
						'body_he'  => 'חז״ל לימדו שכל הברכה שבימות החול נובעת מן השבת — שממנה מתברכים ששת הימים שאחריה. לכן זה לא עוד יום בשבוע ולא סתם מנוחה: זו הנקודה שממנה הכול מתחיל.

וזו גם הסיבה שהמיזם הזה נבנה סביבה דווקא. מה שמחזיק עם שלם לאורך אלפי שנים אינו הדבר שמחלק אותו, אלא הדבר שממנו כולו ניזון.',
						'body_en'  => 'Our sages taught that all the blessing of the working week flows from Shabbat — that the six days which follow are blessed from it. So it is not just another day in the week, and not merely rest: it is the point from which everything begins.

That is also why this project was built around it. What holds a whole people together across thousands of years is not what divides it, but what all of it is nourished by.',
					),
					array(
						'title_he' => 'ובזכות השבת תבוא הגאולה',
						'title_en' => 'And through Shabbat the redemption will come',
						'body_he'  => 'אמרו חכמים: אלמלי משמרין ישראל שתי שבתות כהלכתן — מיד נגאלין. לא מעשה גדול אחד, לא זכות של יחידים: שבת אחת, ביחד.

מכאן הרעיון הפשוט של האתר. כל אדם מוסיף דבר אחד שהוא עושה לכבוד השבת — נר, קידוש, שיחה עם מישהו, ברכה, שעה של שקט — והדבר הזה נהיה נר אחד ביצירה של השבת הזאת. אף אחד לא נמדד מול אף אחד. כל אור נחשב באותה מידה.',
						'body_en'  => 'Our sages said: if Israel were to keep two Shabbatot properly, they would be redeemed at once. Not one great deed, and not the merit of a few: one Shabbat, together.

That is where the simple idea of this site comes from. Each person adds one thing they do in honour of Shabbat — a candle, kiddush, a conversation with someone, a blessing, an hour of quiet — and that thing becomes one candle in this Shabbat’s artwork. Nobody is measured against anybody. Every light counts the same.',
					),
					array(
						'title_he' => 'כל עם ישראל, מכל הסוגים',
						'title_en' => 'All of Israel, of every kind',
						'body_he'  => 'דתיים וחילונים, מסורתיים וחרדים, בארץ ובעולם, מי ששומר שבת כל חייו ומי שמדליק נר בפעם הראשונה — כולם נכנסים לאותה יצירה, ואי אפשר להבדיל ביניהם בתוכה. זה לא ויתור על ההבדלים; זה פשוט לא המקום שבהם.

אנחנו מאמינים שכך זה עובד באמת: לא בוויכוח על מי צודק, אלא באור אחד שמצטרף לאור אחר, עד שיש כאן הרבה יותר ממה שכל אחד מאיתנו יכול היה להדליק לבד.',
						'body_en'  => 'Religious and secular, traditional and haredi, in Israel and around the world, someone who has kept Shabbat all their life and someone lighting a candle for the first time — all of them enter the same artwork, and inside it they cannot be told apart. This is not giving up the differences; it is simply not the place for them.

We believe this is how it actually works: not by arguing over who is right, but by one light joining another, until there is far more here than any of us could have lit alone.',
					),
				),
				'cta_he'          => 'להוסיף את האור שלי',
				'cta_en'          => 'Add my light',
				'back_he'         => 'חזרה לעמוד הבית',
				'back_en'         => 'Back to the home page',
			),
			'auth'              => array(
				'login_enabled'        => 0,
				'google_client_id'     => '',
				'google_client_secret' => '',
				'sign_in_he'           => 'התחברות',
				'sign_in_en'           => 'Sign in',
				'sign_out_he'          => 'התנתקות',
				'sign_out_en'          => 'Sign out',
				'area_cta_he'   => 'האיזור האישי',
				'area_cta_en'   => 'My area',
				'signed_in_as_he'      => 'מחובר/ת בתור',
				'signed_in_as_en'      => 'Signed in as',
				'link_locked_he'       => 'הקישור האישי שלך מחכה לך — התחבר/י כדי לקבל אותו.',
				'link_locked_en'       => 'Your personal link is waiting — sign in to get it.',
				'link_join_he'         => 'הקישור האישי מחכה לך — מי שמוסיף אור מקבל קישור משלו, וכל מי שמצטרף דרכו נזקף לזכותו.',
				'link_join_en'         => 'Your personal link is waiting — add your light and you get a link of your own, with everyone who joins through it credited to you.',
				'link_join_cta_he'     => 'להוסיף אור ולקבל קישור',
				'link_join_cta_en'     => 'Add your light and get a link',
				'link_locked_cta_he'   => 'התחברות עם גוגל',
				'link_locked_cta_en'   => 'Sign in with Google',
				'err_state_he'         => 'ההתחברות לא הושלמה. אפשר לנסות שוב.',
				'err_state_en'         => 'Sign-in did not complete. Please try again.',
				'invite_title_he'      => 'אור אחד מדליק אור נוסף',
				'invite_title_en'      => 'One light kindles another',
				'invite_body_he'       => 'יש לך קישור אישי משלך. כל מי שמצטרף דרכו מוסיף נר ליצירה שנזקף לזכותך — וכך, מאדם לאדם, נדלקים כאן הרבה יותר אורות לשבת הזאת. שווה לשלוח אותו לחמישה אנשים שאכפת לך מהם.',
				'invite_body_en'       => 'You have a personal link of your own. Everyone who joins through it adds a candle to the artwork credited to you — and that is how, person by person, far more light is lit here for this Shabbat. Worth sending to five people you care about.',
				'invite_cta_he'        => 'לקישור האישי שלי',
				'invite_cta_en'        => 'Get my personal link',
				'invite_dismiss_he'    => 'לא עכשיו',
				'invite_dismiss_en'    => 'Not now',
				'remind_title_he'      => 'רגע לפני שאת/ה הולך/ת',
				'remind_title_en'      => 'One moment before you go',
				'remind_body_he'       => 'שבת מגיעה מהר, וקל לשכוח. נשלח לך תזכורת אחת קצרה לפני כניסת השבת — עם האור שבחרת, כדי שיחכה לך.',
				'remind_body_en'       => 'Shabbat comes round quickly, and it is easy to forget. We will send one short reminder before it begins — with the light you chose, waiting for you.',
				'remind_with_he'       => 'האור שבחרת:',
				'remind_with_en'       => 'The light you chose:',
				'remind_name_he'       => 'שם (לא חובה)',
				'remind_name_en'       => 'Name (optional)',
				'remind_email_he'      => 'כתובת מייל',
				'remind_email_en'      => 'Email address',
				'remind_cta_he'        => 'שלחו לי תזכורת',
				'remind_cta_en'        => 'Send me a reminder',
				'remind_dismiss_he'    => 'לא, תודה',
				'remind_dismiss_en'    => 'No thanks',
				'remind_done_he'       => 'נרשמת. נשלח תזכורת אחת לפני כניסת השבת, ולא נעשה בכתובת שימוש אחר.',
				'remind_done_en'       => 'You are on the list. One reminder before Shabbat begins, and the address is used for nothing else.',
				'remind_err_he'        => 'צריך כתובת מייל תקינה.',
				'remind_err_en'        => 'A valid email address is needed.',
				'remind_on'            => 1,
				'remind_idle'          => 45,
				'invite_delay'         => 5,
				'invite_days'          => 7,
			),
			'screens'           => array(
				'back_he'           => 'חזרה ›',
				'back_en'           => '‹ Back',
				'candles_word_he'   => 'נרות',
				'candles_word_en'   => 'candles',
				'art_summary_he'    => 'היצירה של השבת הזאת מורכבת מ-%1$s נרות, אחד לכל משתתף, והיא הושלמה ב-%2$d אחוזים.',
				'art_summary_en'    => 'This Shabbat’s artwork is made of %1$s candles, one per participant, and is %2$d percent complete.',
				'art_hint_zoom_he'  => 'היצירה עשויה מנרות. הגדל כדי לראות אותם.',
				'art_hint_zoom_en'  => 'The artwork is made of candles. Zoom in to see them.',
				'art_hint_pan_he'   => 'לחץ על נר כדי לראות מי הדליק אותו. אפשר לגרור כדי לנוע ביצירה.',
				'art_hint_pan_en'   => 'Click a candle to see who lit it. Drag to move around the artwork.',
				'art_hint_pick_he'  => 'כל נר ביצירה הוא אדם אחד שהוסיף אור לשבת הזאת.',
				'art_hint_pick_en'  => 'Every candle in the artwork is one person who added their light for this Shabbat.',
				'wall_hint_he'      => 'לחץ על נר כדי לראות מי הדליק אותו.',
				'wall_hint_en'      => 'Click a candle to see who lit it.',
				'wall_hint_pick_he' => 'כל נר כאן הוא אדם אחד שהוסיף אור לשבת.',
				'wall_hint_pick_en' => 'Every candle here is one person who added their light for Shabbat.',
				'my_candle_he'      => 'הנר שלי',
				'my_candle_en'      => 'My candle',
				'pick_anon_he'      => 'בעילום שם',
				'pick_anon_en'      => 'Anonymous',
				'pick_anon_sub_he'  => 'בחר/ה להוסיף אור בלי להופיע בשם.',
				'pick_anon_sub_en'  => 'Chose to add their light without a name.',
				'pick_none_he'      => 'אחד מהמדליקים',
				'pick_none_en'      => 'One of the lights',
				'pick_none_sub_he'  => 'נספר/ה למניין לפני שהאתר התחיל לשמור שמות.',
				'pick_none_sub_en'  => 'Counted in before the site began keeping names.',
				'light_mine_he'     => 'להדליק נר משלי',
				'light_mine_en'     => 'Light my own candle',
				'invite_title_he'   => 'הנר של %s, שהודלק כאן לכבוד השבת.',
				'invite_title_en'   => 'This is the candle %s lit for Shabbat.',
				'invite_title_anon_he' => 'זה הנר שהודלק לכבוד השבת, והקישור הזה הוא ההזמנה שלכם.',
				'invite_title_anon_en' => 'This is a candle lit for Shabbat, and this link is your invitation.',
				'invite_count_he'   => 'כבר %s אנשים הדליקו נר דרך הקישור הזה.',
				'invite_count_en'   => '%s people have lit a candle through this link.',
				'invite_first_he'   => 'אתם יכולים להיות הראשונים שמדליקים נר דרך הקישור הזה.',
				'invite_first_en'   => 'You can be the first to light a candle through this link.',
				'zoom_in_he'        => 'הגדלה',
				'zoom_in_en'        => 'Zoom in',
				'zoom_out_he'       => 'הקטנה',
				'zoom_out_en'       => 'Zoom out',
				'wow_parts_he'      => 'חלקים ביצירה של השבת הזאת',
				'wow_parts_en'      => 'pieces in this Shabbat’s artwork',
				'wow_title_he'      => 'עכשיו גם אתה חלק.',
				'wow_title_en'      => 'Now you’re part of it.',
				'wow_line1_he'      => 'הוספת אור לשבת. ויחד עם עוד',
				'wow_line1_en'      => 'You added your light for Shabbat. And together with',
				'wow_line2_he'      => 'אנשים — אנחנו יוצרים אור גדול.',
				'wow_line2_en'      => 'more people — we are making a great light.',
				'wow_cta_he'        => 'לכרטיס השיתוף שלי',
				'wow_cta_en'        => 'To my share card',
				'res_title_he'      => 'גם אני הוספתי אור לשבת ❤️',
				'res_title_en'      => 'I added my light for Shabbat too ❤️',
				'my_thing_label_he' => 'האור שלי',
				'my_thing_label_en' => 'My light',
				'we_are_he'         => 'אנחנו כבר',
				'we_are_en'         => 'We are already',
				'ask_others_he'     => 'מה האור שלכם לשבת?',
				'ask_others_en'     => 'What is your light for Shabbat?',
				'back_home_he'      => 'חזרה ליצירה של השבת',
				'back_home_en'      => 'Back to this Shabbat’s artwork',
				'close_he'          => 'סגירה',
				'close_en'          => 'Close',
			),
		);
	}

	/**
	 * The commitment types, in the order the design lists them.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function options(): array {
		$rows = array(
			array( 'הדלקת נרות', 'Lighting candles' ),
			array( 'קידוש', 'Kiddush' ),
			array( 'סעודת שבת', 'Shabbat dinner' ),
			array( 'זמן עם המשפחה', 'Time with family' ),
			array( 'לימוד', 'Learning' ),
			array( 'הכנסת אורחים', 'Hosting guests' ),
			array( 'מעשה חסד', 'An act of kindness' ),
			array( 'הכנה לכבוד שבת', 'Preparing for Shabbat' ),
			array( 'אור אחר', 'A different light', 1 ),
		);

		return array_map(
			static fn( array $row ): array => array(
				'label_he' => $row[0],
				'label_en' => $row[1],
				'is_other' => (int) ( $row[2] ?? 0 ),
			),
			$rows
		);
	}

	/**
	 * Seed points for the world map, as in the reference.
	 *
	 * Coordinates are city centres, never a participant's own position: the map
	 * shows where people joined from, at city granularity and no finer.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function map_points(): array {
		$rows = array(
			array( 'ירושלים', 31.78, 35.22, 3 ),
			array( 'תל אביב', 32.08, 34.78, 3 ),
			array( 'רמת גן', 32.08, 34.83, 2.4 ),
			array( 'חיפה', 32.79, 34.99, 2 ),
			array( 'באר שבע', 31.25, 34.79, 1.8 ),
			array( 'רחובות', 31.89, 34.81, 1.6 ),
			array( 'צפת', 32.92, 35.30, 1.5 ),
			array( 'ניו יורק', 40.71, -74.01, 3 ),
			array( 'ברוקלין', 40.62, -73.94, 2.4 ),
			array( 'ווסטצ׳סטר', 41.05, -73.79, 1.8 ),
			array( 'בוסטון', 42.35, -71.06, 1.4 ),
			array( 'שיקגו', 41.88, -87.63, 1.8 ),
			array( 'מיאמי', 25.76, -80.19, 2 ),
			array( 'לוס אנג׳לס', 34.05, -118.24, 2.2 ),
			array( 'סן פרנסיסקו', 37.77, -122.42, 1.3 ),
			array( 'פילדלפיה', 39.95, -75.16, 1.3 ),
			array( 'וושינגטון', 38.90, -77.04, 1.2 ),
			array( 'טורונטו', 43.65, -79.38, 1.9 ),
			array( 'מונטריאול', 45.50, -73.57, 1.7 ),
			array( 'מקסיקו סיטי', 19.43, -99.13, 1.2 ),
			array( 'פנמה סיטי', 8.98, -79.52, 1 ),
			array( 'בואנוס איירס', -34.60, -58.38, 1.8 ),
			array( 'סאו פאולו', -23.55, -46.63, 1.4 ),
			array( 'לונדון', 51.51, -0.13, 2.2 ),
			array( 'מנצ׳סטר', 53.48, -2.24, 1.4 ),
			array( 'אנטוורפן', 51.22, 4.40, 1.8 ),
			array( 'פריז', 48.86, 2.35, 1.9 ),
			array( 'אמסטרדם', 52.37, 4.90, 1.3 ),
			array( 'בריסל', 50.85, 4.35, 1.1 ),
			array( 'ברלין', 52.52, 13.40, 1.3 ),
			array( 'וינה', 48.21, 16.37, 1.1 ),
			array( 'ציריך', 47.38, 8.54, 1.2 ),
			array( 'מילאנו', 45.46, 9.19, 1.1 ),
			array( 'מוסקבה', 55.76, 37.62, 1.4 ),
			array( 'קייב', 50.45, 30.52, 1.1 ),
			array( 'שטוקהולם', 59.33, 18.07, 1 ),
			array( 'איסטנבול', 41.01, 28.98, 1 ),
			array( 'יוהנסבורג', -26.20, 28.05, 1.3 ),
			array( 'קייפטאון', -33.92, 18.42, 1.1 ),
			array( 'מלבורן', -37.81, 144.96, 1.7 ),
			array( 'סידני', -33.87, 151.21, 1.4 ),
			array( 'טוקיו', 35.68, 139.65, 1 ),
			array( 'הונג קונג', 22.32, 114.17, 0.9 ),
			array( 'מומבאי', 19.08, 72.88, 0.9 ),
			array( 'סינגפור', 1.35, 103.82, 0.9 ),
			array( 'לימה', -12.05, -77.04, 0.9 ),
			array( 'קראקס', 10.48, -66.90, 0.9 ),
		);

		return array_map(
			static fn( array $row ): array => array(
				'name'   => $row[0],
				'lat'    => $row[1],
				'lng'    => $row[2],
				'weight' => $row[3],
			),
			$rows
		);
	}
}
