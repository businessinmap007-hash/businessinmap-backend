<?php

namespace Tests\Feature;

use App\Models\TrainingPlan;
use App\Models\User;
use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The AdminV2 read-only oversight of training plans. Gated OPERATIONS; lists
 * every plan and shows one with its workout, meals, progress, and adherence.
 */
class AdminTrainingTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $type, string $tag): User
    {
        $u = new User();
        $u->name = $tag . ' ' . Str::random(4);
        $u->email = strtolower($tag) . '-' . uniqid() . '@example.test';
        $u->phone = '01' . random_int(100000000, 999999999);
        $u->password = 'secret-password';
        $u->type = $type;
        $u->api_token = Str::random(80);
        $u->save();

        return $u;
    }

    private function supervisor(): User
    {
        $admin = User::query()->where('type', User::TYPE_ADMIN)->firstOrFail();
        foreach ([AdminAbility::ACCESS, AdminAbility::OPERATIONS] as $ability) {
            \Bouncer::allow($admin)->to($ability);
        }
        \Bouncer::refresh();

        return $admin;
    }

    private function plan(): TrainingPlan
    {
        $trainer = $this->user(User::TYPE_BUSINESS, 'Gym');
        $client = $this->user(User::TYPE_CLIENT, 'Trainee');
        $plan = TrainingPlan::create([
            'trainer_id' => $trainer->id, 'client_id' => $client->id,
            'title' => 'Strength block ' . Str::random(4), 'status' => TrainingPlan::STATUS_ACTIVE,
        ]);
        $plan->exercises()->create(['name' => 'Deadlift', 'sets' => 5, 'reps' => '5']);
        $plan->meals()->create(['meal_type' => 'lunch', 'name' => 'Chicken & rice', 'calories' => 700]);

        return $plan;
    }

    public function test_a_supervisor_lists_and_views_a_plan(): void
    {
        $plan = $this->plan();
        $admin = $this->supervisor();

        $this->actingAs($admin)->get('/admin/training-plans')
            ->assertOk()
            ->assertSee($plan->title)
            ->assertSee('/admin/training-plans/' . $plan->id);

        $this->actingAs($admin)->get("/admin/training-plans/{$plan->id}")
            ->assertOk()
            ->assertSee('Deadlift')
            ->assertSee('Chicken &amp; rice', false);
    }

    public function test_the_search_filter_narrows_the_list(): void
    {
        $keep = $this->plan();
        $this->plan();

        $this->actingAs($this->supervisor())
            ->get('/admin/training-plans?q=' . urlencode($keep->title))
            ->assertOk()
            ->assertSee($keep->title);
    }

    public function test_an_admin_without_operations_is_forbidden(): void
    {
        $plain = new User();
        $plain->name = 'Plain Admin';
        $plain->email = 'plaintr-' . uniqid() . '@example.test';
        $plain->phone = '0157' . random_int(1000000, 9999999);
        $plain->password = 'secret-password';
        $plain->type = User::TYPE_ADMIN;
        $plain->api_token = Str::random(80);
        $plain->save();

        \Bouncer::allow($plain)->to(AdminAbility::ACCESS);
        \Bouncer::refresh();

        $this->actingAs($plain)->get('/admin/training-plans')->assertForbidden();
    }
}
