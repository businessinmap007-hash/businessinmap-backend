<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BusinessHoursService;
use App\Services\Media\ImageUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «ملف النشاط» — من أنت، وكيف تُرى، ومتى تفتح.
 *
 * الأعمدة كانت موجودة كلَّها منذ زمن — `logo` و`cover` و`about` و`latitude` —
 * ولا شاشةَ فى لوحة النشاط تكتبها. تُكتب من لوحة الإدارة (أى: من موظّفى
 * المنصّة، لا من صاحب المحل) ومن الـAPI. فصاحبُ فندقٍ داخل لوحته لا يستطيع
 * تغيير شعاره ولا وصفَ فندقه.
 *
 * وليست شاشةَ فنادق: «حقول الفندق كلها هى حقول عامة مفترض وجودها لكل بزنس»
 * — المالك، 2026-08-19. فلا حَجبَ عليها بقدرةٍ ولا بخدمة: كلُّ نشاطٍ له اسمٌ
 * وشعارٌ وموقعٌ ومواعيد، مهما باع.
 *
 * ── ما لا تكتبه ────────────────────────────────────────────────────────────
 *
 * البريدُ وكلمةُ السر هويةُ الدخول لا بيانات العرض، وتغييرُهما بابٌ آخر له
 * تحقّقُه. فيُعرض البريدُ ولا يُحرَّر هنا.
 */
class ProfileController extends Controller
{
    use ResolvesOwnerCatalog;

    public function __construct(private readonly BusinessHoursService $hours)
    {
    }

    private function business(): User
    {
        return $this->actingBusiness() ?: auth()->user();
    }

    public function edit(): View
    {
        $business = $this->business();

        return view('business.profile.edit', [
            'row' => $business,
            'week' => $this->week($business),
            'timezones' => $this->timezones($business),
        ]);
    }

    /**
     * المناطقُ الزمنية المعروضة.
     *
     * أربعُمئة اسمٍ فى قائمةٍ منسدلة ليست خيارًا بل بحثٌ داخل استمارة. تُعرض
     * ما يخدم تجّارَ المنصّة، ومعها ما اختاره هذا التاجر فعلًا حتى لا تمحوه
     * القائمةُ الضيّقة عليه.
     *
     * @return array<int,string>
     */
    private function timezones(User $business): array
    {
        $common = [
            'Africa/Cairo', 'Asia/Riyadh', 'Asia/Dubai', 'Asia/Kuwait', 'Asia/Qatar',
            'Asia/Bahrain', 'Asia/Amman', 'Asia/Beirut', 'Asia/Baghdad', 'Africa/Khartoum',
            'Africa/Tripoli', 'Africa/Tunis', 'Africa/Algiers', 'Africa/Casablanca',
            'Europe/Istanbul', 'Europe/London', 'UTC',
        ];

        return collect($common)
            ->merge([(string) config('app.timezone'), (string) ($business->timezone ?? '')])
            ->filter()->unique()->values()->all();
    }

    /**
     * أيامُ الأسبوع السبعة كاملةً، حاضرُها وغائبُها.
     *
     * الجدولُ يحمل ما كُتب فقط، فمن كتب الأحدَ وحده يرى صفًّا واحدًا ولا يعرف
     * أين يكتب الباقى. الغائبُ يُعرض فارغًا لا يُخفى.
     *
     * @return array<int,array<string,mixed>>
     */
    private function week(User $business): array
    {
        $rows = $this->hours->hoursFor((int) $business->id);

        $names = [
            0 => 'الأحد', 1 => 'الإثنين', 2 => 'الثلاثاء', 3 => 'الأربعاء',
            4 => 'الخميس', 5 => 'الجمعة', 6 => 'السبت',
        ];

        return collect(BusinessHoursService::DAYS)->map(function (int $day) use ($rows, $names) {
            $row = $rows->get($day);

            return [
                'day' => $day,
                'name' => $names[$day],
                'is_closed' => (bool) ($row->is_closed ?? false),
                'open' => $row ? substr((string) $row->open_time, 0, 5) : '',
                'close' => $row ? substr((string) $row->close_time, 0, 5) : '',
                'known' => (bool) $row,
            ];
        })->all();
    }

    public function update(Request $request): RedirectResponse
    {
        $business = $this->business();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'name_en' => ['nullable', 'string', 'max:191'],
            'about' => ['nullable', 'string', 'max:2000'],
            'phone' => ['required', 'string', 'max:15', Rule::unique('users', 'phone')->ignore($business->id)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'timezone' => ['nullable', 'timezone'],
            'logo' => array_merge(['nullable'], ImageUploadService::validationRules()),
            'cover' => array_merge(['nullable'], ImageUploadService::validationRules()),
            'image' => array_merge(['nullable'], ImageUploadService::validationRules()),
            // «احذف الشعار» — خانةٌ داخل الاستمارة نفسها، لا استمارةٌ داخل
            // استمارة: الأخيرةُ HTML غيرُ صالح والمتصفّحُ يبتلع الداخلية.
            'remove' => ['nullable', 'array'],
            'remove.*' => ['string', 'in:logo,cover,image'],
        ], [], [
            'name' => 'الاسم',
            'phone' => 'الهاتف',
        ]);

        $business->fill([
            'name' => trim((string) $data['name']),
            'name_en' => trim((string) ($data['name_en'] ?? '')) ?: null,
            'about' => trim((string) ($data['about'] ?? '')) ?: null,
            'phone' => trim((string) $data['phone']),
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
        ]);

        // `timezone` ليس فى fillable — ولا ينبغى أن يكون: عمودٌ يُكتب من
        // بابين فقط، هذا والـAPI.
        $business->forceFill(['timezone' => $data['timezone'] ?? null]);

        /*
         * الملفُّ القديم يُحذف مع استبداله.
         *
         * وإلا امتلأ القرصُ بشعاراتٍ لا شىءَ يشير إليها ولا شىءَ يجدها: هذه
         * أعمدةُ مسارٍ مفردة، لا معرضٌ له صفوفُه، فلا `HasOwnedImages` تنظّف
         * خلفها.
         */
        $uploads = app(ImageUploadService::class);
        $remove = (array) ($data['remove'] ?? []);

        foreach (['logo', 'cover', 'image'] as $slot) {
            $old = (string) ($business->{$slot} ?? '');

            if ($request->hasFile($slot)) {
                $business->{$slot} = $uploads->store($request->file($slot));
            } elseif (in_array($slot, $remove, true)) {
                $business->{$slot} = null;
            } else {
                continue;
            }

            if ($old !== '') {
                $uploads->delete($old);
            }
        }

        $business->save();

        return back()->with('success', __('تم حفظ ملف النشاط.'));
    }

    /**
     * مواعيدُ الأسبوع.
     *
     * تمرّ على `BusinessHoursService` نفسها التى يقرأ منها بحثُ العملاء
     * «مفتوح الآن»، فما يكتبه صاحبُ المحل هنا هو ما يُقاس به هناك.
     */
    public function updateHours(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'days' => ['required', 'array', 'max:7'],
            'days.*.day' => ['required', 'integer', 'between:0,6'],
            'days.*.is_closed' => ['nullable'],
            'days.*.open' => ['nullable', 'date_format:H:i'],
            'days.*.close' => ['nullable', 'date_format:H:i'],
        ]);

        $entries = collect($data['days'])->map(fn (array $entry) => [
            'day' => (int) $entry['day'],
            'is_closed' => (bool) ($entry['is_closed'] ?? false),
            'open' => $entry['open'] ?? null,
            'close' => $entry['close'] ?? null,
        ])->all();

        $this->hours->save((int) $this->business()->id, $entries);

        return back()->with('success', __('تم حفظ مواعيد العمل.'));
    }
}
