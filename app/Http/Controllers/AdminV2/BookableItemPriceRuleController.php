<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use App\Models\BookableItemPriceRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookableItemPriceRuleController extends Controller
{
    public function index(BookableItem $bookableItem)
    {
        $bookableItem->loadMissing([
            'service:id,key,name_ar,name_en',
            'business:id,name,type',
        ]);

        $rules = $bookableItem->priceRules()
            ->orderBy('priority')
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin-v2.bookable-items.price-rules.index', [
            'item' => $bookableItem,
            'rules' => $rules,
        ]);
    }

    public function create(BookableItem $bookableItem)
    {
        $bookableItem->loadMissing([
            'service:id,key,name_ar,name_en',
            'business:id,name,type',
        ]);

        return view('admin-v2.bookable-items.price-rules.create', [
            'item' => $bookableItem,
        ]);
    }

    public function store(Request $request, BookableItem $bookableItem)
    {
        $data = $this->validateData($request, $bookableItem);

        $data['bookable_item_id'] = $bookableItem->id;
        $data['business_id'] = $bookableItem->business_id;
        $data['platform_service_id'] = $bookableItem->service_id;
        $data['created_by'] = auth()->id();

        BookableItemPriceRule::create($data);

        return redirect()
            ->route('admin.bookable-items.price-rules.index', $bookableItem)
            ->with('success', 'تم إضافة قاعدة التسعير بنجاح.');
    }

    public function destroy(BookableItem $bookableItem, BookableItemPriceRule $rule)
    {
        if ((int) $rule->bookable_item_id !== (int) $bookableItem->id) {
            throw ValidationException::withMessages([
                'rule' => 'قاعدة التسعير لا تتبع هذا العنصر.',
            ]);
        }

        $rule->delete();

        return back()->with('success', 'تم حذف قاعدة التسعير بنجاح.');
    }

    protected function validateData(Request $request, BookableItem $bookableItem): array
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:150'],

            'rule_type' => [
                'required',
                'string',
                Rule::in([
                    BookableItemPriceRule::RULE_DEFAULT,
                    BookableItemPriceRule::RULE_WEEKDAY,
                    BookableItemPriceRule::RULE_DATE_RANGE,
                    BookableItemPriceRule::RULE_SEASON,
                    BookableItemPriceRule::RULE_SPECIAL_DAY,
                ]),
            ],

            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'weekday' => ['nullable', 'integer', 'min:0', 'max:6'],

            'price_type' => [
                'required',
                'string',
                Rule::in([
                    BookableItemPriceRule::PRICE_FIXED,
                    BookableItemPriceRule::PRICE_DELTA,
                    BookableItemPriceRule::PRICE_PERCENT,
                ]),
            ],

            'price_value' => ['required', 'numeric'],
            'currency' => ['nullable', 'string', 'size:3'],
            'priority' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable'],
        ], [], [
            'title' => 'العنوان',
            'rule_type' => 'نوع القاعدة',
            'start_date' => 'تاريخ البداية',
            'end_date' => 'تاريخ النهاية',
            'weekday' => 'اليوم الأسبوعي',
            'price_type' => 'نوع السعر',
            'price_value' => 'قيمة السعر',
            'currency' => 'العملة',
            'priority' => 'الأولوية',
            'notes' => 'الملاحظات',
            'is_active' => 'الحالة',
        ]);

        $data['currency'] = strtoupper(trim((string) ($data['currency'] ?? 'EGP')));
        $data['priority'] = (int) ($data['priority'] ?? 100);
        $data['is_active'] = $request->boolean('is_active', true);

        if ($data['rule_type'] === BookableItemPriceRule::RULE_WEEKDAY && ! $request->filled('weekday')) {
            throw ValidationException::withMessages([
                'weekday' => 'حقل Weekday مطلوب عند اختيار rule_type = weekday.',
            ]);
        }

        if (
            in_array($data['rule_type'], [
                BookableItemPriceRule::RULE_DATE_RANGE,
                BookableItemPriceRule::RULE_SEASON,
                BookableItemPriceRule::RULE_SPECIAL_DAY,
            ], true)
            && (! $request->filled('start_date') || ! $request->filled('end_date'))
        ) {
            throw ValidationException::withMessages([
                'start_date' => 'حقلا Start Date و End Date مطلوبان لهذا النوع من القواعد.',
                'end_date' => 'حقلا Start Date و End Date مطلوبان لهذا النوع من القواعد.',
            ]);
        }

        if ($data['rule_type'] === BookableItemPriceRule::RULE_DEFAULT) {
            $data['start_date'] = null;
            $data['end_date'] = null;
            $data['weekday'] = null;
        }

        if ($data['rule_type'] === BookableItemPriceRule::RULE_WEEKDAY) {
            $data['start_date'] = null;
            $data['end_date'] = null;
        }

        if (
            in_array($data['rule_type'], [
                BookableItemPriceRule::RULE_DATE_RANGE,
                BookableItemPriceRule::RULE_SEASON,
                BookableItemPriceRule::RULE_SPECIAL_DAY,
            ], true)
        ) {
            $data['weekday'] = null;
        }

        if ($data['price_type'] === BookableItemPriceRule::PRICE_PERCENT && (float) $data['price_value'] < -100) {
            throw ValidationException::withMessages([
                'price_value' => 'في حالة percent لا يمكن أن تقل القيمة عن -100.',
            ]);
        }

        if (
            in_array($data['rule_type'], [
                BookableItemPriceRule::RULE_DATE_RANGE,
                BookableItemPriceRule::RULE_SEASON,
                BookableItemPriceRule::RULE_SPECIAL_DAY,
            ], true)
            && ! empty($data['start_date'])
            && ! empty($data['end_date'])
        ) {
            $overlapExists = BookableItemPriceRule::query()
                ->where('bookable_item_id', $bookableItem->id)
                ->where('is_active', true)
                ->where('rule_type', $data['rule_type'])
                ->where(function ($query) use ($data) {
                    $query
                        ->whereBetween('start_date', [$data['start_date'], $data['end_date']])
                        ->orWhereBetween('end_date', [$data['start_date'], $data['end_date']])
                        ->orWhere(function ($sub) use ($data) {
                            $sub->where('start_date', '<=', $data['start_date'])
                                ->where('end_date', '>=', $data['end_date']);
                        });
                })
                ->exists();

            if ($overlapExists) {
                throw ValidationException::withMessages([
                    'start_date' => 'يوجد بالفعل Rule نشطة متداخلة مع نفس النطاق الزمني.',
                ]);
            }
        }

        return $data;
    }
   public function edit(BookableItem $bookableItem, BookableItemPriceRule $rule)
    {
        abort_unless((int) $rule->bookable_item_id === (int) $bookableItem->id, 404);

        return view('admin-v2.bookable-items.price-rules.edit', compact('bookableItem', 'rule'));
    }

    public function update(Request $request, BookableItem $bookableItem, BookableItemPriceRule $rule)
    {
        abort_unless((int) $rule->bookable_item_id === (int) $bookableItem->id, 404);

        $data = $request->validate([
            'title'       => ['nullable', 'string', 'max:150'],
            'rule_type'   => [
                'required',
                'string',
                Rule::in([
                    BookableItemPriceRule::RULE_DEFAULT,
                    BookableItemPriceRule::RULE_WEEKDAY,
                    BookableItemPriceRule::RULE_DATE_RANGE,
                    BookableItemPriceRule::RULE_SEASON,
                    BookableItemPriceRule::RULE_SPECIAL_DAY,
                ]),
            ],
            'start_date'  => ['nullable', 'date'],
            'end_date'    => ['nullable', 'date', 'after_or_equal:start_date'],
            'weekday'     => ['nullable', 'integer', 'min:0', 'max:6'],
            'price_type'  => [
                'required',
                'string',
                Rule::in([
                    BookableItemPriceRule::PRICE_FIXED,
                    BookableItemPriceRule::PRICE_DELTA,
                    BookableItemPriceRule::PRICE_PERCENT,
                ]),
            ],
            'price_value' => ['required', 'numeric'],
            'currency'    => ['nullable', 'string', 'size:3'],
            'priority'    => ['nullable', 'integer', 'min:1'],
            'notes'       => ['nullable', 'string'],
            'is_active'   => ['nullable', 'boolean'],
        ]);

        $rule->update([
            'title'       => $data['title'] ?? null,
            'rule_type'   => $data['rule_type'],
            'start_date'  => $data['start_date'] ?? null,
            'end_date'    => $data['end_date'] ?? null,
            'weekday'     => $data['weekday'] ?? null,
            'price_type'  => $data['price_type'],
            'price_value' => $data['price_value'],
            'currency'    => strtoupper((string) ($data['currency'] ?? 'EGP')),
            'priority'    => (int) ($data['priority'] ?? 100),
            'notes'       => $data['notes'] ?? null,
            'is_active'   => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()
            ->route('admin.bookable-items.calendar', ['bookableItem' => $bookableItem->id])
            ->with('success', 'تم تحديث قاعدة السعر بنجاح');
    }
}