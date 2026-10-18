<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildOptionDecisions;
use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * «ونش رفع أثاث» — the crane that lifts a sofa or a wardrobe to the fifth floor through the window.
 *
 *     php artisan db:seed --class=FurnitureWinchChildSeeder
 *
 * Owner, 2026-10-18: «سيارات نقل هي من تنقل إلى شحن وتوصيل، وأضف أيضًا ونش رفع الأثاث إن لم يكن موجودًا».
 * Nothing like it existed: «ونش إنقاذ» tows a broken-down car, a mover carries the furniture up the stairs, and a hoist
 * is neither. It is a job booked for a time at an address, so it sits under «شحن وتوصيل» beside the lorry and mirrors
 * «ونش إنقاذ» — the other winch — for everything that is not a priced line: the way it is booked (an appointment with
 * the business), how it is paid, and the rulings recorded for it ([[seeder-must-withdraw]]).
 *
 * The id is FIXED (`CHILD_ID`) because `data/booking_patterns.php` names children by id. Idempotent.
 */
class FurnitureWinchChildSeeder extends Seeder
{
    public const CHILD_ID = 556;

    private const NAME_AR = 'ونش رفع أثاث';

    private const NAME_EN = 'Furniture hoist';

    private const ROOT_SLUG = 'shipping-delivery';

    private const MIRRORS = 'ونش إنقاذ';

    /** The words a furniture hoist shares with the other winch: how it is paid. */
    private const SHARED_GROUPS = ['الدفع والسداد'];

    /**
     * What it sells, in the platform's own word: «ونش نقل عفش» is already a priced line (Furniture Hoist) in the heavy
     * equipment list a company names its machines from. Borrowed, not cloned — one row, one meaning.
     */
    private const LINES = ['ونش نقل عفش'];

    public function run(): void
    {
        DB::transaction(function () {
            $rootId = (int) DB::table('categories')->where('slug', self::ROOT_SLUG)->where('parent_id', 0)->value('id');

            if ($rootId <= 0) {
                $this->command?->warn('  ! جذر الشحن والتوصيل غير موجود.');

                return;
            }

            $childId = $this->child();

            if ($childId <= 0) {
                return;
            }

            DB::table('category_parent_child')->insertOrIgnore([
                'parent_id' => $rootId, 'child_id' => $childId, 'created_at' => now(), 'updated_at' => now(),
            ]);

            $this->mirrorRulings($childId);
            $linked = $this->linkOptions($childId, $rootId);
            $wired = $this->wireServices($childId, $rootId);

            $this->command?->info('Furniture winch child:');
            $this->command?->line('  - «' . self::NAME_AR . "» #{$childId} · خيارات رُبطت {$linked} · خدمات وُصلت {$wired}");
        });
    }

    private function child(): int
    {
        $byName = (int) DB::table('category_children_master')->where('name_ar', self::NAME_AR)->value('id');

        if ($byName > 0) {
            return $byName;
        }

        if (DB::table('category_children_master')->where('id', self::CHILD_ID)->exists()) {
            $this->command?->warn('  ! المعرّف ' . self::CHILD_ID . ' مشغولٌ بابنٍ آخر.');

            return 0;
        }

        DB::table('category_children_master')->insert([
            'id' => self::CHILD_ID,
            'name_ar' => self::NAME_AR,
            'name_en' => self::NAME_EN,
            'reorder' => (int) DB::table('category_children_master')->max('reorder') + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return self::CHILD_ID;
    }

    private function mirrorId(): int
    {
        return (int) DB::table('category_children_master')->where('name_ar', self::MIRRORS)->value('id');
    }

    private function mirrorRulings(int $childId): void
    {
        $mirrorId = $this->mirrorId();

        if ($mirrorId <= 0) {
            return;
        }

        foreach (DB::table('category_child_option_decisions')->where('child_id', $mirrorId)->get() as $ruling) {
            $exists = DB::table('category_child_option_decisions')
                ->where('child_id', $childId)->where('category_id', $ruling->category_id)
                ->where('option_id', $ruling->option_id)->where('kind', $ruling->kind)->exists();

            if (! $exists) {
                DB::table('category_child_option_decisions')->insert([
                    'child_id' => $childId, 'category_id' => $ruling->category_id, 'option_id' => $ruling->option_id,
                    'kind' => $ruling->kind, 'source' => 'furniture-winch-child', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        $forbidden = DB::table('category_child_option_decisions')->where('child_id', $childId)->where('kind', 'withdrawn')->pluck('option_id');

        if ($forbidden->isNotEmpty()) {
            DB::table('category_child_option')->where('child_id', $childId)->whereIn('option_id', $forbidden)->delete();
        }
    }

    private function linkOptions(int $childId, int $rootId): int
    {
        $mirrorId = $this->mirrorId();

        if ($mirrorId <= 0) {
            return 0;
        }

        $wanted = DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('cco.child_id', $mirrorId)
            ->whereIn('g.name_ar', self::SHARED_GROUPS)
            ->pluck('o.id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        $wanted = array_values(array_unique(array_merge($wanted, DB::table('options')->whereIn('name_ar', self::LINES)->pluck('id')->map(fn ($id) => (int) $id)->all())));

        $n = 0;

        foreach (app(ChildOptionDecisions::class)->filter($childId, $rootId, $wanted) as $optionId) {
            if (DB::table('category_child_option')->where('child_id', $childId)->where('category_id', 0)->where('option_id', $optionId)->exists()) {
                continue;
            }

            DB::table('category_child_option')->insert(['child_id' => $childId, 'category_id' => 0, 'option_id' => $optionId, 'reorder' => 0]);
            $n++;
        }

        return $n;
    }

    /** Booking = the other winch's own appointment config, copied from its live row; offers like every shop beside it. */
    private function wireServices(int $childId, int $rootId): int
    {
        $writer = app(ChildServiceWriter::class);
        $bookingId = (int) DB::table('platform_services')->where('key', 'booking')->value('id');
        $mirrorId = $this->mirrorId();

        $copied = $bookingId > 0 && $mirrorId > 0
            ? DB::table('category_service_configs')->where('platform_service_id', $bookingId)->where('child_id', $mirrorId)->where('is_active', 1)->value('config')
            : null;

        $config = is_string($copied) ? (json_decode($copied, true) ?: []) : [];
        unset($config['config_source'], $config['config_updated_at']);

        $config = $config + [
            'booking_modes' => [], 'item_family' => null, 'requires_bookable_item' => false, 'requires_start_end' => true,
            'supports_quantity' => false, 'supports_guest_count' => false, 'supports_extras' => false, 'required_fields' => [],
            'item_groups' => [], 'allowed_item_types' => ['booking_appointment'],
            'booking_pattern' => 'appointment', 'booking_patterns' => ['appointment'],
        ];

        $n = 0;

        foreach (['booking' => $config, 'business_offers' => []] as $key => $patch) {
            $serviceId = (int) DB::table('platform_services')->where('key', $key)->value('id');

            if ($serviceId > 0) {
                $writer->enable($rootId, $childId, $serviceId, $patch, null, null, 'furniture-winch-child');
                $n++;
            }
        }

        return $n;
    }
}
