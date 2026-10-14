<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Booking;
use App\Models\BusinessStaff;
use App\Models\ClinicAppointment;
use App\Models\User;
use App\Support\BusinessCapability;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «نظام التعيين» — المالك، 2026-10-19: who is responsible for a piece of work is a platform concept for EVERY
 * business, not a hospital one. A staff member the owner marks `is_provider` can be assigned a booking; he sees only
 * what is assigned to him; the customer reads who is responsible. A hospital physician is a provider with a medical role
 * and now hears about his own appointments (booked, moved, cancelled) like the clinic does.
 *
 * Rolls back.
 */
class AssignmentCoreTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $type, ?int $category = null, ?int $child = null, string $tag = 'U'): User
    {
        $u = new User();
        $u->forceFill([
            'name' => $tag . ' ' . Str::random(4), 'type' => $type, 'email' => strtolower($tag) . '-' . uniqid() . '@example.test',
            'phone' => '010' . random_int(10000000, 99999999), 'password' => 'secret-password', 'api_token' => Str::random(60),
            'category_id' => $category, 'category_child_id' => $child,
        ])->save();

        return $u;
    }

    /** @return array{0:User,1:User,2:User,3:User} salon, provider A, provider B, reception (plain staff) */
    private function salonWithStaff(): array
    {
        $salon = $this->user(User::TYPE_BUSINESS, 443, 554, 'Salon');
        $a = $this->user(User::TYPE_CLIENT, null, null, 'BarberA');
        $b = $this->user(User::TYPE_CLIENT, null, null, 'BarberB');
        $reception = $this->user(User::TYPE_CLIENT, null, null, 'Reception');

        Sanctum::actingAs($salon);
        foreach ([[$a, 'حلاق أول'], [$b, 'حلاق ثانٍ']] as [$barber, $title]) {
            $this->postJson('/api/v2/business/staff', [
                'user_id' => $barber->id, 'title' => $title, 'capabilities' => [BusinessCapability::BOOKINGS], 'is_provider' => true,
            ])->assertCreated()->assertJsonPath('data.staff.is_provider', true)->assertJsonPath('data.staff.is_physician', false);
        }
        $this->postJson('/api/v2/business/staff', [
            'user_id' => $reception->id, 'title' => 'استقبال', 'capabilities' => [BusinessCapability::BOOKINGS],
        ])->assertCreated()->assertJsonPath('data.staff.is_provider', false);

        foreach ([$a, $b, $reception] as $member) {
            Sanctum::actingAs($member);
            $this->postJson("/api/v2/staff/invitations/{$salon->id}/accept")->assertOk();
        }

        return [$salon, $a, $b, $reception];
    }

    private function booking(User $salon, User $customer): Booking
    {
        return Booking::query()->create([
            'user_id' => $customer->id, 'business_id' => $salon->id, 'status' => Booking::STATUS_PENDING,
            'date' => now()->addDay()->toDateString(), 'time' => '12:00:00',
        ]);
    }

    public function test_any_business_lists_its_providers_and_the_customer_sees_them(): void
    {
        [$salon, $a, $b, $reception] = $this->salonWithStaff();
        $customer = $this->user(User::TYPE_CLIENT, null, null, 'Customer');

        Sanctum::actingAs($salon);
        $own = collect($this->getJson('/api/v2/business/providers')->assertOk()->json('data.providers'));
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $own->pluck('id')->all());
        $this->assertSame('حلاق أول', $own->firstWhere('id', $a->id)['title']);

        // the customer reads name + title, never the staff row's other fields
        Sanctum::actingAs($customer);
        $public = collect($this->getJson("/api/v2/businesses/{$salon->id}/providers")->assertOk()->json('data.providers'));
        $this->assertCount(2, $public);
        $this->assertSame(['id', 'logo', 'name', 'title'], collect($public->first())->keys()->sort()->values()->all());
    }

    public function test_a_booking_is_assigned_told_and_listed_only_to_its_provider(): void
    {
        [$salon, $a, $b, $reception] = $this->salonWithStaff();
        $customer = $this->user(User::TYPE_CLIENT, null, null, 'Customer');
        $mine = $this->booking($salon, $customer);
        $his = $this->booking($salon, $customer);

        // the reception (a plain staff member) hands each booking to a barber
        Sanctum::actingAs($reception);
        $this->withHeader('X-Business-Id', (string) $salon->id);
        $this->putJson("/api/v2/business/bookings/{$mine->id}/assignee", ['assignee_id' => $a->id])
            ->assertOk()->assertJsonPath('data.assignee.id', $a->id)->assertJsonPath('data.assignee.title', 'حلاق أول');
        $this->putJson("/api/v2/business/bookings/{$his->id}/assignee", ['assignee_id' => $b->id])->assertOk();

        // he is told once, in his own inbox
        $this->assertSame(1, AppNotification::query()->where('user_id', $a->id)->where('title_ar', 'تم تعيينك على عمل جديد')->count());

        // a provider sees only his own list, and the booking carries who is responsible
        Sanctum::actingAs($a);
        $list = collect($this->withHeader('X-Business-Id', (string) $salon->id)->getJson('/api/v2/business/bookings')->assertOk()->json('data.bookings.data'));
        $this->assertSame([$mine->id], $list->pluck('id')->all());
        $this->assertSame($a->id, $list->first()['assignee']['id']);

        // …cannot act on a colleague's booking, and cannot hand work around
        $this->withHeader('X-Business-Id', (string) $salon->id)->postJson("/api/v2/business/bookings/{$his->id}/confirm-payment")->assertForbidden();
        $this->withHeader('X-Business-Id', (string) $salon->id)->putJson("/api/v2/business/bookings/{$mine->id}/assignee", ['assignee_id' => $b->id])->assertForbidden();

        // the owner still sees all, and may ask for one person's list
        Sanctum::actingAs($salon);
        $this->assertCount(2, $this->getJson('/api/v2/business/bookings')->json('data.bookings.data'));
        $this->assertSame([$his->id], collect($this->getJson("/api/v2/business/bookings?assignee_id={$b->id}")->json('data.bookings.data'))->pluck('id')->all());

        // the customer reads who is responsible
        Sanctum::actingAs($customer);
        $row = collect($this->getJson('/api/v2/bookings')->assertOk()->json('data.bookings.data') ?? $this->getJson('/api/v2/bookings')->json('data.bookings'))
            ->firstWhere('id', $mine->id);
        $this->assertSame($a->id, $row['assignee']['id'] ?? null);

        // taking it back
        Sanctum::actingAs($salon);
        $this->putJson("/api/v2/business/bookings/{$mine->id}/assignee", ['assignee_id' => null])->assertOk()->assertJsonPath('data.assignee', null);
    }

    public function test_only_a_provider_of_that_business_can_be_assigned(): void
    {
        [$salon, , , $reception] = $this->salonWithStaff();
        $customer = $this->user(User::TYPE_CLIENT, null, null, 'Customer');
        $stranger = $this->user(User::TYPE_CLIENT, null, null, 'Stranger');
        $booking = $this->booking($salon, $customer);

        Sanctum::actingAs($salon);
        // a staff member who is not marked a provider, and a stranger
        $this->putJson("/api/v2/business/bookings/{$booking->id}/assignee", ['assignee_id' => $reception->id])->assertStatus(422);
        $this->putJson("/api/v2/business/bookings/{$booking->id}/assignee", ['assignee_id' => $stranger->id])->assertStatus(422);

        // another business cannot assign this one's booking
        $other = $this->user(User::TYPE_BUSINESS, 443, 554, 'Other');
        Sanctum::actingAs($other);
        $this->putJson("/api/v2/business/bookings/{$booking->id}/assignee", ['assignee_id' => null])->assertForbidden();
    }

    public function test_a_hospital_physician_is_told_about_his_own_appointments(): void
    {
        $hospital = $this->user(User::TYPE_BUSINESS, 20, 513, 'Hospital');
        $doctor = $this->user(User::TYPE_CLIENT, null, null, 'Doctor');
        $patient = $this->user(User::TYPE_CLIENT, null, null, 'Patient');

        Sanctum::actingAs($hospital);
        $this->postJson('/api/v2/business/staff', [
            'user_id' => $doctor->id, 'title' => 'استشاري باطنة', 'capabilities' => [BusinessCapability::CLINIC], 'is_physician' => true,
        ])->assertCreated()->assertJsonPath('data.staff.is_provider', true);
        Sanctum::actingAs($doctor);
        $this->postJson("/api/v2/staff/invitations/{$hospital->id}/accept")->assertOk();

        // a physician is a provider: assignable work and listed for customers
        Sanctum::actingAs($patient);
        $this->assertSame([$doctor->id], collect($this->getJson("/api/v2/businesses/{$hospital->id}/providers")->json('data.providers'))->pluck('id')->all());

        $time = Carbon::today()->addDays(3)->setTimeFromTimeString('10:00')->format('Y-m-d H:i:s');
        Sanctum::actingAs($doctor);
        $this->withHeader('X-Business-Id', (string) $hospital->id)
            ->postJson('/api/v2/business/clinic-slots', ['starts_at' => $time, 'duration_minutes' => 30])->assertCreated();

        Sanctum::actingAs($patient);
        $slot = collect($this->getJson("/api/v2/clinics/{$hospital->id}/slots?physician_id={$doctor->id}")->assertOk()->json('data.data'))->first();
        $appointment = $this->postJson("/api/v2/clinic-slots/{$slot['id']}/book", [])->assertCreated()->json('data.appointment.id');

        $heard = fn (string $event) => AppNotification::query()->where('user_id', $doctor->id)->where('source_id', $appointment)->where('title_ar', $event)->count();
        $this->assertSame(1, $heard('حجز موعد'));

        // cancelled: the clinic AND the physician hear it (before, nobody did)
        $this->postJson("/api/v2/clinic-appointments/{$appointment}/cancel")->assertOk();
        $this->assertSame(1, $heard('إلغاء موعد'));
        $this->assertSame(1, AppNotification::query()->where('user_id', $hospital->id)->where('source_id', $appointment)->where('title_ar', 'إلغاء موعد')->count());
        $this->assertSame(ClinicAppointment::STATUS_CANCELLED, ClinicAppointment::query()->find($appointment)->status);
    }
}
