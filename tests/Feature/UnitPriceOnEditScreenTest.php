<?php

namespace Tests\Feature;

use App\Models\BookableItem;
use App\Models\BusinessServicePrice;
use App\Models\PlatformService;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * «عند فتح تعديل لا يمكننى تعديل السعر من ٦٠٠ الى ٧٠٠، وايضا الاضافات اقامة
 * كاملة وافطار» — المالك، 2026-08-20.
 *
 * السعرُ والإضافاتُ بُنيا فى شاشة الإضافة بالجملة وحدها، وهى شاشةُ إنشاء: لا
 * تُفتح إلا لتصنع وحداتٍ جديدة. فمن أضاف دفعتَه ثم أراد رفعَ السعر مئةً لم
 * يجد أين — شاشةُ تعديل الوحدة تحمل بياناتِها وصورَها وإغلاقَها وقواعدَ
 * مواسمها، ولا تحمل سعرَها.
 *
 * وهو نقضٌ للقاعدة التى بُنيت عليها الشاشةُ نفسها: كلُّ ما يخصّ غرفةً فى
 * مكانٍ واحد.
 */
class UnitPriceOnEditScreenTest extends TestCase
{
    use DatabaseTransactions;

    private const ROOT = 24;
    private const CHILD = 536;
    private const ITEM_TYPE = 'booking_stay';
    private const SINGLE = 965;
    private const BREAKFAST = 855;
    private const FULL_BOARD = 856;

    private User $hotel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hotel = User::create([
            'name' => 'Zz Edit ' . uniqid(),
            'email' => 'zz-edit-' . uniqid() . '@test.local',
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

    private function room(): BookableItem
    {
        return BookableItem::create([
            'business_id' => $this->hotel->id,
            'service_id' => $this->serviceId(),
            'item_type' => self::ITEM_TYPE,
            'line_option_id' => self::SINGLE,
            'code' => 'Z101',
            'quantity' => 1,
            'is_active' => 1,
        ]);
    }

    private function priceRow(float $price = 600): BusinessServicePrice
    {
        return BusinessServicePrice::create([
            'business_id' => $this->hotel->id,
            'child_id' => self::CHILD,
            'service_id' => $this->serviceId(),
            'bookable_item_type' => self::ITEM_TYPE,
            'line_option_id' => self::SINGLE,
            'price' => $price,
            'currency' => 'EGP',
            'is_active' => 1,
        ]);
    }

    private function currentPrice(): ?BusinessServicePrice
    {
        return BusinessServicePrice::query()
            ->where('business_id', $this->hotel->id)
            ->where('bookable_item_type', self::ITEM_TYPE)
            ->where('line_option_id', self::SINGLE)
            ->first();
    }

    private function editScreen(BookableItem $room): string
    {
        return $this->actingAs($this->hotel)
            ->get(route('business.bookable-items.edit', $room->id, false))
            ->assertOk()->getContent();
    }

    private function savePricing(BookableItem $room, array $payload)
    {
        return $this->actingAs($this->hotel)->post(
            route('business.bookable-items.pricing.store', $room->id, false),
            $payload
        );
    }

    /*
    |--------------------------------------------------------------------------
    | الشاشة
    |--------------------------------------------------------------------------
    */

    /** شاشةُ التعديل تعرض سعرَ هذا النوع، لا تُخفيه فى شاشةٍ أخرى. */
    public function test_the_edit_screen_shows_the_price_of_this_units_kind(): void
    {
        $this->priceRow(600);
        $html = $this->editScreen($this->room());

        $this->assertStringContainsString('name="price"', $html);
        $this->assertStringContainsString('600', $html);
    }

    /**
     * وتُفتح لنوعٍ لم يُسعَّر بعد — وهى الحالُ الأشيع اليوم.
     *
     * فارغةٌ لا مكسورة: الخانةُ تنتظر رقمًا، والشاشةُ تقول أنّ هذا سعرُ النوع
     * كلِّه. وحدةٌ بلا سعرٍ تُرفض عند الحجز، فالشاشةُ هى مكانُ إصلاح ذلك.
     */
    public function test_the_edit_screen_opens_for_a_kind_with_no_price_yet(): void
    {
        $room = $this->room();
        $html = $this->editScreen($room);

        $this->assertStringContainsString('name="price"', $html);
        $this->assertStringContainsString(route('business.bookable-items.pricing.store', $room->id, false), $html);
    }

    /*
    |--------------------------------------------------------------------------
    | الحفظ
    |--------------------------------------------------------------------------
    */

    /** «من ٦٠٠ إلى ٧٠٠» — هذا هو الطلب حرفيًّا. */
    public function test_the_price_can_be_raised_from_the_unit_screen(): void
    {
        $this->priceRow(600);
        $room = $this->room();

        $this->savePricing($room, ['price' => 700])->assertRedirect();

        $this->assertSame(700.0, (float) $this->currentPrice()->price);
    }

    /** ويُنشأ السطرُ إن لم يكن — وحدةٌ بلا سعرٍ لا تُباع. */
    public function test_a_price_is_created_when_the_kind_has_none(): void
    {
        $room = $this->room();

        $this->assertNull($this->currentPrice());

        $this->savePricing($room, ['price' => 850])->assertRedirect();

        $this->assertSame(850.0, (float) $this->currentPrice()->price);
    }

    /**
     * والشاشةُ تدلّ على مكان نظام الوجبات بدل أن تحمله.
     *
     * «إفطار» و«إقامة كاملة» نفسُهما فى كل غرفة، فكتابتُهما داخل غرفةٍ بعينها
     * تُوهم أنهما يخصّانها — وتجعل صاحبَها يعيد إدخالهما مع كل نوع.
     */
    public function test_the_screen_points_at_where_meal_plans_live(): void
    {
        $html = $this->editScreen($this->room());

        $this->assertStringContainsString(route('business.booking-add-ons.index', [], false), $html);
        $this->assertStringNotContainsString('name="choice_ids[]"', $html);
    }

    /** وصفةُ الغرفة تُثبَّت عليها، لا على السطر وحده. */
    public function test_a_room_attribute_is_stamped_on_the_unit(): void
    {
        $this->priceRow(600);
        $room = $this->room();

        $this->savePricing($room, [
            'price' => 600,
            'option_ids' => [self::FULL_BOARD],
            'option_adjust' => [self::FULL_BOARD => 100],
        ])->assertRedirect();

        $this->assertContains(self::FULL_BOARD, $room->fresh()->modifierOptionIds()->all());
    }

    /*
    |--------------------------------------------------------------------------
    | ما تقوله القوائم
    |--------------------------------------------------------------------------
    */

    /**
     * «وحداتى» تقول أىُّ وحدةٍ بلا سعر.
     *
     * وحدةٌ لا يُسعَّر نوعُها تُرفَض عند الحجز، ولم يكن فى القائمة ما يقوله —
     * فيظنّ صاحبُها كلَّ شىءٍ جاهزًا حتى يفشل حجزٌ حقيقىّ. وهو ما حدث: فندقٌ
     * سعّر المزدوجةَ والجناحَ ولم يسعّر الفردية، فرُفض حجزُ غرفةٍ فردية.
     */
    public function test_the_units_list_says_which_kind_has_no_price(): void
    {
        $room = $this->room();

        $html = $this->actingAs($this->hotel)
            ->get(route('business.bookable-items.index', [], false))
            ->assertOk()->getContent();

        $this->assertStringContainsString('بلا سعر', $html);

        // ثم يُسعَّر، فتختفى الكلمةُ ويظهر الرقم.
        $this->savePricing($room, ['price' => 640])->assertRedirect();

        $html = $this->actingAs($this->hotel)
            ->get(route('business.bookable-items.index', [], false))
            ->assertOk()->getContent();

        $this->assertStringNotContainsString('بلا سعر', $html);
        $this->assertStringContainsString('640', $html);
    }

    /** ولا يسعّر تاجرٌ وحدةَ تاجرٍ آخر. */
    public function test_one_merchant_cannot_price_another_merchants_unit(): void
    {
        $mine = $this->hotel;

        $this->hotel = User::create([
            'name' => 'Zz Other ' . uniqid(),
            'email' => 'zz-other-' . uniqid() . '@test.local',
            'phone' => '01' . random_int(100000000, 999999999),
            'password' => Hash::make('Passw0rdTest'),
            'type' => User::TYPE_BUSINESS,
            'category_id' => self::ROOT,
            'category_child_id' => self::CHILD,
            'api_token' => 'zz' . uniqid() . bin2hex(random_bytes(8)),
        ]);

        $theirRoom = $this->room();

        $this->actingAs($mine)->post(
            route('business.bookable-items.pricing.store', $theirRoom->id, false),
            ['price' => 999]
        )->assertNotFound();

        $this->assertNull($this->currentPrice());
    }
}
