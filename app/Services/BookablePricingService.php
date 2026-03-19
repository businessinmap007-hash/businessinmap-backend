<?php

namespace App\Services;

use App\Models\BookableItem;
use App\Models\BookableItemPriceRule;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class BookablePricingService
{
    public function resolve(
        BookableItem $item,
        CarbonInterface|string|null $date = null,
        int $quantity = 1
    ): array {
        $quantity = max($quantity, 1);
        $resolvedDate = $this->normalizeDate($date);

        $day = $this->resolveDay($item, $resolvedDate, $quantity);

        return [
            'ok' => true,
            'item_id' => (int) $item->id,
            'date' => $resolvedDate?->toDateString(),
            'quantity' => $quantity,
            'base_price' => (float) $day['base_price'],
            'unit_price' => (float) $day['unit_price'],
            'final_price' => (float) $day['final_price'],
            'currency' => (string) $day['currency'],
            'rule' => $day['rule'],
            'rules' => $day['rules'],
            'breakdown' => $day['breakdown'],
            'pricing_source' => $day['pricing_source'],
        ];
    }

    public function resolveById(
        int $bookableItemId,
        CarbonInterface|string|null $date = null,
        int $quantity = 1
    ): array {
        $item = BookableItem::query()->findOrFail($bookableItemId);

        return $this->resolve($item, $date, $quantity);
    }

    /**
     * مهم للتقويم والمدى.
     */
    public function resolveRange(
        BookableItem $item,
        CarbonInterface|string $startDate,
        CarbonInterface|string $endDate,
        int $quantity = 1
    ): array {
        $quantity = max($quantity, 1);

        $start = $this->normalizeDate($startDate)?->startOfDay();
        $end   = $this->normalizeDate($endDate)?->startOfDay();

        if (! $start || ! $end) {
            throw new InvalidArgumentException('Invalid pricing range.');
        }

        if ($end->lt($start)) {
            throw new InvalidArgumentException('End date must be greater than or equal to start date.');
        }

        $days = [];
        $total = 0.0;
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $day = $this->resolveDay($item, $cursor, $quantity);

            $days[] = [
                'date' => $cursor->toDateString(),
                'base_price' => (float) $day['base_price'],
                'unit_price' => (float) $day['unit_price'],
                'final_price' => (float) $day['final_price'],
                'currency' => (string) $day['currency'],
                'rule' => $day['rule'],
                'rules' => $day['rules'],
                'breakdown' => $day['breakdown'],
                'pricing_source' => $day['pricing_source'],
            ];

            $total += (float) $day['final_price'];
            $cursor->addDay();
        }

        return [
            'ok' => true,
            'item_id' => (int) $item->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'quantity' => $quantity,
            'days_count' => count($days),
            'currency' => $days[0]['currency'] ?? 'EGP',
            'total' => round($total, 2),
            'days' => $days,
        ];
    }

    /**
     * مهم للكـاليندر الشهري.
     */
    public function buildCalendarMonth(
        BookableItem $item,
        int $year,
        int $month,
        int $quantity = 1
    ): array {
        $month = max(1, min(12, $month));
        $year  = max(2024, $year);

        $monthStart = Carbon::create($year, $month, 1)->startOfMonth();
        $monthEnd   = Carbon::create($year, $month, 1)->endOfMonth();

        $calendarStart = $monthStart->copy()->startOfWeek(Carbon::SATURDAY);
        $calendarEnd   = $monthEnd->copy()->endOfWeek(Carbon::FRIDAY);

        $days = [];
        $cursor = $calendarStart->copy();

        while ($cursor->lte($calendarEnd)) {
            $day = $this->resolveDay($item, $cursor, $quantity);

            $days[] = [
                'date' => $cursor->toDateString(),
                'day' => (int) $cursor->day,
                'is_current_month' => $cursor->month === $monthStart->month,
                'is_today' => $cursor->isToday(),
                'base_price' => (float) $day['base_price'],
                'unit_price' => (float) $day['unit_price'],
                'final_price' => (float) $day['final_price'],
                'currency' => (string) $day['currency'],
                'rule' => $day['rule'],
                'rules' => $day['rules'],
                'has_rule' => ! empty($day['rule']),
                'pricing_source' => (string) $day['pricing_source'],
                'breakdown' => $day['breakdown'],
            ];

            $cursor->addDay();
        }

        return [
            'ok' => true,
            'item_id' => (int) $item->id,
            'month_start' => $monthStart,
            'month_end' => $monthEnd,
            'calendar_start' => $calendarStart,
            'calendar_end' => $calendarEnd,
            'days' => $days,
        ];
    }

    protected function resolveDay(
        BookableItem $item,
        ?CarbonInterface $date,
        int $quantity = 1
    ): array {
        $quantity = max($quantity, 1);

        $basePrice = round((float) ($item->price ?? 0), 2);
        $currency  = 'EGP';

        $rules = $this->findMatchingRules($item, $date, $quantity);

        $matchedRule = $rules->first();
        $unitPrice = $basePrice;
        $pricingSource = 'base_price';

        $breakdown = [
            [
                'type' => 'base_price',
                'label' => 'Base price',
                'amount' => $basePrice,
            ],
        ];

        if ($matchedRule) {
            $unitPrice = $this->applyRule(
                basePrice: $basePrice,
                priceType: (string) $matchedRule->price_type,
                priceValue: (float) $matchedRule->price_value
            );

            $currency = (string) ($matchedRule->currency ?: 'EGP');
            $pricingSource = 'price_rule';

            $breakdown[] = [
                'type' => 'rule_applied',
                'label' => 'Price rule applied',
                'rule_id' => (int) $matchedRule->id,
                'title' => (string) ($matchedRule->title ?? ''),
                'rule_type' => (string) ($matchedRule->rule_type ?? ''),
                'price_type' => (string) ($matchedRule->price_type ?? ''),
                'price_value' => (float) ($matchedRule->price_value ?? 0),
                'priority' => (int) ($matchedRule->priority ?? 100),
                'amount' => round($unitPrice, 2),
            ];
        }

        $finalPrice = round($unitPrice * $quantity, 2);

        return [
            'base_price' => $basePrice,
            'unit_price' => round($unitPrice, 2),
            'final_price' => $finalPrice,
            'currency' => $currency,
            'pricing_source' => $pricingSource,
            'rule' => $matchedRule ? $this->mapRule($matchedRule) : null,
            'rules' => $rules->map(fn ($rule) => $this->mapRule($rule))->values()->all(),
            'breakdown' => $breakdown,
        ];
    }

    protected function findMatchingRules(
        BookableItem $item,
        ?CarbonInterface $date,
        int $quantity = 1
    ): Collection {
        $query = BookableItemPriceRule::query()
            ->forBookableItem((int) $item->id)
            ->active();

        if ($date) {
            $query->forDate($date);
            $query->forWeekday((int) $date->dayOfWeek);
        }

        $rules = $query->ordered()->get();

        return $rules->filter(function (BookableItemPriceRule $rule) use ($quantity) {
            $min = $rule->min_quantity !== null ? (int) $rule->min_quantity : null;
            $max = $rule->max_quantity !== null ? (int) $rule->max_quantity : null;

            if ($min !== null && $quantity < $min) {
                return false;
            }

            if ($max !== null && $quantity > $max) {
                return false;
            }

            return true;
        })->values();
    }

    protected function applyRule(float $basePrice, string $priceType, float $priceValue): float
    {
        return match ($priceType) {
            BookableItemPriceRule::PRICE_FIXED   => max($priceValue, 0),
            BookableItemPriceRule::PRICE_DELTA   => max($basePrice + $priceValue, 0),
            BookableItemPriceRule::PRICE_PERCENT => max($basePrice + ($basePrice * $priceValue / 100), 0),
            default                              => $basePrice,
        };
    }

    protected function mapRule(BookableItemPriceRule $rule): array
    {
        return [
            'id' => (int) $rule->id,
            'title' => (string) ($rule->title ?? ''),
            'rule_type' => (string) ($rule->rule_type ?? ''),
            'price_type' => (string) ($rule->price_type ?? ''),
            'price_value' => (float) ($rule->price_value ?? 0),
            'currency' => (string) ($rule->currency ?? 'EGP'),
            'weekday' => $rule->weekday !== null ? (int) $rule->weekday : null,
            'start_date' => optional($rule->start_date)->toDateString(),
            'end_date' => optional($rule->end_date)->toDateString(),
            'min_quantity' => $rule->min_quantity !== null ? (int) $rule->min_quantity : null,
            'max_quantity' => $rule->max_quantity !== null ? (int) $rule->max_quantity : null,
            'priority' => (int) ($rule->priority ?? 100),
        ];
    }

    protected function normalizeDate(CarbonInterface|string|null $date): ?Carbon
    {
        if ($date === null || $date === '') {
            return null;
        }

        if ($date instanceof CarbonInterface) {
            return Carbon::instance($date);
        }

        try {
            return Carbon::parse($date);
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('Invalid pricing date.');
        }
    }
}