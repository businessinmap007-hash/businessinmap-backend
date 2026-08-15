<?php

namespace Database\Seeders;

use App\Models\CategoryChildOption;
use App\Services\Catalog\ChildOptionDecisions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Nine clothing children become three, and what a shop sells becomes options.
 *
 *     php artisan db:seed --class=FashionRemodelSeeder
 *
 * The why is in `data/fashion_taxonomy.php`. In short: a business has exactly
 * one `category_child_id`, so «محل فيه ملابس وأحذية واكسسوارات» had no home, and
 * «كوتشي» carried zero line options — a sneaker shop could name nothing it sold.
 *
 * Idempotent and NON-DESTRUCTIVE, the same contract HealthRemodelSeeder keeps:
 *   - retired children keep their `category_children_master` row and lose only
 *     the `category_parent_child` pivot to root #14, so re-inserting one row
 *     undoes the move;
 *   - a business is re-pointed only after its former specialty is written into
 *     `option_user`, so the claim survives the move;
 *   - every write is updateOrInsert / firstOrCreate on a natural key.
 *
 * Run it twice: the second run reports the same totals and changes nothing.
 */
class FashionRemodelSeeder extends Seeder
{
    public function run(): void
    {
        $data = require database_path('seeders/data/fashion_taxonomy.php');

        $groupId = (int) DB::table('option_groups')->where('name_ar', $data['group_name_ar'])->value('id');

        if ($groupId <= 0) {
            $this->command?->warn("  ! مجموعة «{$data['group_name_ar']}» غير موجودة — لم يُنفَّذ شيء.");

            return;
        }

        DB::transaction(function () use ($data, $groupId) {
            $options = $this->upsertOptions($data['new_options'], $groupId);
            $moved = $this->movePeople($data, $options);
            $detached = $this->detach($data);
            $linked = $this->offerTheWholeVocabulary($data['unscope'], $groupId);

            $this->command?->info('Fashion remodel:');
            $this->command?->line('  - بنود جديدة : ' . count($data['new_options']));
            $this->command?->line('  - أبناء تقاعدوا من الجذر : ' . $detached);
            $this->command?->line('  - روابط بنود أُضيفت للثلاثة : ' . $linked);
            $this->command?->line('  - أنشطة نُقلت : ' . count($moved));

            foreach ($moved as $row) {
                $this->command?->line("      #{$row['id']} {$row['name']} : {$row['from']} → #{$row['to']} (+ خيار: {$row['option']})");
            }
        });
    }

    /**
     * @param  array<string,string>  $names  name_ar => name_en
     * @return array<string,int> name_ar => option id
     */
    private function upsertOptions(array $names, int $groupId): array
    {
        $ids = [];

        foreach ($names as $ar => $en) {
            $id = (int) DB::table('options')->where('group_id', $groupId)->where('name_ar', $ar)->value('id');

            if ($id <= 0) {
                $id = (int) DB::table('options')->insertGetId([
                    'group_id' => $groupId,
                    'name_ar' => $ar,
                    'name_en' => $en,
                ]);
            }

            $ids[$ar] = $id;
        }

        // Everything the group already held stays sayable too.
        foreach (DB::table('options')->where('group_id', $groupId)->get(['id', 'name_ar']) as $row) {
            $ids[(string) $row->name_ar] ??= (int) $row->id;
        }

        return $ids;
    }

    /**
     * Re-point the businesses, writing what each one sold onto it first.
     *
     * @param  array<string,int>  $options
     * @return array<int,array<string,mixed>>
     */
    private function movePeople(array $data, array $options): array
    {
        $moved = [];

        foreach ($data['retire'] as $childName => $spec) {
            $childId = (int) DB::table('category_children_master')->where('name_ar', $childName)->value('id');
            $optionId = $options[$spec['option']] ?? null;

            if ($childId <= 0 || ! $optionId) {
                continue;
            }

            $businesses = DB::table('users')
                ->where('category_child_id', $childId)
                ->where('type', 'business')
                ->get(['id', 'name']);

            foreach ($businesses as $business) {
                DB::table('option_user')->updateOrInsert(
                    ['user_id' => (int) $business->id, 'option_id' => (int) $optionId],
                    []
                );

                DB::table('users')->where('id', $business->id)->update([
                    'category_child_id' => (int) $spec['to'],
                    'category_id' => (int) $data['root_id'],
                ]);

                $moved[] = [
                    'id' => (int) $business->id,
                    'name' => (string) $business->name,
                    'from' => $childName,
                    'to' => (int) $spec['to'],
                    'option' => $spec['option'],
                ];
            }
        }

        return $moved;
    }

    /**
     * Remove the pivot to root #14 only. The master row is the undo record.
     *
     * A child still holding a business is never detached — that would strand it
     * outside every root, which is the one thing worse than the crowding this
     * fixes.
     */
    private function detach(array $data): int
    {
        $detached = 0;

        foreach (array_keys($data['retire']) as $childName) {
            $childId = (int) DB::table('category_children_master')->where('name_ar', $childName)->value('id');

            if ($childId <= 0) {
                continue;
            }

            if (DB::table('users')->where('category_child_id', $childId)->exists()) {
                $this->command?->warn("  ! «{$childName}» ما زال يحمل نشاطًا — لم يُفَك.");

                continue;
            }

            $detached += DB::table('category_parent_child')
                ->where('parent_id', (int) $data['root_id'])
                ->where('child_id', $childId)
                ->delete();
        }

        return $detached;
    }

    /**
     * The three survivors are offered the WHOLE group.
     *
     * Scoping them is exactly what left كوتشي unable to name anything: the shop
     * that sells clothes and shoes and bags must be able to say all three, and
     * the narrowing is the merchant's own ticks, not the taxonomy's guess.
     *
     * Whole EXCEPT what the owner has since taken back. This inserted straight
     * into the pivot, so on 2026-08-14 he withdrew thirty-five words from
     * «اكسسوار» one child at a time — ملابس، أحذية، كوتشي، فساتين زفاف — and
     * this seeder handed every one of them back on its next run. An accessories
     * shop was made to sell wedding dresses by a file, three weeks after a
     * person said it should not.
     *
     * `filter()` at ALL_ROOTS is blocked by a withdrawal under ANY root, which
     * is the right reading here: this grant is shared, so it would reach the
     * root the owner said no under.
     *
     * @param  array<int,int>  $childIds
     */
    private function offerTheWholeVocabulary(array $childIds, int $groupId): int
    {
        $optionIds = DB::table('options')->where('group_id', $groupId)->pluck('id')->map(fn ($id) => (int) $id);
        $decisions = new ChildOptionDecisions;
        $added = 0;

        foreach ($childIds as $childId) {
            $have = DB::table('category_child_option')
                ->where('child_id', $childId)
                ->whereIn('option_id', $optionIds)
                ->pluck('option_id')
                ->map(fn ($id) => (int) $id);

            $missing = collect($decisions->filter(
                (int) $childId,
                CategoryChildOption::ALL_ROOTS,
                $optionIds->diff($have)
            ))->values();

            foreach ($missing->chunk(200) as $chunk) {
                DB::table('category_child_option')->insert(
                    $chunk->map(fn ($id) => ['child_id' => (int) $childId, 'option_id' => $id])->values()->all()
                );
            }

            $added += $missing->count();
        }

        return $added;
    }
}
