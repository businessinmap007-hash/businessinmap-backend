<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Business\Concerns\ResolvesOwnerCatalog;
use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * "My bookable units" for the business owner.
 *
 * Pure inventory: the owner adds the physical units they actually own
 * (room 101, table 5) picking from the item types allowed for their own
 * category_child. Prices/deposits live in BusinessServicePrice (per type),
 * not here, per the services blueprint. Every query is scoped to the owner.
 */
class BookableItemController extends Controller
{
    use ResolvesOwnerCatalog;

    /** أكثر ما تنشئه دفعةٌ واحدة — حارسٌ من خطأٍ مطبعىٍّ فى «إلى». */
    private const BULK_LIMIT = 200;

    private function scopedItem(int $id): BookableItem
    {
        return BookableItem::query()
            ->where('business_id', $this->businessId())
            ->findOrFail($id);
    }

    public function index(Request $request): View
    {
        $serviceId = (int) $request->get('service_id', 0);
        $q = trim((string) $request->get('q', ''));

        $services = $this->servicesForChild();

        $rows = BookableItem::query()
            ->with(['service:id,key,name_ar,name_en', 'lineOption:id,name_ar,name_en'])
            ->where('business_id', $this->businessId())
            ->when($serviceId > 0, fn ($query) => $query->where('service_id', $serviceId))
            ->when($q !== '', function ($query) use ($q) {
                $term = '%' . mb_strtolower($q) . '%';
                $query->where(function ($sub) use ($term) {
                    $sub->whereRaw('LOWER(code) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(title) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(item_type) LIKE ?', [$term]);
                });
            })
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('business.bookable-items.index', [
            'rows' => $rows,
            'services' => $services,
            'serviceId' => $serviceId,
            'q' => $q,
            'childId' => $this->childId(),
        ]);
    }

    public function create(): View
    {
        $services = $this->servicesForChild();

        return view('business.bookable-items.create', [
            'row' => new BookableItem(['is_active' => 1, 'quantity' => 1]),
            'services' => $services,
            'allowedTypesByService' => $this->allowedTypesByService($services),
            'lineOptions' => $this->lineOptionsForUnits(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        BookableItem::create($data + [
            'business_id' => $this->businessId(),
        ]);

        return redirect()
            ->route('business.bookable-items.index', ['service_id' => $data['service_id']])
            ->with('success', 'تمت إضافة الوحدة بنجاح.');
    }

    /**
     * «٦ غرف فردى و١٠ مزدوجة و٥ أجنحة، من ١٠١ إلى ١٠٦ ومن ١٠٧ إلى ١١١».
     *
     * الشاشةُ القديمة تُنشئ وحدةً واحدة لكل حفظ، فواحدٌ وعشرون غرفةً واحدٌ
     * وعشرون نموذجًا لا يتغيّر بينها إلا رقم.
     */
    public function bulk(): View
    {
        $services = $this->servicesForChild();

        return view('business.bookable-items.bulk', [
            'services' => $services,
            'allowedTypesByService' => $this->allowedTypesByService($services),
            'lineOptions' => $this->lineOptionsForUnits(),
            'unitOptions' => $this->unitOptions(),
        ]);
    }

    /**
     * ينشئ المدى دفعةً واحدة، ويتخطّى ما هو موجود.
     *
     * التخطّى لا الفشل: من أضاف ١٠١–١٠٦ ثم عاد ليضيف ١٠١–١١٠ يقصد الأربعةَ
     * الناقصة، ورفضُ الدفعة كلِّها لأجل صفٍّ قائم يجعله يحسب المدى بنفسه —
     * وهذا هو العمل الذى جاء ليتخلّص منه.
     */
    public function bulkStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer'],
            'item_type' => ['required', 'string', 'max:100'],
            'line_option_id' => ['nullable', 'integer'],
            'prefix' => ['nullable', 'string', 'max:40'],
            'from' => ['required', 'integer', 'min:0'],
            'to' => ['required', 'integer', 'min:0', 'gte:from'],
            'pad' => ['nullable', 'integer', 'min:0', 'max:6'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'option_ids' => ['nullable', 'array'],
            'option_ids.*' => ['integer'],
        ], [], [
            'service_id' => 'الخدمة',
            'item_type' => 'نوع العنصر',
            'from' => 'من',
            'to' => 'إلى',
        ]);

        $serviceId = (int) $data['service_id'];
        $itemType = trim((string) $data['item_type']);

        $this->assertAllowed($serviceId, $itemType);

        $from = (int) $data['from'];
        $to = (int) $data['to'];

        // حدٌّ أعلى للدفعة: خطأٌ مطبعىٌّ فى «إلى» لا يجوز أن يصنع ألف غرفة.
        if (($to - $from) >= self::BULK_LIMIT) {
            throw ValidationException::withMessages([
                'to' => __('المدى أكبر من :max وحدة فى الدفعة الواحدة.', ['max' => self::BULK_LIMIT]),
            ]);
        }

        $lineOptionId = $this->sanitizeLineOption($data['line_option_id'] ?? null);
        $modifiers = $this->sanitizeUnitOptions($data['option_ids'] ?? [], $lineOptionId);

        $prefix = trim((string) ($data['prefix'] ?? ''));
        $pad = (int) ($data['pad'] ?? 0);

        $existing = BookableItem::query()
            ->where('business_id', $this->businessId())
            ->pluck('code')
            ->map(fn ($code) => (string) $code)
            ->flip();

        $created = 0;
        $skipped = 0;

        for ($n = $from; $n <= $to; $n++) {
            $code = $prefix . ($pad > 0 ? str_pad((string) $n, $pad, '0', STR_PAD_LEFT) : (string) $n);

            if (isset($existing[$code])) {
                $skipped++;

                continue;
            }

            $item = BookableItem::create([
                'business_id' => $this->businessId(),
                'service_id' => $serviceId,
                'item_type' => $itemType,
                'line_option_id' => $lineOptionId,
                'code' => $code,
                'capacity' => ! empty($data['capacity']) ? (int) $data['capacity'] : null,
                'quantity' => 1,
                'is_active' => 1,
            ]);

            // صفاتُ الوحدة نفسها — الإطلالة، البلكونة — تُسعِّرها لاحقًا بلا
            // أن يؤشّرها النزيل. ولا زيادةَ تُكتب هنا: السعرُ صفةُ سطرِ السعر.
            if ($modifiers !== []) {
                $item->syncOfferingOptions($lineOptionId, $modifiers);
            }

            $created++;
        }

        return redirect()
            ->route('business.bookable-items.index', ['service_id' => $serviceId])
            ->with('success', __('أُضيفت :created وحدة، وتُخطّيت :skipped موجودة.', [
                'created' => $created,
                'skipped' => $skipped,
            ]));
    }

    /** ما يصلح صفةً للوحدة: مُوصِّفاتُ هذا التاجر. */
    private function unitOptions()
    {
        return app(\App\Services\MerchantOfferingVocabulary::class)
            ->for($this->businessId(), $this->childId(), $this->rootId())['modifiers'];
    }

    /** @return array<int,int> */
    private function sanitizeUnitOptions(array $ids, ?int $lineOptionId): array
    {
        $allowed = app(\App\Services\MerchantOfferingVocabulary::class)
            ->pickableIds($this->businessId(), $this->childId(), $this->rootId())['modifiers'];

        return collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $id !== (int) $lineOptionId && $allowed->contains($id))
            ->unique()->values()->all();
    }

    public function edit(int $id): View
    {
        $row = $this->scopedItem($id);
        $services = $this->servicesForChild();

        return view('business.bookable-items.edit', [
            'row' => $row,
            'services' => $services,
            'allowedTypesByService' => $this->allowedTypesByService($services),
            'lineOptions' => $this->lineOptionsForUnits(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $row = $this->scopedItem($id);

        $data = $this->validateData($request);

        $row->update($data);

        return back()->with('success', 'تم تحديث الوحدة بنجاح.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $row = $this->scopedItem($id);
        $row->delete();

        return redirect()
            ->route('business.bookable-items.index')
            ->with('success', 'تم حذف الوحدة بنجاح.');
    }

    protected function validateData(Request $request): array
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer'],
            'item_type' => ['required', 'string', 'max:100'],
            'line_option_id' => ['nullable', 'integer'],
            'code' => ['required', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:191'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable'],
        ], [], [
            'service_id' => 'الخدمة',
            'item_type' => 'نوع العنصر',
            'code' => 'الكود',
        ]);

        $serviceId = (int) $data['service_id'];
        $itemType = trim((string) $data['item_type']);

        // Guard the posted select values against the owner's own catalog.
        $this->assertAllowed($serviceId, $itemType);

        return [
            'service_id' => $serviceId,
            'item_type' => $itemType,
            // Which kind this unit is — «جناح» rather than just «حجز إقامة» —
            // and therefore which of the merchant's priced rows is its own.
            'line_option_id' => $this->sanitizeLineOption($data['line_option_id'] ?? null),
            'code' => trim((string) $data['code']),
            'title' => trim((string) ($data['title'] ?? '')) ?: null,
            'capacity' => ! empty($data['capacity']) ? (int) $data['capacity'] : null,
            'quantity' => max(1, (int) ($data['quantity'] ?? 1)),
            'is_active' => (int) $request->boolean('is_active'),
        ];
    }
}
