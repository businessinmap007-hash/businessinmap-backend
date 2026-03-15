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
    /**
     * Resolve final pricing for a bookable item.
     *
     * Fallback order:
     * 1) matching active price rules
     * 2) base price from bookable_items.price
     *
     * @param  \App\Models\BookableItem  $item
     * @param  \Carbon\CarbonInterface|string|null  $date
     * @param  int  $quantity
     * @return array{
     *   ok: bool,
     *   item_id: int,
     *   date: ?string,
     *   quantity: int,
     *   base_price: float,
     *   unit_price: float,
     *   final_price: float,
     *   currency: string,
     *   rule: ?array,
     *   breakdown: array<int, array<string, mixed>>
     * }
     */
    public function resolve(BookableItem $item, CarbonInterface|string|null $date = null, int $quantity = 1): array
    {
        $quantity = max($quantity, 1);
        $resolvedDate = $this->normalizeDate($date);

        $basePrice = (float) ($item->price ?? 0);
        $currency  = 'EGP';

        $matchedRule = null;
        $unitPrice   = $basePrice;
        $breakdown   = [];

        $rules = $this->findMatchingRules($item, $resolvedDate);

        if ($rules->isNotEmpty()) {
            /** @var \App\Models\BookableItemPriceRule $matchedRule */
            $matchedRule = $rules->first();

            $currency = (string) ($matchedRule->currency ?: 'EGP');
            $unitPrice = $this->applyRule(
                basePrice: $basePrice,
                priceType: (string) $matchedRule->price_type,
                priceValue: (float) $matchedRule->price_value
            );

            $breakdown[] = [
                'type' => 'base_price',
                'label' => 'Base price',
                'amount' => round($basePrice, 2),
            ];

            $breakdown[] = [
                'type' => 'rule_applied',
                'label' => 'Price rule applied',
                'rule_id' => (int) $matchedRule->id,
                'rule_type' => (string) $matchedRule->rule_type,
                'price_type' => (string) $matchedRule->price_type,
                'price_value' => (float) $matchedRule->price_value,
                'amount' => round($unitPrice, 2),
            ];
        } else {
            $breakdown[] = [
                'type' => 'base_price',
                'label' => 'Base price',
                'amount' => round($basePrice, 2),
            ];
        }

        $finalPrice = round($unitPrice * $quantity, 2);

        return [
            'ok' => true,
            'item_id' => (int) $item->id,
            'date' => $resolvedDate?->toDateString(),
            'quantity' => $quantity,
            'base_price' => round($basePrice, 2),
            'unit_price' => round($unitPrice, 2),
            'final_price' => $finalPrice,
            'currency' => $currency,
            'rule' => $matchedRule ? [
                'id' => (int) $matchedRule->id,
                'title' => (string) ($matchedRule->title ?? ''),
                'rule_type' => (string) ($matchedRule->rule_type ?? ''),
                'price_type' => (string) ($matchedRule->price_type ?? ''),
                'price_value' => (float) ($matchedRule->price_value ?? 0),
                'start_date' => optional($matchedRule->start_date)->toDateString(),
                'end_date' => optional($matchedRule->end_date)->toDateString(),
                'weekday' => $matchedRule->weekday !== null ? (int) $matchedRule->weekday : null,
                'priority' => (int) ($matchedRule->priority ?? 100),
                'currency' => (string) ($matchedRule->currency ?? 'EGP'),
            ] : null,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Resolve by item id.
     */
    public function resolveById(int $bookableItemId, CarbonInterface|string|null $date = null, int $quantity = 1): array
    {
        $item = BookableItem::query()->findOrFail($bookableItemId);

        return $this->resolve($item, $date, $quantity);
    }

    /**
     * Return matching active rules ordered by priority.
     *
     * @param  \App\Models\BookableItem  $item
     * @param  \Carbon\CarbonInterface|null  $date
     * @return \Illuminate\Support\Collection<int, \App\Models\BookableItemPriceRule>
     */
    protected function findMatchingRules(BookableItem $item, ?CarbonInterface $date): Collection
    {
        $query = BookableItemPriceRule::query()
            ->forBookableItem($item->id)
            ->active();

        if ($date) {
            $query->forDate($date);

            $weekday = (int) $date->dayOfWeek;
            $query->forWeekday($weekday);
        }

        return $query->ordered()->get();
    }

    /**
     * Apply a pricing rule on top of the base price.
     *
     * fixed   => final = value
     * delta   => final = base + value
     * percent => final = base + (base * value / 100)
     */
    protected function applyRule(float $basePrice, string $priceType, float $priceValue): float
    {
        return match ($priceType) {
            BookableItemPriceRule::PRICE_FIXED => max($priceValue, 0),
            BookableItemPriceRule::PRICE_DELTA => max($basePrice + $priceValue, 0),
            BookableItemPriceRule::PRICE_PERCENT => max($basePrice + ($basePrice * $priceValue / 100), 0),
            default => $basePrice,
        };
    }

    /**
     * Normalize incoming date to Carbon date object.
     */
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
