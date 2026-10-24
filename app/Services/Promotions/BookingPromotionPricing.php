<?php

namespace App\Services\Promotions;

use App\Models\BookableItem;
use App\Models\Booking;
use App\Models\BusinessServicePrice;
use App\Models\Order;
use App\Models\Promotion;
use Illuminate\Support\Collection;

/**
 * «عروض الحجز»: the same promotions, priced into a booking's nights.
 *
 * A hotel writes the offer once — a percentage or an amount off the night, or «ادفع ليلتين وأقم ثلاثًا» — on a room, on a kind of
 * room, or on all of them, optionally only for stays inside a season (`params.stay_from` / `stay_to`). The booking engine asks
 * here for the best offer on the nights it is pricing and takes it off the total, night by night on the room's own price (the
 * extras a guest ticks are not discounted).
 *
 * One offer per booking, the one that takes the most off; it does not stack with the stay-length discount of the unit (the
 * larger of the two applies). The «no fake discount» rule is the same: a room whose price rose recently is not discounted yet.
 */
final class BookingPromotionPricing
{
    public function __construct(private readonly PromotionPricing $pricing)
    {
    }

    /**
     * The best offer on these nights.
     *
     * @param  list<array{date:string,base_price:float}>  $periods  the nights, each at the room's own price that day
     * @return array{promotion_id:int,amount:float,kind:string}|null
     */
    public function best(BookableItem $unit, ?BusinessServicePrice $price, array $periods, int $units, int $customerId, ?int $sellerId = null): ?array
    {
        if ($periods === [] || ($price && $this->pricing->eligibleFrom(BusinessServicePrice::class, (int) $price->id) !== null)) {
            return null;
        }

        $best = null;

        foreach ($this->matching($unit, $customerId, $sellerId) as $promotion) {
            $nights = $this->nightsOf($promotion, $periods);
            $amount = round($this->discountOf($promotion, $nights) * max($units, 1), 2);

            if ($amount > 0 && (! $best || $amount > $best['amount'])) {
                $best = ['promotion_id' => (int) $promotion->id, 'amount' => $amount, 'kind' => $promotion->kind];
            }
        }

        return $best;
    }

    /** One more use of the offer a booking was priced by. @param array<string,mixed> $breakdown the booking's price breakdown */
    public function commitUsage(array $breakdown): void
    {
        $id = (int) ($breakdown['offer_promotion_id'] ?? 0);

        if ($id > 0 && (float) ($breakdown['offer_discount_amount'] ?? 0) > 0) {
            Promotion::query()->whereKey($id)->increment('used_count');
            \App\Support\RequestMemo::bump('promotions');
        }
    }

    /**
     * What a room's card says about its offer: the price of a night after it (a percentage or an amount) or the words of the rule.
     *
     * @return array{promotion_id:int,kind:string,params:array<string,mixed>,price_after:?float}|null
     */
    public function badge(BookableItem $unit, ?BusinessServicePrice $price, float $nightPrice, int $customerId, ?int $sellerId = null): ?array
    {
        if ($nightPrice <= 0 || ($price && $this->pricing->eligibleFrom(BusinessServicePrice::class, (int) $price->id) !== null)) {
            return null;
        }

        $out = null;

        foreach ($this->matching($unit, $customerId, $sellerId) as $promotion) {
            $after = $this->pricing->unitPriceAfter($promotion, $nightPrice);
            $saving = $after !== null ? $nightPrice - $after : 0.0;

            if (! $out || ($after !== null && ($out['price_after'] === null || $saving > $out['saving']))) {
                $out = ['promotion_id' => (int) $promotion->id, 'kind' => $promotion->kind, 'params' => $promotion->params, 'price_after' => $after, 'saving' => $saving];
            }
        }

        return $out ? array_diff_key($out, ['saving' => 0]) : null;
    }

    /** @return Collection<int,Promotion> the running promotions that name this room, its kind, or everything */
    private function matching(BookableItem $unit, int $customerId, ?int $sellerId = null): Collection
    {
        // a company selling a hotel's room through an allocation offers with ITS OWN promotions, not the hotel's
        $owner = $sellerId ?: (int) $unit->business_id;
        $keys = ['all:0', 'bookable_item:' . $unit->id, 'bookable_kind:' . (int) ($unit->line_option_id ?? 0)];

        $running = \App\Support\RequestMemo::remember('promotions', 'running:' . $owner, fn () => Promotion::query()->where('business_id', $owner)->running()->with('targets')->orderBy('id')->get());

        return $running
            ->filter(fn (Promotion $p) => $p->kind !== Promotion::SPEND_PERCENT)
            ->filter(fn (Promotion $p) => array_intersect($keys, $p->targets->map(fn ($t) => $t->target_type . ':' . ($t->target_id ?? 0))->all()) !== [])
            ->filter(fn (Promotion $p) => $p->audience !== Promotion::AUDIENCE_NEW || $this->isNewCustomer($owner, $customerId))
            ->values();
    }

    /** A customer we cannot name is not called new. */
    private function isNewCustomer(int $businessId, int $customerId): bool
    {
        if ($customerId <= 0) {
            return false;
        }

        return ! Booking::query()->where('business_id', $businessId)->where('user_id', $customerId)->whereNotIn('status', [Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED])->exists()
            && ! Order::query()->where('business_id', $businessId)->where('user_id', $customerId)->where('status', '!=', 'cart')->exists();
    }

    /** @return list<float> the nightly prices the offer applies to (inside the season it names, if it names one) */
    private function nightsOf(Promotion $promotion, array $periods): array
    {
        $from = $promotion->params['stay_from'] ?? null;
        $to = $promotion->params['stay_to'] ?? null;

        return array_values(array_map(
            fn (array $p) => (float) $p['base_price'],
            array_filter($periods, fn (array $p) => (! $from || $p['date'] >= substr((string) $from, 0, 10)) && (! $to || $p['date'] <= substr((string) $to, 0, 10)))
        ));
    }

    /** @param list<float> $nights */
    private function discountOf(Promotion $promotion, array $nights): float
    {
        if ($nights === []) {
            return 0.0;
        }

        switch ($promotion->kind) {
            case Promotion::PERCENT:
                return array_sum($nights) * min(90.0, max(0.0, $promotion->param('value'))) / 100;

            case Promotion::AMOUNT:
                $off = max(0.0, $promotion->param('value'));

                return array_sum(array_map(fn ($n) => min($n, $off), $nights));

            case Promotion::N_FOR_PRICE_OF:
                $n = (int) $promotion->param('n');
                $pay = (int) $promotion->param('pay', 1);

                if ($n < 2 || $pay < 1 || $pay >= $n) {
                    return 0.0;
                }

                // «ادفع ليلتين وأقم ثلاثًا»: in every run of n nights the cheapest (n - pay) are free
                rsort($nights);
                $cap = (int) ($promotion->params['max_groups'] ?? 0);
                $free = 0.0;
                $taken = 0;

                foreach (array_chunk($nights, $n) as $group) {
                    if (count($group) < $n || ($cap > 0 && $taken >= $cap)) {
                        continue;
                    }
                    $taken++;
                    $free += array_sum(array_slice($group, $pay));
                }

                return $free;
        }

        return 0.0;
    }
}
