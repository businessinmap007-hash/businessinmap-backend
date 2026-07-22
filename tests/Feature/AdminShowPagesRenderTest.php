<?php

namespace Tests\Feature;

use App\Models\JobPost;
use App\Models\Sponsor;
use App\Models\User;
use App\Support\AdminAbility;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The jobs and sponsors "show" pages render without error.
 *
 * Both were reworked to the a2 design system — jobs/show rewritten off inline
 * styles onto a2 components, and a sponsors/show page created from scratch (the
 * resource had none). New blades are exactly where a bad class or a missing
 * relation blows up at render, so this just asserts a clean 200 with the key
 * chrome present.
 */
class AdminShowPagesRenderTest extends TestCase
{
    use DatabaseTransactions;

    private function contentAdmin(): User
    {
        $a = User::where('type', 'admin')->firstOrFail();
        foreach ([AdminAbility::ACCESS, AdminAbility::CONTENT] as $ab) {
            \Bouncer::allow($a)->to($ab);
        }
        \Bouncer::refresh();

        return $a;
    }

    public function test_the_job_show_page_renders(): void
    {
        $job = JobPost::query()->first();
        if (! $job) {
            $this->markTestSkipped('no job posts');
        }

        $this->actingAs($this->contentAdmin())
            ->get(route('admin.jobs.show', $job->id))
            ->assertOk()
            ->assertSee('a2-album-show-grid', false)   // the unified two-column layout
            ->assertSee('a2-kv', false);               // a2 key/value details
    }

    public function test_the_job_applicants_page_renders(): void
    {
        $job = JobPost::query()->first();
        if (! $job) {
            $this->markTestSkipped('no job posts');
        }

        $this->actingAs($this->contentAdmin())
            ->get(route('admin.jobs.applicants', $job->id))
            ->assertOk()
            ->assertSee('a2-page-head', false)   // unified header, not the inline-styled one
            ->assertSee('a2-table-wrap', false); // wrapped table
    }

    public function test_the_sponsor_show_page_renders(): void
    {
        $sponsor = Sponsor::query()->first();
        if (! $sponsor) {
            $this->markTestSkipped('no sponsors');
        }

        $this->actingAs($this->contentAdmin())
            ->get(route('admin.sponsors.show', $sponsor->id))
            ->assertOk()
            ->assertSee('a2-album-show-grid', false)
            ->assertSee('a2-pill', false);             // styled status pill, not the undefined a2-badge
    }
}
