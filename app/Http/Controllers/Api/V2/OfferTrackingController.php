<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\CommercialOffer;
use App\Models\OfferTrackingEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class OfferTrackingController extends Controller
{
    public function track(Request $request, int $offer)
    {
        $row = CommercialOffer::query()->active()->findOrFail($offer);

        $data = $request->validate([
            'event_type' => ['required', Rule::in(OfferTrackingEvent::eventTypes())],
            'source' => ['nullable', 'string', 'max:80'],
            'session_id' => ['nullable', 'string', 'max:191'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'value_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'reference_type' => ['nullable', 'string', 'max:80'],
            'reference_id' => ['nullable', 'string', 'max:191'],
            'meta' => ['nullable', 'array'],
        ]);

        $user = $request->user();

        $event = OfferTrackingEvent::query()->create([
            'offer_id' => (int) $row->id,
            'user_id' => $user ? (int) $user->id : null,
            'business_id' => (int) $row->seller_business_id,
            'event_type' => (string) $data['event_type'],
            'source' => $data['source'] ?? 'api_v2',
            'session_id' => $data['session_id'] ?? $request->header('X-Session-Id'),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'quantity' => (int) ($data['quantity'] ?? 1),
            'value_amount' => $data['value_amount'] ?? null,
            'currency' => $data['currency'] ?? ($row->currency ?: 'EGP'),
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'occurred_at' => now(),
            'meta' => array_merge((array) ($data['meta'] ?? []), [
                'offerable_type' => $row->offerable_type,
                'offerable_id' => (int) $row->offerable_id,
                'audience_type' => $row->audience_type,
                'source_type' => $row->source_type,
            ]),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Offer event tracked successfully.',
            'data' => [
                'event' => $event,
            ],
        ], 201);
    }

    public function myPerformance(Request $request)
    {
        // Business-only route (gated by the `business` middleware).
        return $this->performanceForBusiness($request, (int) $request->user()->id);
    }

    public function performanceForBusiness(Request $request, int $businessId)
    {
        $data = $request->validate([
            'offer_id' => ['nullable', 'integer', 'min:1'],
            'event_type' => ['nullable', Rule::in(OfferTrackingEvent::eventTypes())],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $base = OfferTrackingEvent::query()
            ->where('business_id', $businessId);

        $this->applyFilters($base, $data);

        $totals = (clone $base)
            ->select('event_type', DB::raw('COUNT(*) as total'), DB::raw('COALESCE(SUM(value_amount), 0) as value_total'))
            ->groupBy('event_type')
            ->get()
            ->mapWithKeys(function ($row) {
                return [
                    (string) $row->event_type => [
                        'total' => (int) $row->total,
                        'value_total' => round((float) $row->value_total, 2),
                    ],
                ];
            });

        $offerStats = (clone $base)
            ->select('offer_id', 'event_type', DB::raw('COUNT(*) as total'), DB::raw('COALESCE(SUM(value_amount), 0) as value_total'))
            ->groupBy('offer_id', 'event_type')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(100)
            ->get();

        $events = (clone $base)
            ->with(['offer:id,title_ar,title_en,offerable_type,offerable_id,final_price,currency,status,audience_type'])
            ->latest('id')
            ->paginate((int) ($data['per_page'] ?? 30))
            ->withQueryString();

        return response()->json([
            'success' => true,
            'data' => [
                'business_id' => $businessId,
                'totals' => $totals,
                'offer_stats' => $offerStats,
                'events' => $events,
            ],
        ]);
    }

    private function applyFilters(Builder $query, array $data): void
    {
        if (! empty($data['offer_id'])) {
            $query->where('offer_id', (int) $data['offer_id']);
        }

        if (! empty($data['event_type'])) {
            $query->where('event_type', (string) $data['event_type']);
        }

        if (! empty($data['date_from'])) {
            $query->where('occurred_at', '>=', $data['date_from']);
        }

        if (! empty($data['date_to'])) {
            $query->where('occurred_at', '<=', $data['date_to']);
        }
    }
}
