<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\CommercialOffer;
use App\Models\OfferTrackingEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class OfferPerformanceController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'business_id' => ['nullable', 'integer', 'min:1'],
            'offer_id' => ['nullable', 'integer', 'min:1'],
            'event_type' => ['nullable', Rule::in(OfferTrackingEvent::eventTypes())],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $base = OfferTrackingEvent::query();
        $this->applyFilters($base, $data);

        $totals = [
            'all' => (clone $base)->count(),
            'views' => (clone $base)->where('event_type', OfferTrackingEvent::EVENT_VIEW)->count(),
            'clicks' => (clone $base)->where('event_type', OfferTrackingEvent::EVENT_CLICK)->count(),
            'leads' => (clone $base)->where('event_type', OfferTrackingEvent::EVENT_LEAD)->count(),
            'conversions' => (clone $base)->where('event_type', OfferTrackingEvent::EVENT_CONVERSION)->count(),
            'value_total' => round((float) (clone $base)->sum('value_amount'), 2),
        ];

        $eventBreakdown = (clone $base)
            ->select('event_type', DB::raw('COUNT(*) as total'), DB::raw('COALESCE(SUM(value_amount), 0) as value_total'))
            ->groupBy('event_type')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->get();

        $offerStats = (clone $base)
            ->select('offer_id', 'business_id', DB::raw('COUNT(*) as events_count'), DB::raw('COALESCE(SUM(value_amount), 0) as value_total'))
            ->with(['offer:id,title_ar,title_en,final_price,currency,audience_type,status', 'business:id,name,logo,type'])
            ->groupBy('offer_id', 'business_id')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->limit(100)
            ->get();

        $events = (clone $base)
            ->with([
                'offer:id,title_ar,title_en,offerable_type,offerable_id,final_price,currency,status,audience_type',
                'business:id,name,logo,type',
                'user:id,name,type,logo,image',
            ])
            ->latest('id')
            ->paginate((int) ($data['per_page'] ?? 30))
            ->withQueryString();

        $businesses = User::query()
            ->where('type', User::TYPE_BUSINESS)
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'email', 'phone']);

        $offers = CommercialOffer::query()
            ->latest('id')
            ->limit(500)
            ->get(['id', 'title_ar', 'title_en', 'seller_business_id', 'final_price', 'currency', 'status']);

        return view('admin-v2.offer-performance.index', [
            'totals' => $totals,
            'eventBreakdown' => $eventBreakdown,
            'offerStats' => $offerStats,
            'events' => $events,
            'businesses' => $businesses,
            'offers' => $offers,
            'eventTypes' => OfferTrackingEvent::eventTypes(),
            'filters' => [
                'business_id' => $data['business_id'] ?? null,
                'offer_id' => $data['offer_id'] ?? null,
                'event_type' => $data['event_type'] ?? null,
                'date_from' => $data['date_from'] ?? null,
                'date_to' => $data['date_to'] ?? null,
                'per_page' => (int) ($data['per_page'] ?? 30),
            ],
        ]);
    }

    private function applyFilters(Builder $query, array $data): void
    {
        if (! empty($data['business_id'])) {
            $query->where('business_id', (int) $data['business_id']);
        }

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
