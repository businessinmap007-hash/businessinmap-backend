<?php

namespace Tests\Feature;

use App\Models\GuaranteeLevel;
use App\Models\User;
use App\Models\UserGuarantee;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «أيقونة بجوار اسم الحساب تعبّر عن مستوى الضمان … ويغطي حتى مبلغ …» — المالك، 2026-10-20.
 * Rolls back.
 */
class GuaranteeBadgeTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $type): User
    {
        $u = new User();
        $u->forceFill([
            'name' => 'U ' . Str::random(4), 'type' => $type, 'email' => 'badge-' . uniqid() . '@example.test',
            'phone' => '010' . random_int(10000000, 99999999), 'password' => 'secret-password', 'api_token' => Str::random(60),
        ])->save();

        return $u;
    }

    private function guarantee(User $user, string $target, string $levelCode, float $coverage, string $status = UserGuarantee::STATUS_ACTIVE): UserGuarantee
    {
        $level = GuaranteeLevel::query()->where('target_type', $target)->where('code', $levelCode)->firstOrFail();

        return UserGuarantee::create([
            'user_id' => $user->id,
            'target_type' => $target,
            'purchased_level_id' => $level->id,
            'effective_level_id' => $level->id,
            'status' => $status,
            'current_coverage_amount' => $coverage,
            'used_coverage_amount' => 0,
        ]);
    }

    public function test_an_account_wears_the_level_and_what_it_covers_up_to(): void
    {
        $client = $this->user(User::TYPE_CLIENT);
        $business = $this->user(User::TYPE_BUSINESS);
        $this->guarantee($client, GuaranteeLevel::TARGET_CLIENT, 'silver', 1500);
        $this->guarantee($business, GuaranteeLevel::TARGET_BUSINESS, 'gold', 9000);

        Sanctum::actingAs($this->user(User::TYPE_CLIENT));
        $badges = $this->getJson("/api/v2/guarantee-badges?ids={$client->id},{$business->id}")
            ->assertOk()->json('data.badges');

        $this->assertSame('silver', $badges[$client->id]['code']);
        $this->assertSame('فضي', $badges[$client->id]['name_ar']);
        $this->assertSame('Silver', $badges[$client->id]['name_en']);
        $this->assertEqualsWithDelta(1500.0, $badges[$client->id]['covers_up_to'], 0.001);
        $this->assertSame('client', $badges[$client->id]['target_type']);

        $this->assertSame('gold', $badges[$business->id]['code']);
        $this->assertSame('business', $badges[$business->id]['target_type']);
        $this->assertEqualsWithDelta(9000.0, $badges[$business->id]['covers_up_to'], 0.001);
    }

    public function test_no_guarantee_or_one_that_is_gone_wears_no_badge(): void
    {
        $none = $this->user(User::TYPE_CLIENT);
        $gone = $this->user(User::TYPE_CLIENT);
        $this->guarantee($gone, GuaranteeLevel::TARGET_CLIENT, 'gold', 3000, UserGuarantee::STATUS_CANCELLED);

        Sanctum::actingAs($this->user(User::TYPE_CLIENT));
        $badges = $this->getJson("/api/v2/guarantee-badges?ids={$none->id},{$gone->id}")->assertOk()->json('data.badges');

        $this->assertNull($badges[$none->id]);
        $this->assertNull($badges[$gone->id]);
    }

    public function test_a_business_shows_its_business_guarantee_not_a_client_one(): void
    {
        $business = $this->user(User::TYPE_BUSINESS);
        $this->guarantee($business, GuaranteeLevel::TARGET_CLIENT, 'diamond', 15000);
        $this->guarantee($business, GuaranteeLevel::TARGET_BUSINESS, 'bronze', 1500);

        Sanctum::actingAs($this->user(User::TYPE_CLIENT));
        $badge = $this->getJson("/api/v2/guarantee-badges?ids={$business->id}")->assertOk()->json("data.badges.{$business->id}");

        $this->assertSame('bronze', $badge['code']);
    }

    public function test_it_needs_a_signed_in_account_and_ignores_junk_ids(): void
    {
        $this->getJson('/api/v2/guarantee-badges?ids=1')->assertUnauthorized();

        Sanctum::actingAs($this->user(User::TYPE_CLIENT));
        $this->getJson('/api/v2/guarantee-badges?ids=abc,,-4,0')->assertOk();
        $this->getJson('/api/v2/guarantee-badges')->assertOk();
    }
}
