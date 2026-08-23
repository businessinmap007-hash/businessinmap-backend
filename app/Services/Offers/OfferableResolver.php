<?php

namespace App\Services\Offers;

use App\Models\BusinessCatalogListing;
use App\Models\BusinessServicePrice;
use App\Models\CommercialOffer;
use App\Models\MenuItem;
use Illuminate\Validation\ValidationException;

/**
 * Finds the priced row an offer names, and refuses to guess.
 *
 * «يجب اختيار المنتج أو الخدمة من المنيو الخاص به مع سعره السابق» — المالك،
 * 2026-08-23.
 *
 * That sentence has two halves and the platform honoured neither. `offerable_id`
 * was validated as `nullable, integer, min:0` — zero was legal, any id was
 * legal, an id belonging to another business was legal — and `base_price` was
 * whatever number came in the request. So «٣٠٪ خصم على الجينز» was two free-text
 * fields with a percentage between them.
 *
 * ── The three surfaces a price can live on ──────────────────────────────────
 *
 *   menu_item  → `menu_items`               a dish, a garment, a listing
 *   product    → `business_catalog_listings` this shop's price for a catalog product
 *   service    → `business_service_prices`   a priced service row
 *
 * `bookable_item` is deliberately NOT resolved here. A bookable unit is priced
 * through `business_service_prices` per kind and per period — «٣٠٪ على غرفة
 * مزدوجة» is a rule about nightly pricing, not a single number — and the
 * booking side already has `bookable_price_rules` for exactly that. An offer on
 * a room belongs there; pretending it is one number would produce a discount
 * that disagrees with the checkout.
 *
 * Allocation offers are also untouched: they are generated from a contract
 * price, not authored, and BusinessOfferController already refuses to edit
 * them.
 */
class OfferableResolver
{
    /** What this resolver can price, and what each one reads. */
    public const PRICEABLE = [
        CommercialOffer::OFFERABLE_MENU_ITEM => MenuItem::class,
        CommercialOffer::OFFERABLE_PRODUCT => BusinessCatalogListing::class,
        CommercialOffer::OFFERABLE_SERVICE => BusinessServicePrice::class,
    ];

    /** True when an offer of this type has a priced row behind it. */
    public function handles(string $offerableType): bool
    {
        return isset(self::PRICEABLE[$offerableType]);
    }

    /**
     * The row, or a 422 saying which half is missing.
     *
     * @param  int  $ownerBusinessId  who must own it — the OWNER of the offer,
     *                                not the seller. A reseller's authorisation
     *                                is checked separately; what is checked here
     *                                is that the price being discounted is the
     *                                price of a row that exists and belongs to
     *                                the business the offer says it belongs to.
     */
    public function resolve(string $offerableType, int $offerableId, int $ownerBusinessId): OfferableRow
    {
        if (! $this->handles($offerableType)) {
            throw ValidationException::withMessages([
                'offerable_type' => __('هذا النوع لا يُسعَّر من هنا.'),
            ]);
        }

        if ($offerableId <= 0) {
            throw ValidationException::withMessages([
                'offerable_id' => __('اختر الصنف من المنيو — العرض يكون على صنفٍ بعينه.'),
            ]);
        }

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $class */
        $class = self::PRICEABLE[$offerableType];

        $row = $class::query()->find($offerableId);

        if (! $row) {
            throw ValidationException::withMessages([
                'offerable_id' => __('هذا الصنف غير موجود.'),
            ]);
        }

        if ((int) $row->business_id !== $ownerBusinessId) {
            /*
             * Not «not found»: the row exists and belongs to somebody else.
             * Saying so plainly is safe — the id came from the caller, so this
             * leaks nothing he did not already have — and «غير موجود» on a row
             * a merchant can see in his own panel is the kind of message that
             * costs an afternoon.
             */
            throw ValidationException::withMessages([
                'offerable_id' => __('هذا الصنف ليس لهذا النشاط.'),
            ]);
        }

        return new OfferableRow(
            model: $row,
            businessId: (int) $row->business_id,
            price: (float) $row->currentTrackedPrice(),
            currency: (string) ($row->currency ?? 'EGP'),
            label: $this->labelOf($row),
        );
    }

    private function labelOf(object $row): string
    {
        foreach (['name_ar', 'name_en', 'sku'] as $field) {
            $value = trim((string) ($row->{$field} ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '#' . $row->getKey();
    }
}
