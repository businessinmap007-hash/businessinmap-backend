<?php

namespace App\Http\Controllers\Business;

use App\Enums\BookingPattern;
use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\BusinessBookingSetting;
use App\Models\CategoryServiceConfig;
use App\Models\PlatformService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «إعدادات الحجز» لصاحب النشاط — الدرجة الوسطى التى لم تكن موجودة.
 *
 * الطفل يفتح الأنماط، وهذه الشاشة هى المكان الذى يقول فيه صاحبُ المحل أيَّها
 * هو وكيف يعمل: عندى ستّة أجهزة أم لا وحدات عندى، الفترة نصف ساعة، أذهب
 * للعميل ولا يأتى إلىّ.
 *
 * الحفظ لا يُنشئ صفًّا فارغًا: نشاطٌ بلا صفٍّ يسلك سلوك نمطه بالضبط، وهذا هو
 * السلوك الصحيح لا الناقص.
 */
class BookingSettingsController extends Controller
{
    use ResolvesOwnerCatalog;

    public function edit(): View
    {
        $patterns = $this->patternsOfChild();
        $row = BusinessBookingSetting::query()
            ->firstOrNew(['business_id' => $this->businessId()]);

        $chosen = $row->pattern() ?? ($patterns[0] ?? null);

        return view('business.booking-settings.edit', [
            'row' => $row,
            'patterns' => $patterns,
            'chosen' => $chosen,
            'shape' => $chosen ? BusinessBookingSetting::resolve($chosen, $row->exists ? $row : null) : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $patterns = $this->patternsOfChild();

        $data = $request->validate([
            'pattern' => ['nullable', Rule::in(array_map(fn (BookingPattern $p) => $p->value, $patterns))],
            'uses_units' => ['nullable', 'in:0,1'],
            'slot_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'min_nights' => ['nullable', 'integer', 'min:1', 'max:255'],
            'lead_time_minutes' => ['nullable', 'integer', 'min:0', 'max:525600'],
            'visit_mode' => ['nullable', Rule::in(BusinessBookingSetting::visitModes())],
            'channels' => ['nullable', 'array'],
            'channels.*' => [Rule::in(BusinessBookingSetting::channels())],
            'notes_label' => ['nullable', 'string', 'max:120'],
        ], [], [
            'pattern' => 'نمط الحجز',
            'slot_minutes' => 'طول الفترة',
            'min_nights' => 'أقل عدد ليالٍ',
            'lead_time_minutes' => 'مهلة الحجز المسبق',
            'notes_label' => 'عنوان حقل الملاحظات',
        ]);

        BusinessBookingSetting::updateOrCreate(
            ['business_id' => $this->businessId()],
            [
                'pattern' => $data['pattern'] ?? null,
                // «لم أقرّر» ليست «لا»: الفراغ يعيد السؤال إلى النمط.
                'uses_units' => ($data['uses_units'] ?? '') === '' ? null : (bool) $data['uses_units'],
                'slot_minutes' => $data['slot_minutes'] ?? null,
                'min_nights' => $data['min_nights'] ?? null,
                'lead_time_minutes' => $data['lead_time_minutes'] ?? null,
                'visit_mode' => $data['visit_mode'] ?? null,
                'channels' => $data['channels'] ?? null,
                'notes_label' => $data['notes_label'] ?? null,
            ]
        );

        return back()->with('success', 'تم حفظ إعدادات الحجز بنجاح.');
    }

    /**
     * الأنماط التى يفتحها تصنيفُ هذا النشاط — الأوّل أساسىٌّ.
     *
     * تُقرأ من إعداد (الجذر، الابن) لا من الابن وحده: صاحبُ النشاط يقف على
     * جذرٍ بعينه، وإعدادُ خدمته هناك هو ما يخصّه.
     *
     * @return BookingPattern[]
     */
    private function patternsOfChild(): array
    {
        $serviceId = (int) PlatformService::query()
            ->where('key', PlatformService::KEY_BOOKING)
            ->where('is_active', 1)
            ->value('id');

        if ($serviceId <= 0 || $this->childId() <= 0) {
            return [];
        }

        $config = CategoryServiceConfig::query()
            ->where('child_id', $this->childId())
            ->where('platform_service_id', $serviceId)
            ->where('is_active', 1)
            ->when($this->rootId() > 0, fn ($q) => $q->where('category_id', $this->rootId()))
            ->value('config');

        $config = is_array($config) ? $config : (json_decode((string) $config, true) ?: []);

        $declared = $config['booking_patterns'] ?? array_filter([$config['booking_pattern'] ?? null]);

        return array_values(array_filter(array_map(
            fn ($value) => BookingPattern::tryFrom((string) $value),
            is_array($declared) ? $declared : []
        )));
    }
}
