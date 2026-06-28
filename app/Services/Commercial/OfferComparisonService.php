<?php

namespace App\Services\Commercial;

use App\Models\CommercialOffer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class OfferComparisonService
{
    public const SORT_LOWEST_PRICE = 'lowest_price';
    public const SORT_HIGHEST_PRICE = 'highest_price';
    public const SORT_BEST_VALUE = 'best_value';
    public const SORT_RANKING = 'ranking';

    public function compare(
        string $offerableType,
        int $offerableId,
        int $quantity = 1,
        string $sort = self::SORT_LOWEST_PRICE,
        array $filters = []
    ): Collection {
        $quantity = max($quantity, 1);

        $query = CommercialOffer::query()
            ->with(['sellerBusiness:id,name,type,logo,category_id,category_child_id', 'ownerBusiness:id,name,type,logo,category_id,category_child_id'])
            ->active()
            ->forOfferable($offerableType, $offerableId);

        $this->applyFilters($query, $filters);

        $offers = $query->get()
            ->filter(fn (CommercialOffer $offer) => $offer->isAvailable($quantity))
            ->map(fn (CommercialOffer $offer) => $this->payload($offer, $quantity))
            ->values();

        return $this->sort($offers, $sort)->values();
    }

    public function lowestPrice(
        string $offerableType,
        int $offerableId,
        int $quantity = 1,
        array $filters = []
    ): ?array {
        return $this->compare(
            offerableType: $offerableType,
            offerableId: $offerableId,
            quantity: $quantity,
            sort: self::SORT_LOWEST_PRICE,
            filters: $filters
        )->first();
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['seller_business_id'])) {
            $query->where('seller_business_id', (int) $filters['seller_business_id']);
        }

        if (! empty($filters['owner_business_id'])) {
            $query->where('owner_business_id', (int) $filters['owner_business_id']);
        }

        if (! empty($filters['source_type'])) {
            $query->where('source_type', (string) $filters['source_type']);
        }

        if (! empty($filters['audience_type'])) {
            $query->where('audience_type', (string) $filters['audience_type']);
        }

        if (array_key_exists('is_refundable', $filters) && $filters['is_refundable'] !== null && $filters['is_refundable'] !== '') {
            $query->where('is_refundable', (bool) $filters['is_refundable']);
        }

        if (! empty($filters['payment_model'])) {
            $query->where('payment_model', (string) $filters['payment_model']);
        }
    }

    private function sort(Collection $offers, string $sort): Collection
    {
        return match ($sort) {
            self::SORT_HIGHEST_PRICE => $offers->sortByDesc('final_price'),
            self::SORT_BEST_VALUE => $offers->sortBy([
                ['is_boosted', 'desc'],
                ['boost_score', 'desc'],
                ['final_price', 'asc'],
                ['is_refundable', 'desc'],
                ['ranking_score', 'desc'],
            ]),
            self::SORT_RANKING => $offers->sortBy([
                ['is_boosted', 'desc'],
                ['boost_score', 'desc'],
                ['ranking_score', 'desc'],
                ['final_price', 'asc'],
            ]),
            default => $offers->sortBy([
                ['is_boosted', 'desc'],
                ['boost_score', 'desc'],
                ['final_price', 'asc'],
            ]),
        };
    }

    private function payload(CommercialOffer $offer, int $quantity): array
    {
        $unitPrice = round((float) $offer->final_price, 2);
        $totalPrice = round($unitPrice * $quantity, 2);
        $boostScore = round($offer->effectiveBoostScore(), 4);

        return [
            'id' => (int) $offer->id,
            'offerable_type' => (string) $offer->offerable_type,
            'offerable_id' => (int) $offer->offerable_id,
            'source_type' => (string) $offer->source_type,
            'audience_type' => (string) ($offer->audience_type ?: CommercialOffer::AUDIENCE_BOTH),
            'source_id' => $offer->source_id ? (int) $offer->source_id : null,
            'owner_business_id' => (int) $offer->owner_business_id,
            'seller_business_id' => (int) $offer->seller_business_id,
            'owner_business' => $offer->ownerBusiness ? [
                'id' => (int) $offer->ownerBusiness->id,
                'name' => (string) $offer->ownerBusiness->name,
                'logo' => $offer->ownerBusiness->logo,
            ] : null,
            'seller_business' => $offer->sellerBusiness ? [
                'id' => (int) $offer->sellerBusiness->id,
                'name' => (string) $offer->sellerBusiness->name,
                'logo' => $offer->sellerBusiness->logo,
            ] : null,
            'title_ar' => $offer->title_ar,
            'title_en' => $offer->title_en,
            'display_title' => $offer->displayTitle(),
            'base_price' => round((float) $offer->base_price, 2),
            'final_price' => $unitPrice,
            'total_price' => $totalPrice,
            'currency' => (string) ($offer->currency ?: 'EGP'),
            'discount_type' => $offer->discount_type,
            'discount_value' => $offer->discount_value === null ? null : round((float) $offer->discount_value, 2),
            'availability_mode' => (string) $offer->availability_mode,
            'available_quantity' => $offer->available_quantity === null ? null : (int) $offer->available_quantity,
            'quantity_requested' => $quantity,
            'is_refundable' => (bool) $offer->is_refundable,
            'payment_model' => $offer->payment_model,
            'ranking_score' => round((float) ($offer->ranking_score ?? 0), 4),
            'is_featured' => (bool) ($offer->is_featured ?? false),
            'featured_until' => $offer->featured_until ? $offer->featured_until->toDateTimeString() : null,
            'is_boosted' => $offer->isBoosted(),
            'boost_score' => $boostScore,
            'meta' => is_array($offer->meta) ? $offer->meta : [],
        ];
    }
}
