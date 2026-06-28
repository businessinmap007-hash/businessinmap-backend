<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\CommercialOffer;
use App\Services\Commercial\BusinessOffersSubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class BusinessOfferController extends Controller
{
    public function index(Request $request, BusinessOffersSubscriptionService $subscriptionService)
    {
        $user = $request->user();

        if (! $user || (string) $user->type !== 'business') {
            return response()->json(['success' => false, 'message' => 'Business account required.'], 403);
        }

        $status = trim((string) $request->get('status', ''));

        $query = CommercialOffer::query()
            ->where('seller_business_id', (int) $user->id)
            ->latest('id');

        if ($status !== '') {
            $query->where('status', $status);
        }

        $offers = $query->paginate((int) $request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => [
                'usage' => $subscriptionService->usage((int) $user->id),
                'offers' => $offers,
            ],
        ]);
    }

    public function store(Request $request, BusinessOffersSubscriptionService $subscriptionService)
    {
        $user = $request->user();

        if (! $user || (string) $user->type !== 'business') {
            return response()->json(['success' => false, 'message' => 'Business account required.'], 403);
        }

        $data = $this->validatedData($request, (int) $user->id);
        $subscriptionService->ensureCanSaveOffer((int) $user->id, $data);

        $offer = CommercialOffer::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Offer created successfully.',
            'data' => [
                'offer' => $offer->fresh(['ownerBusiness:id,name,logo', 'sellerBusiness:id,name,logo']),
                'usage' => $subscriptionService->usage((int) $user->id),
            ],
        ], 201);
    }

    public function update(Request $request, int $offer, BusinessOffersSubscriptionService $subscriptionService)
    {
        $user = $request->user();

        if (! $user || (string) $user->type !== 'business') {
            return response()->json(['success' => false, 'message' => 'Business account required.'], 403);
        }

        $row = CommercialOffer::query()
            ->where('id', $offer)
            ->where('seller_business_id', (int) $user->id)
            ->firstOrFail();

        if ((string) $row->source_type === CommercialOffer::SOURCE_ALLOCATION) {
            return response()->json([
                'success' => false,
                'message' => 'Allocation offers are managed from allocations, not from business offers API.',
            ], 422);
        }

        $data = $this->validatedData($request, (int) $user->id, $row);
        $subscriptionService->ensureCanSaveOffer((int) $user->id, $data, (int) $row->id);

        $row->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Offer updated successfully.',
            'data' => [
                'offer' => $row->fresh(['ownerBusiness:id,name,logo', 'sellerBusiness:id,name,logo']),
                'usage' => $subscriptionService->usage((int) $user->id),
            ],
        ]);
    }

    public function toggle(Request $request, int $offer, BusinessOffersSubscriptionService $subscriptionService)
    {
        $user = $request->user();

        if (! $user || (string) $user->type !== 'business') {
            return response()->json(['success' => false, 'message' => 'Business account required.'], 403);
        }

        $row = CommercialOffer::query()
            ->where('id', $offer)
            ->where('seller_business_id', (int) $user->id)
            ->firstOrFail();

        if ((string) $row->source_type === CommercialOffer::SOURCE_ALLOCATION) {
            return response()->json([
                'success' => false,
                'message' => 'Allocation offers are managed from allocations, not from business offers API.',
            ], 422);
        }

        $newStatus = $row->status === CommercialOffer::STATUS_ACTIVE
            ? CommercialOffer::STATUS_PAUSED
            : CommercialOffer::STATUS_ACTIVE;

        $data = $row->toArray();
        $data['status'] = $newStatus;

        $subscriptionService->ensureCanSaveOffer((int) $user->id, $data, (int) $row->id);

        $row->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => 'Offer status updated successfully.',
            'data' => [
                'offer' => $row->fresh(),
                'usage' => $subscriptionService->usage((int) $user->id),
            ],
        ]);
    }

    public function destroy(Request $request, int $offer)
    {
        $user = $request->user();

        if (! $user || (string) $user->type !== 'business') {
            return response()->json(['success' => false, 'message' => 'Business account required.'], 403);
        }

        $row = CommercialOffer::query()
            ->where('id', $offer)
            ->where('seller_business_id', (int) $user->id)
            ->firstOrFail();

        if ((string) $row->source_type === CommercialOffer::SOURCE_ALLOCATION) {
            return response()->json([
                'success' => false,
                'message' => 'Allocation offers are managed from allocations, not from business offers API.',
            ], 422);
        }

        $row->delete();

        return response()->json([
            'success' => true,
            'message' => 'Offer deleted successfully.',
        ]);
    }

    private function validatedData(Request $request, int $businessId, ?CommercialOffer $existing = null): array
    {
        $data = $request->validate([
            'offerable_type' => ['required', Rule::in([
                CommercialOffer::OFFERABLE_BOOKABLE_ITEM,
                CommercialOffer::OFFERABLE_PRODUCT,
                CommercialOffer::OFFERABLE_SERVICE,
                CommercialOffer::OFFERABLE_PACKAGE,
            ])],
            'offerable_id' => ['nullable', 'integer', 'min:0'],
            'owner_business_id' => ['nullable', 'integer', 'exists:users,id'],
            'source_type' => ['nullable', Rule::in([
                CommercialOffer::SOURCE_DIRECT,
                CommercialOffer::SOURCE_RESELLER,
                CommercialOffer::SOURCE_PROMOTION,
                CommercialOffer::SOURCE_MARKETPLACE,
            ])],
            'source_id' => ['nullable', 'integer', 'min:1'],
            'title_ar' => ['nullable', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'final_price' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'discount_type' => ['nullable', Rule::in(['fixed', 'percent'])],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'availability_mode' => ['nullable', Rule::in([
                CommercialOffer::AVAILABILITY_INSTANT,
                CommercialOffer::AVAILABILITY_REQUEST,
                CommercialOffer::AVAILABILITY_LIMITED,
            ])],
            'available_quantity' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_refundable' => ['nullable', 'boolean'],
            'payment_model' => ['nullable', 'string', 'max:50'],
            'ranking_score' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in([
                CommercialOffer::STATUS_ACTIVE,
                CommercialOffer::STATUS_PAUSED,
                CommercialOffer::STATUS_EXPIRED,
                CommercialOffer::STATUS_CANCELLED,
            ])],
            'meta' => ['nullable', 'array'],
        ]);

        $data['offerable_id'] = (int) ($data['offerable_id'] ?? 0);
        $data['owner_business_id'] = (int) ($data['owner_business_id'] ?? $businessId);
        $data['seller_business_id'] = $businessId;
        $data['source_type'] = (string) ($data['source_type'] ?? CommercialOffer::SOURCE_PROMOTION);
        $data['source_id'] = $data['source_id'] ?? null;
        $data['currency'] = (string) ($data['currency'] ?? ($existing->currency ?? 'EGP'));
        $data['availability_mode'] = (string) ($data['availability_mode'] ?? ($existing->availability_mode ?? CommercialOffer::AVAILABILITY_INSTANT));
        $data['is_refundable'] = $request->boolean('is_refundable', (bool) ($existing->is_refundable ?? false));
        $data['ranking_score'] = (float) ($data['ranking_score'] ?? ($existing->ranking_score ?? 0));
        $data['status'] = (string) ($data['status'] ?? ($existing->status ?? CommercialOffer::STATUS_ACTIVE));
        $data['meta'] = array_merge((array) ($existing->meta ?? []), (array) ($data['meta'] ?? []), [
            'source' => 'api_v2_business_offers',
        ]);

        return $data;
    }
}
