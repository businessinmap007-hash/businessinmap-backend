<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * «خلي نتيجة البحث تفتح صفحة المنتج نفسها» — المالك، 2026-10-02: one public
 * item, shaped like a row of the shop's menu, so a search result can open the
 * product page itself. Rolls back.
 */
class MenuItemPageTest extends TestCase
{
    use DatabaseTransactions;

    private function anItem(): MenuItem
    {
        return MenuItem::query()->where('is_active', true)->whereHas('business')->orderBy('id')->first()
            ?: $this->markTestSkipped('No active menu item to open.');
    }

    public function test_one_item_comes_back_shaped_like_a_menu_row_with_its_shop(): void
    {
        $item = $this->anItem();

        $res = $this->getJson('/api/v2/discovery/menu-items/'.$item->id)->assertOk();

        $this->assertSame($item->id, $res->json('data.item.id'));
        $this->assertSame('menu', $res->json('data.item.kind'));
        foreach (['name', 'base_price', 'images', 'variants', 'extras', 'specs'] as $key) {
            $this->assertArrayHasKey($key, $res->json('data.item'), "a menu row carries {$key}");
        }
        $this->assertSame((int) $item->business_id, $res->json('data.business.id'));
        $this->assertArrayHasKey('is_open_now', $res->json('data.business'));
    }

    public function test_an_item_that_is_switched_off_or_unknown_is_not_found(): void
    {
        $item = $this->anItem();
        $item->forceFill(['is_active' => false])->saveQuietly();

        $this->getJson('/api/v2/discovery/menu-items/'.$item->id)->assertNotFound();
        $this->getJson('/api/v2/discovery/menu-items/999999999')->assertNotFound();
    }
}
