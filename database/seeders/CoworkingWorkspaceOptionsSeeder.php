<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Gives «منطقة عمل مشتركة» the vocabulary its own config had been demanding.
 *
 *     php artisan db:seed --class=CoworkingWorkspaceOptionsSeeder
 *
 * `booking_child_modes.php` has classified the child `units` from the day it was
 * written — a coworking space rents a NAMED room, not a slot — and the config
 * duly carried `requires_bookable_item = true`. But a unit points at a line
 * option (`bookable_items.line_option_id`) and this child had **no line group at
 * all**: three descriptive/modifier groups and nothing to name a desk with. The
 * flag asked for a list whose words did not exist.
 *
 * The hotel is the working precedent and this is the same shape:
 *
 *   kind      booking_time (an hour), where the hotel has booking_stay (a night)
 *   line      «مساحات العمل», where the hotel has «الغرف» #150
 *   modifier  «خدمات المكتب» + «نظام الاشتراك», where the hotel has «إطلالة
 *             الوحدة» + «نظام الوجبات»
 *
 * `CoworkingAndHotelUnitsSeeder` built these six as ITEM TYPES on 2026-08-02 —
 * hot_desk, private_office, meeting_room… — and the kinds collapse dissolved
 * them, correctly: the type says HOW a thing is booked and there is only one
 * answer here, by the hour. What the collapse dissolved was never re-created on
 * the axis it belonged to, and this is that re-creation.
 *
 * **The owner's own list was three lines: «مكتب منفصل / مكتب بسكرتارية / مكتب
 * وسكرتارية وريسبشن».** They are one line and a ladder of extras, so they are
 * modelled as `line + modifiers` — the heading IS the combination, the same
 * mechanism the menu headings run on. Two rows express his three prices, a
 * merchant with a reception and no secretary becomes expressible (the
 * enumeration cannot say it), and «خط هاتف» tomorrow costs one row, not eight.
 *
 * ⚠ The three priced groups are registered in `data/option_price_roles.php`.
 * A group missing from that file is reset to `descriptive` on its next run,
 * which silently stops it pricing — that has bitten five times.
 *
 * Idempotent; nothing is deleted, and no option is added to a group another
 * trade shares.
 */
class CoworkingWorkspaceOptionsSeeder extends Seeder
{
    private const CHILD_NAME = 'منطقة عمل مشتركة';

    private const ROOT_SLUG = 'offices';

    /** An hour of a desk, not a 30-minute appointment. */
    private const KIND = 'booking_time';

    /**
     * The units a customer reserves one OF. Each is a `bookable_items` row's
     * `line_option_id`: «مكتب منفصل / الدور الثاني», capacity 4, quantity 6.
     */
    private const WORKSPACES = [
        'مكتب منفصل' => 'Private Office',
        'مكتب فريق' => 'Team Office',
        'مقعد مشترك' => 'Hot Desk',
        'مكتب ثابت' => 'Dedicated Desk',
        'منطقة مذاكرة' => 'Study Area',
        'قاعة كورسات' => 'Course Room',
        'قاعة اجتماعات' => 'Meeting Room',
        'قاعة مؤتمرات' => 'Conference Room',
        'استوديو تسجيل' => 'Recording Studio',
    ];

    /** Never bought alone; each changes the price of the line it rides on. */
    private const OFFICE_SERVICES = [
        'سكرتارية' => 'Secretarial Service',
        'ريسبشن' => 'Reception Desk',
        'خط هاتف مباشر' => 'Direct Phone Line',
        'عنوان بريدي وتسجيل شركة' => 'Business Address',
    ];

    /** The same desk at two prices — the coworking «نظام الوجبات». */
    private const PLANS = [
        'بالساعة' => 'Hourly',
        'يومي' => 'Daily',
        'أسبوعي' => 'Weekly',
        'شهري' => 'Monthly',
        'ربع سنوي' => 'Quarterly',
    ];

    /**
     * Never priced — it says what the place has.
     *
     * Its own group rather than more rows on the shared «مرافق ومعدات» #23,
     * which nine other children carry: a wedding hall has no lockers and no
     * 24/7 access, and `child_option_groups.php` grants that group WHOLE. The
     * hotel made the same call with «مرافق الإقامة». The two generic rows #23
     * already has are linked from there instead of being written twice.
     */
    private const FACILITIES = [
        'بروجيكتور وشاشة عرض' => 'Projector & Screen',
        'طابعة وماسح ضوئي' => 'Printer & Scanner',
        'ركن قهوة ومطبخ' => 'Coffee & Kitchen',
        'غرفة هدوء' => 'Quiet Room',
        'لوكرز' => 'Lockers',
        'موقف سيارات' => 'Parking Space',
        'دخول ٢٤/٧' => '24/7 Access',
    ];

    private const SHARED_FACILITIES = ['واي فاي', 'وايت بورد'];

    public function run(): void
    {
        $childId = (int) DB::table('category_children_master')
            ->where('name_ar', self::CHILD_NAME)->value('id');

        if ($childId <= 0) {
            $this->command?->warn('  ! «' . self::CHILD_NAME . '» غير موجود — لم يُنفَّذ شيء.');

            return;
        }

        DB::transaction(function () use ($childId) {
            $linked = 0;

            $linked += $this->group('مساحات العمل', 'Workspaces', 'line', self::WORKSPACES, $childId);
            $linked += $this->group('خدمات المكتب', 'Office Services', 'modifier', self::OFFICE_SERVICES, $childId);
            $linked += $this->group('نظام الاشتراك', 'Subscription Plan', 'modifier', self::PLANS, $childId);
            $linked += $this->group('تجهيزات مساحة العمل', 'Workspace Facilities', 'descriptive', self::FACILITIES, $childId);
            $linked += $this->linkShared($childId);

            $kind = $this->correctKind($childId);

            $this->command?->info('Coworking workspace vocabulary:');
            $this->command?->line("  - روابط خيارات : {$linked}");
            $this->command?->line('  - نوع الحجز : ' . ($kind ? 'صُحّح إلى «حجز وقت»' : 'صحيح بالفعل'));
        });
    }

    /**
     * @param  array<string,string>  $options  name_ar => name_en
     * @return int links written
     */
    private function group(string $nameAr, string $nameEn, string $role, array $options, int $childId): int
    {
        $groupId = (int) DB::table('option_groups')->where('name_ar', $nameAr)->value('id');

        if ($groupId <= 0) {
            $groupId = (int) DB::table('option_groups')->insertGetId([
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                'reorder' => 1 + (int) DB::table('option_groups')->max('reorder'),
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // The role is written here AND declared in option_price_roles.php. Here
        // so a standalone run of this seeder produces a working screen; there
        // so the next run of OptionPriceRolesSeeder does not reset it.
        DB::table('option_groups')->where('id', $groupId)
            ->update(['price_role' => $role, 'updated_at' => now()]);

        $linked = 0;

        foreach ($options as $ar => $en) {
            $optionId = (int) DB::table('options')
                ->where('group_id', $groupId)->where('name_ar', $ar)->value('id');

            if ($optionId <= 0) {
                // `options.name_en` collides across groups — «موقف سيارات» is
                // already a hotel amenity. The hotel seeder solves it the same
                // way rather than reusing another group's row: the two mean the
                // same thing to a reader and different things to a filter.
                if (DB::table('options')->where('name_en', $en)->exists()) {
                    $en .= ' (Workspace)';
                }

                $optionId = (int) DB::table('options')->insertGetId([
                    'group_id' => $groupId,
                    'name_ar' => $ar,
                    'name_en' => $en,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $linked += $this->link($childId, $optionId);
        }

        return $linked;
    }

    /** «واي فاي» and «وايت بورد» exist on #23; a coworking space has both. */
    private function linkShared(int $childId): int
    {
        $linked = 0;

        foreach (
            DB::table('options as o')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->where('g.name_ar', 'مرافق ومعدات')
                ->whereIn('o.name_ar', self::SHARED_FACILITIES)
                ->pluck('o.id') as $optionId
        ) {
            $linked += $this->link($childId, (int) $optionId);
        }

        return $linked;
    }

    /**
     * A SHARED link (category_id = 0) — the child sits under one root today and
     * a desk is a desk under any other it may reach.
     */
    private function link(int $childId, int $optionId): int
    {
        $exists = DB::table('category_child_option')
            ->where('child_id', $childId)->where('option_id', $optionId)->exists();

        if ($exists) {
            return 0;
        }

        DB::table('category_child_option')->insert([
            'child_id' => $childId,
            'category_id' => 0,
            'option_id' => $optionId,
            'reorder' => 0,
        ]);

        return 1;
    }

    /** @return bool whether the stored kind had to be changed */
    private function correctKind(int $childId): bool
    {
        $serviceId = (int) DB::table('platform_services')->where('key', 'booking')->value('id');
        $rootId = (int) DB::table('categories')->where('slug', self::ROOT_SLUG)->value('id');

        $row = DB::table('category_service_configs')
            ->where('category_id', $rootId)->where('child_id', $childId)
            ->where('platform_service_id', $serviceId)->first();

        if (! $row) {
            return false;
        }

        $config = json_decode((string) $row->config, true) ?: [];

        if (($config['allowed_item_types'] ?? []) === [self::KIND]) {
            return false;
        }

        $config['allowed_item_types'] = [self::KIND];
        // It reserves a named room; the flag was never the wrong part.
        $config['requires_bookable_item'] = true;
        $config['config_source'] = 'coworking-workspace-options';
        $config['config_updated_at'] = now()->toDateTimeString();

        DB::table('category_service_configs')->where('id', $row->id)->update([
            'config' => json_encode($config, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        return true;
    }
}
