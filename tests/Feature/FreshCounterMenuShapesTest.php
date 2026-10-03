<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «كمّل على اللحوم والدواجن والألبان» — المالك، 2026-10-04: the fresh counters take the shape the fish
 * menu has. Rolls back.
 */
class FreshCounterMenuShapesTest extends TestCase
{
    use DatabaseTransactions;

    /** The vocabulary a merchant standing on [$child] sees, with a clean answer sheet. */
    private function vocabulary(int $child): array
    {
        $shop = User::query()->where('type', 'business')->where('category_child_id', $child)->orderBy('id')->first()
            // The butcher trade has no account yet: borrow one for the request (rolled back).
            ?? tap(User::query()->where('type', 'business')->orderBy('id')->firstOrFail(), fn ($u) => $u->forceFill(['category_child_id' => $child])->save());
        DB::table('option_user')->where('user_id', $shop->id)->delete();

        Sanctum::actingAs($shop);

        return $this->withHeaders(['Accept-Language' => 'ar'])->getJson('/api/v2/business/menu/vocabulary')->assertOk()->json('data');
    }

    private function kind(array $vocab, string $group): array
    {
        return collect($vocab['lines'])->firstWhere('group_name', $group)['detail_profile'];
    }

    public function test_the_butcher_describes_by_condition_and_prices_by_preparation(): void
    {
        $vocab = $this->vocabulary(553);

        $this->assertSame('لحوم', $this->kind($vocab, 'أنواع اللحوم')['name']);
        $this->assertContains('حالة اللحم', collect($vocab['modifiers'])->pluck('group_name')->all());
        $axes = array_column($vocab['price_axes'], 'group_name');
        $this->assertSame('تجهيز اللحم', $axes[0], 'the preparation leads');
        $this->assertContains('وحدة البيع', $axes, 'the butcher\'s own unit axis stays');
        $this->assertNotContains('تجهيز اللحم', array_column($vocab['lines'], 'group_name'), 'a preparation is not «what is it»');
    }

    public function test_poultry_keeps_its_condition_axis_and_gains_a_kind_and_how_it_was_raised(): void
    {
        $vocab = $this->vocabulary(229);

        $this->assertSame('دواجن وبيض', $this->kind($vocab, 'أنواع الدواجن والطيور')['name']);
        $this->assertContains('حالة الدواجن', array_column($vocab['price_axes'], 'group_name'));
        $this->assertContains('طريقة التربية', collect($vocab['modifiers'])->pluck('group_name')->all());
    }

    public function test_dairy_has_a_kind_with_fat_percentage_even_without_a_dairy_shop(): void
    {
        $this->assertSame([], DB::table('users')->where('type', 'business')->whereIn('category_child_id', DB::table('category_children_master')->where('name_ar', 'like', '%ألبان%')->pluck('id'))->pluck('id')->all(), 'no dairy trade exists — the kind travels with the group');

        $vocab = $this->vocabulary(185); // a mini market carries the list
        $fields = collect($this->kind($vocab, 'أنواع الألبان والأجبان')['fields'])->keyBy('code');

        $this->assertTrue($fields['fat_percent']['per_item']);
        $this->assertTrue($fields['weight']['show_on_card']);
    }

    public function test_a_market_is_not_asked_about_a_butchers_condition_or_cut(): void
    {
        $vocab = $this->vocabulary(272);

        $this->assertNotContains('حالة اللحم', collect($vocab['modifiers'])->pluck('group_name')->all());
        $this->assertNotContains('تجهيز اللحم', array_column($vocab['price_axes'], 'group_name'));
    }
}
