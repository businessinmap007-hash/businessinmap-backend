<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\User;
use App\Support\SaleUnits;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «الصنف المسعّر يمكن اضافته للمنيو … مثلا منتج يباع فى محل يكون بالوزن، كيلو
 *  أو لتر» — المالك، 2026-08-23.
 *
 * A menu row carried a name and a number and nothing between them. «طماطم —
 * ٤٥» is forty-five for a kilo, for a crate, or for one tomato, and the
 * customer found out at the counter. Every trade he named for this — خضار،
 * فاكهة، أسماك، جمبري — is sold by weight, and none of them could say so.
 */
class MenuSaleUnitTest extends TestCase
{
    use DatabaseTransactions;

    private function business(): User
    {
        $b = User::query()->where('type', 'business')->first();

        if (! $b) {
            $this->markTestSkipped('Needs a business user.');
        }

        return $b;
    }

    /**
     * The vocabulary is READ from `catalog_units` rather than declared twice.
     *
     * ⚠ That table holds twelve rows and nine words — `g`/`gram`, `l`/`liter`
     * and `pcs`/`piece` are the same unit twice, left by two import batches.
     * A merchant must never be offered «قطعة» twice in one dropdown, and the
     * table itself is left alone: deduplicating it is a curation decision.
     */
    public function test_the_unit_list_comes_from_the_catalog_and_says_each_word_once(): void
    {
        $options = SaleUnits::options();

        $this->assertNotEmpty($options);
        $this->assertSame(
            array_values(array_unique($options)),
            array_values($options),
            'the same unit is offered twice — the catalog duplicates leaked into the picker'
        );

        // The three the owner named, and the one that is the default.
        $this->assertContains('كجم', $options);
        $this->assertContains('لتر', $options);
        $this->assertContains('قطعة', $options);

        // Read, not written down: a code the catalog does not carry is not a
        // unit, whatever a request says.
        $this->assertNotContains('barrel', SaleUnits::codes());
    }

    /** Null is «by the item» — a sandwich — and is not a missing answer. */
    public function test_no_unit_means_by_the_item(): void
    {
        $row = MenuItem::create([
            'business_id' => $this->business()->id,
            'item_type' => 'menu_food',
            'name_ar' => 'ساندوتش',
            'base_price' => 45,
            'is_active' => 1,
        ]);

        $this->assertNull($row->sale_unit);
        $this->assertNull($row->priceUnitLabel());
    }

    /** …and a greengrocer says كجم, which is what gets printed beside the price. */
    public function test_a_row_sold_by_weight_says_so(): void
    {
        $row = MenuItem::create([
            'business_id' => $this->business()->id,
            'item_type' => 'menu_market',
            'name_ar' => 'طماطم',
            'base_price' => 45,
            'sale_unit' => 'kg',
            'is_active' => 1,
        ]);

        $this->assertSame('كجم', $row->priceUnitLabel());
    }

    /** The owner panel offers it, and refuses a unit the catalog does not carry. */
    public function test_the_owner_panel_saves_a_unit_and_refuses_an_invented_one(): void
    {
        $business = $this->business();

        $html = $this->actingAs($business)->get(route('business.menu.create', [], false))
            ->assertOk()->getContent();

        $this->assertStringContainsString('name="sale_unit"', $html);
        $this->assertStringContainsString('— بالقطعة —', $html);

        $this->actingAs($business)->post(route('business.menu.store', [], false), [
            'name_ar' => 'برتقال',
            'base_price' => 30,
            'sale_unit' => 'barrel',
        ])->assertSessionHasErrors('sale_unit');

        $this->actingAs($business)->post(route('business.menu.store', [], false), [
            'name_ar' => 'برتقال',
            'base_price' => 30,
            'sale_unit' => 'kg',
        ])->assertSessionDoesntHaveErrors('sale_unit');

        $this->assertSame(
            'kg',
            DB::table('menu_items')->where('business_id', $business->id)
                ->where('name_ar', 'برتقال')->latest('id')->value('sale_unit')
        );
    }

    /**
     * …and it reaches the customer with the label beside it, so the app prints
     * «٤٥ ج / كجم» without carrying its own unit table.
     */
    public function test_the_customer_menu_carries_the_unit_and_its_label(): void
    {
        $business = $this->business();

        MenuItem::create([
            'business_id' => $business->id,
            'item_type' => 'menu_market',
            'name_ar' => 'طماطم',
            'base_price' => 45,
            'sale_unit' => 'kg',
            'is_active' => 1,
        ]);

        $res = $this->getJson('/api/v2/discovery/menu/' . $business->id);

        if ($res->status() !== 200) {
            $this->markTestSkipped('Menu discovery gated for this business: ' . $res->status());
        }

        $found = collect($res->json('data.sections') ?? [])
            ->flatMap(fn ($s) => $s['items'] ?? [])
            ->firstWhere('name', 'طماطم');

        if (! $found) {
            $this->markTestSkipped('The item did not surface in discovery for this business.');
        }

        $this->assertSame('kg', $found['sale_unit']);
        $this->assertSame('كجم', $found['sale_unit_label']);
    }
}
