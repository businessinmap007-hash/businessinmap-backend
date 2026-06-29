<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\CommercialOffer;
use App\Models\OfferFollow;
use App\Models\OfferFollowNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class OfferFollowDashboardController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'user_type' => ['nullable', Rule::in(['client', 'business', 'admin'])],
            'followable_type' => ['nullable', Rule::in(OfferFollow::followableTypes())],
            'audience_type' => ['nullable', Rule::in(CommercialOffer::audienceTypes())],
            'is_active' => ['nullable', Rule::in(['1', '0'])],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $query = OfferFollow::query()
            ->with(['user:id,name,email,phone,type,logo,image'])
            ->withCount('notifications');

        $this->applyFilters($query, $data);

        $follows = $query
            ->latest('id')
            ->paginate((int) ($data['per_page'] ?? 30))
            ->withQueryString();

        $totals = [
            'follows' => OfferFollow::query()->count(),
            'active_follows' => OfferFollow::query()->where('is_active', 1)->count(),
            'keywords' => OfferFollow::query()->where('followable_type', OfferFollow::FOLLOW_KEYWORD)->count(),
            'b2b' => OfferFollow::query()->where('audience_type', CommercialOffer::AUDIENCE_B2B)->count(),
            'b2c' => OfferFollow::query()->where('audience_type', CommercialOffer::AUDIENCE_B2C)->count(),
            'notifications' => OfferFollowNotification::query()->count(),
            'unread_notifications' => OfferFollowNotification::query()->where('status', OfferFollowNotification::STATUS_UNREAD)->count(),
        ];

        $topKeywords = OfferFollow::query()
            ->select('keyword', DB::raw('COUNT(*) as total'))
            ->where('followable_type', OfferFollow::FOLLOW_KEYWORD)
            ->whereNotNull('keyword')
            ->groupBy('keyword')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        $topFollowables = OfferFollow::query()
            ->select('followable_type', 'followable_id', DB::raw('COUNT(*) as total'))
            ->where('followable_type', '<>', OfferFollow::FOLLOW_KEYWORD)
            ->groupBy('followable_type', 'followable_id')
            ->orderByDesc('total')
            ->limit(30)
            ->get();

        $topCategoryChildren = OfferFollow::query()
            ->select('category_child_id', DB::raw('COUNT(*) as total'))
            ->whereNotNull('category_child_id')
            ->groupBy('category_child_id')
            ->orderByDesc('total')
            ->limit(20)
            ->get();

        $notifications = OfferFollowNotification::query()
            ->with([
                'user:id,name,type,logo,image',
                'follow:id,followable_type,followable_id,keyword,category_child_id,audience_type',
                'offer:id,title_ar,title_en,offerable_type,offerable_id,seller_business_id,audience_type,status,final_price,currency',
                'offer.sellerBusiness:id,name,type,logo',
            ])
            ->latest('id')
            ->limit(30)
            ->get();

        $users = User::query()
            ->orderByDesc('id')
            ->limit(500)
            ->get(['id', 'name', 'email', 'phone', 'type']);

        return view('admin-v2.offer-follows.index', [
            'follows' => $follows,
            'totals' => $totals,
            'topKeywords' => $topKeywords,
            'topFollowables' => $topFollowables,
            'topCategoryChildren' => $topCategoryChildren,
            'notifications' => $notifications,
            'users' => $users,
            'followableTypes' => OfferFollow::followableTypes(),
            'audienceTypes' => CommercialOffer::audienceTypes(),
            'filters' => [
                'q' => $data['q'] ?? '',
                'user_id' => $data['user_id'] ?? null,
                'user_type' => $data['user_type'] ?? '',
                'followable_type' => $data['followable_type'] ?? '',
                'audience_type' => $data['audience_type'] ?? '',
                'is_active' => $data['is_active'] ?? '',
                'per_page' => (int) ($data['per_page'] ?? 30),
            ],
        ]);
    }

    private function applyFilters(Builder $query, array $data): void
    {
        $q = trim((string) ($data['q'] ?? ''));

        if ($q !== '') {
            $query->where(function (Builder $w) use ($q) {
                if (is_numeric($q)) {
                    $w->orWhere('id', (int) $q)
                        ->orWhere('user_id', (int) $q)
                        ->orWhere('followable_id', (int) $q)
                        ->orWhere('category_id', (int) $q)
                        ->orWhere('category_child_id', (int) $q);
                }

                $w->orWhere('keyword', 'like', "%{$q}%")
                    ->orWhereHas('user', function (Builder $u) use ($q) {
                        $u->where('name', 'like', "%{$q}%")
                            ->orWhere('email', 'like', "%{$q}%")
                            ->orWhere('phone', 'like', "%{$q}%");
                    });
            });
        }

        if (! empty($data['user_id'])) {
            $query->where('user_id', (int) $data['user_id']);
        }

        if (! empty($data['user_type'])) {
            $query->whereHas('user', fn (Builder $u) => $u->where('type', (string) $data['user_type']));
        }

        if (! empty($data['followable_type'])) {
            $query->where('followable_type', (string) $data['followable_type']);
        }

        if (! empty($data['audience_type'])) {
            $query->where('audience_type', (string) $data['audience_type']);
        }

        if (($data['is_active'] ?? '') !== '') {
            $query->where('is_active', (int) $data['is_active']);
        }
    }
}
