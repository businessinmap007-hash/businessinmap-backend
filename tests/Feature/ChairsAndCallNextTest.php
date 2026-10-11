<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Booking;
use App\Models\BusinessServicePrice;
use App\Models\ServiceChair;
use App\Models\User;
use App\Services\Media\ImageUploadService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «كرسي وعليه الحلاق» and «نداء الدور التالي»: a salon with several chairs (each its own line), the customer choosing
 * the person or taking whoever is free, the business calling the next turn, and a customer with no booking added by
 * the staff.
 */
class ChairsAndCallNextTest extends TestCase
{
    use DatabaseTransactions;

    private User $salon;

    private BusinessServicePrice $service;

    private Carbon $day;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salon = $this->account(User::TYPE_BUSINESS, 'Salon', 23, 51);
        $this->service = BusinessServicePrice::create([
            'business_id' => $this->salon->id, 'service_id' => 1, 'child_id' => 51,
            'bookable_item_type' => 'booking_appointment', 'price' => 150, 'currency' => 'EGP',
            'is_active' => 1, 'duration_minutes' => 45,
        ]);

        $this->day = now()->addDays(2)->startOfDay();
        DB::table('business_working_hours')->where('business_id', $this->salon->id)->delete();
        // open all week so a walk-in "today" is judged too
        foreach (range(0, 6) as $d) {
            DB::table('business_working_hours')->insert([
                'business_id' => $this->salon->id, 'day_of_week' => $d, 'is_closed' => 0,
                'open_time' => '00:00:00', 'close_time' => '23:59:00', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('business_working_hours')->where('business_id', $this->salon->id)->where('day_of_week', $this->day->dayOfWeek)
            ->update(['open_time' => '10:00:00', 'close_time' => '13:00:00']);
    }

    private function account(string $type, string $tag, ?int $category = null, ?int $child = null): User
    {
        return User::create([
            'name' => $tag . ' ' . Str::random(4),
            'email' => strtolower($tag) . uniqid() . '@example.test',
            'password' => bcrypt('Test1234'),
            'type' => $type,
            'category_id' => $category,
            'category_child_id' => $child,
            'api_token' => Str::random(60),
            'phone' => '010' . random_int(10000000, 99999999),
        ]);
    }

    private function chair(string $person, int $order): ServiceChair
    {
        return ServiceChair::create(['business_id' => $this->salon->id, 'name' => 'كرسي ' . $order, 'person_name' => $person, 'sort_order' => $order, 'is_active' => true]);
    }

    private function queueOn(): void
    {
        Sanctum::actingAs($this->salon);
        $this->putJson('/api/v2/business/booking-settings/flow', ['flow' => 'queue'])->assertOk();
    }

    private function take(User $customer, ?int $chairId = null)
    {
        Sanctum::actingAs($customer);

        return $this->postJson('/api/v2/bookings/queue', array_filter([
            'business_id' => $this->salon->id, 'service_id' => 1, 'offering_id' => $this->service->id,
            'offering_type' => 'service_price', 'date' => $this->day->toDateString(), 'chair_id' => $chairId,
        ]));
    }

    public function test_the_business_manages_its_chairs_and_the_customer_sees_the_active_ones(): void
    {
        Sanctum::actingAs($this->salon);

        $this->postJson('/api/v2/business/chairs', [])->assertStatus(422);

        $photo = UploadedFile::fake()->createWithContent('barber.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        ));
        $response = $this->post('/api/v2/business/chairs', ['name' => 'كرسي 1', 'person_name' => 'أحمد', 'photo' => $photo], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.chair.person_name', 'أحمد');
        $one = (int) $response->json('data.chair.id');
        $path = $response->json('data.chair.photo');
        $this->assertNotEmpty($path);

        $two = (int) $this->postJson('/api/v2/business/chairs', ['person_name' => 'محمود'])->assertCreated()->json('data.chair.id');

        $this->getJson("/api/v2/discovery/appointments/{$this->salon->id}/chairs")
            ->assertOk()->assertJsonCount(2, 'data.chairs');

        // a chair is closed, and then it is no longer offered
        $this->post("/api/v2/business/chairs/{$two}", ['is_active' => 0], ['Accept' => 'application/json'])->assertOk();
        $this->getJson("/api/v2/discovery/appointments/{$this->salon->id}/chairs")->assertOk()->assertJsonCount(1, 'data.chairs');

        app(ImageUploadService::class)->delete($path);
        $this->assertSame($one, (int) ServiceChair::query()->where('business_id', $this->salon->id)->where('person_name', 'أحمد')->value('id'));
    }

    public function test_a_staff_account_sits_at_a_chair_with_its_own_name_and_photo(): void
    {
        $barber = $this->account(User::TYPE_CLIENT, 'Barber', null, null);
        $barber->forceFill(['name' => 'أحمد الحلاق', 'logo' => 'files/uploads/ahmed.png'])->save();
        $stranger = $this->account(User::TYPE_CLIENT, 'Stranger');

        \App\Models\BusinessStaff::create([
            'business_id' => $this->salon->id, 'user_id' => $barber->id, 'title' => 'حلاق',
            'capabilities' => ['bookings'], 'is_active' => true, 'status' => \App\Models\BusinessStaff::STATUS_ACCEPTED,
        ]);

        Sanctum::actingAs($this->salon);

        // the staff the business may seat
        $this->getJson('/api/v2/business/chairs/staff')->assertOk()
            ->assertJsonPath('data.staff.0.user_id', $barber->id)
            ->assertJsonPath('data.staff.0.name', 'أحمد الحلاق');

        // only one of its own staff
        $this->postJson('/api/v2/business/chairs', ['name' => 'كرسي 1', 'staff_user_id' => $stranger->id])->assertStatus(422);

        $this->postJson('/api/v2/business/chairs', ['staff_user_id' => $barber->id])->assertCreated()
            ->assertJsonPath('data.chair.staff_user_id', $barber->id);

        // the customer sees the account's own name and photo
        $this->getJson("/api/v2/discovery/appointments/{$this->salon->id}/chairs")->assertOk()
            ->assertJsonPath('data.chairs.0.person_name', 'أحمد الحلاق')
            ->assertJsonPath('data.chairs.0.photo', 'files/uploads/ahmed.png');

        // a name written on the chair wins over the account's
        $id = ServiceChair::query()->where('staff_user_id', $barber->id)->value('id');
        $this->post("/api/v2/business/chairs/{$id}", ['person_name' => 'أبو علي'], ['Accept' => 'application/json'])->assertOk();
        $this->getJson("/api/v2/discovery/appointments/{$this->salon->id}/chairs")->assertOk()
            ->assertJsonPath('data.chairs.0.person_name', 'أبو علي');
    }

    public function test_each_chair_is_its_own_line_and_no_preference_takes_the_one_free_soonest(): void
    {
        $one = $this->chair('أحمد', 1);
        $two = $this->chair('محمود', 2);
        $this->queueOn();

        // the customer who wants the first barber gets his first time
        $a = $this->take($this->account(User::TYPE_CLIENT, 'A'), $one->id)->assertCreated();
        $this->assertSame($one->id, $a->json('data.booking.chair_id'));
        $this->assertSame('10:00', substr($a->json('data.booking.starts_at'), 11, 5));

        // no preference: the first chair is busy at 10:00, the second is free then
        $b = $this->take($this->account(User::TYPE_CLIENT, 'B'))->assertCreated();
        $this->assertSame($two->id, $b->json('data.booking.chair_id'));
        $this->assertSame('10:00', substr($b->json('data.booking.starts_at'), 11, 5));

        // both busy until 10:45: the lower chair takes the next, and the ticket number runs on across chairs
        $c = $this->take($this->account(User::TYPE_CLIENT, 'C'))->assertCreated();
        $this->assertSame($one->id, $c->json('data.booking.chair_id'));
        $this->assertSame('10:45', substr($c->json('data.booking.starts_at'), 11, 5));
        $this->assertSame(3, $c->json('data.booking.queue_turn'));
        $this->assertSame('أحمد', $c->json('data.booking.chair.person_name'));

        // the customer who wants the second barber waits behind that barber's own line
        $d = $this->take($this->account(User::TYPE_CLIENT, 'D'), $two->id)->assertCreated();
        $this->assertSame('10:45', substr($d->json('data.booking.starts_at'), 11, 5));
    }

    public function test_a_chair_is_never_booked_twice_at_one_time(): void
    {
        $one = $this->chair('أحمد', 1);
        $two = $this->chair('محمود', 2);
        $at = $this->day->copy()->setTime(11, 0);

        $book = function (User $who, ?int $chair = null) use ($at) {
            Sanctum::actingAs($who);

            return $this->postJson('/api/v2/bookings', array_filter([
                'business_id' => $this->salon->id, 'service_id' => 1, 'offering_id' => $this->service->id, 'offering_type' => 'service_price',
                'starts_at' => $at->toIso8601String(), 'ends_at' => $at->copy()->addMinutes(45)->toIso8601String(), 'chair_id' => $chair,
            ]));
        };

        $first = $book($this->account(User::TYPE_CLIENT, 'A'), $one->id)->assertCreated();
        $this->assertSame($one->id, $first->json('data.booking.chair_id'));

        // the same chair at the same time is refused
        $book($this->account(User::TYPE_CLIENT, 'B'), $one->id)->assertStatus(422);

        // no preference gets the other chair
        $third = $book($this->account(User::TYPE_CLIENT, 'C'))->assertCreated();
        $this->assertSame($two->id, $third->json('data.booking.chair_id'));

        // and with both taken there is nothing left
        $book($this->account(User::TYPE_CLIENT, 'D'))->assertStatus(422);
    }

    public function test_the_business_calls_the_next_turn_and_the_customer_is_told(): void
    {
        $this->chair('أحمد', 1);
        $this->queueOn();
        $customer = $this->account(User::TYPE_CLIENT, 'A');
        $id = $this->take($customer)->assertCreated()->json('data.booking.id');

        Sanctum::actingAs($this->salon);
        $this->postJson("/api/v2/business/queue/{$id}/call")->assertOk();

        $booking = Booking::query()->findOrFail($id);
        $this->assertNotNull($booking->called_at);
        $this->assertSame(Booking::STATUS_ACCEPTED, $booking->status, 'a call accepts a booking still waiting');
        $this->assertTrue(AppNotification::query()->where('user_id', $customer->id)->where('title_ar', 'حان دورك')->exists());

        // another business cannot call it
        $other = $this->account(User::TYPE_BUSINESS, 'Other', 23, 51);
        Sanctum::actingAs($other);
        $this->postJson("/api/v2/business/queue/{$id}/call")->assertForbidden();
    }

    /** A barber account: accepted staff of the salon with NO capability at all, sitting at the given chair. */
    private function barberAt(ServiceChair $chair, array $capabilities = []): User
    {
        $barber = $this->account(User::TYPE_CLIENT, 'Barber');
        \App\Models\BusinessStaff::create([
            'business_id' => $this->salon->id, 'user_id' => $barber->id, 'title' => 'حلاق',
            'capabilities' => $capabilities, 'is_active' => true, 'status' => \App\Models\BusinessStaff::STATUS_ACCEPTED,
        ]);
        $chair->forceFill(['staff_user_id' => $barber->id])->save();

        return $barber;
    }

    public function test_a_barber_runs_his_own_chair_and_only_that_chair(): void
    {
        $ahmed = $this->chair('أحمد', 1);
        $mahmoud = $this->chair('محمود', 2);
        $this->queueOn();

        $mine = $this->take($this->account(User::TYPE_CLIENT, 'A'), $ahmed->id)->assertCreated()->json('data.booking.id');
        $theirs = $this->take($this->account(User::TYPE_CLIENT, 'B'), $mahmoud->id)->assertCreated()->json('data.booking.id');

        $barber = $this->barberAt($ahmed); // no capability: the chair is the grant
        Sanctum::actingAs($barber);

        // his line is his chair's, and nothing of the other chair
        $queue = $this->getJson('/api/v2/business/queue?business_id=' . $this->salon->id . '&date=' . $this->day->toDateString())
            ->assertOk();
        $this->assertSame([$ahmed->id], array_column($queue->json('data.chairs'), 'id'));
        $this->assertSame([$mine], array_column($queue->json('data.items'), 'id'));

        // he calls his own customer
        $this->postJson("/api/v2/business/queue/{$mine}/call?business_id={$this->salon->id}")->assertOk();

        // but not the customer of another chair
        $this->postJson("/api/v2/business/queue/{$theirs}/call?business_id={$this->salon->id}")->assertForbidden();
        $this->postJson("/api/v2/business/queue/{$theirs}/start?business_id={$this->salon->id}")->assertForbidden();
        $this->assertNull(Booking::query()->findOrFail($theirs)->called_at);

        // the day's count is his chair's own
        $this->assertSame(0, $this->getJson('/api/v2/business/queue?business_id=' . $this->salon->id . '&date=' . $this->day->toDateString())->json('data.served'));

        // the house's booking list is a `bookings` capability: a chair alone does not open it …
        $this->getJson('/api/v2/business/bookings?business_id=' . $this->salon->id)->assertForbidden();

        // … and with the capability it is still cut to his chair
        \App\Models\BusinessStaff::query()->where('user_id', $barber->id)->update(['capabilities' => ['bookings']]);
        $listed = array_column($this->getJson('/api/v2/business/bookings?business_id=' . $this->salon->id)->assertOk()->json('data.bookings.data'), 'id');
        $this->assertSame([$mine], $listed);
    }

    public function test_a_barbers_walk_in_lands_on_his_chair(): void
    {
        $ahmed = $this->chair('أحمد', 1);
        $mahmoud = $this->chair('محمود', 2);
        $barber = $this->barberAt($mahmoud);

        Sanctum::actingAs($barber);
        $row = $this->postJson('/api/v2/business/queue/walk-in?business_id=' . $this->salon->id, [
            'name' => 'عم سعيد', 'offering_id' => $this->service->id,
        ])->assertCreated();
        $this->assertSame($mahmoud->id, $row->json('data.chair_id'));

        // he serves him from start to finish — and the day's count follows
        $id = $row->json('data.id');
        $this->postJson("/api/v2/business/queue/{$id}/start?business_id={$this->salon->id}")->assertOk();
        $this->postJson("/api/v2/business/queue/{$id}/finish?business_id={$this->salon->id}")->assertOk();
        $this->assertSame(1, $this->getJson('/api/v2/business/queue?business_id=' . $this->salon->id)->json('data.served'));

        // naming another chair is refused
        $this->postJson('/api/v2/business/queue/walk-in?business_id=' . $this->salon->id, [
            'name' => 'x', 'offering_id' => $this->service->id, 'chair_id' => $ahmed->id,
        ])->assertForbidden();
    }

    public function test_the_barbers_memberships_name_his_chair_and_a_stranger_with_no_chair_needs_the_capability(): void
    {
        $ahmed = $this->chair('أحمد', 1);
        $barber = $this->barberAt($ahmed);

        Sanctum::actingAs($barber);
        $this->getJson('/api/v2/business/memberships')->assertOk()
            ->assertJsonPath('data.memberships.0.business.id', $this->salon->id)
            ->assertJsonPath('data.memberships.0.chairs.0.id', $ahmed->id)
            ->assertJsonPath('data.memberships.0.chairs.0.person_name', 'أحمد');

        // staff with no chair and no `bookings` capability cannot open the line
        $clerk = $this->account(User::TYPE_CLIENT, 'Clerk');
        \App\Models\BusinessStaff::create([
            'business_id' => $this->salon->id, 'user_id' => $clerk->id, 'title' => 'سكرتير',
            'capabilities' => [], 'is_active' => true, 'status' => \App\Models\BusinessStaff::STATUS_ACCEPTED,
        ]);
        Sanctum::actingAs($clerk);
        $this->getJson('/api/v2/business/queue?business_id=' . $this->salon->id)->assertForbidden();

        // …and with it, sees the whole line (no chair restricts him)
        \App\Models\BusinessStaff::query()->where('user_id', $clerk->id)->update(['capabilities' => ['bookings']]);
        $this->getJson('/api/v2/business/queue?business_id=' . $this->salon->id)->assertOk();

        // an owner asking for «mine» sits at no chair
        Sanctum::actingAs($this->salon);
        $this->getJson('/api/v2/business/queue?mine=1')->assertOk()->assertJsonCount(0, 'data.chairs')->assertJsonCount(0, 'data.items');
    }

    public function test_the_staff_add_a_customer_with_no_booking_and_he_moves_through_the_line(): void
    {
        $this->chair('أحمد', 1);
        Sanctum::actingAs($this->salon);

        $created = $this->postJson('/api/v2/business/queue/walk-in', [
            'name' => 'عم سعيد', 'phone' => '0100', 'offering_id' => $this->service->id,
        ])->assertCreated();
        $id = $created->json('data.id');
        $this->assertSame(1, $created->json('data.turn'));

        $row = $this->getJson('/api/v2/business/queue')->assertOk()->json('data.items.0');
        $this->assertTrue($row['walk_in']);
        $this->assertSame('عم سعيد', $row['customer']['name']);
        $this->assertSame('accepted', $row['status']);

        // never mistaken for a customer account
        $this->assertSame($this->salon->id, (int) Booking::query()->findOrFail($id)->user_id);

        $this->postJson("/api/v2/business/queue/{$id}/finish")->assertStatus(422); // not started yet
        $this->postJson("/api/v2/business/queue/{$id}/start")->assertOk()->assertJsonPath('data.status', 'in_progress');
        $this->postJson("/api/v2/business/queue/{$id}/finish")->assertOk()->assertJsonPath('data.status', 'completed');

        // the next walk-in takes the next number
        $second = $this->postJson('/api/v2/business/queue/walk-in', ['name' => 'سيدة', 'offering_id' => $this->service->id])->assertCreated();
        $this->assertSame(2, $second->json('data.turn'));

        // a service that is not this business's is refused
        $this->postJson('/api/v2/business/queue/walk-in', ['name' => 'x', 'offering_id' => 999999999])->assertStatus(422);
    }
}
