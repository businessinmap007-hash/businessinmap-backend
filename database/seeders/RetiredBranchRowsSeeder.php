<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Put back the branch rows the collapse retired and someone then deleted.
 *
 * `ServiceKindsCollapseSeeder::prune()` switches an emptied branch OFF and is
 * careful never to delete it, for a reason it states out loud: eight
 * pre-collapse seeders resolve a branch by KEY and file item types into it, and
 * `LegacyOptionGapsSeeder` died on a foreign key the day one went missing. The
 * menu branches show the intended end state — `restaurant_menu`, `supermarket`,
 * `fresh_market` and the rest are all still there with `is_active = 0`.
 *
 * Every one of booking's twelve is gone from the table instead. They were
 * removed by hand from the admin panel, which offers a delete the collapse
 * deliberately does not. Three seeders still name them —
 * `ServicesReformSeeder` (clinic), `CoworkingAndHotelUnitsSeeder` (hotel),
 * `ConsultingConsolidationSeeder` (business_consulting) — and two of the three
 * carry a «tolerate the row having been hand-deleted» guard written after the
 * event, which is a repair at the call site for damage that belongs here.
 *
 * Restoring the ROW is the whole job. It comes back inactive and empty:
 *
 *  - inactive, because the collapse retired it on purpose. Nothing lists it,
 *    no merchant is offered it, and the four kinds under `booking_kinds` remain
 *    the only live booking vocabulary.
 *  - empty, because its item types were pruned and are not coming back. A
 *    branch that resolves and holds nothing is exactly what a seeder that files
 *    into it needs; a branch that does not resolve is a fatal.
 *
 * Ids are NOT preserved and do not need to be: every `category_service_configs`
 * row now names branch #84 alone, and nothing anywhere still points at the old
 * ids. Checked before writing this — zero dangling references.
 *
 * Idempotent. It only ever inserts a key that is missing, and never touches a
 * branch that exists — including one someone has since switched back on.
 */
class RetiredBranchRowsSeeder extends Seeder
{
    /**
     * The twelve booking branches the collapse emptied, with the names they
     * carried. Taken from the labels in the migration that created the table
     * and from `BookingBranchesSeeder`, so a restored row reads in Arabic
     * rather than as a raw key.
     *
     * @var array<string,array{0:string,1:string}>
     */
    private const BOOKING = [
        'clinic' => ['عيادات ومواعيد طبية', 'Clinics & Appointments'],
        'hotel' => ['فنادق ووحدات سكنية', 'Hotels & Units'],
        'restaurant_table' => ['طاولات المطاعم', 'Restaurant Tables'],
        'sports' => ['ملاعب رياضية', 'Sports Fields'],
        'training' => ['تدريب ودورات', 'Training & Courses'],
        'services_tasks' => ['خدمات ومهام', 'Services & Tasks'],
        'halls_events' => ['قاعات ومناسبات', 'Halls & Events'],
        'tourism_travel' => ['سياحة وسفر', 'Tourism & Travel'],
        'real_estate' => ['وحدات عقارية', 'Real Estate Units'],
        'beauty_care' => ['تجميل وعناية', 'Beauty & Care'],
        'business_consulting' => ['استشارات أعمال', 'Business Consulting'],
        'coworking' => ['مساحات عمل مشتركة', 'Coworking Spaces'],
    ];

    public function run(): void
    {
        $serviceId = (int) DB::table('platform_services')->where('key', 'booking')->value('id');

        if (! $serviceId) {
            $this->command?->warn('خدمة الحجز غير موجودة — لا شيء لاستعادته.');

            return;
        }

        $existing = DB::table('platform_service_item_groups')
            ->where('platform_service_id', $serviceId)
            ->pluck('key')
            ->flip();

        $sort = 1 + (int) DB::table('platform_service_item_groups')
            ->where('platform_service_id', $serviceId)
            ->max('sort_order');

        $restored = [];

        foreach (self::BOOKING as $key => [$ar, $en]) {
            if ($existing->has($key)) {
                continue;
            }

            DB::table('platform_service_item_groups')->insert([
                'platform_service_id' => $serviceId,
                'key' => $key,
                'name_ar' => $ar,
                'name_en' => $en,
                'sort_order' => $sort++,
                'is_active' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $restored[] = $ar;
        }

        $this->command?->info($restored === []
            ? 'كل فروع الحجز المتقاعدة موجودة — لا تغيير.'
            : 'أُعيدت ' . count($restored) . ' فرعًا متقاعدًا (مُخملة): ' . implode('، ', $restored));
    }
}
