<?php

namespace Tests\Feature;

use App\Models\BusinessDepositPolicy;
use App\Models\PlatformService;
use App\Models\User;
use App\Services\BookingDepositCalculator;
use App\Services\BookingDepositPolicyResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * «شروط الحجز» — the merchant says how his bookings are secured in plain terms; the booking engine reads the policy row
 * it is written onto. Rolls back.
 */
class BusinessBookingTermsTest extends TestCase
{
    use DatabaseTransactions;

    private User $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = User::create([
            'name' => 'فندق الشروط',
            'email' => 'terms' . uniqid() . '@example.test',
            'password' => bcrypt('Test1234'),
            'type' => User::TYPE_BUSINESS,
            'category_id' => 24,
            'category_child_id' => 536,
            'api_token' => Str::random(60),
            'phone' => '010' . random_int(10000000, 99999999),
        ]);
    }

    private function save(array $over = [])
    {
        return $this->actingAs($this->business, 'sanctum')->putJson('/api/v2/business/booking-terms', array_merge([
            'enabled' => true,
            'security_mode' => 'deposit_freeze',
            'deposit_percent' => 25,
        ], $over));
    }

    public function test_a_business_with_no_terms_sees_the_defaults_and_nothing_is_asked_of_its_customers(): void
    {
        $this->actingAs($this->business, 'sanctum')->getJson('/api/v2/business/booking-terms')
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.security_mode', 'deposit_freeze')
            ->assertJsonPath('data.confirmation', 'merchant_approval')
            ->assertJsonPath('data.max_percent', 50);
    }

    public function test_the_preview_says_what_a_twenty_five_percent_deposit_on_an_800_booking_means(): void
    {
        $this->save(['deposit_percent' => 25, 'business_counter_percent' => 50])
            ->assertOk()
            ->assertJsonPath('data.example.booking_value', 800)
            ->assertJsonPath('data.example.deposit', 200)
            ->assertJsonPath('data.example.customer_hold', 200)
            ->assertJsonPath('data.example.business_hold', 100);
    }

    public function test_the_deposit_can_go_up_to_half_of_the_booking_and_no_further(): void
    {
        $this->save(['deposit_percent' => 50])->assertOk()->assertJsonPath('data.example.deposit', 400);
        $this->save(['deposit_percent' => 51])->assertStatus(422);
        $this->save(['deposit_percent' => 0])->assertStatus(422);
    }

    public function test_the_engine_reads_what_the_merchant_saved(): void
    {
        $this->save(['deposit_percent' => 30, 'deposit_base' => 'total'])->assertOk();

        $service = PlatformService::query()->where('key', 'booking')->firstOrFail();
        $policy = app(BookingDepositPolicyResolver::class)->resolve($this->business, $service);
        $out = app(BookingDepositCalculator::class)->calculate($policy, ['total_amount' => 2000, 'first_day_amount' => 500]);

        $this->assertTrue($policy['enabled']);
        $this->assertSame(600.0, (float) $out['amount'], '30% of the whole booking');
        $this->assertTrue($out['wallet_hold_required']);
    }

    public function test_switching_it_off_asks_nothing(): void
    {
        $this->save()->assertOk();
        $this->save(['enabled' => false])->assertOk()->assertJsonPath('data.enabled', false);

        $service = PlatformService::query()->where('key', 'booking')->firstOrFail();
        $this->assertFalse(app(BookingDepositPolicyResolver::class)->resolve($this->business, $service)['enabled']);
    }

    public function test_a_transfer_outside_the_app_is_confirmed_by_the_business_not_frozen(): void
    {
        $this->save(['security_mode' => 'external_transfer', 'deposit_percent' => 20])
            ->assertOk()
            ->assertJsonPath('data.security_mode', 'external_transfer')
            ->assertJsonPath('data.example.customer_hold', 0)
            ->assertJsonPath('data.example.business_hold', 0)
            ->assertJsonPath('data.example.external_amount', 160);
    }

    public function test_the_guarantee_can_be_asked_to_stand_for_a_multiple_of_the_days_value(): void
    {
        $this->save(['security_mode' => 'guarantee_freeze', 'deposit_percent' => 20, 'guarantee_multiple' => 3])
            ->assertOk()
            ->assertJsonPath('data.security_mode', 'guarantee_freeze')
            ->assertJsonPath('data.guarantee_multiple', 3)
            // 3 × 800 — «حتى لو أضعاف قيمة حجز اليوم»
            ->assertJsonPath('data.example.guarantee_required', 2400);

        // asking for a multiple means nothing outside guarantee mode
        $this->save(['security_mode' => 'deposit_freeze', 'guarantee_multiple' => 3])
            ->assertOk()->assertJsonPath('data.guarantee_multiple', 0);
    }

    public function test_the_business_can_declare_that_a_broken_booking_gives_it_the_whole_deposit(): void
    {
        $this->save(['forfeit_to_business' => true])->assertOk()->assertJsonPath('data.forfeit_to_business', true);

        $row = BusinessDepositPolicy::query()->where('business_id', $this->business->id)->firstOrFail();
        $this->assertTrue($row->forfeit_to_business);
    }

    public function test_saving_twice_edits_the_one_row_and_a_customer_cannot_reach_it(): void
    {
        $this->save()->assertOk();
        $this->save(['deposit_percent' => 40])->assertOk();

        $this->assertSame(1, BusinessDepositPolicy::query()->where('business_id', $this->business->id)->count());

        $customer = User::query()->where('type', '!=', User::TYPE_BUSINESS)->orderBy('id')->firstOrFail();
        $this->actingAs($customer, 'sanctum')->getJson('/api/v2/business/booking-terms')->assertForbidden();
    }
}
