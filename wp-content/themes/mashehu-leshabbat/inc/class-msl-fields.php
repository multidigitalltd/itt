<?php
/**
 * Editable field schema.
 *
 * One declarative description of every editable field drives three things: the
 * meta boxes in the page editor, the sanitisation on save, and the shape of the
 * data the templates read. Adding a field here is the only change needed.
 *
 * The site is bilingual, so most copy fields come in pairs. `bi()` emits both
 * halves from one call; the templates read them through msl_t(), and the same
 * pair is handed to the browser so the header toggle can swap languages with no
 * reload.
 *
 * @package Mashehu_LeShabbat
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Declarative field definitions, grouped by section.
 */
final class MSL_Fields {


	/**
	 * Cached schema.
	 *
	 * @var array<string, array<string, mixed>>|null
	 */
	private static ?array $schema = null;

	/**
	 * The full schema, keyed by section.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		self::$schema ??= self::build();

		return self::$schema;
	}

	/**
	 * Section keys belonging to a given page template.
	 *
	 * @param string $template Template key.
	 * @return string[]
	 */
	public static function sections_for( string $template ): array {
		$keys = array();

		foreach ( self::all() as $key => $section ) {
			if ( $section['template'] === $template ) {
				$keys[] = $key;
			}
		}

		return $keys;
	}

	/**
	 * Field definitions of one section.
	 *
	 * @param string $section Section key.
	 * @return array<int, array<string, mixed>>
	 */
	public static function fields( string $section ): array {
		return self::all()[ $section ]['fields'] ?? array();
	}

	/* ---------------------------------------------------------------------
	 * Field constructors
	 * ------------------------------------------------------------------ */

	/**
	 * A section wrapper.
	 *
	 * @param string                           $label    Meta box title.
	 * @param string                           $template Template key the box belongs to.
	 * @param array<int, array<string, mixed>> $fields   Field definitions.
	 * @return array<string, mixed>
	 */
	private static function section( string $label, string $template, array $fields ): array {
		return array(
			'label'    => $label,
			'template' => $template,
			'fields'   => self::flatten( $fields ),
		);
	}

	/**
	 * Flatten the one-level nesting bi() introduces.
	 *
	 * @param array<int, mixed> $fields Field definitions, possibly nested.
	 * @return array<int, array<string, mixed>>
	 */
	private static function flatten( array $fields ): array {
		$out = array();

		foreach ( $fields as $field ) {
			if ( isset( $field['key'] ) ) {
				$out[] = $field;
				continue;
			}

			foreach ( (array) $field as $sub ) {
				$out[] = $sub;
			}
		}

		return $out;
	}

	/**
	 * A single field of any simple type.
	 *
	 * @param string               $type  Field type.
	 * @param string               $key   Field key.
	 * @param string               $label Editor label.
	 * @param array<string, mixed> $extra Extra definition keys.
	 * @return array<string, mixed>
	 */
	private static function field( string $type, string $key, string $label, array $extra = array() ): array {
		return array_merge(
			array(
				'type'  => $type,
				'key'   => $key,
				'label' => $label,
			),
			$extra
		);
	}

	/**
	 * A one-line text field.
	 *
	 * @param string $key   Field key.
	 * @param string $label Editor label.
	 * @param string $help  Optional hint.
	 * @param bool   $ltr   Whether the value is left-to-right whatever the admin language.
	 * @return array<string, mixed>
	 */
	private static function text( string $key, string $label, string $help = '', bool $ltr = false ): array {
		$extra = array();

		if ( '' !== $help ) {
			$extra['help'] = $help;
		}

		if ( $ltr ) {
			$extra['ltr'] = true;
		}

		return self::field( 'text', $key, $label, $extra );
	}

	/**
	 * A multi-line text field.
	 *
	 * @param string $key   Field key.
	 * @param string $label Editor label.
	 * @param int    $rows  Textarea rows.
	 * @param string $help  Optional hint.
	 * @return array<string, mixed>
	 */
	private static function textarea( string $key, string $label, int $rows = 3, string $help = '' ): array {
		$extra = array( 'rows' => $rows );

		if ( '' !== $help ) {
			$extra['help'] = $help;
		}

		return self::field( 'textarea', $key, $label, $extra );
	}

	/**
	 * A URL field.
	 *
	 * @param string $key   Field key.
	 * @param string $label Editor label.
	 * @param string $help  Optional hint.
	 * @return array<string, mixed>
	 */
	private static function url( string $key, string $label, string $help = '' ): array {
		return self::field( 'url', $key, $label, '' !== $help ? array( 'help' => $help ) : array() );
	}

	/**
	 * A whole-number field.
	 *
	 * @param string $key   Field key.
	 * @param string $label Editor label.
	 * @param int    $min   Lowest accepted value.
	 * @param int    $max   Highest accepted value.
	 * @param string $help  Optional hint.
	 * @return array<string, mixed>
	 */
	private static function number( string $key, string $label, int $min, int $max, string $help = '' ): array {
		$extra = array(
			'min' => $min,
			'max' => $max,
		);

		if ( '' !== $help ) {
			$extra['help'] = $help;
		}

		return self::field( 'number', $key, $label, $extra );
	}

	/**
	 * A decimal field, used for map coordinates.
	 *
	 * @param string $key   Field key.
	 * @param string $label Editor label.
	 * @param float  $min   Lowest accepted value.
	 * @param float  $max   Highest accepted value.
	 * @return array<string, mixed>
	 */
	private static function decimal( string $key, string $label, float $min, float $max ): array {
		return self::field(
			'decimal',
			$key,
			$label,
			array(
				'min' => $min,
				'max' => $max,
			)
		);
	}

	/**
	 * A media-library image field.
	 *
	 * @param string $key   Field key.
	 * @param string $label Editor label.
	 * @param string $help  Optional hint.
	 * @return array<string, mixed>
	 */
	private static function image( string $key, string $label, string $help = '' ): array {
		return self::field( 'image', $key, $label, '' !== $help ? array( 'help' => $help ) : array() );
	}

	/**
	 * A checkbox.
	 *
	 * @param string $key   Field key.
	 * @param string $label Editor label.
	 * @param string $help  Optional hint.
	 * @return array<string, mixed>
	 */
	private static function checkbox( string $key, string $label, string $help = '' ): array {
		return self::field( 'checkbox', $key, $label, '' !== $help ? array( 'help' => $help ) : array() );
	}

	/**
	 * A select constrained to a fixed option list.
	 *
	 * @param string                $key     Field key.
	 * @param string                $label   Editor label.
	 * @param array<string, string> $options value => label.
	 * @param string                $help    Optional hint.
	 * @return array<string, mixed>
	 */
	private static function select( string $key, string $label, array $options, string $help = '' ): array {
		$extra = array( 'options' => $options );

		if ( '' !== $help ) {
			$extra['help'] = $help;
		}

		return self::field( 'select', $key, $label, $extra );
	}

	/**
	 * An unbounded repeating list.
	 *
	 * @param string                           $key         Field key.
	 * @param string                           $label       Editor label.
	 * @param string                           $label_field Sub-field used as the collapsed row summary.
	 * @param array<int, array<string, mixed>> $fields      Sub-field definitions.
	 * @param string                           $help        Optional hint.
	 * @return array<string, mixed>
	 */
	private static function repeater( string $key, string $label, string $label_field, array $fields, string $help = '' ): array {
		$extra = array(
			'label_field' => $label_field,
			'fields'      => self::flatten( $fields ),
		);

		if ( '' !== $help ) {
			$extra['help'] = $help;
		}

		return self::field( 'repeater', $key, $label, $extra );
	}

	/**
	 * A Hebrew/English pair of the same field.
	 *
	 * Returns both halves, which flatten() splices into the section. Templates
	 * read the pair through msl_t( $section, 'key' ), and MSL_I18N ships both to
	 * the browser so the language toggle needs no reload and no second request.
	 *
	 * @param string $type  Field type: 'text' or 'textarea'.
	 * @param string $key   Base field key; the halves get _he and _en suffixes.
	 * @param string $label Editor label.
	 * @param int    $rows  Textarea rows, when the type is 'textarea'.
	 * @param string $help  Optional hint, shown under the Hebrew half.
	 * @return array<int, array<string, mixed>>
	 */
	private static function bi( string $type, string $key, string $label, int $rows = 3, string $help = '' ): array {
		if ( 'textarea' === $type ) {
			return array(
				self::textarea( $key . '_he', $label . ' (עברית)', $rows, $help ),
				self::textarea( $key . '_en', $label . ' (English)', $rows ),
			);
		}

		return array(
			self::text( $key . '_he', $label . ' (עברית)', $help ),
			self::text( $key . '_en', $label . ' (English)' ),
		);
	}

	/**
	 * The menu destinations, as select options.
	 *
	 * @return array<string, string>
	 */
	private static function nav_options(): array {
		$options = array();

		foreach ( MSL_Theme::NAV_TARGETS as $key => $spec ) {
			$options[ $key ] = $spec['label'];
		}

		return $options;
	}

	/**
	 * The place list, as select options.
	 *
	 * The names live with the code that calls Hebcal rather than here, because
	 * a name and the id it stands for have to be changed in one movement — a
	 * list that says "Jerusalem" against Haifa's id is worse than no list.
	 *
	 * @return array<string, string>
	 */
	private static function place_options(): array {
		$options = array();

		foreach ( MSL_Zmanim::PLACES as $id => $names ) {
			$options[ (string) $id ] = $names['he'] . ' · ' . $names['en'];
		}

		return $options;
	}

	/* ---------------------------------------------------------------------
	 * The schema
	 * ------------------------------------------------------------------ */

	/**
	 * Build the schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function build(): array {
		return array(
			'chrome'   => self::section(
				'00 · מיתוג, כותרת עליונה ופוטר',
				'home',
				array(
					self::bi( 'text', 'brand', 'שם המותג', 3, 'מופיע בכותרת העליונה ובכרטיס השיתוף. השם באנגלית סומן על ידי הלקוח כלא סופי.' ),
					self::image( 'logo', 'לוגו', 'ריק = סימן ברירת המחדל של התבנית (ריבוע מעוגל עם נקודת אור).' ),
					self::bi( 'text', 'cta', 'כפתור ההצטרפות הראשי' ),
					self::text( 'lang_btn_he', 'תווית מתג השפה כשהאתר בעברית', 'הכפתור מציג את השפה שאליה עוברים.' ),
					self::text( 'lang_btn_en', 'תווית מתג השפה כשהאתר באנגלית' ),
					self::bi( 'text', 'countdown_days', 'ספירה לאחור — ימים', 3, 'המחרוזת %1$s מוחלפת בשם הפרשה ו-%2$d במספר הימים.' ),
					self::bi( 'text', 'countdown_day', 'ספירה לאחור — יום אחד', 3, '%s מוחלף בשם הפרשה. עברית מבדילה בין יום, יומיים ושלושה ימים, ולכן יש שלוש שורות.' ),
					self::bi( 'text', 'countdown_2days', 'ספירה לאחור — יומיים', 3, '%s מוחלף בשם הפרשה.' ),
					self::bi( 'text', 'countdown_hours', 'ספירה לאחור — שעות', 3, '%1$s = שם השבת, %2$d = מספר השעות. מוצג ביום שלפני השבת.' ),
					self::bi( 'text', 'countdown_2hours', 'ספירה לאחור — שעתיים', 3, '%s = שם השבת.' ),
					self::bi( 'text', 'countdown_hour', 'ספירה לאחור — שעה אחת', 3, '%s = שם השבת.' ),
					self::bi( 'text', 'countdown_minutes', 'ספירה לאחור — דקות', 3, '%1$s = שם השבת, %2$d = מספר הדקות.' ),
					self::bi( 'text', 'countdown_minute', 'ספירה לאחור — הרגע האחרון', 3, '%s = שם השבת.' ),
					self::text( 'credit_text', 'קרדיט בפוטר', 'ריק = לא יוצג.' ),
					self::url( 'credit_url', 'קישור הקרדיט' ),
					self::url( 'accessibility_url', 'קישור להצהרת הנגישות' ),
					self::url( 'terms_url', 'קישור לתנאי השימוש' ),
					self::url( 'privacy_url', 'קישור למדיניות הפרטיות' ),
				)
			),
			'campaign' => self::section(
				'01 · קמפיין השבת',
				'home',
				array(
					self::bi( 'text', 'parsha', 'שם הפרשה', 3, 'מוצג בשעון שבכותרת. יש לעדכן מדי שבוע.' ),
					self::number( 'target', 'מספר החלקים ביצירה (היעד)', 1000, 1000000, 'קובע את אחוז ההשלמה: משתתפים חלקי היעד.' ),
					self::number( 'seed_count', 'מספר משתתפים לפתיחה', 0, 1000000, 'נקודת הפתיחה של המונה, לפני שנרשמות הצטרפויות אמיתיות. אפס = המונה מתחיל מההצטרפויות בפועל.' ),
					self::number( 'demo_rate', 'קצב הצטרפות לתצוגה (לשעה)', 0, 100000, 'כמה הצטרפויות לשעה מוצגות לפני שיש תנועה אמיתית. המונה עולה מעצמו לפי הקצב, והשורה "בעשר הדקות האחרונות הצטרפו…" מקבלת חלק ממנו עם שינוי קטן, כך שהעמוד נראה חי. הקצב נעצר מעצמו כשהמונה מגיע ליעד. אפס = כבוי, והמספרים הם ההצטרפויות בפועל בלבד. זה מספר תצוגה: הוא אינו נרשם כאנשים, אינו מופיע בייצוא ואפשר לאפס אותו בכל רגע.' ),
					self::text( 'demo_from', 'התאריך שממנו הקצב נספר', 'בפורמט YYYY-MM-DD. ריק = מהיום. תאריך מוקדם יותר מתחיל את המונה גבוה יותר.', true ),
					self::select(
						'artwork',
						'צורת היצירה',
						array(
							'rotate'    => 'סבב שבועי — יצירה אחרת בכל שבת',
							'candles'   => 'נרות שבת',
							'star'      => 'מגן דוד',
							'menorah'   => 'מנורת שבעת הקנים',
							'tablets'   => 'לוחות הברית',
							'kiddush'   => 'כוס קידוש',
							'jerusalem' => 'ירושלים',
							'israel'    => 'מפת ישראל',
							'light'     => 'נקודת אור',
						),
						'בסבב השבועי היצירה נקבעת ממספר השבוע בשנה, כך שכל הגולשים רואים את אותה יצירה בלי קשר לזמן הטעינה. בחירת צורה מסוימת מקבעת אותה עד שחוזרים לסבב.'
					),
					self::text( 'accent', 'צבע האור ביצירה', 'קוד HEX. ברירת מחדל #FFB25C.', true ),
					self::select(
						'candle_day',
						'יום כניסת השבת',
						array(
							'5' => 'שישי',
							'6' => 'שבת',
							'4' => 'חמישי',
						)
					),
					self::text( 'candle_time', 'שעת כניסת השבת', 'בפורמט HH:MM לפי אזור הזמן של האתר. ברירת מחדל 19:12.', true ),
					self::number( 'countries', 'מספר מדינות', 0, 300, 'מוצג בסקשן "השבת הזאת" ובכותרת המפה.' ),
					self::number( 'cities', 'מספר ערים', 0, 100000 ),
					self::checkbox( 'closed', 'הקמפיין נסגר — לא ניתן להצטרף' ),
				)
			),
			'zmanim'   => self::section(
				'01א · זמני שבת ותאריך עברי',
				'home',
				array(
					self::checkbox(
						'auto',
						'למשוך את זמני השבת מהרשת (Hebcal)',
						'כשהאפשרות פעילה, כניסת השבת, צאת השבת, שם הפרשה והתאריך העברי נמשכים אוטומטית מ-hebcal.com ומתעדכנים מדי שבוע בלי נגיעה. המשיכה נעשית מהשרת ונשמרת במטמון לשש שעות — הדפדפן של הגולש אינו פונה לשום אתר חיצוני. אם השירות אינו זמין, האתר חוזר ליום ולשעה שהוגדרו ידנית בסעיף 01.'
					),
					self::select( 'place', 'המיקום שלפיו נחשבים הזמנים', self::place_options(), 'בירושלים הדלקת הנרות היא ארבעים דקות לפני השקיעה ובחיפה שלושים — הזמנים כבר יודעים את המנהג של כל עיר.' ),
					self::number( 'place_id', 'מיקום אחר (מזהה GeoNames)', 0, 99999999, 'רק אם העיר הדרושה אינה ברשימה. המספר נמצא בכתובת של העיר באתר geonames.org, למשל 281184 לירושלים. אפס = משתמשים בעיר שנבחרה למעלה.' ),
					self::number( 'havdalah', 'צאת השבת — דקות אחרי השקיעה', 0, 120, 'אפס = צאת הכוכבים לפי החישוב הרגיל של Hebcal. מי שנוהג בזמן אחר יכול לכתוב כאן מספר דקות, למשל 42 או 72.' ),
					self::checkbox( 'show', 'להציג את לוח הזמנים בעמוד', 'כיבוי משאיר את הזמנים פעילים לספירה לאחור ולשם הפרשה, בלי להציג את הלוח עצמו.' ),
					self::checkbox( 'pick_on', 'לאפשר לגולשים לבחור עיר משלהם', 'כפתור "שינוי מיקום" על הלוח, עם רשימת הערים שלמעלה. הבחירה משנה רק את הלוח — הספירה לאחור והיצירה נשארות של הקמפיין, כי כל המשתתפים משלימים את אותה יצירה באותו רגע.' ),
					self::bi( 'text', 'title', 'כותרת הלוח' ),
					self::bi( 'text', 'label_hdate', 'תווית התאריך העברי' ),
					self::bi( 'text', 'label_parsha', 'תווית פרשת השבוע' ),
					self::bi( 'text', 'label_holiday', 'תווית שבת של חג', 3, 'בשבתות של חג אין פרשה, ובמקומה מוצג שם החג — והתווית מתחלפת לזו.' ),
					self::bi( 'text', 'label_candles', 'תווית כניסת השבת' ),
					self::bi( 'text', 'label_havdalah', 'תווית צאת השבת' ),
					self::bi( 'text', 'note', 'שורת המיקום מתחת ללוח', 3, 'המחרוזת %s מוחלפת בשם המקום.' ),
					self::bi( 'text', 'pick_cta', 'כפתור שינוי המיקום' ),
					self::bi( 'text', 'pick_label', 'תווית רשימת הערים' ),
					self::bi( 'text', 'pick_apply', 'כפתור האישור ברשימה' ),
					self::bi( 'text', 'pick_il', 'כותרת קבוצת הערים בארץ' ),
					self::bi( 'text', 'pick_world', 'כותרת קבוצת הערים בעולם' ),
				)
			),
			'hero'     => self::section(
				'02 · הירו',
				'home',
				array(
					self::bi( 'text', 'eyebrow', 'שורת פתיחה מעל הכותרת' ),
					self::bi( 'text', 'h1a', 'כותרת ראשית — שורה ראשונה' ),
					self::bi( 'text', 'h1b', 'כותרת ראשית — שורה שנייה (בצבע ההדגשה)' ),
					self::bi( 'textarea', 'sub', 'פסקת פתיחה', 3 ),
					self::bi( 'text', 'already', 'תווית ליד המונה' ),
					self::number( 'light_count', 'כמות הנרות בהירו', 0, 14, 'נרות מאונפשים משני צידי הכותרת — נדלקים, נכבים וחוזרים במקום אחר. לחיצה על נר מדליקה או מכבה אותו. ארבעה־עשר הם הפריסה המלאה; אפס = בלי נרות.' ),
				)
			),
			'stage'    => self::section(
				'03 · היצירה וקיר הנרות',
				'home',
				array(
					self::bi( 'text', 'art_title', 'כותרת פאנל היצירה' ),
					self::bi( 'text', 'candles_now', 'תווית אחרי מספר הנרות' ),
					self::bi( 'text', 'completed_short', 'תווית ליד אחוז ההשלמה' ),
					self::bi( 'text', 'enter_art', 'כפתור הכניסה ליצירה' ),
					self::bi( 'text', 'wall', 'שם קיר הנרות' ),
					self::bi( 'text', 'wall_count', 'תווית מתחת לשם הקיר' ),
					self::checkbox( 'demo_names', 'להציג שמות לדוגמה על הנרות הוותיקים', 'הנרות שנספרו למניין לפני שהאתר עלה אינם שמורים בשם. כשהאפשרות פעילה, לחיצה על נר כזה מציגה שם מהרשימות שלמטה — שם שנבחר לפי מיקום הנר, כך שאותו נר מציג תמיד את אותו שם. הצטרפויות אמיתיות תמיד גוברות. כשהאפשרות כבויה מוצג "אחד מהמדליקים".' ),
					self::bi( 'textarea', 'demo_first_names', 'שמות לדוגמה', 6, 'שם בכל שורה.' ),
					self::bi( 'textarea', 'demo_cities', 'ערים לדוגמה', 6, 'עיר בכל שורה.' ),
				)
			),
			'marquee'  => self::section(
				'04 · פס הפעילות',
				'home',
				array(
					self::repeater(
						'rows',
						'שורות פתיחה',
						'text_he',
						array(
							self::text( 'text_he', 'טקסט (עברית)' ),
							self::text( 'text_en', 'טקסט (English)' ),
						),
						'הפס מציג הצטרפויות אמיתיות ברגע שיש כאלה. השורות כאן ממלאות אותו עד אז, ומשלימות אותו כשיש פחות מהמינימום.'
					),
				)
			),
			'stats'    => self::section(
				'05 · השבת הזאת',
				'home',
				array(
					self::bi( 'text', 'title', 'כותרת הסקשן' ),
					self::bi( 'text', 'sub', 'שורה מתחת לכותרת' ),
					self::bi( 'text', 'stat_people', 'תווית — אנשים' ),
					self::bi( 'text', 'stat_countries', 'תווית — מדינות' ),
					self::bi( 'text', 'stat_cities', 'תווית — ערים' ),
					self::bi( 'text', 'stat_dedications', 'תווית — הקדשות' ),
					self::bi( 'text', 'completed_label', 'תווית כרטיס ההתקדמות' ),
					self::bi( 'text', 'last10', 'שורת ההצטרפויות האחרונות', 3, 'המחרוזת %d מוחלפת במספר.' ),
				)
			),
			'navcards' => self::section(
				'06 · כרטיסי ניווט',
				'home',
				array(
					self::bi( 'text', 'art_card', 'כותרת כרטיס היצירה' ),
					self::bi( 'textarea', 'art_card_sub', 'תיאור כרטיס היצירה', 2 ),
					self::bi( 'text', 'wall_line1', 'כרטיס הקיר — לפני המספר' ),
					self::bi( 'text', 'wall_line2', 'כרטיס הקיר — אחרי המספר' ),
				)
			),
			'map'      => self::section(
				'07 · מפת העולם',
				'home',
				array(
					self::bi( 'text', 'title', 'כותרת הסקשן' ),
					self::bi( 'textarea', 'sub', 'שורה מתחת לכותרת', 3, 'המחרוזת %d מוחלפת במספר המדינות שהוגדר בקמפיין.' ),
					self::bi( 'textarea', 'summary', 'תקציר טקסטואלי של המפה', 3, 'נקרא על ידי קוראי מסך במקום הקנבס. חובה לנגישות.' ),
					self::repeater(
						'points',
						'נקודות אור על המפה',
						'name',
						array(
							self::text( 'name', 'שם המקום', 'לתיעוד בלבד — לא מוצג.' ),
							self::decimal( 'lat', 'קו רוחב', -90, 90 ),
							self::decimal( 'lng', 'קו אורך', -180, 180 ),
							self::decimal( 'weight', 'עוצמת האור', 0.2, 6 ),
						),
						'נקודות הפתיחה. הצטרפויות אמיתיות מוסיפות נקודות משלהן, מקובצות לפי עיר — לעולם לא לפי אדם.'
					),
				)
			),
			'referral' => self::section(
				'08 · שיתוף והזמנות',
				'home',
				array(
					self::bi( 'text', 'title', 'כותרת הסקשן' ),
					self::bi( 'textarea', 'sub', 'שורה מתחת לכותרת', 3 ),
					self::bi( 'text', 'your_link', 'תווית הקישור האישי' ),
					self::bi( 'text', 'wa_send', 'כפתור וואטסאפ' ),
					self::bi( 'textarea', 'wa_message', 'הודעת הוואטסאפ', 3, 'המחרוזת %s מוחלפת בקישור האישי.' ),
					self::bi( 'text', 'share_more', 'כפתור שיתוף נוסף' ),
					self::bi( 'text', 'copy_btn', 'כפתור העתקה' ),
					self::bi( 'text', 'copied_btn', 'כפתור העתקה — אחרי לחיצה' ),
					self::bi( 'text', 'ref_label', 'תווית מונה ההזמנות' ),
					self::bi( 'text', 'next_goal', 'שורת היעד הבא', 3, 'המחרוזת %d מוחלפת במספר.' ),
					self::bi( 'text', 'friends', 'המילה "חברים" בתגיות היעדים' ),
					self::repeater(
						'milestones',
						'יעדי הזמנות',
						'value',
						array(
							self::number( 'value', 'מספר המוזמנים', 1, 100000 ),
						),
						'הטון הוא שותפות ולא תחרות — אין ולא תהיה טבלת מובילים ציבורית.'
					),
				)
			),
			'closing'  => self::section(
				'09 · סגירה',
				'home',
				array(
					self::bi( 'text', 'title', 'כותרת הסגירה' ),
					self::bi( 'textarea', 'urgency_soon', 'שורת דחיפות בתוך 12 שעות', 2, 'המחרוזת %d מוחלפת במספר השעות שנותרו.' ),
					self::bi( 'textarea', 'urgency_default', 'שורת דחיפות רגילה', 2 ),
					self::bi( 'textarea', 'closed_note', 'הודעה כשהקמפיין נסגר', 2 ),
				)
			),
			'join'     => self::section(
				'10 · תהליך ההצטרפות',
				'home',
				array(
					self::bi( 'text', 'pick_title', 'שלב 1 — כותרת' ),
					self::bi( 'text', 'pick_sub', 'שלב 1 — שורה מתחת' ),
					self::repeater(
						'options',
						'האור שאפשר לבחור',
						'label_he',
						array(
							self::text( 'label_he', 'תווית (עברית)' ),
							self::text( 'label_en', 'תווית (English)' ),
							self::checkbox( 'is_other', 'זו האפשרות "אור אחר" — פותחת שדה טקסט חופשי' ),
						),
						'אפשר לבחור עד שלוש אפשרויות. השורה המסומנת "אור אחר" נפרשת על שתי עמודות.'
					),
					self::bi( 'text', 'other_ph', 'שדה "אור אחר" — טקסט מוביל' ),
					self::bi( 'text', 'pick_cta', 'שלב 1 — כפתור' ),
					self::bi( 'text', 'ded_title', 'שלב 2 — כותרת' ),
					self::bi( 'text', 'ded_sub', 'שלב 2 — שורה מתחת' ),
					self::repeater(
						'ded_types',
						'סוגי הקדשה',
						'label_he',
						array(
							self::text( 'label_he', 'תווית (עברית)' ),
							self::text( 'label_en', 'תווית (English)' ),
						)
					),
					self::bi( 'text', 'ded_ph', 'שדה ההקדשה — טקסט מוביל' ),
					self::bi( 'text', 'ded_field_label', 'שדה ההקדשה — תווית' ),
					self::bi( 'textarea', 'ded_note', 'הערת המודרציה', 2 ),
					self::bi( 'text', 'ded_cta', 'שלב 2 — כפתור' ),
					self::bi( 'text', 'skip', 'כפתור הדילוג' ),
					self::bi( 'text', 'det_title', 'שלב 3 — כותרת' ),
					self::bi( 'text', 'det_sub', 'שלב 3 — שורה מתחת' ),
					self::bi( 'text', 'det_optional', 'שלב 3 — שורת "אפשר לדלג"' ),
					self::bi( 'text', 'ph_name', 'תווית שדה השם' ),
					self::bi( 'text', 'ph_city', 'תווית שדה העיר' ),
					self::bi( 'text', 'ph_country', 'תווית שדה המדינה' ),
					self::bi( 'text', 'remind_title', 'בלוק התזכורת — כותרת' ),
					self::bi( 'textarea', 'remind_sub', 'בלוק התזכורת — הסבר', 2 ),
					self::bi( 'text', 'ph_phone', 'תווית שדה הטלפון' ),
					self::bi( 'text', 'ph_email', 'תווית שדה האימייל' ),
					self::bi( 'text', 'anon_label', 'תווית עילום שם' ),
					self::bi( 'textarea', 'privacy_note', 'הערת הפרטיות', 2 ),
					self::bi( 'text', 'submit_cta', 'שלב 3 — כפתור השליחה' ),
					self::bi( 'text', 'step_label', 'תווית מד ההתקדמות', 3, '%1$d = השלב הנוכחי, %2$d = סך השלבים.' ),
					self::bi( 'text', 'err_email', 'שגיאה — אימייל לא תקין' ),
					self::bi( 'text', 'err_phone', 'שגיאה — טלפון לא תקין' ),
					self::bi( 'text', 'err_generic', 'שגיאה — שליחה נכשלה' ),
					self::bi( 'text', 'err_duplicate', 'שגיאה — כבר הצטרפת' ),
					self::bi( 'text', 'err_rate', 'שגיאה — יותר מדי בקשות' ),
					self::bi( 'text', 'err_closed', 'שגיאה — הקמפיין נסגר' ),
					self::bi( 'text', 'sending', 'הודעת שליחה' ),
				)
			),
			'groupcta' => self::section(
				'12א · הזמנה לפתוח קבוצה',
				'home',
				array(
					self::checkbox( 'show', 'להציג את הסקשן בעמוד הקמפיין', 'הסקשן מוסתר גם כשעמוד הקבוצות אינו קיים או שפתיחת קבוצה כבויה — אין טעם להזמין לטופס שלא ניתן להגיע אליו.' ),
					self::bi( 'text', 'eyebrow', 'שורת פתיחה קטנה' ),
					self::bi( 'text', 'title', 'כותרת הסקשן' ),
					self::bi( 'textarea', 'lead', 'פסקת ההסבר', 3 ),
					self::bi( 'text', 'chips_label', 'הכותרת מעל רשימת הסיבות' ),
					self::bi( 'text', 'chip_refua', 'סיבה — רפואה' ),
					self::bi( 'text', 'chip_zechut', 'סיבה — זכות' ),
					self::bi( 'text', 'chip_iluy', 'סיבה — עילוי נשמה' ),
					self::bi( 'text', 'chip_kavod', 'סיבה — שמחה' ),
					self::bi( 'text', 'chip_zivug', 'סיבה — זיווג' ),
					self::bi( 'text', 'note', 'המשפט על היצירה הכללית' ),
					self::bi( 'text', 'cta', 'הכפתור הראשי' ),
					self::bi( 'text', 'cta_all', 'הקישור לארכיון הקבוצות' ),
				)
			),
			'verses'   => self::section(
				'14 · פסוקים ומאמרי חכמים על השבת',
				'home',
				array(
					self::bi( 'text', 'title', 'כותרת הסקשן' ),
					self::bi( 'textarea', 'sub', 'שורת משנה', 2 ),
					self::repeater(
						'quotes',
						'הציטוטים',
						'text_he',
						array(
							self::textarea( 'text_he', 'הציטוט (עברית)', 3 ),
							self::textarea( 'text_en', 'הציטוט (English)', 3 ),
							self::text( 'source_he', 'המקור (עברית)' ),
							self::text( 'source_en', 'המקור (English)' ),
						),
						'רשימה ריקה = הסקשן לא מוצג בכלל. ציטוט בלי מקור יוצג בלי שורת המקור.'
					),
				)
			),
			'nav'      => self::section(
				'13 · תפריט האתר',
				'home',
				array(
					self::repeater(
						'links',
						'פריטי התפריט',
						'label_he',
						array(
							self::text( 'label_he', 'תווית (עברית)' ),
							self::text( 'label_en', 'תווית (English)' ),
							self::select( 'target', 'לאן הפריט מוביל', self::nav_options(), 'הכתובת נבנית מהעמודים שקיימים באתר הזה, ולכן היא נכונה גם כשוורדפרס יושב בתיקייה או כשמבנה הקישורים שונה.' ),
							self::url( 'url', 'כתובת חופשית', 'ממולא רק כשנבחר למעלה "כתובת אחרת". כתובת מלאה, או נתיב שמתחיל ב-/ .' ),
						),
						'רשימה ריקה = אין תפריט בכלל, וכפתור התפריט לא מוצג.'
					),
					self::bi( 'text', 'menu_open', 'תווית כפתור פתיחת התפריט' ),
					self::bi( 'text', 'menu_close', 'תווית כפתור סגירת התפריט' ),
				)
			),
			'account'  => self::section(
				'האיזור האישי',
				'account',
				array(
					self::checkbox( 'open_on', 'לאפשר פתיחת חשבון חדש', 'כיבוי משאיר את ההתחברות לחשבונות שכבר קיימים ומסיר את טופס ההרשמה.' ),
					self::bi( 'text', 'eyebrow', 'שורת פתיחה קטנה' ),
					self::bi( 'text', 'title', 'כותרת העמוד' ),
					self::bi( 'textarea', 'lead', 'פסקת פתיחה', 3 ),

					self::bi( 'text', 'login_title', 'כותרת טופס ההתחברות' ),
					self::bi( 'text', 'f_email', 'שדה — כתובת מייל' ),
					self::bi( 'text', 'f_password', 'שדה — סיסמה' ),
					self::bi( 'text', 'login_cta', 'כפתור ההתחברות' ),
					self::bi( 'text', 'forgot_cta', 'הקישור לשכחתי סיסמה' ),

					self::bi( 'text', 'register_title', 'כותרת טופס ההרשמה' ),
					self::bi( 'textarea', 'register_lead', 'פסקה מעל ההרשמה', 2 ),
					self::bi( 'text', 'f_name', 'שדה — השם שלי' ),
					self::bi( 'text', 'f_password_new', 'שדה — סיסמה חדשה' ),
					self::bi( 'text', 'password_help', 'הסבר מתחת לשדה הסיסמה', 3, 'כדאי שיאמר מה האורך המינימלי.' ),
					self::bi( 'text', 'register_cta', 'כפתור ההרשמה' ),

					self::bi( 'text', 'forgot_title', 'כותרת שחזור הסיסמה' ),
					self::bi( 'textarea', 'forgot_lead', 'פסקה מעל שחזור הסיסמה', 2 ),
					self::bi( 'text', 'forgot_send', 'כפתור שליחת הקישור' ),
					self::bi( 'text', 'forgot_sent', 'הודעה אחרי השליחה', 3, 'מוצגת גם כשאין חשבון בכתובת — בכוונה.' ),
					self::bi( 'text', 'mail_subject', 'נושא מייל השחזור' ),
					self::bi( 'textarea', 'mail_body', 'גוף מייל השחזור', 4, '%s מוחלף בקישור.' ),

					self::bi( 'text', 'reset_title', 'כותרת קביעת סיסמה חדשה' ),
					self::bi( 'text', 'reset_cta', 'כפתור קביעת הסיסמה' ),

					self::bi( 'text', 'hello', 'שורת הפתיחה למי שמחובר', 3, '%s מוחלף בשם.' ),
					self::bi( 'text', 'signout', 'קישור ההתנתקות' ),
					self::bi( 'text', 'link_title', 'כותרת הקישור האישי' ),
					self::bi( 'text', 'link_lead', 'הסבר מתחת לקישור האישי', 3 ),
					self::bi( 'text', 'link_count', 'תווית מונה ההצטרפויות דרך הקישור' ),
					self::bi( 'text', 'copy_cta', 'כפתור ההעתקה' ),
					self::bi( 'text', 'copied', 'הכפתור אחרי ההעתקה' ),

					self::bi( 'text', 'groups_title', 'כותרת הקבוצות שלי' ),
					self::bi( 'text', 'groups_empty', 'טקסט כשאין קבוצות', 3 ),
					self::bi( 'text', 'groups_open', 'כפתור פתיחת קבוצה חדשה' ),
					self::bi( 'text', 'group_manage', 'כפתור ניהול הקבוצה' ),
					self::bi( 'text', 'group_view', 'כפתור צפייה בקבוצה' ),
					self::bi( 'text', 'group_progress', 'תווית ההתקדמות', 3, '%1$s = כמה נרות, %2$s = היעד.' ),
					self::bi( 'text', 'manage_title', 'כותרת מסך עריכת הקבוצה' ),
					self::bi( 'text', 'manage_save', 'כפתור השמירה' ),
					self::bi( 'text', 'manage_saved', 'הודעה אחרי שמירה' ),
					self::bi( 'text', 'manage_requeued', 'הודעה כששינוי טקסט החזיר את הקבוצה לאישור', 3 ),
					self::bi( 'text', 'manage_back', 'קישור חזרה לאיזור האישי' ),

					self::bi( 'text', 'password_title', 'כותרת החלפת הסיסמה' ),
					self::bi( 'text', 'f_password_current', 'שדה — הסיסמה הנוכחית' ),
					self::bi( 'text', 'password_cta', 'כפתור החלפת הסיסמה' ),
					self::bi( 'text', 'password_saved', 'הודעה אחרי החלפת סיסמה' ),

					self::bi( 'text', 'err_generic', 'שגיאה — כללית' ),
					self::bi( 'text', 'err_email', 'שגיאה — כתובת שאינה תקינה' ),
					self::bi( 'text', 'err_short', 'שגיאה — סיסמה קצרה מדי' ),
					self::bi( 'text', 'err_taken', 'שגיאה — הכתובת כבר רשומה' ),
					self::bi( 'text', 'err_credentials', 'שגיאה — כתובת או סיסמה שגויים' ),
					self::bi( 'text', 'err_rate', 'שגיאה — יותר מדי ניסיונות' ),
					self::bi( 'text', 'err_token', 'שגיאה — קישור שחזור שפג או אינו תקין' ),
				)
			),
			'groups'   => self::section(
				'עמוד הקבוצות',
				'groups',
				array(
					self::checkbox( 'open_on', 'לאפשר לגולשים לפתוח קבוצה', 'כיבוי משאיר את הקבוצות הקיימות ואת הארכיון, ומסיר את הטופס.' ),
					self::checkbox( 'auto_approve', 'קבוצה חדשה עולה לאוויר מיד', 'כבוי (מומלץ) = כל קבוצה ממתינה לאישור במסך "קבוצות לאישור", ובינתיים רואה אותה רק מי שפתח אותה. הטקסט בקבוצה נכתב בידי גולשים ומוצג תחת השם של המיזם.' ),
					self::bi( 'text', 'eyebrow', 'שורת פתיחה קטנה' ),
					self::bi( 'text', 'title', 'כותרת העמוד' ),
					self::bi( 'textarea', 'lead', 'פסקת פתיחה', 3 ),
					self::bi( 'text', 'open_cta', 'כפתור פתיחת קבוצה' ),
					self::bi( 'text', 'archive_title', 'כותרת רשימת הקבוצות' ),
					self::bi( 'text', 'empty', 'טקסט כשאין עדיין קבוצות' ),
					self::bi( 'text', 'card_progress', 'תווית ההתקדמות בכרטיס', 3, '%1$s = כמה נרות, %2$s = היעד.' ),
					self::bi( 'text', 'card_cta', 'כפתור הכניסה לקבוצה' ),

					self::bi( 'text', 'form_title', 'כותרת הטופס' ),
					self::bi( 'textarea', 'form_lead', 'פסקה מעל הטופס', 3 ),
					self::bi( 'text', 'f_title', 'שדה — שם הקבוצה' ),
					self::bi( 'text', 'f_title_help', 'שדה — שם הקבוצה, הסבר' ),
					self::bi( 'text', 'f_occasion', 'שדה — לכבוד מה' ),
					self::bi( 'text', 'f_honouree', 'שדה — שם האדם' ),
					self::bi( 'text', 'f_story', 'שדה — כמה מילים' ),
					self::bi( 'text', 'f_target', 'שדה — היעד' ),
					self::bi( 'text', 'f_target_help', 'שדה — היעד, הסבר' ),
					self::bi( 'text', 'f_artwork', 'שדה — צורת היצירה' ),
					self::bi( 'text', 'f_owner', 'שדה — השם שלך' ),
					self::bi( 'text', 'f_email', 'שדה — המייל שלך' ),
					self::bi( 'text', 'f_email_help', 'שדה — המייל שלך, הסבר' ),
					self::bi( 'text', 'submit', 'כפתור השליחה' ),

					self::bi( 'text', 'occ_none', 'לכבוד מה — בלי הקדשה' ),
					self::bi( 'text', 'occ_refua', 'לכבוד מה — לרפואת' ),
					self::bi( 'text', 'occ_zechut', 'לכבוד מה — לזכות' ),
					self::bi( 'text', 'occ_iluy', 'לכבוד מה — לעילוי נשמת' ),
					self::bi( 'text', 'occ_kavod', 'לכבוד מה — לכבוד' ),
					self::bi( 'text', 'occ_zivug', 'לכבוד מה — לזיווג' ),

					self::bi( 'text', 'done_title', 'אחרי הפתיחה — כותרת' ),
					self::bi( 'textarea', 'done_body', 'אחרי הפתיחה — טקסט', 3 ),
					self::bi( 'text', 'done_pending', 'אחרי הפתיחה — הודעת המתנה לאישור' ),
					self::bi( 'text', 'done_link', 'אחרי הפתיחה — תווית הקישור לשיתוף' ),
					self::bi( 'text', 'done_manage', 'אחרי הפתיחה — תווית הקישור הפרטי לניהול' ),

					self::bi( 'text', 'single_lights', 'בעמוד הקבוצה — תווית מספר הנרות' ),
					self::bi( 'text', 'single_pct', 'בעמוד הקבוצה — תווית האחוז' ),
					self::bi( 'text', 'single_supporters', 'בעמוד הקבוצה — כותרת רשימת המצטרפים' ),
					self::bi( 'text', 'single_anon', 'בעמוד הקבוצה — מי שביקש שלא להופיע בשם' ),
					self::bi( 'text', 'single_first', 'בעמוד הקבוצה — כשאף אחד עדיין לא הצטרף' ),
					self::bi( 'text', 'single_pending_join', 'בעמוד הקבוצה — הסבר שאפשר להצטרף כבר עכשיו', 3, 'מוצג בקבוצה שממתינה לאישור. ההצטרפות פתוחה גם אז, והנרות נספרים.' ),
					self::bi( 'text', 'single_target', 'בעמוד הקבוצה — תווית היעד' ),
					self::bi( 'text', 'single_cta', 'בעמוד הקבוצה — כפתור ההצטרפות' ),
					self::bi( 'text', 'single_share', 'בעמוד הקבוצה — כפתור השיתוף' ),
					self::bi( 'text', 'single_opened_by', 'בעמוד הקבוצה — "נפתחה על ידי"' ),
					self::bi( 'text', 'single_also', 'בעמוד הקבוצה — המשפט על היצירה הכללית' ),
					self::bi( 'text', 'single_back', 'בעמוד הקבוצה — חזרה לרשימה' ),
					self::bi( 'text', 'wa_message', 'נוסח ההודעה בוואטסאפ', 3, '%s מוחלף בקישור הקבוצה.' ),

					self::bi( 'text', 'state_pending', 'שלט מצב — ממתינה לאישור' ),
					self::bi( 'text', 'state_closed', 'שלט מצב — הקבוצה נסגרה' ),
					self::bi( 'text', 'state_rejected', 'שלט מצב — הקבוצה לא אושרה' ),

					self::bi( 'text', 'err_generic', 'שגיאה — כללית' ),
					self::bi( 'text', 'err_title', 'שגיאה — חסר שם לקבוצה' ),
					self::bi( 'text', 'err_email', 'שגיאה — מייל לא תקין' ),
					self::bi( 'text', 'err_rate', 'שגיאה — יותר מדי קבוצות מאותו מחשב' ),
					self::bi( 'text', 'err_closed', 'שגיאה — פתיחת קבוצות סגורה' ),
				)
			),
			'about'    => self::section(
				'עמוד "על המיזם"',
				'about',
				array(
					self::bi( 'text', 'eyebrow', 'שורת פתיחה קטנה' ),
					self::bi( 'text', 'title', 'כותרת ראשית' ),
					self::bi( 'textarea', 'lead', 'פסקת פתיחה', 4 ),
					self::bi( 'text', 'verse', 'פסוק או ציטוט' ),
					self::bi( 'text', 'verse_source', 'מקור הציטוט' ),
					self::repeater(
						'blocks',
						'פרקי העמוד',
						'title_he',
						array(
							self::text( 'title_he', 'כותרת (עברית)' ),
							self::text( 'title_en', 'כותרת (English)' ),
							self::textarea( 'body_he', 'טקסט (עברית)', 5 ),
							self::textarea( 'body_en', 'טקסט (English)', 5 ),
						),
						'שורה ריקה בטקסט פותחת פסקה חדשה.'
					),
					self::bi( 'text', 'cta', 'כפתור החזרה לקמפיין' ),
					self::bi( 'text', 'back', 'קישור חזרה בכותרת' ),
				)
			),
			'auth'     => self::section(
				'12 · התחברות וחשבונות',
				'home',
				array(
					self::checkbox( 'login_enabled', 'להפעיל התחברות עם גוגל', 'הכפתור מוצג רק כששני המפתחות למטה מלאים. בלי התחברות האתר עובד בדיוק כמו קודם.' ),
					self::text( 'google_client_id', 'Google Client ID', 'מתוך Google Cloud › APIs & Services › Credentials › OAuth 2.0 Client ID (סוג Web application).', true ),
					self::text( 'google_client_secret', 'Google Client Secret', 'אפשר להגדיר במקום זה קבועים MSL_GOOGLE_CLIENT_ID ו-MSL_GOOGLE_CLIENT_SECRET בקובץ wp-config.php — זה עדיף, כי אז גיבוי של בסיס הנתונים לא נושא איתו את המפתחות.', true ),
					self::bi( 'text', 'sign_in', 'כפתור ההתחברות' ),
					self::bi( 'text', 'sign_out', 'קישור ההתנתקות' ),
					self::bi( 'text', 'area_cta', 'קישור לאיזור האישי בתפריט המשתמש' ),
					self::bi( 'text', 'signed_in_as', 'תווית "מחובר/ת בתור"' ),
					self::bi( 'text', 'link_locked', 'טקסט במקום הקישור האישי כשלא מחוברים' ),
					self::bi( 'text', 'link_locked_cta', 'כפתור ההתחברות שליד הטקסט הזה' ),
					self::bi( 'textarea', 'link_join', 'טקסט במקום הקישור האישי כשאין התחברות', 2, 'מוצג כשההתחברות עם גוגל כבויה — אז הדרך היחידה לקבל קישור היא להצטרף.' ),
					self::bi( 'text', 'link_join_cta', 'כפתור ההצטרפות שליד הטקסט הזה' ),
					self::bi( 'textarea', 'err_state', 'הודעה כשההתחברות נכשלה', 2 ),
					self::bi( 'text', 'invite_title', 'פופאפ הגיוס — כותרת' ),
					self::bi( 'textarea', 'invite_body', 'פופאפ הגיוס — טקסט', 3 ),
					self::bi( 'text', 'invite_cta', 'פופאפ הגיוס — כפתור ראשי' ),
					self::bi( 'text', 'invite_dismiss', 'פופאפ הגיוס — כפתור משני' ),
					self::checkbox( 'remind_on', 'להציג חלון תזכורת כשגולש עוזב', 'עולה פעם אחת כשנראה שהגולש עוזב את העמוד, ומציע תזכורת אחת לפני כניסת השבת. מי שכבר נרשם או שסגר אותו לא יראה אותו שוב.' ),
					self::number( 'remind_idle', 'שניות חוסר פעילות שיפתחו אותו במובייל', 0, 600, 'בנייד אין עכבר שיוצא מהחלון, אז חוסר פעילות הוא הסימן. אפס = רק במחשב.' ),
					self::bi( 'text', 'remind_title', 'חלון התזכורת — כותרת' ),
					self::bi( 'textarea', 'remind_body', 'חלון התזכורת — טקסט', 3 ),
					self::bi( 'text', 'remind_with', 'חלון התזכורת — תווית "האור שבחרת"' ),
					self::bi( 'text', 'remind_name', 'חלון התזכורת — תווית שם' ),
					self::bi( 'text', 'remind_email', 'חלון התזכורת — תווית מייל' ),
					self::bi( 'text', 'remind_cta', 'חלון התזכורת — כפתור' ),
					self::bi( 'text', 'remind_dismiss', 'חלון התזכורת — כפתור משני' ),
					self::bi( 'textarea', 'remind_done', 'חלון התזכורת — הודעה אחרי הרשמה', 2 ),
					self::bi( 'text', 'remind_err', 'חלון התזכורת — שגיאת מייל' ),
					self::number( 'invite_delay', 'שניות עד שהפופאפ עולה', 0, 120, 'אפס = מיד עם טעינת העמוד. חמש שניות נותנות לגולש לראות את העמוד קודם.' ),
					self::number( 'invite_days', 'ימים עד שהפופאפ יחזור למי שסגר אותו', 0, 365, 'אפס = יעלה בכל טעינה. זה מעצבן; שבעה ימים זה סביר.' ),
				)
			),
			'screens'  => self::section(
				'11 · מסכי היצירה, הקיר והשיתוף',
				'home',
				array(
					self::bi( 'text', 'back', 'כפתור חזרה' ),
					self::bi( 'text', 'candles_word', 'המילה "נרות"' ),
					self::bi( 'textarea', 'art_summary', 'תקציר טקסטואלי של היצירה', 3, 'נקרא על ידי קוראי מסך במקום הקנבס. %1$s = מספר הנרות, %2$d = אחוז ההשלמה.' ),
					self::bi( 'textarea', 'art_hint_zoom', 'רמז — לפני הגדלה', 2 ),
					self::bi( 'textarea', 'art_hint_pan', 'רמז — אחרי הגדלה', 2 ),
					self::bi( 'textarea', 'art_hint_pick', 'רמז — אחרי בחירת נר', 2 ),
					self::bi( 'textarea', 'wall_hint', 'רמז בקיר הנרות', 2 ),
					self::bi( 'textarea', 'wall_hint_pick', 'רמז בקיר אחרי בחירת נר', 2 ),
					self::bi( 'text', 'my_candle', 'כפתור "הנר שלי" ביצירה' ),
					self::bi( 'text', 'pick_anon', 'כרטיס הנר — מדליק בעילום שם' ),
					self::bi( 'text', 'pick_anon_sub', 'כרטיס הנר — שורה שנייה לעילום שם' ),
					self::bi( 'text', 'pick_none', 'כרטיס הנר — מדליק בלי שם שמור', 3, 'נרות שנספרו למניין לפני שהאתר עלה, ואין להם רשומה בבסיס הנתונים.' ),
					self::bi( 'text', 'pick_none_sub', 'כרטיס הנר — שורה שנייה בלי שם שמור' ),
					self::bi( 'text', 'light_mine', 'כפתור "להדליק נר משלי"' ),
					self::bi( 'text', 'zoom_in', 'תווית כפתור הגדלה' ),
					self::bi( 'text', 'zoom_out', 'תווית כפתור הקטנה' ),
					self::bi( 'text', 'wow_parts', 'מסך ההצטרפות — תווית המונה' ),
					self::bi( 'text', 'wow_title', 'מסך ההצטרפות — כותרת' ),
					self::bi( 'text', 'wow_line1', 'מסך ההצטרפות — לפני המספר' ),
					self::bi( 'text', 'wow_line2', 'מסך ההצטרפות — אחרי המספר' ),
					self::bi( 'text', 'wow_cta', 'מסך ההצטרפות — כפתור' ),
					self::bi( 'text', 'res_title', 'כרטיס השיתוף — כותרת' ),
					self::bi( 'text', 'my_thing_label', 'כרטיס השיתוף — תווית הבחירה' ),
					self::bi( 'text', 'we_are', 'כרטיס השיתוף — לפני המונה' ),
					self::bi( 'text', 'ask_others', 'כרטיס השיתוף — שאלת הסיום' ),
					self::bi( 'text', 'back_home', 'כרטיס השיתוף — כפתור חזרה' ),
					self::bi( 'text', 'close', 'תווית סגירת חלון' ),
				)
			),
		);
	}
}
