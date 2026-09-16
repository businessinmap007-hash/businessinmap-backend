<?php

namespace Tests\Feature;

use App\Models\BusinessMenuSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * «التسليم والاستلام» reactivated 2026-09-16: a prior design guessed
 * "shipping" vs "delivery" from whether a business carried `retail`, which
 * mislabelled an ordinary greengrocer as a factory (retail and menu coexist
 * on ~56 goods children on purpose). Reverted in favour of asking the
 * merchant directly, on the same options screen every other option group
 * already uses — checkout shows exactly what was ticked.
 */
class FulfillmentMethodsFromProfileTest extends TestCase
{
    use DatabaseTransactions;

    private const GROUP_NAME = 'التسليم والاستلام';

    private function groupId(): int
    {
        return (int) DB::table('option_groups')->where('name_ar', self::GROUP_NAME)->value('id');
    }

    private function makeBusiness(int $childId): User
    {
        $u = new User();
        $u->name = 'Shop '.Str::random(4);
        $u->email = 'shop-'.uniqid().'@example.test';
        $u->phone = '01'.random_int(100000000, 999999999);
        $u->password = 'secret-password';
        $u->type = User::TYPE_BUSINESS;
        $u->category_id = 17; // المحلات أو أونلاين
        $u->category_child_id = $childId;
        $u->api_token = Str::random(80);
        $u->save();

        return $u;
    }

    public function test_the_group_is_active_again(): void
    {
        $groupId = $this->groupId();

        $this->assertGreaterThan(0, $groupId);
        $this->assertSame(1, (int) DB::table('option_groups')->where('id', $groupId)->value('is_active'));
    }

    public function test_pickup_from_location_exists_alongside_the_original_five(): void
    {
        $names = DB::table('options')->where('group_id', $this->groupId())->pluck('name_ar');

        foreach (['توصيل طلبات', 'تسليم أرض المصنع', 'توصيل مجانى', 'شحن', 'تيك أواى', 'استلام من المكان'] as $name) {
            $this->assertContains($name, $names->all(), "«{$name}»");
        }
    }

    public function test_checkout_shows_exactly_what_the_merchant_ticked(): void
    {
        $childId = (int) DB::table('category_children_master')->where('name_ar', 'خضار وفاكهة')->value('id');
        $business = $this->makeBusiness($childId);

        $shipping = (int) DB::table('options')->where('group_id', $this->groupId())->where('name_ar', 'شحن')->value('id');
        $pickupHere = (int) DB::table('options')->where('group_id', $this->groupId())->where('name_ar', 'استلام من المكان')->value('id');

        DB::table('option_user')->insert([
            ['user_id' => $business->id, 'option_id' => $shipping],
            ['user_id' => $business->id, 'option_id' => $pickupHere],
        ]);

        $methods = BusinessMenuSetting::fulfillmentMethodsFor($business->fresh());
        $names = collect($methods)->pluck('name_ar')->all();

        $this->assertCount(2, $methods, 'only the two ticked options, nothing guessed');
        $this->assertContains('شحن', $names);
        $this->assertContains('استلام من المكان', $names);

        $byName = collect($methods)->keyBy('name_ar');
        $this->assertSame('delivery', $byName['شحن']['type']);
        $this->assertSame('pickup', $byName['استلام من المكان']['type']);
    }

    public function test_a_business_that_never_configured_it_gets_the_two_generic_defaults(): void
    {
        $childId = (int) DB::table('category_children_master')->where('name_ar', 'خضار وفاكهة')->value('id');
        $business = $this->makeBusiness($childId);

        $methods = BusinessMenuSetting::fulfillmentMethodsFor($business);
        $names = collect($methods)->pluck('name_ar')->all();

        $this->assertContains('توصيل طلبات', $names);
        $this->assertContains('استلام من المكان', $names);
    }

    public function test_the_public_business_page_carries_the_methods(): void
    {
        $childId = (int) DB::table('category_children_master')->where('name_ar', 'خضار وفاكهة')->value('id');
        $business = $this->makeBusiness($childId);

        $deliveryOrders = (int) DB::table('options')->where('group_id', $this->groupId())->where('name_ar', 'توصيل طلبات')->value('id');
        DB::table('option_user')->insert(['user_id' => $business->id, 'option_id' => $deliveryOrders]);

        $data = $this->getJson('/api/v2/businesses/' . $business->id)
            ->assertOk()
            ->json('data');

        $names = collect($data['fulfillment']['methods'])->pluck('name_ar')->all();
        $this->assertSame(['توصيل طلبات'], $names);
    }
}
