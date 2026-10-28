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
    /** Memoized resolved base price/currency per bookable item id. */
    protected array $baseCache = [];

    public function __construct(
        protected BusinessServicePriceResolver $businessServicePriceResolver,
    ) {
    }

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

    /**
     * قاعدةُ اليوم مطبَّقةً على أساسٍ يملكه النادى.
     *
     * `resolve()` تحلّ الأساسَ بنفسها من `BusinessServicePriceResolver`، وهو
     * ما يحتاجه التقويم. أما محرّكُ التنفيذ فقد حلَّ سطرَ سعره بالفعل — وربما
     * سطرًا بعينه اختاره العميلُ من الشاشة — فإعادةُ حلِّه هنا تصنع أساسين
     * قد يفترقان، ويفوز فى الفاتورة أيُّهما قُرئ أخيرًا.
     *
     * فالمطابقةُ والتطبيقُ يُستعملان كما هما، والأساسُ يأتى من فوق.
     *
     * @return array{price:float,rule:array<string,mixed>|null}
     */
    public function applyRulesTo(
        BookableItem $item,
        float $basePrice,
        CarbonInterface|string|null $date = null,
        int $quantity = 1
    ): array {
        $day = $this->normalizeDate($date);
        $rule = $this->findMatchingRules($item, $day, max($quantity, 1))->first();

        if (! $rule) {
            // «المواسم»: no rule of the room type's own — the hotel's season of the night prices it
            return ($day ? app(\App\Services\Hotels\HotelSeasons::class)->priceNight($item, $basePrice, $day->toDateString()) : null)
                ?? ['price' => round($basePrice, 2), 'rule' => null];
        }

        return [
            'price' => round($this->applyRule(
                basePrice: $basePrice,
                priceType: (string) $rule->price_type,
                priceValue: (float) $rule->price_value
            ), 2),
            'rule' => $this->mapRule($rule),
        ];
    }

    protected function resolveDay(
        BookableItem $item,
        ?CarbonInterface $date,
        int $quantity = 1
    ): array {
        $quantity = max($quantity, 1);

        // Base price is single-source: the BusinessServicePrice for this unit's
        // item type, not the deprecated bookable_items.price column. Per-day
        // price rules (below) still apply on top of this base.
        [$basePrice, $currency] = $this->resolveBase($item);

        $rules = $this->findMatchingRules($item, $date, $quantity);

        $matchedRule = $rules->first();
        $unitPrice = $basePrice;
        $pricingSource = 'base_price';
        $season = ! $matchedRule && $date ? app(\App\Services\Hotels\HotelSeasons::class)->priceNight($item, $basePrice, $date->format('Y-m-d')) : null;

        if ($season) {
            $unitPrice = $season['price'];
            $pricingSource = 'season';
        }

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

    /**
     * Resolve the unit's base price + currency from the single-source
     * BusinessServicePrice for its item type. Memoized per item id so a full
     * month calendar (~42 days) resolves the price once.
     *
     * @return array{0: float, 1: string} [basePrice, currency]
     */
    protected function resolveBase(BookableItem $item): array
    {
        $key = (int) $item->id;

        if (! array_key_exists($key, $this->baseCache)) {
            $price = $this->businessServicePriceResolver->resolveForBookableItem($item);

            $this->baseCache[$key] = [
                round((float) ($price?->baseUnitPrice() ?? 0), 2),
                (string) ($price?->currencyCode() ?? 'EGP'),
            ];
        }

        return $this->baseCache[$key];
    }

    protected function findMatchingRules(
        BookableItem $item,
        ?CarbonInterface $date,
        int $quantity = 1
    ): Collection {
        // the item's active rules are read ONCE and judged in memory: a thirty-night stay used to run thirty
        // identical queries, one per night
        $day = $date ? $date->format('Y-m-d') : null;
        $weekday = $date ? (int) $date->dayOfWeek : null;

        $rules = $this->activeRulesOf((int) $item->id)->filter(function (BookableItemPriceRule $rule) use ($day, $weekday) {
            if ($day !== null) {
                $from = $rule->start_date?->format('Y-m-d');
                $to = $rule->end_date?->format('Y-m-d');
                $dated = $from !== null && $to !== null && $from <= $day && $to >= $day;
                $always = $from === null && $to === null;

                if (! $dated && ! $always) {
                    return false;
                }
            }

            return $weekday === null || $rule->weekday === null || (int) $rule->weekday === $weekday;
        });

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

    /** @var array<string,\Illuminate\Support\Collection> */
    protected array $rulesCache = [];

    /** Every active rule of an item in the order they apply; dropped whenever any rule is saved or deleted. */
    protected function activeRulesOf(int $itemId): Collection
    {
        $key = BookableItemPriceRule::$revision . ':' . $itemId;

        return $this->rulesCache[$key] ??= BookableItemPriceRule::query()->forBookableItem($itemId)->active()->ordered()->get();
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