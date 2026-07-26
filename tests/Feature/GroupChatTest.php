<?php

namespace Tests\Feature;

use App\Models\Thread;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Group chat: a titled, owned conversation with more than two members, on the
 * same subjectless-thread + member-seat machinery as the 1-to-1 DM. The owner
 * adds members; any member may leave; an empty group is deleted.
 */
class GroupChatTest extends TestCase
{
    use DatabaseTransactions;

    private User $owner;
    private User $bob;
    private User $carol;
    private User $dave;

    protected function setUp(): void
    {
        parent::setUp();

        $users = User::query()->orderBy('id')->limit(4)->get();
        if ($users->count() < 4) {
            $this->markTestSkipped('Needs at least four users.');
        }
        [$this->owner, $this->bob, $this->carol, $this->dave] = [$users[0], $users[1], $users[2], $users[3]];
    }

    private function createGroup(array $memberIds = null): int
    {
        Sanctum::actingAs($this->owner);

        return (int) $this->postJson('/api/v2/chats/group', [
            'title' => 'Team',
            'user_ids' => $memberIds ?? [$this->bob->id, $this->carol->id],
        ])->assertCreated()->json('data.id');
    }

    public function test_creating_a_group_seats_everyone_and_names_it(): void
    {
        $id = $this->createGroup();

        $thread = Thread::with('participants')->findOrFail($id);
        $this->assertTrue($thread->isGroup());
        $this->assertSame('Team', $thread->title);
        $this->assertSame((int) $this->owner->id, (int) $thread->created_by);
        $this->assertSame(3, $thread->participants->count());
    }

    public function test_a_group_needs_at_least_one_other_member(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/v2/chats/group', ['title' => 'Solo', 'user_ids' => [$this->owner->id]])
            ->assertStatus(422);
    }

    public function test_any_member_can_post_and_read_a_non_member_cannot(): void
    {
        $id = $this->createGroup();

        Sanctum::actingAs($this->bob);
        $this->postJson("/api/v2/chats/{$id}/messages", ['body' => 'hi team'])->assertCreated();

        Sanctum::actingAs($this->carol);
        $this->getJson("/api/v2/chats/{$id}")->assertOk()->assertJsonPath('data.0.is_mine', false);

        Sanctum::actingAs($this->dave); // not a member
        $this->getJson("/api/v2/chats/{$id}")->assertNotFound();
        $this->postJson("/api/v2/chats/{$id}/messages", ['body' => 'let me in'])->assertNotFound();
    }

    public function test_only_the_owner_can_add_a_member(): void
    {
        $id = $this->createGroup();

        // A non-owner member cannot add.
        Sanctum::actingAs($this->bob);
        $this->postJson("/api/v2/chats/{$id}/members", ['user_id' => $this->dave->id])->assertStatus(422);

        // The owner can.
        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v2/chats/{$id}/members", ['user_id' => $this->dave->id])->assertCreated();

        Sanctum::actingAs($this->dave);
        $this->getJson("/api/v2/chats/{$id}")->assertOk();
    }

    public function test_a_member_can_leave_and_then_cannot_read(): void
    {
        $id = $this->createGroup();

        Sanctum::actingAs($this->bob);
        $this->postJson("/api/v2/chats/{$id}/leave")->assertOk();
        $this->getJson("/api/v2/chats/{$id}")->assertNotFound();

        // The group still exists for the others.
        $this->assertDatabaseHas('threads', ['id' => $id]);
    }

    public function test_the_last_member_leaving_deletes_the_group(): void
    {
        // A group of just owner + bob.
        $id = $this->createGroup([$this->bob->id]);

        Sanctum::actingAs($this->bob);
        $this->postJson("/api/v2/chats/{$id}/leave")->assertOk();

        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v2/chats/{$id}/leave")->assertOk();

        $this->assertDatabaseMissing('threads', ['id' => $id]);
    }

    public function test_a_dm_is_not_a_group_and_cannot_be_left(): void
    {
        Sanctum::actingAs($this->owner);
        $dmId = $this->postJson('/api/v2/chats', ['user_id' => $this->bob->id])->json('data.id');

        $this->postJson("/api/v2/chats/{$dmId}/leave")->assertStatus(422);
    }

    public function test_owner_can_rename_the_group_others_cannot(): void
    {
        $id = $this->createGroup();

        Sanctum::actingAs($this->bob);
        $this->patchJson("/api/v2/chats/{$id}", ['title' => 'Hijack'])->assertStatus(422);

        Sanctum::actingAs($this->owner);
        $this->patchJson("/api/v2/chats/{$id}", ['title' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Renamed');
    }

    public function test_owner_removes_a_member_but_not_the_owner(): void
    {
        $id = $this->createGroup();

        Sanctum::actingAs($this->owner);
        // Cannot remove the owner (self / creator).
        $this->deleteJson("/api/v2/chats/{$id}/members/{$this->owner->id}")->assertStatus(422);

        // Remove bob → he loses access.
        $this->deleteJson("/api/v2/chats/{$id}/members/{$this->bob->id}")->assertOk();
        Sanctum::actingAs($this->bob);
        $this->getJson("/api/v2/chats/{$id}")->assertNotFound();
    }

    public function test_a_non_owner_cannot_remove_a_member(): void
    {
        $id = $this->createGroup();

        Sanctum::actingAs($this->bob);
        $this->deleteJson("/api/v2/chats/{$id}/members/{$this->carol->id}")->assertStatus(422);
        $this->assertDatabaseHas('threads', ['id' => $id]);
    }

    public function test_owner_deletes_the_whole_group(): void
    {
        $id = $this->createGroup();

        Sanctum::actingAs($this->bob);
        $this->deleteJson("/api/v2/chats/{$id}")->assertStatus(422); // not the owner

        Sanctum::actingAs($this->owner);
        $this->deleteJson("/api/v2/chats/{$id}")->assertOk();
        $this->assertDatabaseMissing('threads', ['id' => $id]);
    }
}
