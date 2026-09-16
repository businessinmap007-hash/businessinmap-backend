<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

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
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }

    /**
     * The specific "توصيل/استلام" answers THIS business ticked on its own
     * options screen, from the reactivated "التسليم والاستلام" group —
     * checkout shows exactly these, never a guess. A prior version derived
     * "shipping" vs "delivery" from whether the business carried `retail`;
     * retail and menu coexist on ~56 goods children ON PURPOSE (a produce
     * shop selling both at the counter and in bulk), so that guess
     * mislabelled an ordinary greengrocer as a factory. Reverted 2026-09-16.
     *
     * Each option maps to the underlying fulfilment type CustomerCartService
     * actually processes (delivery/pickup) so old order-placement logic
     * needs no change; only the label and the number of choices vary.
     *
     * @return array<int,array{id:int,name_ar:string,name_en:string,type:string}>
     */
    public static function fulfillmentMethodsFor(User $business): array
    {
        $groupId = (int) DB::table('option_groups')->where('name_ar', 'التسليم والاستلام')->value('id');

        if ($groupId <= 0) {
            return [];
        }

        $ticked = DB::table('option_user as ou')
            ->join('options as o', 'o.id', '=', 'ou.option_id')
            ->where('ou.user_id', $business->id)
            ->where('o.group_id', $groupId)
            ->select('o.id', 'o.name_ar', 'o.name_en')
            ->get();

        if ($ticked->isEmpty()) {
            // Nothing configured yet: the two generic answers, so checkout is
            // never left with no fulfilment options at all.
            $ticked = DB::table('options')->where('group_id', $groupId)
                ->whereIn('name_ar', ['توصيل طلبات', 'استلام من المكان'])
                ->get(['id', 'name_ar', 'name_en']);
        }

        return $ticked->map(fn ($o) => [
            'id' => (int) $o->id,
            'name_ar' => $o->name_ar,
            'name_en' => $o->name_en,
            'type' => self::typeOf($o->name_ar),
        ])->values()->all();
    }

    /** delivery = the customer receives it somewhere; pickup = they go get it. */
    private static function typeOf(string $nameAr): string
    {
        $pickupNames = [
            'تسليم أرض المصنع',
            'تيك أواى',
            'استلام من المكان',
        ];

        return in_array($nameAr, $pickupNames, true) ? 'pickup' : 'delivery';
    }
}
