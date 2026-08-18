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
	 * @return array<string, mixed>
	 */
	private static function text( string $key, string $label, string $help = '' ): array {
		return self::field( 'text', $key, $label, '' !== $help ? array( 'help' => $help ) : array() );
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
	 * @return array<string, mixed>
	 */
	private static function checkbox( string $key, string $label ): array {
		return self::field( 'checkbox', $key, $label );
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
					self::bi( 'text', 'countdown_clock', 'ספירה לאחור — פחות מיממה', 3, '%1$s = שם הפרשה, %2$s = שעון HH:MM:SS.' ),
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
					self::select(
						'artwork',
						'צורת היצירה',
						array(
							'candles' => 'נרות שבת',
							'star'    => 'מגן דוד',
							'light'   => 'נקודת אור',
						)
					),
					self::text( 'accent', 'צבע האור ביצירה', 'קוד HEX. ברירת מחדל #FFB25C.' ),
					self::select(
						'candle_day',
						'יום כניסת השבת',
						array(
							'5' => 'שישי',
							'6' => 'שבת',
							'4' => 'חמישי',
						)
					),
					self::text( 'candle_time', 'שעת כניסת השבת', 'בפורמט HH:MM לפי אזור הזמן של האתר. ברירת מחדל 19:12.' ),
					self::number( 'countries', 'מספר מדינות', 0, 300, 'מוצג בסקשן "השבת הזאת" ובכותרת המפה.' ),
					self::number( 'cities', 'מספר ערים', 0, 100000 ),
					self::checkbox( 'closed', 'הקמפיין נסגר — לא ניתן להצטרף' ),
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
					self::repeater(
						'collage',
						'תמונות הקולאז׳',
						'alt_he',
						array(
							self::image( 'image', 'תמונה' ),
							self::text( 'alt_he', 'טקסט חלופי (עברית)' ),
							self::text( 'alt_en', 'טקסט חלופי (English)' ),
						),
						'שמונה משבצות מוצגות בכל רגע, והן מתחלפות בין התמונות שברשימה. פחות משמונה תמונות = המשבצות הריקות לא מוצגות כלל.'
					),
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
						'המשהו שאפשר לבחור',
						'label_he',
						array(
							self::text( 'label_he', 'תווית (עברית)' ),
							self::text( 'label_en', 'תווית (English)' ),
							self::checkbox( 'is_other', 'זו האפשרות "משהו אחר" — פותחת שדה טקסט חופשי' ),
						),
						'אפשר לבחור עד שלוש אפשרויות. השורה המסומנת "משהו אחר" נפרשת על שתי עמודות.'
					),
					self::bi( 'text', 'other_ph', 'שדה "משהו אחר" — טקסט מוביל' ),
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
					self::bi( 'text', 'err_name', 'שגיאה — שם חסר' ),
					self::bi( 'text', 'err_city', 'שגיאה — עיר חסרה' ),
					self::bi( 'text', 'err_email', 'שגיאה — אימייל לא תקין' ),
					self::bi( 'text', 'err_phone', 'שגיאה — טלפון לא תקין' ),
					self::bi( 'text', 'err_generic', 'שגיאה — שליחה נכשלה' ),
					self::bi( 'text', 'err_duplicate', 'שגיאה — כבר הצטרפת' ),
					self::bi( 'text', 'err_rate', 'שגיאה — יותר מדי בקשות' ),
					self::bi( 'text', 'err_closed', 'שגיאה — הקמפיין נסגר' ),
					self::bi( 'text', 'sending', 'הודעת שליחה' ),
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
