<?php

namespace Tests\Feature;

use App\Models\BusinessPartnership;
use App\Models\CommercialOffer;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Authz/integrity guards on the business offers API. ranking_score drives
 * public discovery ordering, so it must never be settable by the business
 * that owns the offer (else it self-boosts above competitors). Rows are
 * created inside a rolled-back transaction.
 */
class BusinessOfferSecurityTest extends TestCase
{
    use DatabaseTransactions;

    private function business(): User
    {
        $b = User::query()->where('type', 'business')->first();
        if (! $b) {
            $this->markTestSkipped('Needs a business user.');
        }

        $this->openTheOffersDoor((int) $b->id);

        return $b;
    }

    /**
     * `business_offers` is off platform-wide and the merchant must be
     * subscribed to it — both true of the live database, and neither of them
     * what this file is about.
     *
     * Three of these five tests skipped on it, which reads as «passing» in a
     * green run and means the ranking-score guard — the thing that stops a
     * business boosting itself above its competitors in discovery — had not
     * actually been exercised in months. Opened inside the transaction.
     */
    private function openTheOffersDoor(int $businessId): void
    {
        $serviceId = (int) DB::table('platform_services')->where('key', 'business_offers')->value('id');

        if ($serviceId <= 0) {
            $this->markTestSkipped('The business_offers service does not exist.');
        }

        DB::table('platform_services')->where('id', $serviceId)->update(['is_active' => 1]);

        DB::table('user_platform_service')->updateOrInsert(
            ['user_id' => $businessId, 'platform_service_id' => $serviceId],
            ['is_active' => 1, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    /**
     * A priced row to hang an offer on.
     *
     * `offerable_id => 0` used to be legal — the offer named nothing, and the
     * «previous price» was whatever the request said. It is refused now
     * (OfferEligibility), so these tests state an owner and a price the way a
     * real caller has to.
     */
    private function pricedRow(int $businessId, float $price = 100): MenuItem
    {
        return MenuItem::create([
            'business_id' => $businessId,
            'item_type' => 'menu_food',
            'name_ar' => 'صنف اختبار الأمان',
            'base_price' => $price,
            'is_active' => 1,
        ]);
    }

    public function test_client_cannot_set_ranking_score_on_create(): void
    {
        $business = $this->business();
        Sanctum::actingAs($business);

        $res = $this->postJson('/api/v2/business/offers', [
            'offerable_type' => CommercialOffer::OFFERABLE_MENU_ITEM,
            'offerable_id' => $this->pricedRow((int) $business->id)->id,
            'final_price' => 90,
            'ends_at' => now()->addWeek()->toDateTimeString(),
            'ranking_score' => 999999,
            'title_ar' => 'اختبار أمان',
        ]);

        if ($res->status() !== 201) {
            $this->markTestSkipped('Offer creation gated (subscription/validation): ' . $res->getContent());
        }

        $offerId = (int) $res->json('data.offer.id');
        $this->assertSame(
            0.0,
            (float) CommercialOffer::query()->whereKey($offerId)->value('ranking_score'),
            'client-supplied ranking_score must be ignored'
        );
    }

    public function test_owner_cannot_raise_ranking_score_via_update(): void
    {
        $business = $this->business();

        $row = $this->pricedRow((int) $business->id);

        $offer = CommercialOffer::create([
            'offerable_type' => CommercialOffer::OFFERABLE_MENU_ITEM,
            'offerable_id' => $row->id,
            'owner_business_id' => $business->id,
            'seller_business_id' => $business->id,
            'source_type' => CommercialOffer::SOURCE_PROMOTION,
            'base_price' => 100,
            'final_price' => 90,
            'currency' => 'EGP',
            'status' => CommercialOffer::STATUS_ACTIVE,
            'ends_at' => now()->addWeek(),
            'ranking_score' => 0,
        ]);

        Sanctum::actingAs($business);

        $res = $this->putJson("/api/v2/business/offers/{$offer->id}", [
            'offerable_type' => CommercialOffer::OFFERABLE_MENU_ITEM,
            'offerable_id' => $row->id,
            'final_price' => 90,
            'ranking_score' => 999999,
        ]);

        if ($res->status() !== 200) {
            $this->markTestSkipped('Offer update gated: ' . $res->getContent());
        }

        $this->assertSame(0.0, (float) $offer->fresh()->ranking_score, 'ranking_score must stay system-owned');
    }

    public function test_cannot_resell_another_owner_without_partnership(): void
    {
        $businesses = User::query()->where('type', 'business')->take(2)->pluck('id')->all();
        if (count($businesses) < 2) {
            $this->markTestSkipped('Needs two business users.');
        }
        [$ownerId, $sellerId] = $businesses;

        // No partnership exists (rolled back). The seller must be rejected.
        Sanctum::actingAs(User::find($sellerId));

        $this->postJson('/api/v2/business/offers', [
            'offerable_type' => CommercialOffer::OFFERABLE_MENU_ITEM,
            'offerable_id' => $this->pricedRow($ownerId)->id,
            'owner_business_id' => $ownerId,
            'final_price' => 90,
            'ends_at' => now()->addWeek()->toDateTimeString(),
        ])->assertStatus(422)->assertJsonValidationErrors(['owner_business_id']);
    }

    public function test_can_resell_with_active_partnership(): void
    {
        $businesses = User::query()->where('type', 'business')->take(2)->pluck('id')->all();
        if (count($businesses) < 2) {
            $this->markTestSkipped('Needs two business users.');
        }
        [$ownerId, $sellerId] = $businesses;

        BusinessPartnership::create([
            'owner_business_id' => $ownerId,
            'partner_business_id' => $sellerId,
            'relationship_type' => BusinessPartnership::TYPE_RESELLER,
            'status' => BusinessPartnership::STATUS_ACTIVE,
        ]);

        $this->openTheOffersDoor($sellerId);

        Sanctum::actingAs(User::find($sellerId));

        $res = $this->postJson('/api/v2/business/offers', [
            'offerable_type' => CommercialOffer::OFFERABLE_MENU_ITEM,
            // The OWNER's row. A reseller discounts what the owner sells, and
            // the resolver checks the row against the owner for exactly that
            // reason — the partnership is what lets him sell it, not what lets
            // him invent it.
            'offerable_id' => $this->pricedRow($ownerId)->id,
            'owner_business_id' => $ownerId,
            'final_price' => 90,
            'ends_at' => now()->addWeek()->toDateTimeString(),
        ]);

        // The partnership clears the ownership guard. Creation may still be
        // gated by subscription — but it must NOT fail on owner_business_id.
        if ($res->status() === 422) {
            $this->assertArrayNotHasKey('owner_business_id', $res->json('errors') ?? [], 'partnership must satisfy the ownership guard');
            $this->markTestSkipped('Creation gated downstream (not by ownership): ' . $res->getContent());
        }

        $res->assertCreated();
        $this->assertSame($ownerId, (int) $res->json('data.offer.owner_business_id'));
    }

    public function test_business_cannot_update_another_businesses_offer(): void
    {
        $businesses = User::query()->where('type', 'business')->take(2)->pluck('id')->all();
        if (count($businesses) < 2) {
            $this->markTestSkipped('Needs two business users.');
        }
        [$ownerId, $attackerId] = $businesses;

        $offer = CommercialOffer::create([
            'offerable_type' => CommercialOffer::OFFERABLE_SERVICE,
            'offerable_id' => 0,
            'owner_business_id' => $ownerId,
            'seller_business_id' => $ownerId,
            'source_type' => CommercialOffer::SOURCE_PROMOTION,
            'base_price' => 100,
            'final_price' => 90,
            'currency' => 'EGP',
            'status' => CommercialOffer::STATUS_ACTIVE,
        ]);

        Sanctum::actingAs(User::find($attackerId));

        // The attacker (a different business) must not be able to touch it.
        $this->putJson("/api/v2/business/offers/{$offer->id}", [
            'offerable_type' => CommercialOffer::OFFERABLE_SERVICE,
            'base_price' => 1,
            'final_price' => 1,
        ])->assertNotFound();

        $this->deleteJson("/api/v2/business/offers/{$offer->id}")->assertNotFound();
    }
}
