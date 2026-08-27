<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Retail\RetailCatalogNudgeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SeedsRetailCatalog;
use Tests\TestCase;

/**
 * RetailCatalogNudgeService: notify a retail-eligible business, once ever,
 * that the shared catalog has stock for them. See the service's own docblock
 * for why this exists — the 2026-08-28 zero-listings investigation.
 */
class RetailCatalogNudgeTest extends TestCase
{
    use DatabaseTransactions;
    use SeedsRetailCatalog;

    /** آثاث → home_furnishings/furniture, an existing retail-linked child. */
    private function retailBusiness(): User
    {
        $childId = (int) DB::table('category_children_master')->where('name_ar', 'آثاث')->value('id');
        $user = User::query()->where('type', 'business')->firstOrFail();

        DB::table('users')->where('id', $user->id)->update(['category_child_id' => $childId]);
        DB::table('business_catalog_listings')->where('business_id', $user->id)->delete();
        DB::table('app_notifications')
            ->where('user_id', $user->id)
            ->where('source_type', RetailCatalogNudgeService::EVENT_KEY)
            ->delete();

        return $user->refresh();
    }

    public function test_an_eligible_business_that_never_listed_is_notified(): void
    {
        $this->makeCatalogProduct('furniture', 'كنبة إشعار');
        $business = $this->retailBusiness();

        $notified = app(RetailCatalogNudgeService::class)->notifyIfEligible($business->id);

        $this->assertTrue($notified);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $business->id,
            'source_type' => RetailCatalogNudgeService::EVENT_KEY,
            'action_url' => '/business/products/create',
        ]);
    }

    public function test_a_business_with_an_existing_listing_is_not_notified(): void
    {
        $productId = $this->makeCatalogProduct('furniture', 'كنبة موجودة بالفعل');
        $business = $this->retailBusiness();

        DB::table('business_catalog_listings')->insert([
            'business_id' => $business->id,
            'catalog_product_id' => $productId,
            'price' => 100,
            'stock' => 1,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $notified = app(RetailCatalogNudgeService::class)->notifyIfEligible($business->id);

        $this->assertFalse($notified);
        $this->assertDatabaseMissing('app_notifications', [
            'user_id' => $business->id,
            'source_type' => RetailCatalogNudgeService::EVENT_KEY,
        ]);
    }

    public function test_an_already_notified_business_is_not_notified_again(): void
    {
        $this->makeCatalogProduct('furniture', 'كنبة تكرار');
        $business = $this->retailBusiness();

        $service = app(RetailCatalogNudgeService::class);

        $this->assertTrue($service->notifyIfEligible($business->id));
        $this->assertFalse($service->notifyIfEligible($business->id));

        $this->assertSame(1, DB::table('app_notifications')
            ->where('user_id', $business->id)
            ->where('source_type', RetailCatalogNudgeService::EVENT_KEY)
            ->count());
    }

    public function test_a_business_with_no_retail_link_is_not_notified(): void
    {
        $business = User::query()->where('type', 'business')->firstOrFail();
        DB::table('users')->where('id', $business->id)->update(['category_child_id' => null]);
        DB::table('business_catalog_listings')->where('business_id', $business->id)->delete();
        DB::table('app_notifications')
            ->where('user_id', $business->id)
            ->where('source_type', RetailCatalogNudgeService::EVENT_KEY)
            ->delete();

        $notified = app(RetailCatalogNudgeService::class)->notifyIfEligible($business->id);

        $this->assertFalse($notified);
    }

    public function test_run_finds_and_notifies_a_qualifying_candidate(): void
    {
        $this->makeCatalogProduct('furniture', 'كنبة سحب جماعي');
        $business = $this->retailBusiness();

        $result = app(RetailCatalogNudgeService::class)->run(2000);

        $this->assertGreaterThanOrEqual(1, $result['notified']);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $business->id,
            'source_type' => RetailCatalogNudgeService::EVENT_KEY,
        ]);
    }
}
