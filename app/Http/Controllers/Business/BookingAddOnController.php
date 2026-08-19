<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use App\Models\BusinessServicePrice;
use App\Models\OfferingOption;
use App\Models\PlatformService;
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
        $rows = $this->priceRows();

        return view('business.booking-add-ons.index', [
            'vocabulary' => $this->vocabulary->for($this->businessId(), $this->childId(), $this->rootId())['modifiers'],
            'addOns' => $this->currentAddOns($rows),
            'kinds' => $this->kindsInPlay($rows),
            'declared' => $this->declaredByUnits(),
        ]);
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

        $allowed = $this->vocabulary
            ->pickableIds($this->businessId(), $this->childId(), $this->rootId())['modifiers'];

        $chosen = collect($data['option_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $allowed->contains($id))
            ->unique()->values();

        $declared = $this->declaredByUnits();
        $rows = $this->priceRows();

        if ($rows->isEmpty()) {
            return back()->withErrors([
                'option_ids' => __('سعّر نوعًا واحدًا على الأقل أولًا — الإضافة تُكتب على سطر السعر.'),
            ]);
        }

        foreach ($rows as $row) {
            /*
             * ما تعلنه وحداتُ هذا النوع عن نفسها يبقى كما هو.
             *
             * `syncOfferingOptions` تمسح ثم تكتب، وهذه الشاشةُ لا تعرض
             * الإطلالةَ ولا تُسأل عنها — فلو كتبت ما عندها وحده لمحت
             * «إطلالة بحرية +١٠٠» من كل غرفةٍ تطلّ على البحر، صامتةً.
             */
            $keep = $row->offeringOptions()
                ->where('role', OfferingOption::ROLE_MODIFIER)
                ->pluck('option_id')->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $declared->contains($id));

            $existing = $row->currentOfferingAdjustments();

            $adjustments = [];

            foreach ($chosen as $id) {
                $type = (string) (($data['adjust_type'] ?? [])[$id] ?? OfferingOption::ADJUST_AMOUNT);

                $adjustments[$id] = [
                    'type' => in_array($type, OfferingOption::adjustTypes(), true)
                        ? $type
                        : OfferingOption::ADJUST_AMOUNT,
                    'value' => round((float) (($data['adjust'] ?? [])[$id] ?? 0), 2),
                    // «ليس الافطار فى الغرفة الفردى مثل الغرفة الثلاثية».
                    'per_person' => (bool) (($data['per_person'] ?? [])[$id] ?? false),
                ];
            }

            // زياداتُ الصفات تُنقل كما هى: ليست من شأن هذه الشاشة.
            foreach ($keep as $id) {
                $adjustments[$id] = $existing[$id]
                    ?? ['type' => OfferingOption::ADJUST_AMOUNT, 'value' => 0, 'per_person' => false];
            }

            $row->syncOfferingOptions(
                (int) ($row->line_option_id ?? 0) ?: null,
                $keep->merge($chosen)->unique()->values()->all(),
                $adjustments
            );
        }

        return back()->with('success', __('تم حفظ الإضافات على :count نوعًا.', ['count' => $rows->count()]));
    }

    /** سطورُ أسعار الحجز عند هذا التاجر. */
    private function priceRows(): Collection
    {
        $serviceId = (int) PlatformService::query()
            ->where('key', PlatformService::KEY_BOOKING)->value('id');

        return BusinessServicePrice::query()
            ->where('business_id', $this->businessId())
            ->where('service_id', $serviceId)
            ->where('is_active', 1)
            ->get();
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

    /**
     * الإضافاتُ المكتوبة الآن: ما على سطور الأسعار ولم تعلنه وحدة.
     *
     * تُقرأ من أوّل سطرٍ يحملها لا من مجموعها، لأن الشاشةَ تكتبها على الجميع
     * دفعةً — فاختلافُها بين نوعين يعنى أنّ أحدًا عدّلها من شاشة الوحدة، وأوّلُ
     * ما يُعرض هو ما سيُكتب على الكلّ عند الحفظ.
     *
     * @return array<int,array{type:string,value:float}>
     */
    private function currentAddOns(Collection $rows): array
    {
        $declared = $this->declaredByUnits();
        $out = [];

        foreach ($rows as $row) {
            foreach ($row->currentOfferingAdjustments() as $id => $adjust) {
                if ($declared->contains((int) $id) || array_key_exists((int) $id, $out)) {
                    continue;
                }

                $out[(int) $id] = $adjust;
            }
        }

        return $out;
    }

    /** أسماءُ الأنواع التى ستُكتب عليها — تُعرض حتى يعرف على ماذا يوقّع. */
    private function kindsInPlay(Collection $rows): array
    {
        return $rows->map(function (BusinessServicePrice $row) {
            $option = $row->lineOption();

            return $option
                ? (string) ($option->name_ar ?: $option->name_en)
                : __('السعر العام');
        })->unique()->values()->all();
    }
}
