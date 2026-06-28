<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\CommercialOffer;
use App\Services\Commercial\OfferComparisonService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class OfferDiscoveryController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'offerable_type' => ['nullable', Rule::in($this->offerableTypes())],
            'offerable_id' => ['nullable', 'integer', 'min:0'],
            'seller_business_id' => ['nullable', 'integer', 'min:1'],
            'owner_business_id' => ['nullable', 'integer', 'min:1'],
            'source_type' => ['nullable', Rule::in($this->publicSourceTypes())],
            'audience_type' => ['nullable', Rule::in(CommercialOffer::audienceTypes())],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'sort' => ['nullable', Rule::in(['latest', 'lowest_price', 'highest_price', 'ranking'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = CommercialOffer::query()
            ->with(['sellerBusiness:id,name,type,logo,category_id,category_child_id', 'ownerBusiness:id,name,type,logo,category_id,category_child_id'])
            ->active()
            ->whereIn('source_type', $this->publicSourceTypes());

        $this->applyAudience($query, $request, $data);
        $this->applyFilters($query, $data);
        $this->applySort($query, (string) ($data['sort'] ?? 'latest'));

        $offers = $query->paginate((int) ($data['per_page'] ?? 20))->withQueryString();

        return response()->json([
            'success' => true,
            'data' => [
                'offers' => $offers,
                'filters' => [
                    'offerable_types' => $this->offerableTypes(),
                    'source_types' => $this->publicSourceTypes(),
                    'audience_types' => CommercialOffer::audienceTypes(),
                    'sorts' => ['latest', 'lowest_price', 'highest_price', 'ranking'],
                ],
            ],
        ]);
    }

    public function show(Request $request, int $offer)
    {
        $query = CommercialOffer::query()
            ->with(['sellerBusiness:id,name,type,logo,category_id,category_child_id', 'ownerBusiness:id,name,type,logo,category_id,category_child_id'])
            ->active()
            ->whereIn('source_type', $this->publicSourceTypes());

        $this->applyAudience($query, $request, []);

        $row = $query->findOrFail($offer);

        return response()->json([
            'success' => true,
            'data' => [
                'offer' => $row,
            ],
        ]);
    }

    public function byBusiness(Request $request, int $business)
    {
        $request->merge(['seller_business_id' => $business]);

        return $this->index($request);
    }

    public function lowestForOfferable(Request $request, OfferComparisonService $comparisonService)
    {
        $data = $request->validate([
            'offerable_type' => ['required', Rule::in($this->offerableTypes())],
            'offerable_id' => ['required', 'integer', 'min:0'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'source_type' => ['nullable', Rule::in($this->publicSourceTypes())],
            'audience_type' => ['nullable', Rule::in(CommercialOffer::audienceTypes())],
            'seller_business_id' => ['nullable', 'integer', 'min:1'],
            'owner_business_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $filters = array_filter([
            'source_type' => $data['source_type'] ?? null,
            'seller_business_id' => $data['seller_business_id'] ?? null,
            'owner_business_id' => $data['owner_business_id'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        $offers = $comparisonService->compare(
            offerableType: (string) $data['offerable_type'],
            offerableId: (int) $data['offerable_id'],
            quantity: (int) ($data['quantity'] ?? 1),
            sort: OfferComparisonService::SORT_LOWEST_PRICE,
            filters: $filters
        );

        $visibleAudiences = $this->visibleAudiences($request, $data['audience_type'] ?? null);

        $offers = $offers->filter(function (array $offer) use ($visibleAudiences) {
            return in_array((string) ($offer['source_type'] ?? ''), $this->publicSourceTypes(), true)
                && in_array((string) ($offer['audience_type'] ?? CommercialOffer::AUDIENCE_BOTH), $visibleAudiences, true);
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'offerable_type' => (string) $data['offerable_type'],
                'offerable_id' => (int) $data['offerable_id'],
                'quantity' => (int) ($data['quantity'] ?? 1),
                'lowest_price' => $offers->first(),
                'offers' => $offers,
            ],
        ]);
    }

    private function applyAudience(Builder $query, Request $request, array $data): void
    {
        $visible = $this->visibleAudiences($request, $data['audience_type'] ?? null);
        $query->whereIn('audience_type', $visible);
    }

    private function visibleAudiences(Request $request, ?string $requestedAudience = null): array
    {
        if ($requestedAudience && $requestedAudience !== CommercialOffer::AUDIENCE_PRIVATE) {
            return [$requestedAudience];
        }

        $user = method_exists($request, 'user') ? $request->user() : null;
        $type = $user ? (string) $user->type : 'client';

        if ($type === 'business') {
            return [CommercialOffer::AUDIENCE_B2B, CommercialOffer::AUDIENCE_BOTH];
        }

        return [CommercialOffer::AUDIENCE_B2C, CommercialOffer::AUDIENCE_BOTH];
    }

    private function applyFilters(Builder $query, array $data): void
    {
        $q = trim((string) ($data['q'] ?? ''));

        if ($q !== '') {
            $query->where(function (Builder $w) use ($q) {
                $w->where('title_ar', 'like', "%{$q}%")
                    ->orWhere('title_en', 'like', "%{$q}%")
                    ->orWhereHas('sellerBusiness', function (Builder $b) use ($q) {
                        $b->where('name', 'like', "%{$q}%");
                    });
            });
        }

        if (! empty($data['offerable_type'])) {
            $query->where('offerable_type', (string) $data['offerable_type']);
        }

        if (array_key_exists('offerable_id', $data) && $data['offerable_id'] !== null && $data['offerable_id'] !== '') {
            $query->where('offerable_id', (int) $data['offerable_id']);
        }

        if (! empty($data['seller_business_id'])) {
            $query->where('seller_business_id', (int) $data['seller_business_id']);
        }

        if (! empty($data['owner_business_id'])) {
            $query->where('owner_business_id', (int) $data['owner_business_id']);
        }

        if (! empty($data['source_type'])) {
            $query->where('source_type', (string) $data['source_type']);
        }

        if (array_key_exists('min_price', $data) && $data['min_price'] !== null && $data['min_price'] !== '') {
            $query->where('final_price', '>=', (float) $data['min_price']);
        }

        if (array_key_exists('max_price', $data) && $data['max_price'] !== null && $data['max_price'] !== '') {
            $query->where('final_price', '<=', (float) $data['max_price']);
        }
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'lowest_price' => $query->orderBy('final_price')->orderByDesc('ranking_score')->latest('id'),
            'highest_price' => $query->orderByDesc('final_price')->latest('id'),
            'ranking' => $query->orderByDesc('ranking_score')->orderBy('final_price')->latest('id'),
            default => $query->latest('id'),
        };
    }

    private function offerableTypes(): array
    {
        return [
            CommercialOffer::OFFERABLE_BOOKABLE_ITEM,
            CommercialOffer::OFFERABLE_PRODUCT,
            CommercialOffer::OFFERABLE_SERVICE,
            CommercialOffer::OFFERABLE_PACKAGE,
        ];
    }

    private function publicSourceTypes(): array
    {
        return [
            CommercialOffer::SOURCE_DIRECT,
            CommercialOffer::SOURCE_ALLOCATION,
            CommercialOffer::SOURCE_RESELLER,
            CommercialOffer::SOURCE_PROMOTION,
            CommercialOffer::SOURCE_MARKETPLACE,
        ];
    }
}
