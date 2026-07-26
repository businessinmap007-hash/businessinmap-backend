<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The AdminV2 read-only oversight of business project timelines. Gated on the
 * OPERATIONS ability; lists every project and shows one with its stages.
 */
class AdminProjectsTest extends TestCase
{
    use DatabaseTransactions;

    private function makeBusiness(): User
    {
        $u = new User();
        $u->name = 'Admin Proj Shop ' . Str::random(4);
        $u->email = 'adminproj-' . uniqid() . '@example.test';
        $u->phone = '0104' . random_int(1000000, 9999999);
        $u->password = 'secret-password';
        $u->type = User::TYPE_BUSINESS;
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

    public function test_a_supervisor_lists_and_views_a_project(): void
    {
        $business = $this->makeBusiness();
        $project = Project::create([
            'business_id' => $business->id,
            'title' => 'Warehouse steelwork',
            'reference' => 'WH-STEEL-9',
        ]);
        $project->tasks()->create(['title' => 'Erect frame', 'progress' => 60]);

        $admin = $this->supervisor();

        $this->actingAs($admin)->get('/admin/projects')
            ->assertOk()
            ->assertSee('Warehouse steelwork')
            ->assertSee('/admin/projects/' . $project->id);

        $this->actingAs($admin)->get("/admin/projects/{$project->id}")
            ->assertOk()
            ->assertSee('Erect frame');
    }

    public function test_the_search_filter_narrows_the_list(): void
    {
        $business = $this->makeBusiness();
        $keep = Project::create(['business_id' => $business->id, 'title' => 'Findable ' . Str::random(5), 'reference' => 'REF-KEEP']);
        Project::create(['business_id' => $business->id, 'title' => 'Hidden other']);

        $this->actingAs($this->supervisor())
            ->get('/admin/projects?q=REF-KEEP')
            ->assertOk()
            ->assertSee($keep->title)
            ->assertDontSee('Hidden other');
    }

    public function test_an_admin_without_operations_is_forbidden(): void
    {
        $plain = new User();
        $plain->name = 'Plain Admin';
        $plain->email = 'plainop-' . uniqid() . '@example.test';
        $plain->phone = '0156' . random_int(1000000, 9999999);
        $plain->password = 'secret-password';
        $plain->type = User::TYPE_ADMIN;
        $plain->api_token = Str::random(80);
        $plain->save();

        \Bouncer::allow($plain)->to(AdminAbility::ACCESS);
        \Bouncer::refresh();

        $this->actingAs($plain)->get('/admin/projects')->assertForbidden();
    }
}
