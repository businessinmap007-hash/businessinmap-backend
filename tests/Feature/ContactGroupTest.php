<?php

namespace Tests\Feature;

use App\Models\ContactGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A user's own named contact groups — a reusable circle of already-
 * registered friends to invite to a shared cart at once. See
 * ContactGroupController + CustomerCartService::inviteGroupToShared
 * (tested in SharedCartTest).
 */
class ContactGroupTest extends TestCase
{
    use DatabaseTransactions;

    private User $owner;
    private User $friend;
    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();
        $customers = User::query()->where('type', '!=', 'business')->orderBy('id')->take(3)->get();
        if ($customers->count() < 3) {
            $this->markTestSkipped('Needs three non-business users.');
        }
        [$this->owner, $this->friend, $this->stranger] = $customers;
    }

    public function test_owner_can_create_a_group(): void
    {
        Sanctum::actingAs($this->owner);
        $this->postJson('/api/v2/contact-groups', ['name' => 'العائلة'])
            ->assertCreated()
            ->assertJsonPath('data.group.name', 'العائلة')
            ->assertJsonPath('data.group.members_count', 0);

        $this->assertDatabaseHas('contact_groups', ['user_id' => $this->owner->id, 'name' => 'العائلة']);
    }

    public function test_index_only_lists_the_caller_s_own_groups(): void
    {
        ContactGroup::query()->create(['user_id' => $this->owner->id, 'name' => 'العائلة']);
        ContactGroup::query()->create(['user_id' => $this->stranger->id, 'name' => 'مش بتاعي']);

        Sanctum::actingAs($this->owner);
        $res = $this->getJson('/api/v2/contact-groups')->assertOk();

        $names = collect($res->json('data.groups'))->pluck('name')->all();
        $this->assertSame(['العائلة'], $names);
    }

    public function test_owner_can_add_a_registered_friend_by_phone(): void
    {
        $group = ContactGroup::query()->create(['user_id' => $this->owner->id, 'name' => 'العائلة']);

        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v2/contact-groups/{$group->id}/members", ['identifier' => $this->friend->phone])
            ->assertCreated()
            ->assertJsonPath('data.group.members_count', 1)
            ->assertJsonPath('data.group.members.0.user_id', $this->friend->id);

        $this->assertDatabaseHas('contact_group_members', ['contact_group_id' => $group->id, 'user_id' => $this->friend->id]);
    }

    public function test_adding_an_unknown_identifier_fails(): void
    {
        $group = ContactGroup::query()->create(['user_id' => $this->owner->id, 'name' => 'العائلة']);

        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v2/contact-groups/{$group->id}/members", ['identifier' => 'nobody-like-this@example.test'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['identifier']);
    }

    public function test_owner_cannot_add_themself(): void
    {
        $group = ContactGroup::query()->create(['user_id' => $this->owner->id, 'name' => 'العائلة']);

        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v2/contact-groups/{$group->id}/members", ['identifier' => $this->owner->phone])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['identifier']);
    }

    public function test_adding_the_same_member_twice_fails(): void
    {
        $group = ContactGroup::query()->create(['user_id' => $this->owner->id, 'name' => 'العائلة']);
        $group->members()->create(['user_id' => $this->friend->id]);

        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v2/contact-groups/{$group->id}/members", ['identifier' => $this->friend->phone])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['identifier']);
    }

    public function test_owner_can_remove_a_member(): void
    {
        $group = ContactGroup::query()->create(['user_id' => $this->owner->id, 'name' => 'العائلة']);
        $member = $group->members()->create(['user_id' => $this->friend->id]);

        Sanctum::actingAs($this->owner);
        $this->deleteJson("/api/v2/contact-groups/{$group->id}/members/{$member->id}")
            ->assertOk()
            ->assertJsonPath('data.group.members_count', 0);

        $this->assertDatabaseMissing('contact_group_members', ['id' => $member->id]);
    }

    public function test_owner_can_rename_a_group(): void
    {
        $group = ContactGroup::query()->create(['user_id' => $this->owner->id, 'name' => 'العائلة']);

        Sanctum::actingAs($this->owner);
        $this->patchJson("/api/v2/contact-groups/{$group->id}", ['name' => 'أصدقاء دمياط'])
            ->assertOk()
            ->assertJsonPath('data.group.name', 'أصدقاء دمياط');
    }

    public function test_owner_can_delete_a_group_and_its_members(): void
    {
        $group = ContactGroup::query()->create(['user_id' => $this->owner->id, 'name' => 'العائلة']);
        $group->members()->create(['user_id' => $this->friend->id]);

        Sanctum::actingAs($this->owner);
        $this->deleteJson("/api/v2/contact-groups/{$group->id}")->assertOk();

        $this->assertDatabaseMissing('contact_groups', ['id' => $group->id]);
        $this->assertDatabaseMissing('contact_group_members', ['contact_group_id' => $group->id]);
    }

    public function test_a_stranger_cannot_see_read_or_modify_someone_else_s_group(): void
    {
        $group = ContactGroup::query()->create(['user_id' => $this->owner->id, 'name' => 'العائلة']);

        Sanctum::actingAs($this->stranger);
        $this->patchJson("/api/v2/contact-groups/{$group->id}", ['name' => 'اتسرقت'])->assertStatus(404);
        $this->deleteJson("/api/v2/contact-groups/{$group->id}")->assertStatus(404);
        $this->postJson("/api/v2/contact-groups/{$group->id}/members", ['identifier' => $this->friend->phone])->assertStatus(404);

        $this->assertDatabaseHas('contact_groups', ['id' => $group->id, 'name' => 'العائلة']);
    }
}
