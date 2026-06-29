<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\OfferFollowNotification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class OfferNotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'status' => ['nullable', Rule::in([
                OfferFollowNotification::STATUS_UNREAD,
                OfferFollowNotification::STATUS_READ,
                OfferFollowNotification::STATUS_ARCHIVED,
            ])],
            'match_type' => ['nullable', 'string', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = OfferFollowNotification::query()
            ->with([
                'follow:id,followable_type,followable_id,keyword,category_id,category_child_id,audience_type,min_price,max_price',
                'offer:id,title_ar,title_en,offerable_type,offerable_id,seller_business_id,source_type,audience_type,base_price,final_price,currency,discount_type,discount_value,availability_mode,available_quantity,starts_at,ends_at,is_refundable,payment_model,ranking_score,is_featured,featured_until,boost_score,status,meta',
                'offer.sellerBusiness:id,name,type,logo,image,category_id,category_child_id',
            ])
            ->where('user_id', (int) $user->id)
            ->latest('id');

        if (! empty($data['status'])) {
            $query->where('status', (string) $data['status']);
        }

        if (! empty($data['match_type'])) {
            $query->where('match_type', (string) $data['match_type']);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $this->unreadCountForUser((int) $user->id),
                'notifications' => $query->paginate((int) ($data['per_page'] ?? 20)),
            ],
        ]);
    }

    public function unreadCount(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $this->unreadCountForUser((int) $request->user()->id),
            ],
        ]);
    }

    public function show(Request $request, int $notification)
    {
        $row = $this->ownedNotification($request, $notification)
            ->with([
                'follow',
                'offer.sellerBusiness:id,name,type,logo,image,category_id,category_child_id',
            ])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'notification' => $row,
                'unread_count' => $this->unreadCountForUser((int) $request->user()->id),
            ],
        ]);
    }

    public function markRead(Request $request, int $notification)
    {
        $row = $this->ownedNotification($request, $notification)->firstOrFail();

        $row->update([
            'status' => OfferFollowNotification::STATUS_READ,
            'read_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
            'data' => [
                'notification' => $row->fresh(),
                'unread_count' => $this->unreadCountForUser((int) $request->user()->id),
            ],
        ]);
    }

    public function markAllRead(Request $request)
    {
        $userId = (int) $request->user()->id;

        $updated = OfferFollowNotification::query()
            ->where('user_id', $userId)
            ->where('status', OfferFollowNotification::STATUS_UNREAD)
            ->update([
                'status' => OfferFollowNotification::STATUS_READ,
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
            'data' => [
                'updated' => (int) $updated,
                'unread_count' => 0,
            ],
        ]);
    }

    public function archive(Request $request, int $notification)
    {
        $row = $this->ownedNotification($request, $notification)->firstOrFail();

        $row->update([
            'status' => OfferFollowNotification::STATUS_ARCHIVED,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification archived.',
            'data' => [
                'notification' => $row->fresh(),
                'unread_count' => $this->unreadCountForUser((int) $request->user()->id),
            ],
        ]);
    }

    private function ownedNotification(Request $request, int $notification)
    {
        return OfferFollowNotification::query()
            ->where('id', $notification)
            ->where('user_id', (int) $request->user()->id);
    }

    private function unreadCountForUser(int $userId): int
    {
        return (int) OfferFollowNotification::query()
            ->where('user_id', $userId)
            ->where('status', OfferFollowNotification::STATUS_UNREAD)
            ->count();
    }
}
