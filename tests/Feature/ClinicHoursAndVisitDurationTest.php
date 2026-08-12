<?php

namespace Tests\Feature;

use App\Models\BusinessServicePrice;
use App\Models\BusinessWorkingHour;
use App\Models\ClinicAppointment;
use App\Models\User;
use App\Services\BusinessHoursService;
use App\Services\Clinics\ClinicAppointmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Two gaps found together, because they are the same gap seen twice.
 *
 * `business_working_hours` existed and only SEARCH ever read it. The booking
 * engine and the clinic's slot generator did not, so a clinic shut on Friday
 * could publish four weeks of bookable Friday slots, and one that closes at
 * 17:00 was handed a 16:50 appointment thirty minutes long.
 *
 * And the length itself lived on the slot, so a clinic could say «this batch is
 * thirty minutes» but never «a كشف is always thirty and an استشارة always
 * twenty» — it retyped it on every publish.
 *
 * @see \App\Services\BusinessHoursService::isOpenThroughout
 * @see \App\Services\Clinics\ClinicAppointmentService::resolveDuration
 */
class ClinicHoursAndVisitDurationTest extends TestCase
{
    use DatabaseTransactions;

    private function clinic(): User
    {
        $user = User::query()->where('type', 'business')->first();

        if (! $user) {
            $this->markTestSkipped('No business account.');
        }

        return $user;
    }

    /** A week where the business opens 09:00–17:00 every day but Friday (5). */
    private function openNineToFive(int $businessId): void
    {
        foreach (BusinessHoursService::DAYS as $day) {
            BusinessWorkingHour::query()->updateOrCreate(
                ['business_id' => $businessId, 'day_of_week' => $day],
                $day === 5
                    ? ['is_closed' => true, 'open_time' => null, 'close_time' => null]
                    : ['is_closed' => false, 'open_time' => '09:00:00', 'close_time' => '17:00:00'],
            );
        }
    }

    private function nextWeekday(int $dayOfWeek, string $time): Carbon
    {
        $at = Carbon::today()->addDay();

        while ($at->dayOfWeek !== $dayOfWeek) {
            $at->addDay();
        }

        [$h, $m] = array_map('intval', explode(':', $time));

        return $at->setTime($h, $m);
    }

    /*
    |--------------------------------------------------------------------------
    | The window, not the instant
    |--------------------------------------------------------------------------
    */
    public function test_a_business_that_set_no_hours_is_never_refused(): void
    {
        $clinic = $this->clinic();
        BusinessWorkingHour::query()->where('business_id', $clinic->id)->delete();

        $at = $this->nextWeekday(5, '03:00');

        $this->assertTrue(
            app(BusinessHoursService::class)->isOpenThroughout((int) $clinic->id, $at, $at->copy()->addMinutes(30)),
            'hours unknown is not hours refused — almost nobody has set them yet'
        );
    }

    public function test_an_appointment_that_runs_past_closing_is_refused(): void
    {
        $clinic = $this->clinic();
        $this->openNineToFive((int) $clinic->id);

        $hours = app(BusinessHoursService::class);
        $late = $this->nextWeekday(1, '16:50');

        $this->assertFalse(
            $hours->isOpenThroughout((int) $clinic->id, $late, $late->copy()->addMinutes(30)),
            '16:50 + 30 minutes ends after a 17:00 close'
        );

        // …while the last honest appointment of the day still fits exactly.
        $last = $this->nextWeekday(1, '16:30');

        $this->assertTrue(
            $hours->isOpenThroughout((int) $clinic->id, $last, $last->copy()->addMinutes(30)),
            'ending ON closing time is the last appointment, not a refusal'
        );
    }

    public function test_a_multi_day_stay_is_not_an_opening_hours_question(): void
    {
        $clinic = $this->clinic();
        $this->openNineToFive((int) $clinic->id);

        $in = $this->nextWeekday(1, '14:00');

        $this->assertTrue(
            app(BusinessHoursService::class)->isOpenThroughout((int) $clinic->id, $in, $in->copy()->addDays(3)),
            'a four-night stay must not be refused because the hotel «closes» at 17:00'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | The grid that published four weeks of closed Fridays
    |--------------------------------------------------------------------------
    */
    public function test_the_weekly_grid_skips_the_days_the_clinic_is_shut(): void
    {
        $clinic = $this->clinic();
        $this->openNineToFive((int) $clinic->id);

        $result = app(ClinicAppointmentService::class)
            ->generateSlots($clinic, [5], ['10:00', '11:00'], 21, 30);

        $this->assertSame(0, $result['created'], 'not one Friday slot may be published');
        $this->assertGreaterThan(0, $result['outside_hours'], 'and the answer says why');
    }

    public function test_the_weekly_grid_still_publishes_an_open_day(): void
    {
        $clinic = $this->clinic();
        $this->openNineToFive((int) $clinic->id);

        $result = app(ClinicAppointmentService::class)
            ->generateSlots($clinic, [1], ['10:00'], 21, 30);

        $this->assertGreaterThan(0, $result['created']);
        $this->assertSame(0, $result['outside_hours']);
    }

    public function test_no_appointment_may_be_confirmed_while_the_clinic_is_shut(): void
    {
        $clinic = $this->clinic();
        $this->openNineToFive((int) $clinic->id);

        $this->expectException(ValidationException::class);

        app(ClinicAppointmentService::class)
            ->assertNoConflict((int) $clinic->id, $this->nextWeekday(5, '10:00'), 30, null);
    }

    /*
    |--------------------------------------------------------------------------
    | «الكشف ٣٠ دقيقة والاستشارة ٢٠»
    |--------------------------------------------------------------------------
    */
    /**
     * Two visits are two ROWS, and the unique key
     * (business, child, service, item_type, line) is what keeps them apart —
     * so a fixture that gives both the same nameless shape collides, exactly as
     * a clinic typing «كشف» twice would.
     */
    private function pricedVisit(User $clinic, int $minutes, string $itemType = 'category'): BusinessServicePrice
    {
        return BusinessServicePrice::create([
            'business_id' => $clinic->id,
            'child_id' => $clinic->category_child_id ?: 1,
            'service_id' => 1,
            'bookable_item_type' => $itemType,
            'price' => 300,
            'currency' => 'EGP',
            'duration_minutes' => $minutes,
            'is_active' => 1,
        ]);
    }

    public function test_the_visit_kind_sets_the_length(): void
    {
        $clinic = $this->clinic();
        $service = app(ClinicAppointmentService::class);

        $exam = $this->pricedVisit($clinic, 30, 'booking_appointment');
        $consult = $this->pricedVisit($clinic, 20, 'booking_online_consultation');

        [$examMinutes] = $service->resolveDuration((int) $clinic->id, ['service_price_id' => $exam->id]);
        [$consultMinutes] = $service->resolveDuration((int) $clinic->id, ['service_price_id' => $consult->id]);

        $this->assertSame(30, $examMinutes);
        $this->assertSame(20, $consultMinutes);
    }

    /** The clinic's setting beats the patient's form field, or it is decorative. */
    public function test_the_clinics_setting_outranks_a_posted_duration(): void
    {
        $clinic = $this->clinic();
        $consult = $this->pricedVisit($clinic, 20);

        [$minutes] = app(ClinicAppointmentService::class)->resolveDuration((int) $clinic->id, [
            'service_price_id' => $consult->id,
            'duration_minutes' => 120,
        ]);

        $this->assertSame(20, $minutes, 'a patient may not book two hours of a twenty-minute consultation');
    }

    public function test_a_visit_kind_from_another_clinic_is_refused(): void
    {
        $clinic = $this->clinic();
        $other = User::query()->where('type', 'business')->where('id', '!=', $clinic->id)->first();

        if (! $other) {
            $this->markTestSkipped('Needs a second business.');
        }

        $theirs = $this->pricedVisit($other, 45);

        $this->expectException(ValidationException::class);

        app(ClinicAppointmentService::class)
            ->resolveDuration((int) $clinic->id, ['service_price_id' => $theirs->id]);
    }

    public function test_a_clinic_that_priced_nothing_keeps_the_thirty_minute_default(): void
    {
        [$minutes, $priceId] = app(ClinicAppointmentService::class)
            ->resolveDuration((int) $this->clinic()->id, []);

        $this->assertSame(ClinicAppointmentService::DEFAULT_DURATION, $minutes);
        $this->assertNull($priceId);
    }

    /** The appointment remembers WHICH visit it was, not just how long. */
    public function test_the_appointment_records_the_visit_kind(): void
    {
        $clinic = $this->clinic();
        BusinessWorkingHour::query()->where('business_id', $clinic->id)->delete();

        $patient = User::query()->where('id', '!=', $clinic->id)->first();

        if (! $patient) {
            $this->markTestSkipped('Needs a patient.');
        }

        $consult = $this->pricedVisit($clinic, 20);

        $appointment = app(ClinicAppointmentService::class)->request($patient, $clinic, [
            'scheduled_at' => Carbon::tomorrow()->setTime(11, 0)->toDateTimeString(),
            'service_price_id' => $consult->id,
        ]);

        $this->assertInstanceOf(ClinicAppointment::class, $appointment);
        $this->assertSame((int) $consult->id, (int) $appointment->service_price_id);
        $this->assertSame(20, (int) $appointment->duration_minutes);
    }
}
