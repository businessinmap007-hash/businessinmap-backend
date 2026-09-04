<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\BusinessMenuSetting;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * «حتى يطلب من المورد كميات اضافية... التنبية قبل النفاذ الكمية مثلا ب 5 او
 * 10 وحدات» — المالك، 2026-09-04. The alert goes to the BUSINESS, never the
 * customer — available_quantity is merchant-set, not auto-decremented by an
 * order, so the only moment worth checking is a save that changes it.
 */
class LowStockAlertTest extends TestCase
{
    use DatabaseTransactions;

    private function business(): User
    {
        return User::query()->where('type', 'business')->orderBy('id')->firstOrFail();
    }

    private function latestAlert(int $businessId, string $eventKey): ?AppNotification
    {
        return AppNotification::query()
            ->where('user_id', $businessId)
            ->whereJsonContains('meta->event_key', $eventKey)
            ->latest('id')
            ->first();
    }

    public function test_reaching_zero_alerts_the_business_not_the_customer(): void
    {
        $business = $this->business();
        $item = MenuItem::create([
            'business_id' => $business->id, 'name_ar' => 'فراولة', 'base_price' => 20, 'available_quantity' => 5,
        ]);

        $item->update(['available_quantity' => 0]);

        $alert = $this->latestAlert($business->id, 'menu_item_out_of_stock');
        $this->assertNotNull($alert, 'no out-of-stock alert was created');
        $this->assertStringContainsString('فراولة', (string) $alert->body_ar);
    }

    public function test_a_row_created_already_at_zero_still_alerts(): void
    {
        $business = $this->business();
        MenuItem::create([
            'business_id' => $business->id, 'name_ar' => 'عنب', 'base_price' => 30, 'available_quantity' => 0,
        ]);

        $this->assertNotNull($this->latestAlert($business->id, 'menu_item_out_of_stock'));
    }

    public function test_reaching_zero_twice_in_a_row_alerts_only_once(): void
    {
        $business = $this->business();
        $item = MenuItem::create([
            'business_id' => $business->id, 'name_ar' => 'تفاح', 'base_price' => 15, 'available_quantity' => 3,
        ]);

        $item->update(['available_quantity' => 0]);
        $first = $this->latestAlert($business->id, 'menu_item_out_of_stock');

        $item->update(['base_price' => 16]); // unrelated save, quantity untouched
        $item->refresh();
        $item->update(['available_quantity' => 0]); // already 0 → no new alert

        $this->assertSame($first->id, $this->latestAlert($business->id, 'menu_item_out_of_stock')->id);
    }

    public function test_no_threshold_configured_means_no_low_stock_alert_before_zero(): void
    {
        $business = $this->business();
        BusinessMenuSetting::query()->where('business_id', $business->id)->delete();

        $item = MenuItem::create([
            'business_id' => $business->id, 'name_ar' => 'موز', 'base_price' => 25, 'available_quantity' => 20,
        ]);
        $item->update(['available_quantity' => 3]);

        $this->assertNull($this->latestAlert($business->id, 'menu_item_low_stock'));
    }

    public function test_crossing_the_configured_threshold_alerts_with_the_sale_unit(): void
    {
        $business = $this->business();
        BusinessMenuSetting::updateOrCreate(['business_id' => $business->id], ['low_stock_threshold' => 5]);

        $item = MenuItem::create([
            'business_id' => $business->id, 'name_ar' => 'جمبري', 'base_price' => 200,
            'available_quantity' => 20, 'sale_unit' => 'kg',
        ]);

        $item->update(['available_quantity' => 4]);

        $alert = $this->latestAlert($business->id, 'menu_item_low_stock');
        $this->assertNotNull($alert, 'no low-stock alert was created');
        $this->assertStringContainsString('جمبري', (string) $alert->body_ar);
    }

    public function test_staying_under_threshold_does_not_alert_again(): void
    {
        $business = $this->business();
        BusinessMenuSetting::updateOrCreate(['business_id' => $business->id], ['low_stock_threshold' => 10]);

        $item = MenuItem::create([
            'business_id' => $business->id, 'name_ar' => 'كابوريا', 'base_price' => 300, 'available_quantity' => 8,
        ]);
        $first = $this->latestAlert($business->id, 'menu_item_low_stock');
        $this->assertNotNull($first);

        $item->update(['available_quantity' => 6]); // still under the threshold, not a fresh crossing

        $this->assertSame($first->id, $this->latestAlert($business->id, 'menu_item_low_stock')->id);
    }

    public function test_a_quantity_above_the_threshold_does_not_alert(): void
    {
        $business = $this->business();
        BusinessMenuSetting::updateOrCreate(['business_id' => $business->id], ['low_stock_threshold' => 5]);

        $item = MenuItem::create([
            'business_id' => $business->id, 'name_ar' => 'خيار', 'base_price' => 10, 'available_quantity' => 50,
        ]);
        $item->update(['available_quantity' => 30]);

        $this->assertNull($this->latestAlert($business->id, 'menu_item_low_stock'));
    }

    public function test_a_save_that_does_not_touch_quantity_never_alerts(): void
    {
        $business = $this->business();
        $item = MenuItem::create([
            'business_id' => $business->id, 'name_ar' => 'بطاطس', 'base_price' => 10, 'available_quantity' => 0,
        ]);
        AppNotification::query()->where('user_id', $business->id)->delete();

        $item->update(['base_price' => 12]);

        $this->assertNull($this->latestAlert($business->id, 'menu_item_out_of_stock'));
    }

    public function test_the_api_saves_the_threshold(): void
    {
        $business = User::query()->where('type', 'business')->where('category_child_id', 272)->first()
            ?: $this->markTestSkipped('No supermarket business to test with.');

        $this->actingAs($business, 'sanctum')
            ->putJson('/api/v2/business/menu/market-catalog/low-stock-threshold', ['low_stock_threshold' => 8])
            ->assertOk()
            ->assertJsonPath('data.low_stock_threshold', 8);

        $this->assertSame(8, BusinessMenuSetting::query()->where('business_id', $business->id)->value('low_stock_threshold'));

        $this->getJson('/api/v2/business/menu/market-catalog')
            ->assertOk()
            ->assertJsonPath('data.low_stock_threshold', 8);
    }
}
