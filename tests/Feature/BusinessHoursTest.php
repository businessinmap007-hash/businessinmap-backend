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
}
