<?php

namespace Tests\Feature;

use App\Models\BusinessGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A business's own named groups of OTHER businesses — a reusable target
 * list for wholesale/retail offers, so naming the same circle of buyers
 * repeatedly doesn't mean searching for each one every time. Mirrors
 * ContactGroupTest; members are added by business id (no phone/email
 * lookup — see BusinessGroupController).
 */
class BusinessGroupTest extends TestCase
{
    use DatabaseTransactions;

    private User $owner;
    private User $targetA;
    private User $targetB;
    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();
        $businesses = User::query()->where('type', 'business')->orderBy('id')->take(4)->get();
        if ($businesses->count() < 4) {
            $this->markTestSkipped('Needs four business users.');
        }
        [$this->owner, $this->targetA, $this->targetB, $this->stranger] = $businesses;
    }

    public function test_owner_can_create_a_group(): void
    {
        Sanctum::actingAs($this->owner);
        $this->postJson('/api/v2/business-groups', ['name' => 'محلات الخضار'])
            ->assertCreated()
            ->assertJsonPath('data.group.name', 'محلات الخضار')
            ->assertJsonPath('data.group.members_count', 0);

        $this->assertDatabaseHas('business_groups', ['user_id' => $this->owner->id, 'name' => 'محلات الخضار']);
    }

    public function test_index_only_lists_the_caller_s_own_groups(): void
    {
        BusinessGroup::query()->create(['user_id' => $this->owner->id, 'name' => 'محلاتي']);
        BusinessGroup::query()->create(['user_id' => $this->stranger->id, 'name' => 'مش بتاعي']);

        Sanctum::actingAs($this->owner);
        $res = $this->getJson('/api/v2/business-groups')->assertOk();

        $names = collect($res->json('data.groups'))->pluck('name')->all();
        $this->assertSame(['محلاتي'], $names);
    }

    public function test_owner_can_add_a_business_by_id(): void
    {
        $group = BusinessGroup::query()->create(['user_id' => $this->owner->id, 'name' => 'محلاتي']);

        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v2/business-groups/{$group->id}/members", ['business_id' => $this->targetA->id])
            ->assertCreated()
            ->assertJsonPath('data.group.members_count', 1)
            ->assertJsonPath('data.group.members.0.business_id', $this->targetA->id);

        $this->assertDatabaseHas('business_group_members', ['business_group_id' => $group->id, 'business_id' => $this->targetA->id]);
    }

    public function test_adding_a_non_business_or_unknown_id_fails(): void
    {
        $customer = User::query()->where('type', '!=', 'business')->value('id');
        if (! $customer) {
            $this->markTestSkipped('Needs a non-business user.');
        }

        $group = BusinessGroup::query()->create(['user_id' => $this->owner->id, 'name' => 'محلاتي']);

        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v2/business-groups/{$group->id}/members", ['business_id' => $customer])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['business_id']);
    }

    public function test_owner_cannot_add_their_own_business(): void
    {
        $group = BusinessGroup::query()->create(['user_id' => $this->owner->id, 'name' => 'محلاتي']);

        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v2/business-groups/{$group->id}/members", ['business_id' => $this->owner->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['business_id']);
    }

    public function test_adding_the_same_member_twice_fails(): void
    {
        $group = BusinessGroup::query()->create(['user_id' => $this->owner->id, 'name' => 'محلاتي']);
        $group->members()->create(['business_id' => $this->targetA->id]);

        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v2/business-groups/{$group->id}/members", ['business_id' => $this->targetA->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['business_id']);
    }

    public function test_owner_can_remove_a_member(): void
    {
        $group = BusinessGroup::query()->create(['user_id' => $this->owner->id, 'name' => 'محلاتي']);
        $member = $group->members()->create(['business_id' => $this->targetA->id]);

        Sanctum::actingAs($this->owner);
        $this->deleteJson("/api/v2/business-groups/{$group->id}/members/{$member->id}")
            ->assertOk()
            ->assertJsonPath('data.group.members_count', 0);

        $this->assertDatabaseMissing('business_group_members', ['id' => $member->id]);
    }

    public function test_owner_can_rename_a_group(): void
    {
        $group = BusinessGroup::query()->create(['user_id' => $this->owner->id, 'name' => 'محلاتي']);

        Sanctum::actingAs($this->owner);
        $this->patchJson("/api/v2/business-groups/{$group->id}", ['name' => 'مصانع الأثاث'])
            ->assertOk()
            ->assertJsonPath('data.group.name', 'مصانع الأثاث');
    }

    public function test_owner_can_delete_a_group_and_its_members(): void
    {
        $group = BusinessGroup::query()->create(['user_id' => $this->owner->id, 'name' => 'محلاتي']);
        $group->members()->create(['business_id' => $this->targetA->id]);

        Sanctum::actingAs($this->owner);
        $this->deleteJson("/api/v2/business-groups/{$group->id}")->assertOk();

        $this->assertDatabaseMissing('business_groups', ['id' => $group->id]);
        $this->assertDatabaseMissing('business_group_members', ['business_group_id' => $group->id]);
    }

    public function test_a_stranger_cannot_see_read_or_modify_someone_else_s_group(): void
    {
        $group = BusinessGroup::query()->create(['user_id' => $this->owner->id, 'name' => 'محلاتي']);

        Sanctum::actingAs($this->stranger);
        $this->patchJson("/api/v2/business-groups/{$group->id}", ['name' => 'اتسرقت'])->assertStatus(404);
        $this->deleteJson("/api/v2/business-groups/{$group->id}")->assertStatus(404);
        $this->postJson("/api/v2/business-groups/{$group->id}/members", ['business_id' => $this->targetB->id])->assertStatus(404);

        $this->assertDatabaseHas('business_groups', ['id' => $group->id, 'name' => 'محلاتي']);
    }
}
