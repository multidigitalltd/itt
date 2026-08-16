<?php
/**
 * Editable field schema.
 *
 * A single declarative description of every editable field drives three things:
 * the meta boxes in the page editor, the sanitisation on save, and the shape of
 * the data the templates read. Adding a field here is the only change needed.
 *
 * @package ITT_Landing
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Declarative field definitions, grouped by section.
 */
final class ITT_Fields {

	/**
	 * Colour/style variants shared by several card grids.
	 */
	private const VARIANTS = array(
		'purple' => 'סגול',
		'cyan'   => 'תכלת',
		'gold'   => 'זהב',
		'orange' => 'כתום',
		'gray'   => 'אפור',
	);

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
	 * @param string $template Template key: 'landing' or 'thank-you'.
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

	/**
	 * Build the schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function build(): array {
		return array(
			'chrome'   => self::section(
				'כותרת עליונה, פוטר וסרגל צף',
				'landing',
				array(
					self::text( 'topbar_text', 'טקסט הפס העליון' ),
					self::text( 'topbar_cta', 'קישור בפס העליון' ),
					self::image( 'logo', 'לוגו', 'ברירת מחדל: הלוגו המצורף לתבנית.' ),
					self::text( 'logo_alt', 'טקסט חלופי ללוגו' ),
					self::text( 'header_note', 'הערה בכותרת' ),
					self::text( 'phone', 'טלפון מרכזייה' ),
					self::text( 'header_cta', 'כפתור בכותרת' ),
					self::text( 'men_text', 'כפתור מעבר לדף גברים' ),
					self::url( 'men_url', 'כתובת דף הגברים', 'ריק = הכפתור לא מוצג.' ),
					self::url( 'whatsapp', 'קישור וואטסאפ' ),
					self::text( 'whatsapp_label', 'מספר וואטסאפ לתצוגה' ),
					self::email( 'email', 'אימייל' ),
					self::text( 'footer_tagline', 'שורת הפוטר' ),
					self::text( 'credit_text', 'קרדיט בפוטר', 'ריק = לא יוצג.' ),
					self::url( 'credit_url', 'קישור הקרדיט' ),
					self::text( 'sticky_text', 'טקסט הסרגל הצף' ),
					self::text( 'sticky_whatsapp', 'כפתור וואטסאפ בסרגל הצף' ),
					self::text( 'sticky_cta', 'כפתור ראשי בסרגל הצף' ),
				)
			),
			'hero'     => self::section(
				'01 · הירו',
				'landing',
				array(
					self::text( 'badge', 'תגית עליונה' ),
					self::rich( 'heading', 'כותרת ראשית (H1)' ),
					self::textarea( 'lead', 'פסקת פתיחה' ),
					self::text( 'cta_primary', 'כפתור ראשי' ),
					self::text( 'cta_whatsapp', 'כפתור וואטסאפ' ),
					self::text( 'note', 'הערה מתחת לכפתורים' ),
					self::image( 'image', 'תמונת הירו' ),
					self::text( 'image_alt', 'טקסט חלופי לתמונת הירו' ),
					self::repeater(
						'facts',
						'שלוש עובדות מפתח',
						array(
							self::select(
								'icon',
								'אייקון',
								array(
									'calendar' => 'לוח שנה',
									'pin'      => 'מיקום',
									'cap'      => 'כובע סטודנט',
								)
							),
							self::text( 'label', 'תווית' ),
							self::text( 'value', 'ערך' ),
						),
						'value'
					),
				)
			),
			'jump'     => self::section(
				'02 · ניתוב מהיר',
				'landing',
				array(
					self::text( 'heading', 'כותרת (H2)' ),
					self::repeater(
						'links',
						'כפתורי עוגן',
						array(
							self::text( 'text', 'טקסט' ),
							self::select(
								'anchor',
								'יעד',
								array(
									'syllabus' => 'סילבוס',
									'outcomes' => 'עם מה יוצאים',
									'voices'   => 'עדויות',
									'form'     => 'טופס',
								)
							),
							self::select(
								'variant',
								'סגנון',
								array(
									'cyan'         => 'תכלת',
									'purple'       => 'סגול',
									'orange-blink' => 'כתום מהבהב',
								)
							),
						),
						'text'
					),
				)
			),
			'pain'     => self::section(
				'03 · הכאב',
				'landing',
				array(
					self::rich( 'heading', 'כותרת (H2)' ),
					self::rich( 'lead', 'פסקת פתיחה', 4 ),
					self::repeater( 'cards', 'כרטיסי כאב', array( self::textarea( 'text', 'טקסט', 3 ) ), 'text' ),
					self::textarea( 'band_text', 'טקסט הפס הסגול' ),
					self::text( 'band_cta', 'כפתור בפס הסגול' ),
				)
			),
			'video'    => self::section(
				'04 · וידאו עדות וסליידר',
				'landing',
				array(
					self::text( 'badge', 'תגית' ),
					self::text( 'heading', 'כותרת (H2)' ),
					self::textarea( 'text', 'טקסט' ),
					self::image( 'poster', 'תמונת פתיח לוידאו' ),
					self::text( 'poster_alt', 'טקסט חלופי לתמונת הפתיח' ),
					self::video( 'video_file', 'קובץ וידאו מהמדיה' ),
					self::url( 'video_url', 'או קישור לוידאו', 'YouTube או Vimeo. אם הועלה קובץ וידאו הוא קודם. ריק בשניהם = מוצגת תמונה בלבד ללא כפתור נגן.' ),
					self::text( 'slider_heading', 'כותרת הסליידר (H3)' ),
					self::repeater(
						'slides',
						'סרטוני המלצות (אפשר להוסיף כמה שרוצים)',
						array(
							self::image( 'image', 'תמונת פתיח' ),
							self::text( 'image_alt', 'טקסט חלופי' ),
							self::text( 'name', 'שם התלמידה' ),
							self::text( 'quote', 'משפט מפתח' ),
							self::video( 'video_file', 'קובץ וידאו מהמדיה' ),
							self::url( 'video_url', 'או קישור לוידאו (YouTube / Vimeo)' ),
						),
						'name'
					),
				)
			),
			'method'   => self::section(
				'05 · השיטה',
				'landing',
				array(
					self::text( 'heading', 'כותרת (H2)' ),
					self::textarea( 'lead', 'פסקה ראשונה', 4 ),
					self::rich( 'lead_2', 'פסקה שנייה', 4 ),
					self::text( 'equation_number', 'משוואה · מספר' ),
					self::text( 'equation_label', 'משוואה · תווית' ),
					self::text( 'equation_mid', 'משוואה · אמצע' ),
					self::text( 'equation_mid_sub', 'משוואה · אמצע, כיתוב' ),
					self::text( 'equation_result', 'משוואה · תוצאה' ),
					self::text( 'equation_result_sub', 'משוואה · תוצאה, כיתוב' ),
					self::text( 'box_badge', 'תיבה סגולה · תגית' ),
					self::textarea( 'box_heading', 'תיבה סגולה · כותרת' ),
					self::repeater(
						'tools',
						'כלים בפרוטוקול',
						array(
							self::text( 'text', 'שם הכלי' ),
							self::select(
								'dot',
								'צבע נקודה',
								array(
									'cyan'   => 'תכלת',
									'orange' => 'כתום',
									'gold'   => 'זהב',
									'green'  => 'ירוק',
								)
							),
							self::checkbox( 'ltr', 'שם באנגלית (LTR)' ),
						),
						'text'
					),
				)
			),
			'skills'   => self::section(
				'06 · יכולות וציטוטים',
				'landing',
				array(
					self::text( 'heading', 'כותרת (H2)' ),
					self::repeater(
						'cards',
						'כרטיסי יכולות',
						array(
							self::text( 'badge', 'תגית' ),
							self::textarea( 'text', 'טקסט', 3 ),
							self::select( 'variant', 'סגנון', self::VARIANTS ),
						),
						'badge'
					),
					self::repeater(
						'quotes',
						'ציטוטים מתחלפים',
						array(
							self::textarea( 'text', 'ציטוט', 4 ),
							self::text( 'author', 'שם' ),
						),
						'author'
					),
				)
			),
			'outcomes' => self::section(
				'07 · עם מה יוצאים',
				'landing',
				array(
					self::text( 'badge', 'תגית' ),
					self::text( 'heading', 'כותרת (H2)' ),
					self::repeater(
						'cards',
						'מסלולי תוצאה',
						array(
							self::text( 'title', 'כותרת' ),
							self::textarea( 'text', 'טקסט', 3 ),
							self::select( 'variant', 'סגנון', self::VARIANTS ),
							self::lines( 'tags', 'תגיות', 'תגית אחת בכל שורה.' ),
						),
						'title'
					),
				)
			),
			'voices'   => self::section(
				'08 · עדויות כתובות',
				'landing',
				array(
					self::rich( 'heading', 'כותרת (H2)' ),
					self::repeater(
						'cards',
						'עדויות',
						array(
							self::text( 'title', 'כותרת קטנה' ),
							self::textarea( 'text', 'העדות', 5 ),
							self::text( 'author', 'שם' ),
							self::select( 'variant', 'צבע הכותרת', self::VARIANTS ),
						),
						'author'
					),
				)
			),
			'gallery'  => self::section(
				'08b · גלריה',
				'landing',
				array(
					self::text( 'heading', 'כותרת (H2)' ),
					self::textarea( 'lead', 'טקסט' ),
					self::repeater(
						'images',
						'תמונות (הראשונה גדולה)',
						array(
							self::image( 'image', 'תמונה' ),
							self::text( 'alt', 'טקסט חלופי' ),
						),
						'alt'
					),
				)
			),
			'syllabus' => self::section(
				'09 · סילבוס',
				'landing',
				array(
					self::text( 'badge', 'תגית' ),
					self::rich( 'heading', 'כותרת (H2)' ),
					self::repeater(
						'stats',
						'מספרים',
						array(
							self::text( 'number', 'מספר' ),
							self::text( 'label', 'תווית' ),
						),
						'label'
					),
					self::text( 'tab_a', 'שם טאב א׳' ),
					self::text( 'tab_b', 'שם טאב ב׳' ),
					self::repeater(
						'lessons_a',
						'שיעורי סמסטר א׳',
						array(
							self::text( 'title', 'שם השיעור' ),
							self::lines( 'bullets', 'נקודות', 'נקודה אחת בכל שורה.' ),
						),
						'title'
					),
					self::repeater(
						'lessons_b',
						'שיעורי סמסטר ב׳',
						array(
							self::text( 'title', 'שם השיעור' ),
							self::lines( 'bullets', 'נקודות', 'נקודה אחת בכל שורה.' ),
						),
						'title'
					),
					self::text( 'bonus_title', 'תיבת בונוס · כותרת' ),
					self::lines( 'bonus_lines', 'תיבת בונוס · שורות' ),
					self::text( 'band_title', 'פס תכלת · כותרת' ),
					self::text( 'band_text', 'פס תכלת · טקסט' ),
					self::text( 'band_cta', 'פס תכלת · כפתור' ),
				)
			),
			'envelope' => self::section(
				'10 · המעטפת',
				'landing',
				array(
					self::rich( 'heading', 'כותרת (H2)' ),
					self::repeater(
						'cards',
						'כרטיסי מעטפת',
						array(
							self::text( 'badge', 'תגית' ),
							self::textarea( 'text', 'טקסט', 4 ),
							self::select( 'variant', 'סגנון', self::VARIANTS ),
						),
						'badge'
					),
				)
			),
			'doctor'   => self::section(
				'11 · ד״ר אמיר קוליק',
				'landing',
				array(
					self::text( 'heading', 'כותרת (H2)' ),
					self::textarea( 'text', 'פסקה ראשונה', 4 ),
					self::textarea( 'text_2', 'פסקה שנייה', 4 ),
					self::image( 'image', 'תמונה' ),
					self::text( 'image_alt', 'טקסט חלופי' ),
					self::repeater(
						'stats',
						'מספרים',
						array(
							self::text( 'number', 'מספר' ),
							self::text( 'label', 'תווית' ),
							self::select( 'variant', 'צבע', self::VARIANTS ),
						),
						'label'
					),
				)
			),
			'fit'      => self::section(
				'12 · למי מתאים',
				'landing',
				array(
					self::text( 'badge', 'תגית' ),
					self::text( 'heading', 'כותרת (H2)' ),
					self::repeater(
						'cards',
						'למי כן',
						array(
							self::text( 'title', 'כותרת' ),
							self::textarea( 'text', 'טקסט', 4 ),
							self::select( 'variant', 'סגנון', self::VARIANTS ),
						),
						'title'
					),
					self::text( 'cta_title', 'כרטיס CTA · כותרת' ),
					self::textarea( 'cta_text', 'כרטיס CTA · טקסט' ),
					self::text( 'cta_label', 'כרטיס CTA · כפתור' ),
					self::text( 'exclude_title', 'למי לא · כותרת' ),
					self::repeater( 'exclude', 'למי לא · פריטים', array( self::textarea( 'text', 'טקסט', 3 ) ), 'text' ),
				)
			),
			'bonuses'  => self::section(
				'13 · בונוסים',
				'landing',
				array(
					self::text( 'badge', 'תגית' ),
					self::rich( 'heading', 'כותרת (H2)' ),
					self::checkbox( 'show_values', 'להציג תגיות שווי' ),
					self::rich( 'subtitle', 'שורת השווי הכולל' ),
					self::repeater(
						'cards',
						'בונוסים',
						array(
							self::text( 'title', 'כותרת' ),
							self::text( 'value', 'שווי', 'ריק = ללא תגית שווי.' ),
							self::textarea( 'text', 'טקסט', 3 ),
						),
						'title'
					),
					self::rich( 'total_title', 'פס סיכום · כותרת' ),
					self::text( 'total_text', 'פס סיכום · טקסט' ),
					self::text( 'total_cta', 'פס סיכום · כפתור' ),
					self::text( 'urgency_title', 'פס דחיפות · כותרת' ),
					self::text( 'urgency_text', 'פס דחיפות · טקסט' ),
					self::text( 'urgency_cta', 'פס דחיפות · כפתור' ),
				)
			),
			'process'  => self::section(
				'14 · תהליך קבלה',
				'landing',
				array(
					self::text( 'heading', 'כותרת (H2)' ),
					self::repeater(
						'steps',
						'שלבים',
						array(
							self::text( 'title', 'כותרת' ),
							self::textarea( 'text', 'טקסט', 3 ),
						),
						'title'
					),
				)
			),
			'faq'      => self::section(
				'15 · שאלות ותשובות',
				'landing',
				array(
					self::text( 'heading', 'כותרת (H2)' ),
					self::repeater(
						'items',
						'שאלות',
						array(
							self::text( 'question', 'שאלה' ),
							self::textarea( 'answer', 'תשובה', 6 ),
						),
						'question'
					),
				)
			),
			'form'     => self::section(
				'16 · סגירה וטופס',
				'landing',
				array(
					self::rich( 'heading', 'כותרת (H2)' ),
					self::textarea( 'lead', 'טקסט' ),
					self::lines( 'bullets', 'נקודות', 'נקודה אחת בכל שורה.' ),
					self::text( 'form_title', 'טופס · כותרת' ),
					self::text( 'form_subtitle', 'טופס · כיתוב' ),
					self::text( 'label_name', 'תווית שדה שם' ),
					self::text( 'label_phone', 'תווית שדה טלפון' ),
					self::text( 'label_email', 'תווית שדה אימייל' ),
					self::checkbox( 'show_email', 'להציג שדה אימייל' ),
					self::text( 'label_message', 'תווית שדה הודעה' ),
					self::checkbox( 'show_message', 'להציג שדה הודעה' ),
					self::text( 'submit', 'כפתור שליחה' ),
					self::text( 'privacy', 'הערת פרטיות' ),
					self::textarea( 'consent_text', 'טקסט אישור התקנון' ),
					self::url( 'terms_url', 'קישור לתנאי השימוש', 'הטקסט [terms]…[/terms] יהפוך לקישור לכתובת הזו. ריק = יוצג כטקסט רגיל.' ),
					self::url( 'privacy_url', 'קישור למדיניות הפרטיות', 'הטקסט [privacy]…[/privacy] יהפוך לקישור לכתובת הזו.' ),
				)
			),
			'ty_hero'  => self::section(
				'01 · תודה',
				'thank-you',
				array(
					self::text( 'heading', 'כותרת ראשית (H1)' ),
					self::textarea( 'lead', 'טקסט', 4 ),
					self::text( 'note', 'תגית תחתונה' ),
				)
			),
			'ty_steps' => self::section(
				'02 · מה קורה עכשיו',
				'thank-you',
				array(
					self::text( 'heading', 'כותרת (H2)' ),
					self::repeater(
						'steps',
						'שלבים',
						array(
							self::text( 'title', 'כותרת' ),
							self::textarea( 'text', 'טקסט', 3 ),
						),
						'title'
					),
					self::textarea( 'tip', 'טיפ' ),
				)
			),
			'ty_links' => self::section(
				'03 · בינתיים',
				'thank-you',
				array(
					self::text( 'heading', 'כותרת (H2)' ),
					self::text( 'lead', 'כיתוב' ),
					self::repeater(
						'cards',
						'קישורים',
						array(
							self::text( 'title', 'כותרת' ),
							self::textarea( 'text', 'טקסט', 3 ),
							self::text( 'cta', 'טקסט הקישור' ),
							self::url( 'url', 'כתובת', 'עוגן כמו ‎#syllabus יופנה אוטומטית לדף הנחיתה.' ),
							self::select( 'variant', 'סגנון', self::VARIANTS ),
						),
						'title'
					),
				)
			),
			'ty_quote' => self::section(
				'04 · ציטוט',
				'thank-you',
				array(
					self::textarea( 'text', 'ציטוט', 4 ),
					self::text( 'author', 'שם' ),
				)
			),
		);
	}

	/**
	 * Wrap a field list into a section definition.
	 *
	 * @param string                          $label    Human label shown on the meta box.
	 * @param string                          $template Template key the section belongs to.
	 * @param array<int, array<string, mixed>> $fields   Field definitions.
	 * @return array<string, mixed>
	 */
	private static function section( string $label, string $template, array $fields ): array {
		return array(
			'label'    => $label,
			'template' => $template,
			'fields'   => $fields,
		);
	}

	/**
	 * Single-line text field.
	 *
	 * @param string $key   Field key.
	 * @param string $label Field label.
	 * @param string $help  Optional help text.
	 * @return array<string, mixed>
	 */
	private static function text( string $key, string $label, string $help = '' ): array {
		return compact( 'key', 'label', 'help' ) + array( 'type' => 'text' );
	}

	/**
	 * Multi-line plain text field.
	 *
	 * @param string $key   Field key.
	 * @param string $label Field label.
	 * @param int    $rows  Textarea rows.
	 * @return array<string, mixed>
	 */
	private static function textarea( string $key, string $label, int $rows = 3 ): array {
		return compact( 'key', 'label', 'rows' ) + array(
			'type' => 'textarea',
			'help' => '',
		);
	}

	/**
	 * Text field that accepts the inline emphasis tokens.
	 *
	 * @param string $key   Field key.
	 * @param string $label Field label.
	 * @param int    $rows  Textarea rows.
	 * @return array<string, mixed>
	 */
	private static function rich( string $key, string $label, int $rows = 3 ): array {
		return compact( 'key', 'label', 'rows' ) + array(
			'type' => 'rich',
			'help' => 'תגיות עיצוב: [hl:gold]מילה[/hl] (גם cyan / orange), [pop]מילה[/pop], [blink]מילה[/blink], [value]סכום[/value], [gold]טקסט[/gold], [count:26], [count:4600,comma].',
		);
	}

	/**
	 * Newline-separated list, stored as one string.
	 *
	 * @param string $key   Field key.
	 * @param string $label Field label.
	 * @param string $help  Optional help text.
	 * @return array<string, mixed>
	 */
	private static function lines( string $key, string $label, string $help = 'פריט אחד בכל שורה.' ): array {
		return compact( 'key', 'label', 'help' ) + array(
			'type' => 'lines',
			'rows' => 4,
		);
	}

	/**
	 * URL field.
	 *
	 * @param string $key   Field key.
	 * @param string $label Field label.
	 * @param string $help  Optional help text.
	 * @return array<string, mixed>
	 */
	private static function url( string $key, string $label, string $help = '' ): array {
		return compact( 'key', 'label', 'help' ) + array( 'type' => 'url' );
	}

	/**
	 * Email field.
	 *
	 * @param string $key   Field key.
	 * @param string $label Field label.
	 * @return array<string, mixed>
	 */
	private static function email( string $key, string $label ): array {
		return compact( 'key', 'label' ) + array(
			'type' => 'email',
			'help' => '',
		);
	}

	/**
	 * Media-library image field, stored as an attachment ID.
	 *
	 * @param string $key   Field key.
	 * @param string $label Field label.
	 * @param string $help  Optional help text.
	 * @return array<string, mixed>
	 */
	private static function image( string $key, string $label, string $help = 'ריק = התמונה המצורפת לתבנית.' ): array {
		return compact( 'key', 'label', 'help' ) + array( 'type' => 'image' );
	}

	/**
	 * Media-library video field, stored as an attachment ID.
	 *
	 * @param string $key   Field key.
	 * @param string $label Field label.
	 * @param string $help  Optional help text.
	 * @return array<string, mixed>
	 */
	private static function video( string $key, string $label, string $help = 'העלאה ישירה לספריית המדיה (mp4 / webm).' ): array {
		return compact( 'key', 'label', 'help' ) + array( 'type' => 'video' );
	}

	/**
	 * Boolean checkbox, stored as 0/1.
	 *
	 * @param string $key   Field key.
	 * @param string $label Field label.
	 * @return array<string, mixed>
	 */
	private static function checkbox( string $key, string $label ): array {
		return compact( 'key', 'label' ) + array(
			'type' => 'checkbox',
			'help' => '',
		);
	}

	/**
	 * Select field with a fixed option list.
	 *
	 * @param string                $key     Field key.
	 * @param string                $label   Field label.
	 * @param array<string, string> $options Value => label pairs.
	 * @return array<string, mixed>
	 */
	private static function select( string $key, string $label, array $options ): array {
		return compact( 'key', 'label', 'options' ) + array(
			'type' => 'select',
			'help' => '',
		);
	}

	/**
	 * Repeatable group of fields.
	 *
	 * @param string                          $key         Field key.
	 * @param string                          $label       Field label.
	 * @param array<int, array<string, mixed>> $fields      Sub-field definitions.
	 * @param string                          $label_field Sub-field used as the row summary.
	 * @return array<string, mixed>
	 */
	private static function repeater( string $key, string $label, array $fields, string $label_field ): array {
		return compact( 'key', 'label', 'fields', 'label_field' ) + array(
			'type' => 'repeater',
			'help' => '',
		);
	}
}
