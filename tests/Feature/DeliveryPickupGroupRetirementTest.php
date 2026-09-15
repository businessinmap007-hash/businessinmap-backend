<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * «التسليم والاستلام» (option_groups #49) is retired by deactivating the
 * GROUP alone (is_active=0) - never by deleting its options, links or
 * category_child_option_decisions. Those stay exactly as they were (the
 * `child_option_groups.php` "fulfilment" bundle wiring depends on them
 * platform-wide - see the incident this test follows). Only visibility on
 * the two screens that read CategoryChild::activeOptions() is affected.
 */
class DeliveryPickupGroupRetirementTest extends TestCase
{
    use DatabaseTransactions;

    private const GROUP_NAME = 'التسليم والاستلام';

    private const ANTIQUES_CHILD = 21; // carries all five of the group's options

    private function makeBusiness(int $childId): User
    {
        $u = new User();
        $u->name = 'Shop ' . Str::random(4);
        $u->email = 'shop-' . uniqid() . '@example.test';
        $u->phone = '01' . random_int(100000000, 999999999);
        $u->password = 'secret-password';
        $u->type = User::TYPE_BUSINESS;
        $u->category_id = 21; // معارض
        $u->category_child_id = $childId;
        $u->api_token = Str::random(80);
        $u->save();

        return $u;
    }

    private function groupId(): int
    {
        return (int) DB::table('option_groups')->where('name_ar', self::GROUP_NAME)->value('id');
    }

    public function test_the_group_is_deactivated_not_deleted(): void
    {
        $groupId = $this->groupId();

        $this->assertGreaterThan(0, $groupId, 'the group itself must still exist');
        $this->assertSame(0, (int) DB::table('option_groups')->where('id', $groupId)->value('is_active'));
        $this->assertGreaterThan(0, DB::table('options')->where('group_id', $groupId)->count(), 'its options must still exist');
        $this->assertGreaterThan(
            0,
            DB::table('category_child_option')->whereIn('option_id', DB::table('options')->where('group_id', $groupId)->pluck('id'))->count(),
            'child links must still exist — the fulfilment bundle depends on them'
        );
    }

    public function test_the_business_no_longer_sees_it_on_its_own_attributes_screen(): void
    {
        $business = $this->makeBusiness(self::ANTIQUES_CHILD);

        $groups = $this->actingAs($business, 'sanctum')
            ->getJson('/api/v2/profile/options')
            ->assertOk()
            ->json('data.groups');

        $names = collect($groups)->pluck('name');
        $this->assertNotContains(self::GROUP_NAME, $names);
    }

    public function test_it_no_longer_appears_as_a_discovery_filter(): void
    {
        $business = $this->makeBusiness(self::ANTIQUES_CHILD);
        $groupId = $this->groupId();
        $optionId = (int) DB::table('options')->where('group_id', $groupId)->value('id');

        // Even a business that HAS explicitly ticked one of the five options...
        DB::table('option_user')->insert(['user_id' => $business->id, 'option_id' => $optionId]);

        $groups = $this->getJson('/api/v2/discovery/attributes?child_id=' . self::ANTIQUES_CHILD . '&category_id=21')
            ->assertOk()
            ->json('data.groups');

        $ids = collect($groups)->pluck('id');
        $this->assertNotContains($groupId, $ids, '...must not surface the retired group as a filter');
    }
}
