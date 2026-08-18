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
				'brand_he'          => 'משהו לשבת',
				'brand_en'          => 'Something for Shabbat',
				'logo'              => 0,
				'cta_he'            => 'גם אני מוסיף משהו',
				'cta_en'            => 'I’m adding something too',
				'lang_btn_he'       => 'EN',
				'lang_btn_en'       => 'עב',
				'countdown_days_he' => 'השבת פרשת %1$s בעוד %2$d ימים',
				'countdown_days_en' => 'Shabbat Parashat %1$s in %2$d days',
				'countdown_clock_he' => 'השבת פרשת %1$s בעוד %2$s',
				'countdown_clock_en' => 'Shabbat Parashat %1$s in %2$s',
				'credit_text'       => 'uxui & dev by multi digital',
				'credit_url'        => 'https://m-d.co.il/',
				'accessibility_url' => '',
				'terms_url'         => '',
				'privacy_url'       => '',
			),
			'campaign' => array(
				'parsha_he'   => 'משפטים',
				'parsha_en'   => 'Mishpatim',
				'target'      => 172000,
				'seed_count'  => 127438,
				'artwork'     => 'candles',
				'accent'      => '#FFB25C',
				'candle_day'  => '5',
				'candle_time' => '19:12',
				'countries'   => 42,
				'cities'      => 386,
				'closed'      => 0,
			),
			'hero'     => array(
				'eyebrow_he' => 'יצירה אחת. כל השבוע. כל העולם.',
				'eyebrow_en' => 'One artwork. Every week. The whole world.',
				'h1a_he'     => 'כל אחד מוסיף אור קטן.',
				'h1a_en'     => 'Everyone adds a little light.',
				'h1b_he'     => 'ביחד יוצרים משהו גדול.',
				'h1b_en'     => 'Together we make something big.',
				'sub_he'     => 'השבת, אלפי יהודים בארץ ובעולם בוחרים להוסיף משהו אחד לכבוד השבת. מה המשהו שלך?',
				'sub_en'     => 'This Shabbat, thousands of Jews in Israel and around the world are choosing one thing to add. What is your something?',
				'already_he' => 'כבר בפנים',
				'already_en' => 'already in',
				'collage'    => array(),
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
				'stat_people_he'      => 'אנשים הוסיפו משהו',
				'stat_people_en'      => 'people added something',
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
				'sub_he'     => 'יהודים מ-%d מדינות כבר הוסיפו משהו לשבת הקרובה. כל נקודת אור על המפה היא מקום שממנו מישהו הצטרף.',
				'sub_en'     => 'Jews from %d countries have already added something for this Shabbat. Every point of light is a place someone joined from.',
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
				'wa_message_he' => 'הוספתי משהו לשבת הקרובה. מה המשהו שלך? %s',
				'wa_message_en' => 'I added something for this Shabbat. What is your something? %s',
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
				'title_he'           => 'מה המשהו שלך לשבת הקרובה?',
				'title_en'           => 'What is your something for this Shabbat?',
				'urgency_soon_he'    => 'נותרו עוד %d שעות להצטרף ליצירה של השבת הזאת.',
				'urgency_soon_en'    => '%d hours left to join this Shabbat’s artwork.',
				'urgency_default_he' => 'כשהשבת נכנסת, היצירה נסגרת ונשמרת בארכיון. מיד אחריה מתחילה היצירה של השבת הבאה.',
				'urgency_default_en' => 'When Shabbat begins the artwork closes and is saved to the archive. Right after it, next Shabbat’s artwork starts.',
				'closed_note_he'     => 'השבת נכנסה והיצירה נסגרה. היצירה של השבת הבאה תיפתח מיד אחרי צאת השבת.',
				'closed_note_en'     => 'Shabbat has begun and the artwork is closed. Next Shabbat’s artwork opens right after Shabbat ends.',
			),
			'join'     => array(
				'pick_title_he'      => 'אז... מה המשהו שלך לשבת?',
				'pick_title_en'      => 'So… what is your something?',
				'pick_sub_he'        => 'אפשר לבחור עד שלושה. כל משהו נחשב.',
				'pick_sub_en'        => 'Choose up to three. Every something counts.',
				'options'            => self::options(),
				'other_ph_he'        => 'מה המשהו שלך?',
				'other_ph_en'        => 'What is your something?',
				'pick_cta_he'        => 'זה המשהו שלי',
				'pick_cta_en'        => 'That’s my something',
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
				'privacy_note_he'    => 'לא נבקש יותר מזה. השם והעיר יוצגו רק אם תרצה, והטלפון והמייל משמשים לתזכורת בלבד.',
				'privacy_note_en'    => 'We won’t ask for more. Your name and city appear only if you want, and phone and email are used for the reminder only.',
				'submit_cta_he'      => 'אני מצטרף',
				'submit_cta_en'      => 'I’m joining',
				'step_label_he'      => 'שלב %1$d מתוך %2$d',
				'step_label_en'      => 'Step %1$d of %2$d',
				'err_name_he'        => 'נא למלא שם פרטי.',
				'err_name_en'        => 'Please enter a first name.',
				'err_city_he'        => 'נא למלא עיר.',
				'err_city_en'        => 'Please enter a city.',
				'err_email_he'       => 'נא למלא כתובת אימייל תקינה.',
				'err_email_en'       => 'Please enter a valid email address.',
				'err_phone_he'       => 'נא למלא מספר טלפון תקין.',
				'err_phone_en'       => 'Please enter a valid phone number.',
				'err_generic_he'     => 'השליחה נכשלה. אפשר לנסות שוב בעוד רגע.',
				'err_generic_en'     => 'Something went wrong. Please try again in a moment.',
				'err_duplicate_he'   => 'כבר הוספת משהו לשבת הזאת.',
				'err_duplicate_en'   => 'You have already added something for this Shabbat.',
				'err_rate_he'        => 'התקבלו יותר מדי בקשות מהחיבור הזה. אפשר לנסות שוב עוד שעה.',
				'err_rate_en'        => 'Too many requests from this connection. Please try again in an hour.',
				'err_closed_he'      => 'השבת נכנסה והיצירה של השבוע נסגרה.',
				'err_closed_en'      => 'Shabbat has begun and this week’s artwork is closed.',
				'sending_he'         => 'שולח…',
				'sending_en'         => 'Sending…',
			),
			'screens'  => array(
				'back_he'            => 'חזרה ›',
				'back_en'            => '‹ Back',
				'candles_word_he'    => 'נרות',
				'candles_word_en'    => 'candles',
				'art_summary_he'     => 'היצירה של השבת הזאת מורכבת מ-%1$s נרות, אחד לכל משתתף, והיא הושלמה ב-%2$d אחוזים.',
				'art_summary_en'     => 'This Shabbat’s artwork is made of %1$s candles, one per participant, and is %2$d percent complete.',
				'art_hint_zoom_he'   => 'היצירה עשויה מנרות. הגדל כדי לראות אותם.',
				'art_hint_zoom_en'   => 'The artwork is made of candles. Zoom in to see them.',
				'art_hint_pan_he'    => 'לחץ על נר כדי לראות מי הדליק אותו. אפשר לגרור כדי לנוע ביצירה.',
				'art_hint_pan_en'    => 'Click a candle to see who lit it. Drag to move around the artwork.',
				'art_hint_pick_he'   => 'כל נר ביצירה הוא אדם אחד שהוסיף משהו לשבת הזאת.',
				'art_hint_pick_en'   => 'Every candle in the artwork is one person who added something for this Shabbat.',
				'wall_hint_he'       => 'לחץ על נר כדי לראות מי הדליק אותו.',
				'wall_hint_en'       => 'Click a candle to see who lit it.',
				'wall_hint_pick_he'  => 'כל נר כאן הוא אדם אחד שהוסיף משהו לשבת.',
				'wall_hint_pick_en'  => 'Every candle here is one person who added something for Shabbat.',
				'light_mine_he'      => 'להדליק נר משלי',
				'light_mine_en'      => 'Light my own candle',
				'zoom_in_he'         => 'הגדלה',
				'zoom_in_en'         => 'Zoom in',
				'zoom_out_he'        => 'הקטנה',
				'zoom_out_en'        => 'Zoom out',
				'wow_parts_he'       => 'חלקים ביצירה של השבת הזאת',
				'wow_parts_en'       => 'pieces in this Shabbat’s artwork',
				'wow_title_he'       => 'עכשיו גם אתה חלק.',
				'wow_title_en'       => 'Now you’re part of it.',
				'wow_line1_he'       => 'הוספת משהו לשבת. ויחד עם עוד',
				'wow_line1_en'       => 'You added something for Shabbat. And together with',
				'wow_line2_he'       => 'אנשים — אנחנו יוצרים משהו גדול.',
				'wow_line2_en'       => 'more people — we are making something big.',
				'wow_cta_he'         => 'לכרטיס השיתוף שלי',
				'wow_cta_en'         => 'To my share card',
				'res_title_he'       => 'גם אני הוספתי משהו לשבת ❤️',
				'res_title_en'       => 'I added something for Shabbat too ❤️',
				'my_thing_label_he'  => 'המשהו שלי',
				'my_thing_label_en'  => 'My something',
				'we_are_he'          => 'אנחנו כבר',
				'we_are_en'          => 'We are already',
				'ask_others_he'      => 'מה המשהו שלכם לשבת?',
				'ask_others_en'      => 'What is your something for Shabbat?',
				'back_home_he'       => 'חזרה ליצירה של השבת',
				'back_home_en'       => 'Back to this Shabbat’s artwork',
				'close_he'           => 'סגירה',
				'close_en'           => 'Close',
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
			array( 'משהו אחר', 'Something else', 1 ),
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
