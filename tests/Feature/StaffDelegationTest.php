<?php

namespace Tests\Feature;

use App\Models\BusinessStaff;
use App\Models\User;
use App\Support\BusinessCapability;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Delegated staff: a business grants an employee a set of capabilities from the
 * one shared services registry, and the employee then acts AS the business on
 * the surfaces they were granted — nothing more.
 */
class StaffDelegationTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(string $type, string $tag): User
    {
        $u = new User();
        $u->name = $tag . ' ' . Str::random(4);
        $u->email = strtolower($tag) . '-' . uniqid() . '@example.test';
        $u->phone = '01' . random_int(100000000, 999999999);
        $u->password = 'secret-password';
        $u->type = $type;
        $u->api_token = Str::random(80);
        $u->save();

        return $u;
    }

    public function test_the_capabilities_catalog_is_the_single_services_list(): void
    {
        Sanctum::actingAs($this->makeUser(User::TYPE_BUSINESS, 'Shop'));

        $keys = collect($this->getJson('/api/v2/business/capabilities')->assertOk()->json('data.capabilities'))
            ->pluck('key')->all();

        $this->assertContains(BusinessCapability::WORKING_HOURS, $keys);
        $this->assertContains(BusinessCapability::ORDERS, $keys);
    }

    public function test_owner_grants_staff_who_then_acts_as_the_business(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Clinic');
        $secretary = $this->makeUser(User::TYPE_CLIENT, 'Secretary');

        // Owner grants the secretary the working-hours capability.
        Sanctum::actingAs($owner);
        $this->postJson('/api/v2/business/staff', [
            'user_id' => $secretary->id,
            'title' => 'سكرتيرة',
            'capabilities' => [BusinessCapability::WORKING_HOURS],
        ])->assertCreated()->assertJsonPath('data.staff.capabilities', [BusinessCapability::WORKING_HOURS]);

        // The secretary sees the membership and sets the CLINIC's hours (single
        // membership ⇒ the acting business is inferred, no header needed).
        Sanctum::actingAs($secretary);
        $this->getJson('/api/v2/business/memberships')
            ->assertOk()
            ->assertJsonPath('data.memberships.0.business.id', (int) $owner->id);

        $this->putJson('/api/v2/business/working-hours', [
            'bulk' => ['all' => true, 'open' => '09:00', 'close' => '17:00'],
        ])->assertOk();

        // The hours landed on the EMPLOYER's account, not the secretary's.
        $this->assertDatabaseHas('business_working_hours', ['business_id' => (int) $owner->id]);
        $this->assertDatabaseMissing('business_working_hours', ['business_id' => (int) $secretary->id]);

        // And the owner reads back what the secretary set.
        Sanctum::actingAs($owner);
        $days = collect($this->getJson('/api/v2/business/working-hours')->json('data.days'))->keyBy('day');
        $this->assertSame('09:00', $days[1]['open']);
    }

    public function test_staff_are_limited_to_their_granted_capabilities(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest');
        $waiter = $this->makeUser(User::TYPE_CLIENT, 'Waiter');

        // Granted ORDERS only — not working hours.
        BusinessStaff::create([
            'business_id' => $owner->id,
            'user_id' => $waiter->id,
            'capabilities' => [BusinessCapability::ORDERS],
            'is_active' => true,
        ]);

        Sanctum::actingAs($waiter);

        // Can reach the orders surface for the employer…
        $this->getJson('/api/v2/business/orders')->assertOk();

        // …but not the working-hours surface (lacks the capability).
        $this->putJson('/api/v2/business/working-hours', [
            'bulk' => ['all' => true, 'open' => '09:00', 'close' => '17:00'],
        ])->assertForbidden();
    }

    public function test_a_menu_delegate_creates_a_section_for_the_employer(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest');
        $cashier = $this->makeUser(User::TYPE_CLIENT, 'Cashier');

        BusinessStaff::create([
            'business_id' => $owner->id,
            'user_id' => $cashier->id,
            'capabilities' => [BusinessCapability::MENU],
            'is_active' => true,
        ]);

        Sanctum::actingAs($cashier);
        $this->postJson('/api/v2/business/menu/sections', ['name_ar' => 'مشروبات'])
            ->assertSuccessful();

        // The section belongs to the EMPLOYER, created by the delegate.
        $this->assertDatabaseHas('menu_sections', [
            'business_id' => (int) $owner->id,
            'name_ar' => 'مشروبات',
        ]);
        $this->assertDatabaseMissing('menu_sections', ['business_id' => (int) $cashier->id]);

        // But the same delegate lacks `prices`, so pricing is closed to them.
        $this->getJson('/api/v2/business/prices')->assertForbidden();
    }

    public function test_a_stranger_cannot_act_for_a_business(): void
    {
        $this->makeUser(User::TYPE_BUSINESS, 'Other');
        $stranger = $this->makeUser(User::TYPE_CLIENT, 'Stranger');

        Sanctum::actingAs($stranger);
        $this->getJson('/api/v2/business/orders')->assertForbidden();
    }

    public function test_a_deactivated_staff_member_loses_access(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Store');
        $staff = $this->makeUser(User::TYPE_CLIENT, 'Emp');

        BusinessStaff::create([
            'business_id' => $owner->id,
            'user_id' => $staff->id,
            'capabilities' => [BusinessCapability::ORDERS],
            'is_active' => false,
        ]);

        Sanctum::actingAs($staff);
        $this->getJson('/api/v2/business/orders')->assertForbidden();
    }

    public function test_only_the_owner_manages_staff(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Owner');
        $staff = $this->makeUser(User::TYPE_CLIENT, 'Emp');
        BusinessStaff::create([
            'business_id' => $owner->id,
            'user_id' => $staff->id,
            'capabilities' => [BusinessCapability::ORDERS],
            'is_active' => true,
        ]);

        // A staff member (a client) cannot manage the roster.
        Sanctum::actingAs($staff);
        $this->getJson('/api/v2/business/staff')->assertForbidden();
        $this->postJson('/api/v2/business/staff', [
            'user_id' => $staff->id,
            'capabilities' => [BusinessCapability::MENU],
        ])->assertForbidden();
    }
}
