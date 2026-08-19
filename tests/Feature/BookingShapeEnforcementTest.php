<?php

namespace Tests\Feature;

use App\Enums\BookingPattern;
use App\Models\BusinessBookingSetting;
use App\Models\PlatformService;
use App\Models\User;
use App\Services\BookingShapeResolver;
use App\Services\ServiceExecutionEngine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * الخطوة الرابعة: الشكل يُفرَض، بعد أن كان يَصِف.
 *
 * نقطةُ إنشاء الحجز تخدم ستّة أشكال، فكل حقولها `nullable` ولا قاعدةَ ثابتة
 * تصلح لها جميعًا. الشكل هو ما يحوّل `nullable` إلى «مطلوبٌ هنا» — ولا يفعل
 * ذلك إلا حيث يعنى شيئًا.
 */
class BookingShapeEnforcementTest extends TestCase
{
    use DatabaseTransactions;

    private BookingShapeResolver $shapes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shapes = app(BookingShapeResolver::class);
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

    /**
     * النمط يضمن أن الحجز قابلٌ للتنفيذ، ولا يزيد.
     *
     * هذا هو الخطّ الذى رسمه اختبارٌ أحمر: «كم نزيلًا» شرطًا على «إقامة» رفض
     * تأجيرَ سيارة، والسيارة تُحجَز بالمدّة ولا نزلاءَ فيها.
     */
    public function test_the_pattern_requires_only_what_a_booking_cannot_run_without(): void
    {
        $this->assertSame(['date_range'], BookingPattern::STAY->requires());
        $this->assertNotContains('guest_count', BookingPattern::STAY->requires());
        $this->assertContains('guest_count', BookingPattern::STAY->asks(), 'الفندق لم يعد يسأل أصلًا');
    }

    /** ما ينقص يُسمّى بلغة الشاشة، لا بلغة الأعمدة. */
    public function test_what_is_missing_is_named_the_way_the_customer_reads_it(): void
    {
        $hotel = new BusinessBookingSetting(['requires' => ['guest_count']]);
        $shape = BusinessBookingSetting::resolve(BookingPattern::STAY, $hotel);

        $missing = $this->shapes->missingFrom($shape, [
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDays(3)->toDateTimeString(),
        ]);

        $this->assertArrayHasKey('party_size', $missing, 'عدد النزلاء يصل عمود party_size');
        $this->assertSame(__('كم نزيلًا؟'), $missing['party_size']);

        // ونفس العمود يُسأل عنه بلسان المطعم.
        $restaurant = new BusinessBookingSetting(['requires' => ['party_size']]);
        $shape = BusinessBookingSetting::resolve(BookingPattern::TABLE, $restaurant);

        $this->assertSame(
            __('كم فردًا؟'),
            $this->shapes->missingFrom($shape, ['starts_at' => now()->addDay()->toDateTimeString()])['party_size'] ?? null
        );
    }

    /** ومتى اكتملت الحمولة لم يبقَ نقصٌ يُبلَّغ عنه. */
    public function test_a_complete_payload_leaves_nothing_missing(): void
    {
        $hotel = new BusinessBookingSetting(['requires' => ['guest_count']]);
        $shape = BusinessBookingSetting::resolve(BookingPattern::STAY, $hotel);

        $this->assertSame([], $this->shapes->missingFrom($shape, [
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDays(3)->toDateTimeString(),
            'party_size' => 2,
        ]));
    }

    /** «مدّة» تحتاج طرفَى الفترة — لحظةُ بداية بلا نهاية ليست مدّة. */
    public function test_a_duration_needs_both_ends(): void
    {
        $shape = BusinessBookingSetting::resolve(BookingPattern::DURATION, null);

        $this->assertNotSame([], $this->shapes->missingFrom($shape, [
            'starts_at' => now()->addDay()->toDateTimeString(),
        ]));

        $this->assertSame([], $this->shapes->missingFrom($shape, [
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDay()->addHour()->toDateTimeString(),
        ]));
    }

    /** تصنيفٌ بلا نمط لا يشترط شيئًا: الغياب سكوتٌ لا حكم. */
    public function test_a_business_with_no_pattern_is_not_blocked(): void
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

        $this->assertNull($this->shapes->forBusiness((int) $stranger->id));

        app(ServiceExecutionEngine::class)->assertShapeSatisfied((int) $stranger->id, []);
        $this->addToAssertionCount(1);
    }

    /** والفرض يمرّ من المحرّك، لا من قواعد التحقّق الثابتة. */
    public function test_the_engine_refuses_a_payload_the_business_declared_incomplete(): void
    {
        $hotel = $this->businessOn(BookingPattern::STAY);

        BusinessBookingSetting::updateOrCreate(
            ['business_id' => $hotel->id],
            ['pattern' => BookingPattern::STAY->value, 'requires' => ['guest_count']]
        );

        $this->expectException(ValidationException::class);

        app(ServiceExecutionEngine::class)->assertShapeSatisfied((int) $hotel->id, [
            'starts_at' => now()->addDay()->toDateTimeString(),
            'ends_at' => now()->addDays(3)->toDateTimeString(),
        ]);
    }

    /**
     * الامتناع لا يخرج من المُحلِّل.
     *
     * `UNIT_OPTIONAL` كلامٌ عن الطفل؛ ومن يسأل عن نشاطٍ بعينه يجب أن يأخذ
     * جوابًا لا سؤالًا.
     */
    public function test_the_resolver_never_hands_back_an_abstention(): void
    {
        $business = $this->businessOn(BookingPattern::DURATION);
        $shape = $this->shapes->forBusiness((int) $business->id);

        $this->assertNotNull($shape);
        $this->assertNotSame(BookingPattern::UNIT_OPTIONAL, $shape['unit']);
        $this->assertContains($shape['unit'], [BookingPattern::UNIT_ALWAYS, BookingPattern::UNIT_NEVER]);
    }
}
