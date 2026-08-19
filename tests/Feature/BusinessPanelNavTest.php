<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BusinessPanelNav;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «فتحت حساب تسويق واعطانى منيو وحجوزات والخطط التدريبية لماذا كل الخدمات
 * موجوده» — المالك، 2026-08-19.
 *
 * البيانات كانت صحيحة: «تسويق» #177 لا يفعّل إلا `booking`، و`menu` و`delivery`
 * مطفأتان عليه. لكن شريطَ اللوحة كان سبعةَ عشرَ رابطًا مكتوبةً بلا شرطٍ واحد —
 * لم يسأل يومًا عمّا يبيعه صاحبُ المحل.
 */
class BusinessPanelNavTest extends TestCase
{
    use DatabaseTransactions;

    private const MARKETING = 177;

    private function marketer(): User
    {
        $owner = User::query()->where('type', User::TYPE_BUSINESS)
            ->where('category_child_id', self::MARKETING)->first();

        return $owner ?: $this->markTestSkipped('لا حساب على «تسويق» #177.');
    }

    /** ما لا يبيعه لا يُعرض عليه. */
    public function test_a_marketing_business_is_not_offered_a_menu(): void
    {
        $marketer = $this->marketer();
        $services = BusinessPanelNav::servicesOf($marketer);

        $this->assertNotContains('menu', $services, '«تسويق» يفعّل المنيو؟');

        foreach (['menu', 'tables', 'table-calls', 'products', 'training-plans'] as $link) {
            $this->assertFalse(
                BusinessPanelNav::shows($link, $marketer),
                "«{$link}» معروضٌ على نشاطٍ لا يبيعه"
            );
        }
    }

    /** وما يبيعه يُعرض: التسويق يبيع حجزًا، فله شاشاته. */
    public function test_what_it_does_sell_is_shown(): void
    {
        $marketer = $this->marketer();

        $this->assertContains('booking', BusinessPanelNav::servicesOf($marketer));

        foreach (['bookings', 'booking-settings', 'bookable-items'] as $link) {
            $this->assertTrue(BusinessPanelNav::shows($link, $marketer), "«{$link}» مخفىٌّ عمّن يبيعه");
        }
    }

    /** وإدارةُ الحساب ليست خدمةً تُباع — تظهر للجميع. */
    public function test_account_management_is_never_gated(): void
    {
        foreach (['dashboard', 'prices', 'staff', 'orders', 'offerings'] as $link) {
            $this->assertTrue(BusinessPanelNav::shows($link, $this->marketer()));
        }
    }

    /** والشريط نفسه — لا الدالّة وحدها — يخلو ممّا لا يخصّه. */
    public function test_the_rendered_bar_drops_what_the_trade_does_not_sell(): void
    {
        $response = $this->actingAs($this->marketer())
            ->get(route('business.booking-settings.edit', [], false))
            ->assertOk();

        $response->assertDontSee(route('business.menu.index', [], false), false);
        $response->assertDontSee(route('business.training-plans.index', [], false), false);
        $response->assertSee(route('business.bookings.index', [], false), false);
    }

    /** ونشاطٌ على مطعم يرى منيوه — الحارس يقرأ التصنيف، لا يخفى بالجملة. */
    public function test_a_restaurant_still_sees_its_menu(): void
    {
        $restaurant = User::query()->where('type', User::TYPE_BUSINESS)
            ->whereIn('category_child_id', function ($q) {
                $q->select('l.child_id')->from('category_platform_services as l')
                    ->join('platform_services as s', 's.id', '=', 'l.platform_service_id')
                    ->where('s.key', 'menu')->where('l.is_active', 1);
            })->first();

        if (! $restaurant) {
            $this->markTestSkipped('لا نشاط على تصنيفٍ يفعّل المنيو.');
        }

        $this->assertTrue(BusinessPanelNav::shows('menu', $restaurant));
    }

    /** وحسابٌ بلا تصنيف لا يُعرض عليه شىءٌ يُباع — لا كلُّ شىء. */
    public function test_a_business_with_no_child_is_offered_nothing_gated(): void
    {
        $stray = new User(['type' => User::TYPE_BUSINESS]);
        $stray->category_child_id = 0;

        $this->assertSame([], BusinessPanelNav::servicesOf($stray));
        $this->assertFalse(BusinessPanelNav::shows('menu', $stray));
        $this->assertTrue(BusinessPanelNav::shows('dashboard', $stray));
    }

    /** كل رابطٍ محروس يشير إلى خدمةٍ موجودة فعلًا — وإلا أخفى نفسه للأبد. */
    public function test_every_gate_names_a_service_that_exists(): void
    {
        $keys = DB::table('platform_services')->pluck('key')->all();

        $reflection = new \ReflectionClass(BusinessPanelNav::class);
        $gated = $reflection->getConstant('GATED');

        foreach ($gated as $link => $service) {
            $this->assertContains($service, $keys, "«{$link}» محروسٌ بخدمةٍ لا وجود لها: «{$service}»");
        }
    }
}
