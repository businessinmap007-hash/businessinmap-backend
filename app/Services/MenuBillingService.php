<?php

namespace App\Services;

use App\Models\BusinessCatalogListing;
use App\Models\BusinessMenuSetting;
use App\Models\CategoryChildServiceFee;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\PlatformService;
use App\Models\User;

/**
 * Computes a menu-order bill: items subtotal + platform service fee (the client
 * fee configured per category-child for the menu service) + tax (a global VAT
 * percentage). Used to show each participant of a shared cart their own share
 * (service fee + tax on their own order only); payment is cash on arrival.
 *
 * A restaurant may declare (business_menu_settings) that its displayed prices
 * already INCLUDE the service fee and/or tax. When a component is included it is
 * NOT added on top — its amount is instead back-calculated (embedded in the
 * price) for the bill, and `total` stays equal to the picked items subtotal for
 * that component. The default (both excluded) adds both on top — the original
 * behaviour, unchanged.
 *
 * The service fee reuses the BIM primitive CategoryChildServiceFee::amountFor.
 */
class MenuBillingService
{
    /**
     * Resolve the client-fee row for a business's menu service, or null.
     *
     * Gated on the business's fee-auto-charge consent: a business that has not
     * opted in charges NO platform service fee (so the customer's bill is never
     * inflated by a fee BIM will not collect at settlement). See
     * OrderFeeSettlementService.
     */
    public function feeRowForBusiness(int $businessId): ?CategoryChildServiceFee
    {
        $business = User::query()->whereKey($businessId)->first(['id', 'category_child_id']);

        if (! $business || ! $business->hasFeeAutoChargeEnabled()) {
            return null;
        }

        $childId = (int) ($business->category_child_id ?? 0);
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

    /** [prices_include_service, prices_include_tax] for a business (defaults false). */
    public function inclusiveFlagsForBusiness(int $businessId): array
    {
        $row = BusinessMenuSetting::query()->where('business_id', $businessId)->first();

        return [
            (bool) ($row->prices_include_service ?? false),
            (bool) ($row->prices_include_tax ?? false),
        ];
    }

    /** The global (default) menu tax rate (percent). */
    public function taxRatePercent(): float
    {
        return (float) config('bim.menu_tax_rate_percent', 14);
    }

    /**
     * The tax rate (percent) for a business: the owner's own rate when set,
     * otherwise the global config rate. An owner rate of 0 is honoured (tax-free).
     */
    public function taxRatePercentForBusiness(int $businessId): float
    {
        $rate = BusinessMenuSetting::query()->where('business_id', $businessId)->value('tax_rate_percent');

        return $rate === null ? $this->taxRatePercent() : (float) $rate;
    }

    /**
     * Build a bill for a displayed items subtotal against a (possibly null) fee
     * row and the business's inclusive flags.
     *
     * Model: net (food value) → service = client fee on net → tax = rate on
     * (net + service). The displayed subtotal S already contains whichever
     * components are "included": S = net + inc_service·service + inc_tax·tax.
     * We solve net in closed form, then report the true service/tax amounts and
     * total = net + service + tax (= S + the components added on top).
     *
     * With both flags false this reduces to net = S and total = S + service +
     * tax — the original behaviour.
     */
    public function bill(float $itemsSubtotal, ?CategoryChildServiceFee $feeRow, bool $incService = false, bool $incTax = false, ?float $taxRatePercent = null): array
    {
        $s = round(max($itemsSubtotal, 0), 2);
        $tr = ($taxRatePercent ?? $this->taxRatePercent()) / 100;

        $chargeable = $feeRow && $feeRow->isChargeableFor(CategoryChildServiceFee::PAYER_CLIENT);
        $isPercent = $chargeable && ($feeRow->client_fee_type ?: 'fixed') === CategoryChildServiceFee::CALC_TYPE_PERCENT;
        $sp = $isPercent ? ((float) $feeRow->client_fee_amount) / 100 : 0.0;
        $fixed = ($chargeable && ! $isPercent) ? (float) $feeRow->client_fee_amount : 0.0;

        // Solve net (food value) from the displayed subtotal.
        if ($isPercent) {
            // S = net·(1 + incS·sp + incT·(1+sp)·tr)
            $denom = 1 + ($incService ? $sp : 0) + ($incTax ? (1 + $sp) * $tr : 0);
            $net = $denom > 0 ? $s / $denom : $s;
        } else {
            // service is fixed F: S = net·(1 + incT·tr) + incS·F + incT·F·tr
            $denom = 1 + ($incTax ? $tr : 0);
            $net = $denom > 0 ? ($s - ($incService ? $fixed : 0) - ($incTax ? $fixed * $tr : 0)) / $denom : $s;
        }

        $net = round(max($net, 0), 2);

        $serviceFee = $chargeable ? round((float) $feeRow->amountFor(CategoryChildServiceFee::PAYER_CLIENT, $net), 2) : 0.0;
        $tax = round(($net + $serviceFee) * $tr, 2);

        $total = round($s + ($incService ? 0 : $serviceFee) + ($incTax ? 0 : $tax), 2);

        return [
            'items_subtotal' => $s,
            'service_fee' => $serviceFee,
            'service_included' => $incService,
            'tax' => $tax,
            'tax_included' => $incTax,
            'total' => $total,
        ];
    }

    /**
     * Order-level bill: menu (food) lines are billed per "biller" (grouped by
     * added_by_user_id, so a shared cart bills each participant on their own
     * order — matters for a fixed fee); retail lines stay plain. Returns the
     * summed service_fee / tax, the menu payable (items + added fee/tax), the
     * retail subtotal, and the owner's inclusive flags.
     */
    public function orderBill(Order $order): array
    {
        $order->loadMissing('items');

        $businessId = (int) $order->business_id;
        $feeRow = $this->feeRowForBusiness($businessId);
        [$incService, $incTax] = $this->inclusiveFlagsForBusiness($businessId);
        $taxRate = $this->taxRatePercentForBusiness($businessId);

        $menuLines = $order->items->where('offering_type', MenuItem::class);
        $retailSubtotal = round((float) $order->items
            ->where('offering_type', BusinessCatalogListing::class)->sum('total_price'), 2);

        $serviceFee = 0.0;
        $tax = 0.0;
        $menuPayable = 0.0;

        // Group by biller (added_by_user_id); a personal cart has one null group.
        foreach ($menuLines->groupBy(fn ($l) => (int) ($l->added_by_user_id ?? 0)) as $lines) {
            $bill = $this->bill((float) $lines->sum('total_price'), $feeRow, $incService, $incTax, $taxRate);
            $serviceFee += $bill['service_fee'];
            $tax += $bill['tax'];
            $menuPayable += $bill['total'];
        }

        return [
            'menu_subtotal' => round((float) $menuLines->sum('total_price'), 2),
            'retail_subtotal' => $retailSubtotal,
            'service_fee' => round($serviceFee, 2),
            'service_included' => $incService,
            'tax' => round($tax, 2),
            'tax_included' => $incTax,
            'menu_payable' => round($menuPayable, 2),
        ];
    }
}
