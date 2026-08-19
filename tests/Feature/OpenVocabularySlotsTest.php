<?php

namespace Tests\Feature;

use App\Models\OptionGroup;
use App\Models\User;
use App\Services\MerchantOfferingVocabulary;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «افتح الحاجز ويكون بشكل منظم وسلس» — المالك، 2026-08-19.
 *
 * كان دورُ المجموعة إذنًا: ما تسمّيه المنصّة مُوصِّفًا لا يُباع أبدًا، والترقيةُ
 * لا تعمل إلا حين يخلو التصنيفُ من سطر — وهذا صحيحٌ فى ٢ من ١٣٠ تصنيفًا حيًّا.
 * صار الدورُ ترتيبًا: البديهىُّ أوّلًا، والباقى متاحٌ لمن يحتاجه.
 */
class OpenVocabularySlotsTest extends TestCase
{
    use DatabaseTransactions;

    private const PLAYSTATION = 225;

    private function vocabularyOf(int $childId): array
    {
        $owner = User::query()->where('type', User::TYPE_BUSINESS)
            ->where('category_child_id', $childId)->first();

        if (! $owner) {
            $this->markTestSkipped("لا حساب على التصنيف #{$childId}.");
        }

        return app(MerchantOfferingVocabulary::class)
            ->for((int) $owner->id, $childId, (int) $owner->category_id);
    }

    private function names(iterable $groups): array
    {
        return collect($groups)->flatten()->pluck('name_ar')->all();
    }

    /** صاحبُ الصالة يستطيع بيعَ ساعة «بلايستيشن ٥» بذاتها. */
    public function test_a_merchant_may_sell_what_the_platform_calls_a_modifier(): void
    {
        $vocabulary = $this->vocabularyOf(self::PLAYSTATION);

        $this->assertContains('بلايستيشن ٥', $this->names($vocabulary['lines']), 'الحاجز ما زال مغلقًا');
    }

    /** ويستطيع جعلَ ما تسمّيه المنصّة سطرًا زيادةً على غيره. */
    public function test_a_merchant_may_qualify_with_what_the_platform_calls_a_line(): void
    {
        $vocabulary = $this->vocabularyOf(self::PLAYSTATION);

        $this->assertContains('غرفة خاصة', $this->names($vocabulary['modifiers']));
    }

    /** والبديهىُّ يبقى أوّلَ ما يُقرأ — الترتيب هو كل الفرق بين المتاح والفوضى. */
    public function test_the_platform_answer_is_still_the_first_one_offered(): void
    {
        $vocabulary = $this->vocabularyOf(self::PLAYSTATION);

        $this->assertSame(
            'ألعاب ومرافق الترفيه',
            array_key_first($vocabulary['lines']->all()),
            'ما تسمّيه المنصّة سطرًا لم يعد يُعرض أوّلًا'
        );

        $this->assertSame(
            'فئة جهاز الألعاب',
            array_key_first($vocabulary['modifiers']->all()),
            'ما تسمّيه المنصّة وصفًا لم يعد يُعرض أوّلًا'
        );
    }

    /** والشاشة تعرف الفرق، فتفصل المألوف عن الباقى بدل قائمتين متطابقتين. */
    public function test_the_screen_is_told_which_half_is_the_usual_answer(): void
    {
        $vocabulary = $this->vocabularyOf(self::PLAYSTATION);

        $this->assertNotEmpty($vocabulary['preferred_lines']);
        $this->assertNotEmpty($vocabulary['preferred_modifiers']);

        $this->assertEmpty(
            array_intersect($vocabulary['preferred_lines'], $vocabulary['preferred_modifiers']),
            'كلمةٌ واحدة مفضَّلةٌ فى الخانتين — الترتيب يفقد معناه'
        );
    }

    /**
     * وكلمةٌ يقولها مئةُ تاجرٍ عن نفسه ليست منتجَ أحد.
     *
     * «كاش» و«جديد» و«تقسيط» تبقى خارج خانة البيع مهما فُتح الحاجز: هى وصفُ
     * الصفقة لا الشىء المُباع، وهذا هو الحدُّ الوحيد الباقى.
     */
    public function test_the_widest_words_are_still_never_sellable(): void
    {
        $businesses = User::query()->where('type', User::TYPE_BUSINESS)
            ->whereNotNull('category_child_id')->limit(25)->get(['id', 'category_id', 'category_child_id']);

        $vocabulary = app(MerchantOfferingVocabulary::class);

        foreach ($businesses as $business) {
            $lines = $this->names($vocabulary->for(
                (int) $business->id,
                (int) $business->category_child_id,
                (int) $business->category_id
            )['lines']);

            foreach (['كاش', 'تقسيط', 'جديد'] as $dealWord) {
                $this->assertNotContains(
                    $dealWord,
                    $lines,
                    "«{$dealWord}» معروضٌ كشىءٍ يُباع على النشاط #{$business->id}"
                );
            }
        }
    }

    /** وما يُرسَل يُقبل: ما صار معروضًا للبيع صار قابلًا للحفظ. */
    public function test_what_the_screen_offers_the_save_accepts(): void
    {
        $owner = User::query()->where('type', User::TYPE_BUSINESS)
            ->where('category_child_id', self::PLAYSTATION)->first();

        if (! $owner) {
            $this->markTestSkipped('لا حساب على «بلاي ستيشن».');
        }

        $vocabulary = app(MerchantOfferingVocabulary::class);
        $shown = $vocabulary->for((int) $owner->id, self::PLAYSTATION, (int) $owner->category_id);
        $picks = $vocabulary->pickableIds((int) $owner->id, self::PLAYSTATION, (int) $owner->category_id);

        $shownLineIds = collect($shown['lines'])->flatten()->pluck('id')->map(fn ($id) => (int) $id);

        foreach ($shownLineIds as $id) {
            $this->assertTrue(
                $picks['lines']->contains($id),
                "الخيار #{$id} معروضٌ للبيع ويرفضه الحفظ"
            );
        }
    }

    /** والدور ما زال قائمًا فى الجدول — فُتح الحاجز ولم تُمسح المعرفة. */
    public function test_the_platform_role_is_unchanged_in_the_data(): void
    {
        $role = DB::table('option_groups')->where('name_ar', 'فئة جهاز الألعاب')->value('price_role');

        $this->assertSame(OptionGroup::ROLE_MODIFIER, $role, 'الدور نفسه تغيّر بدل أن يصير ترتيبًا');
    }
}
