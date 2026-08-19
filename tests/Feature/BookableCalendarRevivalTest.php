<?php

namespace Tests\Feature;

use App\Models\BookableItem;
use App\Models\BookableItemBlockedSlot;
use App\Models\BookableItemPriceRule;
use App\Models\BusinessServicePrice;
use App\Models\PlatformService;
use App\Models\User;
use App\Services\BookableAvailabilityService;
use App\Services\ServiceExecutionEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * «قم بالاحياء» — المالك، 2026-08-19.
 *
 * جدولان مبنيّان بالكامل وبصفرِ صفوف:
 *
 *   bookable_item_blocked_slots  — `BookableAvailabilityService` تقرؤها وترفض
 *                                  حجزًا يقع فيها. تعمل، ولا بابَ يكتبها إلا
 *                                  لوحةُ الإدارة.
 *   bookable_item_price_rules    — لها نموذجُها وخدمتُها وشاشةُ تقويمٍ كاملة،
 *                                  و`BookablePricingService` محقونةٌ فى محرّك
 *                                  التنفيذ ولا تُنادى أبدًا. فقاعدةٌ تُكتب
 *                                  تُرى فى التقويم ولا تغيّر فاتورةً واحدة.
 *
 * والسببُ فى الحالتين واحد: الذى يعرف أن غرفة ١٠٣ تحت الصيانة، وأن الجمعة
 * أغلى، هو صاحبُ المحل — وموظّفُ المنصّة هو وحده من كان له باب.
 */
class BookableCalendarRevivalTest extends TestCase
{
    use DatabaseTransactions;

    private const ROOT = 24;
    private const CHILD = 536;
    private const ITEM_TYPE = 'booking_stay';
    private const SINGLE_ROOM = 965;
    private const SEA_VIEW = 856;

    private function hotel(): User
    {
        return User::create([
            'name' => 'Zz Hotel ' . uniqid(),
            'email' => 'zz-cal-' . uniqid() . '@test.local',
            'phone' => '01' . random_int(100000000, 999999999),
            'password' => Hash::make('Passw0rdTest'),
            'type' => User::TYPE_BUSINESS,
            'category_id' => self::ROOT,
            'category_child_id' => self::CHILD,
            'api_token' => 'zz' . uniqid() . bin2hex(random_bytes(8)),
        ]);
    }

    private function serviceId(): int
    {
        return (int) PlatformService::query()
            ->where('key', PlatformService::KEY_BOOKING)->where('is_active', 1)->value('id');
    }

    /** فندقٌ بغرفةٍ واحدة مسعّرة بستّمئة. */
    private function room(User $hotel, float $price = 600): BookableItem
    {
        BusinessServicePrice::create([
            'business_id' => $hotel->id,
            'child_id' => self::CHILD,
            'service_id' => $this->serviceId(),
            'bookable_item_type' => self::ITEM_TYPE,
            'line_option_id' => self::SINGLE_ROOM,
            'price' => $price,
            'currency' => 'EGP',
            'is_active' => 1,
        ]);

        return BookableItem::create([
            'business_id' => $hotel->id,
            'service_id' => $this->serviceId(),
            'item_type' => self::ITEM_TYPE,
            'line_option_id' => self::SINGLE_ROOM,
            'code' => 'Z101',
            'quantity' => 1,
            'is_active' => 1,
        ]);
    }

    /** السعرُ كما يحسبه المحرّك ليومٍ بعينه. */
    private function priceOn(BookableItem $room, string $date): array
    {
        $price = app(\App\Services\BusinessServicePriceResolver::class)->resolveForBookableItem($room);

        return app(ServiceExecutionEngine::class)->resolvePriceBreakdown(
            service: PlatformService::findOrFail($price->service_id),
            businessPrice: $price,
            bookable: $room->fresh(),
            quantity: 1,
            pricingDate: $date,
            optionIds: app(ServiceExecutionEngine::class)->withUnitOwnOptions($room->fresh(), [])
        );
    }

    /** أوّلُ يومٍ قادمٍ يوافق هذا اليوم من الأسبوع (0 = الأحد). */
    private function nextWeekday(int $weekday): Carbon
    {
        $cursor = Carbon::today()->addDay();

        while ($cursor->dayOfWeek !== $weekday) {
            $cursor->addDay();
        }

        return $cursor;
    }

    /*
    |--------------------------------------------------------------------------
    | الإغلاق
    |--------------------------------------------------------------------------
    */

    /** ما يُغلق من الشاشة يُرفض فى المحرّك — نفسُ الخدمة التى تفحص التوفّر. */
    public function test_a_slot_closed_from_the_panel_refuses_a_booking_in_it(): void
    {
        $hotel = $this->hotel();
        $room = $this->room($hotel);

        $from = Carbon::tomorrow()->setTime(12, 0);
        $to = $from->copy()->addDays(3);

        $this->assertTrue(
            app(BookableAvailabilityService::class)->check($room, $from, $to)['available'],
            'الغرفةُ مشغولةٌ قبل أن يُغلقها أحد'
        );

        $this->actingAs($hotel)->post(
            route('business.bookable-items.blocked-slots.store', $room->id, false),
            [
                'starts_at' => $from->format('Y-m-d\TH:i'),
                'ends_at' => $to->format('Y-m-d\TH:i'),
                'block_type' => BookableItemBlockedSlot::TYPE_MAINTENANCE,
                'reason' => 'تغيير التكييف',
            ]
        )->assertRedirect();

        $check = app(BookableAvailabilityService::class)->check($room, $from->copy()->addDay(), $to->copy()->subDay());

        $this->assertFalse($check['available'], 'الإغلاقُ كُتب ولم يمنع شيئًا');
    }

    /** والإغلاقُ يُنسب إلى الوحدة ونشاطها، لا إلى ما أرسلته الشاشة. */
    public function test_the_block_takes_its_business_and_service_from_the_unit(): void
    {
        $hotel = $this->hotel();
        $room = $this->room($hotel);

        $this->actingAs($hotel)->post(
            route('business.bookable-items.blocked-slots.store', $room->id, false),
            [
                'starts_at' => Carbon::tomorrow()->format('Y-m-d\TH:i'),
                'ends_at' => Carbon::tomorrow()->addDay()->format('Y-m-d\TH:i'),
                'block_type' => BookableItemBlockedSlot::TYPE_MANUAL,
                // نشاطٌ وخدمةٌ لا تخصّانه — تُتجاهَلان.
                'business_id' => 1,
                'platform_service_id' => 99999,
            ]
        )->assertRedirect();

        $slot = BookableItemBlockedSlot::where('bookable_item_id', $room->id)->firstOrFail();

        $this->assertSame((int) $hotel->id, (int) $slot->business_id);
        $this->assertSame($this->serviceId(), (int) $slot->platform_service_id);
        $this->assertSame((int) $hotel->id, (int) $slot->created_by);
    }

    /** ويُفتح من جديد بحذفه. */
    public function test_reopening_a_closed_period_frees_the_unit(): void
    {
        $hotel = $this->hotel();
        $room = $this->room($hotel);

        $from = Carbon::tomorrow()->setTime(12, 0);
        $to = $from->copy()->addDays(2);

        $this->actingAs($hotel)->post(
            route('business.bookable-items.blocked-slots.store', $room->id, false),
            [
                'starts_at' => $from->format('Y-m-d\TH:i'),
                'ends_at' => $to->format('Y-m-d\TH:i'),
                'block_type' => BookableItemBlockedSlot::TYPE_HOLIDAY,
            ]
        )->assertRedirect();

        $slot = BookableItemBlockedSlot::where('bookable_item_id', $room->id)->firstOrFail();

        $this->actingAs($hotel)
            ->delete(route('business.bookable-items.blocked-slots.destroy', [$room->id, $slot->id], false))
            ->assertRedirect();

        $this->assertTrue(app(BookableAvailabilityService::class)->check($room, $from, $to)['available']);
    }

    /** ولا يغلق تاجرٌ وحدةَ تاجرٍ آخر. */
    public function test_one_merchant_cannot_close_another_merchants_unit(): void
    {
        $mine = $this->hotel();
        $theirs = $this->hotel();
        $theirRoom = $this->room($theirs);

        $this->actingAs($mine)->post(
            route('business.bookable-items.blocked-slots.store', $theirRoom->id, false),
            [
                'starts_at' => Carbon::tomorrow()->format('Y-m-d\TH:i'),
                'ends_at' => Carbon::tomorrow()->addDay()->format('Y-m-d\TH:i'),
                'block_type' => BookableItemBlockedSlot::TYPE_MANUAL,
            ]
        )->assertNotFound();

        $this->assertSame(0, BookableItemBlockedSlot::where('bookable_item_id', $theirRoom->id)->count());
    }

    /*
    |--------------------------------------------------------------------------
    | قواعد السعر
    |--------------------------------------------------------------------------
    */

    /** «الجمعة أغلى ٢٠٠» — تُغيّر الفاتورة، لا التقويمَ وحده. */
    public function test_a_weekday_rule_changes_what_the_engine_charges(): void
    {
        $hotel = $this->hotel();
        $room = $this->room($hotel, 600);

        $friday = $this->nextWeekday(5);
        $saturday = $this->nextWeekday(6);

        $this->actingAs($hotel)->post(
            route('business.bookable-items.price-rules.store', $room->id, false),
            [
                'title' => 'نهاية الأسبوع',
                'rule_type' => BookableItemPriceRule::RULE_WEEKDAY,
                'weekday' => 5,
                'price_type' => BookableItemPriceRule::PRICE_DELTA,
                'price_value' => 200,
            ]
        )->assertRedirect();

        $this->assertSame(800.0, (float) $this->priceOn($room, $friday->toDateString())['final_price']);
        $this->assertSame(600.0, (float) $this->priceOn($room, $saturday->toDateString())['final_price'], 'القاعدةُ سرت على يومٍ آخر');
    }

    /** و«موسم الصيف ١٢٠٠ ثابت» تحلّ محلّ الأساسى. */
    public function test_a_fixed_rule_replaces_the_base_price(): void
    {
        $hotel = $this->hotel();
        $room = $this->room($hotel, 600);

        $start = Carbon::today()->addDays(2);
        $end = $start->copy()->addDays(20);

        $this->actingAs($hotel)->post(
            route('business.bookable-items.price-rules.store', $room->id, false),
            [
                'rule_type' => BookableItemPriceRule::RULE_SEASON,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'price_type' => BookableItemPriceRule::PRICE_FIXED,
                'price_value' => 1200,
            ]
        )->assertRedirect();

        $this->assertSame(1200.0, (float) $this->priceOn($room, $start->copy()->addDay()->toDateString())['final_price']);
        $this->assertSame(600.0, (float) $this->priceOn($room, $end->copy()->addDays(5)->toDateString())['final_price']);
    }

    /**
     * والقاعدةُ تُطبَّق على الأساس، ثم تُضاف صفاتُ الغرفة إليها.
     *
     * والعكسُ يجعل «١٢٠٠ ثابت» تمحو زيادةَ الإطلالة بدل أن تحملها — فتخسر
     * الغرفةُ المميّزة ميزتَها فى الموسم الذى بُنيت له.
     */
    public function test_a_rule_prices_the_base_and_the_room_attributes_add_on_top(): void
    {
        $hotel = $this->hotel();
        $room = $this->room($hotel, 600);

        $price = BusinessServicePrice::where('business_id', $hotel->id)->firstOrFail();
        $price->syncOfferingOptions(self::SINGLE_ROOM, [self::SEA_VIEW], [
            self::SEA_VIEW => ['type' => 'amount', 'value' => 100],
        ]);
        $room->syncOfferingOptions(self::SINGLE_ROOM, [self::SEA_VIEW]);

        $day = Carbon::today()->addDays(3);

        $this->assertSame(700.0, (float) $this->priceOn($room, $day->toDateString())['final_price'], 'الإطلالةُ وحدها');

        $this->actingAs($hotel)->post(
            route('business.bookable-items.price-rules.store', $room->id, false),
            [
                'rule_type' => BookableItemPriceRule::RULE_DATE_RANGE,
                'start_date' => $day->toDateString(),
                'end_date' => $day->copy()->addDay()->toDateString(),
                'price_type' => BookableItemPriceRule::PRICE_FIXED,
                'price_value' => 1200,
            ]
        )->assertRedirect();

        $this->assertSame(1300.0, (float) $this->priceOn($room, $day->toDateString())['final_price']);
    }

    /** والنسبةُ تُقرأ من الأساسى. */
    public function test_a_percent_rule_is_taken_from_the_base(): void
    {
        $hotel = $this->hotel();
        $room = $this->room($hotel, 600);
        $day = Carbon::today()->addDays(4);

        $this->actingAs($hotel)->post(
            route('business.bookable-items.price-rules.store', $room->id, false),
            [
                'rule_type' => BookableItemPriceRule::RULE_DATE_RANGE,
                'start_date' => $day->toDateString(),
                'end_date' => $day->toDateString(),
                'price_type' => BookableItemPriceRule::PRICE_PERCENT,
                'price_value' => 25,
            ]
        )->assertRedirect();

        $this->assertSame(750.0, (float) $this->priceOn($room, $day->toDateString())['final_price']);
    }

    /** وعند تعارض قاعدتين يغلب الأقلُّ أولويةً. */
    public function test_the_lower_priority_number_wins_a_clash(): void
    {
        $hotel = $this->hotel();
        $room = $this->room($hotel, 600);
        $day = Carbon::today()->addDays(6);

        foreach ([[900, 100], [1500, 10]] as [$value, $priority]) {
            $this->actingAs($hotel)->post(
                route('business.bookable-items.price-rules.store', $room->id, false),
                [
                    'rule_type' => BookableItemPriceRule::RULE_DATE_RANGE,
                    'start_date' => $day->toDateString(),
                    'end_date' => $day->toDateString(),
                    'price_type' => BookableItemPriceRule::PRICE_FIXED,
                    'price_value' => $value,
                    'priority' => $priority,
                ]
            )->assertRedirect();
        }

        $breakdown = $this->priceOn($room, $day->toDateString());

        $this->assertSame(1500.0, (float) $breakdown['final_price']);
        $this->assertSame(10, (int) $breakdown['price_rule']['priority']);
    }

    /** والفاتورةُ تقول أىُّ قاعدةٍ حكمت عليها. */
    public function test_the_breakdown_names_the_rule_that_priced_the_day(): void
    {
        $hotel = $this->hotel();
        $room = $this->room($hotel, 600);
        $day = Carbon::today()->addDays(7);

        $this->assertNull($this->priceOn($room, $day->toDateString())['price_rule']);

        $this->actingAs($hotel)->post(
            route('business.bookable-items.price-rules.store', $room->id, false),
            [
                'title' => 'عيد',
                'rule_type' => BookableItemPriceRule::RULE_SPECIAL_DAY,
                'start_date' => $day->toDateString(),
                'end_date' => $day->toDateString(),
                'price_type' => BookableItemPriceRule::PRICE_DELTA,
                'price_value' => 300,
            ]
        )->assertRedirect();

        $rule = $this->priceOn($room, $day->toDateString())['price_rule'];

        $this->assertSame('عيد', $rule['title']);
        $this->assertSame(900.0, (float) $this->priceOn($room, $day->toDateString())['final_price']);
    }

    /**
     * وقاعدةُ مدًى بلا تاريخين تُرفض.
     *
     * `scopeForDate` تقرأ السطرَ بلا تاريخين على أنه «كلَّ يوم»، فقاعدةُ موسمٍ
     * نُسى تاريخُها تصير سعرًا دائمًا يغلب الأساسىَّ إلى الأبد — ولا شىءَ فى
     * الشاشة يقول ذلك.
     */
    public function test_a_dated_rule_without_dates_is_refused(): void
    {
        $hotel = $this->hotel();
        $room = $this->room($hotel, 600);

        $this->actingAs($hotel)->post(
            route('business.bookable-items.price-rules.store', $room->id, false),
            [
                'rule_type' => BookableItemPriceRule::RULE_SEASON,
                'price_type' => BookableItemPriceRule::PRICE_FIXED,
                'price_value' => 1200,
            ]
        )->assertSessionHasErrors('start_date');

        $this->assertSame(0, BookableItemPriceRule::where('bookable_item_id', $room->id)->count());
    }

    /** وقاعدةُ يومٍ بلا يوم كذلك. */
    public function test_a_weekday_rule_without_a_weekday_is_refused(): void
    {
        $hotel = $this->hotel();
        $room = $this->room($hotel, 600);

        $this->actingAs($hotel)->post(
            route('business.bookable-items.price-rules.store', $room->id, false),
            [
                'rule_type' => BookableItemPriceRule::RULE_WEEKDAY,
                'price_type' => BookableItemPriceRule::PRICE_DELTA,
                'price_value' => 200,
            ]
        )->assertSessionHasErrors('weekday');

        $this->assertSame(0, BookableItemPriceRule::where('bookable_item_id', $room->id)->count());
    }

    /** ولا يسعّر تاجرٌ وحدةَ تاجرٍ آخر. */
    public function test_one_merchant_cannot_price_another_merchants_unit(): void
    {
        $mine = $this->hotel();
        $theirRoom = $this->room($this->hotel());

        $this->actingAs($mine)->post(
            route('business.bookable-items.price-rules.store', $theirRoom->id, false),
            [
                'rule_type' => BookableItemPriceRule::RULE_WEEKDAY,
                'weekday' => 5,
                'price_type' => BookableItemPriceRule::PRICE_DELTA,
                'price_value' => 200,
            ]
        )->assertNotFound();

        $this->assertSame(0, BookableItemPriceRule::where('bookable_item_id', $theirRoom->id)->count());
    }

    /** والبابان على شاشة الوحدة نفسها، وإلا فهما جدولان بلا كاتب. */
    public function test_the_unit_screen_offers_both_doors(): void
    {
        $hotel = $this->hotel();
        $room = $this->room($hotel);

        $html = $this->actingAs($hotel)
            ->get(route('business.bookable-items.edit', $room->id, false))
            ->assertOk()->getContent();

        $this->assertStringContainsString(
            route('business.bookable-items.blocked-slots.store', $room->id, false), $html
        );
        $this->assertStringContainsString(
            route('business.bookable-items.price-rules.store', $room->id, false), $html
        );
    }
}
