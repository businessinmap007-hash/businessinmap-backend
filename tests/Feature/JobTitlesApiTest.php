<?php

namespace Tests\Feature;

use App\Models\JobTitle;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The closed job-title list: a business picks «طباخ» / «ويتر» from the list of
 * its own field instead of typing a title, so search, follow and reporting can
 * key on one handle. Rolls back; titles are created here, never assumed.
 */
class JobTitlesApiTest extends TestCase
{
    use DatabaseTransactions;

    private const ROOT = 16;   // مطاعم وكافيهات

    private const CHILD = 245; // مطعم

    private const OTHER_ROOT = 23; // مصانع

    private function business(): User
    {
        return User::query()->forceCreate([
            'name' => 'Test Business '.uniqid(),
            'phone' => '01'.random_int(100000000, 999999999),
            'email' => 'biz'.uniqid().'@test.local',
            'password' => Hash::make('secret123'),
            'api_token' => \Illuminate\Support\Str::random(60),
            'type' => 'business',
        ]);
    }

    private function title(string $ar, ?int $root = null, ?int $child = null, bool $active = true): JobTitle
    {
        return JobTitle::create([
            'category_id' => $root, 'category_child_id' => $child,
            'name_ar' => $ar, 'is_active' => $active,
        ]);
    }

    public function test_titles_are_scoped_to_the_field_plus_root_plus_general(): void
    {
        $mine = $this->title('طباخ '.uniqid(), null, self::CHILD);
        $rootWide = $this->title('مشرف '.uniqid(), self::ROOT);
        $general = $this->title('محاسب '.uniqid());
        $foreign = $this->title('لحام '.uniqid(), null, 8);
        $inactive = $this->title('مخفي '.uniqid(), null, self::CHILD, false);

        $ids = collect($this->getJson('/api/v2/jobs/titles?category_id='.self::ROOT.'&category_child_id='.self::CHILD)
            ->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertTrue($ids->contains($rootWide->id));
        $this->assertTrue($ids->contains($general->id));
        $this->assertFalse($ids->contains($foreign->id), 'another trade\'s title must not leak in');
        $this->assertFalse($ids->contains($inactive->id), 'a retired title is not offered');
    }

    public function test_a_job_can_be_posted_from_a_title_and_filtered_by_it(): void
    {
        $cook = $this->title('طباخ '.uniqid(), null, self::CHILD);
        $waiter = $this->title('ويتر '.uniqid(), null, self::CHILD);
        $business = $this->business();

        $this->actingAs($business, 'sanctum')->postJson('/api/v2/jobs', [
            'category_id' => self::ROOT, 'category_child_id' => self::CHILD,
            'job_title_id' => $cook->id, 'body' => 'مطلوب طباخ',
        ])->assertCreated()
            ->assertJsonPath('data.title', $cook->name_ar)
            ->assertJsonPath('data.job_title.id', $cook->id);

        $this->actingAs($business, 'sanctum')->postJson('/api/v2/jobs', [
            'category_id' => self::ROOT, 'category_child_id' => self::CHILD,
            'job_title_id' => $waiter->id, 'title' => 'ويتر صالة', 'body' => 'مطلوب ويتر',
        ])->assertCreated()->assertJsonPath('data.title', 'ويتر صالة');

        $cooks = $this->getJson('/api/v2/jobs?job_title_id='.$cook->id)->assertOk()->json('data.data');
        $this->assertCount(1, $cooks);
        $this->assertSame($cook->id, $cooks[0]['job_title']['id']);

        $count = collect($this->getJson('/api/v2/jobs/titles?category_child_id='.self::CHILD)->json('data'))
            ->firstWhere('id', $cook->id)['jobs_count'];
        $this->assertSame(1, $count, 'the title carries its open-job count');
    }

    public function test_a_title_from_another_field_is_refused(): void
    {
        $welder = $this->title('لحام '.uniqid(), self::OTHER_ROOT);

        $this->actingAs($this->business(), 'sanctum')->postJson('/api/v2/jobs', [
            'category_id' => self::ROOT, 'category_child_id' => self::CHILD,
            'job_title_id' => $welder->id, 'body' => 'x',
        ])->assertStatus(422);
    }

    public function test_a_title_or_a_free_text_title_is_required(): void
    {
        $this->actingAs($this->business(), 'sanctum')->postJson('/api/v2/jobs', [
            'category_id' => self::ROOT, 'body' => 'x',
        ])->assertStatus(422)->assertJsonValidationErrors(['title']);
    }

    public function test_a_free_text_title_still_works_without_a_pick(): void
    {
        $this->actingAs($this->business(), 'sanctum')->postJson('/api/v2/jobs', [
            'category_id' => self::ROOT, 'title' => 'وظيفة حرة', 'body' => 'x',
        ])->assertCreated()->assertJsonPath('data.job_title', null);
    }

    public function test_the_admin_can_list_add_edit_and_delete_titles(): void
    {
        $admin = User::query()->where('type', 'admin')->first();

        if (! $admin) {
            $this->markTestSkipped('No admin account to act as.');
        }

        $name = 'مسمى اختبار '.uniqid();

        $this->actingAs($admin)->post(route('admin.job-titles.store'), [
            'scope' => 'c:'.self::CHILD, 'name_ar' => $name,
        ])->assertRedirect();

        $title = JobTitle::query()->where('name_ar', $name)->firstOrFail();
        $this->assertSame(self::CHILD, (int) $title->category_child_id);

        $this->actingAs($admin)->get(route('admin.job-titles.index', ['q' => $name]))
            ->assertOk()->assertSee($name, false);

        $this->actingAs($admin)->put(route('admin.job-titles.update', $title), [
            'name_ar' => $name.' 2', 'sort_order' => 5,
        ])->assertRedirect();
        $this->assertFalse((bool) $title->fresh()->is_active, 'an unchecked box retires the title');
        $this->assertSame($name.' 2', $title->fresh()->name_ar);

        $this->actingAs($admin)->delete(route('admin.job-titles.destroy', $title))->assertRedirect();
        $this->assertNull(JobTitle::find($title->id));
    }
}
