<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\CommercialOffer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class SearchOffersController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'category_child_id' => ['nullable', 'integer', 'min:1'],
            'business_id' => ['nullable', 'integer', 'min:1'],
            'offerable_type' => ['nullable', Rule::in($this->offerableTypes())],
            'source_type' => ['nullable', Rule::in($this->sourceTypes())],
            'audience_type' => ['nullable', Rule::in(CommercialOffer::audienceTypes())],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $businesses = $this->businessesQuery($data)
            ->limit(20)
            ->get(['id', 'name', 'type', 'logo', 'category_id', 'category_child_id']);

        $offersQuery = CommercialOffer::query()
            ->with(['sellerBusiness:id,name,type,logo,category_id,category_child_id', 'ownerBusiness:id,name,type,logo,category_id,category_child_id'])
            ->active()
            ->whereIn('source_type', $this->sourceTypes())
            ->whereIn('audience_type', $this->visibleAudiences($request, $data['audience_type'] ?? null));

        $this->applyOfferFilters($offersQuery, $data);

        if ($businesses->isNotEmpty() && empty($data['business_id'])) {
            $ids = $businesses->pluck('id')->map(fn ($id) => (int) $id)->all();
            $offersQuery->where(function (Builder $q) use ($ids) {
                $q->whereIn('seller_business_id', $ids)
                    ->orWhereIn('owner_business_id', $ids);
            });
        }

        $offers = $offersQuery
            ->orderBy('final_price')
            ->orderByDesc('ranking_score')
            ->latest('id')
            ->paginate((int) ($data['per_page'] ?? 20))
            ->withQueryString();

        return response()->json([
            'success' => true,
            'data' => [
                'query' => [
                    'q' => $data['q'] ?? null,
                    'category_id' => $data['category_id'] ?? null,
                    'category_child_id' => $data['category_child_id'] ?? null,
                    'business_id' => $data['business_id'] ?? null,
                    'offerable_type' => $data['offerable_type'] ?? null,
                    'audience_type' => $data['audience_type'] ?? null,
                ],
                'businesses' => $businesses,
                'offers' => $offers,
                'best_offer' => $offers->getCollection()->first(),
            ],
        ]);
    }

    public function business(int $businessId, Request $request)
    {
        $request->merge(['business_id' => $businessId]);

        return $this->index($request);
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

    private function businessesQuery(array $data): Builder
    {
        $query = User::query()->where('type', 'business');

        $q = trim((string) ($data['q'] ?? ''));

        if ($q !== '') {
            $query->where(function (Builder $w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%");
            });
        }

        if (! empty($data['business_id'])) {
            $query->where('id', (int) $data['business_id']);
        }

        if (! empty($data['category_id'])) {
            $query->where('category_id', (int) $data['category_id']);
        }

        if (! empty($data['category_child_id'])) {
            $query->where('category_child_id', (int) $data['category_child_id']);
        }

        return $query->latest('id');
    }

    private function applyOfferFilters(Builder $query, array $data): void
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

        if (! empty($data['business_id'])) {
            $businessId = (int) $data['business_id'];
            $query->where(function (Builder $q) use ($businessId) {
                $q->where('seller_business_id', $businessId)
                    ->orWhere('owner_business_id', $businessId);
            });
        }

        if (! empty($data['category_id'])) {
            $query->whereHas('sellerBusiness', function (Builder $b) use ($data) {
                $b->where('category_id', (int) $data['category_id']);
            });
        }

        if (! empty($data['category_child_id'])) {
            $query->whereHas('sellerBusiness', function (Builder $b) use ($data) {
                $b->where('category_child_id', (int) $data['category_child_id']);
            });
        }

        if (! empty($data['offerable_type'])) {
            $query->where('offerable_type', (string) $data['offerable_type']);
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

    private function offerableTypes(): array
    {
        return [
            CommercialOffer::OFFERABLE_BOOKABLE_ITEM,
            CommercialOffer::OFFERABLE_PRODUCT,
            CommercialOffer::OFFERABLE_SERVICE,
            CommercialOffer::OFFERABLE_PACKAGE,
        ];
    }

    private function sourceTypes(): array
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
