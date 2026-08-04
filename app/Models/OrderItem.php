<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    // Columns match the order_items table. `offering_type`/`offering_id` are the
    // polymorphic offering reference (menu item now; catalog listing / bookable
    // type later); `menu_id` is kept for backward compatibility.
    protected $fillable = [
        'order_id',
        'added_by_user_id',
        'menu_id',
        'offering_type',
        'offering_id',
        'offering_label',
        'size_id',
        'addons',
        'qty',
        'price',
        'total_price',
    ];

    protected $casts = [
        'addons' => 'array',
        'qty' => 'integer',
        'price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    /**
     * Freeze what this line was at the moment it was ordered.
     *
     * The hook rather than the three call sites (MenuOrderService,
     * BookingFoodService x2) because an order line that missed the snapshot is
     * indistinguishable from one that had nothing to snapshot — it just quietly
     * reads its name live again, and starts drifting when the merchant re-tags
     * the item.
     */
    protected static function booted(): void
    {
        static::creating(function (self $line) {
            if ($line->offering_label !== null || ! $line->offering_id) {
                return;
            }

            $offering = $line->offering()->getResults();

            if (! $offering || ! method_exists($offering, 'offeringLabel')) {
                return;
            }

            // The item's own name first, then what the platform calls it:
            // «برجر لحم» / «غرفة نوم — مودرن». Stored in the language the order
            // was placed in, which is what the customer actually saw.
            $own = method_exists($offering, 'loc')
                ? (string) $offering->loc('name')
                : (string) ($offering->name_ar ?? '');

            $line->offering_label = $offering->offeringLabel($own ?: null) ?: null;
        });
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * «غرفة نوم — مودرن»: what was ordered, in the platform's own words, as it
     * read on the day. Falls back to the item's current name.
     */
    public function displayName(): string
    {
        if ($this->offering_label) {
            return (string) $this->offering_label;
        }

        $item = $this->menuItem;

        if ($item) {
            return (string) ($item->loc('name') ?: ('#' . $this->menu_id));
        }

        // a retail listing names itself from the shared catalog, not from here
        if ((string) $this->offering_type === BusinessCatalogListing::class) {
            return __('منتج #') . $this->offering_id;
        }

        return '#' . ($this->menu_id ?: $this->offering_id);
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class, 'menu_id');
    }

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    /**
     * The offering this line refers to (MenuItem now; catalog listing / bookable
     * item type later). Polymorphic — see the Phase 3 offering layer.
     */
    public function offering()
    {
        return $this->morphTo(__FUNCTION__, 'offering_type', 'offering_id');
    }
}
