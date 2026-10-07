<?php

namespace Database\Seeders;

use App\Models\BookingShape;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * «أشكال الحجز» — the starting shapes, and every booking trade put on the one its pattern says.
 *
 * Add-only on purpose ([[seeder-must-withdraw]]): a shape that exists is never rewritten and a trade that already has
 * a shape is never moved, so what the admin curated on the page survives a re-seed. Only what is missing is added.
 */
class BookingShapesSeeder extends Seeder
{
    /** Shape → what it says; the rest comes from BookingShape::SETTINGS defaults. */
    private const SHAPES = [
        'hotel_rooms' => [
            'name_ar' => 'فندق — غرف بالليلة', 'name_en' => 'Hotel — rooms by the night', 'icon' => 'bed', 'pattern' => 'stay', 'sort_order' => 10,
            'description' => 'كل نوع غرفة قسم (الغرف الفردية، المزدوجة…) تحته غرفه بصورها وأسعارها؛ ثم نظام الوجبات (اختيار واحد) ثم التواريخ والإجمالي.',
            'settings' => ['layout' => 'sections', 'pick_order' => 'unit_then_dates', 'offer_day_use' => true, 'in_stay_requests' => true],
        ],
        'furnished_units' => [
            'name_ar' => 'شقق وشاليهات — قائمة وحدات', 'name_en' => 'Flats and chalets — a list of units', 'icon' => 'home', 'pattern' => 'stay', 'sort_order' => 20,
            'description' => 'وحداتٌ بصور كبيرة وسعر لليلة بلا أقسام؛ يختار الضيف التواريخ فتظهر المتاحة.',
            'settings' => ['layout' => 'flat', 'pick_order' => 'dates_then_unit', 'offer_day_use' => false, 'in_stay_requests' => false, 'ask_children' => false],
        ],
        'hourly_venue' => [
            'name_ar' => 'ملاعب وقاعات — بالساعة', 'name_en' => 'Pitches and halls — by the hour', 'icon' => 'clock', 'pattern' => 'duration', 'sort_order' => 30,
            'description' => 'أنواع (ملعب خماسي، قاعة صغيرة…) تحتها وحداتها؛ يختار الضيف اليوم والساعة والمدة.',
            'settings' => ['layout' => 'sections', 'pick_order' => 'dates_then_unit', 'ask_guest_counts' => false, 'ask_children' => false, 'offer_day_use' => false],
        ],
        'table' => [
            'name_ar' => 'طاولة', 'name_en' => 'Table', 'icon' => 'table', 'pattern' => 'table', 'sort_order' => 40,
            'description' => 'اليوم والوقت وعدد الأفراد؛ الطاولات المتاحة تظهر بسعتها.',
            'settings' => ['layout' => 'flat', 'show_photos' => false, 'show_price' => false, 'price_includes_features' => false, 'pick_order' => 'dates_then_unit', 'ask_children' => false, 'offer_day_use' => false],
        ],
        'appointment' => [
            'name_ar' => 'موعد', 'name_en' => 'Appointment', 'icon' => 'calendar', 'pattern' => 'appointment', 'sort_order' => 50,
            'description' => 'موعدٌ مع النشاط نفسه بلا وحدات: اليوم والوقت فقط.',
            'settings' => ['layout' => 'flat', 'show_photos' => false, 'show_price' => false, 'price_includes_features' => false, 'show_capacity' => false, 'show_availability' => false, 'pick_order' => 'dates_then_unit', 'ask_guest_counts' => false, 'ask_children' => false, 'offer_day_use' => false],
        ],
        'course' => [
            'name_ar' => 'كورس', 'name_en' => 'Course', 'icon' => 'book', 'pattern' => 'course', 'sort_order' => 60,
            'description' => 'التحاقٌ ممتد: فترة الكورس والمجموعة والمستوى.',
            'settings' => ['layout' => 'flat', 'show_photos' => false, 'show_capacity' => false, 'pick_order' => 'dates_then_unit', 'ask_guest_counts' => false, 'ask_children' => false, 'offer_day_use' => false],
        ],
    ];

    /** A pattern → the shape it starts on. */
    private const BY_PATTERN = [
        'stay' => 'hotel_rooms',
        'duration' => 'hourly_venue',
        'table' => 'table',
        'appointment' => 'appointment',
        'consultation' => 'appointment',
        'course' => 'course',
    ];

    /** Trades that are stays but not hotel rooms: a flat, a chalet, a rented unit. */
    private const FURNISHED_CHILDREN = [537, 552, 517, 518, 522];

    public function run(): void
    {
        $ids = [];

        foreach (self::SHAPES as $code => $row) {
            $shape = BookingShape::query()->where('code', $code)->first();

            if (! $shape) {
                $shape = BookingShape::create([
                    'code' => $code,
                    'name_ar' => $row['name_ar'],
                    'name_en' => $row['name_en'],
                    'icon' => $row['icon'],
                    'pattern' => $row['pattern'],
                    'description' => $row['description'],
                    'settings' => BookingShape::sanitize($row['settings'] + BookingShape::defaults()),
                    'is_active' => true,
                    'sort_order' => $row['sort_order'],
                ]);
            }

            $ids[$code] = (int) $shape->id;
        }

        $patterns = require __DIR__ . '/data/booking_patterns.php';
        $existing = DB::table('booking_shape_children')->pluck('child_id')->map(fn ($id) => (int) $id)->all();
        $liveChildren = DB::table('category_children_master')->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($patterns as $childId => $list) {
            $childId = (int) $childId;
            $first = (string) ($list[0] ?? '');

            if (in_array($childId, $existing, true) || ! in_array($childId, $liveChildren, true) || ! isset(self::BY_PATTERN[$first])) {
                continue;
            }

            $code = in_array($childId, self::FURNISHED_CHILDREN, true) && $first === 'stay' ? 'furnished_units' : self::BY_PATTERN[$first];

            DB::table('booking_shape_children')->insert([
                'child_id' => $childId, 'booking_shape_id' => $ids[$code], 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
