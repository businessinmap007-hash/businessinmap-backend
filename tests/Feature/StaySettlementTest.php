<?php

namespace Tests\Feature;

use App\Models\BookableItem;
use App\Models\Booking;
use App\Models\BusinessServicePrice;
use App\Models\GuaranteeLevel;
use App\Models\PlatformService;
use App\Models\User;
use App\Models\UserGuarantee;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «التسوية» of a non-refundable stay (owner, 2026-10-28): both sides' «عربون الجدية» frozen on acceptance — guarantee first — never
 * released by a timer; «تمت التسوية» from BOTH sides, or a payment the guest proved and the hotel confirmed, or a judge after 3 days.
 */
class StaySettlementTest extends TestCase
{
    use DatabaseTransactions;

    private const ROOT = 24;
    private const CHILD = 536;
    private const SINGLE_ROOM = 965;

    private User $hotel;
    private User $guest;
    private BookableItem $unit;
    private int $serviceId;

    protected function setUp(): void
    {
        parent::setUp();

        $make = fn (string $type, array $extra = []) => User::create([
            'name' => 'Zz Settle ' . uniqid(), 'email' => 'zz-settle-' . uniqid() . '@test.local', 'phone' => '01' . random_int(100000000, 999999999),
            'password' => Hash::make('Passw0rdTest'), 'type' => $type, 'api_token' => 'zz' . uniqid() . bin2hex(random_bytes(8)),
        ] + $extra);

        $this->hotel = $make(User::TYPE_BUSINESS, ['category_id' => self::ROOT, 'category_child_id' => self::CHILD]);
        $this->guest = $make(User::TYPE_CLIENT);
        $this->serviceId = (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->value('id');

        BusinessServicePrice::create([
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'child_id' => self::CHILD,
            'bookable_item_type' => 'booking_stay', 'line_option_id' => self::SINGLE_ROOM, 'price' => 1000, 'currency' => 'EGP', 'is_active' => 1,
        ]);
        $this->unit = BookableItem::create([
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'item_type' => 'booking_stay',
            'line_option_id' => self::SINGLE_ROOM, 'code' => 'S-' . uniqid(), 'quantity' => 5, 'capacity' => 2, 'is_active' => 1,
        ]);
        $this->unit->syncOfferingOptions(self::SINGLE_ROOM, []);

        // the non-refundable rate at 10% off, its «عربون الجدية» 20%
        Sanctum::actingAs($this->hotel);
        $this->putJson('/api/v2/business/booking-settings/rate-plans', ['non_refundable_percent' => 10, 'deposit_percent' => 20])
            ->assertOk()->assertJsonPath('data.deposit_percent', 20);
    }

    private function wallet(User $u, float $balance): void
    {
        app(WalletService::class)->getOrCreateWallet($u->id)->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => $balance, 'locked_balance' => 0]);
    }

    private function locked(User $u): float
    {
        return (float) Wallet::query()->where('user_id', $u->id)->value('locked_balance');
    }

    /** two nights at 1000, non-refundable: 1800, its deposit 360 */
    private function book(int $fromDay = 40): int
    {
        Sanctum::actingAs($this->guest);
        $start = now()->addDays($fromDay)->startOfDay();

        return (int) $this->postJson('/api/v2/bookings', [
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'bookable_id' => $this->unit->id, 'quantity' => 1,
            'starts_at' => $start->toDateTimeString(), 'ends_at' => $start->copy()->addDays(2)->toDateTimeString(), 'party_size' => 2,
            'rate_plan' => 'non_refundable',
        ])->assertCreated()->json('data.booking.id');
    }

    private function accept(int $id)
    {
        Sanctum::actingAs($this->hotel);

        return $this->postJson("/api/v2/bookings/{$id}/accept");
    }

    public function test_accepting_freezes_both_sides_and_a_cancellation_keeps_it_until_both_say_settled(): void
    {
        // a guest whose guarantee and wallet cannot stand the deposit does not book the rate
        $this->wallet($this->guest, 100);
        Sanctum::actingAs($this->guest);
        $start = now()->addDays(40)->startOfDay();
        $this->postJson('/api/v2/bookings', [
            'business_id' => $this->hotel->id, 'service_id' => $this->serviceId, 'bookable_id' => $this->unit->id, 'quantity' => 1,
            'starts_at' => $start->toDateTimeString(), 'ends_at' => $start->copy()->addDays(2)->toDateTimeString(), 'party_size' => 2, 'rate_plan' => 'non_refundable',
        ])->assertStatus(422)->assertJsonValidationErrors(['rate_plan']);

        $this->wallet($this->guest, 1000);
        $id = $this->book();

        // the preview told him: 20% of 1800 = 360
        $this->assertEquals(0, $this->locked($this->guest));

        // a hotel that cannot stand its counter deposit cannot accept — the booking stays pending
        $this->wallet($this->hotel, 50);
        $this->accept($id)->assertStatus(422)->assertJsonValidationErrors(['deposit']);
        $this->assertSame(Booking::STATUS_PENDING, Booking::find($id)->status);

        $this->wallet($this->hotel, 1000);
        $this->accept($id)->assertOk();
        $this->assertEquals(360, $this->locked($this->guest));
        $this->assertEquals(360, $this->locked($this->hotel));

        // the guest cancels: nothing unwinds — both are asked to settle
        Sanctum::actingAs($this->guest);
        $this->postJson("/api/v2/bookings/{$id}/cancel")->assertOk();
        $this->assertEquals(360, $this->locked($this->guest));
        $this->getJson('/api/v2/me/stay-settlements')->assertOk()->assertJsonPath('data.count', 1)->assertJsonPath('data.settlements.0.status', 'open');
        Sanctum::actingAs($this->hotel);
        $this->getJson('/api/v2/me/stay-settlements')->assertOk()->assertJsonPath('data.count', 1)->assertJsonPath('data.settlements.0.cancelled_by', 'client');

        // one side's «تمت التسوية» is not enough, and it can be withdrawn
        Sanctum::actingAs($this->guest);
        $this->postJson("/api/v2/bookings/{$id}/stay-settlement/confirm")->assertOk()->assertJsonPath('data.settlement.status', 'open');
        $this->deleteJson("/api/v2/bookings/{$id}/stay-settlement/confirm")->assertOk()->assertJsonPath('data.settlement.client_confirmed_at', null);
        $this->postJson("/api/v2/bookings/{$id}/stay-settlement/confirm")->assertOk();
        $this->assertEquals(360, $this->locked($this->guest));

        // the second side's confirmation unwinds everything to whoever posted it
        Sanctum::actingAs($this->hotel);
        $this->postJson("/api/v2/bookings/{$id}/stay-settlement/confirm")->assertOk()->assertJsonPath('data.settlement.status', 'settled');
        $this->assertEquals(0, $this->locked($this->guest));
        $this->assertEquals(0, $this->locked($this->hotel));
        $this->getJson('/api/v2/me/stay-settlements')->assertOk()->assertJsonPath('data.count', 0);

        // a stranger learns nothing
        Sanctum::actingAs(User::query()->where('type', User::TYPE_CLIENT)->whereKeyNot($this->guest->id)->first());
        $this->getJson("/api/v2/bookings/{$id}/stay-settlement")->assertNotFound();
    }

    public function test_a_payment_the_guest_proves_and_the_hotel_confirms_settles_it(): void
    {
        $this->wallet($this->guest, 1000);
        $this->wallet($this->hotel, 1000);
        $id = $this->book();
        $this->accept($id)->assertOk();
        Sanctum::actingAs($this->hotel);
        $this->postJson("/api/v2/bookings/{$id}/cancel")->assertOk(); // the hotel cancels: the same settlement

        Sanctum::actingAs($this->guest);
        $body = ['amount' => 1800, 'method' => 'transfer', 'reference' => 'TR-77', 'proof' => UploadedFile::fake()->createWithContent('receipt.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='))];
        // «الدفع خارج التطبيق على مسؤوليتك الشخصية»: no acknowledgement, no record; no proof, no record
        $this->post("/api/v2/bookings/{$id}/stay-settlement/payments", $body, ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors(['acknowledge']);
        $this->post("/api/v2/bookings/{$id}/stay-settlement/payments", ['acknowledge' => 1] + array_diff_key($body, ['proof' => 1]), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors(['proof']);

        $res = $this->post("/api/v2/bookings/{$id}/stay-settlement/payments", $body + ['acknowledge' => 1], ['Accept' => 'application/json'])->assertCreated();
        $payment = $res->json('data.settlement.payments.0');
        $this->assertSame('recorded', $payment['status']);
        $this->assertStringContainsString('stay-payment-proofs', $payment['proof_url']);

        // the payer cannot confirm his own payment; the hotel says it arrived
        $this->postJson("/api/v2/bookings/{$id}/stay-settlement/payments/{$payment['id']}/answer", ['received' => true])->assertStatus(422);
        Sanctum::actingAs($this->hotel);
        $this->postJson("/api/v2/bookings/{$id}/stay-settlement/payments/{$payment['id']}/answer", ['received' => true])
            ->assertOk()->assertJsonPath('data.settlement.status', 'settled');
        $this->assertEquals(0, $this->locked($this->guest));
        $this->assertEquals(0, $this->locked($this->hotel));

        // the proof is served to whoever holds the signed link, and only through it
        $this->get($payment['proof_url'])->assertOk();
        $this->get(route('stay-payment-proofs.show', ['payment' => $payment['id']]))->assertForbidden();
    }

    public function test_no_settlement_after_three_days_lets_one_side_ask_a_judge(): void
    {
        $this->wallet($this->guest, 1000);
        $this->wallet($this->hotel, 1000);
        $id = $this->book();
        $this->accept($id)->assertOk();
        Sanctum::actingAs($this->guest);
        $this->postJson("/api/v2/bookings/{$id}/cancel")->assertOk();

        // not before three days
        Sanctum::actingAs($this->hotel);
        $this->postJson("/api/v2/bookings/{$id}/stay-settlement/arbitration")->assertStatus(422)->assertJsonValidationErrors(['arbitration']);
        $this->getJson("/api/v2/bookings/{$id}/stay-settlement")->assertOk()->assertJsonPath('data.settlement.can_request_arbitration', false);

        // nothing ever unwinds by itself
        $this->travel(30)->days();
        $this->assertEquals(360, $this->locked($this->guest));

        $res = $this->postJson("/api/v2/bookings/{$id}/stay-settlement/arbitration")->assertOk();
        $this->assertNotNull($res->json('data.dispute_id'));
        $this->assertSame('arbitration', $res->json('data.settlement.status'));
        $this->assertSame('under_review', DB::table('disputes')->where('id', $res->json('data.dispute_id'))->value('status'));
    }

    public function test_when_the_guest_cancelled_the_hotel_collects_the_deposit_from_the_app_wallet(): void
    {
        $this->wallet($this->guest, 1000);
        $this->wallet($this->hotel, 1000);
        $id = $this->book();
        $this->accept($id)->assertOk();

        // not while the stay stands
        Sanctum::actingAs($this->hotel);
        $this->postJson("/api/v2/bookings/{$id}/stay-settlement/collect")->assertStatus(422);

        Sanctum::actingAs($this->guest);
        $this->postJson("/api/v2/bookings/{$id}/cancel")->assertOk();

        // the guest cannot collect from himself; the hotel may — a fine up to the held deposit
        $this->postJson("/api/v2/bookings/{$id}/stay-settlement/collect")->assertStatus(422);
        Sanctum::actingAs($this->hotel);
        $this->getJson("/api/v2/bookings/{$id}/stay-settlement")->assertOk()
            ->assertJsonPath('data.settlement.can_collect', true)->assertJsonPath('data.settlement.collectable', 360);
        $this->postJson("/api/v2/bookings/{$id}/stay-settlement/collect", ['amount' => 500])->assertStatus(422)->assertJsonValidationErrors(['amount']);

        // a fine of 200: the guest keeps 160 of his hold, the hotel gets its 360 back plus 200
        $this->postJson("/api/v2/bookings/{$id}/stay-settlement/collect", ['amount' => 200])->assertOk()
            ->assertJsonPath('data.settlement.status', 'settled')->assertJsonPath('data.settlement.collected', 200);
        $this->assertEquals(0, $this->locked($this->guest));
        $this->assertEquals(0, $this->locked($this->hotel));
        $this->assertEquals(1000 - 200, (float) Wallet::query()->where('user_id', $this->guest->id)->value('balance'));
        $this->assertEquals(1000 + 200, (float) Wallet::query()->where('user_id', $this->hotel->id)->value('balance'));
    }

    public function test_the_hotel_cannot_collect_when_it_cancelled_itself(): void
    {
        $this->wallet($this->guest, 1000);
        $this->wallet($this->hotel, 1000);
        $id = $this->book();
        $this->accept($id)->assertOk();
        Sanctum::actingAs($this->hotel);
        $this->postJson("/api/v2/bookings/{$id}/cancel")->assertOk();
        $this->postJson("/api/v2/bookings/{$id}/stay-settlement/collect")->assertStatus(422)->assertJsonValidationErrors(['settlement']);
    }

    public function test_the_guest_paid_and_checked_in_so_everything_is_released(): void
    {
        $this->wallet($this->guest, 1000);
        $this->wallet($this->hotel, 1000);
        $id = $this->book();
        $this->accept($id)->assertOk();
        $this->assertEquals(360, $this->locked($this->guest));

        // the hotel received the prepayment, both sides confirmed attendance, the stay starts
        Sanctum::actingAs($this->hotel);
        $this->postJson("/api/v2/business/bookings/{$id}/deposit/confirm-received", ['method' => 'cash'])->assertOk();
        $booking = Booking::find($id);
        $meta = (array) $booking->meta;
        $meta['_start_confirm'] = ['client' => true, 'business' => true];
        $booking->forceFill(['meta' => $meta])->save();
        app(\App\Services\ServiceExecutionEngine::class)->moveBookingToInProgress($booking->fresh());

        $this->assertSame(Booking::STATUS_IN_PROGRESS, Booking::find($id)->status);
        $this->assertEquals(0, $this->locked($this->guest));
        $this->assertEquals(0, $this->locked($this->hotel));
        $this->getJson("/api/v2/bookings/{$id}/stay-settlement")->assertOk()
            ->assertJsonPath('data.settlement.status', 'settled')->assertJsonPath('data.settlement.settled_by', 'stay_started');
    }

    public function test_a_guarantee_stands_instead_of_the_wallet(): void
    {
        $levelId = (int) DB::table('guarantee_levels')->value('id');

        if ($levelId <= 0) {
            $this->markTestSkipped('Needs a guarantee level.');
        }

        foreach ([[$this->guest, GuaranteeLevel::TARGET_CLIENT], [$this->hotel, GuaranteeLevel::TARGET_BUSINESS]] as [$u, $target]) {
            $u->forceFill(['guarantee_enabled' => 1])->save();
            UserGuarantee::create([
                'user_id' => $u->id, 'target_type' => $target, 'purchased_level_id' => $levelId, 'effective_level_id' => $levelId,
                'status' => UserGuarantee::STATUS_ACTIVE, 'current_coverage_amount' => 5000, 'used_coverage_amount' => 0,
            ]);
        }

        $this->wallet($this->guest, 0);
        $this->wallet($this->hotel, 0);
        $id = $this->book();
        $this->accept($id)->assertOk();

        // both stood with their guarantee: nothing locked in either wallet
        $this->assertEquals(0, $this->locked($this->guest));
        $this->assertEquals(0, $this->locked($this->hotel));
        Sanctum::actingAs($this->guest);
        $this->getJson("/api/v2/bookings/{$id}/stay-settlement")->assertOk()
            ->assertJsonPath('data.settlement.client_hold.guarantee', 360)
            ->assertJsonPath('data.settlement.business_hold.guarantee', 360);
    }
}
