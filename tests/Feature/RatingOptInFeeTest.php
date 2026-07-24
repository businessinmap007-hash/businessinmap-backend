<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The fee model: transacting is free, and opening your rating is the per-party
 * opt-in that makes YOU (and only you) liable for service fees.
 *
 * A business or a client can list/buy with no fees to anyone. Fees begin only
 * when a party opens their own rating (POST /api/v2/ratings/enable), which
 * forces that party's fee consent via ServiceFeeConsentEnforcer — and leaves
 * the other party untouched, because fees are charged per user.
 */
class RatingOptInFeeTest extends TestCase
{
    use DatabaseTransactions;

    private function freshUser(string $type): User
    {
        $u = new User();
        $u->name = 'Rating OptIn ' . $type;
        $u->email = 'optin-' . uniqid() . '@example.test';
        $u->phone = '0100' . random_int(1000000, 9999999);
        $u->password = 'A-good-password1';
        $u->type = $type;
        $u->api_token = Str::random(80);
        $u->save();

        return $u->fresh();
    }

    public function test_a_fresh_party_owes_no_fees_and_has_a_closed_rating(): void
    {
        $client = $this->freshUser(User::TYPE_CLIENT);

        // No opt-in yet → no fee liability, rating closed.
        $this->assertFalse($client->hasRatingEnabled());
        $this->assertFalse($client->canBeChargedServiceFees());

        $this->actingAs($client, 'sanctum')->getJson('/api/v2/ratings/me')
            ->assertOk()
            ->assertJsonPath('data.rating_enabled', false)
            ->assertJsonPath('data.fee_auto_charge_enabled', false);
    }

    public function test_opening_rating_makes_that_party_fee_liable(): void
    {
        $business = $this->freshUser(User::TYPE_BUSINESS);

        $this->actingAs($business, 'sanctum')->postJson('/api/v2/ratings/enable')
            ->assertOk()
            ->assertJsonPath('data.rating_enabled', true)
            ->assertJsonPath('data.fee_auto_charge_enabled', true);

        $fresh = $business->fresh();
        $this->assertTrue($fresh->hasRatingEnabled());
        $this->assertTrue($fresh->canBeChargedServiceFees());
    }

    public function test_opt_in_is_per_party_and_does_not_touch_the_other_side(): void
    {
        $business = $this->freshUser(User::TYPE_BUSINESS);
        $client = $this->freshUser(User::TYPE_CLIENT);

        // The business opens its rating…
        $this->actingAs($business, 'sanctum')->postJson('/api/v2/ratings/enable')->assertOk();

        // …the client, who did nothing, still owes no fees and has a closed rating.
        $client = $client->fresh();
        $this->assertFalse($client->hasRatingEnabled());
        $this->assertFalse($client->canBeChargedServiceFees());
    }
}
