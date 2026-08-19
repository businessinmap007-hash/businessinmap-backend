<?php

namespace Tests\Feature;

use App\Models\BookableItem;
use App\Models\OfferingOption;
use App\Models\PlatformService;
use App\Models\User;
use App\Services\MerchantOfferingVocabulary;
use App\Services\ServiceExecutionEngine;
use App\Models\BusinessServicePrice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «٦ غرف فردى و١٠ غرف زوجى و٥ أجنحة ويضيف لكل منه الاسم من ١٠١ إلى ١٠٦ ومن
 * ١٠٧ إلى ١١١» — المالك، 2026-08-19.
 *
 * النموذجُ كان يحتمل ذلك كلَّه، والإدخالُ وحدةً وحدة: واحدٌ وعشرون نموذجًا لا
 * يتغيّر بينها إلا رقم.
 */
class BulkBookableUnitsTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * بادئةٌ لا يستعملها أحد.
     *
     * الاختبارات تجرى على قاعدةٍ حيّة، وفندقُها يحمل بالفعل وحدتين مكوّدتين
     * «101» — و`code` بلا قيد تفرُّد. فمدًى عارٍ كان يقيس بيانات المالك لا
     * عملَ الشاشة.
     */
    private string $prefix;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prefix = 'T' . uniqid() . '-';
    }

    private function hotel(): User
    {
        $owner = User::query()->where('type', User::TYPE_BUSINESS)
            ->where('category_child_id', 536)->first();

        return $owner ?: $this->markTestSkipped('لا حساب فندقٍ قائم.');
    }

    private function bookingContext(User $hotel): array
    {
        $serviceId = (int) PlatformService::query()
            ->where('key', PlatformService::KEY_BOOKING)->where('is_active', 1)->value('id');

        $config = DB::table('category_service_configs')
            ->where('child_id', $hotel->category_child_id)
            ->where('category_id', $hotel->category_id)
            ->where('platform_service_id', $serviceId)->where('is_active', 1)->value('config');

        $types = json_decode((string) $config, true)['allowed_item_types'] ?? [];

        if ($types === []) {
            $this->markTestSkipped('الفندق بلا أنواع عناصر.');
        }

        return [$serviceId, $types[0]];
    }

    private function submit(User $hotel, array $payload)
    {
        [$serviceId, $itemType] = $this->bookingContext($hotel);

        return $this->actingAs($hotel)->post(
            route('business.bookable-items.bulk.store', [], false),
            $payload + [
                'service_id' => $serviceId,
                'item_type' => $itemType,
                'prefix' => $this->prefix,
            ]
        );
    }

    /** المدى يُنشأ كلُّه فى حفظةٍ واحدة. */
    public function test_a_range_is_created_in_one_save(): void
    {
        $hotel = $this->hotel();

        $this->submit($hotel, ['from' => 101, 'to' => 106])->assertRedirect();

        $codes = BookableItem::query()->where('business_id', $hotel->id)
            ->where('code', 'like', $this->prefix . '%')
            ->pluck('code')->sort()->values()->all();

        $this->assertSame(
            array_map(fn ($n) => $this->prefix . $n, [101, 102, 103, 104, 105, 106]),
            $codes
        );
    }

    /** والموجودُ يُتخطّى ولا يُرفض — من أعاد المدى يقصد الناقص. */
    public function test_existing_codes_are_skipped_rather_than_failing_the_batch(): void
    {
        $hotel = $this->hotel();

        $this->submit($hotel, ['from' => 201, 'to' => 203])->assertRedirect();
        $this->submit($hotel, ['from' => 201, 'to' => 205])->assertRedirect();

        $this->assertSame(
            5,
            BookableItem::query()->where('business_id', $hotel->id)
                ->where('code', 'like', $this->prefix . '%')->count(),
            'الدفعة الثانية أنشأت مكرّرًا أو رفضت الكل'
        );
    }

    /** والبادئةُ وعددُ الخانات يكتبان الكود كما يكتبه صاحبُه. */
    public function test_a_prefix_and_padding_shape_the_code(): void
    {
        $hotel = $this->hotel();

        $this->submit($hotel, ['from' => 1, 'to' => 3, 'pad' => 3])->assertRedirect();

        $this->assertSame(
            array_map(fn ($n) => $this->prefix . $n, ['001', '002', '003']),
            BookableItem::query()->where('business_id', $hotel->id)
                ->where('code', 'like', $this->prefix . '%')->pluck('code')->sort()->values()->all()
        );
    }

    /** ومدًى مقلوبٌ يُرفض قبل أن يكتب شيئًا. */
    public function test_a_backwards_range_is_refused(): void
    {
        $hotel = $this->hotel();

        $this->submit($hotel, ['from' => 300, 'to' => 299])->assertSessionHasErrors('to');

        $this->assertSame(0, BookableItem::query()->where('business_id', $hotel->id)
            ->where('code', 'like', $this->prefix . '%')->count());
    }

    /** وخطأٌ مطبعىٌّ فى «إلى» لا يصنع ألف غرفة. */
    public function test_an_oversized_range_is_refused(): void
    {
        $hotel = $this->hotel();

        $this->submit($hotel, ['from' => 1, 'to' => 5000])->assertSessionHasErrors('to');
    }

    /**
     * وصفاتُ الوحدة تُحفظ عليها — «إطلالة بحرية» على الستّ جميعًا.
     */
    public function test_the_batch_carries_the_options_it_was_given(): void
    {
        $hotel = $this->hotel();

        $modifierId = collect(app(MerchantOfferingVocabulary::class)
            ->for((int) $hotel->id, (int) $hotel->category_child_id, (int) $hotel->category_id)['modifiers'])
            ->flatten()->pluck('id')->first();

        if (! $modifierId) {
            $this->markTestSkipped('لا مُوصِّفات فى مفردات الفندق.');
        }

        $this->submit($hotel, ['from' => 401, 'to' => 403, 'option_ids' => [$modifierId]])->assertRedirect();

        foreach (['401', '402', '403'] as $n) {
            $code = $this->prefix . $n;
            $unit = BookableItem::query()->where('business_id', $hotel->id)->where('code', $code)->firstOrFail();

            $this->assertContains(
                (int) $modifierId,
                $unit->offeringOptions->where('role', OfferingOption::ROLE_MODIFIER)->pluck('option_id')->all(),
                "الوحدة {$code} لم تحمل صفتها"
            );
        }
    }

    /** وخيارٌ خارج مفردات التاجر لا يُكتب ولو أُرسل. */
    public function test_an_option_outside_the_merchants_vocabulary_is_dropped(): void
    {
        $hotel = $this->hotel();
        $stranger = (int) DB::table('options')->orderByDesc('id')->value('id');

        $this->submit($hotel, ['from' => 501, 'to' => 501, 'option_ids' => [$stranger]])->assertRedirect();

        $unit = BookableItem::query()->where('business_id', $hotel->id)
            ->where('code', $this->prefix . '501')->firstOrFail();

        $this->assertNotContains($stranger, $unit->offeringOptions->pluck('option_id')->all());
    }

    /**
     * وما تعلنه الغرفةُ عن نفسها يُسعَّر بلا أن يؤشّره النزيل.
     *
     * صفةٌ ثابتة فى الغرفة لا يملك العميلُ تغييرَها؛ مطالبتُه بتأشيرها تسأله
     * عمّا لا يقرّره، وتركُها تُسقط الزيادةَ من فاتورةٍ صحيحة.
     */
    public function test_a_units_own_option_prices_itself(): void
    {
        $hotel = $this->hotel();
        [$serviceId, $itemType] = $this->bookingContext($hotel);

        $modifierId = collect(app(MerchantOfferingVocabulary::class)
            ->for((int) $hotel->id, (int) $hotel->category_child_id, (int) $hotel->category_id)['modifiers'])
            ->flatten()->pluck('id')->first();

        if (! $modifierId) {
            $this->markTestSkipped('لا مُوصِّفات فى مفردات الفندق.');
        }

        $price = BusinessServicePrice::create([
            'business_id' => $hotel->id,
            'child_id' => $hotel->category_child_id,
            'service_id' => $serviceId,
            'bookable_item_type' => $itemType,
            'price' => 400,
            'charge_mode' => BusinessServicePrice::CHARGE_STANDARD,
            'currency' => 'EGP',
            'is_active' => 1,
        ]);

        $price->syncOfferingOptions(null, [(int) $modifierId], [
            (int) $modifierId => ['type' => 'amount', 'value' => 100],
        ]);

        $unit = BookableItem::create([
            'business_id' => $hotel->id,
            'service_id' => $serviceId,
            'item_type' => $itemType,
            'code' => $this->prefix . 'SEA-1',
            'quantity' => 1,
            'is_active' => 1,
        ]);

        $unit->syncOfferingOptions(null, [(int) $modifierId]);

        $engine = app(ServiceExecutionEngine::class);
        $service = PlatformService::findOrFail($serviceId);

        $bare = $engine->resolvePriceBreakdown($service, $price, null, 1, now(), []);
        $withUnit = $engine->resolvePriceBreakdown(
            $service,
            $price,
            null,
            1,
            now(),
            $engine->withUnitOwnOptions($unit->fresh(), [])
        );

        $this->assertSame(400.0, $bare['final_price']);
        $this->assertSame(500.0, $withUnit['final_price'], 'الغرفة لم تُسعِّر صفتها');
    }
}
