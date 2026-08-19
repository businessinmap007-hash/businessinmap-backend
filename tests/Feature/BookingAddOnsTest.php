<?php

namespace Tests\Feature;

use App\Models\BookableItem;
use App\Models\BusinessServicePrice;
use App\Models\PlatformService;
use App\Models\User;
use App\Services\ServiceExecutionEngine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * «هل الافضل اخراج افطار فقط او اقامة كاملة وتسعيرها منفصل بدلا من ادخالها
 * كلها مره، اما اطلالة بحرية او على المسبح تكون مع الغرف منفردة لان ممكن غرفة
 * D117 تكون على المسبح و D118 تطل على البحر» — المالك، 2026-08-20.
 *
 * الشيئان كانا فى بطاقتين متجاورتين على شاشة الوحدة، فبدَوَا نوعين من شىءٍ
 * واحد. وهما يفترقان فى نطاقهما لا فى شكلهما:
 *
 *   الإطلالة  تخصّ غرفةً بعينها. D117 على المسبح وD118 على البحر، وهما من
 *             نفس النوع وبنفس سطر السعر. فمكانُها شاشةُ الوحدة.
 *
 *   الوجبات   قرارُ النزيل، وهو نفسُه فى كل غرفة. تُكتب مرّةً وتُعرض على
 *             الجميع. فمكانُها شاشةُ «إضافات الحجز».
 *
 * وكلاهما يسكن `offering_options` على سطر السعر، فالفصلُ بينهما ليس عمودًا
 * جديدًا: الصفةُ ما أعلنته وحدةٌ عن نفسها، والإضافةُ ما لم تعلنه. وهو نفسُ
 * الفصل الذى تقرأ به واجهةُ العميل منذ أن بُنيت.
 */
class BookingAddOnsTest extends TestCase
{
    use DatabaseTransactions;

    private const ROOT = 24;
    private const CHILD = 536;
    private const ITEM_TYPE = 'booking_stay';

    private const SINGLE = 965;
    private const DOUBLE = 966;
    private const BREAKFAST = 855;
    private const FULL_BOARD = 856;

    private User $hotel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = User::create([
            'name' => 'Zz AddOns ' . uniqid(),
            'email' => 'zz-addons-' . uniqid() . '@test.local',
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
        $row = BusinessServicePrice::create([
            'business_id' => $this->hotel->id,
            'child_id' => self::CHILD,
            'service_id' => $this->serviceId(),
            'bookable_item_type' => self::ITEM_TYPE,
            'line_option_id' => $kind,
            'price' => $price,
            'currency' => 'EGP',
            'is_active' => 1,
        ]);

        $row->syncOfferingOptions($kind ?: null, []);

        return $row->refresh();
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

    private function saveAddOns(array $payload)
    {
        return $this->actingAs($this->hotel)
            ->put(route('business.booking-add-ons.update', [], false), $payload);
    }

    /** ما يدفعه النزيل على فترةٍ واحدة، باختياراته وعددِ رفقته. */
    private function nightly(BookableItem $room, array $chosen = [], int $people = 1): float
    {
        $engine = app(ServiceExecutionEngine::class);
        $price = app(\App\Services\BusinessServicePriceResolver::class)->resolveForBookableItem($room);

        return (float) $engine->resolvePriceBreakdown(
            service: PlatformService::findOrFail($price->service_id),
            businessPrice: $price,
            bookable: $room->fresh(),
            quantity: 1,
            pricingDate: now(),
            optionIds: $engine->withUnitOwnOptions($room->fresh(), $chosen),
            partySize: $people
        )['final_price'];
    }

    private function adjustmentsOf(int $kind): array
    {
        return BusinessServicePrice::query()
            ->where('business_id', $this->hotel->id)
            ->where('line_option_id', $kind)
            ->firstOrFail()
            ->currentOfferingAdjustments();
    }

    /*
    |--------------------------------------------------------------------------
    | الشاشة
    |--------------------------------------------------------------------------
    */

    /** الشاشةُ موجودة، وتقول على أىِّ الأنواع ستُكتب. */
    public function test_the_screen_lists_the_kinds_it_will_write_on(): void
    {
        $this->priceFor(self::SINGLE, 600);
        $this->priceFor(self::DOUBLE, 900);

        $html = $this->actingAs($this->hotel)
            ->get(route('business.booking-add-ons.index', [], false))
            ->assertOk()->getContent();

        $this->assertStringContainsString('غرفة فردية', $html);
        $this->assertStringContainsString('غرفة مزدوجة', $html);
        $this->assertStringContainsString('name="option_ids[]"', $html);
    }

    /** والشجرةُ الجانبية تدلّ عليها بجوار «وحداتي». */
    public function test_the_sidebar_points_at_it(): void
    {
        app()->setLocale('ar');
        auth()->setUser($this->hotel);

        $menu = view('business.layouts._partials.menu')->render();

        $this->assertStringContainsString(route('business.booking-add-ons.index', [], false), $menu);
    }

    /** ولا تُكتب إضافةٌ بلا سعرٍ تُكتب عليه — وتقول ذلك بدل أن تصمت. */
    public function test_it_refuses_when_nothing_is_priced_yet(): void
    {
        $this->saveAddOns([
            'option_ids' => [self::BREAKFAST],
            'adjust' => [self::BREAKFAST => 50],
        ])->assertSessionHasErrors('option_ids');
    }

    /*
    |--------------------------------------------------------------------------
    | مرّةً واحدة، على الجميع
    |--------------------------------------------------------------------------
    */

    /** «إفطار +٥٠» تُكتب مرّةً فتصل كلَّ نوعٍ عنده. */
    public function test_one_save_reaches_every_kind(): void
    {
        $this->priceFor(self::SINGLE, 600);
        $this->priceFor(self::DOUBLE, 900);

        $this->saveAddOns([
            'option_ids' => [self::BREAKFAST],
            'adjust' => [self::BREAKFAST => 50],
            'adjust_type' => [self::BREAKFAST => 'amount'],
        ])->assertRedirect();

        foreach ([self::SINGLE, self::DOUBLE] as $kind) {
            $this->assertSame(50.0, $this->adjustmentsOf($kind)[self::BREAKFAST]['value']);
        }
    }

    /** «فردى ٦٠٠، وإفطار ٥٠، فالمجموع ٦٥٠» — وهو الطلب حرفيًّا. */
    public function test_the_guest_pays_for_what_they_choose(): void
    {
        $this->priceFor(self::SINGLE, 600);
        $room = $this->roomOf(self::SINGLE, 'Z101');

        $this->saveAddOns([
            'option_ids' => [self::BREAKFAST],
            'adjust' => [self::BREAKFAST => 50],
        ])->assertRedirect();

        $this->assertSame(600.0, $this->nightly($room), 'بلا إفطار');
        $this->assertSame(650.0, $this->nightly($room, [self::BREAKFAST]));
    }

    /** ولكلٍّ سعرُه: الإفطارُ ٥٠ والإقامةُ الكاملة ١٥٠. */
    public function test_each_add_on_is_priced_separately(): void
    {
        $this->priceFor(self::SINGLE, 600);
        $room = $this->roomOf(self::SINGLE, 'Z101');

        $this->saveAddOns([
            'option_ids' => [self::BREAKFAST, self::FULL_BOARD],
            'adjust' => [self::BREAKFAST => 50, self::FULL_BOARD => 150],
        ])->assertRedirect();

        $this->assertSame(650.0, $this->nightly($room, [self::BREAKFAST]));
        $this->assertSame(750.0, $this->nightly($room, [self::FULL_BOARD]));
    }

    /** ولا تُثبَّت على الغرفة: تُعرض عليه ليقرّر. */
    public function test_an_add_on_is_not_stamped_on_any_room(): void
    {
        $this->priceFor(self::SINGLE, 600);
        $room = $this->roomOf(self::SINGLE, 'Z101');

        $this->saveAddOns([
            'option_ids' => [self::BREAKFAST],
            'adjust' => [self::BREAKFAST => 50],
        ])->assertRedirect();

        $this->assertNotContains(self::BREAKFAST, $room->fresh()->modifierOptionIds()->all());
        $this->assertSame(600.0, $this->nightly($room), 'الإفطارُ حُسب بلا أن يُطلَب');
    }

    /** وترتفع قيمتُها كما يرتفع أىُّ سعر، وتُرفع بنزع علامتها. */
    public function test_an_add_on_can_be_repriced_and_removed(): void
    {
        $this->priceFor(self::SINGLE, 600);

        $this->saveAddOns([
            'option_ids' => [self::BREAKFAST, self::FULL_BOARD],
            'adjust' => [self::BREAKFAST => 50, self::FULL_BOARD => 150],
        ])->assertRedirect();

        $this->saveAddOns([
            'option_ids' => [self::BREAKFAST],
            'adjust' => [self::BREAKFAST => 75],
        ])->assertRedirect();

        $adjustments = $this->adjustmentsOf(self::SINGLE);

        $this->assertSame(75.0, $adjustments[self::BREAKFAST]['value']);
        $this->assertArrayNotHasKey(self::FULL_BOARD, $adjustments, 'الإقامةُ الكاملة بقيت بعد نزع علامتها');
    }

    /*
    |--------------------------------------------------------------------------
    | لكل فرد
    |--------------------------------------------------------------------------
    */

    /**
     * «ليس الافطار فى الغرفة الفردى مثل الغرفة الثلاثية».
     *
     * الزيادةُ كانت على الوحدة دائمًا، فخمسون على الغرفة سواءٌ نزل فيها واحدٌ
     * أو ثلاثة. والطعامُ يُؤكل بالأفواه لا بالغرف.
     */
    public function test_a_per_person_add_on_is_multiplied_by_the_guests(): void
    {
        $this->priceFor(self::SINGLE, 600);
        $room = $this->roomOf(self::SINGLE, 'Z101');

        $this->saveAddOns([
            'option_ids' => [self::BREAKFAST],
            'adjust' => [self::BREAKFAST => 50],
            'per_person' => [self::BREAKFAST => 1],
        ])->assertRedirect();

        $this->assertSame(650.0, $this->nightly($room, [self::BREAKFAST], 1), 'نزيلٌ واحد');
        $this->assertSame(750.0, $this->nightly($room, [self::BREAKFAST], 3), 'ثلاثةُ نزلاء');
    }

    /** وما ليس «لكل فرد» لا يُضرب: البحرُ لا يُقسَّم على من فى الغرفة. */
    public function test_an_add_on_that_is_not_per_person_ignores_the_guest_count(): void
    {
        $this->priceFor(self::SINGLE, 600);
        $room = $this->roomOf(self::SINGLE, 'Z101');

        $this->saveAddOns([
            'option_ids' => [self::BREAKFAST],
            'adjust' => [self::BREAKFAST => 50],
        ])->assertRedirect();

        $this->assertSame(650.0, $this->nightly($room, [self::BREAKFAST], 3));
    }

    /** والنسبةُ لكل فردٍ تُقرأ كما تُكتب: «١٠٪ لكل فرد» ثلاثون لثلاثة. */
    public function test_a_per_person_percent_scales_with_the_guests(): void
    {
        $this->priceFor(self::SINGLE, 600);
        $room = $this->roomOf(self::SINGLE, 'Z101');

        $this->saveAddOns([
            'option_ids' => [self::BREAKFAST],
            'adjust' => [self::BREAKFAST => 10],
            'adjust_type' => [self::BREAKFAST => 'percent'],
            'per_person' => [self::BREAKFAST => 1],
        ])->assertRedirect();

        $this->assertSame(780.0, $this->nightly($room, [self::BREAKFAST], 3), '٦٠٠ + ٣×٦٠');
    }

    /** ويُحفظ العلمُ فيُقرأ حين تُفتح الشاشةُ ثانيةً. */
    public function test_the_per_person_flag_survives_a_reload(): void
    {
        $this->priceFor(self::SINGLE, 600);

        $this->saveAddOns([
            'option_ids' => [self::BREAKFAST],
            'adjust' => [self::BREAKFAST => 50],
            'per_person' => [self::BREAKFAST => 1],
        ])->assertRedirect();

        $this->assertTrue($this->adjustmentsOf(self::SINGLE)[self::BREAKFAST]['per_person']);
    }

    /*
    |--------------------------------------------------------------------------
    | وأكثرُ من إضافة
    |--------------------------------------------------------------------------
    */

    /** «هناك بعض الحالات سيكون اكتر من اضافة» — تُجمَع لا تتنافس. */
    public function test_several_add_ons_are_charged_together(): void
    {
        $this->priceFor(self::SINGLE, 600);
        $room = $this->roomOf(self::SINGLE, 'Z101');

        $this->saveAddOns([
            'option_ids' => [self::BREAKFAST, self::FULL_BOARD],
            'adjust' => [self::BREAKFAST => 50, self::FULL_BOARD => 150],
        ])->assertRedirect();

        $this->assertSame(800.0, $this->nightly($room, [self::BREAKFAST, self::FULL_BOARD]));
    }

    /** ولا شىءَ منها خيارٌ أيضًا: «وارد ان احجز فى فندق ولا اريد حتى الافطار». */
    public function test_choosing_none_of_them_costs_nothing_extra(): void
    {
        $this->priceFor(self::SINGLE, 600);
        $room = $this->roomOf(self::SINGLE, 'Z101');

        $this->saveAddOns([
            'option_ids' => [self::BREAKFAST, self::FULL_BOARD],
            'adjust' => [self::BREAKFAST => 50, self::FULL_BOARD => 150],
        ])->assertRedirect();

        $this->assertSame(600.0, $this->nightly($room, []));
    }

    /*
    |--------------------------------------------------------------------------
    | الحدُّ بين الشاشتين
    |--------------------------------------------------------------------------
    */

    /**
     * حفظُ الإضافات لا يمحو إطلالةَ غرفة.
     *
     * `syncOfferingOptions` تمسح ثم تكتب، وهذه الشاشةُ لا تعرض الإطلالةَ ولا
     * تُسأل عنها — فلو كتبت ما عندها وحده لسقط سعرُ الإطلالة من كل غرفةٍ تطلّ
     * على البحر، صامتًا.
     */
    public function test_saving_add_ons_keeps_a_rooms_own_view(): void
    {
        $price = $this->priceFor(self::SINGLE, 600);
        $room = $this->roomOf(self::SINGLE, 'D118');

        $price->syncOfferingOptions(self::SINGLE, [self::FULL_BOARD], [
            self::FULL_BOARD => ['type' => 'amount', 'value' => 100],
        ]);
        $room->syncOfferingOptions(self::SINGLE, [self::FULL_BOARD]);

        $this->assertSame(700.0, $this->nightly($room), 'الإطلالةُ قبل الحفظ');

        $this->saveAddOns([
            'option_ids' => [self::BREAKFAST],
            'adjust' => [self::BREAKFAST => 50],
        ])->assertRedirect();

        $this->assertSame(700.0, $this->nightly($room), 'الإطلالةُ مُحيت من شاشة الإضافات');
        $this->assertSame(750.0, $this->nightly($room, [self::BREAKFAST]));
    }

    /** وحفظُ صفةِ غرفةٍ لا يمحو نظامَ الوجبات — نفسُ الحرص معكوسًا. */
    public function test_saving_a_rooms_view_keeps_the_add_ons(): void
    {
        $this->priceFor(self::SINGLE, 600);
        $room = $this->roomOf(self::SINGLE, 'D117');

        $this->saveAddOns([
            'option_ids' => [self::BREAKFAST],
            'adjust' => [self::BREAKFAST => 50],
        ])->assertRedirect();

        $this->actingAs($this->hotel)->post(
            route('business.bookable-items.pricing.store', $room->id, false),
            [
                'price' => 600,
                'option_ids' => [self::FULL_BOARD],
                'option_adjust' => [self::FULL_BOARD => 100],
            ]
        )->assertRedirect();

        $this->assertSame(700.0, $this->nightly($room), 'الغرفةُ تحمل صفتَها');
        $this->assertSame(750.0, $this->nightly($room, [self::BREAKFAST]), 'الإفطارُ مُحى من شاشة الوحدة');
    }

    /**
     * وغرفتان من نوعٍ واحد، لكلٍّ إطلالتُها.
     *
     * وهذا هو السببُ الذى لأجله بقيت الإطلالةُ على شاشة الوحدة: D117 على
     * المسبح وD118 على البحر، وسطرُ سعرهما واحد.
     */
    public function test_two_rooms_of_one_kind_carry_different_views(): void
    {
        $price = $this->priceFor(self::SINGLE, 600);

        $pool = $this->roomOf(self::SINGLE, 'D117');
        $sea = $this->roomOf(self::SINGLE, 'D118');

        $price->syncOfferingOptions(self::SINGLE, [self::BREAKFAST, self::FULL_BOARD], [
            self::BREAKFAST => ['type' => 'amount', 'value' => 60],
            self::FULL_BOARD => ['type' => 'amount', 'value' => 120],
        ]);

        $pool->syncOfferingOptions(self::SINGLE, [self::BREAKFAST]);
        $sea->syncOfferingOptions(self::SINGLE, [self::FULL_BOARD]);

        $this->assertSame(660.0, $this->nightly($pool));
        $this->assertSame(720.0, $this->nightly($sea));
    }

    /** وما أعلنته وحدةٌ عن نفسها لا يُعرض إضافةً — ثمنُه محسوبٌ مرّةً. */
    public function test_what_a_room_declares_is_not_offered_as_an_add_on(): void
    {
        $price = $this->priceFor(self::SINGLE, 600);
        $room = $this->roomOf(self::SINGLE, 'D118');

        $price->syncOfferingOptions(self::SINGLE, [self::FULL_BOARD], [
            self::FULL_BOARD => ['type' => 'amount', 'value' => 100],
        ]);
        $room->syncOfferingOptions(self::SINGLE, [self::FULL_BOARD]);

        $html = $this->actingAs($this->hotel)
            ->get(route('business.booking-add-ons.index', [], false))
            ->assertOk()->getContent();

        $this->assertStringContainsString('is-declared', $html, 'الصفةُ معروضةٌ كإضافةٍ عادية');

        // وحتى لو أُرسلت، لا تُقبل إضافةً: هى صفةُ وحدة.
        $this->saveAddOns([
            'option_ids' => [self::FULL_BOARD],
            'adjust' => [self::FULL_BOARD => 999],
        ])->assertRedirect();

        $this->assertSame(100.0, $this->adjustmentsOf(self::SINGLE)[self::FULL_BOARD]['value']);
    }

    /*
    |--------------------------------------------------------------------------
    | ما يصل النزيل
    |--------------------------------------------------------------------------
    */

    /** والقائمةُ تعرض الإضافاتِ بأسعارها، كما يُقرأ أىُّ موقع حجز. */
    public function test_the_customer_list_offers_the_add_ons(): void
    {
        $this->priceFor(self::SINGLE, 600);
        $this->roomOf(self::SINGLE, 'Z101');

        $this->saveAddOns([
            'option_ids' => [self::BREAKFAST],
            'adjust' => [self::BREAKFAST => 50],
        ])->assertRedirect();

        $kind = $this->getJson('/api/v2/discovery/units/' . $this->hotel->id)
            ->assertOk()->json('data.kinds.0');

        $this->assertCount(1, $kind['choices']);
        $this->assertSame(self::BREAKFAST, $kind['choices'][0]['option_id']);
        $this->assertSame(50.0, (float) $kind['choices'][0]['amount']);
    }

    /** ولا يكتب تاجرٌ إضافاتِ تاجرٍ آخر. */
    public function test_it_only_writes_the_signed_in_merchants_rows(): void
    {
        $this->priceFor(self::SINGLE, 600);

        $other = User::create([
            'name' => 'Zz Other ' . uniqid(),
            'email' => 'zz-other-' . uniqid() . '@test.local',
            'phone' => '01' . random_int(100000000, 999999999),
            'password' => Hash::make('Passw0rdTest'),
            'type' => User::TYPE_BUSINESS,
            'category_id' => self::ROOT,
            'category_child_id' => self::CHILD,
            'api_token' => 'zz' . uniqid() . bin2hex(random_bytes(8)),
        ]);

        $theirRow = BusinessServicePrice::create([
            'business_id' => $other->id,
            'child_id' => self::CHILD,
            'service_id' => $this->serviceId(),
            'bookable_item_type' => self::ITEM_TYPE,
            'line_option_id' => self::SINGLE,
            'price' => 400,
            'currency' => 'EGP',
            'is_active' => 1,
        ]);

        $this->saveAddOns([
            'option_ids' => [self::BREAKFAST],
            'adjust' => [self::BREAKFAST => 50],
        ])->assertRedirect();

        $this->assertSame([], $theirRow->fresh()->currentOfferingAdjustments());
    }
}
