<?php

namespace App\Services\Commercial;

use App\Models\CommercialOffer;
use App\Models\OfferBoostPurchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class OfferBoostMaintenanceService
{
    public function expireDueBoosts(): int
    {
        $updated = 0;

        DB::transaction(function () use (&$updated) {
            $updated = OfferBoostPurchase::query()
                ->where('status', OfferBoostPurchase::STATUS_ACTIVE)
                ->where('ends_at', '<', now())
                ->update([
                    'status' => OfferBoostPurchase::STATUS_EXPIRED,
                    'updated_at' => now(),
                ]);

            if (Schema::hasColumn('commercial_offers', 'is_featured')) {
                CommercialOffer::query()
                    ->where('is_featured', 1)
                    ->whereNotNull('featured_until')
                    ->where('featured_until', '<', now())
                    ->update(array_filter([
                        'is_featured' => 0,
                        'boost_score' => Schema::hasColumn('commercial_offers', 'boost_score') ? 0 : null,
                        'updated_at' => now(),
                    ], fn ($value) => $value !== null));
            }
        });

        return (int) $updated;
    }
}
