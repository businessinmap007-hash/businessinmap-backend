<?php

namespace Tests\Feature;

use App\Models\BusinessStaff;
use App\Models\StaffAttendanceQrToken;
use App\Models\User;
use App\Support\BusinessCapability;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Opt-in GPS + rotating-QR verification on top of the plain self-service
 * check-in/check-out (StaffAttendanceController, StaffAttendanceQrService).
 * A business that never turns attendance_verification_enabled on keeps the
 * exact old behaviour - most of this file is about proving that boundary
 * holds, not just that the new checks work.
 */
class AttendanceQrVerificationTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(string $type, string $tag, ?float $lat = null, ?float $lng = null): User
    {
        $u = new User();
        $u->name = $tag . ' ' . Str::random(4);
        $u->email = strtolower($tag) . '-' . uniqid() . '@example.test';
        $u->phone = '01' . random_int(100000000, 999999999);
        $u->password = 'secret-password';
        $u->type = $type;
        $u->latitude = $lat;
        $u->longitude = $lng;
        $u->api_token = Str::random(80);
        $u->save();

        return $u;
    }

    private function hireWaiter(User $owner, User $waiter): void
    {
        BusinessStaff::create([
            'business_id' => $owner->id,
            'user_id' => $waiter->id,
            'capabilities' => [BusinessCapability::ORDERS],
            'is_active' => true,
        ]);
    }

    public function test_check_in_is_unaffected_when_verification_is_off(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'ShopOff', 30.0, 31.0);
        $waiter = $this->makeUser(User::TYPE_CLIENT, 'WaiterOff');
        $this->hireWaiter($owner, $waiter);

        $this->actingAs($waiter, 'sanctum')
            ->postJson('/api/v2/staff/attendance/check-in', ['business_id' => $owner->id])
            ->assertOk()
            ->assertJsonPath('data.is_present', true);
    }

    public function test_check_in_is_refused_without_a_qr_token_or_location_when_verification_is_on(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'ShopOn', 30.0, 31.0);
        $owner->attendance_verification_enabled = true;
        $owner->save();
        $waiter = $this->makeUser(User::TYPE_CLIENT, 'WaiterOn');
        $this->hireWaiter($owner, $waiter);

        $this->actingAs($waiter, 'sanctum')
            ->postJson('/api/v2/staff/attendance/check-in', ['business_id' => $owner->id])
            ->assertStatus(422);
    }

    public function test_the_owner_can_fetch_a_current_qr_code_and_it_stays_stable_until_used(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'ShopQr', 30.0, 31.0);

        $first = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v2/business/staff/attendance-qr')
            ->assertOk()
            ->json('data.token');

        $second = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v2/business/staff/attendance-qr')
            ->assertOk()
            ->json('data.token');

        $this->assertSame($first, $second, 'the display should not get a new code on every poll, only once the old one is gone');
    }

    public function test_a_staff_member_cannot_fetch_the_display_code(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'ShopQr2');
        $waiter = $this->makeUser(User::TYPE_CLIENT, 'WaiterQr2');
        $this->hireWaiter($owner, $waiter);

        $this->actingAs($waiter, 'sanctum')
            ->getJson('/api/v2/business/staff/attendance-qr')
            ->assertForbidden();
    }

    public function test_check_in_with_a_valid_code_and_matching_location_succeeds(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'ShopGood', 30.0, 31.0);
        $owner->attendance_verification_enabled = true;
        $owner->save();
        $waiter = $this->makeUser(User::TYPE_CLIENT, 'WaiterGood');
        $this->hireWaiter($owner, $waiter);

        $token = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v2/business/staff/attendance-qr')
            ->json('data.token');

        $this->actingAs($waiter, 'sanctum')
            ->postJson('/api/v2/staff/attendance/check-in', [
                'business_id' => $owner->id,
                'qr_token' => $token,
                'lat' => 30.0001,
                'lng' => 31.0001,
            ])
            ->assertOk()
            ->assertJsonPath('data.is_present', true);
    }

    public function test_the_same_code_cannot_be_scanned_twice(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'ShopReuse', 30.0, 31.0);
        $owner->attendance_verification_enabled = true;
        $owner->save();
        $waiter = $this->makeUser(User::TYPE_CLIENT, 'WaiterReuse');
        $intruder = $this->makeUser(User::TYPE_CLIENT, 'IntruderReuse');
        $this->hireWaiter($owner, $waiter);
        $this->hireWaiter($owner, $intruder);

        $token = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v2/business/staff/attendance-qr')
            ->json('data.token');

        $this->actingAs($waiter, 'sanctum')
            ->postJson('/api/v2/staff/attendance/check-in', [
                'business_id' => $owner->id, 'qr_token' => $token, 'lat' => 30.0, 'lng' => 31.0,
            ])
            ->assertOk();

        // Someone photographs the already-scanned screen and tries it later.
        $this->actingAs($intruder, 'sanctum')
            ->postJson('/api/v2/staff/attendance/check-in', [
                'business_id' => $owner->id, 'qr_token' => $token, 'lat' => 30.0, 'lng' => 31.0,
            ])
            ->assertStatus(422);
    }

    public function test_a_far_away_location_is_refused_even_with_a_valid_code(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'ShopFar', 30.0, 31.0);
        $owner->attendance_verification_enabled = true;
        $owner->save();
        $waiter = $this->makeUser(User::TYPE_CLIENT, 'WaiterFar');
        $this->hireWaiter($owner, $waiter);

        $token = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v2/business/staff/attendance-qr')
            ->json('data.token');

        $this->actingAs($waiter, 'sanctum')
            ->postJson('/api/v2/staff/attendance/check-in', [
                'business_id' => $owner->id, 'qr_token' => $token, 'lat' => 31.2, 'lng' => 29.9,
            ])
            ->assertStatus(422);

        $this->assertNull(StaffAttendanceQrToken::where('token', $token)->first()->used_at);
    }

    public function test_verification_is_skipped_when_the_business_never_set_a_location(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'ShopNoLoc', null, null);
        $owner->attendance_verification_enabled = true;
        $owner->save();
        $waiter = $this->makeUser(User::TYPE_CLIENT, 'WaiterNoLoc');
        $this->hireWaiter($owner, $waiter);

        $token = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v2/business/staff/attendance-qr')
            ->json('data.token');

        $this->actingAs($waiter, 'sanctum')
            ->postJson('/api/v2/staff/attendance/check-in', [
                'business_id' => $owner->id, 'qr_token' => $token, 'lat' => 0.0, 'lng' => 0.0,
            ])
            ->assertOk();
    }

    public function test_a_code_from_another_business_is_refused(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'ShopMine', 30.0, 31.0);
        $owner->attendance_verification_enabled = true;
        $owner->save();
        $otherOwner = $this->makeUser(User::TYPE_BUSINESS, 'ShopOther', 30.0, 31.0);
        $waiter = $this->makeUser(User::TYPE_CLIENT, 'WaiterMine');
        $this->hireWaiter($owner, $waiter);

        $foreignToken = $this->actingAs($otherOwner, 'sanctum')
            ->getJson('/api/v2/business/staff/attendance-qr')
            ->json('data.token');

        $this->actingAs($waiter, 'sanctum')
            ->postJson('/api/v2/staff/attendance/check-in', [
                'business_id' => $owner->id, 'qr_token' => $foreignToken, 'lat' => 30.0, 'lng' => 31.0,
            ])
            ->assertStatus(422);
    }

    public function test_only_the_owner_can_toggle_verification(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'ShopToggle');
        $waiter = $this->makeUser(User::TYPE_CLIENT, 'WaiterToggle');
        $this->hireWaiter($owner, $waiter);

        $this->actingAs($waiter, 'sanctum')
            ->patchJson('/api/v2/business/staff/attendance-settings', ['enabled' => true])
            ->assertForbidden();

        $this->actingAs($owner, 'sanctum')
            ->patchJson('/api/v2/business/staff/attendance-settings', ['enabled' => true])
            ->assertOk()
            ->assertJsonPath('data.enabled', true);

        $this->assertTrue((bool) $owner->fresh()->attendance_verification_enabled);
    }

    public function test_the_owner_can_read_back_the_current_setting(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'ShopRead');
        $waiter = $this->makeUser(User::TYPE_CLIENT, 'WaiterRead');
        $this->hireWaiter($owner, $waiter);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/v2/business/staff/attendance-settings')
            ->assertOk()
            ->assertJsonPath('data.enabled', false);

        $this->actingAs($waiter, 'sanctum')
            ->getJson('/api/v2/business/staff/attendance-settings')
            ->assertForbidden();
    }

    public function test_memberships_reports_whether_verification_is_required(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'ShopReports');
        $owner->attendance_verification_enabled = true;
        $owner->save();
        $waiter = $this->makeUser(User::TYPE_CLIENT, 'WaiterReports');
        $this->hireWaiter($owner, $waiter);

        $rows = $this->actingAs($waiter, 'sanctum')
            ->getJson('/api/v2/business/memberships')
            ->assertOk()
            ->json('data.memberships');

        $mine = collect($rows)->firstWhere('business.id', $owner->id);
        $this->assertTrue($mine['attendance_verification_enabled']);
    }
}
