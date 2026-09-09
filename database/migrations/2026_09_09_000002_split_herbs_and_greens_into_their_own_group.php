<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "أعشاب وورقيات" (Herbs & Greens) was a single flat OPTION (#2442) sitting
 * inside "الخضروات" (group 825, Vegetables) — a catch-all right alongside
 * the specific herbs it was supposed to summarize (نعناع، بقدونس،
 * كزبرة خضراء...), which lived as ten more of the SAME group's own flat
 * options. A greengrocer saw "أعشاب وورقيات" as one more vegetable to tick
 * next to "بطاطس", with no grouping at all — exactly the shape the owner
 * asked to fix: "أعشاب وورقيات يجب ان تكون قسما ... ويكون تحتها النعناع
 * والبقدونس والملوخية وكل ما يندرج تحت الاعشاب والورقيات".
 *
 * This gives it a real group of its own, mirroring "الخضروات"/"الفواكه"
 * exactly (same `line` role, same platform-wide reach): the ten options
 * move in by re-pointing `options.group_id` — every existing
 * `category_child_option` grant and every item already priced on one of
 * them is untouched, since those key on `option_id`, never `group_id`.
 *
 * The old catch-all (#2442) moves in too rather than being deleted — it is
 * unused as a line by any live item (verified before writing this), but
 * category_child_option still grants it to FOUR other children (149, 185,
 * 272, 109) that were never checked for whether they also carry the ten
 * specific herbs; deleting it could leave one of them with no "herbs" line
 * at all. Renamed to read as its own catch-all rather than a duplicate of
 * the group it now sits in.
 *
 * Idempotent: re-running finds the group already made and the options
 * already moved, and changes nothing.
 */
return new class extends Migration
{
    private const GROUP_NAME_AR = 'أعشاب وورقيات';

    private const GROUP_NAME_EN = 'Herbs & Greens';

    private const OLD_GROUP_ID = 825; // «الخضروات» / Vegetables

    /** @var array<int,array{ar:string,en:string}> for the down() rename of #2442 only — every other option keeps its existing name. */
    private const CATCHALL_OPTION_ID = 2442;

    private const CATCHALL_RENAME_AR = 'أعشاب متنوعة';

    private const CATCHALL_RENAME_EN = 'Mixed Herbs';

    /** @var array<int,int> the specific herbs moving out of "الخضروات" */
    private const MOVE_OPTION_IDS = [
        2430, // ملوخية / Molokhia
        7066, // بقدونس / Parsley
        7067, // كزبرة خضراء / Fresh Coriander
        7068, // شبت / Dill
        7069, // نعناع / Fresh Mint
        7070, // ريحان / Basil
        7071, // جرجير / Rocket
        7072, // كرفس / Celery
        7081, // زنجبيل طازج / Fresh Ginger
        7082, // ورق عنب / Vine Leaves
    ];

    public function up(): void
    {
        DB::transaction(function () {
            $groupId = DB::table('option_groups')->where('name_ar', self::GROUP_NAME_AR)->value('id');

            if (! $groupId) {
                $groupId = DB::table('option_groups')->insertGetId([
                    'name_ar' => self::GROUP_NAME_AR,
                    'name_en' => self::GROUP_NAME_EN,
                    'reorder' => 0,
                    'is_active' => 1,
                    'price_role' => 'line',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('options')
                ->whereIn('id', self::MOVE_OPTION_IDS)
                ->update(['group_id' => $groupId, 'updated_at' => now()]);

            DB::table('options')
                ->where('id', self::CATCHALL_OPTION_ID)
                ->update([
                    'group_id' => $groupId,
                    'name_ar' => self::CATCHALL_RENAME_AR,
                    'name_en' => self::CATCHALL_RENAME_EN,
                    'updated_at' => now(),
                ]);
        });
    }

    public function down(): void
    {
        DB::table('options')
            ->whereIn('id', self::MOVE_OPTION_IDS)
            ->update(['group_id' => self::OLD_GROUP_ID, 'updated_at' => now()]);

        DB::table('options')
            ->where('id', self::CATCHALL_OPTION_ID)
            ->update([
                'group_id' => self::OLD_GROUP_ID,
                'name_ar' => self::GROUP_NAME_AR,
                'name_en' => self::GROUP_NAME_EN,
                'updated_at' => now(),
            ]);

        // The group itself is left in place even after every option moves
        // back out — same convention as every other option-catalog
        // migration here (see e.g. 2026_09_09_000000): an empty group is
        // inert, and deleting it risks a race with a concurrent grant.
    }
};
