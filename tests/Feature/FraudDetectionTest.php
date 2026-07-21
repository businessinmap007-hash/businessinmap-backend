<?php

namespace Tests\Feature;

use App\Models\FraudFlag;
use App\Models\User;
use App\Models\UserOperationRating;
use App\Services\FraudDetectionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Stage C: raising suspected-fraud flags from the rating graph.
 *
 * The rules that keep it honest: a clean account is never flagged, a high
 * dispute share is, a tiny sample is not (one dispute on one op is not a
 * pattern), the scan only suggests (it never fines or bans), and a flag a human
 * dismissed is never resurrected.
 */
class FraudDetectionTest extends TestCase
{
    use DatabaseTransactions;

    private FraudDetectionService $detector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->detector = app(FraudDetectionService::class);

        config([
            'bim.fraud.min_operations' => 5,
            'bim.fraud.disputed_ratio' => 0.30,
            'bim.fraud.cancelled_ratio' => 0.50,
        ]);
    }

    private function makeUser(): User
    {
        $u = new User();
        $u->name = 'Rated ' . uniqid();
        $u->email = 'fraud-' . uniqid() . '@example.test';
        $u->phone = '0100' . random_int(1000000, 9999999);
        $u->password = 'secret-password';
        $u->type = User::TYPE_CLIENT;
        $u->api_token = Str::random(80);
        $u->save();

        return $u;
    }

    private function rate(User $u, int $total, int $disputed, int $cancelled): void
    {
        UserOperationRating::create([
            'user_id' => $u->id, 'role' => UserOperationRating::ROLE_CLIENT,
            'total_operations' => $total, 'success_count' => max(0, $total - $disputed - $cancelled),
            'disputed_count' => $disputed, 'cancelled_count' => $cancelled,
        ]);
    }

    public function test_a_high_dispute_share_is_flagged(): void
    {
        $u = $this->makeUser();
        $this->rate($u, 10, 4, 0); // 40% disputed ≥ 30%

        $this->detector->scan();

        $flag = FraudFlag::where('user_id', $u->id)->first();
        $this->assertNotNull($flag);
        $this->assertSame(FraudFlag::STATUS_OPEN, $flag->status);
        $this->assertContains('disputed_ratio', $flag->reasons);
        $this->assertGreaterThan(0, (float) $flag->score);
    }

    public function test_a_clean_account_is_not_flagged(): void
    {
        $u = $this->makeUser();
        $this->rate($u, 20, 0, 1); // 0% disputed, 5% cancelled

        $this->detector->scan();

        $this->assertNull(FraudFlag::where('user_id', $u->id)->first());
    }

    public function test_a_tiny_sample_is_not_flagged(): void
    {
        $u = $this->makeUser();
        $this->rate($u, 2, 2, 0); // 100% disputed but only 2 operations

        $this->detector->scan();

        $this->assertNull(FraudFlag::where('user_id', $u->id)->first(), 'below the min-operations floor');
    }

    public function test_the_scan_never_fines_or_bans(): void
    {
        $u = $this->makeUser();
        $this->rate($u, 10, 8, 0);

        $this->detector->scan();

        $this->assertFalse($u->fresh()->isBanned(), 'detection only suggests');
        $this->assertDatabaseMissing('fines', ['user_id' => $u->id]);
    }

    public function test_a_dismissed_flag_is_not_resurrected(): void
    {
        $u = $this->makeUser();
        $this->rate($u, 10, 5, 0);
        $this->detector->scan();

        $flag = FraudFlag::where('user_id', $u->id)->firstOrFail();
        $admin = User::query()->where('type', 'admin')->value('id') ?? $u->id;
        $this->detector->dismiss($flag, (int) $admin);

        // Scanning again must leave the dismissed flag dismissed.
        $this->detector->scan();

        $this->assertSame(FraudFlag::STATUS_DISMISSED, $flag->fresh()->status);
    }

    public function test_ratios_are_summed_across_roles_to_the_account(): void
    {
        $u = $this->makeUser();
        // Two role rows for one account; together 6/12 disputed = 50%.
        $this->rate($u, 6, 3, 0);
        UserOperationRating::create([
            'user_id' => $u->id, 'role' => UserOperationRating::ROLE_BUSINESS,
            'total_operations' => 6, 'success_count' => 3, 'disputed_count' => 3, 'cancelled_count' => 0,
        ]);

        $this->detector->scan();

        $flag = FraudFlag::where('user_id', $u->id)->firstOrFail();
        $this->assertSame(12, $flag->total_operations);
        $this->assertEqualsWithDelta(0.50, (float) $flag->disputed_ratio, 0.001);
    }
}
