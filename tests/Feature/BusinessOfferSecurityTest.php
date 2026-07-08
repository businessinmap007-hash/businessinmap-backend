<?php

namespace Tests\Feature;

use App\Models\CommercialOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
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

        return $b;
    }

    public function test_client_cannot_set_ranking_score_on_create(): void
    {
        $business = $this->business();
        Sanctum::actingAs($business);

        $res = $this->postJson('/api/v2/business/offers', [
            'offerable_type' => CommercialOffer::OFFERABLE_SERVICE,
            'offerable_id' => 0,
            'base_price' => 100,
            'final_price' => 90,
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

        $offer = CommercialOffer::create([
            'offerable_type' => CommercialOffer::OFFERABLE_SERVICE,
            'offerable_id' => 0,
            'owner_business_id' => $business->id,
            'seller_business_id' => $business->id,
            'source_type' => CommercialOffer::SOURCE_PROMOTION,
            'base_price' => 100,
            'final_price' => 90,
            'currency' => 'EGP',
            'status' => CommercialOffer::STATUS_ACTIVE,
            'ranking_score' => 0,
        ]);

        Sanctum::actingAs($business);

        $res = $this->putJson("/api/v2/business/offers/{$offer->id}", [
            'offerable_type' => CommercialOffer::OFFERABLE_SERVICE,
            'offerable_id' => 0,
            'base_price' => 100,
            'final_price' => 90,
            'ranking_score' => 999999,
        ]);

        if ($res->status() !== 200) {
            $this->markTestSkipped('Offer update gated: ' . $res->getContent());
        }

        $this->assertSame(0.0, (float) $offer->fresh()->ranking_score, 'ranking_score must stay system-owned');
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
