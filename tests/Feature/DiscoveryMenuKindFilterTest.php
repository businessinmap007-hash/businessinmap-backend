<?php

namespace Tests\Feature;

use App\Models\PlatformService;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\SeedsMenu;
use Tests\TestCase;

/**
 * «منيو مطاعم» vs «منيو ماركت» — a second chip row under the still-single
 * «menu» service chip, filtering by MenuItem::item_type. See
 * [[three-catalog-shapes]]: deliberately NOT a new platform_services row.
 * Rolls back.
 */
class DiscoveryMenuKindFilterTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsMenu;

    public function test_menu_kind_narrows_recommended_to_that_kind_only(): void
    {
        $menuServiceId = (int) PlatformService::query()->where('key', 'menu')->value('id');

        $restaurant = User::query()->where('type', 'business')->orderBy('id')->firstOrFail();
        $market = User::query()->where('type', 'business')->orderBy('id')->skip(1)->firstOrFail();

        $this->seedMenuItem($restaurant->id, null, 50.0, 'برجر')->update(['item_type' => 'menu_food']);
        $this->seedMenuItem($market->id, null, 20.0, 'أرز')->update(['item_type' => 'menu_market']);

        $foodOnly = collect($this->getJson('/api/v2/discovery/recommended?service_id=' . $menuServiceId . '&menu_kind=menu_food&per_page=50')
            ->assertOk()->json('data.businesses.data'))->pluck('id');
        $this->assertContains($restaurant->id, $foodOnly);
        $this->assertNotContains($market->id, $foodOnly);

        $marketOnly = collect($this->getJson('/api/v2/discovery/recommended?service_id=' . $menuServiceId . '&menu_kind=menu_market&per_page=50')
            ->assertOk()->json('data.businesses.data'))->pluck('id');
        $this->assertContains($market->id, $marketOnly);
        $this->assertNotContains($restaurant->id, $marketOnly);

        // No menu_kind: both kinds of menu business are still reachable.
        $all = collect($this->getJson('/api/v2/discovery/recommended?service_id=' . $menuServiceId . '&per_page=50')
            ->assertOk()->json('data.businesses.data'))->pluck('id');
        $this->assertContains($restaurant->id, $all);
        $this->assertContains($market->id, $all);
    }
}
