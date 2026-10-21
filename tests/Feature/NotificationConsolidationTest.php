<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\User;
use App\Services\Notifications\InAppNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * #4 duplicate-system consolidation: the dead singular push route and the
 * separate offer-notification inbox are gone; offer-follow matches surface in
 * the single /notifications center. Rolls back.
 */
class NotificationConsolidationTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::query()->orderBy('id')->firstOrFail();
    }

    public function test_removed_duplicate_routes_are_gone(): void
    {
        $this->actingAs($this->user, 'sanctum')->getJson('/api/v2/offer-notifications')->assertNotFound();
        $this->actingAs($this->user, 'sanctum')->getJson('/api/v2/offer-follows/notifications')->assertNotFound();
        $this->actingAs($this->user, 'sanctum')->postJson('/api/v2/push-token', ['token' => 'x'])->assertNotFound();
    }

    public function test_unified_surfaces_still_respond(): void
    {
        $this->actingAs($this->user, 'sanctum')->getJson('/api/v2/notifications')->assertOk();
    }
}
