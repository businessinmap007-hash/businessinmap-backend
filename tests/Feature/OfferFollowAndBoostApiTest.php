<?php

namespace Tests\Feature;

use App\Models\OfferFollow;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Gap #1 coverage: offer-follow CRUD (per-user scoped) and the offer-boost
 * business-only guard. Boost money mechanics live in OfferBoostService; here we
 * assert the HTTP surface — validation, ownership scoping, the `business`
 * middleware. Rolls back.
 */
class OfferFollowAndBoostApiTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;
    private User $other;
    private User $business;
    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::query()->orderBy('id')->firstOrFail();
        $this->other = User::query()->where('id', '!=', $this->user->id)->orderBy('id')->firstOrFail();
        $this->business = User::query()->where('type', 'business')->orderBy('id')->firstOrFail();
        $this->client = User::query()->where('type', 'client')->orderBy('id')->first()
            ?: User::query()->where('type', '!=', 'business')->orderBy('id')->firstOrFail();
    }

    // ---- offer-follows ---------------------------------------------------

    public function test_store_keyword_follow_creates_a_row(): void
    {
        $keyword = 'follow-probe-' . random_int(10000, 99999);

        $followId = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v2/offer-follows', [
                'followable_type' => OfferFollow::FOLLOW_KEYWORD,
                'keyword' => $keyword,
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->json('data.follow.id');

        $this->assertDatabaseHas('offer_follows', [
            'id' => $followId,
            'user_id' => $this->user->id,
            'keyword' => $keyword,
            'is_active' => 1,
        ]);
    }

    public function test_keyword_follow_requires_a_keyword(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v2/offer-follows', [
                'followable_type' => OfferFollow::FOLLOW_KEYWORD,
            ])
            ->assertStatus(422);
    }

    public function test_invalid_followable_type_is_rejected(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v2/offer-follows', [
                'followable_type' => 'not_a_real_type',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['followable_type']);
    }

    public function test_index_lists_only_own_follows(): void
    {
        $mine = OfferFollow::create([
            'user_id' => $this->user->id,
            'followable_type' => OfferFollow::FOLLOW_KEYWORD,
            'followable_id' => 0,
            'keyword' => 'mine-' . random_int(1000, 9999),
            'is_active' => 1,
        ]);
        $foreign = OfferFollow::create([
            'user_id' => $this->other->id,
            'followable_type' => OfferFollow::FOLLOW_KEYWORD,
            'followable_id' => 0,
            'keyword' => 'theirs-' . random_int(1000, 9999),
            'is_active' => 1,
        ]);

        $ids = collect(
            $this->actingAs($this->user, 'sanctum')
                ->getJson('/api/v2/offer-follows?per_page=100')
                ->assertOk()
                ->json('data.follows.data')
        )->pluck('id')->all();

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_can_delete_own_follow(): void
    {
        $follow = OfferFollow::create([
            'user_id' => $this->user->id,
            'followable_type' => OfferFollow::FOLLOW_KEYWORD,
            'followable_id' => 0,
            'keyword' => 'del-' . random_int(1000, 9999),
            'is_active' => 1,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v2/offer-follows/{$follow->id}")
            ->assertOk();

        $this->assertDatabaseMissing('offer_follows', ['id' => $follow->id]);
    }

    public function test_cannot_delete_foreign_follow(): void
    {
        $foreign = OfferFollow::create([
            'user_id' => $this->other->id,
            'followable_type' => OfferFollow::FOLLOW_KEYWORD,
            'followable_id' => 0,
            'keyword' => 'foreign-del-' . random_int(1000, 9999),
            'is_active' => 1,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v2/offer-follows/{$foreign->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('offer_follows', ['id' => $foreign->id]);
    }

    public function test_offer_follows_require_authentication(): void
    {
        $this->getJson('/api/v2/offer-follows')->assertUnauthorized();
        $this->postJson('/api/v2/offer-follows', [])->assertUnauthorized();
    }

    // ---- offer boost (business-only) -------------------------------------

    public function test_business_can_list_boost_packages(): void
    {
        $this->actingAs($this->business, 'sanctum')
            ->getJson('/api/v2/business/offers/boost/packages')
            ->assertOk()
            ->assertJsonStructure(['data' => ['packages']]);
    }

    public function test_client_is_blocked_from_business_boost_routes(): void
    {
        $this->actingAs($this->client, 'sanctum')
            ->getJson('/api/v2/business/offers/boost/packages')
            ->assertForbidden();
    }

    public function test_boost_activate_requires_package_id(): void
    {
        $this->actingAs($this->business, 'sanctum')
            ->postJson('/api/v2/business/offers/1/boost', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['package_id']);
    }
}
