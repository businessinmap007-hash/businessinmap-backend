<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminV2\CategoryServiceBulkController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards the bulk services screen against overwriting a child's item-type
 * selection with its whole branch.
 *
 * Before the kinds collapse, a branch was a coarse group and ticking it to mean
 * «all of these» was right. The collapse then put all eleven booking kinds into
 * ONE branch, «أنواع الحجز», which a child chooses AMONG: a clinic takes كشف،
 * متابعة، استشارة أونلاين، زيارة منزلية and not إجراء طبي or حجز طاولة.
 *
 * That turned a harmless union into a destructive one. The stored config keeps
 * `item_groups = [84]` alongside its four kinds, so re-opening the screen
 * re-ticked the branch, expanded it to all eleven, and saving wrote all eleven
 * back — undoing in one click what the child workbench had set, with a success
 * message either way.
 */
class ServiceBulkAllowedTypesTest extends TestCase
{
    use DatabaseTransactions;

    private const CLINIC = 514;

    private const BOOKING = 1;

    private function rootId(): int
    {
        return (int) DB::table('category_service_configs')
            ->where('child_id', self::CLINIC)
            ->where('platform_service_id', self::BOOKING)
            ->value('category_id');
    }

    /**
     * Every case here is an admin WORKING the picker — ticking a branch, naming
     * types — so the request carries the touched flag the blade raises on the
     * first change event. An untouched picker is a different question, and
     * BulkSaveKeepsChildKindsTest asks it: it must not write to anyone.
     */
    private function apply(array $itemGroups, array $allowedTypes): array
    {
        // Resolved from the container: the controller now takes the one
        // ChildServiceWriter every admin screen writes through.
        app(CategoryServiceBulkController::class)->apply(Request::create('/x', 'POST', [
            'root_id' => $this->rootId(),
            'category_ids' => [self::CLINIC],
            'platform_service_ids' => [self::BOOKING],
            'mode' => 'append',
            'item_groups' => [self::BOOKING => $itemGroups],
            'allowed_item_types' => [self::BOOKING => $allowedTypes],
            'types_touched' => [self::BOOKING => 1],
        ]));

        $config = json_decode((string) DB::table('category_service_configs')
            ->where('child_id', self::CLINIC)
            ->where('platform_service_id', self::BOOKING)
            ->value('config'), true);

        return $config['allowed_item_types'] ?? [];
    }

    /**
     * The case that was losing data: the branch is ticked (so its id is posted)
     * AND specific types are named. The named types are the answer.
     */
    public function test_ticked_types_win_over_the_branch_they_sit_in(): void
    {
        $branchId = (int) DB::table('platform_service_item_groups')
            ->where('platform_service_id', self::BOOKING)
            ->where('key', 'booking_kinds')
            ->value('id');

        $this->assertNotSame(0, $branchId, 'the booking kinds branch must exist');

        $wanted = ['booking_examination', 'booking_follow_up'];

        $this->assertSame($wanted, $this->apply([$branchId], $wanted));
    }

    /** With nothing fine-tuned, ticking a branch still means all of its types. */
    public function test_a_branch_with_no_named_types_still_expands(): void
    {
        $branchId = (int) DB::table('platform_service_item_groups')
            ->where('platform_service_id', self::BOOKING)
            ->where('key', 'booking_kinds')
            ->value('id');

        $expected = DB::table('platform_service_item_group_type as gt')
            ->join('platform_service_item_types as t', 't.id', '=', 'gt.item_type_id')
            ->where('gt.group_id', $branchId)
            ->where('t.platform_service_id', self::BOOKING)
            ->where('t.is_active', 1)
            ->pluck('t.key')
            ->all();

        $got = $this->apply([$branchId], []);

        sort($expected);
        sort($got);

        $this->assertSame($expected, $got);
        $this->assertGreaterThan(2, count($got), 'the branch should expand to more than a fine-tuned pick');
    }

    /** A retired kind must never come back through a branch expansion. */
    public function test_the_expansion_skips_retired_types(): void
    {
        $branchId = (int) DB::table('platform_service_item_groups')
            ->where('platform_service_id', self::BOOKING)
            ->where('key', 'booking_kinds')
            ->value('id');

        $retired = DB::table('platform_service_item_types')
            ->where('platform_service_id', self::BOOKING)
            ->where('is_active', 0)
            ->pluck('key');

        $got = $this->apply([$branchId], []);

        foreach ($retired as $key) {
            $this->assertNotContains((string) $key, $got, "«{$key}» is retired and must not be offered");
        }
    }
}
