<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Reconnects the accommodation-facility options to the hotel children that
 * actually exist.
 *
 *   php artisan db:seed --class=HotelChildOptionsSeeder
 *
 * When hospitality moved off the «⭐» children onto accommodation TYPES
 * (فندق، شقق فندقية، منتجع…), the grade group followed the move but the
 * «مرافق الإقامة» group (#42) did not: its 90 links stayed on the six star
 * children, which now belong to no root at all. The result is that a real hotel
 * can declare a star rating but cannot say it has a pool, free wifi, or an
 * included breakfast — while 15 useful descriptors sit on rows no picker reads.
 *
 * This moves those links onto the six live type children and clears them off the
 * dead ones. Nothing is deleted from `options`, and no business selection is
 * touched (option_user holds zero rows for this group).
 *
 * Idempotent: a re-run finds the links already in place and reports zero.
 */
class HotelChildOptionsSeeder extends Seeder
{
    private const HOTELS_ROOT = 'tourist-hotels';

    private const FACILITIES_GROUP = 'مرافق الإقامة';

    /** The retired star children that still hold the facility links. */
    private const DEAD_STAR_CHILDREN = [1, 2, 3, 4, 5, 6];

    public function run(): void
    {
        DB::transaction(function () {
            $groupId = (int) DB::table('option_groups')
                ->where('name_ar', self::FACILITIES_GROUP)->value('id');

            if (! $groupId) {
                $this->command?->warn('  ! مجموعة «' . self::FACILITIES_GROUP . '» غير موجودة — لا شيء ليُنقل.');

                return;
            }

            $optionIds = DB::table('options')->where('group_id', $groupId)->pluck('id');

            $rootId = (int) DB::table('categories')->where('slug', self::HOTELS_ROOT)->value('id');

            $children = DB::table('category_parent_child as pc')
                ->join('category_children_master as ch', 'ch.id', '=', 'pc.child_id')
                ->where('pc.parent_id', $rootId)
                ->pluck('ch.name_ar', 'ch.id');

            if ($children->isEmpty()) {
                $this->command?->warn('  ! لا توجد أبناء تحت «' . self::HOTELS_ROOT . '».');

                return;
            }

            $added = $this->linkFacilities($children->keys(), $optionIds);
            $cleared = $this->clearDeadStarLinks($optionIds);

            $this->command?->info('Hotel facility options:');
            $this->command?->line('  - أبناء الإقامة        : ' . $children->count() . ' (' . $children->implode('، ') . ')');
            $this->command?->line("  - روابط مرافق أُضيفت : {$added}");
            $this->command?->line("  - روابط على أبناء النجوم المتقاعدة أُزيلت : {$cleared}");
        });
    }

    /** Every accommodation type may declare every facility; the business picks. */
    private function linkFacilities($childIds, $optionIds): int
    {
        $existing = DB::table('category_child_option')
            ->whereIn('child_id', $childIds)
            ->whereIn('option_id', $optionIds)
            ->get(['child_id', 'option_id'])
            ->map(fn ($r) => $r->child_id . ':' . $r->option_id)
            ->flip();

        $rows = [];

        foreach ($childIds as $childId) {
            foreach ($optionIds as $optionId) {
                if ($existing->has($childId . ':' . $optionId)) {
                    continue;
                }

                $rows[] = ['child_id' => $childId, 'option_id' => $optionId];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('category_child_option')->insert($chunk);
        }

        return count($rows);
    }

    /** The star children have no root; links on them are unreachable. */
    private function clearDeadStarLinks($optionIds): int
    {
        $stillLinked = DB::table('category_parent_child')
            ->whereIn('child_id', self::DEAD_STAR_CHILDREN)
            ->count();

        if ($stillLinked > 0) {
            $this->command?->warn('  ! أبناء النجوم ما زالت مرتبطة بجذر — تُركت روابطها كما هي.');

            return 0;
        }

        return DB::table('category_child_option')
            ->whereIn('child_id', self::DEAD_STAR_CHILDREN)
            ->whereIn('option_id', $optionIds)
            ->delete();
    }
}
