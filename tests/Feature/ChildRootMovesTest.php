<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «انقل مأذون شرعى من مهن وحرفيين الى مكاتب» — owner, 2026-08-09.
 *
 * A filing correction, not a remodel: a مأذون is not a craftsman you call to
 * the house, he receives you at his office beside «محاماه». Nothing is retired
 * and no vocabulary changes — only the root the child hangs from, because that
 * root is where a customer looks.
 *
 * The same conversation set two rules these tests also guard:
 *
 *   «نحن لا نحذف الفروع … ليس معنى ان الفرع لا يوجد حسابات تستخدمه انه يحذف»
 *   «حداد … هو فني حدادة وفى مهن هى ورشة حدادة» — one name under two roots can
 *   be two different trades, so a same-name pair is never merged on sight.
 */
class ChildRootMovesTest extends TestCase
{
    use DatabaseTransactions;

    private const PROFESSIONS = 6;
    private const OFFICES = 19;

    private function childId(string $nameAr): int
    {
        return (int) DB::table('category_children_master')->where('name_ar', $nameAr)->value('id');
    }

    /** It hangs from مكاتب now, and from مهن no longer. */
    public function test_the_marriage_registrar_moved_to_the_offices_root(): void
    {
        $childId = $this->childId('مأذون شرعى');
        $this->assertGreaterThan(0, $childId);

        $roots = DB::table('category_parent_child')->where('child_id', $childId)
            ->pluck('parent_id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains(self::OFFICES, $roots);
        $this->assertNotContains(self::PROFESSIONS, $roots);
    }

    /**
     * A move must carry the wiring, or the child arrives offering nothing —
     * the config rows are keyed on the ROOT, so leaving them behind is silent.
     */
    public function test_its_services_came_with_it(): void
    {
        $childId = $this->childId('مأذون شرعى');
        $booking = (int) DB::table('platform_services')->where('key', 'booking')->value('id');

        $this->assertTrue(
            DB::table('category_platform_services')
                ->where('category_id', self::OFFICES)->where('child_id', $childId)
                ->where('platform_service_id', $booking)->where('is_active', 1)->exists(),
            'the booking link stayed behind under مهن وحرفيين'
        );

        $config = json_decode((string) DB::table('category_service_configs')
            ->where('category_id', self::OFFICES)->where('child_id', $childId)
            ->where('platform_service_id', $booking)->where('is_active', 1)->value('config'), true) ?: [];

        $this->assertContains('booking_appointment', $config['allowed_item_types'] ?? []);
        $this->assertFalse((bool) ($config['requires_bookable_item'] ?? false));

        foreach (['category_platform_services', 'category_service_configs', 'category_child_service_fees'] as $table) {
            $this->assertSame(
                0,
                DB::table($table)->where('category_id', self::PROFESSIONS)->where('child_id', $childId)->count(),
                "{$table} still has a row under the old root"
            );
        }
    }

    /** Nobody is left pointing at a root the child no longer hangs from. */
    public function test_no_account_is_left_on_the_old_root(): void
    {
        $childId = $this->childId('مأذون شرعى');

        $this->assertSame(
            0,
            DB::table('users')->where('category_child_id', $childId)->where('category_id', self::PROFESSIONS)->count()
        );
    }

    /**
     * «حداد» stays two rows. Under ورش it is the workshop, under مهن it is the
     * tradesman — same word, two businesses — so the earlier "duplicate" reading
     * was wrong and must not be re-applied by a future sweep.
     */
    public function test_the_blacksmith_pair_is_left_alone(): void
    {
        $rows = DB::table('category_children_master')->where('name_ar', 'حداد')->pluck('id');

        $this->assertGreaterThanOrEqual(2, $rows->count(), '«حداد» was merged into one row');

        $roots = DB::table('category_parent_child')->whereIn('child_id', $rows)
            ->pluck('parent_id')->map(fn ($id) => (int) $id)->unique();

        $this->assertGreaterThanOrEqual(2, $roots->count(), '«حداد» no longer stands under two roots');
    }

    /** Re-running the move does nothing at all. */
    public function test_the_seeder_is_idempotent(): void
    {
        $childId = $this->childId('مأذون شرعى');

        $before = [
            DB::table('category_parent_child')->where('child_id', $childId)->count(),
            DB::table('category_platform_services')->where('child_id', $childId)->count(),
            DB::table('category_service_configs')->where('child_id', $childId)->count(),
        ];

        $this->artisan('db:seed', ['--class' => 'ChildRootMovesSeeder', '--no-interaction' => true])->run();

        $this->assertSame($before, [
            DB::table('category_parent_child')->where('child_id', $childId)->count(),
            DB::table('category_platform_services')->where('child_id', $childId)->count(),
            DB::table('category_service_configs')->where('child_id', $childId)->count(),
        ]);
    }
}
