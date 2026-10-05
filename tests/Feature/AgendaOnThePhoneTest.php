<?php

namespace Tests\Feature;

use App\Models\AgendaItem;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «الأجندا تُحفظ على الفون» — المالك، 2026-10-05. A personal task's title and notes live on the owner's phone; the
 * server keeps only WHEN, so two things are still never booked into the same minute. Rolls back.
 */
class AgendaOnThePhoneTest extends TestCase
{
    use DatabaseTransactions;

    private User $me;

    protected function setUp(): void
    {
        parent::setUp();
        $u = new User();
        $u->name = 'Me ' . Str::random(4);
        $u->email = 'me-' . uniqid() . '@example.test';
        $u->phone = '01' . random_int(100000000, 999999999);
        $u->password = 'secret-password';
        $u->type = User::TYPE_CLIENT;
        $u->api_token = Str::random(80);
        $u->save();
        $this->me = $u;
        Sanctum::actingAs($u);
    }

    private function at(int $days, int $hour): string
    {
        return Carbon::today()->addDays($days)->setTime($hour, 0)->toIso8601String();
    }

    public function test_a_private_task_sends_only_its_time_and_the_server_never_sees_the_title(): void
    {
        $item = $this->postJson('/api/v2/agenda', [
            'private' => true, 'starts_at' => $this->at(2, 10), 'ends_at' => $this->at(2, 11), 'remind' => true,
            'title' => 'موعد مع المحامي', 'notes' => 'ملف القضية',
        ])->assertCreated()->json('data.item');

        $this->assertSame(AgendaItem::PRIVATE_TITLE, $item['title']);
        $this->assertNull($item['notes']);
        $this->assertTrue($item['private']);

        $row = AgendaItem::query()->findOrFail($item['id']);
        $this->assertSame(AgendaItem::PRIVATE_TITLE, $row->title, 'even a title sent by mistake is not kept');
        $this->assertNull($row->notes);
        $this->assertTrue((bool) $row->blocking);
        $this->assertTrue((bool) $row->remind);
    }

    public function test_the_title_is_not_required_when_it_stays_on_the_phone_but_is_otherwise(): void
    {
        $this->postJson('/api/v2/agenda', ['private' => true, 'starts_at' => $this->at(2, 10)])->assertCreated();
        $this->postJson('/api/v2/agenda', ['starts_at' => $this->at(3, 10)])->assertUnprocessable();
        $whole = $this->postJson('/api/v2/agenda', ['title' => 'مهمة كاملة', 'starts_at' => $this->at(3, 10)])->assertCreated()->json('data.item');
        $this->assertSame('مهمة كاملة', $whole['title'], 'the old way still works');
        $this->assertFalse($whole['private']);
    }

    public function test_a_private_task_still_blocks_the_time(): void
    {
        $this->postJson('/api/v2/agenda', ['private' => true, 'starts_at' => $this->at(2, 10), 'ends_at' => $this->at(2, 11)])->assertCreated();

        $this->postJson('/api/v2/agenda', ['private' => true, 'starts_at' => $this->at(2, 10)])->assertUnprocessable();
    }

    public function test_a_repeating_private_task_returns_the_created_ids(): void
    {
        $result = $this->postJson('/api/v2/agenda/recurring', [
            'private' => true, 'start_time' => '08:00', 'duration_minutes' => 30, 'frequency' => 'daily', 'weeks' => 1,
        ])->assertCreated()->json('data');

        $this->assertGreaterThan(0, $result['created']);
        $this->assertCount($result['created'], $result['ids']);
        foreach ($result['ids'] as $id) {
            $row = AgendaItem::query()->findOrFail($id);
            $this->assertSame(AgendaItem::PRIVATE_TITLE, $row->title);
            $this->assertNull($row->notes);
        }
    }

    public function test_scrub_drops_the_title_and_notes_of_my_old_personal_tasks_and_nothing_else(): void
    {
        $old = $this->postJson('/api/v2/agenda', ['title' => 'سر', 'notes' => 'تفاصيل', 'starts_at' => $this->at(2, 10)])->assertCreated()->json('data.item.id');
        $appointment = AgendaItem::create(['user_id' => $this->me->id, 'kind' => AgendaItem::KIND_APPOINTMENT, 'title' => 'عيادة', 'starts_at' => now()->addDays(4), 'blocking' => true, 'status' => 'active']);

        $other = new User();
        $other->name = 'Other';
        $other->email = 'o-' . uniqid() . '@example.test';
        $other->phone = '01' . random_int(100000000, 999999999);
        $other->password = 'secret-password';
        $other->type = User::TYPE_CLIENT;
        $other->api_token = Str::random(80);
        $other->save();
        $theirs = AgendaItem::create(['user_id' => $other->id, 'kind' => AgendaItem::KIND_PERSONAL, 'title' => 'ليست لي', 'notes' => 'x', 'starts_at' => now()->addDays(5), 'blocking' => true, 'status' => 'active']);

        $this->postJson('/api/v2/agenda/scrub', ['ids' => [$old, $appointment->id, $theirs->id]])->assertOk()->assertJsonPath('data.scrubbed', 1);

        $this->assertSame(AgendaItem::PRIVATE_TITLE, AgendaItem::query()->findOrFail($old)->title);
        $this->assertNull(AgendaItem::query()->findOrFail($old)->notes);
        $this->assertSame('عيادة', $appointment->fresh()->title, 'a mirrored commitment keeps its title');
        $this->assertSame('ليست لي', $theirs->fresh()->title, 'someone else task is untouched');
        // again: nothing left to drop, and no error
        $this->postJson('/api/v2/agenda/scrub', ['ids' => [$old]])->assertOk()->assertJsonPath('data.scrubbed', 0);
    }

    public function test_upcoming_lists_only_what_asks_to_be_reminded_of(): void
    {
        $with = $this->postJson('/api/v2/agenda', ['private' => true, 'starts_at' => $this->at(2, 10), 'remind' => true])->assertCreated()->json('data.item.id');
        $without = $this->postJson('/api/v2/agenda', ['private' => true, 'starts_at' => $this->at(3, 10)])->assertCreated()->json('data.item.id');
        $far = $this->postJson('/api/v2/agenda', ['private' => true, 'starts_at' => $this->at(40, 10), 'remind' => true])->assertCreated()->json('data.item.id');

        $ids = collect($this->getJson('/api/v2/agenda/upcoming')->assertOk()->json('data.items'))->pluck('id');

        $this->assertTrue($ids->contains($with));
        $this->assertFalse($ids->contains($without), 'no reminder asked');
        $this->assertFalse($ids->contains($far), 'beyond the window');
        $this->assertTrue(collect($this->getJson('/api/v2/agenda/upcoming?days=60')->json('data.items'))->pluck('id')->contains($far));
    }
}
