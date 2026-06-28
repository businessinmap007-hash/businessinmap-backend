<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\CommercialOffer;
use App\Models\OfferFollow;
use App\Models\OfferFollowNotification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class OfferFollowController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $rows = OfferFollow::query()
            ->where('user_id', (int) $user->id)
            ->latest('id')
            ->paginate((int) $request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => [
                'follows' => $rows,
                'types' => OfferFollow::followableTypes(),
                'audiences' => CommercialOffer::audienceTypes(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'followable_type' => ['required', Rule::in(OfferFollow::followableTypes())],
            'followable_id' => ['nullable', 'integer', 'min:0'],
            'keyword' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'category_child_id' => ['nullable', 'integer', 'min:1'],
            'audience_type' => ['nullable', Rule::in(CommercialOffer::audienceTypes())],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'meta' => ['nullable', 'array'],
        ]);

        $type = (string) $data['followable_type'];

        if ($type === OfferFollow::FOLLOW_KEYWORD && trim((string) ($data['keyword'] ?? '')) === '') {
            return response()->json(['success' => false, 'message' => 'keyword is required for keyword follow.'], 422);
        }

        if (! in_array($type, [OfferFollow::FOLLOW_KEYWORD, OfferFollow::FOLLOW_CATEGORY_CHILD], true)
            && (int) ($data['followable_id'] ?? 0) <= 0) {
            return response()->json(['success' => false, 'message' => 'followable_id is required for this follow type.'], 422);
        }

        if ($type === OfferFollow::FOLLOW_CATEGORY_CHILD && (int) ($data['category_child_id'] ?? 0) <= 0) {
            return response()->json(['success' => false, 'message' => 'category_child_id is required for category_child follow.'], 422);
        }

        $lookup = [
            'user_id' => (int) $user->id,
            'followable_type' => $type,
            'followable_id' => (int) ($data['followable_id'] ?? 0),
            'keyword' => $data['keyword'] ?? null,
            'category_child_id' => $data['category_child_id'] ?? null,
        ];

        $follow = OfferFollow::query()->updateOrCreate($lookup, [
            'category_id' => $data['category_id'] ?? null,
            'audience_type' => $data['audience_type'] ?? ($user->type === 'business' ? CommercialOffer::AUDIENCE_B2B : CommercialOffer::AUDIENCE_B2C),
            'min_price' => $data['min_price'] ?? null,
            'max_price' => $data['max_price'] ?? null,
            'is_active' => 1,
            'meta' => array_merge((array) ($data['meta'] ?? []), ['source' => 'api_v2_offer_follows']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Follow saved successfully.',
            'data' => ['follow' => $follow],
        ], 201);
    }

    public function destroy(Request $request, int $follow)
    {
        $user = $request->user();

        $row = OfferFollow::query()
            ->where('id', $follow)
            ->where('user_id', (int) $user->id)
            ->firstOrFail();

        $row->delete();

        return response()->json([
            'success' => true,
            'message' => 'Follow removed successfully.',
        ]);
    }

    public function notifications(Request $request)
    {
        $user = $request->user();
        $status = trim((string) $request->get('status', ''));

        $query = OfferFollowNotification::query()
            ->with(['offer.sellerBusiness:id,name,logo,type,category_id,category_child_id', 'follow'])
            ->where('user_id', (int) $user->id)
            ->latest('id');

        if ($status !== '') {
            $query->where('status', $status);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'notifications' => $query->paginate((int) $request->get('per_page', 20)),
                'unread_count' => OfferFollowNotification::query()
                    ->where('user_id', (int) $user->id)
                    ->where('status', OfferFollowNotification::STATUS_UNREAD)
                    ->count(),
            ],
        ]);
    }

    public function markRead(Request $request, int $notification)
    {
        $user = $request->user();

        $row = OfferFollowNotification::query()
            ->where('id', $notification)
            ->where('user_id', (int) $user->id)
            ->firstOrFail();

        $row->update([
            'status' => OfferFollowNotification::STATUS_READ,
            'read_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
            'data' => ['notification' => $row->fresh()],
        ]);
    }
}
