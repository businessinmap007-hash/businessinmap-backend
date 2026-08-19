<?php

namespace Tests\Feature;

use App\Enums\BookingPattern;
use App\Models\BusinessBookingSetting;
use App\Models\PlatformService;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * الخطوة الخامسة: الخادم يرسم الشاشة، والتطبيق ينفّذ.
 *
 * التطبيق لا يعرف شيئًا عن الفنادق ولا عن البلايستيشن ولا عن العيادات — يسأل
 * فيُقال له أىُّ حقلٍ يظهر وأيُّه لا يُقبل الحجز بدونه. ولهذا لا يحتاج نشاطٌ
 * جديد إصدارَ تطبيق.
 */
class BookingFormEndpointTest extends TestCase
{
    use DatabaseTransactions;

    private function client(): User
    {
        return User::query()->where('type', 'client')->firstOrFail();
    }

    private function businessOn(BookingPattern $pattern): User
    {
        $serviceId = (int) PlatformService::query()
            ->where('key', PlatformService::KEY_BOOKING)->where('is_active', 1)->value('id');

        $rows = DB::table('category_service_configs')
            ->where('platform_service_id', $serviceId)->where('is_active', 1)
            ->get(['category_id', 'child_id', 'config']);

        foreach ($rows as $row) {
            if (BookingPattern::tryFromConfig($row->config) !== $pattern) {
                continue;
            }

            $owner = User::query()->where('type', User::TYPE_BUSINESS)
                ->where('category_child_id', $row->child_id)
                ->where('category_id', $row->category_id)
                ->first();

            if ($owner) {
                return $owner;
            }
        }

        $this->markTestSkipped("لا نشاط قائم على نمط «{$pattern->label()}».");
    }

    private function form(User $business)
    {
        return $this->actingAs($this->client(), 'sanctum')
            ->getJson("/api/v2/bookings/form/{$business->id}");
    }

    /** الفندق يُسأل عن نزلائه، والحقل مسمًّى بلسانه. */
    public function test_a_hotel_is_told_to_ask_for_its_guests(): void
    {
        $hotel = $this->businessOn(BookingPattern::STAY);

        $response = $this->form($hotel)->assertOk();

        $response->assertJsonPath('data.shape.pattern', BookingPattern::STAY->value);
        $response->assertJsonPath('data.shape.unit', BookingPattern::UNIT_ALWAYS);

        $fields = collect($response->json('data.shape.fields'))->keyBy('key');

        $this->assertArrayHasKey('guest_count', $fields->all(), 'الفندق لا يسأل «كم نزيلًا»');
        $this->assertSame(__('booking.field.guest_count'), $fields['guest_count']['label']);
        $this->assertTrue($fields['date_range']['required'], 'إقامةٌ بلا مدة');
    }

    /** وما يشترطه صاحبُ النشاط يصل إلى الشاشة مطلوبًا. */
    public function test_what_the_business_refuses_to_book_without_arrives_marked_required(): void
    {
        $hotel = $this->businessOn(BookingPattern::STAY);

        $this->assertFalse(
            collect($this->form($hotel)->json('data.shape.fields'))
                ->firstWhere('key', 'guest_count')['required'],
            'النمط وحده لا يشترط عدد النزلاء'
        );

        BusinessBookingSetting::updateOrCreate(
            ['business_id' => $hotel->id],
            ['pattern' => BookingPattern::STAY->value, 'requires' => ['guest_count']]
        );

        $this->assertTrue(
            collect($this->form($hotel)->json('data.shape.fields'))
                ->firstWhere('key', 'guest_count')['required'],
            'شرطُ الفندق لم يصل الشاشة'
        );
    }

    /** ولا تُعرض وحداتٌ على شكلٍ لا وحدةَ فيه — قائمةٌ فارغة تُربك ولا تفيد. */
    public function test_a_unit_free_shape_is_sent_no_units(): void
    {
        $business = $this->businessOn(BookingPattern::APPOINTMENT);

        $response = $this->form($business)->assertOk();

        $this->assertSame(BookingPattern::UNIT_NEVER, $response->json('data.shape.unit'));
        $this->assertSame([], $response->json('data.units'));
    }

    /** «زيارةٌ عندك» لا تظهر إلا لمن يفعل الاثنين. */
    public function test_the_visit_place_reaches_the_screen_only_when_it_is_a_real_choice(): void
    {
        $business = $this->businessOn(BookingPattern::APPOINTMENT);

        $keys = fn () => collect($this->form($business)->json('data.shape.fields'))->pluck('key')->all();

        $this->assertNotContains('visit_place', $keys());

        BusinessBookingSetting::updateOrCreate(
            ['business_id' => $business->id],
            [
                'pattern' => BookingPattern::APPOINTMENT->value,
                'visit_mode' => BusinessBookingSetting::VISIT_BOTH,
            ]
        );

        $this->assertContains('visit_place', $keys());
    }

    /** وتصنيفٌ بلا نمط يردّ سكوتًا لا خطأ. */
    public function test_a_business_with_no_pattern_gets_a_null_shape_not_an_error(): void
    {
        $stranger = User::query()->where('type', User::TYPE_BUSINESS)
            ->whereNotIn('category_child_id', function ($q) {
                $q->select('child_id')->from('category_service_configs')
                    ->where('is_active', 1)
                    ->whereRaw("config LIKE '%booking_pattern%'");
            })->first();

        if (! $stranger) {
            $this->markTestSkipped('كل الأنشطة على تصنيفات لها نمط.');
        }

        $this->form($stranger)->assertOk()->assertJsonPath('data.shape', null);
    }

    /** ونشاطٌ لا وجود له لا يُخترع له شكل. */
    public function test_an_unknown_business_is_refused(): void
    {
        $this->actingAs($this->client(), 'sanctum')
            ->getJson('/api/v2/bookings/form/99999999')
            ->assertStatus(404);
    }
}
