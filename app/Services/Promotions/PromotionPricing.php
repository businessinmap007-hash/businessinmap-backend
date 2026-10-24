<?php

namespace App\Services\Promotions;

use App\Models\BusinessCatalogListing;
use App\Models\MenuItem;
use App\Models\OfferingPriceChange;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Promotion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Prices a cart's lines by the promotions of its business — the one place a discount becomes a number the customer pays.
 *
 * ── The rules ───────────────────────────────────────────────────────────────
 *  - A line carries ONE promotion: of every rule that fits it, the one that takes the most off it (ties go to the older rule). Two
 *    offers never stack on the same line.
 *  - The discount is taken off the item's OWN price — its extras and add-ons are not discounted.
 *  - Never on an item whose price rose by more than the tolerance within the quiet days (config `bim.promotions`: 15 days, 5%) —
 *    the «no fake discount» rule — nor on a line bought on instalments (that price belongs to its plan).
 *  - The service fee and the tax are billed on what is left, because the line's `total_price` IS what is left; `discount` and
 *    `promotion_id` only remember what was promised.
 *  - Only a CART is repriced. An order that was placed keeps the line prices it was placed with.
 *
 * Idempotent: every pass starts from the list price, so calling it after any change of the cart gives the same answer.
 */
final class PromotionPricing
{
    /** @var array<string,bool> quiet-month answers per item, for this run */
    private array $quiet = [];

    public function apply(Order $order): void
    {
        if ((string) $order->status !== 'cart') {
            return;
        }

        $items = $order->items()->get();

        // start from the list price, whatever happened before
        foreach ($items as $line) {
            $gross = round((float) $line->price * (float) $line->qty, 2);

            if ((float) $line->total_price !== $gross || (float) $line->discount !== 0.0 || $line->promotion_id !== null) {
                $line->forceFill(['total_price' => $gross, 'discount' => 0, 'promotion_id' => null])->save();
            }
        }

        $promotions = $this->running((int) $order->business_id, (int) $order->user_id, (int) $order->id);

        if ($promotions->isEmpty()) {
            return;
        }

        $lines = $this->eligibleLines($items);

        if ($lines->isEmpty()) {
            return;
        }

        /** @var array<int,array{discount:float,promotion:int}> $best */
        $best = [];

        foreach ($promotions as $promotion) {
            foreach ($this->allocate($promotion, $lines) as $lineId => $discount) {
                if ($discount > 0 && (! isset($best[$lineId]) || $discount > $best[$lineId]['discount'])) {
                    $best[$lineId] = ['discount' => $discount, 'promotion' => (int) $promotion->id];
                }
            }
        }

        foreach ($items as $line) {
            if (! isset($best[$line->id])) {
                continue;
            }

            $gross = round((float) $line->price * (float) $line->qty, 2);
            $discount = round(min($best[$line->id]['discount'], $gross), 2);

            $line->forceFill(['discount' => $discount, 'promotion_id' => $best[$line->id]['promotion'], 'total_price' => round($gross - $discount, 2)])->save();
        }
    }

    /** The order was placed: every promotion that priced it has been used once more. */
    public function commitUsage(Order $order): void
    {
        $ids = $order->items()->where('discount', '>', 0)->whereNotNull('promotion_id')->pluck('promotion_id')->unique()->all();

        if ($ids !== []) {
            Promotion::query()->whereIn('id', $ids)->increment('used_count');
        }
    }

    /**
     * What a promotion takes off one unit of an item on its own — for the badge on an item card. Only a percentage or an amount can
     * say it for a single item; the other kinds are described, not priced.
     */
    public function unitPriceAfter(Promotion $promotion, float $unitPrice): ?float
    {
        return match ($promotion->kind) {
            Promotion::PERCENT => round($unitPrice * (1 - min(90.0, $promotion->param('value')) / 100), 2),
            Promotion::AMOUNT => round(max($unitPrice - $promotion->param('value'), 0), 2),
            default => null,
        };
    }

    /** @return Collection<int,Promotion> the promotions this customer may use on this business right now, oldest first */
    public function running(int $businessId, int $customerId, int $exceptOrderId = 0): Collection
    {
        $promotions = Promotion::query()->where('business_id', $businessId)->running()->with('targets')->orderBy('id')->get();

        if ($promotions->contains('audience', Promotion::AUDIENCE_NEW)) {
            $returning = Order::query()
                ->where('business_id', $businessId)->where('user_id', $customerId)
                ->where('status', '!=', 'cart')->where('id', '!=', $exceptOrderId)->exists();

            if ($returning) {
                $promotions = $promotions->reject(fn (Promotion $p) => $p->audience === Promotion::AUDIENCE_NEW)->values();
            }
        }

        return $promotions;
    }

    /**
     * The lines a promotion could touch, with what each is made of.
     *
     * @return Collection<int,array{id:int,item:string,keys:list<string>,base:float,qty:float}>
     */
    private function eligibleLines(Collection $items): Collection
    {
        $shop = $items->filter(fn (OrderItem $l) => in_array($l->offering_type, [MenuItem::class, BusinessCatalogListing::class], true)
            && $l->payment_plan_id === null && ! $l->isRemoved());

        $menuIds = $shop->where('offering_type', MenuItem::class)->pluck('offering_id')->unique()->all();
        $listingIds = $shop->where('offering_type', BusinessCatalogListing::class)->pluck('offering_id')->unique()->all();

        $sections = $menuIds ? MenuItem::query()->whereIn('id', $menuIds)->pluck('menu_section_id', 'id')->all() : [];
        $categories = $listingIds
            ? DB::table('business_catalog_listings as l')->join('catalog_products as p', 'p.id', '=', 'l.catalog_product_id')
                ->whereIn('l.id', $listingIds)->pluck('p.product_category_id', 'l.id')->all()
            : [];

        return $shop->map(function (OrderItem $l) use ($sections, $categories) {
            $isMenu = $l->offering_type === MenuItem::class;
            $keys = $isMenu
                ? ['all', 'menu_item:' . $l->offering_id, 'menu_section:' . ($sections[$l->offering_id] ?? 0)]
                : ['all', 'listing:' . $l->offering_id, 'listing_category:' . ($categories[$l->offering_id] ?? 0)];

            $addons = collect($l->addons ?? [])->sum(fn ($a) => (float) ($a['price'] ?? 0) * (int) ($a['qty'] ?? 1));

            return [
                'id' => (int) $l->id,
                'item' => $l->offering_type . ':' . $l->offering_id,
                'keys' => $keys,
                'base' => round(max((float) $l->price - $addons, 0), 2),
                'qty' => (float) $l->qty,
                'quiet' => $this->quietMonth($l->offering_type, (int) $l->offering_id),
            ];
        })->filter(fn (array $l) => $l['quiet'] && $l['base'] > 0)->values();
    }

    /** True when the item's price has not risen (by more than the tolerance) within the quiet days — a discount on it is a real one. */
    public function quietMonth(string $type, int $id): bool
    {
        $key = $type . ':' . $id;

        if (! isset($this->quiet[$key])) {
            $this->quiet[$key] = $this->eligibleFrom($type, $id) === null;
        }

        return $this->quiet[$key];
    }

    /** The day the item may be discounted again, or null if it may be now. */
    public function eligibleFrom(string $type, int $id): ?Carbon
    {
        // asked once per room and per card of the same price row in one page: read once
        return \App\Support\RequestMemo::remember('price-history', $type . ':' . $id, fn () => $this->readEligibleFrom($type, $id));
    }

    private function readEligibleFrom(string $type, int $id): ?Carbon
    {
        $model = $type::query()->find($id);

        if (! $model) {
            return null;
        }

        $tolerance = (float) config('bim.promotions.rise_tolerance_percent', 5);
        $days = (int) config('bim.promotions.quiet_days', 15);

        $at = OfferingPriceChange::query()->for($model)->increases()
            ->whereRaw('old_price > 0 AND (new_price - old_price) * 100 / old_price > ?', [$tolerance])
            ->max('changed_at');

        $from = $at ? Carbon::parse($at)->addDays($days) : null;

        return $from && $from->isFuture() ? $from : null;
    }

    /** The two numbers of the rule, for the screen that explains it to the merchant. @return array{quiet_days:int,rise_tolerance_percent:float} */
    public function rules(): array
    {
        return ['quiet_days' => (int) config('bim.promotions.quiet_days', 15), 'rise_tolerance_percent' => (float) config('bim.promotions.rise_tolerance_percent', 5)];
    }

    /**
     * What one promotion takes off each line it fits.
     *
     * @param  Collection<int,array{id:int,item:string,keys:list<string>,base:float,qty:float}>  $lines
     * @return array<int,float> line id => discount
     */
    private function allocate(Promotion $promotion, Collection $lines): array
    {
        $wanted = $promotion->targets->map(fn ($t) => $t->target_type === Promotion::TARGET_ALL ? 'all' : $t->target_type . ':' . $t->target_id)->all();
        $fit = $lines->filter(fn (array $l) => array_intersect($l['keys'], $wanted) !== [])->values();

        if ($fit->isEmpty()) {
            return [];
        }

        $out = [];

        switch ($promotion->kind) {
            case Promotion::PERCENT:
                $rate = min(90.0, max(0.0, $promotion->param('value'))) / 100;
                foreach ($fit as $l) {
                    $out[$l['id']] = round($l['base'] * $l['qty'] * $rate, 2);
                }
                break;

            case Promotion::AMOUNT:
                $off = max(0.0, $promotion->param('value'));
                foreach ($fit as $l) {
                    $out[$l['id']] = round(min($l['base'], $off) * $l['qty'], 2);
                }
                break;

            case Promotion::SPEND_PERCENT:
                $spent = $fit->sum(fn (array $l) => $l['base'] * $l['qty']);
                if ($spent >= $promotion->param('min_total') && $promotion->param('min_total') > 0) {
                    $rate = min(90.0, max(0.0, $promotion->param('value'))) / 100;
                    foreach ($fit as $l) {
                        $out[$l['id']] = round($l['base'] * $l['qty'] * $rate, 2);
                    }
                }
                break;

            case Promotion::N_FOR_PRICE_OF:
                $n = (int) $promotion->param('n');
                $pay = (int) $promotion->param('pay', 1);

                if ($n < 2 || $pay < 1 || $pay >= $n) {
                    break;
                }

                // «اشترِ pay واحصل على n»: every whole piece, dearest first; in each group of n the cheapest (n - pay) are free.
                // With `same_item` a group is made of pieces of ONE item; `max_groups` caps how many groups an order may take.
                $sameItem = (bool) ($promotion->params['same_item'] ?? false);
                $cap = (int) ($promotion->params['max_groups'] ?? 0);
                $taken = 0;

                $pools = [];
                foreach ($fit as $l) {
                    for ($i = 0, $whole = (int) floor($l['qty']); $i < $whole; $i++) {
                        $pools[$sameItem ? $l['item'] : 'all'][] = ['id' => $l['id'], 'price' => $l['base']];
                    }
                }

                foreach ($pools as $units) {
                    usort($units, fn ($a, $b) => $b['price'] <=> $a['price']);

                    foreach (array_chunk($units, $n) as $group) {
                        if (count($group) < $n || ($cap > 0 && $taken >= $cap)) {
                            continue;
                        }
                        $taken++;
                        foreach (array_slice($group, $pay) as $free) {
                            $out[$free['id']] = round(($out[$free['id']] ?? 0) + $free['price'], 2);
                        }
                    }
                }
                break;
        }

        return $out;
    }
}
