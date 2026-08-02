<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Puts hospitality on the right axes (owner call 2026-08-02). The six children
 * under tourist-hotels were STAR RATINGS («1 ⭐» … «5 ➕ ⭐ ⭐ ⭐ ⭐ ⭐»), which
 * by the standing rule describe a hotel rather than saying what it IS — the
 * same error the medical specialties made. Accommodation type becomes the
 * child; the grade becomes an option.
 *
 *   php artisan db:seed --class=HotelsRemodelSeeder
 *   php artisan db:seed --class=NewChildrenBranchesSeeder
 *   php artisan db:seed --class=BookingChildModesSeeder
 *
 * DELIBERATELY CONSERVATIVE ON MIGRATION. Of the 67 accounts sitting on star
 * children, exactly ONE (#212 فندق الاندلس) has any hotel data at all —
 * bookable units and prices. The other 66 have none, and their names read
 * «اعمال سباكة», «ورشة نجارة», «خدمات ليموزين», «ملابس», «test»: the star
 * children are acting as a signup dumping ground, not a classification. So
 * only the real hotel is moved here. Re-filing the other 66 means deciding, per
 * account, which root they actually belong to — an owner call, not a guess.
 *
 * The star children are NOT deleted or detached for the same reason: 66 live
 * accounts still point at them. They are left in place until those accounts are
 * re-filed, at which point detaching them is a one-line follow-up.
 */
class HotelsRemodelSeeder extends Seeder
{
    private const ROOT_SLUG = 'tourist-hotels';
    private const UNIVERSAL_OPTION_GROUP_ID = 12;

    /** Axis 1 — what the business IS. */
    private const CHILDREN = [
        ['name_ar' => 'فندق', 'name_en' => 'Hotel'],
        ['name_ar' => 'شقق فندقية', 'name_en' => 'Serviced Apartments'],
        ['name_ar' => 'منتجع', 'name_en' => 'Resort'],
        ['name_ar' => 'نُزل / هوستل', 'name_en' => 'Hostel'],
        ['name_ar' => 'بيت ضيافة', 'name_en' => 'Guest House'],
        ['name_ar' => 'فندق عائم / بوت نيلي', 'name_en' => 'Floating Hotel / Nile Boat'],
    ];

    /** Axis 2 — the grade, multi-select like every other descriptor. */
    private const GRADE_GROUP = ['name_ar' => 'تصنيف الإقامة', 'name_en' => 'Accommodation Grade'];

    private const GRADES = [
        'نجمة واحدة' => 'One Star',
        'نجمتان' => 'Two Stars',
        'ثلاث نجوم' => 'Three Stars',
        'أربع نجوم' => 'Four Stars',
        'خمس نجوم' => 'Five Stars',
        'خمس نجوم بلس / فاخر' => 'Five Stars Plus',
        'غير مصنّف' => 'Unrated',
    ];

    /** The one account with real hotel data: star child id => [account, grade]. */
    private const MIGRATE = [
        212 => ['child' => 'فندق', 'grade' => 'نجمة واحدة'],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $rootId = (int) DB::table('categories')->where('slug', self::ROOT_SLUG)->value('id');

            $childIds = $this->upsertChildren($rootId);
            $universal = $this->attachUniversalOptions($childIds);
            $gradeIds = $this->buildGradePool($childIds);
            $moved = $this->migrateRealHotels($childIds, $gradeIds);

            $this->command?->info('Hotels remodel applied:');
            $this->command?->line('  - accommodation-type children : ' . count($childIds));
            $this->command?->line('  - universal commerce-option links added : ' . $universal);
            $this->command?->line('  - grade options : ' . count($gradeIds));
            $this->command?->line('  - real hotels migrated : ' . count($moved));

            foreach ($moved as $row) {
                $this->command?->line("      #{$row['id']} {$row['name']} → {$row['child']} + «{$row['grade']}»");
            }

            $left = DB::table('users')->whereIn('category_child_id', [1, 2, 3, 4, 5, 6])
                ->where('type', 'business')->count();
            $this->command?->warn("  ! {$left} حساب ما زال على أبناء النجوم — يحتاج إعادة تصنيف يدوية (لا بيانات فندقية لديه).");
        });
    }

    /** @return array<string,int> */
    private function upsertChildren(int $rootId): array
    {
        $ids = [];

        foreach (self::CHILDREN as $child) {
            $id = DB::table('category_children_master')->where('name_ar', $child['name_ar'])->value('id');

            if (! $id) {
                $id = DB::table('category_children_master')->insertGetId([
                    'name_ar' => $child['name_ar'],
                    'name_en' => $child['name_en'],
                    'reorder' => 1 + (int) DB::table('category_children_master')->max('reorder'),
                ]);
            }

            DB::table('category_parent_child')->updateOrInsert(
                ['parent_id' => $rootId, 'child_id' => (int) $id],
                ['updated_at' => now()]
            );

            $ids[$child['name_ar']] = (int) $id;
        }

        return $ids;
    }

    private function attachUniversalOptions(array $childIds): int
    {
        $universalIds = DB::table('options')->where('group_id', self::UNIVERSAL_OPTION_GROUP_ID)->pluck('id');
        $added = 0;

        foreach ($childIds as $childId) {
            foreach ($universalIds as $optionId) {
                if (DB::table('category_child_option')->where('child_id', $childId)->where('option_id', $optionId)->exists()) {
                    continue;
                }

                DB::table('category_child_option')->insert(['child_id' => $childId, 'option_id' => $optionId, 'reorder' => 0]);
                $added++;
            }
        }

        return $added;
    }

    /** @return array<string,int> */
    private function buildGradePool(array $childIds): array
    {
        $groupId = DB::table('option_groups')->where('name_ar', self::GRADE_GROUP['name_ar'])->value('id')
            ?: DB::table('option_groups')->insertGetId(self::GRADE_GROUP + [
                'reorder' => 1 + (int) DB::table('option_groups')->max('reorder'),
                'is_active' => 1,
            ]);

        $ids = [];

        foreach (self::GRADES as $ar => $en) {
            $optionId = DB::table('options')->where('group_id', $groupId)->where('name_ar', $ar)->value('id');

            if (! $optionId) {
                if (DB::table('options')->where('name_en', $en)->exists()) {
                    $en .= ' (Accommodation)';
                }

                $optionId = DB::table('options')->insertGetId(['group_id' => $groupId, 'name_ar' => $ar, 'name_en' => $en]);
            }

            $ids[$ar] = (int) $optionId;
        }

        // every accommodation type may carry any grade
        foreach ($childIds as $childId) {
            $order = 0;

            foreach ($ids as $optionId) {
                DB::table('category_child_option')->updateOrInsert(
                    ['child_id' => $childId, 'option_id' => $optionId],
                    ['reorder' => ++$order]
                );
            }
        }

        return $ids;
    }

    /** @return array<int,array{id:int,name:string,child:string,grade:string}> */
    private function migrateRealHotels(array $childIds, array $gradeIds): array
    {
        $moved = [];

        foreach (self::MIGRATE as $userId => $target) {
            $user = DB::table('users')->where('id', $userId)->where('type', 'business')->first(['id', 'name']);
            $childId = $childIds[$target['child']] ?? null;
            $gradeId = $gradeIds[$target['grade']] ?? null;

            if (! $user || ! $childId || ! $gradeId) {
                continue;
            }

            // the grade must survive the move, exactly as specialties did
            DB::table('option_user')->updateOrInsert(
                ['user_id' => (int) $user->id, 'option_id' => (int) $gradeId], []
            );

            DB::table('users')->where('id', $user->id)->update(['category_child_id' => $childId]);

            $moved[] = [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'child' => $target['child'],
                'grade' => $target['grade'],
            ];
        }

        return $moved;
    }
}
