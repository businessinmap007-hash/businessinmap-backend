<?php

namespace Tests\Feature;

use App\Models\PlatformService;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsMenu;
use Tests\TestCase;

/**
 * «منيو مطاعم» vs «منيو ماركت» — a second chip row under the still-single
 * «menu» service chip, filtering by the business's OWN (child, root) config
 * (category_service_configs.config->allowed_item_types) — the same source
 * BusinessPanelNav::menuKindsOf() reads. NOT menu_items.item_type: real
 * items are mostly NULL there (never backfilled), confirmed live on the
 * emulator — matching on it would hide almost every real menu business. See
 * [[three-catalog-shapes]]. Rolls back.
 */
class DiscoveryMenuKindFilterTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsMenu;

    /** A business scoped to a (child, root) that allows exactly $kind, with one active menu item. */
    private function menuBusiness(int $skip, int $childId, int $categoryId, string $kind, string $itemName): User
    {
        $business = User::query()->where('type', 'business')->orderBy('id')->skip($skip)->firstOrFail();
        $business->category_child_id = $childId;
        $business->category_id = $categoryId;
        $business->save();

        DB::table('category_service_configs')->updateOrInsert(
            ['category_id' => $categoryId, 'child_id' => $childId, 'platform_service_id' => (int) PlatformService::query()->where('key', 'menu')->value('id')],
            ['config' => json_encode(['allowed_item_types' => [$kind]]), 'is_active' => 1, 'sort_order' => 0, 'created_at' => now(), 'updated_at' => now()]
        );

        $this->seedMenuItem($business->id, null, 30.0, $itemName);

        return $business->fresh();
    }

    public function test_menu_kind_narrows_recommended_to_that_kind_only(): void
    {
        $menuServiceId = (int) PlatformService::query()->where('key', 'menu')->value('id');

        $restaurant = $this->menuBusiness(0, 51, 23, 'menu_food', 'برجر');
        $market = $this->menuBusiness(1, 52, 23, 'menu_market', 'أرز');

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
