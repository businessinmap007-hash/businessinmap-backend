<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsRetailCatalog;
use Tests\TestCase;

/**
 * «كل منتج ممكن يكون له 5 اسعار: جديد - مستعمل - كسر زيرو - كاش - قسط، كل
 * واحد منهم سطر بيانات كامل. من الفلتر اختار كاش / جديد يظهر كل من حالتهم
 * كاش / جديد، وان لم اختار يظهر كل واحد فى سطر بسعره ووصفه» — المالك،
 * 2026-09-24. Rolls back.
 */
class RetailPriceVariantsTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRetailCatalog;

    private int $new;
    private int $used;
    private int $cash;
    private int $installment;

    private function option(string $group, string $name): int
    {
        return (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', $group)->where('o.name_ar', $name)->value('o.id');
    }

    /** A furniture-child business that carries جديد/مستعمل and كاش/تقسيط. */
    private function owner(): User
    {
        $childId = (int) DB::table('category_children_master')->where('name_ar', 'آثاث')->value('id');
        $user = User::query()->where('type', 'business')->orderBy('id')->first();

        if ($childId <= 0 || ! $user) {
            $this->markTestSkipped('Needs the «آثاث» child and a business user.');
        }

        $this->new = $this->option('حالة المنتج', 'جديد');
        $this->used = $this->option('حالة المنتج', 'مستعمل');
        $this->cash = $this->option('الدفع والسداد', 'كاش');
        $this->installment = $this->option('الدفع والسداد', 'تقسيط');

        foreach ([$this->new, $this->used, $this->cash, $this->installment] as $optionId) {
            DB::table('category_child_option')->updateOrInsert(
                ['child_id' => $childId, 'category_id' => 0, 'option_id' => $optionId],
                ['reorder' => 0]
            );
        }

        $user->category_child_id = $childId;
        $user->is_suspend = 0;
        Auth::setUser($user);

        return $user;
    }

    private function listVariant(int $product, array $extra): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v2/business/retail-listings', ['catalog_product_id' => $product] + $extra);
    }

    public function test_a_product_holds_one_full_row_per_condition_and_payment(): void
    {
        $owner = $this->owner();
        $product = $this->makeCatalogProduct('furniture', 'ثلاجة متغيرة');
        Sanctum::actingAs($owner);

        $this->listVariant($product, ['price' => 5000, 'condition_option_id' => $this->new, 'payment_option_id' => $this->cash, 'description_ar' => 'جديدة كاش'])
            ->assertCreated()->assertJsonPath('data.condition.id', $this->new)->assertJsonPath('data.description', 'جديدة كاش');
        $this->listVariant($product, ['price' => 5500, 'condition_option_id' => $this->new, 'payment_option_id' => $this->installment])->assertCreated();
        $this->listVariant($product, ['price' => 3000, 'condition_option_id' => $this->used, 'payment_option_id' => $this->cash])->assertCreated();

        $this->assertSame(3, DB::table('business_catalog_listings')
            ->where('business_id', $owner->id)->where('catalog_product_id', $product)->count());

        // The same combination twice is still refused.
        $this->listVariant($product, ['price' => 1, 'condition_option_id' => $this->new, 'payment_option_id' => $this->cash])->assertStatus(422);

        // An option the child does not carry (or from another group) is refused.
        $this->listVariant($product, ['price' => 1, 'condition_option_id' => $this->cash])->assertStatus(422)
            ->assertJsonValidationErrors('condition_option_id');
    }

    public function test_the_filter_narrows_rows_and_no_filter_shows_every_row_with_its_price(): void
    {
        $owner = $this->owner();
        $product = $this->makeCatalogProduct('furniture', 'ثلاجة فلتر');
        Sanctum::actingAs($owner);

        $this->listVariant($product, ['price' => 5000, 'condition_option_id' => $this->new, 'payment_option_id' => $this->cash])->assertCreated();
        $this->listVariant($product, ['price' => 5500, 'condition_option_id' => $this->new, 'payment_option_id' => $this->installment])->assertCreated();
        $this->listVariant($product, ['price' => 3000, 'condition_option_id' => $this->used, 'payment_option_id' => $this->cash, 'description_ar' => 'مستعملة بحالة جيدة'])->assertCreated();

        $mine = fn (array $q) => collect($this->getJson('/api/v2/discovery/retail/business/' . $owner->id . '?' . http_build_query($q))
            ->assertOk()->json('data.listings'))->where('product.id', $product);

        // Nothing chosen: every row, each with its own price and description.
        $all = $mine([]);
        $this->assertCount(3, $all);
        $this->assertEqualsCanonicalizing([5000, 5500, 3000], $all->pluck('price')->map(fn ($p) => (int) $p)->all());
        $this->assertSame('مستعملة بحالة جيدة', $all->firstWhere('price', 3000)['description']);

        // جديد chosen: both new rows. جديد + كاش: just the one.
        $this->assertCount(2, $mine(['condition_option_ids' => [$this->new]]));
        $onlyOne = $mine(['condition_option_ids' => [$this->new], 'payment_option_ids' => [$this->cash]]);
        $this->assertCount(1, $onlyOne);
        $this->assertSame(5000, (int) $onlyOne->first()['price']);

        // The public facet lists only options that live rows actually carry.
        $facets = $this->getJson('/api/v2/discovery/retail/filters')->assertOk()->json('data');
        $this->assertContains($this->new, array_column($facets['conditions'], 'id'));
        $this->assertContains($this->cash, array_column($facets['payments'], 'id'));
    }
}
