<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\OfferingOption;
use App\Models\User;
use App\Services\MerchantOfferingVocabulary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * «نظام الوجبات» وأخواته — ما يختاره النزيل وقت الحجز.
 *
 * «هل الافضل اخراج افطار فقط او اقامة كاملة وتسعيرها منفصل بدلا من ادخالها
 * كلها مره، اما اطلالة بحرية او على المسبح تكون مع الغرف منفردة لان ممكن غرفة
 * D117 تكون على المسبح و D118 تطل على البحر» — المالك، 2026-08-20.
 *
 * نعم. الشيئان يبدوان واحدًا — كلاهما كلمةٌ تزيد على السعر — ويفترقان فى
 * نطاقهما، وهو ما لم تكن الشاشةُ تقوله:
 *
 *   الإطلالة  صفةُ غرفةٍ بعينها. D117 على المسبح وD118 على البحر، وهما من
 *             نفس النوع وبنفس السطر. فمكانُها شاشةُ الوحدة الواحدة.
 *
 *   الوجبات   قرارُ النزيل، وهو نفسُه فى كل غرفة. «إفطار +٥٠» تُكتب مرّةً
 *             وتُعرض على كل من يحجز. فمكانُها هنا.
 *
 * ── وأين تُخزَّن ────────────────────────────────────────────────────────────
 *
 * على سطور الأسعار، فى `offering_options` — نفسِ المكان الذى يقرأ منه المحرّكُ
 * الزياداتِ و`BookingController::modifiersOf` الاختياراتِ المعروضة. فلا آليةَ
 * ثانية ولا جدولَ جديد: هذه الشاشةُ تكتب على كل سطورِ حجزِ التاجر دفعةً.
 *
 * وما تعلنه وحدةٌ عن نفسها لا تمسّه: الفصلُ بين الاثنين هو أن الصفةَ مطبوعةٌ
 * على وحدةٍ ما، والإضافةَ ليست. وهو نفسُ الفصل الذى تقرأ به واجهةُ العميل.
 */
class BookingAddOnController extends Controller
{
    use ResolvesOwnerCatalog;

    public function __construct(private readonly MerchantOfferingVocabulary $vocabulary)
    {
    }

    public function index(): View
    {
        return view('business.booking-add-ons.index', [
            // ما أعلنه التاجرُ «إضافة بسعر منفصل» — نظامُ الوجبات ونحوه.
            'vocabulary' => $this->addOnVocabulary(),
            // «لماذا اطلالة بحرية لم تظهر فى تسعير اضافاتى» — لأنها كانت
            // تُسعَّر داخل كل غرفةٍ على حدة. سعرُها واحد، فمكانُه واحد.
            'features' => $this->featureVocabulary(),
            'addOns' => $this->business()->currentOfferingAdjustments(),
        ]);
    }

    /**
     * ما يصلح إضافةً: ما أعلنه التاجرُ `addon`.
     *
     * ويشمل مجموعةً وصفيّةً عند المنصّة أعلنها هو إضافة — «مرافق الإقامة»
     * فيها جيمٌ وسبا، ومن أعلنها إضافةً يقصد بيعَ دخولهما.
     */
    private function addOnVocabulary(): Collection
    {
        return app(\App\Services\BookingVocabularyRoles::class)->only(
            $this->vocabulary->for($this->businessId(), $this->childId(), $this->rootId())['modifiers'],
            $this->businessId(),
            \App\Services\BookingVocabularyRoles::ROLE_ADDON,
            $this->vocabulary->everythingOffered($this->businessId(), $this->childId(), $this->rootId())
        );
    }

    /**
     * وما يميّز وحدةً بعينها: ما أعلنه التاجرُ `unit`.
     *
     * يُسعَّر هنا مرّةً — «الإطلالة البحرية ‎+٢٠٠» — وتُؤشَّر فى شاشة الوحدة
     * أىُّ الغرف تحملها. وكان السعرُ يُكتب داخل كل غرفة، فستُّ غرفٍ مطلّة
     * ستُّ فرصٍ لكتابة رقمٍ مختلف.
     */
    private function featureVocabulary(): Collection
    {
        return app(\App\Services\BookingVocabularyRoles::class)->only(
            $this->vocabulary->for($this->businessId(), $this->childId(), $this->rootId())['modifiers'],
            $this->businessId(),
            \App\Services\BookingVocabularyRoles::ROLE_UNIT,
            $this->vocabulary->everythingOffered($this->businessId(), $this->childId(), $this->rootId())
        );
    }

    /** ومعرّفاتُ ما تعرضه الشاشةُ بالدورين، لقبول ما يُرسَل. */
    private function pickableIds(): Collection
    {
        return $this->addOnVocabulary()->flatten(1)
            ->merge($this->featureVocabulary()->flatten(1))
            ->map(fn ($o) => (int) $o->id)->unique()->values();
    }

    private function business(): User
    {
        return $this->actingBusiness() ?: User::findOrFail($this->businessId());
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'option_ids' => ['nullable', 'array'],
            'option_ids.*' => ['integer'],
            'feature_ids' => ['nullable', 'array'],
            'feature_ids.*' => ['integer'],
            'adjust' => ['nullable', 'array'],
            'adjust_type' => ['nullable', 'array'],
            'per_person' => ['nullable', 'array'],
        ]);

        $allowed = $this->pickableIds();

        /*
         * القائمتان تُكتبان معًا على النشاط.
         *
         * كلاهما سعرٌ يُكتب مرّةً: «إفطار ٥٠ لكل فرد» يختاره النزيل، و«إطلالة
         * بحرية ‎+٢٠٠» تحملها الغرفةُ التى أُشّرت بها. والفرقُ بينهما ليس فى
         * مكان السعر بل فيمن يقرّر — والدورُ المُعلَن هو ما يقوله.
         *
         * وتُحفظ فى نداءٍ واحد لأن `syncOfferingOptions` تمسح ثم تكتب: نداءان
         * يمحو ثانيهما ما كتبه أوّلُهما.
         */
        $chosen = collect($data['option_ids'] ?? [])
            ->merge($data['feature_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $allowed->contains($id))
            ->unique()->values();

        $adjustments = [];

        foreach ($chosen as $id) {
            $type = (string) (($data['adjust_type'] ?? [])[$id] ?? OfferingOption::ADJUST_AMOUNT);

            $adjustments[$id] = [
                'type' => in_array($type, OfferingOption::adjustTypes(), true)
                    ? $type
                    : OfferingOption::ADJUST_AMOUNT,
                'value' => round((float) (($data['adjust'] ?? [])[$id] ?? 0), 2),
                // «يجب ان يضرب عدد الافراد فى سعر وجبة الافطار».
                'per_person' => (bool) (($data['per_person'] ?? [])[$id] ?? false),
            ];
        }

        $this->business()->syncOfferingOptions(null, $chosen->all(), $adjustments);

        return back()->with('success', __('تم حفظ الأسعار.'));
    }

}
