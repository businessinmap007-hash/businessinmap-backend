<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\BookableItem;
use App\Models\BookableItemBlockedSlot;
use App\Models\BookableItemPriceRule;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BookableItemCalendarController extends Controller
{
    public function index(Request $request, BookableItem $bookableItem)
    {
        $month = max(1, min(12, (int) $request->get('month', now()->month)));
        $year  = max(2024, (int) $request->get('year', now()->year));

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
                  });
            })
            ->orderBy('priority')
            ->get();

        $days = [];
        $cursor = $calendarStart->copy();

        while ($cursor->lte($calendarEnd)) {
            $date = $cursor->toDateString();

            $dayBlocked = $blockedSlots->filter(function ($slot) use ($cursor) {
                return $cursor->between(
                    Carbon::parse($slot->starts_at)->startOfDay(),
                    Carbon::parse($slot->ends_at)->endOfDay()
                );
            })->values();

            $dayRules = $priceRules->filter(function ($rule) use ($date) {
                if (!$rule->start_date || !$rule->end_date) {
                    return false;
                }

                return $date >= $rule->start_date->toDateString()
                    && $date <= $rule->end_date->toDateString();
            })->values();

            $days[] = [
                'date' => $date,
                'day' => $cursor->day,
                'is_current_month' => $cursor->month === $monthStart->month,
                'is_today' => $cursor->isToday(),
                'blocked_count' => $dayBlocked->count(),
                'price_rules_count' => $dayRules->count(),
                'blocked' => $dayBlocked->map(function ($slot) {
                    return [
                        'id' => (int) $slot->id,
                        'block_type' => (string) $slot->block_type,
                        'reason' => (string) ($slot->reason ?? ''),
                        'starts_at' => optional($slot->starts_at)->toDateTimeString(),
                        'ends_at' => optional($slot->ends_at)->toDateTimeString(),
                    ];
                })->values()->all(),
                'rules' => $dayRules->map(function ($rule) {
                    return [
                        'id' => (int) $rule->id,
                        'title' => (string) ($rule->title ?? ''),
                        'rule_type' => (string) ($rule->rule_type ?? ''),
                        'price_type' => (string) ($rule->price_type ?? ''),
                        'price_value' => (float) ($rule->price_value ?? 0),
                        'currency' => (string) ($rule->currency ?? 'EGP'),
                    ];
                })->values()->all(),
            ];

            $cursor->addDay();
        }

        $prev = $monthStart->copy()->subMonth();
        $next = $monthStart->copy()->addMonth();

        return view('admin-v2.bookable-items.calendar', [
            'bookableItem' => $bookableItem,
            'monthStart' => $monthStart,
            'monthEnd' => $monthEnd,
            'days' => $days,
            'prevMonth' => $prev->month,
            'prevYear' => $prev->year,
            'nextMonth' => $next->month,
            'nextYear' => $next->year,
        ]);
    }

    public function storeBlockedSlot(Request $request, BookableItem $bookableItem)
    {
        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'block_type' => ['required', 'string', 'max:50'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        BookableItemBlockedSlot::create([
            'bookable_item_id' => $bookableItem->id,
            'business_id' => $bookableItem->business_id,
            'platform_service_id' => $bookableItem->service_id,
            'block_type' => $data['block_type'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'reason' => $data['reason'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
            'is_active' => true,
        ]);

        return back()->with('success', 'تمت إضافة فترة الغلق');
    }

    public function storePriceRule(Request $request, BookableItem $bookableItem)
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:150'],
            'rule_type' => ['required', 'string', 'max:50'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'price_type' => ['required', 'string', 'max:20'],
            'price_value' => ['required', 'numeric'],
            'currency' => ['nullable', 'string', 'size:3'],
            'priority' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ]);

        BookableItemPriceRule::create([
            'bookable_item_id' => $bookableItem->id,
            'business_id' => $bookableItem->business_id,
            'platform_service_id' => $bookableItem->service_id,
            'title' => $data['title'] ?? null,
            'rule_type' => $data['rule_type'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'price_type' => $data['price_type'],
            'price_value' => $data['price_value'],
            'currency' => strtoupper($data['currency'] ?? 'EGP'),
            'priority' => $data['priority'] ?? 100,
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
            'is_active' => true,
        ]);

        return back()->with('success', 'تمت إضافة قاعدة التسعير');
    }
}
