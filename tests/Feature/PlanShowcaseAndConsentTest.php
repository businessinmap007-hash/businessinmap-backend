<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BodyCompositionReport;
use App\Models\Post;
use App\Models\Project;
use App\Models\ProjectShowcase;
use App\Models\ShowcaseConsent;
use App\Models\TrainingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «نتائج موثَّقة» for training / nutrition plans (anonymous, changes only, only the months the client confirmed) and the GENERAL
 * consent that publishes a covered request at once — but never puts back what the person took down himself.
 */
class PlanShowcaseAndConsentTest extends TestCase
{
    use DatabaseTransactions;

    private User $trainer;
    private User $client;
    private TrainingPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->trainer = $this->user('Coach', User::TYPE_BUSINESS);
        $this->client = $this->user('Trainee', User::TYPE_CLIENT);

        $this->plan = TrainingPlan::create([
            'trainer_id' => $this->trainer->id, 'client_id' => $this->client->id, 'title' => 'Ahmed cutting plan',
            'status' => TrainingPlan::STATUS_ACTIVE, 'starts_on' => now()->subWeeks(9)->toDateString(),
        ]);
        $this->plan->exercises()->create(['name' => 'Squat', 'sets' => 3, 'reps' => '10']);
        $this->plan->meals()->create(['meal_type' => 'lunch', 'name' => 'Grilled chicken']);
    }

    private function user(string $tag, string $type): User
    {
        $u = new User();
        $u->forceFill([
            'name' => $tag . ' ' . Str::random(4), 'type' => $type, 'email' => strtolower($tag) . '-' . uniqid() . '@example.test',
            'phone' => '010' . random_int(10000000, 99999999), 'password' => 'secret-password', 'api_token' => Str::random(60),
        ])->save();

        return $u;
    }

    private function report(string $month, float $weight, float $fat): BodyCompositionReport
    {
        return BodyCompositionReport::create([
            'training_plan_id' => $this->plan->id, 'client_id' => $this->client->id, 'trainer_id' => $this->trainer->id,
            'for_month' => $month, 'measured_on' => $month, 'weight_kg' => $weight, 'fat_percent' => $fat,
        ]);
    }

    private function ask()
    {
        Sanctum::actingAs($this->trainer);

        return $this->postJson("/api/v2/business/training-plans/{$this->plan->id}/showcase", ['public_title' => 'Eight weeks, real results', 'note' => 'Cutting']);
    }

    private function consent(array $on): void
    {
        Sanctum::actingAs($this->client);
        $this->putJson('/api/v2/showcase-consent', $on)->assertOk();
    }

    public function test_the_client_confirms_a_month_and_the_post_shows_only_confirmed_changes_never_names_or_weights(): void
    {
        $r1 = $this->report('2026-08-01', 95.0, 30.0);
        $r2 = $this->report('2026-09-01', 91.5, 26.5);
        $r3 = $this->report('2026-10-01', 88.0, 23.0);

        $this->ask()->assertCreated()->assertJsonPath('data.showcase.status', 'requested');

        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/training-plans/{$this->plan->id}/body-reports/{$r1->id}/confirm")->assertOk();
        $this->postJson("/api/v2/training-plans/{$this->plan->id}/body-reports/{$r2->id}/confirm")->assertOk();
        $this->postJson("/api/v2/training-plans/{$this->plan->id}/showcase/approve")->assertOk()->assertJsonPath('data.showcase.status', 'approved');

        $postId = (int) \App\Models\PlanShowcase::query()->where('training_plan_id', $this->plan->id)->value('post_id');
        $fetch = fn () => collect($this->getJson("/api/v2/businesses/{$this->trainer->id}/posts")->json('data'))->firstWhere('id', $postId)['plan_showcase'];

        $s = $fetch();
        $this->assertSame('combined', $s['kind']);
        $this->assertSame(1, $s['confirmed_months']);                     // month 3 is not confirmed yet
        $this->assertEquals(-3.5, $s['total_change']['weight_kg']);
        $this->assertEquals(-3.5, $s['total_change']['fat_percent']);

        // the client confirms the third month → the post grows by itself
        $this->postJson("/api/v2/training-plans/{$this->plan->id}/body-reports/{$r3->id}/confirm")->assertOk();
        $this->assertEquals(-7.0, $fetch()['total_change']['weight_kg']);

        // a month the trainer re-measures needs the client's word again
        Sanctum::actingAs($this->trainer);
        $this->postJson("/api/v2/business/training-plans/{$this->plan->id}/body-reports", ['for_month' => '2026-10-01', 'weight_kg' => 87.0])->assertOk();
        $this->assertSame(1, $fetch()['confirmed_months']);

        $raw = json_encode($fetch(), JSON_UNESCAPED_UNICODE);
        foreach (['Ahmed', $this->client->name, '95', '91.5'] as $leak) {
            $this->assertStringNotContainsString($leak, $raw, "leaked: {$leak}");
        }
    }

    public function test_an_empty_or_unaccepted_plan_cannot_be_shown(): void
    {
        $this->plan->update(['status' => TrainingPlan::STATUS_PENDING]);
        $this->ask()->assertStatus(422);

        $this->plan->update(['status' => TrainingPlan::STATUS_ACTIVE]);
        $this->plan->exercises()->delete();
        $this->plan->meals()->delete();
        $this->ask()->assertStatus(422);
    }

    public function test_a_general_consent_publishes_at_once_but_never_puts_back_what_the_client_took_down(): void
    {
        // combined plan: both switches are needed
        $this->consent(['training' => true]);
        $this->ask()->assertCreated()->assertJsonPath('data.showcase.status', 'requested');
        Sanctum::actingAs($this->trainer);
        $this->deleteJson("/api/v2/business/training-plans/{$this->plan->id}/showcase")->assertOk();

        $this->consent(['nutrition' => true]);
        $this->ask()->assertCreated()->assertJsonPath('data.showcase.status', 'approved')->assertJsonPath('data.showcase.decided_via', 'general');
        $postId = (int) \App\Models\PlanShowcase::query()->where('training_plan_id', $this->plan->id)->value('post_id');
        $this->assertTrue((bool) Post::query()->whereKey($postId)->value('is_active'));

        // the client takes it down → the business asks again → no standing consent overrides his word
        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/training-plans/{$this->plan->id}/showcase/withdraw")->assertOk();
        $this->assertFalse((bool) Post::query()->whereKey($postId)->value('is_active'));
        $this->ask()->assertCreated()->assertJsonPath('data.showcase.status', 'requested');

        // …but what the BUSINESS took down comes back under the consent
        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/training-plans/{$this->plan->id}/showcase/approve")->assertOk();
        Sanctum::actingAs($this->trainer);
        $this->deleteJson("/api/v2/business/training-plans/{$this->plan->id}/showcase")->assertOk();
        $this->ask()->assertCreated()->assertJsonPath('data.showcase.status', 'approved');
        $this->assertTrue((bool) Post::query()->whereKey($postId)->value('is_active'));
    }

    public function test_a_general_consent_covers_projects_and_photos_are_a_separate_switch(): void
    {
        $office = $this->user('Office', User::TYPE_BUSINESS);
        $booking = Booking::query()->create(['user_id' => $this->client->id, 'business_id' => $office->id, 'status' => Booking::STATUS_PENDING, 'date' => now()->addDay()->toDateString(), 'time' => '12:00:00']);
        $project = Project::create(['business_id' => $office->id, 'title' => 'Villa', 'status' => Project::STATUS_PLANNING, 'starts_on' => now()->toDateString(), 'operation_type' => Booking::class, 'operation_id' => $booking->id]);
        $project->tasks()->create(['title' => 'Frame', 'requires_photo' => false, 'sort_order' => 1]);

        Sanctum::actingAs($office);
        $this->postJson("/api/v2/business/projects/{$project->id}/agreement", ['starts_on' => now()->toDateString(), 'due_on' => now()->addDays(60)->toDateString(), 'grace_days' => 30])->assertCreated();
        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/operations/booking/{$booking->id}/project/agreement/agree")->assertOk();

        $this->consent(['projects' => true]);

        // photos asked, photos not allowed → still waits for his word
        Sanctum::actingAs($office);
        $this->postJson("/api/v2/business/projects/{$project->id}/showcase", ['public_title' => 'A villa', 'show_photos' => true])->assertCreated()->assertJsonPath('data.showcase.status', 'requested');
        $this->deleteJson("/api/v2/business/projects/{$project->id}/showcase")->assertOk();

        // no photos → published at once under the general consent
        $this->postJson("/api/v2/business/projects/{$project->id}/showcase", ['public_title' => 'A villa'])->assertCreated()->assertJsonPath('data.showcase.status', 'approved')->assertJsonPath('data.showcase.decided_via', 'general');

        // the customer takes it down: a standing consent does not undo his act
        Sanctum::actingAs($this->client);
        $this->postJson("/api/v2/operations/booking/{$booking->id}/project/showcase/withdraw")->assertOk();
        Sanctum::actingAs($office);
        $this->postJson("/api/v2/business/projects/{$project->id}/showcase", ['public_title' => 'A villa'])->assertCreated()->assertJsonPath('data.showcase.status', 'requested');
    }

    public function test_the_consent_is_per_person_and_photos_need_projects(): void
    {
        $this->consent(['project_photos' => true]);
        $this->assertFalse(ShowcaseConsent::forUser($this->client->id)['project_photos'], 'photos only on top of showing projects');

        $this->consent(['projects' => true, 'project_photos' => true, 'training' => true]);
        $this->assertSame(['projects' => true, 'project_photos' => true, 'training' => true, 'nutrition' => false], ShowcaseConsent::forUser($this->client->id));

        $this->assertFalse(ShowcaseConsent::forUser($this->trainer->id)['training']);

        // only the plan's client confirms or answers; a stranger gets 404
        $stranger = $this->user('Other', User::TYPE_CLIENT);
        $r = $this->report('2026-08-01', 90.0, 25.0);
        Sanctum::actingAs($stranger);
        $this->postJson("/api/v2/training-plans/{$this->plan->id}/body-reports/{$r->id}/confirm")->assertNotFound();
        $this->postJson("/api/v2/training-plans/{$this->plan->id}/showcase/approve")->assertNotFound();
    }
}
