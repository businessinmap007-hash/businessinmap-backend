<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\CommercialOffer;
use App\Models\OfferBoostPackage;
use App\Models\OfferBoostPurchase;
use App\Services\Commercial\OfferBoostService;
use Illuminate\Http\Request;

final class OfferBoostController extends Controller
{
    public function packages()
    {
        $packages = OfferBoostPackage::query()
            ->where('is_active', 1)
            ->orderBy('price')
            ->orderBy('duration_days')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'packages' => $packages,
            ],
        ]);
    }

    public function activate(Request $request, int $offer, OfferBoostService $boostService)
    {
        $user = $request->user();

        if (! $user || (string) $user->type !== 'business') {
            return response()->json(['success' => false, 'message' => 'Business account required.'], 403);
        }

        $data = $request->validate([
            'package_id' => ['required', 'integer', 'exists:offer_boost_packages,id'],
        ]);

        $owned = CommercialOffer::query()
            ->where('id', $offer)
            ->where('seller_business_id', (int) $user->id)
            ->exists();

        if (! $owned) {
            return response()->json(['success' => false, 'message' => 'Offer not found for this business.'], 404);
        }

        $purchase = $boostService->activate(
            offerId: $offer,
            packageId: (int) $data['package_id'],
            adminId: null
        );

        return response()->json([
            'success' => true,
            'message' => 'Offer boost activated successfully.',
            'data' => [
                'purchase' => $purchase->fresh(['package', 'offer']),
            ],
        ], 201);
    }

    public function myPurchases(Request $request)
    {
        $user = $request->user();

        if (! $user || (string) $user->type !== 'business') {
            return response()->json(['success' => false, 'message' => 'Business account required.'], 403);
        }

        $rows = OfferBoostPurchase::query()
            ->with(['package', 'offer:id,title_ar,title_en,status,final_price,currency,audience_type'])
            ->where('business_id', (int) $user->id)
            ->latest('id')
            ->paginate((int) $request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => [
                'purchases' => $rows,
            ],
        ]);
    }
}
