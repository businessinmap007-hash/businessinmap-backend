<?php

namespace App\Services;

use App\Models\CategoryChildServiceFee;
use App\Models\PlatformService;
use App\Models\User;

/**
 * Computes a menu-order bill: items subtotal + platform service fee (the client
 * fee configured per category-child for the menu service) + tax (a global VAT
 * percentage). Used to show each participant of a shared cart their own share
 * (service fee + tax on their own order only); payment is cash on arrival.
 *
 * The service fee reuses the BIM primitive CategoryChildServiceFee::amountFor
 * (fixed = flat per person's order; percent = % of their items).
 */
class MenuBillingService
{
    /** Resolve the client-fee row for a business's menu service, or null. */
    public function feeRowForBusiness(int $businessId): ?CategoryChildServiceFee
    {
        $childId = (int) (User::query()->whereKey($businessId)->value('category_child_id') ?? 0);
        $serviceId = (int) (PlatformService::query()->where('key', PlatformService::KEY_MENU)->value('id') ?? 0);

        if ($childId <= 0 || $serviceId <= 0) {
            return null;
        }

        return CategoryChildServiceFee::query()
            ->active()
            ->where('child_id', $childId)
            ->where('platform_service_id', $serviceId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    /** The configured menu tax rate (percent). */
    public function taxRatePercent(): float
    {
        return (float) config('bim.menu_tax_rate_percent', 14);
    }

    /**
     * Build a bill for an items subtotal against a (possibly null) fee row.
     * Service fee is the client fee on the items; tax is applied to
     * (items + service fee). Returns items_subtotal / service_fee / tax / total.
     */
    public function bill(float $itemsSubtotal, ?CategoryChildServiceFee $feeRow): array
    {
        $items = round(max($itemsSubtotal, 0), 2);

        $serviceFee = $feeRow
            ? round((float) $feeRow->amountFor(CategoryChildServiceFee::PAYER_CLIENT, $items), 2)
            : 0.0;

        $tax = round(($items + $serviceFee) * $this->taxRatePercent() / 100, 2);

        return [
            'items_subtotal' => $items,
            'service_fee' => $serviceFee,
            'tax' => $tax,
            'total' => round($items + $serviceFee + $tax, 2),
        ];
    }
}
