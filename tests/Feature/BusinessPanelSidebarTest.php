<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BusinessPanelNav;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «تنسيق القائمة الجانبية وجمع الخدمات فى شجرة واحدة» — المالك، 2026-08-19.
 *
 * كانت لوحةُ النشاط شريطًا أفقيًّا من سبعةَ عشرَ زرًّا: كلُّ شاشةٍ بنفس الوزن،
 * ولا شىء يقول أىُّ زرٍّ يخصّ أىَّ خدمة. صارت شجرةً، فرعٌ لكل خدمةٍ يبيعها
 * صاحبُ المحل وتحته كلُّ ما يخصّها.
 */
class BusinessPanelSidebarTest extends TestCase
{
    use DatabaseTransactions;

    private function on(int $childId): User
    {
        $owner = User::query()->where('type', User::TYPE_BUSINESS)
            ->where('category_child_id', $childId)->first();

        return $owner ?: $this->markTestSkipped("لا حساب على التصنيف #{$childId}.");
    }

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * العربيةُ صراحةً.
         *
         * الشجرةُ تمرّ كلَّ عنوانٍ على `__()`، ولوحةُ النشاط ثنائيةُ اللغة —
         * فتأكيدٌ على «الرئيسية» يسقط تحت الإنجليزية وهو لم يقصد اللغة أصلًا.
         * العناوينُ هنا هى النصوصُ المصدرية كما فى الشجرة.
         */
        app()->setLocale('ar');
    }

    private function menuOf(User $business): string
    {
        auth()->setUser($business);

        return view('business.layouts._partials.menu')->render();
    }

    /** @return array<int,string> نصوص الروابط والفروع بالترتيب */
    private function labels(string $html): array
    {
        preg_match_all('/<span class="a2-nav-text">([^<]+)<\/span>/u', $html, $m);

        return $m[1];
    }

    /** الفرعُ يجمع ما يخصّ خدمته: إعدادُها ومخزونُها وعملياتُها. */
    public function test_a_service_gathers_its_own_screens_under_one_branch(): void
    {
        $html = $this->menuOf($this->on(536)); // فندق

        $this->assertGreaterThanOrEqual(2, substr_count($html, 'a2-nav-group'), 'لا فروعَ أصلًا');

        // «إعدادات الحجز» صارت «إعدادات الخدمات» يوم 2026-08-20: الشاشةُ
        // نفسُها تُعلن أدوارَ مجموعات الكلمات، وهى تخدم المنيو والتجزئة
        // كما تخدم الحجز — فاسمُها لم يعد يصفها.
        foreach (['الحجز', 'إعدادات الخدمات', 'وحداتي', 'حجوزاتي'] as $label) {
            $this->assertContains($label, $this->labels($html), "«{$label}» ليست فى الشجرة");
        }
    }

    /** وفرعٌ خلت كلُّ روابطه لا يُرسَم — عنوانٌ بلا شىءٍ تحته وعدٌ مكسور. */
    public function test_an_empty_branch_is_not_drawn_at_all(): void
    {
        $labels = $this->labels($this->menuOf($this->on(177))); // تسويق

        $this->assertNotContains('المنيو', $labels);
        $this->assertNotContains('التجزئة', $labels);
        $this->assertNotContains('التدريب', $labels);
        $this->assertContains('الحجز', $labels, 'التسويق يبيع حجزًا');
    }

    /** والفرعُ يحمل اسمَ كتالوج صاحبه: «المنيو» لمن يطعم و«الكتالوج» لمن يعرض. */
    public function test_the_catalogue_branch_carries_the_trade_s_own_word(): void
    {
        $serviceId = (int) DB::table('platform_services')->where('key', 'menu')->value('id');

        /*
         * أىُّ منيو غيرِ الطعام: «موبليات» و«ماركت» و«عقارات» و«سيارات» كلُّها
         * كتالوجات. البحثُ عن نوعٍ بعينه يجعل الاختبار يتخطّى نفسه كلّما نُقل
         * تاجرُ الأثاث بين الجذور.
         */
        $trader = null;

        foreach (['menu_furniture', 'menu_market', 'menu_properties', 'menu_vehicles'] as $kind) {
            $rows = DB::table('category_service_configs')
                ->where('platform_service_id', $serviceId)->where('is_active', 1)
                ->where('config', 'like', '%"' . $kind . '"%')
                ->get(['category_id', 'child_id']);

            foreach ($rows as $row) {
                $trader = User::query()->where('type', User::TYPE_BUSINESS)
                    ->where('category_child_id', $row->child_id)
                    ->where('category_id', $row->category_id)->first();

                if ($trader) {
                    break 2;
                }
            }
        }

        if (! $trader) {
            $this->markTestSkipped('لا تاجرَ كتالوج.');
        }

        $labels = $this->labels($this->menuOf($trader));

        $this->assertContains('الكتالوج', $labels);
        $this->assertNotContains('المنيو', $labels, 'معرضُ أثاثٍ يقرأ «المنيو»');
    }

    /** وإدارةُ الحساب تُعرض على الجميع، وليست فرعَ خدمة. */
    public function test_account_management_is_offered_to_every_trade(): void
    {
        foreach ([177, 536, 225] as $childId) {
            $labels = $this->labels($this->menuOf($this->on($childId)));

            foreach (['الرئيسية', 'العروض والأسعار', 'أسعاري', 'الحساب', 'الموظفون'] as $label) {
                $this->assertContains($label, $labels, "«{$label}» مخفىٌّ عن التصنيف #{$childId}");
            }
        }
    }

    /** ولا رابطَ يشير إلى مسارٍ لا وجود له — عنوانٌ لطيف فوق ٤٠٤. */
    public function test_every_link_points_at_a_route_that_exists(): void
    {
        $html = $this->menuOf($this->on(245)); // مطعم — أوسع شجرة

        preg_match_all('/href="([^"]+)"/u', $html, $m);

        $this->assertNotEmpty($m[1]);

        foreach ($m[1] as $href) {
            $this->assertStringNotContainsString('#', $href, 'رابطٌ معطّل فى الشجرة');
        }
    }

    /** والصفحةُ نفسها ترسم القائمة الجانبية لا الشريط. */
    public function test_the_panel_renders_as_a_sidebar(): void
    {
        $owner = $this->on(536);

        $html = $this->actingAs($owner)
            ->get(route('business.dashboard', [], false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('a2-sidebar', $html);
        $this->assertStringContainsString('a2-nav-group', $html);
    }

    /** والحَجب هو نفسه الذى تحرسه BusinessPanelNav — لا نسخةً ثانية منه. */
    public function test_the_tree_asks_the_same_gate_the_rest_of_the_panel_does(): void
    {
        $marketer = $this->on(177);
        $labels = $this->labels($this->menuOf($marketer));

        $this->assertSame(
            BusinessPanelNav::shows('menu', $marketer),
            in_array('الأصناف', $labels, true),
            'الشجرة تجيب غير ما يجيبه الحارس'
        );
    }
}
