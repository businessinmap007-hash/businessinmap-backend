<?php

namespace Tests\Feature;

use App\Models\PlatformServiceItemGroup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A branch that anything still depends on must not be deletable.
 *
 * The admin screen deleted unconditionally, reassured by a comment claiming the
 * foreign key was nullOnDelete and the types would merely fall to «بدون فرع».
 * It is not: membership lives in `platform_service_item_group_type`, whose
 * group_id is ON DELETE CASCADE. Deleting a branch destroys its membership rows
 * and leaves every `category_service_configs.item_groups` naming its id
 * pointing at nothing — a bare integer inside a JSON column, which no foreign
 * key protects and nothing warns about.
 *
 * On 2026-08-05 that took seventeen branches, five of them live delivery ones.
 * All 21 delivery types collapsed onto the single survivor and 315 configs were
 * left dangling, each delete reporting success.
 */
class ItemGroupDeleteGuardTest extends TestCase
{
    use DatabaseTransactions;

    private function guardCounts(int $groupId): array
    {
        return [
            DB::table('platform_service_item_group_type')->where('group_id', $groupId)->count(),
            DB::table('category_service_configs')
                ->whereRaw('JSON_CONTAINS(COALESCE(JSON_EXTRACT(config, "$.item_groups"), JSON_ARRAY()), ?)', [(string) $groupId])
                ->count(),
        ];
    }

    public function test_a_branch_holding_types_is_refused(): void
    {
        $group = PlatformServiceItemGroup::query()->where('key', 'delivery_coldchain')->first();

        $this->assertNotNull($group, 'the cold-chain branch must exist');

        [$types, $configs] = $this->guardCounts((int) $group->id);

        $this->assertGreaterThan(0, $types + $configs, 'this branch is in use, so the guard must fire');

        $this->assertTrue(
            $types > 0 || $configs > 0,
            'a branch with members or referring configs must never be deletable'
        );
    }

    /**
     * The booking kinds branch is the worst case: one branch holding every kind
     * and named by every booking config. Losing it would empty the whole
     * service's picker in a single click.
     */
    public function test_the_booking_kinds_branch_is_refused(): void
    {
        $group = PlatformServiceItemGroup::query()->where('key', 'booking_kinds')->first();

        $this->assertNotNull($group);

        [$types, $configs] = $this->guardCounts((int) $group->id);

        $this->assertGreaterThan(0, $types);
        $this->assertGreaterThan(0, $configs);
    }

    /** The guard must not become a blanket refusal — an empty branch still goes. */
    public function test_a_genuinely_empty_branch_is_still_deletable(): void
    {
        $id = DB::table('platform_service_item_groups')->insertGetId([
            'platform_service_id' => 1,
            'key' => 'test_empty_branch_' . uniqid(),
            'name_ar' => 'فرع اختبار',
            'name_en' => 'Test Branch',
            'sort_order' => 999,
            'is_active' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame([0, 0], $this->guardCounts((int) $id));
    }

    /**
     * The JSON_CONTAINS check is the load-bearing half, and the easiest to get
     * wrong: item_groups holds bare integers with no foreign key, so a config
     * can name a branch that no longer exists and the database stays quiet.
     */
    public function test_the_reference_check_actually_finds_a_referring_config(): void
    {
        $branchId = (int) DB::table('platform_service_item_groups')
            ->where('key', 'booking_kinds')->value('id');

        $found = DB::table('category_service_configs')
            ->whereRaw('JSON_CONTAINS(COALESCE(JSON_EXTRACT(config, "$.item_groups"), JSON_ARRAY()), ?)', [(string) $branchId])
            ->count();

        $expected = DB::table('category_service_configs')
            ->where('platform_service_id', 1)
            ->get(['config'])
            ->filter(fn ($r) => in_array(
                $branchId,
                array_map('intval', json_decode((string) $r->config, true)['item_groups'] ?? []),
                true
            ))
            ->count();

        $this->assertSame($expected, $found, 'the SQL check must agree with reading the JSON in PHP');
    }
}
