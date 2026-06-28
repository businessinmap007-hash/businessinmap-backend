<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\CommercialOffer;
use App\Services\Commercial\OfferComparisonService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class OfferComparisonController extends Controller
{
    public function compare(Request $request, OfferComparisonService $service)
    {
        $data = $request->validate([
            'offerable_type' => ['required', Rule::in([
                CommercialOffer::OFFERABLE_BOOKABLE_ITEM,
                CommercialOffer::OFFERABLE_PRODUCT,
                CommercialOffer::OFFERABLE_SERVICE,
                CommercialOffer::OFFERABLE_PACKAGE,
            ])],
            'offerable_id' => ['required', 'integer', 'min:0'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', Rule::in([
                OfferComparisonService::SORT_LOWEST_PRICE,
                OfferComparisonService::SORT_HIGHEST_PRICE,
                OfferComparisonService::SORT_BEST_VALUE,
                OfferComparisonService::SORT_RANKING,
            ])],
            'seller_business_id' => ['nullable', 'integer', 'min:1'],
            'owner_business_id' => ['nullable', 'integer', 'min:1'],
            'source_type' => ['nullable', 'string', 'max:50'],
            'is_refundable' => ['nullable', 'boolean'],
            'payment_model' => ['nullable', 'string', 'max:50'],
        ]);

        $filters = array_filter([
            'seller_business_id' => $data['seller_business_id'] ?? null,
            'owner_business_id' => $data['owner_business_id'] ?? null,
            'source_type' => $data['source_type'] ?? null,
            'is_refundable' => $request->has('is_refundable') ? $request->boolean('is_refundable') : null,
            'payment_model' => $data['payment_model'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        $offers = $service->compare(
            offerableType: (string) $data['offerable_type'],
            offerableId: (int) $data['offerable_id'],
            quantity: (int) ($data['quantity'] ?? 1),
            sort: (string) ($data['sort'] ?? OfferComparisonService::SORT_LOWEST_PRICE),
            filters: $filters
        );

        return response()->json([
            'success' => true,
            'data' => [
                'offerable_type' => (string) $data['offerable_type'],
                'offerable_id' => (int) $data['offerable_id'],
                'quantity' => (int) ($data['quantity'] ?? 1),
                'sort' => (string) ($data['sort'] ?? OfferComparisonService::SORT_LOWEST_PRICE),
                'offers' => $offers,
                'lowest_price' => $offers->first(),
            ],
        ]);
    }
}
