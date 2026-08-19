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

        // «وحداتي» ليست هنا عمدًا: الحجزُ مفعَّلٌ عليه، لكن نمطه «استشارة»
        // ولا وحدةَ فيها — وهو ما يقيسه test_units_follow_the_shape_not_the_service.
        foreach (['bookings', 'booking-settings'] as $link) {
            $this->assertTrue(BusinessPanelNav::shows($link, $marketer), "«{$link}» مخفىٌّ عمّن يبيعه");
        }
    }

    /**
     * وإدارةُ الحساب ليست خدمةً تُباع — تظهر للجميع.
     *
     * و«الطلبات» خرجت من هذه القائمة بعد أن تبيّن أنها ليست إدارةَ حساب: الطلبُ
     * يأتى من منيو أو توصيل أو تجزئة، ووكالةُ التسويق لا تملك واحدةً منها،
     * فكانت تفتح شاشةً لا تمتلئ أبدًا.
     */
    public function test_account_management_is_never_gated(): void
    {
        foreach (['dashboard', 'prices', 'staff', 'offerings'] as $link) {
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

    /**
     * الشريط مقصورٌ على (الجذر، الابن) لا على الابن وحده.
     *
     * «آثاث» #116 يقف تحت ثلاثة جذور بثلاث حزمٍ مختلفة — يحجز تحت «شركات»
     * ولا يحجز تحت جذر المحلات. وقراءةُ الابن وحده كانت تعطى صاحبَ المحل
     * حجوزاتٍ ووحداتٍ يفعلها ابنُه فى مكانٍ لا يقف هو فيه.
     */
    public function test_the_bar_is_scoped_to_the_root_the_business_stands_on(): void
    {
        $childId = 116;

        $roots = DB::table('category_platform_services as l')
            ->join('platform_services as s', 's.id', '=', 'l.platform_service_id')
            ->where('l.child_id', $childId)->where('l.is_active', 1)->where('s.key', 'booking')
            ->pluck('l.category_id')->map(fn ($r) => (int) $r)->all();

        $without = DB::table('category_platform_services')
            ->where('child_id', $childId)->where('is_active', 1)
            ->whereNotIn('category_id', $roots ?: [0])
            ->value('category_id');

        if (! $roots || ! $without) {
            $this->markTestSkipped('«آثاث» لم يعد يقف على جذرين يختلفان فى الحجز.');
        }

        $here = new User(['type' => User::TYPE_BUSINESS]);
        $here->category_child_id = $childId;
        $here->category_id = $roots[0];

        $there = new User(['type' => User::TYPE_BUSINESS]);
        $there->category_child_id = $childId;
        $there->category_id = (int) $without;

        $this->assertTrue(BusinessPanelNav::shows('bookings', $here));
        $this->assertFalse(
            BusinessPanelNav::shows('bookings', $there),
            'ابنٌ يحجز تحت جذرٍ آخر منح صاحبَ هذا الجذر حجوزات'
        );
    }

    /** والطاولةُ سؤالُ طعامٍ لا سؤالُ منيو: تاجرُ الأثاث لا طاولاتِ عنده. */
    public function test_only_a_food_menu_gets_tables(): void
    {
        $restaurant = $this->onChildWithMenuKind('menu_food');
        $trader = $this->onChildWithMenuKind('menu_furniture') ?? $this->onChildWithMenuKind('menu_market');

        if (! $restaurant || ! $trader) {
            $this->markTestSkipped('لا حسابان على منيو طعامٍ ومنيو غيره.');
        }

        $this->assertTrue(BusinessPanelNav::shows('tables', $restaurant));
        $this->assertFalse(BusinessPanelNav::shows('tables', $trader), 'تاجرٌ يجد «الطاولات» فى لوحته');
        $this->assertFalse(BusinessPanelNav::shows('table-calls', $trader));
        $this->assertTrue(BusinessPanelNav::shows('menu', $trader), 'المنيو هى كتالوجه — لا تُخفى');
    }

    /** و«وحداتي» سؤالُ شكلٍ لا سؤالُ خدمة. */
    public function test_units_follow_the_shape_not_the_service(): void
    {
        $marketer = $this->marketer();

        $this->assertContains('booking', BusinessPanelNav::servicesOf($marketer));
        $this->assertFalse(
            BusinessPanelNav::shows('bookable-items', $marketer),
            'استشارةٌ لا وحدةَ فيها، والشاشة تبقى فارغة'
        );
    }

    /**
     * الآلة واحدة والاسمُ ليس كذلك.
     *
     * معرضُ الأثاث وتاجرُ الجملة ومكتبُ العقارات يستعملون آلةَ المنيو نفسها
     * ولا يسمّون بضاعتهم منيو — والاسمُ يُقرأ من نوع العناصر لأنه هو ما يقول
     * بأىِّ لغةٍ يتكلّم هذا التاجر.
     */
    public function test_the_catalogue_is_called_a_menu_only_where_there_is_food(): void
    {
        $restaurant = $this->onChildWithMenuKind('menu_food');
        $trader = $this->onChildWithMenuKind('menu_furniture') ?? $this->onChildWithMenuKind('menu_market');

        if (! $restaurant || ! $trader) {
            $this->markTestSkipped('لا حسابان على منيو طعامٍ ومنيو غيره.');
        }

        $this->assertSame('المنيو', BusinessPanelNav::catalogLabel($restaurant));
        $this->assertSame('الكتالوج', BusinessPanelNav::catalogLabel($trader));

        $this->assertNotSame(
            __('الكتالوج', [], 'en'),
            'الكتالوج',
            'الاسم الجديد يصل الإنجليزىَّ عربيًّا'
        );
    }

    /** والشاشة نفسها تحمل الاسم، لا الشريط وحده. */
    public function test_the_catalogue_screen_carries_the_name_too(): void
    {
        $trader = $this->onChildWithMenuKind('menu_furniture') ?? $this->onChildWithMenuKind('menu_market');

        if (! $trader || ! BusinessPanelNav::shows('menu', $trader)) {
            $this->markTestSkipped('لا تاجرَ كتالوجٍ تُفتح له الشاشة.');
        }

        $this->actingAs($trader)
            ->get(route('business.menu.index', [], false))
            ->assertOk()
            ->assertSee('الكتالوج')
            ->assertDontSee('منيو نشاطي');
    }

    private function onChildWithMenuKind(string $kind): ?User
    {
        $serviceId = (int) DB::table('platform_services')->where('key', 'menu')->value('id');

        $rows = DB::table('category_service_configs')
            ->where('platform_service_id', $serviceId)->where('is_active', 1)
            ->where('config', 'like', '%"' . $kind . '"%')
            ->get(['category_id', 'child_id']);

        foreach ($rows as $row) {
            $owner = User::query()->where('type', User::TYPE_BUSINESS)
                ->where('category_child_id', $row->child_id)
                ->where('category_id', $row->category_id)->first();

            if ($owner) {
                return $owner;
            }
        }

        return null;
    }

    /**
     * ونشاطٌ على مطعم يرى منيوه — الحارس يقرأ التصنيف، لا يخفى بالجملة.
     *
     * كان يختار أىَّ حسابٍ ابنُه يفعّل المنيو **فى أىِّ جذر**، فصار يسقط حين
     * صار الحارس مقصورًا على (الجذر، الابن): الحسابُ المختار كان يقف على جذرٍ
     * لا منيوَ فيه. والاختيارُ الآن بالزوج، وهو ما يقيسه الاختبار أصلًا.
     */
    public function test_a_restaurant_still_sees_its_menu(): void
    {
        $restaurant = $this->onChildWithMenuKind('menu_food');

        if (! $restaurant) {
            $this->markTestSkipped('لا نشاط على تصنيفٍ يفعّل منيو الطعام.');
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
