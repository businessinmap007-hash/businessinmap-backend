<?php

namespace Tests\Feature;

use App\Models\PlatformService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The services hub: every platform service CONFIGURED as available for a
 * category child (the app's "services" tab), even one no business prices yet.
 */
class DiscoveryServicesHubTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_lists_the_services_configured_for_a_child(): void
    {
        $service = PlatformService::query()->where('is_active', 1)->first();
        if (! $service) {
            $this->markTestSkipped('Needs an active platform service.');
        }

        // A fresh child id, wired to the service via the availability catalog.
        $childId = (int) (DB::table('category_platform_services')->max('child_id') ?? 0) + 5000;
        DB::table('category_platform_services')->insert([
            'category_id' => 1,
            'child_id' => $childId,
            'platform_service_id' => $service->id,
            'is_active' => 1,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $keys = collect(
            $this->getJson('/api/v2/discovery/services?child_id=' . $childId)
                ->assertOk()
                ->json('data.services')
        )->pluck('key')->all();

        $this->assertContains((string) $service->key, $keys);
    }

    public function test_child_id_is_required(): void
    {
        $this->getJson('/api/v2/discovery/services')
            ->assertStatus(422)
            ->assertJsonValidationErrors('child_id');
    }

    public function test_a_child_with_no_services_returns_an_empty_list(): void
    {
        $this->getJson('/api/v2/discovery/services?child_id=99999999')
            ->assertOk()
            ->assertJsonPath('data.services', []);
    }
}
