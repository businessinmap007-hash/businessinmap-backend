<?php

namespace App\Services\Menu;

use App\Models\BusinessMenuSetting;
use App\Models\MenuItem;
use App\Services\Notifications\NotificationDispatcherService;
use App\Support\SaleUnits;

/**
 * «قبل النفاذ الكمية مثلا ب 5 او 10 وحدات سواء عبوة او بالكيلو» — المالك،
 * 2026-09-04. Alerts the BUSINESS itself (not the customer) so it can reorder
 * from its own supplier before a shelf item runs out.
 *
 * `available_quantity` is merchant-set, never auto-decremented by an order —
 * see MenuItem::heading()'s doc and [[butcher-child-and-display-order]]. So
 * the only moment worth checking is a SAVE that changes it, which is exactly
 * what `MenuItem::booted()`'s `saved` hook calls this on.
 */
class LowStockAlertService
{
    public const EVENT_OUT_OF_STOCK = 'menu_item_out_of_stock';
    public const EVENT_LOW_STOCK = 'menu_item_low_stock';

    public function __construct(private readonly NotificationDispatcherService $dispatcher)
    {
    }

    /**
     * @param  int|null  $previousQuantity  what the row had before this save;
     *         null means "wasn't tracked" (a brand new row, or a row that had
     *         no quantity before) — treated as "above threshold" so a newly
     *         created low/out row still alerts.
     */
    public function checkAndNotify(MenuItem $item, ?int $previousQuantity): void
    {
        $qty = $item->available_quantity;

        if ($qty === null) {
            return;
        }

        if ($qty === 0) {
            if ($previousQuantity !== 0) {
                $this->notify(self::EVENT_OUT_OF_STOCK, $item);
            }

            return;
        }

        $threshold = BusinessMenuSetting::query()->where('business_id', $item->business_id)->value('low_stock_threshold');

        if ($threshold === null) {
            return;
        }

        $wasAboveThreshold = $previousQuantity === null || $previousQuantity > $threshold;

        if ($qty <= $threshold && $wasAboveThreshold) {
            $this->notify(self::EVENT_LOW_STOCK, $item, (int) $threshold);
        }
    }

    private function notify(string $eventKey, MenuItem $item, ?int $threshold = null): void
    {
        $name = (string) ($item->name_ar ?: $item->name_en ?: ('#' . $item->id));
        $unit = SaleUnits::label($item->sale_unit);
        $qty = (string) $item->available_quantity;

        if ($eventKey === self::EVENT_OUT_OF_STOCK) {
            $bodyAr = "نفدت كمية «{$name}» من المخزون. حدّث الكمية بعد التوريد من المورد.";
            $bodyEn = "\"{$name}\" is out of stock. Update the quantity once you restock from your supplier.";
        } else {
            $qtyLabelAr = $unit ? "{$qty} {$unit}" : $qty;
            $bodyAr = "تبقّى {$qtyLabelAr} فقط من «{$name}» — اطلب كمية إضافية من المورد قبل أن تنفد.";
            $bodyEn = "Only {$qty} left of \"{$name}\" — order more from your supplier before it runs out.";
        }

        $this->dispatcher->dispatch($eventKey, (int) $item->business_id, [
            'body_ar' => $bodyAr,
            'body_en' => $bodyEn,
            'notifiable_type' => MenuItem::class,
            'notifiable_id' => $item->id,
            'action_type' => 'menu_item',
        ]);
    }
}
