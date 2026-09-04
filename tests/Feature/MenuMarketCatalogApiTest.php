<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\User;
use App\Services\MerchantOfferingVocabulary;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * bim_app counterpart to MenuMarketCatalogTest (the web panel) — same
 * MenuMarketCatalogService underneath, so this only proves the JSON surface
 * and its bilingual shape; the business rules are already covered there.
 */
class MenuMarketCatalogApiTest extends TestCase
{
    use DatabaseTransactions;

    private function marketBusiness(int $childId): User
    {
        return User::query()->where('type', 'business')->where('category_child_id', $childId)->orderBy('id')->first()
            ?: $this->markTestSkipped("No business stands on child #{$childId}.");
    }

    private function firstLineOption(User $business): object
    {
        $lines = app(MerchantOfferingVocabulary::class)->for(
            (int) $business->id,
            (int) $business->category_child_id,
            (int) $business->category_id
        )['lines'];

        if ($lines->isEmpty()) {
            $this->markTestSkipped('This market has no line vocabulary.');
        }

        return $lines->first()->first();
    }

    public function test_index_returns_bilingual_groups_for_a_market_child(): void
    {
        $business = $this->marketBusiness(272);
        $option = $this->firstLineOption($business);

        $this->actingAs($business, 'sanctum')
            ->getJson('/api/v2/business/menu/market-catalog')
            ->assertOk()
            ->assertJsonFragment(['name_ar' => (string) $option->name_ar])
            ->assertJsonPath('data.groups.0.filled', fn ($v) => is_int($v));
    }

    public function test_index_is_forbidden_for_a_made_to_order_trade(): void
    {
        $restaurant = User::query()
            ->where('type', 'business')->where('category_child_id', '>', 0)
            ->whereIn('id', function ($q) {
                $q->select('u.id')->from('users as u')
                    ->join('category_children_master as c', 'c.id', '=', 'u.category_child_id')
                    ->where('c.name_ar', 'مطعم');
            })
            ->orderBy('id')->first() ?: $this->markTestSkipped('Needs a restaurant business.');

        $this->actingAs($restaurant, 'sanctum')
            ->getJson('/api/v2/business/menu/market-catalog')
            ->assertForbidden();
    }

    public function test_save_creates_a_priced_item_and_the_customer_endpoint_never_sees_the_cost(): void
    {
        $business = $this->marketBusiness(272);
        $option = $this->firstLineOption($business);

        $this->actingAs($business, 'sanctum')
            ->postJson('/api/v2/business/menu/market-catalog', [
                'rows' => [
                    $option->id => [
                        'quantity' => 40,
                        'supply_price' => 30,
                        'base_price' => 45,
                        'sale_unit' => 'kg',
                        'brand_name' => 'المراعي',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.saved', 1);

        $item = MenuItem::query()->where('business_id', $business->id)->latest('id')->first();
        $this->assertSame(45.0, (float) $item->base_price);
        $this->assertSame('المراعي', $item->brand_name);

        $this->getJson('/api/v2/discovery/menu/' . $business->id)
            ->assertOk()
            ->assertDontSee('supply_price');
    }

    public function test_save_with_a_blank_price_clears_the_row_instead_of_deleting_it(): void
    {
        $business = $this->marketBusiness(272);
        $option = $this->firstLineOption($business);

        $this->actingAs($business, 'sanctum')->postJson('/api/v2/business/menu/market-catalog', [
            'rows' => [$option->id => ['base_price' => 45]],
        ])->assertOk();

        $item = MenuItem::query()->where('business_id', $business->id)->latest('id')->first();
        $this->assertTrue((bool) $item->is_active);

        $this->actingAs($business, 'sanctum')->postJson('/api/v2/business/menu/market-catalog', [
            'rows' => [$option->id => ['base_price' => '']],
        ])->assertOk()->assertJsonPath('data.cleared', 1);

        $this->assertSame(1, MenuItem::query()->where('id', $item->id)->count(), 'the row was deleted, not deactivated');
        $this->assertFalse((bool) $item->fresh()->is_active);
    }

    public function test_save_refuses_an_option_outside_this_merchants_own_vocabulary(): void
    {
        $business = $this->marketBusiness(272);

        $foreign = \Illuminate\Support\Facades\DB::table('options')
            ->whereNotIn('id', app(MerchantOfferingVocabulary::class)->pickableIds(
                (int) $business->id, (int) $business->category_child_id, (int) $business->category_id
            )['lines'])
            ->value('id') ?: $this->markTestSkipped('No option outside the vocabulary to test with.');

        $this->actingAs($business, 'sanctum')->postJson('/api/v2/business/menu/market-catalog', [
            'rows' => [$foreign => ['base_price' => 45]],
        ])->assertOk()->assertJsonPath('data.saved', 0);
    }
}
