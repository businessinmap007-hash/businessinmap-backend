<?php

namespace Tests\Feature;

use App\Models\JobPost;
use App\Models\Sponsor;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The admin "create" screens, which AdminV2ScreensSmokeTest found returning
 * 500. Rendering is only half of it — a form that loads but cannot save is
 * still broken, and that is exactly what had happened to sponsors: the shared
 * _form partial never rendered the `type` field its validator required, so
 * every save 422'd and no test ever posted to it.
 */
class AdminV2CreateScreensTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): User
    {
        return User::query()->where('type', 'admin')->orderBy('id')->first()
            ?: $this->markTestSkipped('Needs an admin user.');
    }

    private function business(): User
    {
        return User::query()->where('type', 'business')->orderBy('id')->first()
            ?: $this->markTestSkipped('Needs a business user.');
    }

    // ───────────────────────────── jobs ─────────────────────────────

    public function test_the_job_create_form_renders(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/jobs/create')
            ->assertOk()
            ->assertSee(__('إضافة وظيفة'), false);
    }

    public function test_creating_a_job_stores_it_active_and_owned(): void
    {
        $business = $this->business();

        $this->actingAs($this->admin())
            ->post('/admin/jobs', [
                'user_id' => $business->id,
                'title' => 'وظيفة من لوحة الإدارة',
                'body' => 'الوصف',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $job = JobPost::query()->where('title', 'وظيفة من لوحة الإدارة')->first();

        $this->assertNotNull($job, 'the job must be stored');
        $this->assertSame((int) $business->id, (int) $job->user_id, 'a job with no owner is an orphan');
        $this->assertTrue((bool) $job->is_active, 'the form posts is_active, so it must not save inactive');
        $this->assertSame('job', $job->type, 'the JobPost creating hook stamps the type');
    }

    /**
     * validateData coerces a missing is_active to false. The form ships a hidden
     * "0" before the checkbox, so an unchecked box is a deliberate inactive.
     */
    public function test_an_unchecked_active_box_stores_an_inactive_job(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/jobs', [
                'user_id' => $this->business()->id,
                'title' => 'وظيفة موقوفة',
                'is_active' => '0',
            ])
            ->assertRedirect();

        $job = JobPost::query()->where('title', 'وظيفة موقوفة')->first();

        $this->assertNotNull($job);
        $this->assertFalse((bool) $job->is_active);
    }

    public function test_a_job_without_a_title_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/jobs', ['body' => 'بلا عنوان'])
            ->assertSessionHasErrors('title');
    }

    // ─────────────────────────── sponsors ───────────────────────────

    public function test_the_sponsor_create_form_renders_with_the_type_field(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/sponsors/create')
            ->assertOk()
            // The field whose absence made every save fail validation.
            ->assertSee('name="type"', false);
    }

    public function test_creating_a_sponsor_stores_it(): void
    {
        // create(), not image(): fake()->image() needs the GD extension, which
        // is not installed here. An explicit mime satisfies the `image` rule.
        $this->actingAs($this->admin())
            ->post('/admin/sponsors', [
                'type' => 'free',
                'price' => '100',
                'image' => UploadedFile::fake()->create('banner.jpg', 10, 'image/jpeg'),
            ])
            ->assertRedirect(route('admin.sponsors.index'));

        $sponsor = Sponsor::query()->latest('id')->first();

        $this->assertNotNull($sponsor);
        $this->assertSame('free', $sponsor->type);
        $this->assertNotEmpty($sponsor->image, 'the image column is NOT NULL');

        // The controller moves the upload into public/ — that write is outside
        // the transaction this test rolls back, so remove it explicitly.
        $written = public_path($sponsor->image);
        if (is_file($written)) {
            @unlink($written);
        }
    }

    public function test_a_sponsor_without_an_image_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/sponsors', ['type' => 'free'])
            ->assertSessionHasErrors('image');
    }

    /** The `type` fix applies to edit too — that form was equally unsubmittable. */
    public function test_updating_a_sponsor_round_trips_the_type(): void
    {
        $sponsor = Sponsor::query()->create([
            'type' => 'free',
            'image' => 'files/uploads/seed.jpg',
        ]);

        $this->actingAs($this->admin())
            ->put("/admin/sponsors/{$sponsor->id}", ['type' => 'paid', 'price' => '250'])
            ->assertRedirect(route('admin.sponsors.index'));

        $this->assertSame('paid', $sponsor->fresh()->type);
    }

    // ────────────────────────── categories ──────────────────────────

    public function test_the_category_create_form_renders(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin/categories/create')
            ->assertOk()
            ->assertSee(__('إضافة قسم رئيسي'), false);
    }

    // ──────────────────────────── posts ─────────────────────────────

    /**
     * Posts are authored by users in the app; PostController implements neither
     * create nor store, so both routes are gone rather than left 500ing.
     */
    public function test_the_post_create_and_store_routes_do_not_exist(): void
    {
        $names = collect(Route::getRoutes())->map(fn ($r) => $r->getName())->filter()->all();

        $this->assertNotContains('admin.posts.create', $names);
        $this->assertNotContains('admin.posts.store', $names);
    }
}
