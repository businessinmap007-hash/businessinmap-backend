<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «حذف آثاث وباب وشباك من ابناء الورش وحذف عفشجي من شحن وتوصيل» — owner,
 * 2026-08-10.
 *
 * A detachment is not a move: it says where a child must STOP being, and it may
 * have nowhere to send it. What it may never do is leave a merchant pointing at
 * a root its child no longer hangs from — that account disappears from every
 * screen at once, and nothing downstream notices.
 */
class ChildRootDetachTest extends TestCase
{
    use DatabaseTransactions;

    private function rootId(string $slug): int
    {
        return (int) DB::table('categories')->where('slug', $slug)->value('id');
    }

    private function childId(string $nameAr): int
    {
        return (int) DB::table('category_children_master')->where('name_ar', $nameAr)->value('id');
    }

    private function standsUnder(string $nameAr, string $rootSlug): bool
    {
        return DB::table('category_parent_child as p')
            ->join('category_children_master as c', 'c.id', '=', 'p.child_id')
            ->where('p.parent_id', $this->rootId($rootSlug))->where('c.name_ar', $nameAr)->exists();
    }

    /**
     * @dataProvider detachments
     */
    public function test_the_child_left_the_root(string $nameAr, string $rootSlug): void
    {
        $this->assertFalse($this->standsUnder($nameAr, $rootSlug), "«{$nameAr}» is still under «{$rootSlug}»");

        $childId = $this->childId($nameAr);

        // The master row is half the undo record; the pivot row that went is the
        // other half. Nothing here deletes a child.
        $this->assertGreaterThan(0, $childId, "«{$nameAr}» lost its master row");

        foreach (['category_platform_services', 'category_child_service_fees'] as $table) {
            $this->assertSame(
                0,
                DB::table($table)->where('category_id', $this->rootId($rootSlug))->where('child_id', $childId)->count(),
                "{$table} still wires «{$nameAr}» under «{$rootSlug}»"
            );
        }

        $this->assertSame(
            0,
            DB::table('category_service_configs')->where('category_id', $this->rootId($rootSlug))
                ->where('child_id', $childId)->where('is_active', 1)->count(),
            "a live config survives for «{$nameAr}» under «{$rootSlug}»"
        );
    }

    /**
     * «آثاث» and «باب وشباك» keep OTHER roots (showroom/company/factory), so
     * their master row is expected to survive regardless of what happens
     * here — that is what makes them the right shape for this test.
     *
     * The other six children this file used to name here — عفشجى، رياض
     * أطفال، ابتدائي، إعدادي، ثانوي عام، ثانوي أزهرى، دبلومات فنية، إدارة
     * صفحات — stood under NO other root, and their master rows were the
     * undo record this test asserted survives. On 2026-08-26 the owner
     * reviewed the platform's full rootless list («جمع الابناء الذين ليس
     * لديهم جذر لاقرر مصيرهم») and hard-deleted every one of them himself,
     * directly against the database — one deliberate exception to «لا شىء
     * يُحذف», not a bug this test should keep failing against.
     * `test_the_owners_2026_08_26_cleanup_left_nothing_dangling` covers what
     * they became instead of «still exists».
     *
     * @return array<string,array{0:string,1:string}>
     */
    public static function detachments(): array
    {
        return [
            'آثاث' => ['آثاث', 'workshops'],
            'باب وشباك' => ['باب وشباك', 'workshops'],
        ];
    }

    /**
     * The owner's own cleanup, 2026-08-26: every child that had stood under
     * no root at all is gone for real now, not just detached. The one row
     * that briefly survived (id #1 — a live, enabled deposit policy for a
     * real account #212 hanging off it) was itself removed later the same
     * day once the owner had the wallet/deposit test data wiped alongside
     * it, closing out the exception this session had flagged.
     */
    public function test_the_owners_2026_08_26_cleanup_left_nothing_dangling(): void
    {
        $goneNames = [
            'عفشجى', 'رياض أطفال', 'ابتدائي', 'إعدادي',
            'ثانوي عام', 'ثانوي أزهري', 'دبلومات فنية', 'إدارة صفحات',
        ];

        foreach ($goneNames as $name) {
            $this->assertSame(
                0,
                DB::table('category_children_master')->where('name_ar', $name)->count(),
                "«{$name}» is back — the owner's 2026-08-26 cleanup should not be reversed by a seeder"
            );
        }

        $stillRootless = DB::table('category_children_master as c')
            ->whereNotExists(fn ($q) => $q->from('category_parent_child')->whereColumn('child_id', 'c.id'))
            ->get(['c.id']);

        $this->assertCount(0, $stillRootless, 'a rootless child survived — nothing should be left dangling now');
    }

    /**
     * «اطوها كالورش» — the workshop shape, applied to دورات وتدريب: six children
     * that were already six OPTIONS standing beside them.
     */
    public function test_every_folded_stage_is_still_a_word_on_the_tutoring_centre(): void
    {
        $centre = (int) DB::table('category_parent_child as p')
            ->join('category_children_master as c', 'c.id', '=', 'p.child_id')
            ->where('p.parent_id', $this->rootId('training-courses'))
            ->where('c.name_ar', 'سنتر دروس')->value('c.id');

        $this->assertGreaterThan(0, $centre, '«سنتر دروس» is gone — the stages folded into nothing');

        $stages = DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('cco.child_id', $centre)->where('g.name_ar', 'المراحل التعليمية')
            ->pluck('o.name_ar')->all();

        foreach (['رياض أطفال', 'ابتدائي', 'إعدادي', 'ثانوي عام', 'ثانوي أزهري', 'دبلومات فنية'] as $stage) {
            $this->assertContains($stage, $stages, "«{$stage}» was folded away without becoming a word");
        }

        // And the centre can still name every subject it teaches, which is what
        // the stage children used to hold between them.
        $subjects = DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('cco.child_id', $centre)->where('g.name_ar', 'المواد الدراسية')->count();

        $this->assertGreaterThan(30, $subjects);
    }

    /**
     * «حضانات» is a PLACE with three live merchants, not a stage — the one row
     * in the matrix that is also a business you walk into.
     */
    public function test_the_nursery_is_not_a_stage_and_stays(): void
    {
        $this->assertTrue($this->standsUnder('حضانات', 'training-courses'));

        $this->assertGreaterThan(
            0,
            DB::table('users')->where('category_child_id', $this->childId('حضانات'))->count()
        );
    }

    /**
     * The matrix that would have gone with them. It is the only record of which
     * subjects belong to which stage and the UI that reads it was never built,
     * so the declaration must outlive the rows.
     */
    public function test_the_stage_subject_matrix_was_not_deleted_with_the_rows(): void
    {
        $source = file_get_contents(database_path('seeders/EducationalStagesSeeder.php'));

        foreach (['ثانوي أزهري', 'دبلومات فنية', 'فلسفة ومنطق', 'قرآن وتجويد'] as $needle) {
            $this->assertStringContainsString($needle, $source, "the matrix lost «{$needle}»");
        }
    }

    /** And nothing re-creates them: the seeder never inserts a stage row again. */
    public function test_the_stages_seeder_does_not_conjure_the_rows_back(): void
    {
        $before = DB::table('category_parent_child')
            ->where('parent_id', $this->rootId('training-courses'))->count();

        $this->artisan('db:seed', ['--class' => 'EducationalStagesSeeder', '--no-interaction' => true])->run();

        $this->assertSame(
            $before,
            DB::table('category_parent_child')->where('parent_id', $this->rootId('training-courses'))->count(),
            'EducationalStagesSeeder put the stage children back'
        );
    }

    /** The 29 furniture workshops landed on the child built for them. */
    public function test_the_furniture_merchants_landed_in_the_workshop(): void
    {
        $workshops = $this->rootId('workshops');

        $this->assertSame(
            0,
            DB::table('users')->where('category_child_id', $this->childId('آثاث'))
                ->where('category_id', $workshops)->count(),
            'a furniture merchant is still filed under a root its child left'
        );

        $this->assertGreaterThanOrEqual(
            29,
            DB::table('users')->where('category_child_id', $this->childId('ورشة أثاث ونجارة'))->count(),
            'the workshop did not receive them'
        );
    }

    /** And «آثاث» keeps every root where it really is a seller. */
    public function test_the_seller_kept_its_other_roots(): void
    {
        foreach (['exhibitions', 'companies', 'factories'] as $slug) {
            $this->assertTrue($this->standsUnder('آثاث', $slug), "«آثاث» lost «{$slug}»");
        }

        // Detaching one root must not strip what the others gave it.
        $this->assertGreaterThan(
            0,
            DB::table('category_child_option')->where('child_id', $this->childId('آثاث'))->count(),
            'the seller was left with no vocabulary at all'
        );
    }

    /** «عفشجى» stood under one root, so this retired the trade outright. */
    public function test_the_mover_now_stands_under_no_root(): void
    {
        $childId = $this->childId('عفشجى');

        $this->assertSame(
            0,
            DB::table('category_parent_child')->where('child_id', $childId)->count()
        );

        $this->assertSame(
            0,
            DB::table('users')->where('category_child_id', $childId)->count(),
            'a merchant was left on a child no root can reach'
        );

        $this->assertGreaterThan(
            0,
            DB::table('users')->where('category_child_id', $this->childId('مندوب'))->count(),
            'the courier tier did not receive him'
        );
    }

    /**
     * The doors trade kept every root it actually SELLS from, and only those.
     *
     * «professions» was on this list until 2026-08-10 and is now asserted the
     * other way. It is the second craftsmen root the trade has had to give
     * back, for the reason ورش gave it back: that root holds twenty-eight
     * one-man crafts, every one of which stands under it alone, and this is a
     * goods trade whose three other standings all carry retail. The craftsman
     * form has always been «نجار باب وشباك».
     */
    public function test_the_doors_trade_kept_its_selling_roots(): void
    {
        foreach (['factories', 'companies', 'shops-online'] as $slug) {
            $this->assertTrue($this->standsUnder('باب وشباك', $slug), "«باب وشباك» lost «{$slug}»");
        }

        foreach (['workshops', 'professions'] as $slug) {
            $this->assertFalse(
                $this->standsUnder('باب وشباك', $slug),
                "the trade is back under «{$slug}», where the craftsman belongs"
            );
        }

        // Both craftsmen roots are answered by the same child, which is why
        // neither detachment left anything unsaid.
        $this->assertTrue($this->standsUnder('ورشة باب وشباك', 'workshops'));
    }

    /**
     * An entry with merchants and no declared destination must be refused
     * outright — the guard that makes this seeder safe to extend by hand.
     */
    public function test_a_detachment_with_no_home_for_its_merchants_is_refused(): void
    {
        foreach (require database_path('seeders/data/child_root_detachments.php') as $entry) {
            if (($entry['reassign_to'] ?? null) !== null) {
                continue;
            }

            $rootId = $this->rootId($entry['root_slug']);
            $childId = $this->childId($entry['child_name_ar']);

            $this->assertSame(
                0,
                DB::table('users')->where('category_child_id', $childId)->where('category_id', $rootId)->count(),
                "«{$entry['child_name_ar']}» was detached from «{$entry['root_slug']}» with merchants on it"
            );
        }
    }

    /** Re-running writes nothing and withdraws nothing. */
    public function test_the_seeder_is_idempotent(): void
    {
        $count = fn () => [
            DB::table('category_parent_child')->count(),
            DB::table('category_child_option')->count(),
            DB::table('category_platform_services')->count(),
            DB::table('users')->count(),
        ];

        $before = $count();

        $this->artisan('db:seed', ['--class' => 'ChildRootDetachSeeder', '--no-interaction' => true])->run();

        $this->assertSame($before, $count());
    }
}
