<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\BusinessStaff;
use App\Models\User;
use App\Support\BusinessCapability;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A staff grant used to take effect the instant the owner saved it. Now the
 * invited person must accept before it actually grants anything (
 * BusinessAccessService::resolveContext()'s status filter) - mirrors the
 * OperationGuarantor invite/accept/decline shape.
 */
class StaffInvitationAcceptanceTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(string $type, string $name): User
    {
        $u = new User();
        $u->name = $name;
        $u->email = 'u-'.uniqid().'@example.test';
        $u->phone = '01'.random_int(100000000, 999999999);
        $u->password = 'secret-password';
        $u->type = $type;
        $u->api_token = Str::random(80);
        $u->save();

        return $u;
    }

    public function test_a_fresh_grant_starts_pending_and_notifies_the_invited_person(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Shop-Inv1');
        $staffUser = $this->makeUser(User::TYPE_CLIENT, 'Employee-Inv1');

        $this->actingAs($owner, 'sanctum')->postJson('/api/v2/business/staff', [
            'phone' => $staffUser->phone, 'capabilities' => [BusinessCapability::ORDERS],
        ])->assertCreated()->assertJsonPath('data.staff.status', BusinessStaff::STATUS_PENDING);

        $this->assertDatabaseHas('business_staff', [
            'business_id' => $owner->id, 'user_id' => $staffUser->id, 'status' => BusinessStaff::STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $staffUser->id,
            'action_type' => 'open_staff_invitation',
            'notifiable_type' => User::class,
            'notifiable_id' => $owner->id,
        ]);
    }

    public function test_a_pending_grant_has_no_real_access_yet(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Shop-Inv2');
        $staffUser = $this->makeUser(User::TYPE_CLIENT, 'Employee-Inv2');

        $this->actingAs($owner, 'sanctum')->postJson('/api/v2/business/staff', [
            'phone' => $staffUser->phone, 'capabilities' => [BusinessCapability::ORDERS],
        ])->assertCreated();

        $this->actingAs($staffUser, 'sanctum')
            ->postJson('/api/v2/staff/attendance/check-in', ['business_id' => $owner->id])
            ->assertForbidden();
    }

    public function test_accepting_grants_real_access_and_notifies_the_owner(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Shop-Inv3');
        $staffUser = $this->makeUser(User::TYPE_CLIENT, 'Employee-Inv3');

        $this->actingAs($owner, 'sanctum')->postJson('/api/v2/business/staff', [
            'phone' => $staffUser->phone, 'capabilities' => [BusinessCapability::ORDERS],
        ])->assertCreated();

        $this->actingAs($staffUser, 'sanctum')
            ->postJson("/api/v2/staff/invitations/{$owner->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.staff.status', BusinessStaff::STATUS_ACCEPTED);

        $this->assertDatabaseHas('business_staff', [
            'business_id' => $owner->id, 'user_id' => $staffUser->id, 'status' => BusinessStaff::STATUS_ACCEPTED,
        ]);

        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $owner->id, 'actor_id' => $staffUser->id, 'source_type' => 'staff_accepted',
        ]);

        // Real access now: check-in succeeds.
        $this->actingAs($staffUser, 'sanctum')
            ->postJson('/api/v2/staff/attendance/check-in', ['business_id' => $owner->id])
            ->assertOk();
    }

    public function test_declining_leaves_the_grant_permanently_inactive(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Shop-Inv4');
        $staffUser = $this->makeUser(User::TYPE_CLIENT, 'Employee-Inv4');

        $this->actingAs($owner, 'sanctum')->postJson('/api/v2/business/staff', [
            'phone' => $staffUser->phone, 'capabilities' => [BusinessCapability::ORDERS],
        ])->assertCreated();

        $this->actingAs($staffUser, 'sanctum')
            ->postJson("/api/v2/staff/invitations/{$owner->id}/decline")
            ->assertOk()
            ->assertJsonPath('data.staff.status', BusinessStaff::STATUS_DECLINED);

        $this->actingAs($staffUser, 'sanctum')
            ->postJson('/api/v2/staff/attendance/check-in', ['business_id' => $owner->id])
            ->assertForbidden();
    }

    public function test_the_invited_person_can_list_their_pending_invitations(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Shop-Inv5');
        $staffUser = $this->makeUser(User::TYPE_CLIENT, 'Employee-Inv5');

        $this->actingAs($owner, 'sanctum')->postJson('/api/v2/business/staff', [
            'phone' => $staffUser->phone, 'capabilities' => [BusinessCapability::ORDERS],
        ])->assertCreated();

        $this->actingAs($staffUser, 'sanctum')
            ->getJson('/api/v2/staff/invitations')
            ->assertOk()
            ->assertJsonPath('data.invitations.0.business.id', $owner->id);
    }

    public function test_accepting_rewrites_the_original_invite_notification_in_place(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Shop-Inv7');
        $staffUser = $this->makeUser(User::TYPE_CLIENT, 'Employee-Inv7');

        $this->actingAs($owner, 'sanctum')->postJson('/api/v2/business/staff', [
            'phone' => $staffUser->phone, 'capabilities' => [BusinessCapability::ORDERS],
        ])->assertCreated();

        $invite = AppNotification::query()
            ->where('user_id', $staffUser->id)
            ->where('action_type', 'open_staff_invitation')
            ->firstOrFail();

        $this->actingAs($staffUser, 'sanctum')
            ->postJson("/api/v2/staff/invitations/{$owner->id}/accept")
            ->assertOk();

        $invite->refresh();
        $this->assertSame('open_business', $invite->action_type, 'a second tap must never re-open the accept/decline popup');
        $this->assertStringContainsString('قبول', $invite->title_ar);
    }

    public function test_declining_rewrites_the_original_invite_notification_too(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Shop-Inv8');
        $staffUser = $this->makeUser(User::TYPE_CLIENT, 'Employee-Inv8');

        $this->actingAs($owner, 'sanctum')->postJson('/api/v2/business/staff', [
            'phone' => $staffUser->phone, 'capabilities' => [BusinessCapability::ORDERS],
        ])->assertCreated();

        $invite = AppNotification::query()
            ->where('user_id', $staffUser->id)
            ->where('action_type', 'open_staff_invitation')
            ->firstOrFail();

        $this->actingAs($staffUser, 'sanctum')
            ->postJson("/api/v2/staff/invitations/{$owner->id}/decline")
            ->assertOk();

        $invite->refresh();
        $this->assertSame('open_business', $invite->action_type);
        $this->assertStringContainsString('رفض', $invite->title_ar);
    }

    public function test_editing_an_already_accepted_member_does_not_reset_their_status(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Shop-Inv6');
        $staffUser = $this->makeUser(User::TYPE_CLIENT, 'Employee-Inv6');

        $this->actingAs($owner, 'sanctum')->postJson('/api/v2/business/staff', [
            'phone' => $staffUser->phone, 'capabilities' => [BusinessCapability::ORDERS],
        ])->assertCreated();
        $this->actingAs($staffUser, 'sanctum')->postJson("/api/v2/staff/invitations/{$owner->id}/accept")->assertOk();

        $this->actingAs($owner, 'sanctum')->patchJson("/api/v2/business/staff/{$staffUser->id}", [
            'title' => 'Senior Waiter',
        ])->assertOk()->assertJsonPath('data.staff.status', BusinessStaff::STATUS_ACCEPTED);
    }
}
