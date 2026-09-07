<?php

namespace Tests\Feature;

use App\Models\BusinessServicePrice;
use App\Models\OfferingOptionGroupSetting;
use App\Models\PlatformService;
use App\Models\User;
use App\Services\MerchantOfferingVocabulary;
use App\Services\ServiceExecutionEngine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * توحيد `offering_options` (إضافات الحجز) مع نظام `selection_type`.
 *
 * `option_groups` تصنيفٌ عالمي (راجع الميجريشن)، فالإعلانُ «هذه المجموعة
 * راديو» يُكتب على صاحب العرض — سطرَ سعرٍ بعينه أو النشاطَ كلَّه، نفسَ نطاقَى
 * `offering_options` ذاتها — لا على المجموعة، تمامًا كما فُعل مع
 * `menu_item_extra_groups` من قبل. والضمانُ الحقيقى هنا سطرٌ لا واجهة: عميلٌ
 * يتخطّى الشاشةَ ويرسل خيارين من مجموعةٍ فرديّة يُرفَض هو الآخر.
 */
class BookingModifierGroupSelectionTest extends TestCase
{
    use DatabaseTransactions;

    private function business(): User
    {
        return User::query()->where('type', User::TYPE_BUSINESS)->firstOrFail();
    }

    private function price(User $business, float $base = 100): BusinessServicePrice
    {
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

    /**
     * خياران من نفس المجموعة، من مفردات هذا النشاط نفسه — لا أىَّ مجموعةٍ فى
     * المنصّة، فشاشةُ «الإضافات والمميزات» لا تعرض إلا ما يجوز لهذا النشاط.
     *
     * @return array{0:int,1:int,2:int}
     */
    private function twoOptionsInOneGroup(User $business): array
    {
        $vocabulary = app(MerchantOfferingVocabulary::class)
            ->for((int) $business->id, (int) $business->category_child_id, (int) $business->category_id);

        $group = collect($vocabulary['modifiers'])->first(fn ($options) => count($options) >= 2);

        $this->assertNotNull($group, 'محتاج نشاطًا حقيقيًّا له مجموعةُ مُوصِّفاتٍ من خيارين على الأقل ليُختبر عليه');

        $ids = collect($group)->pluck('id')->take(2)->all();

        return [(int) $ids[0], (int) $ids[1], (int) $group[0]->group_id];
    }

    private function breakdown(BusinessServicePrice $price, array $optionIds): array
    {
        return app(ServiceExecutionEngine::class)->resolvePriceBreakdown(
            service: PlatformService::findOrFail($price->service_id),
            businessPrice: $price,
            bookable: null,
            quantity: 1,
            pricingDate: now(),
            optionIds: $optionIds
        );
    }

    public function test_two_options_from_an_unmarked_group_may_both_be_picked(): void
    {
        $business = $this->business();
        $price = $this->price($business);
        [$a, $b] = $this->twoOptionsInOneGroup($business);

        $price->syncOfferingOptions(null, [$a, $b], [
            $a => ['type' => 'amount', 'value' => 10],
            $b => ['type' => 'amount', 'value' => 15],
        ]);

        $this->assertSame(125.0, $this->breakdown($price, [$a, $b])['final_price'], 'بلا إعلانٍ، الافتراض متعدد');
    }

    public function test_a_single_select_group_rejects_two_picks_on_the_price_line(): void
    {
        $business = $this->business();
        $price = $this->price($business);
        [$a, $b, $groupId] = $this->twoOptionsInOneGroup($business);

        $price->syncOfferingOptions(null, [$a, $b], [
            $a => ['type' => 'amount', 'value' => 10],
            $b => ['type' => 'amount', 'value' => 15],
        ]);

        OfferingOptionGroupSetting::query()->create([
            'offering_type' => $price->getMorphClass(),
            'offering_id' => $price->id,
            'option_group_id' => $groupId,
            'selection_type' => OfferingOptionGroupSetting::SELECTION_SINGLE,
        ]);

        $this->expectException(ValidationException::class);

        $this->breakdown($price, [$a, $b]);
    }

    public function test_a_single_select_group_still_allows_one_pick(): void
    {
        $business = $this->business();
        $price = $this->price($business);
        [$a, $b, $groupId] = $this->twoOptionsInOneGroup($business);

        $price->syncOfferingOptions(null, [$a, $b], [
            $a => ['type' => 'amount', 'value' => 10],
            $b => ['type' => 'amount', 'value' => 15],
        ]);

        OfferingOptionGroupSetting::query()->create([
            'offering_type' => $price->getMorphClass(),
            'offering_id' => $price->id,
            'option_group_id' => $groupId,
            'selection_type' => OfferingOptionGroupSetting::SELECTION_SINGLE,
        ]);

        $this->assertSame(110.0, $this->breakdown($price, [$a])['final_price']);
    }

    /** الشاشةُ الوحيدة التى تكتب اليوم («الإضافات والمميزات») تكتب على النشاط كلِّه، لا سطرٍ بعينه. */
    public function test_a_business_wide_single_select_setting_is_honoured_too(): void
    {
        $business = $this->business();
        $price = $this->price($business);
        [$a, $b, $groupId] = $this->twoOptionsInOneGroup($business);

        // إضافةٌ على النشاط كلِّه، لا على هذا السطر وحده.
        $business->syncOfferingOptions(null, [$a, $b], [
            $a => ['type' => 'amount', 'value' => 10],
            $b => ['type' => 'amount', 'value' => 15],
        ]);

        OfferingOptionGroupSetting::query()->create([
            'offering_type' => (new User)->getMorphClass(),
            'offering_id' => $business->id,
            'option_group_id' => $groupId,
            'selection_type' => OfferingOptionGroupSetting::SELECTION_SINGLE,
        ]);

        $this->expectException(ValidationException::class);

        $this->breakdown($price, [$a, $b]);
    }

    /** إعلانٌ على سطرٍ بعينه أخصّ من إعلان النشاط كلِّه، فيغلبه. */
    public function test_a_price_line_setting_overrides_the_business_wide_default(): void
    {
        $business = $this->business();
        $price = $this->price($business);
        [$a, $b, $groupId] = $this->twoOptionsInOneGroup($business);

        $price->syncOfferingOptions(null, [$a, $b], [
            $a => ['type' => 'amount', 'value' => 10],
            $b => ['type' => 'amount', 'value' => 15],
        ]);

        // النشاط كلُّه يقول فردي، وهذا السطرُ بعينه يقول متعدد — فيغلب.
        OfferingOptionGroupSetting::query()->create([
            'offering_type' => (new User)->getMorphClass(),
            'offering_id' => $business->id,
            'option_group_id' => $groupId,
            'selection_type' => OfferingOptionGroupSetting::SELECTION_SINGLE,
        ]);
        OfferingOptionGroupSetting::query()->create([
            'offering_type' => $price->getMorphClass(),
            'offering_id' => $price->id,
            'option_group_id' => $groupId,
            'selection_type' => OfferingOptionGroupSetting::SELECTION_MULTIPLE,
        ]);

        $this->assertSame(125.0, $this->breakdown($price, [$a, $b])['final_price']);
    }

    /**
     * الحلقة كاملةً: صاحبُ النشاط يحفظ فردي من لوحته، والعميلُ يراه فى شكل
     * شاشة الحجز — راجع `BookingController::modifiersOf`.
     */
    public function test_the_owner_sets_it_from_the_panel_and_the_customer_sees_it_in_the_form(): void
    {
        $business = $this->business();
        [$a, $b, $groupId] = $this->twoOptionsInOneGroup($business);

        // `form()` يردّ `shape: null` بلا مُوصِّفاتٍ لنشاطٍ لم يُعلَن له نمطُ
        // حجزٍ بعد — وهذا النشاط الحقيقي المُلتقَط قد لا يملك واحدًا. النمطُ
        // نفسُه لا شأن له بهذا الاختبار، فأى نمطٍ يكفى ليخرج المُوصِّفات.
        $bookingServiceId = (int) \App\Models\PlatformService::query()
            ->where('key', \App\Models\PlatformService::KEY_BOOKING)->where('is_active', 1)->value('id');
        \App\Models\CategoryServiceConfig::query()->updateOrCreate(
            [
                'category_id' => $business->category_id,
                'child_id' => $business->category_child_id,
                'platform_service_id' => $bookingServiceId,
            ],
            ['config' => ['booking_patterns' => ['appointment']], 'is_active' => true]
        );

        $this->withSession([])->actingAs($business, 'web');

        $this->put(route('business.booking-add-ons.update'), [
            'option_ids' => [$a, $b],
            'adjust' => [$a => 10, $b => 15],
            'adjust_type' => [$a => 'amount', $b => 'amount'],
            'selection_type' => [$groupId => 'single'],
        ])->assertRedirect();

        $this->assertSame(
            OfferingOptionGroupSetting::SELECTION_SINGLE,
            OfferingOptionGroupSetting::forOwnOffering((new User)->getMorphClass(), $business->id)[$groupId] ?? null
        );

        $token = $this->postJson('/api/v2/auth/register', [
            'name' => 'عميل الإضافات',
            'email' => 'booking-groups-' . uniqid() . '@example.test',
            'phone' => '0155' . random_int(1000000, 9999999),
            'password' => 'Secret-password1',
            'password_confirmation' => 'Secret-password1',
            'terms_accepted' => true,
        ])->assertCreated()->json('token');

        $modifiers = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v2/bookings/form/' . $business->id)
            ->assertOk()
            ->json('data.modifiers');

        $mine = collect($modifiers)->whereIn('option_id', [$a, $b]);

        $this->assertCount(2, $mine, 'الإضافتان اللتان كتبهما صاحبُ النشاط لكل أنواعه يجب أن تظهرا للعميل');
        $this->assertTrue($mine->every(fn ($m) => $m['group_id'] === $groupId));
        $this->assertTrue($mine->every(fn ($m) => $m['selection_type'] === 'single'));
    }
}
