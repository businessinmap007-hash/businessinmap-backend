<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BusinessCapability;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «لماذا فى الموظفون يوجد الوصفات الطبية والحساب تسويق» — المالك، 2026-08-19.
 *
 * السجلُّ كان يُعرض كاملًا على كل نشاط، فوكالةُ تسويق تمنح سكرتيرتها «الوصفات
 * الطبية» و«مواعيد العيادة» و«المنيو» — وهى أبوابٌ لا تفتح عندها شيئًا.
 */
class BusinessCapabilityScopeTest extends TestCase
{
    use DatabaseTransactions;

    private const MARKETING = 177;

    private function on(int $childId): User
    {
        $owner = User::query()->where('type', User::TYPE_BUSINESS)
            ->where('category_child_id', $childId)->first();

        return $owner ?: $this->markTestSkipped("لا حساب على التصنيف #{$childId}.");
    }

    private function underHealth(): User
    {
        $rootId = (int) DB::table('categories')->where('slug', 'health')->value('id');

        $owner = User::query()->where('type', User::TYPE_BUSINESS)
            ->where('category_id', $rootId)->first();

        return $owner ?: $this->markTestSkipped('لا حساب تحت جذر «الصحة».');
    }

    /** الوصفةُ والعيادة للطبّ وحده. */
    public function test_a_marketing_agency_is_not_offered_prescriptions(): void
    {
        $keys = array_keys(BusinessCapability::forBusiness($this->on(self::MARKETING)));

        $this->assertNotContains(BusinessCapability::PRESCRIPTIONS, $keys);
        $this->assertNotContains(BusinessCapability::CLINIC, $keys);
        $this->assertNotContains(BusinessCapability::MENU, $keys, 'وكالةُ تسويق تدير منيو؟');
        $this->assertNotContains(BusinessCapability::TRAINING, $keys);
    }

    /** والعيادةُ تُعرضان عليها. */
    public function test_a_clinic_is(): void
    {
        $keys = array_keys(BusinessCapability::forBusiness($this->underHealth()));

        $this->assertContains(BusinessCapability::PRESCRIPTIONS, $keys);
        $this->assertContains(BusinessCapability::CLINIC, $keys);
    }

    /** وإدارةُ الحساب ليست خدمةً تُباع — تبقى للجميع. */
    public function test_account_management_is_offered_to_everyone(): void
    {
        $keys = array_keys(BusinessCapability::forBusiness($this->on(self::MARKETING)));

        foreach ([
            BusinessCapability::ORDERS,
            BusinessCapability::OFFERS,
            BusinessCapability::PRICES,
            BusinessCapability::WORKING_HOURS,
        ] as $always) {
            $this->assertContains($always, $keys);
        }
    }

    /**
     * وإخفاءُ المربّع لا يكفى.
     *
     * الحفظ يُرسَل بيدٍ أو بأداة، ومن يرسله يمنح موظّفه صلاحيةً لا يملكها هو.
     */
    public function test_a_hidden_capability_cannot_be_granted_by_posting_it(): void
    {
        $marketer = $this->on(self::MARKETING);

        $granted = BusinessCapability::sanitizeFor($marketer, [
            BusinessCapability::PRESCRIPTIONS,
            BusinessCapability::MENU,
            BusinessCapability::ORDERS,
        ]);

        $this->assertSame([BusinessCapability::ORDERS], $granted);
    }

    /** والشاشة نفسها لا تعرضها. */
    public function test_the_rendered_screen_drops_them(): void
    {
        $response = $this->actingAs($this->on(self::MARKETING))
            ->get(route('business.staff.index', [], false))
            ->assertOk();

        $response->assertDontSee('الوصفات الطبية');
        $response->assertDontSee('مواعيد العيادة');
        $response->assertSee('الحجوزات');
    }

    /** وكل مفتاحٍ فى السجلّ له اسمٌ بالعربية والإنجليزية — بلا مفتاحٍ عارٍ. */
    public function test_every_screen_field_is_named_in_both_languages(): void
    {
        foreach (['ar', 'en'] as $locale) {
            foreach (\App\Enums\BookingPattern::cases() as $pattern) {
                foreach ($pattern->asks() as $field) {
                    $key = 'booking.field.' . $field;

                    $this->assertNotSame(
                        $key,
                        trans($key, [], $locale),
                        "«{$field}» يصل العميل مفتاحًا عاريًا فى «{$locale}»"
                    );
                }
            }
        }
    }
}
