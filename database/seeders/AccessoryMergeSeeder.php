<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Four accessory children become one, and what kind becomes an option.
 *
 *     php artisan db:seed --class=AccessoryMergeSeeder
 *
 * See data/accessory_merge.php — including why «موبيلات و اكسسوار» is given the
 * vocabulary and NOT folded.
 *
 * Same order every fold here uses, and each step exists because skipping it
 * loses something: vocabulary first, then the keeper takes the roots it needs to
 * receive anyone, then accounts (the option ticked BEFORE the move), and only
 * then the detachment — never of a child still holding an account.
 *
 * Idempotent.
 */
class AccessoryMergeSeeder extends Seeder
{
    public function run(): void
    {
        $data = require __DIR__ . '/data/accessory_merge.php';

        DB::transaction(function () use ($data) {
            $this->command?->info('Accessory merge:');

            $keeperId = $this->liveChildId($data['keeper']['name_ar']);

            if ($keeperId <= 0) {
                $this->command?->warn("  ! «{$data['keeper']['name_ar']}» غير موجود.");

                return;
            }

            [$groupId, $options] = $this->upsertGroup($data['group']);

            $this->linkOptions($keeperId, array_values($options));
            $rooted = $this->gainRoots($keeperId, $data['keeper']['gain_roots'] ?? []);

            $carried = 0;
            foreach ($data['carry_only'] as $name) {
                $carried += $this->linkOptions($this->liveChildId($name), array_values($options));
            }

            $moved = $this->fold($data['folds'], $keeperId, $options);

            $this->command?->line("  - «{$data['keeper']['name_ar']}» #{$keeperId} · خيارات المجموعة : " . count($options));
            $this->command?->line("      جذور اكتُسبت (خدمات) : {$rooted} · روابط للأبناء المحتفظين : {$carried}");
            $this->command?->line('      حسابات نُقلت : ' . count($moved));

            foreach ($moved as $row) {
                $this->command?->line("        #{$row['id']} {$row['name']} : {$row['from']} → {$row['option']}");
            }
        });
    }

    /**
     * @param  array<string,mixed>  $group
     * @return array{0:int,1:array<string,int>}
     */
    private function upsertGroup(array $group): array
    {
        $groupId = (int) DB::table('option_groups')->where('name_ar', $group['name_ar'])->value('id');

        if ($groupId <= 0) {
            $groupId = (int) DB::table('option_groups')->insertGetId([
                'name_ar' => $group['name_ar'],
                'name_en' => $group['name_en'],
                'price_role' => $group['price_role'],
                'reorder' => 1 + (int) DB::table('option_groups')->max('reorder'),
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $options = [];

        foreach ($group['options'] as $ar => $en) {
            $id = (int) DB::table('options')->where('group_id', $groupId)->where('name_ar', $ar)->value('id');

            if ($id <= 0) {
                // `options.name_en` is UNIQUE across the whole table.
                if (DB::table('options')->where('name_en', $en)->exists()) {
                    $this->command?->warn("      ! «{$en}» مستخدم في مجموعة أخرى — «{$ar}» لم تُضف.");

                    continue;
                }

                $id = (int) DB::table('options')->insertGetId([
                    'group_id' => $groupId,
                    'name_ar' => $ar,
                    'name_en' => $en,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $options[$ar] = $id;
        }

        return [$groupId, $options];
    }

    /**
     * The keeper cannot receive a merchant standing under a root it does not
     * stand under itself. Wiring is COPIED from a named child already there, so
     * the trade arrives offering what that root already meant by it.
     *
     * @param  array<string,string>  $gains  root slug => donor child name
     */
    private function gainRoots(int $keeperId, array $gains): int
    {
        $writer = app(ChildServiceWriter::class);
        $wired = 0;

        foreach ($gains as $slug => $donorName) {
            $rootId = (int) DB::table('categories')->where('slug', $slug)->value('id');
            $donorId = $this->liveChildId($donorName);

            if ($rootId <= 0 || $donorId <= 0) {
                $this->command?->warn("      ! تعذّر اكتساب «{$slug}».");

                continue;
            }

            DB::table('category_parent_child')->updateOrInsert(
                ['parent_id' => $rootId, 'child_id' => $keeperId],
                ['updated_at' => now()]
            );

            foreach (
                DB::table('category_platform_services')
                    ->where('category_id', $rootId)->where('child_id', $donorId)->where('is_active', 1)
                    ->pluck('platform_service_id') as $serviceId
            ) {
                $serviceId = (int) $serviceId;

                $already = DB::table('category_platform_services')
                    ->where('category_id', $rootId)->where('child_id', $keeperId)
                    ->where('platform_service_id', $serviceId)->where('is_active', 1)->exists();

                if ($already) {
                    continue;
                }

                $config = json_decode((string) DB::table('category_service_configs')
                    ->where('category_id', $rootId)->where('child_id', $donorId)
                    ->where('platform_service_id', $serviceId)->value('config'), true) ?: [];

                $writer->enable($rootId, $keeperId, $serviceId, $config, null, null, 'accessory-merge');
                $wired++;
            }
        }

        return $wired;
    }

    /**
     * @param  array<string,string>  $folds  child name => option name
     * @param  array<string,int>  $options
     * @return array<int,array{id:int,name:string,from:string,option:string}>
     */
    private function fold(array $folds, int $keeperId, array $options): array
    {
        $moved = [];

        foreach ($folds as $childName => $optionAr) {
            $childId = $this->liveChildId($childName);

            if ($childId <= 0 || $childId === $keeperId) {
                continue;
            }

            $optionId = $options[$optionAr] ?? 0;

            foreach (DB::table('users')->where('category_child_id', $childId)->get(['id', 'name', 'category_id']) as $account) {
                if ($optionId > 0) {
                    DB::table('option_user')->updateOrInsert(
                        ['user_id' => (int) $account->id, 'option_id' => $optionId],
                        []
                    );
                }

                // Keep him on the root he chose, unless the keeper does not
                // stand there — then the keeper's own first root is the only
                // honest answer, and it is reported.
                $root = (int) $account->category_id;

                if (! DB::table('category_parent_child')->where('parent_id', $root)->where('child_id', $keeperId)->exists()) {
                    $root = (int) DB::table('category_parent_child')->where('child_id', $keeperId)->min('parent_id');
                    $this->command?->warn("      ! #{$account->id} نُقل إلى جذر آخر — «{$childName}» كان تحت جذر لا يقف فيه المُحتفَظ به.");
                }

                DB::table('users')->where('id', $account->id)->update([
                    'category_child_id' => $keeperId,
                    'category_id' => $root,
                ]);

                $moved[] = [
                    'id' => (int) $account->id,
                    'name' => (string) $account->name,
                    'from' => $childName,
                    'option' => $optionAr,
                ];
            }

            if (DB::table('users')->where('category_child_id', $childId)->exists()) {
                $this->command?->warn("      ! «{$childName}» ما زال عليه نشاط — لم يُفصل.");

                continue;
            }

            DB::table('category_parent_child')->where('child_id', $childId)->delete();

            // The platform's debris rule: links, fees and option rows go, the
            // config is only deactivated, the master row is untouched.
            DB::table('category_service_configs')->where('child_id', $childId)
                ->update(['is_active' => 0, 'updated_at' => now()]);

            foreach (['category_platform_services', 'category_child_service_fees', 'category_child_option'] as $table) {
                DB::table($table)->where('child_id', $childId)->delete();
            }
        }

        return $moved;
    }

    /** @param array<int,int> $optionIds */
    private function linkOptions(int $childId, array $optionIds): int
    {
        if ($childId <= 0) {
            return 0;
        }

        $have = DB::table('category_child_option')->where('child_id', $childId)
            ->pluck('option_id')->map(fn ($id) => (int) $id)->all();

        $order = (int) DB::table('category_child_option')->where('child_id', $childId)->max('reorder');
        $added = 0;

        foreach ($optionIds as $optionId) {
            if (in_array($optionId, $have, true)) {
                continue;
            }

            DB::table('category_child_option')->insert([
                'child_id' => $childId,
                'category_id' => 0,
                'option_id' => $optionId,
                'reorder' => ++$order,
            ]);

            $have[] = $optionId;
            $added++;
        }

        return $added;
    }

    /** The row of that name a customer can reach — never the retired twin. */
    private function liveChildId(string $nameAr): int
    {
        return (int) DB::table('category_children_master as c')
            ->join('category_parent_child as p', 'p.child_id', '=', 'c.id')
            ->where('c.name_ar', $nameAr)
            ->orderByDesc(DB::raw('(SELECT COUNT(*) FROM users u WHERE u.category_child_id = c.id)'))
            ->orderBy('c.id')
            ->value('c.id');
    }
}
