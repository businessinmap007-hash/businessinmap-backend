<?php

namespace App\Services\Commercial;

use App\Models\CommercialOffer;
use App\Models\OfferBoostPackage;
use App\Models\OfferBoostPurchase;
use App\Models\Wallet;
use App\Services\Wallet\WalletLedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class OfferBoostService
{
    public function activate(int $offerId, int $packageId, ?int $adminId = null): OfferBoostPurchase
    {
        return DB::transaction(function () use ($offerId, $packageId, $adminId) {
            $offer = CommercialOffer::query()->lockForUpdate()->findOrFail($offerId);
            $package = OfferBoostPackage::query()
                ->where('is_active', 1)
                ->findOrFail($packageId);

            if ((string) $offer->source_type === CommercialOffer::SOURCE_ALLOCATION) {
                throw ValidationException::withMessages([
                    'offer_id' => __('لا يمكن تفعيل Boost لعروض allocation لأنها تدار من نظام الحصص.'),
                ]);
            }

            if ((string) $offer->status !== CommercialOffer::STATUS_ACTIVE) {
                throw ValidationException::withMessages([
                    'offer_id' => __('يجب أن يكون العرض Active قبل تفعيل Boost.'),
                ]);
            }

            $businessId = (int) $offer->seller_business_id;
            $wallet = Wallet::query()->firstOrCreate(
                ['user_id' => $businessId],
                ['balance' => 0, 'locked_balance' => 0, 'status' => 'active']
            );

            $ledger = app(WalletLedgerService::class);
            $tx = null;
            $price = round((float) $package->price, 2);

            if ($price > 0) {
                $tx = $ledger->withdraw(
                    walletId: (int) $wallet->id,
                    userId: $businessId,
                    amount: $price,
                    op: [
                        'type' => 'offer_boost_fee',
                        'reference_type' => 'offer_boost_package',
                        'reference_id' => (string) $package->id,
                        'idempotency_key' => 'offer_boost:' . $offer->id . ':' . $package->id . ':' . now()->format('YmdHis') . ':' . uniqid(),
                        'meta' => [
                            'source' => 'admin_offer_boost',
                            'offer_id' => (int) $offer->id,
                            'package_id' => (int) $package->id,
                            'business_id' => $businessId,
                            'admin_id' => $adminId,
                        ],
                    ]
                );
            }

            $startsAt = now();
            $endsAt = now()->addDays(max((int) $package->duration_days, 1));

            $purchase = OfferBoostPurchase::query()->create([
                'offer_id' => (int) $offer->id,
                'business_id' => $businessId,
                'package_id' => (int) $package->id,
                'wallet_transaction_id' => $tx ? (int) $tx->id : null,
                'price' => $price,
                'currency' => (string) ($package->currency ?: 'EGP'),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'boost_score' => (float) $package->boost_score,
                'is_featured' => (bool) $package->is_featured,
                'status' => OfferBoostPurchase::STATUS_ACTIVE,
                'meta' => [
                    'package_key' => $package->key,
                    'package_name' => $package->displayName(),
                    'source' => 'offer_boost_service',
                ],
            ]);

            $this->applyBoostColumns($offer, $package, $endsAt);

            return $purchase;
        });
    }

    private function applyBoostColumns(CommercialOffer $offer, OfferBoostPackage $package, $endsAt): void
    {
        $updates = [];

        if (Schema::hasColumn('commercial_offers', 'is_featured')) {
            $updates['is_featured'] = (bool) $package->is_featured;
        }

        if (Schema::hasColumn('commercial_offers', 'featured_until')) {
            $updates['featured_until'] = $endsAt;
        }

        if (Schema::hasColumn('commercial_offers', 'boost_score')) {
            $updates['boost_score'] = (float) $package->boost_score;
        }

        if ($updates) {
            $offer->forceFill($updates)->save();
        }
    }
}
