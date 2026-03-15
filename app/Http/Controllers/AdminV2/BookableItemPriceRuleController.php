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
        return view('admin-v2.bookable-items.price-rules.create', [
            'item' => $bookableItem,
        ]);
    }

    public function store(Request $request, BookableItem $bookableItem)
    {
        $data = $this->validateData($request);

        $data['bookable_item_id'] = $bookableItem->id;
        $data['business_id'] = $bookableItem->business_id;
        $data['platform_service_id'] = $bookableItem->service_id;
        $data['created_by'] = auth()->id();

        BookableItemPriceRule::create($data);

        return redirect()
            ->route('admin.bookable-items.price-rules.index', $bookableItem)
            ->with('success', 'تم إضافة قاعدة التسعير');
    }

    public function destroy(BookableItem $bookableItem, BookableItemPriceRule $rule)
    {
        if ((int) $rule->bookable_item_id !== (int) $bookableItem->id) {
            throw ValidationException::withMessages([
                'rule' => 'قاعدة التسعير لا تتبع هذا العنصر.',
            ]);
        }

        $rule->delete();

        return back()->with('success', 'تم حذف القاعدة');
    }

    protected function validateData(Request $request): array
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
        ]);

        $data['currency'] = strtoupper((string) ($data['currency'] ?? 'EGP'));
        $data['priority'] = (int) ($data['priority'] ?? 100);
        $data['is_active'] = (bool) $request->input('is_active', 1);

        if ($data['rule_type'] === BookableItemPriceRule::RULE_WEEKDAY && $request->filled('weekday') === false) {
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
            && (!$request->filled('start_date') || !$request->filled('end_date'))
        ) {
            throw ValidationException::withMessages([
                'start_date' => 'حقلا Start Date و End Date مطلوبان لهذا النوع من القواعد.',
                'end_date' => 'حقلا Start Date و End Date مطلوبان لهذا النوع من القواعد.',
            ]);
        }

        if ($data['price_type'] === BookableItemPriceRule::PRICE_PERCENT) {
            if ((float) $data['price_value'] < -100) {
                throw ValidationException::withMessages([
                    'price_value' => 'في حالة percent لا يمكن أن تقل القيمة عن -100.',
                ]);
            }
        }

        return $data;
    }
}
