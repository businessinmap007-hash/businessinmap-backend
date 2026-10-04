<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SaleUnits;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase two of re-ordering the option groups — المالك، 2026-10-04: a handful of detail types decide a
 * product's units and whether payment plans exist; «وحدة البيع» no longer lists specification units
 * (حصان، بوصة، وات). Rolls back.
 */
class DetailTypesAndSaleUnitsTest extends TestCase
{
    use DatabaseTransactions;

    private function vocabulary(int $child): array
    {
        $shop = User::query()->where('type', 'business')->where('category_child_id', $child)->orderBy('id')->first()
            ?? tap(User::query()->where('type', 'business')->orderBy('id')->firstOrFail(), fn ($u) => $u->forceFill(['category_child_id' => $child])->save());
        DB::table('option_user')->where('user_id', $shop->id)->delete();
        Sanctum::actingAs($shop);

        return $this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/business/menu/vocabulary')->assertOk()->json('data');
    }

    public function test_a_sale_unit_is_never_a_unit_of_a_specification(): void
    {
        $codes = SaleUnits::codes();

        foreach (['hp', 'inch', 'w', 'gb', 'mp', 'mah', 'cc', 'burner', 'drawer', 'place_setting', 'cu_ft', 'km'] as $spec) {
            $this->assertNotContains($spec, $codes, "«{$spec}» describes a product, it is not how one is sold");
        }
        foreach (['kg', 'g', 'l', 'pcs', 'pack', 'box', 'm', 'm2', 'm3', 'set', 'ton'] as $sale) {
            $this->assertContains($sale, $codes);
        }
    }

    public function test_the_endpoint_a_greengrocer_reads_offers_only_sale_units(): void
    {
        Sanctum::actingAs(User::query()->where('type', 'business')->orderBy('id')->firstOrFail());

        $offered = collect($this->getJson('/api/v2/business/menu/sale-units')->assertOk()->json('data.units'))->pluck('code')->all();

        $this->assertNotContains('hp', $offered);
        $this->assertNotContains('inch', $offered);
        $this->assertContains('kg', $offered);
    }

    public function test_each_type_narrows_the_units_of_its_groups(): void
    {
        $line = fn (array $vocab, string $group) => collect($vocab['lines'])->firstWhere('group_name', $group);

        $greengrocer = $this->vocabulary(272); // a supermarket carries fruit, fish and bakery
        $this->assertEqualsCanonicalizing(['kg', 'g', 'bunch'], $line($greengrocer, 'الفواكه')['sale_unit_codes']);
        $this->assertContains('l', $line($greengrocer, 'أنواع الألبان والأجبان')['sale_unit_codes']);
        $this->assertNotContains('bunch', $line($greengrocer, 'أنواع الألبان والأجبان')['sale_unit_codes']);

        $stone = $this->vocabulary(174);
        $this->assertEqualsCanonicalizing(['m', 'm2', 'm3', 'pcs', 'kg', 'ton', 'bag', 'thousand'], $line($stone, 'أنواع الرخام والجرانيت')['sale_unit_codes']);
    }

    public function test_only_big_ticket_types_have_payment_plans(): void
    {
        $plans = DB::table('menu_detail_types')->pluck('allows_payment_plans', 'code')->map(fn ($v) => (bool) $v)->all();

        foreach (['tech', 'vehicles', 'furniture'] as $type) {
            $this->assertTrue($plans[$type], "{$type} sells on instalments");
        }
        foreach (['fresh_produce', 'weighed', 'meal', 'pharmacy', 'piece_goods', 'measured', 'basic'] as $type) {
            $this->assertFalse($plans[$type], "{$type} is paid for on the spot");
        }

        $fish = collect($this->vocabulary(101)['lines'])->firstWhere('group_name', 'أنواع الأسماك والمأكولات البحرية');
        $this->assertFalse($fish['detail_type']['allows_payment_plans'], 'no instalments on food');
        $mobile = collect($this->vocabulary(186)['lines'])->firstWhere('group_name', 'أجهزة الموبايل');
        $this->assertTrue($mobile['detail_type']['allows_payment_plans']);
    }

    public function test_every_menu_line_group_a_trade_sells_has_a_type(): void
    {
        $untyped = DB::table('option_groups as g')
            ->join('service_option_group_placements as p', 'p.option_group_id', '=', 'g.id')
            ->where('p.usage', 'section')->where('p.is_active', 1)->where('g.price_role', 'line')->whereNull('g.detail_type')
            ->distinct()->pluck('g.name_ar')->all();

        $this->assertSame([], $untyped, 'a group without a type offers every unit and decides nothing about payment plans');
    }
}
