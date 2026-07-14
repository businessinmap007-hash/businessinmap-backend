<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\CommercialOffer;
use App\Models\OfferFollow;
use App\Models\OfferFollowNotification;
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
        $this->actingAs($this->user, 'sanctum')->getJson('/api/v2/offer-follows')->assertOk();
    }

    public function test_offer_follow_match_lands_in_the_unified_center(): void
    {
        $business = User::query()->where('type', 'business')->orderBy('id')->first()
            ?: $this->markTestSkipped('Needs a business user.');

        $offer = CommercialOffer::query()->first() ?: CommercialOffer::create([
            'offerable_type' => CommercialOffer::OFFERABLE_PRODUCT,
            'offerable_id' => 1,
            'owner_business_id' => $business->id,
            'seller_business_id' => $business->id,
            'source_type' => CommercialOffer::SOURCE_DIRECT,
            'audience_type' => CommercialOffer::AUDIENCE_BOTH,
            'title_ar' => 'عرض اختبار الدمج',
            'title_en' => 'Merge test offer',
            'base_price' => 100,
            'final_price' => 90,
            'currency' => 'EGP',
            'availability_mode' => CommercialOffer::AVAILABILITY_INSTANT,
            'status' => CommercialOffer::STATUS_ACTIVE,
        ]);

        $follow = OfferFollow::create([
            'user_id' => $this->user->id,
            'followable_type' => OfferFollow::FOLLOW_KEYWORD,
            'followable_id' => 0,
            'keyword' => 'consolidation-probe',
            'is_active' => 1,
        ]);

        $ofn = OfferFollowNotification::create([
            'user_id' => $this->user->id,
            'follow_id' => $follow->id,
            'offer_id' => $offer->id,
            'match_type' => OfferFollow::FOLLOW_KEYWORD,
            'match_score' => 0.5,
            'status' => OfferFollowNotification::STATUS_UNREAD,
            'meta' => ['source' => 'test'],
        ]);

        // The bridge that unifies offer matches into app_notifications.
        $appNotif = app(InAppNotificationService::class)->createFromOfferFollowNotification($ofn);

        $this->assertNotNull($appNotif);
        $this->assertSame(AppNotification::TYPE_OFFER, $appNotif->type);
        $this->assertDatabaseHas('app_notifications', [
            'id' => $appNotif->id,
            'user_id' => $this->user->id,
            'source_type' => 'offer_follow_notification',
            'source_id' => $ofn->id,
        ]);

        // And it is visible through the single notification center.
        $ids = collect(
            $this->actingAs($this->user, 'sanctum')->getJson('/api/v2/notifications')
                ->assertOk()->json('data.notifications.data')
        )->pluck('id')->all();
        $this->assertContains($appNotif->id, $ids);
    }
}
