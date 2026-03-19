<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use App\Models\BookableItemBlockedSlot;
use App\Models\BookableItemPriceRule;
use App\Services\BookablePricingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

class BookableItemCalendarController extends Controller
{
    public function index(
        Request $request,
        BookableItem $bookableItem,
        BookablePricingService $pricingService
    ) {
        $month = max(1, min(12, (int) $request->get('month', now()->month)));
        $year  = max(2024, (int) $request->get('year', now()->year));
        $quantity = max(1, (int) $request->get('quantity', 1));

        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd   = Carbon::create($year, $month, 1)->endOfMonth();

        $calendarStart = $monthStart->copy()->startOfWeek(Carbon::SATURDAY);
        $calendarEnd   = $monthEnd->copy()->endOfWeek(Carbon::FRIDAY);

        $blockedSlots = $bookableItem->blockedSlots()
            ->where('is_active', 1)
            ->where(function ($q) use ($calendarStart, $calendarEnd) {
                $q->whereBetween('starts_at', [$calendarStart, $calendarEnd])
                    ->orWhereBetween('ends_at', [$calendarStart, $calendarEnd])
                    ->orWhere(function ($w) use ($calendarStart, $calendarEnd) {
                        $w->where('starts_at', '<=', $calendarStart)
                            ->where('ends_at', '>=', $calendarEnd);
                    });
            })
            ->orderBy('starts_at')
            ->get();

        $priceRules = $bookableItem->priceRules()
            ->where('is_active', 1)
            ->where(function ($q) use ($calendarStart, $calendarEnd) {
                $q->whereBetween('start_date', [$calendarStart->toDateString(), $calendarEnd->toDateString()])
                    ->orWhereBetween('end_date', [$calendarStart->toDateString(), $calendarEnd->toDateString()])
                    ->orWhere(function ($w) use ($calendarStart, $calendarEnd) {
                        $w->where('start_date', '<=', $calendarStart->toDateString())
                            ->where('end_date', '>=', $calendarEnd->toDateString());
                    })
                    ->orWhere(function ($w) {
                        $w->whereNull('start_date')->whereNull('end_date');
                    });
            })
            ->orderBy('priority')
            ->orderByDesc('id')
            ->get();

        $hasBlockedEdit = Route::has('admin.bookable-items.blocked-slots.edit');
        $hasRuleEdit    = Route::has('admin.bookable-items.price-rules.edit');

        $days = [];
        $cursor = $calendarStart->copy();

        while ($cursor->lte($calendarEnd)) {
            $date = $cursor->toDateString();

            $pricing = $pricingService->resolve(
                item: $bookableItem,
                date: $date,
                quantity: $quantity
            );

            $dayBlocked = $blockedSlots->filter(function ($slot) use ($cursor) {
                return $cursor->between(
                    Carbon::parse($slot->starts_at)->startOfDay(),
                    Carbon::parse($slot->ends_at)->endOfDay()
                );
            })->values();

            $dayRules = $priceRules->filter(function ($rule) use ($cursor) {
                $dateMatch = true;

                if ($rule->start_date && $rule->end_date) {
                    $dateMatch = $cursor->toDateString() >= $rule->start_date->toDateString()
                        && $cursor->toDateString() <= $rule->end_date->toDateString();
                }

                $weekdayMatch = $rule->weekday === null
                    || (int) $rule->weekday === (int) $cursor->dayOfWeek;

                return $dateMatch && $weekdayMatch;
            })->values();

            $firstRule = $pricing['rule']
                ?? ($dayRules->isNotEmpty() ? [
                    'id' => (int) $dayRules->first()->id,
                    'title' => (string) ($dayRules->first()->title ?? ''),
                    'rule_type' => (string) ($dayRules->first()->rule_type ?? ''),
                    'price_type' => (string) ($dayRules->first()->price_type ?? ''),
                    'price_value' => (float) ($dayRules->first()->price_value ?? 0),
                    'currency' => (string) ($dayRules->first()->currency ?? 'EGP'),
                    'start_date' => optional($dayRules->first()->start_date)->toDateString(),
                    'end_date' => optional($dayRules->first()->end_date)->toDateString(),
                    'weekday' => $dayRules->first()->weekday !== null ? (int) $dayRules->first()->weekday : null,
                    'priority' => (int) ($dayRules->first()->priority ?? 100),
                ] : null);

            $days[] = [
                'date' => $date,
                'day' => (int) $cursor->day,
                'is_current_month' => $cursor->month === $monthStart->month,
                'is_today' => $cursor->isToday(),

                'base_price' => (float) ($pricing['base_price'] ?? 0),
                'unit_price' => (float) ($pricing['unit_price'] ?? 0),
                'final_price' => (float) ($pricing['final_price'] ?? 0),
                'currency' => (string) ($pricing['currency'] ?? 'EGP'),
                'breakdown' => $pricing['breakdown'] ?? [],

                'rule' => $firstRule,
                'has_rule' => $firstRule !== null,

                'blocked_count' => $dayBlocked->count(),
                'price_rules_count' => $dayRules->count(),
                'is_blocked' => $dayBlocked->isNotEmpty(),

                'blocked' => $dayBlocked->map(function ($slot) use ($bookableItem, $hasBlockedEdit) {
                    return [
                        'id' => (int) $slot->id,
                        'block_type' => (string) $slot->block_type,
                        'reason' => (string) ($slot->reason ?? ''),
                        'starts_at' => optional($slot->starts_at)->toDateTimeString(),
                        'ends_at' => optional($slot->ends_at)->toDateTimeString(),
                        'edit_url' => $hasBlockedEdit
                            ? route('admin.bookable-items.blocked-slots.edit', [
                                'bookableItem' => $bookableItem->id,
                                'slot' => $slot->id,
                            ])
                            : null,
                    ];
                })->values()->all(),

                'rules' => $dayRules->map(function ($rule) use ($bookableItem, $hasRuleEdit) {
                    return [
                        'id' => (int) $rule->id,
                        'title' => (string) ($rule->title ?? ''),
                        'rule_type' => (string) ($rule->rule_type ?? ''),
                        'price_type' => (string) ($rule->price_type ?? ''),
                        'price_value' => (float) ($rule->price_value ?? 0),
                        'currency' => (string) ($rule->currency ?? 'EGP'),
                        'priority' => (int) ($rule->priority ?? 100),
                        'weekday' => $rule->weekday !== null ? (int) $rule->weekday : null,
                        'start_date' => optional($rule->start_date)->toDateString(),
                        'end_date' => optional($rule->end_date)->toDateString(),
                        'edit_url' => $hasRuleEdit
                            ? route('admin.bookable-items.price-rules.edit', [
                                'bookableItem' => $bookableItem->id,
                                'rule' => $rule->id,
                            ])
                            : null,
                    ];
                })->values()->all(),
            ];

            $cursor->addDay();
        }

        $prev = $monthStart->copy()->subMonth();
        $next = $monthStart->copy()->addMonth();

        return view('admin-v2.bookable-items.calendar', [
            'bookableItem' => $bookableItem,
            'monthStart'   => $monthStart,
            'monthEnd'     => $monthEnd,
            'days'         => $days,
            'quantity'     => $quantity,
            'prevMonth'    => $prev->month,
            'prevYear'     => $prev->year,
            'nextMonth'    => $next->month,
            'nextYear'     => $next->year,
        ]);
    }

    public function storeBlockedSlot(Request $request, BookableItem $bookableItem)
    {
        $data = $request->validate([
            'starts_at'  => ['required', 'date'],
            'ends_at'    => ['required', 'date', 'after_or_equal:starts_at'],
            'block_type' => ['required', 'string', 'max:50'],
            'reason'     => ['nullable', 'string', 'max:255'],
            'notes'      => ['nullable', 'string'],
        ]);

        BookableItemBlockedSlot::create([
            'bookable_item_id'    => $bookableItem->id,
            'business_id'         => $bookableItem->business_id,
            'platform_service_id' => $bookableItem->service_id,
            'block_type'          => $data['block_type'],
            'starts_at'           => $data['starts_at'],
            'ends_at'             => $data['ends_at'],
            'reason'              => $data['reason'] ?? null,
            'notes'               => $data['notes'] ?? null,
            'created_by'          => auth()->id(),
            'is_active'           => true,
        ]);

        return back()->with('success', 'تمت إضافة فترة الغلق');
    }

    public function storePriceRule(Request $request, BookableItem $bookableItem)
    {
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
        ]);

        BookableItemPriceRule::create([
            'bookable_item_id'    => $bookableItem->id,
            'business_id'         => $bookableItem->business_id,
            'platform_service_id' => $bookableItem->service_id,
            'title'               => $data['title'] ?? null,
            'rule_type'           => $data['rule_type'],
            'start_date'          => $data['start_date'] ?? null,
            'end_date'            => $data['end_date'] ?? null,
            'weekday'             => $data['weekday'] ?? null,
            'price_type'          => $data['price_type'],
            'price_value'         => $data['price_value'],
            'currency'            => strtoupper((string) ($data['currency'] ?? 'EGP')),
            'priority'            => (int) ($data['priority'] ?? 100),
            'notes'               => $data['notes'] ?? null,
            'created_by'          => auth()->id(),
            'is_active'           => true,
        ]);

        return back()->with('success', 'تمت إضافة قاعدة التسعير');
    }
}