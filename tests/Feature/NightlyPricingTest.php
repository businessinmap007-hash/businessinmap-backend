<?php

namespace Tests\Feature;

use App\Models\BookableItem;
use App\Models\BookableItemPriceRule;
use App\Models\BusinessServicePrice;
use App\Models\PlatformService;
use App\Models\User;
use App\Services\ServiceExecutionEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * «ابنى تسعير الليالى» — المالك، 2026-08-20.
 *
 * كان الحسابُ سعرًا واحدًا مضروبًا فى `quantity`، وقاعدةُ اليوم تُقرأ ليومِ
 * الوصول وحده. فإقامةُ خميسٍ إلى أحدٍ فى فندقٍ يرفع سعرَ الجمعة تُحاسَب بسعر
 * الخميس ثلاثَ مرّات، والقاعدةُ مكتوبةٌ ومُفعّلة ولا أثرَ لها.
 *
 * الليلةُ الآن وحدةُ الحساب: لكلٍّ قاعدتُها وزياداتُها، والمجموعُ مجموعُها.
 *
 * ── ولا تتحرّك فاتورةٌ قائمة ─────────────────────────────────────────────
 *
 * مجموعُ ن ليلةٍ متساوية = سعرٌ واحد × ن. فبلا قاعدةٍ لا فرقَ بين الطريقين،
 * وهذا مقصود: كلُّ حجزٍ فى قاعدة البيانات يحمل `quantity` مساويةً لعدد لياليه،
 * ولو تغيّر الحساب لتغيّرت أسعارٌ اتُّفق عليها.
 */
class NightlyPricingTest extends TestCase
{
    use DatabaseTransactions;

    private const ROOT = 24;
    private const CHILD = 536;
    private const ITEM_TYPE = 'booking_stay';

    private const SINGLE = 965;
    private const DOUBLE = 966;
    private const SUITE = 967;
    private const BREAKFAST = 855;

    private User $hotel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = User::create([
            'name' => 'Zz Nights ' . uniqid(),
            'email' => 'zz-nights-' . uniqid() . '@test.local',
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

    private function priceFor(int $kind, float $price): BusinessServicePrice
    {
        return BusinessServicePrice::create([
            'business_id' => $this->hotel->id,
            'child_id' => self::CHILD,
            'service_id' => $this->serviceId(),
            'bookable_item_type' => self::ITEM_TYPE,
            'line_option_id' => $kind,
            'price' => $price,
            'currency' => 'EGP',
            'is_active' => 1,
        ]);
    }

    private function roomOf(int $kind, string $code): BookableItem
    {
        return BookableItem::create([
            'business_id' => $this->hotel->id,
            'service_id' => $this->serviceId(),
            'item_type' => self::ITEM_TYPE,
            'line_option_id' => $kind,
            'code' => $code,
            'quantity' => 1,
            'is_active' => 1,
        ]);
    }

    /** الفاتورةُ كما يبنيها المحرّك لنافذةٍ بعينها. */
    private function stay(BookableItem $room, Carbon $from, int $nights, int $quantity = 1): array
    {
        return app(ServiceExecutionEngine::class)->prepare(
            businessId: (int) $this->hotel->id,
            serviceId: $this->serviceId(),
            bookableId: (int) $room->id,
            quantity: $quantity,
            pricingDate: $from->toDateTimeString(),
            until: $from->copy()->addDays($nights)->toDateTimeString()
        )['price_breakdown'];
    }

    private function nextWeekday(int $weekday): Carbon
    {
        $cursor = Carbon::today()->addDay()->setTime(14, 0);

        while ($cursor->dayOfWeek !== $weekday) {
            $cursor->addDay();
        }

        return $cursor;
    }

    /*
    |--------------------------------------------------------------------------
    | الليالى
    |--------------------------------------------------------------------------
    */

    /** ثلاثُ ليالٍ ثلاثةُ أسعار، لا سعرٌ واحد. */
    public function test_a_three_night_stay_is_charged_three_nights(): void
    {
        $this->priceFor(self::SINGLE, 600);
        $room = $this->roomOf(self::SINGLE, 'Z101');

        $breakdown = $this->stay($room, Carbon::today()->addDay()->setTime(14, 0), 3);

        $this->assertSame(3, (int) $breakdown['nights_count']);
        $this->assertSame(1800.0, (float) $breakdown['final_price']);
    }

    /** ويومُ المغادرة لا يُحاسَب: ليلتان بين أربعاءَ وجمعة. */
    public function test_the_checkout_day_is_not_a_night(): void
    {
        $this->priceFor(self::SINGLE, 600);
        $room = $this->roomOf(self::SINGLE, 'Z101');

        $from = Carbon::today()->addDay()->setTime(14, 0);

        $this->assertSame(2, (int) $this->stay($room, $from, 2)['nights_count']);
        $this->assertSame(1200.0, (float) $this->stay($room, $from, 2)['final_price']);
    }

    /** والقاعدةُ تُقرأ لكل ليلة: الجمعةُ وحدها أغلى. */
    public function test_a_weekday_rule_prices_only_its_own_night(): void
    {
        $this->priceFor(self::SINGLE, 600);
        $room = $this->roomOf(self::SINGLE, 'Z101');

        BookableItemPriceRule::create([
            'bookable_item_id' => $room->id,
            'business_id' => $this->hotel->id,
            'platform_service_id' => $this->serviceId(),
            'rule_type' => BookableItemPriceRule::RULE_WEEKDAY,
            'weekday' => 5, // الجمعة
            'price_type' => BookableItemPriceRule::PRICE_DELTA,
            'price_value' => 200,
            'priority' => 100,
            'is_active' => 1,
        ]);

        // خميس → أحد: خميسٌ وجمعةٌ وسبت.
        $breakdown = $this->stay($room, $this->nextWeekday(4), 3);

        $this->assertSame([600.0, 800.0, 600.0], array_map(
            fn (array $night) => (float) $night['price'],
            $breakdown['nights']
        ));

        $this->assertSame(2000.0, (float) $breakdown['final_price']);
    }

    /** والفاتورةُ تقول ليلةً ليلة بتاريخها، فتُقرأ ولا تُصدَّق. */
    public function test_the_breakdown_lists_every_night_with_its_date(): void
    {
        $this->priceFor(self::SINGLE, 600);
        $room = $this->roomOf(self::SINGLE, 'Z101');

        $from = Carbon::today()->addDays(2)->setTime(14, 0);
        $nights = $this->stay($room, $from, 3)['nights'];

        $this->assertSame($from->toDateString(), $nights[0]['date']);
        $this->assertSame($from->copy()->addDays(2)->toDateString(), $nights[2]['date']);
        $this->assertArrayHasKey('base_price', $nights[0]);
        $this->assertArrayHasKey('rule', $nights[0]);
    }

    /*
    |--------------------------------------------------------------------------
    | المُوصِّفات على الليالى
    |--------------------------------------------------------------------------
    */

    /** «إفطار +٥٠» خمسون كلَّ صباح، لا خمسون على الإقامة. */
    public function test_a_modifier_is_charged_on_every_night(): void
    {
        $price = $this->priceFor(self::SINGLE, 600);
        $price->syncOfferingOptions(self::SINGLE, [self::BREAKFAST], [
            self::BREAKFAST => ['type' => 'amount', 'value' => 50],
        ]);

        $room = $this->roomOf(self::SINGLE, 'Z101');
        $room->syncOfferingOptions(self::SINGLE, [self::BREAKFAST]);

        $breakdown = $this->stay($room, Carbon::today()->addDay()->setTime(14, 0), 3);

        $this->assertSame(1950.0, (float) $breakdown['final_price'], '٣ × (٦٠٠ + ٥٠)');
    }

    /** والنسبةُ تُقرأ من أساس ليلتها: ليلةٌ أغلى نسبتُها أعلى. */
    public function test_a_percent_modifier_reads_the_base_of_its_own_night(): void
    {
        $price = $this->priceFor(self::SINGLE, 600);
        $price->syncOfferingOptions(self::SINGLE, [self::BREAKFAST], [
            self::BREAKFAST => ['type' => 'percent', 'value' => 10],
        ]);

        $room = $this->roomOf(self::SINGLE, 'Z101');
        $room->syncOfferingOptions(self::SINGLE, [self::BREAKFAST]);

        BookableItemPriceRule::create([
            'bookable_item_id' => $room->id,
            'business_id' => $this->hotel->id,
            'platform_service_id' => $this->serviceId(),
            'rule_type' => BookableItemPriceRule::RULE_WEEKDAY,
            'weekday' => 5,
            'price_type' => BookableItemPriceRule::PRICE_FIXED,
            'price_value' => 1000,
            'priority' => 100,
            'is_active' => 1,
        ]);

        $nights = $this->stay($room, $this->nextWeekday(4), 2)['nights'];

        $this->assertSame(660.0, (float) $nights[0]['price'], 'الخميس: ٦٠٠ + ١٠٪');
        $this->assertSame(1100.0, (float) $nights[1]['price'], 'الجمعة: ١٠٠٠ + ١٠٪');
    }

    /*
    |--------------------------------------------------------------------------
    | ما لا يتحرّك
    |--------------------------------------------------------------------------
    */

    /** بلا قاعدة، المجموعُ هو نفسُه الذى كان — والفواتيرُ القائمة لا تتغيّر. */
    public function test_without_a_rule_the_total_is_exactly_what_it_used_to_be(): void
    {
        $this->priceFor(self::SINGLE, 600);
        $room = $this->roomOf(self::SINGLE, 'Z101');

        // ستُّ ليالٍ بستّمئة = ٣٦٠٠، وهو رقمُ الحجز ١٧٦٠٩ فى قاعدة البيانات.
        $this->assertSame(3600.0, (float) $this->stay($room, Carbon::today()->addDay()->setTime(10, 0), 6)['final_price']);
    }

    /**
     * والنافذةُ هى الكمّية، لا ما أرسله العميل.
     *
     * كلُّ حجزٍ قائم يملأ `quantity` بعدد الليالى، فلو ضُربت فيها بعد جمع
     * الليالى لحُسبت الإقامةُ مرّتين.
     */
    public function test_the_window_decides_the_nights_not_the_posted_quantity(): void
    {
        $this->priceFor(self::SINGLE, 600);
        $room = $this->roomOf(self::SINGLE, 'Z101');

        $from = Carbon::today()->addDay()->setTime(14, 0);

        $this->assertSame(1800.0, (float) $this->stay($room, $from, 3, quantity: 3)['final_price']);
        $this->assertSame(1800.0, (float) $this->stay($room, $from, 3, quantity: 99)['final_price']);
    }

    /**
     * وما لا نافذةَ له لا ليالىَ له.
     *
     * ثلاثُ ساعاتٍ على البلايستيشن تبدأ وتنتهى فى اليوم نفسه: الحسابُ كما كان
     * — سعرُ الوحدة × الكمّية — والليلةُ ليست وحدةَ كلِّ حجز.
     */
    public function test_a_same_day_window_keeps_the_old_path(): void
    {
        $this->priceFor(self::SINGLE, 100);
        $room = $this->roomOf(self::SINGLE, 'Z101');

        $start = Carbon::today()->addDay()->setTime(14, 0);

        $breakdown = app(ServiceExecutionEngine::class)->prepare(
            businessId: (int) $this->hotel->id,
            serviceId: $this->serviceId(),
            bookableId: (int) $room->id,
            quantity: 3,
            pricingDate: $start->toDateTimeString(),
            until: $start->copy()->addHours(3)->toDateTimeString()
        )['price_breakdown'];

        $this->assertSame(0, (int) $breakdown['nights_count']);
        $this->assertSame(300.0, (float) $breakdown['final_price'], '٣ ساعات × ١٠٠');
    }

    /** وكذلك ما لا نهايةَ له أصلًا: كشفُ طبيبٍ أو طاولةُ مطعم. */
    public function test_a_booking_with_no_window_keeps_the_old_path(): void
    {
        $this->priceFor(self::SINGLE, 250);
        $room = $this->roomOf(self::SINGLE, 'Z101');

        $breakdown = app(ServiceExecutionEngine::class)->prepare(
            businessId: (int) $this->hotel->id,
            serviceId: $this->serviceId(),
            bookableId: (int) $room->id,
            quantity: 2,
            pricingDate: Carbon::today()->addDay()->toDateTimeString()
        )['price_breakdown'];

        $this->assertSame(0, (int) $breakdown['nights_count']);
        $this->assertSame(500.0, (float) $breakdown['final_price']);
    }

    /*
    |--------------------------------------------------------------------------
    | سعرٌ لكل نوع
    |--------------------------------------------------------------------------
    */

    /**
     * «سعر للفردية وسعر للمزدوجة وسعر للجناح».
     *
     * سطرُ السعر مفتاحُه (الخدمة، نوعُ العنصر، نوعُ الوحدة)، والوحدةُ تشير إلى
     * نوعها — فكلُّ غرفةٍ فردية تأخذ سعرَ الفردية بلا سطرٍ لها.
     */
    public function test_each_kind_carries_its_own_price(): void
    {
        $this->priceFor(self::SINGLE, 600);
        $this->priceFor(self::DOUBLE, 900);
        $this->priceFor(self::SUITE, 2000);

        $from = Carbon::today()->addDay()->setTime(14, 0);

        $this->assertSame(600.0, (float) $this->stay($this->roomOf(self::SINGLE, 'Z101'), $from, 1)['final_price']);
        $this->assertSame(900.0, (float) $this->stay($this->roomOf(self::DOUBLE, 'Z201'), $from, 1)['final_price']);
        $this->assertSame(2000.0, (float) $this->stay($this->roomOf(self::SUITE, 'Z301'), $from, 1)['final_price']);
    }

    /** وكلُّ غرفةٍ داخل النوع تأخذ سعرَه — ستُّ غرفٍ فردية بسطرٍ واحد. */
    public function test_every_room_inside_a_kind_takes_that_kinds_price(): void
    {
        $this->priceFor(self::SINGLE, 600);
        $this->priceFor(self::DOUBLE, 900);

        $from = Carbon::today()->addDay()->setTime(14, 0);

        foreach (['Z101', 'Z102', 'Z103', 'Z104', 'Z105', 'Z106'] as $code) {
            $this->assertSame(
                600.0,
                (float) $this->stay($this->roomOf(self::SINGLE, $code), $from, 1)['final_price'],
                "الغرفة {$code} لم تأخذ سعر نوعها"
            );
        }

        $this->assertSame(900.0, (float) $this->stay($this->roomOf(self::DOUBLE, 'Z201'), $from, 1)['final_price']);
    }

    /** والنوعُ يحمل سعرَه عبر الليالى كلِّها. */
    public function test_a_kinds_price_carries_across_the_whole_stay(): void
    {
        $this->priceFor(self::SUITE, 2000);
        $room = $this->roomOf(self::SUITE, 'Z301');

        $this->assertSame(6000.0, (float) $this->stay($room, Carbon::today()->addDay()->setTime(14, 0), 3)['final_price']);
    }
}
