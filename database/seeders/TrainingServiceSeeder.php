<?php

namespace Database\Seeders;

use App\Models\PlatformService;
use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Puts «التدريب والتغذية» on the shelf, so a gym can SELL a plan.
 *
 *     php artisan db:seed --class=TrainingServiceSeeder
 *
 * The plans themselves were built long ago — a trainer writes a workout and a
 * nutrition schedule, the client logs rounds, they talk in a plan chat. What was
 * missing is that none of it was ever a SERVICE. `training` existed only as a
 * staff CAPABILITY, which says who inside a business may manage plans; nothing
 * said the business offers them. So a gym could deliver a plan it had no way to
 * be found for, and no way to price.
 *
 * Four kinds, because a subscription and a one-off session are not the same sale
 * and never carry the same price:
 *
 *   خطة تدريب        the workout alone
 *   نظام غذائي       the nutrition alone — a dietitian sells only this
 *   تدريب وتغذية     the pair, which is what most subscriptions are
 *   حصة خاصة         one session with the coach, priced per session
 *
 * Idempotent, and it never switches the service on for a child that already
 * carries it — the admin's own `allowed_item_types` survive a re-run.
 */
class TrainingServiceSeeder extends Seeder
{
    /** kind key => [ar, en] */
    private const KINDS = [
        'training_workout' => ['خطة تدريب', 'Workout Plan'],
        'training_nutrition' => ['نظام غذائي', 'Nutrition Plan'],
        'training_combined' => ['تدريب وتغذية', 'Training & Nutrition'],
        'training_session' => ['حصة خاصة', 'Private Session'],
    ];

    /**
     * Who sells it. A gym and an academy train; a «نادي صحي» is a health club
     * and does too. Nothing else on the platform coaches a person, and adding a
     * child that does not is how a service ends up wired to 300 shops.
     */
    private const CHILDREN = ['جيم', 'نادي رياضي', 'أكاديمية رياضية', 'نادي صحي'];

    public function run(): void
    {
        $service = PlatformService::query()->where('key', PlatformService::KEY_TRAINING)->first();

        if (! $service) {
            $this->command?->warn('  ! خدمة «التدريب والتغذية» غير موجودة — شغّل PlatformServiceSeeder أولًا.');

            return;
        }

        DB::transaction(function () use ($service) {
            $kinds = $this->upsertKinds((int) $service->id);
            $this->fileUnderBranch((int) $service->id);
            $wired = $this->wireChildren((int) $service->id);

            $this->command?->info('Training service:');
            $this->command?->line("  - أنواع الخدمة : {$kinds}");
            $this->command?->line("  - أبناء مُنحوا الخدمة : {$wired}");
        });
    }

    /**
     * Every item type has to sit in a BRANCH — the admin screens list types
     * through their branch, and a type no branch reaches is invisible to the
     * people who would tick it. One branch is right here: the four kinds are
     * one question with four answers, not four families.
     */
    private function fileUnderBranch(int $serviceId): void
    {
        $groupId = (int) DB::table('platform_service_item_groups')
            ->where('platform_service_id', $serviceId)->where('key', 'training_kinds')->value('id');

        if ($groupId <= 0) {
            $groupId = (int) DB::table('platform_service_item_groups')->insertGetId([
                'platform_service_id' => $serviceId,
                'key' => 'training_kinds',
                'name_ar' => 'أنواع التدريب',
                'name_en' => 'Training Kinds',
                'sort_order' => 0,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $typeIds = DB::table('platform_service_item_types')
            ->where('platform_service_id', $serviceId)
            ->whereIn('key', array_keys(self::KINDS))->pluck('id');

        foreach ($typeIds as $typeId) {
            DB::table('platform_service_item_group_type')->updateOrInsert(
                ['group_id' => $groupId, 'item_type_id' => (int) $typeId], []
            );
        }
    }

    private function upsertKinds(int $serviceId): int
    {
        $order = 1;

        foreach (self::KINDS as $key => [$ar, $en]) {
            DB::table('platform_service_item_types')->updateOrInsert(
                ['platform_service_id' => $serviceId, 'key' => $key],
                [
                    'name_ar' => $ar,
                    'name_en' => $en,
                    'is_active' => 1,
                    'sort_order' => $order++,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        return count(self::KINDS);
    }

    private function wireChildren(int $serviceId): int
    {
        $writer = app(ChildServiceWriter::class);
        $wired = 0;

        foreach (self::CHILDREN as $name) {
            $childId = (int) DB::table('category_children_master')->where('name_ar', $name)->value('id');

            if ($childId <= 0) {
                $this->command?->warn("  ! «{$name}» غير موجود — تُخطّي.");

                continue;
            }

            // Already offered: whatever the admin narrowed it to is his.
            if (DB::table('category_platform_services')
                ->where('child_id', $childId)
                ->where('platform_service_id', $serviceId)
                ->where('is_active', 1)
                ->exists()) {
                continue;
            }

            foreach (DB::table('category_parent_child')->where('child_id', $childId)->pluck('parent_id') as $rootId) {
                $writer->enable((int) $rootId, $childId, $serviceId, [
                    'allowed_item_types' => array_keys(self::KINDS),
                    // Nothing is reserved out of an inventory: a plan is written
                    // for one person, not picked off a shelf.
                    'requires_bookable_item' => false,
                ], null, null, 'training-service');
            }

            $wired++;
        }

        return $wired;
    }
}
