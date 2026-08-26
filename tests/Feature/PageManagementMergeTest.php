<?php

namespace Tests\Feature;

use App\Services\MerchantOfferingVocabulary;
use Database\Seeders\ChildRenameSeeder;
use Database\Seeders\ChildRootDetachSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «ادمج إدارة صفحات فى دعاية وإعلان ولكن فى اسم يعبر عن الاثنين» — المالك،
 * 2026-08-25.
 *
 * «إدارة صفحات» #205 had 0 merchants and had never carried its own
 * vocabulary — only four rows borrowed from «تخصصات الدعاية والإعلان» and the
 * subscription axis «نظام التعاقد». The keeper (#11) already carried the
 * superset; the merge widens it with the one thing it did not yet carry.
 *
 * Rolls back.
 */
class PageManagementMergeTest extends TestCase
{
    use DatabaseTransactions;

    private const KEEPER_ID = 11;

    private const FOLDED_ID = 205;

    public function test_the_keeper_carries_the_new_name(): void
    {
        $row = DB::table('category_children_master')->where('id', self::KEEPER_ID)->first(['name_ar', 'name_en']);

        $this->assertSame('دعاية وإعلان وإدارة صفحات', $row->name_ar);
        $this->assertSame('Advertising & Page Management', $row->name_en);
    }

    /**
     * The master row survived as the undo record until 2026-08-26, when the
     * owner reviewed the whole rootless list («جمع الابناء الذين ليس لديهم
     * جذر لاقرر مصيرهم») and hard-deleted it himself, directly against the
     * database — an explicit, one-time exception to «لا شىء فى هذا التصنيف
     * يُحذف», not a bug in any seeder or screen here. What the merge itself
     * promised — the keeper carries everything, no account is stranded —
     * still has to hold with the row gone.
     */
    public function test_the_folded_child_is_gone_and_the_keeper_still_carries_everything(): void
    {
        $this->assertNull(
            DB::table('category_children_master')->where('id', self::FOLDED_ID)->first(),
            'the owner deleted this row 2026-08-26 — it should not have come back'
        );

        $this->assertSame(
            0,
            DB::table('users')->where('category_child_id', self::FOLDED_ID)->count(),
            'a merchant is pointed at a child that no longer exists'
        );
    }

    public function test_the_keeper_carries_the_borrowed_specialties_as_its_own(): void
    {
        $lines = DB::table('category_child_option as l')
            ->join('options as o', 'o.id', '=', 'l.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('l.child_id', self::KEEPER_ID)
            ->where('g.name_ar', 'تخصصات الدعاية والإعلان')
            ->pluck('o.name_ar')->all();

        foreach (['تسويق رقمي وسوشيال ميديا', 'إعلانات ممولة', 'تصميم جرافيك', 'تصوير وإنتاج'] as $row) {
            $this->assertContains($row, $lines, "«{$row}» — the digital half «إدارة صفحات» borrowed — is missing");
        }

        // The physical half is not lost either: the keeper owned it outright
        // and always did.
        foreach (['لافتات وإعلانات طرق', 'مطبوعات دعائية'] as $row) {
            $this->assertContains($row, $lines);
        }
    }

    /**
     * The one thing the folded child carried that the keeper did not: the
     * subscription-billing axis. Widened rather than duplicated — «حملة» and
     * «إدارة مستمرة» are now the same question with four answers instead of
     * two.
     */
    public function test_the_keeper_gained_the_subscription_axis(): void
    {
        $terms = DB::table('category_child_option as l')
            ->join('options as o', 'o.id', '=', 'l.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('l.child_id', self::KEEPER_ID)
            ->where('g.name_ar', 'نظام التعاقد')
            ->pluck('o.name_ar')->all();

        foreach (['بالمهمة', 'شهري', 'ربع سنوي', 'سنوي'] as $term) {
            $this->assertContains($term, $terms, "«{$term}» is missing from the merged trade's billing terms");
        }
    }

    /** The end-to-end read: a merchant on the keeper sees one merged list. */
    public function test_the_merchant_facing_reader_sees_one_merged_child(): void
    {
        $keeper = DB::table('category_children_master')->where('id', self::KEEPER_ID)->first(['id']);
        $rootId = (int) DB::table('category_parent_child')->where('child_id', $keeper->id)->value('parent_id');

        $lines = collect(app(MerchantOfferingVocabulary::class)->for(0, self::KEEPER_ID, $rootId)['lines']);
        $names = $lines->flatMap(fn ($set) => collect($set)->pluck('name_ar'));

        $this->assertTrue(
            $names->contains('تسويق رقمي وسوشيال ميديا') && $names->contains('لافتات وإعلانات طرق'),
            'the merged trade cannot price both halves of what it now sells'
        );
    }

    /**
     * Every file that named the keeper by its OLD name («دعاية وإعلان») has
     * to know the new one, or a rename silently unwires it — the exact
     * failure `child_renames.php`'s own docblock warns about.
     */
    public function test_no_data_file_still_names_the_keeper_by_its_old_name(): void
    {
        $offenders = [];

        foreach (glob(database_path('seeders/data/*.php')) ?: [] as $file) {
            // The legacy v1 `categories` dump shares the string by coincidence
            // (a different table, a different id) and is out of scope —
            // [[v1-dead-code-keep]]. `child_renames.php` is the historical
            // record of the OLD name and is supposed to say it.
            if (str_contains($file, 'categories.php') || str_contains($file, 'child_renames.php')) {
                continue;
            }

            $body = file_get_contents($file);

            if (preg_match("/(?<!و)'دعاية وإعلان'(?!\\s*و)/u", $body)) {
                $offenders[] = basename($file);
            }
        }

        $this->assertSame([], $offenders, 'ما زالت تسمّى الابن بالاسم القديم : ' . implode('، ', $offenders));
    }

    public function test_the_rename_and_detach_seeders_are_idempotent(): void
    {
        $before = [
            'name' => DB::table('category_children_master')->where('id', self::KEEPER_ID)->value('name_ar'),
            'roots' => DB::table('category_parent_child')->where('child_id', self::FOLDED_ID)->count(),
            'options' => DB::table('options')->count(),
        ];

        (new ChildRenameSeeder())->run();
        (new ChildRootDetachSeeder())->run();

        $this->assertSame($before['name'], DB::table('category_children_master')->where('id', self::KEEPER_ID)->value('name_ar'));
        $this->assertSame($before['roots'], DB::table('category_parent_child')->where('child_id', self::FOLDED_ID)->count());
        $this->assertSame($before['options'], DB::table('options')->count());
    }
}
