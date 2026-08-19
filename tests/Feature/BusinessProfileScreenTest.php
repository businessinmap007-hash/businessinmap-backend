<?php

namespace Tests\Feature;

use App\Models\BusinessWorkingHour;
use App\Models\User;
use App\Support\BusinessPanelNav;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * «حقول الفندق كلها هى حقول عامة مفترض وجودها لكل بزنس» — المالك، 2026-08-19.
 *
 * الأعمدة كانت موجودة كلَّها — `logo`، `cover`، `about`، `latitude` — ولا
 * شاشةَ فى لوحة النشاط تكتبها: تُكتب من لوحة الإدارة (أى من موظّفى المنصّة)
 * ومن الـAPI. فصاحبُ فندقٍ داخل لوحته لا يستطيع تغيير شعاره ولا وصفَ فندقه،
 * ولا يعرف أنه لا يستطيع — لا رسالةَ ولا شاشةَ فارغة، لا شىء.
 *
 * وليست شاشةَ فنادق. الحَجبُ فى هذه اللوحة يسأل «ما الذى يبيعه هذا النشاط؟»،
 * وهذا سؤالٌ لا معنى له هنا: كلُّ نشاطٍ له اسمٌ وشعارٌ وموقعٌ ومواعيد.
 */
class BusinessProfileScreenTest extends TestCase
{
    use DatabaseTransactions;

    private function business(int $rootId, int $childId): User
    {
        return User::create([
            'name' => 'Zz Biz ' . uniqid(),
            'email' => 'zz-biz-' . uniqid() . '@test.local',
            'phone' => '01' . random_int(100000000, 999999999),
            'password' => Hash::make('Passw0rdTest'),
            'type' => User::TYPE_BUSINESS,
            'category_id' => $rootId,
            'category_child_id' => $childId,
            'api_token' => 'zz' . uniqid() . bin2hex(random_bytes(8)),
        ]);
    }

    /** أنشطةٌ من تجاراتٍ مختلفة عمدًا: الشاشةُ لا تخصّ واحدةً منها. */
    private function anyTrades(int $count = 3): array
    {
        return DB::table('users')
            ->where('type', User::TYPE_BUSINESS)
            ->whereNotNull('category_child_id')
            ->select('category_id', 'category_child_id')
            ->distinct()->limit($count)->get()
            ->map(fn ($r) => $this->business((int) $r->category_id, (int) $r->category_child_id))
            ->all();
    }

    private function payload(User $biz, array $overrides = []): array
    {
        return $overrides + [
            'name' => $biz->name,
            'phone' => $biz->phone,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | الشاشةُ موجودة، ولكلٍّ
    |--------------------------------------------------------------------------
    */

    /** الحقولُ التى كانت بلا باب: النبذةُ والشعارُ والغلافُ والموقع. */
    public function test_the_screen_carries_the_fields_that_had_no_door(): void
    {
        $biz = $this->anyTrades(1)[0];

        $html = $this->actingAs($biz)->get(route('business.profile.edit', [], false))
            ->assertOk()->getContent();

        foreach (['name="about"', 'name="logo"', 'name="cover"', 'name="latitude"', 'name="longitude"'] as $field) {
            $this->assertStringContainsString($field, $html, "«{$field}» ليس على الشاشة");
        }
    }

    /** وتُفتح لكل تجارة: ليست شاشةَ خدمةٍ حتى تُحجَب بها. */
    public function test_every_trade_reaches_it_whatever_it_sells(): void
    {
        foreach ($this->anyTrades(3) as $biz) {
            $this->actingAs($biz)->get(route('business.profile.edit', [], false))->assertOk();
        }
    }

    /** والشجرةُ الجانبية تدلّ عليها، وإلا فهى شاشةٌ لا يعرف أحدٌ أنها هناك. */
    public function test_the_sidebar_points_at_it(): void
    {
        app()->setLocale('ar');
        $biz = $this->anyTrades(1)[0];

        auth()->setUser($biz);
        $menu = view('business.layouts._partials.menu')->render();

        $this->assertStringContainsString('ملف النشاط', $menu);
        $this->assertStringContainsString(route('business.profile.edit', [], false), $menu);
    }

    /*
    |--------------------------------------------------------------------------
    | ما تكتبه
    |--------------------------------------------------------------------------
    */

    public function test_it_saves_the_identity_and_the_location(): void
    {
        $biz = $this->anyTrades(1)[0];

        $this->actingAs($biz)->put(route('business.profile.update', [], false), $this->payload($biz, [
            'name' => 'فندق النيل الأزرق',
            'name_en' => 'Blue Nile Hotel',
            'about' => 'أربعون غرفة على الكورنيش.',
            'latitude' => 30.0444,
            'longitude' => 31.2357,
            'timezone' => 'Africa/Cairo',
        ]))->assertRedirect();

        $biz->refresh();

        $this->assertSame('فندق النيل الأزرق', $biz->name);
        $this->assertSame('Blue Nile Hotel', $biz->name_en);
        $this->assertSame('أربعون غرفة على الكورنيش.', $biz->about);
        $this->assertSame(30.0444, (float) $biz->latitude);
        $this->assertSame('Africa/Cairo', $biz->timezone);
    }

    /** والشعارُ يُرفع ويُخزَّن مسارًا نسبيًّا، كما تفعل بقيةُ المنصّة. */
    public function test_it_stores_an_uploaded_logo(): void
    {
        $biz = $this->anyTrades(1)[0];

        $this->actingAs($biz)->put(route('business.profile.update', [], false), $this->payload($biz, [
            'logo' => UploadedFile::fake()->create('logo.jpg', 12, 'image/jpeg'),
        ]))->assertRedirect();

        $biz->refresh();

        $this->assertNotNull($biz->logo);
        $this->assertStringStartsWith('files/uploads/', $biz->logo);
        $this->assertFileExists(public_path($biz->logo));

        @unlink(public_path($biz->logo));
    }

    /**
     * والقديمُ يُمحى مع استبداله.
     *
     * عمودُ مسارٍ مفرد، لا معرضٌ له صفوفُه، فلا شىءَ ينظّف خلفه: كلُّ تغييرِ
     * شعارٍ كان يترك ملفًّا على القرص لا شىءَ يشير إليه ولا شىءَ يجده.
     */
    public function test_replacing_a_logo_removes_the_file_it_replaced(): void
    {
        $biz = $this->anyTrades(1)[0];

        $this->actingAs($biz)->put(route('business.profile.update', [], false), $this->payload($biz, [
            'logo' => UploadedFile::fake()->create('first.jpg', 12, 'image/jpeg'),
        ]))->assertRedirect();

        $first = $biz->refresh()->logo;

        $this->actingAs($biz)->put(route('business.profile.update', [], false), $this->payload($biz, [
            'logo' => UploadedFile::fake()->create('second.jpg', 12, 'image/jpeg'),
        ]))->assertRedirect();

        $second = $biz->refresh()->logo;

        $this->assertNotSame($first, $second);
        $this->assertFileDoesNotExist(public_path($first));

        @unlink(public_path($second));
    }

    /** ويُحذف بطلبه، فى نفس الحفظة. */
    public function test_a_logo_can_be_removed(): void
    {
        $biz = $this->anyTrades(1)[0];

        $this->actingAs($biz)->put(route('business.profile.update', [], false), $this->payload($biz, [
            'logo' => UploadedFile::fake()->create('logo.jpg', 12, 'image/jpeg'),
        ]))->assertRedirect();

        $path = $biz->refresh()->logo;

        $this->actingAs($biz)->put(route('business.profile.update', [], false), $this->payload($biz, [
            'remove' => ['logo'],
        ]))->assertRedirect();

        $this->assertNull($biz->refresh()->logo);
        $this->assertFileDoesNotExist(public_path($path));
    }

    /** ولا يسرق هاتفَ غيره: الهاتفُ هويةُ دخولٍ فريدة. */
    public function test_it_refuses_a_phone_that_belongs_to_someone_else(): void
    {
        [$mine, $theirs] = $this->anyTrades(2);

        $this->actingAs($mine)
            ->put(route('business.profile.update', [], false), $this->payload($mine, ['phone' => $theirs->phone]))
            ->assertSessionHasErrors('phone');

        $this->assertNotSame($theirs->phone, $mine->refresh()->phone);
    }

    /** والبريدُ لا يُكتب من هنا مهما أُرسل: بابُ تغييره غيرُ هذا. */
    public function test_the_login_email_is_not_writable_from_this_screen(): void
    {
        $biz = $this->anyTrades(1)[0];
        $was = $biz->email;

        $this->actingAs($biz)->put(route('business.profile.update', [], false), $this->payload($biz, [
            'email' => 'taken-over-' . uniqid() . '@test.local',
        ]))->assertRedirect();

        $this->assertSame($was, $biz->refresh()->email);
    }

    /*
    |--------------------------------------------------------------------------
    | المواعيد
    |--------------------------------------------------------------------------
    */

    /** الأسبوعُ كلُّه يُعرض، حاضرُه وغائبُه — وإلا لم يعرف أين يكتب الباقى. */
    public function test_the_whole_week_is_offered_even_when_nothing_was_saved(): void
    {
        $biz = $this->anyTrades(1)[0];

        $html = $this->actingAs($biz)->get(route('business.profile.edit', [], false))
            ->assertOk()->getContent();

        foreach (range(0, 6) as $day) {
            $this->assertStringContainsString('name="days[' . $day . '][day]"', $html);
        }
    }

    /** ويُحفظ عبر الخدمة نفسها التى يقرأ منها بحثُ «مفتوح الآن». */
    public function test_it_saves_the_working_week(): void
    {
        $biz = $this->anyTrades(1)[0];

        $days = collect(range(0, 6))->map(fn (int $d) => [
            'day' => $d,
            'open' => '09:00',
            'close' => '23:00',
        ])->all();

        $days[5]['is_closed'] = 1; // الجمعة

        $this->actingAs($biz)
            ->put(route('business.profile.hours', [], false), ['days' => $days])
            ->assertRedirect();

        $rows = BusinessWorkingHour::where('business_id', $biz->id)->get()->keyBy('day_of_week');

        $this->assertCount(7, $rows);
        $this->assertSame('09:00:00', (string) $rows[0]->open_time);
        $this->assertTrue((bool) $rows[5]->is_closed);
        $this->assertNull($rows[5]->open_time, 'يومٌ مغلق لا يحمل مواعيد');
    }

    /** ولا يقرأ نشاطٌ ملفَّ نشاطٍ آخر ولا يكتبه: كلُّ شىءٍ مقصورٌ على الداخل. */
    public function test_it_only_ever_touches_the_signed_in_business(): void
    {
        [$mine, $theirs] = $this->anyTrades(2);
        $theirName = $theirs->name;

        $this->actingAs($mine)
            ->put(route('business.profile.update', [], false), $this->payload($mine, ['name' => 'اسمى الجديد']))
            ->assertRedirect();

        $this->assertSame($theirName, $theirs->refresh()->name);
        $this->assertSame('اسمى الجديد', $mine->refresh()->name);
    }

    /** والحارسُ لم يُفتح: زائرٌ بلا حساب لا يصل إليها. */
    public function test_a_stranger_is_sent_to_the_login(): void
    {
        $this->get(route('business.profile.edit', [], false))
            ->assertRedirect(route('business.login', [], false));
    }

    /** ولا يُخفيها الحَجب: BusinessPanelNav لا تسأل عنها أصلًا. */
    public function test_it_is_not_a_gated_service_screen(): void
    {
        $biz = $this->anyTrades(1)[0];
        auth()->setUser($biz);

        // الشاشةُ تصل لمن لا يبيع منيو ولا حجزًا ولا تجزئة.
        $sells = collect(['menu', 'bookings', 'products'])->filter(fn ($gate) => BusinessPanelNav::shows($gate));

        $this->actingAs($biz)->get(route('business.profile.edit', [], false))
            ->assertOk('شاشةُ الملفّ حُجبت بخدمةٍ لا شأن لها بها: ' . $sells->implode('، '));
    }
}
