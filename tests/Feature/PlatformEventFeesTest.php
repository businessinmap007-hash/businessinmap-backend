<?php

namespace Tests\Feature;

use App\Models\PlatformEventCharge;
use App\Models\PlatformEventFee;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Fees\PlatformEventFeeService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The general event-fee core: priced per event and party from the control panel, taken only when every party who owes
 * something has the balance, and never twice for the same occurrence.
 */
class PlatformEventFeesTest extends TestCase
{
    use DatabaseTransactions;

    private function user(float $balance): User
    {
        $u = new User();
        $u->name = 'Event Fee Test';
        $u->email = 'pef-' . uniqid() . '@example.test';
        $u->phone = '0100' . random_int(1000000, 9999999);
        $u->password = 'secret-password';
        $u->type = User::TYPE_CLIENT;
        $u->api_token = Str::random(80);
        $u->save();

        app(WalletService::class)->getOrCreateWallet((int) $u->id)->update(['status' => Wallet::STATUS_ACTIVE, 'balance' => $balance, 'locked_balance' => 0]);

        return $u->fresh();
    }

    private function price(string $event, string $role, string $type, float $amount, bool $enabled = true, ?float $min = null, ?float $max = null): void
    {
        PlatformEventFee::query()->updateOrCreate(
            ['event_key' => $event, 'role' => $role],
            ['enabled' => $enabled, 'calc_type' => $type, 'amount' => $amount, 'min_amount' => $min, 'max_amount' => $max]
        );
    }

    private function balance(User $u): float
    {
        return (float) app(WalletService::class)->getOrCreateWallet($u->id)->balance;
    }

    public function test_each_party_is_priced_on_its_own_and_a_disabled_fee_is_free(): void
    {
        $e = PlatformEventFeeService::TRAINING_PROGRAM_CHANGE;
        $this->price($e, 'trainer', 'fixed', 15);
        $this->price($e, 'client', 'fixed', 40, enabled: false);

        $q = app(PlatformEventFeeService::class)->quote($e, ['trainer' => 1, 'client' => 2]);

        $this->assertEquals(15, $q['lines']['trainer']['amount']);
        $this->assertEquals(0, $q['lines']['client']['amount']);
        $this->assertEquals(15, $q['total']);
    }

    public function test_a_percent_is_kept_between_its_floor_and_its_ceiling(): void
    {
        $e = PlatformEventFeeService::OWNER_LISTING_CONTACT;
        $this->price($e, 'buyer', 'percent', 1.0, min: 20, max: 300);
        $svc = app(PlatformEventFeeService::class);

        $this->assertEquals(20, $svc->quote($e, ['buyer' => 1], 1000)['total'], '1% of 1000 is 10: the floor holds');
        $this->assertEquals(200, $svc->quote($e, ['buyer' => 1], 20000)['total']);
        $this->assertEquals(300, $svc->quote($e, ['buyer' => 1], 900000)['total'], 'the ceiling holds');
    }

    public function test_nothing_is_taken_unless_every_party_can_pay_and_the_other_sides_balance_is_not_named(): void
    {
        $e = PlatformEventFeeService::NUTRITION_PROGRAM_CHANGE;
        $this->price($e, 'trainer', 'fixed', 30);
        $this->price($e, 'client', 'fixed', 30);
        $trainer = $this->user(100);
        $client = $this->user(5);
        $svc = app(PlatformEventFeeService::class);
        $parties = ['trainer' => $trainer->id, 'client' => $client->id];

        try {
            $svc->charge($e, $parties, 0, 'training_plan', 1);
            $this->fail('the client cannot pay');
        } catch (ValidationException $ex) {
            $this->assertStringNotContainsString('30', $ex->errors()['balance'][0], 'the trainer is not told the client is short by how much');
        }

        $this->assertEquals(100, $this->balance($trainer), 'the trainer paid nothing');
        $this->assertSame(0, PlatformEventCharge::query()->where('reference_type', 'training_plan')->where('reference_id', 1)->count());

        // acting as the short side names the shortfall to that person
        try {
            $svc->assertCanPay($svc->quote($e, $parties), (int) $client->id);
            $this->fail('short');
        } catch (ValidationException $ex) {
            $this->assertStringContainsString('30', $ex->errors()['balance'][0]);
        }

        // a request that only needs the asker's own share ignores the other side
        $svc->assertCanPay($svc->quote($e, $parties), (int) $trainer->id, onlyActing: true);
    }

    public function test_a_charge_takes_every_share_once_and_a_repeat_takes_nothing_more(): void
    {
        $e = PlatformEventFeeService::TRAINING_PROGRAM_CHANGE;
        $this->price($e, 'trainer', 'fixed', 10);
        $this->price($e, 'client', 'fixed', 25);
        $trainer = $this->user(100);
        $client = $this->user(100);
        $svc = app(PlatformEventFeeService::class);
        $parties = ['trainer' => $trainer->id, 'client' => $client->id];

        $this->assertCount(2, $svc->charge($e, $parties, 0, 'training_plan', 77, '1'));
        $this->assertEquals(90, $this->balance($trainer));
        $this->assertEquals(75, $this->balance($client));

        $this->assertCount(0, $svc->charge($e, $parties, 0, 'training_plan', 77, '1'));
        $this->assertEquals(90, $this->balance($trainer));

        // a later occurrence is a new fee
        $this->assertCount(2, $svc->charge($e, $parties, 0, 'training_plan', 77, '2'));
        $this->assertEquals(80, $this->balance($trainer));
    }

    public function test_the_control_panel_page_prices_each_party_of_an_event(): void
    {
        $admin = User::query()->where('type', 'admin')->orderBy('id')->first() ?: $this->markTestSkipped('Needs an admin user.');
        $e = PlatformEventFeeService::OWNER_LISTING_CONTACT;

        $this->actingAs($admin)->get('/admin/event-fees')->assertOk()->assertSee('buyer', false)->assertSee('fees[' . $e . '][seller][amount]', false);

        $this->actingAs($admin)->put('/admin/event-fees', ['fees' => [$e => [
            'buyer' => ['enabled' => '1', 'calc_type' => 'fixed', 'amount' => '35', 'min_amount' => '', 'max_amount' => ''],
            'seller' => ['enabled' => '0', 'calc_type' => 'percent', 'amount' => '2', 'min_amount' => '10', 'max_amount' => '90'],
            'stranger' => ['enabled' => '1', 'calc_type' => 'fixed', 'amount' => '5'],
        ]]])->assertRedirect();

        $this->assertEquals(35, PlatformEventFee::where('event_key', $e)->where('role', 'buyer')->first()->feeFor(1000));
        $this->assertEquals(0, PlatformEventFee::where('event_key', $e)->where('role', 'seller')->first()->feeFor(1000), 'disabled');
        $this->assertNull(PlatformEventFee::where('event_key', $e)->where('role', 'stranger')->first(), 'an unknown party is ignored');

        $this->actingAs($admin)->put('/admin/event-fees', ['fees' => [$e => ['buyer' => ['calc_type' => 'percent', 'amount' => '150']]]])->assertSessionHasErrors('fees');
    }
}
