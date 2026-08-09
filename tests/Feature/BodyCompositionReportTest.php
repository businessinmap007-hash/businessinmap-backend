<?php

namespace Tests\Feature;

use App\Models\BodyCompositionReport;
use App\Models\PlatformService;
use App\Models\TrainingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * «تقرير شهرى لحجم العضلات ومستوى الدهون والمياة بشكل منظم خاص بين المدرب
 * والعميل» — owner, 2026-08-09.
 *
 * The plan already had `plan_progress_logs`: a weight and a note, whenever the
 * client felt like adding one. That cannot answer the question a training plan
 * is actually judged on — whether the weight that moved was muscle, fat or
 * water. A client who drops three kilos of water reads it as progress; his
 * trainer has to read it as a warning.
 *
 * One row per plan per MONTH, the trainer records it (he owns the scale), and
 * nobody outside the two of them can see it.
 */
class BodyCompositionReportTest extends TestCase
{
    use DatabaseTransactions;

    private User $trainer;

    private User $client;

    private TrainingPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->trainer = $this->user(User::TYPE_BUSINESS, 'جيم الاختبار');
        $this->client = $this->user(User::TYPE_CLIENT, 'متدرب');

        $this->plan = TrainingPlan::create([
            'trainer_id' => $this->trainer->id,
            'client_id' => $this->client->id,
            'title' => 'خطة تجريبية',
            'status' => TrainingPlan::STATUS_ACTIVE,
        ]);
    }

    /** The owner's three numbers, recorded and read back. */
    public function test_the_trainer_records_muscle_fat_and_water(): void
    {
        $report = $this->record('2026-03-14', [
            'weight_kg' => 92.4, 'muscle_mass_kg' => 38.2, 'fat_percent' => 27.5, 'water_percent' => 52.1,
        ]);

        $this->assertSame('2026-03', $report['for_month'], 'the month was not normalised');
        $this->assertSame(38.2, $report['muscle_mass_kg']);
        $this->assertSame(27.5, $report['fat_percent']);
        $this->assertSame(52.1, $report['water_percent']);
    }

    /** «بشكل منظم» — one report per month, however often the scale is read. */
    public function test_a_second_reading_in_the_same_month_updates_it(): void
    {
        $this->record('2026-03-01', ['weight_kg' => 92.4]);
        $this->record('2026-03-28', ['weight_kg' => 90.9]);

        $rows = BodyCompositionReport::query()->where('training_plan_id', $this->plan->id)->get();

        $this->assertCount(1, $rows, 'March was filed twice');
        $this->assertSame('90.90', (string) $rows->first()->weight_kg);
    }

    /** The series carries the change, so nobody does arithmetic on two cards. */
    public function test_each_month_carries_its_change_from_the_one_before(): void
    {
        $this->record('2026-03-05', ['muscle_mass_kg' => 38.0, 'fat_percent' => 28.0]);
        $this->record('2026-04-05', ['muscle_mass_kg' => 39.5, 'fat_percent' => 25.5]);

        $reports = $this->actingAs($this->trainer, 'sanctum')
            ->getJson("/api/v2/business/training-plans/{$this->plan->id}/body-reports?lang=ar")
            ->assertOk()->json('data.reports');

        // newest first
        $this->assertSame('2026-04', $reports[0]['for_month']);
        $this->assertSame(1.5, $reports[0]['change']['muscle_mass_kg']);
        $this->assertSame(-2.5, $reports[0]['change']['fat_percent']);

        $this->assertNull($reports[1]['change']['muscle_mass_kg'], 'the first month has nothing to compare to');
    }

    /** The client reads his own body. He does not record it. */
    public function test_the_client_reads_but_cannot_record(): void
    {
        $this->record('2026-03-01', ['fat_percent' => 27.5]);

        $reports = $this->actingAs($this->client, 'sanctum')
            ->getJson("/api/v2/training-plans/{$this->plan->id}/body-reports?lang=ar")
            ->assertOk()->json('data.reports');

        $this->assertCount(1, $reports);
        $this->assertSame(27.5, $reports[0]['fat_percent']);

        // 405, not 404: the client's URI exists for READING and the write verb
        // was never declared on it. That is the rule stated by the router
        // itself, which is stronger than any check inside a controller.
        $this->actingAs($this->client, 'sanctum')
            ->postJson("/api/v2/training-plans/{$this->plan->id}/body-reports", ['fat_percent' => 10])
            ->assertStatus(405);

        // And the trainer's own route refuses him: he is not the trainer.
        $this->actingAs($this->client, 'sanctum')
            ->postJson("/api/v2/business/training-plans/{$this->plan->id}/body-reports", ['fat_percent' => 10])
            ->assertStatus(403);
    }

    /** «خاص بين المدرب والعميل» — a third subscriber gets 404, never 403. */
    public function test_nobody_else_can_read_it(): void
    {
        $this->record('2026-03-01', ['fat_percent' => 27.5]);

        $stranger = $this->user(User::TYPE_CLIENT, 'مشترك آخر');
        $otherGym = $this->user(User::TYPE_BUSINESS, 'جيم آخر');

        $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/v2/training-plans/{$this->plan->id}/body-reports")
            ->assertStatus(404);

        $this->actingAs($otherGym, 'sanctum')
            ->getJson("/api/v2/business/training-plans/{$this->plan->id}/body-reports")
            ->assertStatus(404);
    }

    /** An empty report would occupy the month and read as «measured, all zero». */
    public function test_a_report_with_no_measurement_is_refused(): void
    {
        $this->actingAs($this->trainer, 'sanctum')
            ->postJson("/api/v2/business/training-plans/{$this->plan->id}/body-reports", [
                'for_month' => '2026-03-01', 'notes' => 'نسيت الميزان',
            ])
            ->assertStatus(422);
    }

    /** Fat and water are shares of one body; together they cannot pass 100. */
    public function test_impossible_percentages_are_refused(): void
    {
        $this->actingAs($this->trainer, 'sanctum')
            ->postJson("/api/v2/business/training-plans/{$this->plan->id}/body-reports", [
                'for_month' => '2026-03-01', 'fat_percent' => 60, 'water_percent' => 55,
            ])
            ->assertStatus(422);
    }

    /** Deleting the plan takes its reports — they are about that plan's months. */
    public function test_the_reports_die_with_the_plan(): void
    {
        $this->record('2026-03-01', ['weight_kg' => 92.4]);

        $planId = $this->plan->id;
        $this->plan->delete();

        $this->assertSame(0, BodyCompositionReport::query()->where('training_plan_id', $planId)->count());
    }

    /** The service the gym now sells the plan under. */
    public function test_training_is_a_service_a_gym_can_offer(): void
    {
        $service = PlatformService::query()->where('key', PlatformService::KEY_TRAINING)->first();

        $this->assertNotNull($service, 'training is not a platform service');
        $this->assertTrue((bool) $service->is_active);

        $gym = (int) DB::table('category_children_master')->where('name_ar', 'جيم')->value('id');

        $this->assertTrue(
            DB::table('category_platform_services')->where('child_id', $gym)
                ->where('platform_service_id', $service->id)->where('is_active', 1)->exists(),
            'a gym cannot offer training'
        );

        $config = json_decode((string) DB::table('category_service_configs')
            ->where('child_id', $gym)->where('platform_service_id', $service->id)
            ->where('is_active', 1)->value('config'), true) ?: [];

        $this->assertContains('training_combined', $config['allowed_item_types'] ?? []);
        $this->assertFalse((bool) ($config['requires_bookable_item'] ?? false));
    }

    /** @param array<string,mixed> $measures @return array<string,mixed> */
    private function record(string $month, array $measures): array
    {
        return $this->actingAs($this->trainer, 'sanctum')
            ->postJson("/api/v2/business/training-plans/{$this->plan->id}/body-reports?lang=ar",
                $measures + ['for_month' => $month])
            ->assertSuccessful()
            ->json('data.report');
    }

    private function user(string $type, string $name): User
    {
        return User::create([
            'name' => $name,
            'email' => 'body' . uniqid() . '@example.test',
            'password' => bcrypt('Test1234'),
            'type' => $type,
            'api_token' => Str::random(60),
            'phone' => '010' . random_int(10000000, 99999999),
        ]);
    }
}
