<?php

namespace App\Models;

use App\Support\BusinessPanelNav;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A restaurant's menu billing settings — whether menu prices already include
 * the service fee / tax. See MenuBillingService + the 2026_07_16 migration.
 */
class BusinessMenuSetting extends Model
{
    public const DISPLAY_LIST = 'list';
    public const DISPLAY_GRID = 'grid';
    public const DISPLAY_MODES = [self::DISPLAY_LIST, self::DISPLAY_GRID];

    protected $fillable = [
        'business_id',
        'prices_include_service',
        'prices_include_tax',
        'tax_rate_percent',
        'min_order_amount',
        'default_margin_percent',
        'deposit_required_above',
        'low_stock_threshold',
        'supports_delivery',
        'supports_pickup',
        'supports_international_shipping',
        'supports_domestic_shipping',
        'display_mode',
    ];

    protected $casts = [
        'business_id' => 'integer',
        'prices_include_service' => 'boolean',
        'prices_include_tax' => 'boolean',
        'tax_rate_percent' => 'float',
        'min_order_amount' => 'float',
        'default_margin_percent' => 'float',
        'deposit_required_above' => 'float',
        'low_stock_threshold' => 'integer',
        'supports_delivery' => 'boolean',
        'supports_pickup' => 'boolean',
        'supports_international_shipping' => 'boolean',
        'supports_domestic_shipping' => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    /**
     * Whether THIS business's fulfilment pair means "توصيل/استلام" (delivery
     * to the customer) or "شحن/استلام أرض المصنع" (freight the customer
     * arranges) — decided by what the business actually sells (`retail`),
     * never by which root/category it happens to be filed under. Replaces
     * the old "التسليم والاستلام" option group's descriptive tags — those
     * asked a child which of five words applied to it by hand; this reads
     * one thing the platform already knows about the business.
     *
     * @return array{delivery_key: string, delivery_ar: string, delivery_en: string, pickup_ar: string, pickup_en: string, is_freight: bool}
     */
    public static function labelsFor(User $business): array
    {
        $isFreight = in_array('retail', BusinessPanelNav::servicesOf($business), true);

        if ($isFreight) {
            return [
                'is_freight' => true,
                'delivery_ar' => 'شحن', 'delivery_en' => 'Shipping',
                'pickup_ar' => 'استلام أرض المصنع', 'pickup_en' => 'Factory-gate pickup',
            ];
        }

        return [
            'is_freight' => false,
            'delivery_ar' => 'توصيل', 'delivery_en' => 'Delivery',
            'pickup_ar' => 'استلام', 'pickup_en' => 'Pickup',
        ];
    }
}
