<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «كمّل على العصائر والمشروبات» — المالك، 2026-10-04. Rolls back.
 */
class JuicesAndDrinksMenuShapesTest extends TestCase
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

    public function test_a_juice_bar_prices_by_cup_size_and_describes_by_sugar_and_serving(): void
    {
        $vocab = $this->vocabulary(158);

        $this->assertSame('عصائر ومشروبات طازجة', collect($vocab['lines'])->firstWhere('group_name', 'أصناف العصائر والمشروبات')['detail_profile']['name']);
        $this->assertSame(['حجم الكوب'], array_column($vocab['price_axes'], 'group_name'), 'a juice is one drink in three prices — and makes nothing to order');
        $modifiers = collect($vocab['modifiers'])->pluck('group_name')->all();
        $this->assertContains('درجة السكر', $modifiers);
        $this->assertContains('طريقة التقديم', $modifiers);
        $this->assertNotContains('نظام التصنيع', $modifiers);
        $this->assertNotContains('حجم الكوب', array_column($vocab['lines'], 'group_name'));
    }

    public function test_the_coffee_roaster_keeps_its_roast_axis_and_gains_a_kind_and_origin(): void
    {
        $vocab = $this->vocabulary(63);

        $this->assertSame('شاي وقهوة', collect($vocab['lines'])->firstWhere('group_name', 'أنواع الشاي والقهوة')['detail_profile']['name']);
        $this->assertContains('درجة التحميص والطحن', array_column($vocab['price_axes'], 'group_name'));
        $this->assertContains('أصل البن', collect($vocab['modifiers'])->pluck('group_name')->all());
    }

    public function test_bottled_drinks_have_a_kind_and_a_market_is_not_asked_about_cups(): void
    {
        $vocab = $this->vocabulary(272);

        $this->assertSame('مشروبات معبأة', collect($vocab['lines'])->firstWhere('group_name', 'أنواع المشروبات المعبأة')['detail_profile']['name']);
        $this->assertNotContains('حجم الكوب', array_column($vocab['price_axes'], 'group_name'));
        $this->assertNotContains('درجة السكر', collect($vocab['modifiers'])->pluck('group_name')->all());
    }
}
