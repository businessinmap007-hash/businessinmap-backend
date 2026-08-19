<?php

namespace Tests\Feature;

use App\Models\BusinessStaff;
use App\Models\User;
use App\Support\BusinessCapability;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The business-owner web panel for delegated staff: an owner grants, edits, and
 * revokes a staff member's capabilities. Scoped to the logged-in owner.
 */
class BusinessStaffPanelTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * تصنيفٌ يفعّل المنيو والحجز — «مطعم» #245 تحت «مطاعم وكافيهات».
     *
     * أُضيف 2026-08-19 حين صار سجلُّ الصلاحيات مقصورًا على ما يستطيع النشاط
     * فعله. كان النشاطُ هنا يُصنع بلا تصنيفٍ أصلًا، فصار «امنح موظفك المنيو»
     * اختبارًا على حسابٍ لا منيوَ له — وهو حالٌ لا توجد فى الواقع: التسجيل
     * يشترط `category_child_id` على مسار البزنس.
     */
    private const RESTAURANT_CHILD = 245;

    private const RESTAURANT_ROOT = 16;

    private function makeUser(string $type, string $tag): User
    {
        $u = new User();
        $u->name = $tag . ' ' . Str::random(4);
        $u->email = strtolower($tag) . '-' . uniqid() . '@example.test';
        $u->phone = '01' . random_int(100000000, 999999999);
        $u->password = 'secret-password';
        $u->type = $type;
        $u->api_token = Str::random(80);

        if ($type === User::TYPE_BUSINESS) {
            $u->category_id = self::RESTAURANT_ROOT;
            $u->category_child_id = self::RESTAURANT_CHILD;
        }

        $u->save();

        return $u;
    }

    public function test_the_panel_is_closed_to_non_business(): void
    {
        $client = $this->makeUser(User::TYPE_CLIENT, 'Client');

        $this->actingAs($client)->get('/business/staff')->assertRedirect(route('business.login'));
    }

    public function test_owner_grants_a_staff_member_by_phone(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Clinic');
        $secretary = $this->makeUser(User::TYPE_CLIENT, 'Secretary');

        $this->actingAs($owner)->get('/business/staff')->assertOk()->assertSee('الموظفون');

        $this->actingAs($owner)->post('/business/staff', [
            'phone' => $secretary->phone,
            'title' => 'سكرتيرة',
            'capabilities' => [BusinessCapability::WORKING_HOURS, BusinessCapability::ORDERS],
        ])->assertRedirect(route('business.staff.index'));

        $this->assertDatabaseHas('business_staff', [
            'business_id' => $owner->id,
            'user_id' => $secretary->id,
            'is_active' => 1,
        ]);

        $row = BusinessStaff::query()->where('business_id', $owner->id)->where('user_id', $secretary->id)->first();
        $this->assertEqualsCanonicalizing(
            [BusinessCapability::WORKING_HOURS, BusinessCapability::ORDERS],
            (array) $row->capabilities,
        );
    }

    public function test_an_unknown_phone_is_reported(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Shop');

        // A phone guaranteed to belong to no user.
        do {
            $absent = '0100' . random_int(1000000, 9999999);
        } while (User::query()->where('phone', $absent)->exists());

        $this->actingAs($owner)->post('/business/staff', [
            'phone' => $absent,
            'capabilities' => [BusinessCapability::ORDERS],
        ])->assertSessionHasErrors('phone');
    }

    public function test_owner_updates_and_revokes_a_member(): void
    {
        $owner = $this->makeUser(User::TYPE_BUSINESS, 'Rest');
        $staff = $this->makeUser(User::TYPE_CLIENT, 'Emp');
        BusinessStaff::create([
            'business_id' => $owner->id,
            'user_id' => $staff->id,
            'capabilities' => [BusinessCapability::ORDERS],
            'is_active' => true,
        ]);

        // Narrow to menu only, and deactivate.
        $this->actingAs($owner)->put("/business/staff/{$staff->id}", [
            'capabilities' => [BusinessCapability::MENU],
            'is_active' => 0,
        ])->assertRedirect(route('business.staff.index'));

        $row = BusinessStaff::query()->where('business_id', $owner->id)->where('user_id', $staff->id)->first();
        $this->assertSame([BusinessCapability::MENU], (array) $row->capabilities);
        $this->assertFalse((bool) $row->is_active);

        // Revoke.
        $this->actingAs($owner)->delete("/business/staff/{$staff->id}")
            ->assertRedirect(route('business.staff.index'));
        $this->assertDatabaseMissing('business_staff', ['business_id' => $owner->id, 'user_id' => $staff->id]);
    }
}
