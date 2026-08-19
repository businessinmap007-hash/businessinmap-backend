<?php

namespace Tests\Feature;

use App\Models\BusinessServicePrice;
use App\Models\OfferingOption;
use App\Models\PlatformService;
use App\Models\User;
use App\Services\ServiceExecutionEngine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «سعر للغرفة الخاصة مع بلايستشن 4 وسعر ل 5 وايضا اضافة سعر على الشاشة
 * الكبيرة» — المالك، 2026-08-19.
 *
 * الأدوارُ الثلاثة كانت تقول ما يُباع وما يوصِّفه، ثم يقف الأمر: المُوصِّفُ
 * يوصِّف ولا يُسعِّر. فمن أراد سعرين لغرفةٍ بجهازين اضطُرّ إلى سطرين.
 */
class ModifierPricingTest extends TestCase
{
    use DatabaseTransactions;

    private function price(float $base = 100): BusinessServicePrice
    {
        $business = User::query()->where('type', User::TYPE_BUSINESS)->firstOrFail();

        $serviceId = (int) PlatformService::query()
            ->where('key', PlatformService::KEY_BOOKING)->where('is_active', 1)->value('id');

        return BusinessServicePrice::create([
            'business_id' => $business->id,
            'child_id' => $business->category_child_id,
            'service_id' => $serviceId,
            'bookable_item_type' => 'booking_time',
            'price' => $base,
            'currency' => 'EGP',
            'is_active' => 1,
        ]);
    }

    private function option(string $name): int
    {
        return (int) DB::table('options')->where('name_ar', $name)->value('id')
            ?: (int) DB::table('options')->value('id');
    }

    private function breakdown(BusinessServicePrice $price, array $optionIds, int $quantity = 1): array
    {
        return app(ServiceExecutionEngine::class)->resolvePriceBreakdown(
            service: PlatformService::findOrFail($price->service_id),
            businessPrice: $price,
            bookable: null,
            quantity: $quantity,
            pricingDate: now(),
            optionIds: $optionIds
        );
    }

    /** «شاشة كبيرة +٢٠» — تُضاف إلى سعر الوحدة. */
    public function test_a_modifier_can_carry_a_surcharge(): void
    {
        $price = $this->price(100);
        $screen = $this->option('شاشة كبيرة');

        $price->syncOfferingOptions(null, [$screen], [$screen => ['type' => 'amount', 'value' => 20]]);

        $this->assertSame(100.0, $this->breakdown($price, [])['final_price'], 'بلا اختيار، لا زيادة');
        $this->assertSame(120.0, $this->breakdown($price, [$screen])['final_price']);
    }

    /** وعلى كل وحدة، لا على الحجز كلِّه: ساعتان بشاشةٍ كبيرة = ٢×(١٠٠+٢٠). */
    public function test_the_surcharge_is_per_unit_not_per_booking(): void
    {
        $price = $this->price(100);
        $screen = $this->option('شاشة كبيرة');

        $price->syncOfferingOptions(null, [$screen], [$screen => ['type' => 'amount', 'value' => 20]]);

        $this->assertSame(240.0, $this->breakdown($price, [$screen], 2)['final_price']);
    }

    /** والنسبةُ من السعر الأصلىّ، فلا يغيّر ترتيبُ الاختيار الناتج. */
    public function test_a_percent_is_taken_from_the_base_not_the_running_total(): void
    {
        $price = $this->price(100);
        $screen = $this->option('شاشة كبيرة');
        $vr = $this->option('نظارة واقع افتراضي');

        $price->syncOfferingOptions(null, [$screen, $vr], [
            $screen => ['type' => 'amount', 'value' => 20],
            $vr => ['type' => 'percent', 'value' => 10],
        ]);

        // ١٠٠ + ٢٠ + (١٠٪ من ١٠٠) = ١٣٠ — بأىِّ ترتيب.
        $this->assertSame(130.0, $this->breakdown($price, [$screen, $vr])['final_price']);
        $this->assertSame(130.0, $this->breakdown($price, [$vr, $screen])['final_price']);
    }

    /** والقيمةُ قد تكون سالبة: «بلايستيشن ٤» أرخصُ من «٥». */
    public function test_a_modifier_may_lower_the_price(): void
    {
        $price = $this->price(60);
        $ps4 = $this->option('بلايستيشن ٤');

        $price->syncOfferingOptions(null, [$ps4], [$ps4 => ['type' => 'amount', 'value' => -20]]);

        $this->assertSame(40.0, $this->breakdown($price, [$ps4])['final_price']);
    }

    /** ولا يهبط السعر تحت الصفر مهما بلغت الخصومات. */
    public function test_the_price_never_goes_below_zero(): void
    {
        $price = $this->price(30);
        $ps4 = $this->option('بلايستيشن ٤');

        $price->syncOfferingOptions(null, [$ps4], [$ps4 => ['type' => 'amount', 'value' => -500]]);

        $this->assertSame(0.0, $this->breakdown($price, [$ps4])['final_price']);
    }

    /** ومُوصِّفٌ لم يُعلَّق على هذا السطر لا يُسعِّره — ولو أرسله العميل. */
    public function test_an_option_this_price_never_declared_changes_nothing(): void
    {
        $price = $this->price(100);
        $stranger = $this->option('شاشة كبيرة');

        $this->assertSame(100.0, $this->breakdown($price, [$stranger])['final_price']);
    }

    /** ومُوصِّفٌ بلا سعرٍ يوصِّف ولا يظهر فى التفصيل. */
    public function test_a_modifier_with_no_surcharge_is_not_a_line_in_the_breakdown(): void
    {
        $price = $this->price(100);
        $screen = $this->option('شاشة كبيرة');

        $price->syncOfferingOptions(null, [$screen]);

        $breakdown = $this->breakdown($price, [$screen]);

        $this->assertSame(100.0, $breakdown['final_price']);
        $this->assertSame([], $breakdown['modifiers']);
    }

    /** والتفصيل يسمّى ما زاد، فيُقرأ على الفاتورة. */
    public function test_the_breakdown_names_what_was_added(): void
    {
        $price = $this->price(100);
        $screen = $this->option('شاشة كبيرة');

        $price->syncOfferingOptions(null, [$screen], [$screen => ['type' => 'amount', 'value' => 20]]);

        $breakdown = $this->breakdown($price, [$screen]);

        $this->assertSame(100.0, $breakdown['base_unit_price']);
        $this->assertSame(20.0, $breakdown['modifiers_total']);
        $this->assertSame('شاشة كبيرة', $breakdown['modifiers'][0]['name']);
        $this->assertSame(20.0, $breakdown['modifiers'][0]['amount']);
    }

    /**
     * وحفظٌ لا يعرف الأسعار لا يمحوها.
     *
     * `syncOfferingOptions` تمسح ثم تكتب، فشاشةُ الأدمن — التى لا تعرض خانات
     * الزيادة — كانت ستمحو سعرًا كتبه صاحبُ المحل بيده، صامتًا.
     */
    public function test_a_save_that_knows_nothing_of_surcharges_preserves_them(): void
    {
        $price = $this->price(100);
        $screen = $this->option('شاشة كبيرة');

        $price->syncOfferingOptions(null, [$screen], [$screen => ['type' => 'amount', 'value' => 20]]);

        $price->syncOfferingOptions(
            null,
            $price->modifierOptions()->pluck('id')->all(),
            $price->currentOfferingAdjustments()
        );

        $this->assertSame(120.0, $this->breakdown($price, [$screen])['final_price']);
    }

    /** والمنصّة تعرف نوعين لا أكثر، ونوعٌ مجهول يُقرأ مبلغًا. */
    public function test_an_unknown_adjust_type_falls_back_to_a_flat_amount(): void
    {
        $this->assertSame(
            [OfferingOption::ADJUST_AMOUNT, OfferingOption::ADJUST_PERCENT],
            OfferingOption::adjustTypes()
        );

        $price = $this->price(100);
        $screen = $this->option('شاشة كبيرة');

        $price->syncOfferingOptions(null, [$screen], [$screen => ['type' => 'nonsense', 'value' => 20]]);

        $this->assertSame(120.0, $this->breakdown($price, [$screen])['final_price']);
    }
}
