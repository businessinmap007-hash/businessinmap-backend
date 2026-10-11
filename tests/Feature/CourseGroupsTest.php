<?php

namespace Tests\Feature;

use App\Models\BusinessServicePrice;
use App\Models\CourseGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * «كورس»: a course is a priced row; a group is one run of it with its own start, schedule and seats; the booking a
 * customer makes is the enrolment and takes a seat.
 */
class CourseGroupsTest extends TestCase
{
    use DatabaseTransactions;

    private User $academy;

    private BusinessServicePrice $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->academy = $this->account(User::TYPE_BUSINESS, 'Academy', 12, 86);
        $this->course = BusinessServicePrice::create([
            'business_id' => $this->academy->id, 'service_id' => 1, 'child_id' => 86,
            'bookable_item_type' => 'book_a_course', 'price' => 1600, 'currency' => 'EGP', 'is_active' => 1,
        ]);
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

    private function open(array $extra = []): int
    {
        Sanctum::actingAs($this->academy);

        return (int) $this->postJson('/api/v2/business/course-groups', $extra + [
            'offering_id' => $this->course->id,
            'name' => 'مجموعة المساء',
            'level' => 'متوسط',
            'schedule_text' => 'الأحد والثلاثاء · 7 م',
            'starts_on' => now()->addDays(7)->toDateString(),
            'ends_on' => now()->addDays(63)->toDateString(),
            'seats' => 2,
        ])->assertCreated()->json('data.group.id');
    }

    private function enrol(User $student, int $groupId)
    {
        Sanctum::actingAs($student);

        return $this->postJson('/api/v2/bookings', [
            'business_id' => $this->academy->id,
            'service_id' => 1,
            'offering_id' => $this->course->id,
            'offering_type' => 'service_price',
            'starts_at' => now()->addDays(7)->startOfDay()->toIso8601String(),
            'ends_at' => now()->addDays(63)->startOfDay()->toIso8601String(),
            'all_day' => true,
            'course_group_id' => $groupId,
        ]);
    }

    public function test_the_business_opens_a_group_and_the_customer_discovers_it_with_its_seats(): void
    {
        $group = $this->open();

        $this->getJson("/api/v2/discovery/courses/{$this->academy->id}")
            ->assertOk()
            ->assertJsonPath('data.courses.0.id', $this->course->id)
            ->assertJsonPath('data.courses.0.groups.0.id', $group)
            ->assertJsonPath('data.courses.0.groups.0.seats_left', 2)
            ->assertJsonPath('data.courses.0.groups.0.level', 'متوسط');

        // a course of another business is refused
        $other = $this->account(User::TYPE_BUSINESS, 'Other', 12, 86);
        $foreign = BusinessServicePrice::create([
            'business_id' => $other->id, 'service_id' => 1, 'child_id' => 86,
            'bookable_item_type' => 'book_a_course', 'price' => 900, 'currency' => 'EGP', 'is_active' => 1,
        ]);
        Sanctum::actingAs($this->academy);
        $this->postJson('/api/v2/business/course-groups', [
            'offering_id' => $foreign->id, 'name' => 'x', 'starts_on' => now()->addDay()->toDateString(), 'seats' => 3,
        ])->assertStatus(422);
    }

    public function test_enrolling_takes_a_seat_at_the_courses_whole_price_and_a_full_group_refuses(): void
    {
        $group = $this->open();
        $a = $this->account(User::TYPE_CLIENT, 'StudentA');
        $b = $this->account(User::TYPE_CLIENT, 'StudentB');
        $c = $this->account(User::TYPE_CLIENT, 'StudentC');

        $first = $this->enrol($a, $group)->assertCreated();
        // 1600 for the course, not 1600 a day for the sixty-odd days it runs
        $this->assertEquals(1600, (float) $first->json('data.price') ?: (float) $first->json('data.booking.price'));
        $this->assertSame($group, (int) \App\Models\Booking::query()->latest('id')->value('course_group_id'));

        $this->enrol($b, $group)->assertCreated();
        $this->assertSame(0, CourseGroup::query()->findOrFail($group)->seatsLeft());

        $this->enrol($c, $group)->assertStatus(422);

        // a cancelled enrolment gives its seat back
        \App\Models\Booking::query()->where('course_group_id', $group)->where('user_id', $a->id)
            ->update(['status' => \App\Models\Booking::STATUS_CANCELLED]);
        $this->assertSame(1, CourseGroup::query()->findOrFail($group)->seatsLeft());
    }

    public function test_a_group_with_students_is_closed_not_deleted_and_seats_cannot_drop_below_the_taken(): void
    {
        $group = $this->open();
        $this->enrol($this->account(User::TYPE_CLIENT, 'StudentA'), $group)->assertCreated();

        Sanctum::actingAs($this->academy);
        $this->deleteJson("/api/v2/business/course-groups/{$group}")->assertStatus(422);
        $this->putJson("/api/v2/business/course-groups/{$group}", [
            'offering_id' => $this->course->id, 'name' => 'مجموعة المساء', 'starts_on' => now()->addDays(7)->toDateString(), 'seats' => 0,
        ])->assertStatus(422);

        $this->putJson("/api/v2/business/course-groups/{$group}", [
            'offering_id' => $this->course->id, 'name' => 'مجموعة المساء', 'starts_on' => now()->addDays(7)->toDateString(), 'seats' => 5, 'is_active' => false,
        ])->assertOk()->assertJsonPath('data.group.is_active', false);

        // closed: no longer offered
        $this->getJson("/api/v2/discovery/courses/{$this->academy->id}")->assertOk()->assertJsonPath('data.courses', []);
    }
}
