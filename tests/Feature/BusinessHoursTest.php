<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BusinessHoursService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Business opening hours, and the "open right now" question a customer's search
 * uses to skip closed shops.
 */
class BusinessHoursTest extends TestCase
{
    use DatabaseTransactions;

    private BusinessHoursService $hours;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hours = app(BusinessHoursService::class);
    }

    private function makeBusiness(): User
    {
        $u = new User();
        $u->name = 'Hours Shop ' . Str::random(4);
        $u->email = 'hours-' . uniqid() . '@example.test';
        $u->phone = '0106' . random_int(1000000, 9999999);
        $u->password = 'secret-password';
        $u->type = User::TYPE_BUSINESS;
        $u->api_token = Str::random(80);
        $u->save();

        return $u;
    }

    public function test_is_open_now_across_windows_closed_and_unknown(): void
    {
        // A Monday at 14:30.
        $at = Carbon::parse('2026-07-27 14:30:00');
        $day = $at->dayOfWeek;

        $shop = $this->makeBusiness();

        // Open 09:00–22:00 → open at 14:30.
        $this->hours->save($shop->id, [['day' => $day, 'open' => '09:00', 'close' => '22:00']]);
        $this->assertTrue($this->hours->isOpenNow($shop->id, $at));

        // Evening-only window → closed at 14:30.
        $this->hours->save($shop->id, [['day' => $day, 'open' => '20:00', 'close' => '23:00']]);
        $this->assertFalse($this->hours->isOpenNow($shop->id, $at));

        // Explicitly closed today.
        $this->hours->save($shop->id, [['day' => $day, 'is_closed' => true]]);
        $this->assertFalse($this->hours->isOpenNow($shop->id, $at));

        // Past-midnight window 20:00→02:00: closed at 14:30, open at 21:00.
        $this->hours->save($shop->id, [['day' => $day, 'open' => '20:00', 'close' => '02:00']]);
        $this->assertFalse($this->hours->isOpenNow($shop->id, $at));
        $this->assertTrue($this->hours->isOpenNow($shop->id, Carbon::parse('2026-07-27 21:00:00')));

        // A shop with no hours configured is treated as available.
        $unknown = $this->makeBusiness();
        $this->assertTrue($this->hours->isOpenNow($unknown->id, $at));
    }

    public function test_filter_open_now_excludes_only_the_currently_closed(): void
    {
        $at = Carbon::parse('2026-07-27 14:30:00');
        $day = $at->dayOfWeek;

        $open = $this->makeBusiness();
        $closed = $this->makeBusiness();
        $unknown = $this->makeBusiness();

        $this->hours->save($open->id, [['day' => $day, 'open' => '09:00', 'close' => '22:00']]);
        $this->hours->save($closed->id, [['day' => $day, 'is_closed' => true]]);
        // $unknown: no rows.

        $ids = User::query()
            ->whereIn('id', [$open->id, $closed->id, $unknown->id])
            ->tap(fn ($q) => $this->hours->filterOpenNow($q, $at))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $this->assertContains((int) $open->id, $ids);
        $this->assertContains((int) $unknown->id, $ids, 'unknown hours must not be hidden');
        $this->assertNotContains((int) $closed->id, $ids);

        // The batch map agrees.
        $map = $this->hours->openNowMap([$open->id, $closed->id, $unknown->id], $at);
        $this->assertTrue($map[$open->id]);
        $this->assertFalse($map[$closed->id]);
        $this->assertTrue($map[$unknown->id]);
    }

    public function test_open_now_is_accepted_by_retail_search(): void
    {
        // The filter must apply cleanly (no SQL error).
        $this->getJson('/api/v2/discovery/retail/products?open_now=1')->assertOk();
    }

    public function test_open_now_is_accepted_across_all_discovery_screens(): void
    {
        $shop = $this->makeBusiness();

        $this->getJson('/api/v2/discovery/retail/filters?open_now=1')->assertOk();
        $this->getJson('/api/v2/discovery/filters?child_id=1&open_now=1')->assertOk();
        $this->getJson('/api/v2/discovery/attributes?child_id=1&open_now=1')->assertOk();

        // The single-shop menu view carries is_open_now (no hours ⇒ available).
        $this->getJson('/api/v2/discovery/menu/' . $shop->id)
            ->assertOk()
            ->assertJsonPath('data.business.is_open_now', true);
    }

    public function test_a_day_open_all_day_is_open_at_every_moment_and_says_so(): void
    {
        $shop = $this->makeBusiness();
        Sanctum::actingAs($shop);

        // the flag, the way the app writes it
        $this->putJson('/api/v2/business/working-hours', ['bulk' => ['all' => true, 'is_24h' => true]])
            ->assertOk()
            ->assertJsonPath('data.days.0.is_24h', true)
            ->assertJsonPath('data.days.6.is_24h', true);

        foreach (['00:00:00', '03:15:00', '12:00:00', '23:59:30'] as $time) {
            $this->assertTrue(
                $this->hours->isOpenNow($shop->id, Carbon::parse('2026-07-27 ' . $time)),
                "open all day must be open at {$time}"
            );
        }

        // the search filter agrees, at the very end of the day too
        $ids = User::query()->whereKey($shop->id)
            ->tap(fn ($q) => $this->hours->filterOpenNow($q, Carbon::parse('2026-07-27 23:59:00')))
            ->pluck('id')->all();
        $this->assertSame([(int) $shop->id], array_map('intval', $ids));

        // a booking window anywhere in the day fits
        $this->assertTrue($this->hours->isOpenThroughout(
            $shop->id,
            Carbon::parse('2026-07-27 02:00:00'),
            Carbon::parse('2026-07-27 05:30:00')
        ));
    }

    public function test_a_plain_00_00_to_23_59_is_read_as_open_all_day(): void
    {
        $shop = $this->makeBusiness();
        Sanctum::actingAs($shop);

        // the web panel's time fields cannot say "24 hours" — 00:00 to 23:59 is how it asks
        $this->putJson('/api/v2/business/working-hours', [
            'days' => [
                ['day' => 2, 'open' => '00:00', 'close' => '23:59'],
                ['day' => 3, 'open' => '09:00', 'close' => '17:00'],
            ],
        ])->assertOk();

        $days = collect($this->getJson('/api/v2/business/working-hours')->assertOk()->json('data.days'))->keyBy('day');
        $this->assertTrue($days[2]['is_24h']);
        $this->assertFalse($days[3]['is_24h']);
        $this->assertSame('09:00', $days[3]['open']);

        // the customer's page carries it too
        $pub = collect($this->getJson('/api/v2/businesses/' . $shop->id)->assertOk()->json('data.working_hours.days'))->keyBy('day');
        $this->assertTrue($pub[2]['is_24h']);
        $this->assertFalse($pub[3]['is_24h']);

        // and a day edited back to a normal window is no longer all day
        $this->putJson('/api/v2/business/working-hours', ['days' => [['day' => 2, 'open' => '08:00', 'close' => '20:00']]])->assertOk();
        $again = collect($this->getJson('/api/v2/business/working-hours')->json('data.days'))->keyBy('day');
        $this->assertFalse($again[2]['is_24h']);
    }

    public function test_a_business_sets_and_reads_its_own_hours(): void
    {
        $shop = $this->makeBusiness();
        Sanctum::actingAs($shop);

        $this->putJson('/api/v2/business/working-hours', [
            'days' => [
                ['day' => 0, 'open' => '10:00', 'close' => '23:00'],
                ['day' => 1, 'is_closed' => true],
            ],
        ])->assertOk();

        $res = $this->getJson('/api/v2/business/working-hours')->assertOk();

        $days = collect($res->json('data.days'))->keyBy('day');
        $this->assertSame('10:00', $days[0]['open']);
        $this->assertSame('23:00', $days[0]['close']);
        $this->assertTrue($days[1]['is_closed']);
    }

    public function test_every_business_account_carries_the_business_timezone_from_the_day_it_exists(): void
    {
        $shop = $this->makeBusiness();
        $this->assertSame('Africa/Cairo', (string) $shop->fresh()->timezone);

        // its own choice is kept, and clearing it falls back to Cairo rather than to UTC
        $shop->forceFill(['timezone' => 'Asia/Riyadh'])->save();
        $this->assertSame('Asia/Riyadh', (string) $shop->fresh()->timezone);
        $shop->forceFill(['timezone' => null])->save();
        $this->assertSame('Africa/Cairo', (string) $shop->fresh()->timezone);

        // a client account is not given one
        $client = \App\Models\User::create([
            'name' => 'C', 'email' => 'c' . uniqid() . '@example.test', 'password' => bcrypt('Test1234'),
            'type' => \App\Models\User::TYPE_CLIENT, 'api_token' => \Illuminate\Support\Str::random(60),
            'phone' => '010' . random_int(10000000, 99999999),
        ]);
        $this->assertEmpty($client->fresh()->timezone);
    }

    public function test_a_business_sets_its_timezone_and_hours_are_judged_in_it(): void
    {
        $shop = $this->makeBusiness();

        // Default is the platform's business timezone (Cairo) until one is set — a business never sits on UTC.
        $this->assertSame('Africa/Cairo', $this->hours->timezoneFor($shop->id));

        Sanctum::actingAs($shop);
        $this->putJson('/api/v2/business/working-hours', [
            'timezone' => 'Asia/Tokyo',
            'bulk' => ['all' => true, 'open' => '00:00', 'close' => '23:59'],
        ])->assertOk()->assertJsonPath('data.timezone', 'Asia/Tokyo');

        $this->assertSame('Asia/Tokyo', $this->hours->timezoneFor($shop->id));

        // isOpenNow with no $at now reads "now" in the shop's timezone (open all
        // day here ⇒ open) — and it must not error on the per-tz path.
        $this->assertTrue($this->hours->isOpenNow($shop->id));

        // An invalid timezone is rejected.
        $this->putJson('/api/v2/business/working-hours', ['timezone' => 'Not/AZone'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('timezone');
    }

    public function test_bulk_sets_all_days_at_once_and_per_day_overrides_it(): void
    {
        $shop = $this->makeBusiness();
        Sanctum::actingAs($shop);

        // Whole week 09:00–17:00 in one call.
        $this->putJson('/api/v2/business/working-hours', [
            'bulk' => ['all' => true, 'open' => '09:00', 'close' => '17:00'],
        ])->assertOk();

        $days = collect($this->getJson('/api/v2/business/working-hours')->json('data.days'))->keyBy('day');
        foreach (BusinessHoursService::DAYS as $d) {
            $this->assertSame('09:00', $days[$d]['open']);
            $this->assertSame('17:00', $days[$d]['close']);
        }

        // Bulk-close the weekend, but override Saturday individually.
        $this->putJson('/api/v2/business/working-hours', [
            'bulk' => ['days' => [5, 6], 'is_closed' => true],
            'days' => [['day' => 6, 'open' => '12:00', 'close' => '20:00']],
        ])->assertOk();

        $after = collect($this->getJson('/api/v2/business/working-hours')->json('data.days'))->keyBy('day');
        $this->assertTrue($after[5]['is_closed']);          // bulk closed
        $this->assertSame('12:00', $after[6]['open']);      // per-day override won
        $this->assertFalse($after[6]['is_closed']);
    }
}
