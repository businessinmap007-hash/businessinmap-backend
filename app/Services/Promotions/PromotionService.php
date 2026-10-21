<?php

namespace App\Services\Promotions;

use App\Models\BookableItem;
use App\Models\BusinessCatalogListing;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Promotion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A business writes its promotions. This is where a rule is checked before it can exist: the kind's own numbers, that every target
 * is the business's own, that it has an end, and that it does not give an item away.
 */
final class PromotionService
{
    public function __construct(private readonly PromotionCatalog $catalog, private readonly PromotionPricing $pricing)
    {
    }

    /** @param array<string,mixed> $data the validated request */
    public function create(int $businessId, array $data): Promotion
    {
        $this->check($businessId, $data);

        return DB::transaction(function () use ($businessId, $data) {
            $promotion = Promotion::create($this->fields($data) + ['business_id' => $businessId, 'used_count' => 0, 'is_active' => true]);
            $this->syncTargets($promotion, $data['targets']);

            return $promotion->load('targets');
        });
    }

    /** @param array<string,mixed> $data */
    public function update(Promotion $promotion, array $data): Promotion
    {
        $this->check((int) $promotion->business_id, $data);

        return DB::transaction(function () use ($promotion, $data) {
            $promotion->update($this->fields($data));
            $this->syncTargets($promotion, $data['targets']);

            return $promotion->load('targets');
        });
    }

    public function setActive(Promotion $promotion, bool $active): Promotion
    {
        $promotion->update(['is_active' => $active]);

        return $promotion;
    }

    /** The «no fake discount» rule's two numbers, for the screen that explains it. @return array{quiet_days:int,rise_tolerance_percent:float} */
    public function rules(): array
    {
        return $this->pricing->rules();
    }

    /** @return array<string,mixed> */
    public function serialize(Promotion $p, bool $withItems = false): array
    {
        $p->loadMissing('targets');

        return [
            'id' => (int) $p->id,
            'title' => $p->title,
            'kind' => $p->kind,
            'params' => $p->params,
            'audience' => $p->audience,
            'starts_at' => optional($p->starts_at)->toIso8601String(),
            'ends_at' => optional($p->ends_at)->toIso8601String(),
            'max_uses' => $p->max_uses,
            'used_count' => (int) $p->used_count,
            'remaining_uses' => $p->max_uses === null ? null : max(0, $p->max_uses - (int) $p->used_count),
            'is_active' => (bool) $p->is_active,
            'is_running' => $p->isRunning(),
            'targets' => $this->catalog->targetNames($p),
        ] + ($withItems ? ['items' => $this->catalog->coveredItems($p)] : []);
    }

    /**
     * Items a promotion covers that it will NOT discount yet, because their price rose within the quiet days, and the day each may be.
     *
     * @return list<array{name:string,type:string,id:int,eligible_from:string}>
     */
    public function recentlyRaised(Promotion $promotion): array
    {
        $out = [];

        foreach ($this->catalog->coveredItems($promotion, 500) as $item) {
            $class = match ($item['type']) {
                Promotion::TARGET_MENU_ITEM => MenuItem::class,
                Promotion::TARGET_BOOKABLE_ITEM => BookableItem::class,
                default => BusinessCatalogListing::class,
            };

            // a room's price lives on its price row, which is what the «no fake discount» rule watches
            if ($class === BookableItem::class) {
                $unit = BookableItem::query()->find((int) $item['id']);

                // a hotel's room sold through an allocation: its price history is the hotel's, not this company's
                if (! $unit || (int) $unit->business_id !== (int) $promotion->business_id) {
                    continue;
                }

                $row = app(\App\Services\BusinessServicePriceResolver::class)->resolveForBookableItem($unit);
                $from = $row ? $this->pricing->eligibleFrom(\App\Models\BusinessServicePrice::class, (int) $row->id) : null;

                if ($from) {
                    $out[] = ['name' => $item['name'], 'type' => $item['type'], 'id' => (int) $item['id'], 'eligible_from' => $from->toDateString()];
                }

                continue;
            }


            $from = $this->pricing->eligibleFrom($class, (int) $item['id']);

            if ($from) {
                $out[] = ['name' => $item['name'], 'type' => $item['type'], 'id' => (int) $item['id'], 'eligible_from' => $from->toDateString()];
            }
        }

        return $out;
    }

    /* ------------------------------------------------------------------ checks */

    /** @param array<string,mixed> $data */
    private function check(int $businessId, array $data): void
    {
        $p = $data['params'] ?? [];
        $kind = (string) $data['kind'];

        $bad = fn (string $field, string $message) => throw ValidationException::withMessages([$field => $message]);

        match ($kind) {
            Promotion::PERCENT => ($v = (float) ($p['value'] ?? 0)) >= 1 && $v <= 90 ?: $bad('params.value', __('نسبة الخصم بين ١٪ و٩٠٪.')),
            Promotion::AMOUNT => (float) ($p['value'] ?? 0) > 0 ?: $bad('params.value', __('اكتب مبلغ الخصم.')),
            Promotion::N_FOR_PRICE_OF => ($n = (int) ($p['n'] ?? 0)) >= 2 && $n <= 20 && ($pay = (int) ($p['pay'] ?? 1)) >= 1 && $pay < $n
                ?: $bad('params.n', __('اشترِ عددًا، واحصل على عدد أكبر منه (حتى ٢٠ قطعة).')),
            Promotion::SPEND_PERCENT => (float) ($p['min_total'] ?? 0) > 0 && ($v = (float) ($p['value'] ?? 0)) >= 1 && $v <= 90
                ?: $bad('params.min_total', __('اكتب حدًّا أدنى للطلب ونسبة خصم بين ١٪ و٩٠٪.')),
            default => $bad('kind', __('نوع العرض غير معروف.')),
        };

        // «لكل عرض شرط انتهاء»: a date, or a number of orders
        if (empty($data['ends_at']) && empty($data['max_uses'])) {
            $bad('ends_at', __('لكل عرض شرط انتهاء: تاريخ، أو عدد محدد من الطلبات.'));
        }

        if (! empty($data['starts_at']) && ! empty($data['ends_at']) && strtotime((string) $data['ends_at']) < strtotime((string) $data['starts_at'])) {
            $bad('ends_at', __('تاريخ الانتهاء قبل تاريخ البداية.'));
        }

        $this->checkTargets($businessId, $data['targets'] ?? []);

        // a minimum order is a cart's idea; a night has no cart
        $booking = collect($data['targets'])->contains(fn ($t) => in_array($t['type'], [Promotion::TARGET_BOOKABLE_ITEM, Promotion::TARGET_BOOKABLE_KIND], true));
        if ($booking && $kind === Promotion::SPEND_PERCENT) {
            $bad('kind', __('الحد الأدنى للطلب لا ينطبق على الحجز. اختر نسبة أو مبلغًا أو «اشترِ … واحصل على …».'));
        }

        // the season a booking offer covers
        foreach (['stay_from', 'stay_to'] as $field) {
            if (! empty($p[$field]) && strtotime((string) $p[$field]) === false) {
                $bad("params.$field", __('تاريخ الإقامة غير صحيح.'));
            }
        }
        if (! empty($p['stay_from']) && ! empty($p['stay_to']) && strtotime((string) $p['stay_to']) < strtotime((string) $p['stay_from'])) {
            $bad('params.stay_to', __('نهاية موسم الإقامة قبل بدايته.'));
        }

        // a fixed amount must leave every covered item with a price
        if ($kind === Promotion::AMOUNT) {
            $draft = new Promotion(['business_id' => $businessId, 'kind' => $kind, 'params' => $p]);
            $draft->setRelation('targets', collect($data['targets'])->map(fn ($t) => new \App\Models\PromotionTarget(['target_type' => $t['type'], 'target_id' => $t['id'] ?? null])));
            $cheapest = collect($this->catalog->coveredItems($draft, 500))->min('price');

            if ($cheapest !== null && (float) $p['value'] >= (float) $cheapest) {
                $bad('params.value', __('مبلغ الخصم يساوي سعر أرخص صنف في العرض أو يزيد عليه.'));
            }
        }
    }

    /** @param list<array{type:string,id?:int|null}> $targets */
    private function checkTargets(int $businessId, array $targets): void
    {
        if ($targets === []) {
            throw ValidationException::withMessages(['targets' => __('اختر ما يشمله العرض.')]);
        }

        $sold = app(\App\Services\Partnerships\AllocationService::class)->soldBy($businessId)->pluck('bookable_item_id')->all();

        foreach ($targets as $i => $t) {
            $type = (string) ($t['type'] ?? '');
            $id = (int) ($t['id'] ?? 0);

            $owned = match ($type) {
                Promotion::TARGET_ALL => true,
                Promotion::TARGET_MENU_SECTION => MenuSection::query()->where('business_id', $businessId)->whereKey($id)->exists(),
                Promotion::TARGET_MENU_ITEM => MenuItem::query()->where('business_id', $businessId)->whereKey($id)->exists(),
                Promotion::TARGET_LISTING => BusinessCatalogListing::query()->where('business_id', $businessId)->whereKey($id)->exists(),
                // a hotel's rooms a company was given to sell are among ITS rooms for offers (the offer comes off the company's margin)
                Promotion::TARGET_BOOKABLE_ITEM => BookableItem::query()->where(fn ($q) => $q->where('business_id', $businessId)->orWhereIn('id', $sold))->whereKey($id)->exists(),
                Promotion::TARGET_BOOKABLE_KIND => BookableItem::query()->where(fn ($q) => $q->where('business_id', $businessId)->orWhereIn('id', $sold))->where('line_option_id', $id)->exists(),
                Promotion::TARGET_LISTING_CATEGORY => DB::table('business_catalog_listings as l')->join('catalog_products as p', 'p.id', '=', 'l.catalog_product_id')
                    ->where('l.business_id', $businessId)->where('p.product_category_id', $id)->exists(),
                default => false,
            };

            if (! $owned) {
                throw ValidationException::withMessages(["targets.$i" => __('هذا الاختيار ليس من منتجات نشاطك.')]);
            }
        }
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function fields(array $data): array
    {
        return [
            'title' => isset($data['title']) && trim((string) $data['title']) !== '' ? mb_substr(trim((string) $data['title']), 0, 120) : null,
            'kind' => $data['kind'],
            'params' => $data['params'],
            'audience' => ($data['audience'] ?? Promotion::AUDIENCE_ALL) === Promotion::AUDIENCE_NEW ? Promotion::AUDIENCE_NEW : Promotion::AUDIENCE_ALL,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'max_uses' => ! empty($data['max_uses']) ? (int) $data['max_uses'] : null,
        ];
    }

    /** @param list<array{type:string,id?:int|null}> $targets */
    private function syncTargets(Promotion $promotion, array $targets): void
    {
        $promotion->targets()->delete();

        foreach ($targets as $t) {
            $promotion->targets()->create(['target_type' => $t['type'], 'target_id' => $t['type'] === Promotion::TARGET_ALL ? null : (int) $t['id']]);
        }
    }
}
