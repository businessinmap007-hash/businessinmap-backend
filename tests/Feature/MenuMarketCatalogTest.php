<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\User;
use App\Services\MerchantOfferingVocabulary;
use App\Support\MarketCatalogChildren;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «تعبئة الرفوف» — تجار السلع الجاهزة (`menu_market`) يملأون منيوهم من مفردات
 * المنصّة الجاهزة بدل الكتابة اليدوية.
 *
 * «ناخد اسم مجموعة الخيارات ويكون هو القسم وتحته اقسام المجموعة نفسها …
 *  الكمية … سعر التوريد اختيارى وسعر البيع والوحدة … اسم الشركة المنتجة او
 *  الماركة اختيارى» — المالك، 2026-08-25. Started scoped to three ids; widened
 * 2026-08-26 to «تجار السلع الجاهزة فقط» — every `menu_market` child, not
 * every child — {@see \App\Support\MarketCatalogChildren}.
 *
 * A filled row is an ordinary MenuItem carrying its option as an offering
 * LINE, so the customer-facing arrangement (`MenuItem::heading()`,
 * `MenuOutline`) needs no change to place it under its group's name.
 */
class MenuMarketCatalogTest extends TestCase
{
    use DatabaseTransactions;

    private function marketBusiness(int $childId): User
    {
        return User::query()->where('type', 'business')->where('category_child_id', $childId)->orderBy('id')->first()
            ?: $this->markTestSkipped("No business stands on child #{$childId}.");
    }

    /** A row from the vocabulary — a plain DB row, not an Eloquent model. */
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

    public function test_the_screen_is_open_to_the_original_three_markets(): void
    {
        foreach ([149, 185, 272] as $childId) {
            $this->actingAs($this->marketBusiness($childId))
                ->get(route('business.menu.catalog.index'))
                ->assertOk();
        }
    }

    /**
     * The 2026-08-26 widening: any `menu_market` child, not just the three it
     * started with — a fishmonger (#101) is exactly the shape «تجار السلع
     * الجاهزة» was meant to include and never carried the original three ids.
     */
    public function test_the_screen_widened_to_every_ready_goods_child(): void
    {
        $this->assertNotContains(101, [149, 185, 272], 'pick a child outside the original three');

        $this->actingAs($this->marketBusiness(101))
            ->get(route('business.menu.catalog.index'))
            ->assertOk();
    }

    /**
     * A made-to-order trade — a restaurant plates a dish, it does not shelve
     * one — stays closed even though it carries `line` options of its own.
     */
    public function test_a_made_to_order_trade_is_refused(): void
    {
        $restaurant = User::query()
            ->where('type', 'business')->where('category_child_id', '>', 0)
            ->whereIn('id', function ($q) {
                $q->select('u.id')->from('users as u')
                    ->join('category_children_master as c', 'c.id', '=', 'u.category_child_id')
                    ->where('c.name_ar', 'مطعم');
            })
            ->orderBy('id')->first() ?: $this->markTestSkipped('Needs a restaurant business.');

        $this->assertFalse(MarketCatalogChildren::includes($restaurant), 'a restaurant was read as a goods catalog');

        $this->actingAs($restaurant)
            ->get(route('business.menu.catalog.index'))
            ->assertForbidden();
    }

    public function test_the_group_name_is_the_option_groups_own_name(): void
    {
        $business = $this->marketBusiness(272);
        $option = $this->firstLineOption($business);
        $groupName = (string) $option->group_name;

        $this->actingAs($business)
            ->get(route('business.menu.catalog.index'))
            ->assertOk()
            ->assertSee($groupName)
            ->assertSee((string) ($option->name_ar ?: $option->name_en));
    }

    public function test_filling_a_row_creates_an_ordinary_priced_item(): void
    {
        $business = $this->marketBusiness(272);
        $option = $this->firstLineOption($business);

        $this->actingAs($business)
            ->put(route('business.menu.catalog.update'), [
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
            ->assertRedirect();

        $item = MenuItem::query()->where('business_id', $business->id)->latest('id')->first();

        $this->assertNotNull($item, 'the filled row never became an item');
        $this->assertSame((string) ($option->name_ar ?: $option->name_en), $item->name_ar);
        $this->assertSame(45.0, (float) $item->base_price);
        $this->assertSame(30.0, (float) $item->supply_price);
        $this->assertSame('المراعي', $item->brand_name);
        $this->assertSame(40, (int) $item->available_quantity);
        $this->assertTrue((bool) $item->is_active);
        $this->assertSame((int) $option->id, $item->lineOption()?->id);
    }

    public function test_a_row_with_no_price_creates_nothing(): void
    {
        $business = $this->marketBusiness(272);
        $option = $this->firstLineOption($business);

        $before = MenuItem::query()->where('business_id', $business->id)->count();

        $this->actingAs($business)
            ->put(route('business.menu.catalog.update'), [
                'rows' => [$option->id => ['quantity' => 10]],
            ])
            ->assertRedirect();

        $this->assertSame($before, MenuItem::query()->where('business_id', $business->id)->count());
    }

    public function test_emptying_a_filled_row_deactivates_rather_than_deletes(): void
    {
        $business = $this->marketBusiness(272);
        $option = $this->firstLineOption($business);

        $this->actingAs($business)->put(route('business.menu.catalog.update'), [
            'rows' => [$option->id => ['base_price' => 45]],
        ]);

        $item = MenuItem::query()->where('business_id', $business->id)->latest('id')->first();
        $this->assertTrue((bool) $item->is_active);

        $this->actingAs($business)->put(route('business.menu.catalog.update'), [
            'rows' => [$option->id => ['base_price' => '']],
        ]);

        $this->assertSame(1, MenuItem::query()->where('id', $item->id)->count(), 'the row was deleted, not deactivated');
        $this->assertFalse((bool) $item->fresh()->is_active);
    }

    public function test_a_row_outside_this_merchants_own_vocabulary_is_refused(): void
    {
        $business = $this->marketBusiness(272);

        $foreign = DB::table('options')
            ->whereNotIn('id', app(MerchantOfferingVocabulary::class)->pickableIds(
                (int) $business->id, (int) $business->category_child_id, (int) $business->category_id
            )['lines'])
            ->value('id') ?: $this->markTestSkipped('No option outside the vocabulary to test with.');

        $this->actingAs($business)->put(route('business.menu.catalog.update'), [
            'rows' => [$foreign => ['base_price' => 45]],
        ]);

        $this->assertSame(
            0,
            MenuItem::query()->where('business_id', $business->id)
                ->whereHas('offeringOptions', fn ($q) => $q->where('option_id', $foreign))
                ->count()
        );
    }

    public function test_the_customer_facing_heading_is_the_groups_name(): void
    {
        $business = $this->marketBusiness(272);
        $option = $this->firstLineOption($business);
        $groupName = (string) $option->group_name;

        $this->actingAs($business)->put(route('business.menu.catalog.update'), [
            'rows' => [$option->id => ['base_price' => 45, 'brand_name' => 'المراعي']],
        ]);

        $this->get('/api/v2/discovery/menu/' . $business->id)
            ->assertOk()
            ->assertJsonFragment(['name' => $groupName])
            ->assertJsonFragment(['brand_name' => 'المراعي']);

        // The cost never reaches the customer endpoint.
        $this->get('/api/v2/discovery/menu/' . $business->id)
            ->assertDontSee('supply_price');
    }
}
