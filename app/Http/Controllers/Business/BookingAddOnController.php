<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\BookableItem;
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
            'addOns' => $this->business()->currentOfferingAdjustments(),
            'declared' => $this->declaredByUnits(),
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

    /** ومعرّفاتُها، لقبول ما يُرسَل. */
    private function addOnIds(): Collection
    {
        return $this->addOnVocabulary()->flatten(1)
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
            'adjust' => ['nullable', 'array'],
            'adjust_type' => ['nullable', 'array'],
            'per_person' => ['nullable', 'array'],
        ]);

        // ما عرضته الشاشةُ هو ما يُقبل: قائمةُ التسعير وحدها كانت ترفض ما
        // أعلنه التاجرُ إضافةً وهو وصفىٌّ عند المنصّة.
        $allowed = $this->addOnIds();

        /*
         * وما أعلنته وحدةٌ عن نفسها لا يصلح إضافة.
         *
         * «إطلالة بحرية» صفةُ غرفةٍ بعينها وثمنُها داخلَ سعرها المعروض، فقبولُها
         * هنا يعرضها على النزيل ويُحصّلها مرّةً ثانية.
         */
        $declared = $this->declaredByUnits();

        $chosen = collect($data['option_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $allowed->contains($id) && ! $declared->contains($id))
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

        /*
         * تُكتب على النشاط، لا على سطور الأسعار.
         *
         * كانت تُنسخ على كل سطرِ سعرٍ عنده، فارتبطت بالغرف: لا تُكتب قبل أن
         * يُسعَّر نوعٌ، وتُنسى مع نوعٍ يُضاف بعدها، وتبدو كأنها تتغيّر بتغيّر
         * الغرفة. وهى لا تتغيّر — «خدمة ثابته مع كل الغرف».
         */
        $this->business()->syncOfferingOptions(null, $chosen->all(), $adjustments);

        return back()->with('success', __('تم حفظ الإضافات.'));
    }

    /**
     * ما أعلنته وحداتُ التاجر عن نفسها — إطلالةً أو بلكونة.
     *
     * @return \Illuminate\Support\Collection<int,int>
     */
    private function declaredByUnits(): Collection
    {
        return OfferingOption::query()
            ->where('offering_type', (new BookableItem)->getMorphClass())
            ->whereIn('offering_id', BookableItem::query()
                ->where('business_id', $this->businessId())->select('id'))
            ->where('role', OfferingOption::ROLE_MODIFIER)
            ->pluck('option_id')->map(fn ($id) => (int) $id)->unique();
    }

}
