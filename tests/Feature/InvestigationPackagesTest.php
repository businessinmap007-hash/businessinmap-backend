<?php

namespace Tests\Feature;

use App\Models\BusinessServicePrice;
use App\Models\InvestigationOrder;
use App\Models\PlatformService;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «المعمل والأشعة كما في اللوحة: باقات، وسحب العينة من المنزل، والمريض يختار الموعد» — المالك، 2026-10-20.
 *
 * Rolls back.
 */
class InvestigationPackagesTest extends TestCase
{
    use DatabaseTransactions;

    private const HEALTH = 20;
    private const LAB = 163;
    private const RADIOLOGY = 252;

    private function user(string $type, ?int $child = null, string $tag = 'U'): User
    {
        $u = new User();
        $u->forceFill([
            'name' => $tag . ' ' . Str::random(4), 'type' => $type, 'email' => strtolower($tag) . '-' . uniqid() . '@example.test',
            'phone' => '010' . random_int(10000000, 99999999), 'password' => 'secret-password', 'api_token' => Str::random(60),
            'category_id' => $child ? self::HEALTH : null, 'category_child_id' => $child,
        ])->save();

        return $u;
    }

    private function option(string $group, string $name): int
    {
        $id = (int) DB::table('options as o')->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', $group)->where('o.name_ar', $name)->value('o.id');
        $this->assertGreaterThan(0, $id, $name);

        return $id;
    }

    private function price(User $center, int $optionId, float $price): void
    {
        BusinessServicePrice::create([
            'business_id' => $center->id, 'service_id' => (int) PlatformService::query()->where('key', PlatformService::KEY_BOOKING)->value('id'),
            'child_id' => (int) $center->category_child_id, 'bookable_item_type' => BusinessServicePrice::DEFAULT_ITEM_TYPE,
            'line_option_id' => $optionId, 'price' => $price, 'currency' => 'EGP', 'is_active' => 1,
        ]);
    }

    /** a day this many days ahead, at the given time, in the platform's clock */
    private function at(int $days, string $time): string
    {
        return Carbon::now('Africa/Cairo')->addDays($days)->startOfDay()->setTimeFromTimeString($time)->toIso8601String();
    }

    /** @return array{0:User,1:array<string,int>} a lab with two priced tests, and their option ids */
    private function lab(): array
    {
        $lab = $this->user(User::TYPE_BUSINESS, self::LAB, 'Lab');
        $ids = [
            'cbc' => $this->option('التحاليل الطبية', 'صورة دم كاملة CBC'),
            'sugar' => $this->option('التحاليل الطبية', 'سكر صائم وفاطر'),
            'liver' => $this->option('التحاليل الطبية', 'وظائف كبد'),
        ];
        $this->price($lab, $ids['cbc'], 100);
        $this->price($lab, $ids['sugar'], 60);
        $this->price($lab, $ids['liver'], 150);

        return [$lab, $ids];
    }

    public function test_a_lab_prices_a_package_and_a_patient_orders_it_at_that_price(): void
    {
        [$lab, $ids] = $this->lab();
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');

        Sanctum::actingAs($lab);
        $packages = $this->postJson('/api/v2/business/investigation-packages', [
            'name' => 'باقة الفحص الشامل', 'price' => 250, 'option_ids' => [$ids['cbc'], $ids['sugar'], $ids['liver']], 'note' => 'صيام 10 ساعات',
        ])->assertOk()->json('data.packages');
        $this->assertCount(1, $packages);
        $this->assertCount(3, $packages[0]['tests']);
        $packageId = $packages[0]['id'];

        // the patient sees it on the centre's page, with its price and what it includes
        Sanctum::actingAs($patient);
        $seen = $this->getJson("/api/v2/investigation-centers/{$lab->id}/tests")->assertOk()->json('data');
        $this->assertSame([$packageId], array_column($seen['packages'], 'id'));
        $this->assertSame(250.0, (float) $seen['packages'][0]['price']);

        // one test + the package: the package is ONE line at its own price, not the sum of its tests
        $order = $this->postJson('/api/v2/investigation-orders/request', [
            'center_id' => $lab->id, 'option_ids' => [], 'package_ids' => [$packageId],
        ])->assertCreated()->json('data.order');

        $this->assertSame('sent', $order['status']);
        $this->assertSame(250.0, (float) $order['total']);
        $this->assertCount(1, $order['items']);
        $this->assertSame('package', $order['items'][0]['kind']);
        $this->assertStringContainsString('وظائف كبد', (string) $order['items'][0]['details']);

        // a package of another centre, or nothing at all, is refused
        $other = $this->user(User::TYPE_BUSINESS, self::LAB, 'Lab2');
        $this->postJson('/api/v2/investigation-orders/request', ['center_id' => $other->id, 'package_ids' => [$packageId]])->assertStatus(422);
        $this->postJson('/api/v2/investigation-orders/request', ['center_id' => $lab->id])->assertStatus(422);
    }

    public function test_a_package_can_be_edited_hidden_and_deleted_only_by_its_centre(): void
    {
        [$lab, $ids] = $this->lab();
        $stranger = $this->user(User::TYPE_BUSINESS, self::LAB, 'Lab2');

        Sanctum::actingAs($lab);
        $id = $this->postJson('/api/v2/business/investigation-packages', ['name' => 'أ', 'price' => 100, 'option_ids' => [$ids['cbc']]])
            ->json('data.packages.0.id');

        Sanctum::actingAs($stranger);
        $this->postJson('/api/v2/business/investigation-packages', ['id' => $id, 'name' => 'x', 'price' => 1, 'option_ids' => [$ids['cbc']]])->assertNotFound();
        $this->deleteJson("/api/v2/business/investigation-packages/{$id}")->assertNotFound();

        Sanctum::actingAs($lab);
        $this->postJson('/api/v2/business/investigation-packages', ['id' => $id, 'name' => 'أ', 'price' => 120, 'option_ids' => [$ids['cbc'], $ids['sugar']], 'is_active' => false])->assertOk();
        // hidden: the patient no longer sees it
        Sanctum::actingAs($this->user(User::TYPE_CLIENT));
        $this->assertSame([], $this->getJson("/api/v2/investigation-centers/{$lab->id}/tests")->json('data.packages'));

        Sanctum::actingAs($lab);
        $this->deleteJson("/api/v2/business/investigation-packages/{$id}")->assertOk()->assertJsonPath('data.packages', []);
    }

    public function test_a_lab_takes_samples_at_home_for_a_fee_and_a_radiology_centre_does_not(): void
    {
        [$lab, $ids] = $this->lab();
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');

        // not offered yet: asking for it is refused
        Sanctum::actingAs($patient);
        $this->postJson('/api/v2/investigation-orders/request', ['center_id' => $lab->id, 'option_ids' => [$ids['cbc']], 'home_collection' => true])->assertStatus(422);

        Sanctum::actingAs($lab);
        $this->putJson('/api/v2/business/investigation-home-fee', ['home_fee' => 50])->assertOk()->assertJsonPath('data.home_fee', 50);

        Sanctum::actingAs($patient);
        $this->assertSame(50.0, (float) $this->getJson("/api/v2/investigation-centers/{$lab->id}/tests")->json('data.home_fee'));

        $order = $this->postJson('/api/v2/investigation-orders/request', [
            'center_id' => $lab->id, 'option_ids' => [$ids['cbc'], $ids['sugar']], 'home_collection' => true,
        ])->assertCreated()->json('data.order');

        // 100 + 60 + the 50 fee, and the order says it is a home sample
        $this->assertSame(210.0, (float) $order['total']);
        $this->assertTrue($order['home_collection']);
        $this->assertSame(50.0, (float) $order['home_fee']);

        // a radiology centre has no home sample at all
        $radiology = $this->user(User::TYPE_BUSINESS, self::RADIOLOGY, 'Radiology');
        Sanctum::actingAs($radiology);
        $this->putJson('/api/v2/business/investigation-home-fee', ['home_fee' => 30])->assertStatus(422);
    }

    public function test_the_patient_chooses_the_day_and_time_within_the_centres_hours_and_it_stands_when_accepted(): void
    {
        [$lab, $ids] = $this->lab();
        $patient = $this->user(User::TYPE_CLIENT, null, 'Patient');

        // open 09:00–17:00 every day
        DB::table('business_working_hours')->where('business_id', $lab->id)->delete();
        foreach (range(0, 6) as $d) {
            DB::table('business_working_hours')->insert([
                'business_id' => $lab->id, 'day_of_week' => $d, 'is_closed' => 0,
                'open_time' => '09:00:00', 'close_time' => '17:00:00', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Sanctum::actingAs($patient);
        $day = Carbon::now('Africa/Cairo')->addDays(3)->toDateString();
        $times = $this->getJson("/api/v2/investigation-centers/{$lab->id}/times?date={$day}")->assertOk()->json('data');
        $this->assertFalse($times['closed']);
        $this->assertSame('09:00', $times['times'][0]);
        $this->assertSame('16:30', end($times['times']));

        // outside the hours: refused; inside: kept
        $this->postJson('/api/v2/investigation-orders/request', ['center_id' => $lab->id, 'option_ids' => [$ids['cbc']], 'requested_at' => $this->at(3, '20:00')])
            ->assertStatus(422);
        $this->postJson('/api/v2/investigation-orders/request', ['center_id' => $lab->id, 'option_ids' => [$ids['cbc']], 'requested_at' => $this->at(-1, '10:00')])
            ->assertStatus(422);

        $orderId = $this->postJson('/api/v2/investigation-orders/request', [
            'center_id' => $lab->id, 'option_ids' => [$ids['cbc']], 'requested_at' => $this->at(3, '10:30'),
        ])->assertCreated()->assertJsonPath('data.order.requested_at', fn ($v) => $v !== null)->json('data.order.id');

        // the centre accepts without proposing another time: the patient's choice stands
        Sanctum::actingAs($lab);
        $accepted = $this->postJson("/api/v2/business/investigation-orders/{$orderId}/accept")->assertOk()->json('data.order');
        $this->assertNotNull($accepted['appointment_at']);
        $this->assertSame(
            Carbon::parse($accepted['requested_at'])->toIso8601String(),
            Carbon::parse($accepted['appointment_at'])->toIso8601String(),
        );

        $this->assertSame(InvestigationOrder::STATUS_ACCEPTED, InvestigationOrder::query()->find($orderId)->status);
    }
}
