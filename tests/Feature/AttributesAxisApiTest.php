<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 1c: the attributes axis was wired-dead — an admin could link an
 * option to a category child (category_child_option) and nothing on the
 * merchant or customer side ever read it. This proves the full loop end to
 * end using child 68 («شركة»), the one child with real option links (108
 * توصيل طلبات / 109 شحن وتوصيل / 292 دفع مسبق, all group 12 «أنماط خدمة
 * وتجارية»): a merchant can only pick from what its specialty allows, and a
 * customer can filter businesses by what they picked. Rolls back.
 */
class AttributesAxisApiTest extends TestCase
{
    use DatabaseTransactions;

    private const CHILD_ID = 68;
    private const OPTION_DELIVERY = 108;
    private const OPTION_PREPAID = 292;

    private function business(): User
    {
        return User::query()->forceCreate([
            'name' => 'Test Business '.uniqid(),
            'phone' => '01'.random_int(100000000, 999999999),
            'email' => 'biz'.uniqid().'@test.local',
            'password' => Hash::make('secret123'),
            'api_token' => \Illuminate\Support\Str::random(60),
            'type' => 'business',
            'category_child_id' => self::CHILD_ID,
        ]);
    }

    private function givePricedOffer(User $business): void
    {
        DB::table('business_service_prices')->insert([
            'business_id' => $business->id,
            'child_id' => self::CHILD_ID,
            'service_id' => DB::table('platform_services')->value('id'),
            'bookable_item_type' => 'test_type_'.uniqid(),
            'price' => 100,
            'charge_mode' => 'standard',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_discovery_attributes_lists_the_childs_options_with_business_counts(): void
    {
        $response = $this->getJson('/api/v2/discovery/attributes?child_id='.self::CHILD_ID);

        $response->assertOk();
        $groups = $response->json('data.groups');
        $this->assertNotEmpty($groups, 'child 68 has real option links — the endpoint must surface them');

        $flat = collect($groups)->pluck('options')->flatten(1)->keyBy('id');
        $this->assertTrue($flat->has(self::OPTION_DELIVERY), 'option 108 is linked to child 68');
        $this->assertArrayHasKey('businesses', $flat[self::OPTION_DELIVERY]);
    }

    public function test_businesses_filter_narrows_to_holders_of_every_selected_option(): void
    {
        $both = $this->business();
        $this->givePricedOffer($both);
        DB::table('option_user')->insert([
            ['user_id' => $both->id, 'option_id' => self::OPTION_DELIVERY],
            ['user_id' => $both->id, 'option_id' => self::OPTION_PREPAID],
        ]);

        $onlyOne = $this->business();
        $this->givePricedOffer($onlyOne);
        DB::table('option_user')->insert(['user_id' => $onlyOne->id, 'option_id' => self::OPTION_DELIVERY]);

        $r1 = $this->getJson('/api/v2/discovery/businesses?child_id='.self::CHILD_ID.'&option_ids[]='.self::OPTION_DELIVERY);
        $ids1 = collect($r1->json('data.businesses.data'))->pluck('id');
        $this->assertTrue($ids1->contains($both->id));
        $this->assertTrue($ids1->contains($onlyOne->id));

        $r2 = $this->getJson('/api/v2/discovery/businesses?child_id='.self::CHILD_ID
            .'&option_ids[]='.self::OPTION_DELIVERY.'&option_ids[]='.self::OPTION_PREPAID);
        $ids2 = collect($r2->json('data.businesses.data'))->pluck('id');
        $this->assertTrue($ids2->contains($both->id));
        $this->assertFalse($ids2->contains($onlyOne->id), 'a business missing one selected attribute must be filtered out');
    }

    public function test_merchant_can_only_sync_options_that_belong_to_its_own_specialty(): void
    {
        $merchant = $this->business();

        $response = $this->actingAs($merchant, 'sanctum')
            ->patchJson('/api/v2/profile/options', ['option_ids' => [self::OPTION_DELIVERY, self::OPTION_PREPAID]]);

        $response->assertOk();
        $this->assertSame([self::OPTION_DELIVERY, self::OPTION_PREPAID], $response->json('data.selected_ids'));
        $this->assertDatabaseHas('option_user', ['user_id' => $merchant->id, 'option_id' => self::OPTION_DELIVERY]);

        $foreignOption = DB::table('options')
            ->whereNotIn('id', DB::table('category_child_option')->where('child_id', self::CHILD_ID)->pluck('option_id'))
            ->value('id');

        $rejected = $this->actingAs($merchant, 'sanctum')
            ->patchJson('/api/v2/profile/options', ['option_ids' => [$foreignOption]]);

        $rejected->assertStatus(422);
        $this->assertDatabaseHas('option_user', ['user_id' => $merchant->id, 'option_id' => self::OPTION_DELIVERY]);
    }

    public function test_option_ids_present_but_empty_clears_all_selections(): void
    {
        $merchant = $this->business();
        DB::table('option_user')->insert(['user_id' => $merchant->id, 'option_id' => self::OPTION_DELIVERY]);

        $response = $this->actingAs($merchant, 'sanctum')
            ->patchJson('/api/v2/profile/options', ['option_ids' => []]);

        $response->assertOk();
        $this->assertDatabaseMissing('option_user', ['user_id' => $merchant->id]);
    }

    public function test_a_client_account_has_no_attributes_to_set(): void
    {
        $client = User::query()->where('type', 'client')->first();

        if (! $client) {
            $this->markTestSkipped('No client account to act as.');
        }

        $this->actingAs($client, 'sanctum')
            ->patchJson('/api/v2/profile/options', ['option_ids' => []])
            ->assertStatus(403);

        $this->actingAs($client, 'sanctum')
            ->getJson('/api/v2/profile/options')
            ->assertStatus(403);
    }
}
