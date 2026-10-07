<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * «شكل الحجز» — how a booking trade page is drawn for the guest. The sibling of «أشكال المنيو»: the same page is
 * tried on a phone in the admin, assigned to trades, and the app draws what the shape says (the app knows no trade).
 *
 * The settings are a CLOSED list: a shape can switch these things and nothing else, so the app can honour every one
 * of them and an old app ignores a new key harmlessly.
 */
class BookingShape extends Model
{
    public const LAYOUT_SECTIONS = 'sections';

    public const LAYOUT_FLAT = 'flat';

    public const PICK_UNIT_THEN_DATES = 'unit_then_dates';

    public const PICK_DATES_THEN_UNIT = 'dates_then_unit';

    /**
     * key → label, kind and the value used when a shape has not said.
     *
     * @var array<string,array<string,mixed>>
     */
    public const SETTINGS = [
        'layout' => [
            'label' => 'تقسيم الصفحة',
            'hint' => 'أقسام: كل نوع (غرفة فردية، مزدوجة…) قسمٌ تحته وحداته بصورها وأسعارها — مثل أقسام المنيو. قائمة: وحداتٌ بلا أقسام.',
            'type' => 'select',
            'options' => [self::LAYOUT_SECTIONS => 'أقسام (نوع ← وحدات)', self::LAYOUT_FLAT => 'قائمة واحدة'],
            'default' => self::LAYOUT_SECTIONS,
        ],
        'show_photos' => ['label' => 'صور الوحدات', 'hint' => 'صورة الوحدة على بطاقتها.', 'type' => 'bool', 'default' => true],
        'show_price' => ['label' => 'سعر الوحدة على بطاقتها', 'hint' => 'سعر الليلة (أو الساعة) كما يراه الضيف قبل أن يختار.', 'type' => 'bool', 'default' => true],
        'price_includes_features' => ['label' => 'السعر يشمل ما تتميز به الوحدة', 'hint' => 'غرفة 206 تطل على المسبح: 800 سعر النوع + 150 الإطلالة = 950 على بطاقتها.', 'type' => 'bool', 'default' => true],
        'show_capacity' => ['label' => 'السعة', 'hint' => 'عدد الأفراد الذي تتسع له الوحدة.', 'type' => 'bool', 'default' => true],
        'show_availability' => ['label' => 'متاحة / غير متاحة', 'hint' => 'بعد أن يختار الضيف التواريخ.', 'type' => 'bool', 'default' => true],
        'pick_order' => [
            'label' => 'ترتيب الاختيار',
            'hint' => 'الوحدة أولًا ثم الإضافات (نظام الوجبات) ثم التواريخ والإجمالي — أو التواريخ أولًا فتظهر المتاحة فقط.',
            'type' => 'select',
            'options' => [self::PICK_UNIT_THEN_DATES => 'الوحدة ← الإضافات ← التواريخ', self::PICK_DATES_THEN_UNIT => 'التواريخ ← الوحدة ← الإضافات'],
            'default' => self::PICK_UNIT_THEN_DATES,
        ],
        'ask_guest_counts' => ['label' => 'عدد النزلاء', 'hint' => 'يُسأل الضيف عن عدد الأفراد.', 'type' => 'bool', 'default' => true],
        'ask_children' => ['label' => 'عدد الأطفال', 'hint' => 'سؤالٌ مستقلّ عن الأطفال.', 'type' => 'bool', 'default' => true],
        'offer_day_use' => ['label' => 'Day use', 'hint' => 'يُعرض الخيار على كل نوع أتاحه الفندق.', 'type' => 'bool', 'default' => false],
        'in_stay_requests' => ['label' => 'طلبات النزيل أثناء الإقامة', 'hint' => 'زرّا «بلّغ عن مشكلة» و«اطلب خدمة» على الحجز الجاري.', 'type' => 'bool', 'default' => false],
    ];

    protected $table = 'booking_shapes';

    protected $fillable = ['code', 'name_ar', 'name_en', 'icon', 'pattern', 'description', 'settings', 'is_active', 'sort_order'];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public static function defaults(): array
    {
        return array_map(fn ($def) => $def['default'], self::SETTINGS);
    }

    /** The settings this shape says, over the closed list defaults; nothing outside the list gets through. */
    public function resolvedSettings(): array
    {
        $saved = is_array($this->settings) ? $this->settings : [];

        return array_replace(self::defaults(), array_intersect_key($saved, self::SETTINGS));
    }

    /** Keeps only what the closed list knows, coerced to its type — the one door settings are written through. */
    public static function sanitize(array $input): array
    {
        $out = [];

        foreach (self::SETTINGS as $key => $def) {
            if ($def['type'] === 'bool') {
                $out[$key] = filter_var($input[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
            } else {
                $value = (string) ($input[$key] ?? $def['default']);
                $out[$key] = array_key_exists($value, $def['options'] ?? []) ? $value : $def['default'];
            }
        }

        return $out;
    }
}
