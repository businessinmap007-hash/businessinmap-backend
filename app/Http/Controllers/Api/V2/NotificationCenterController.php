<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class NotificationCenterController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'status' => ['nullable', Rule::in(AppNotification::statuses())],
            'type' => ['nullable', Rule::in(AppNotification::types())],
            'priority' => ['nullable', Rule::in(AppNotification::priorities())],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = AppNotification::query()
            ->with(['actor:id,name,type,logo,image'])
            ->where('user_id', (int) $user->id)
            ->visible()
            ->latest('id');

        if (! empty($data['status'])) {
            $query->where('status', (string) $data['status']);
        }

        if (! empty($data['type'])) {
            $query->where('type', (string) $data['type']);
        }

        if (! empty($data['priority'])) {
            $query->where('priority', (string) $data['priority']);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $this->unreadCountFor((int) $user->id),
                'summary' => $this->summaryFor((int) $user->id),
                'notifications' => $query->paginate((int) ($data['per_page'] ?? 20)),
            ],
        ]);
    }

    public function unreadCount(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $this->unreadCountFor((int) $request->user()->id),
                'summary' => $this->summaryFor((int) $request->user()->id),
            ],
        ]);
    }

    public function show(Request $request, int $notification)
    {
        $row = $this->owned($request, $notification)
            ->with(['actor:id,name,type,logo,image'])
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'notification' => $row,
                'unread_count' => $this->unreadCountFor((int) $request->user()->id),
            ],
        ]);
    }

    public function markRead(Request $request, int $notification)
    {
        $row = $this->owned($request, $notification)->firstOrFail();

        $row->update([
            'status' => AppNotification::STATUS_READ,
            'read_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
            'data' => [
                'notification' => $row->fresh(),
                'unread_count' => $this->unreadCountFor((int) $request->user()->id),
            ],
        ]);
    }

    public function markAllRead(Request $request)
    {
        $userId = (int) $request->user()->id;

        $updated = AppNotification::query()
            ->where('user_id', $userId)
            ->where('status', AppNotification::STATUS_UNREAD)
            ->update([
                'status' => AppNotification::STATUS_READ,
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
        $row = $this->owned($request, $notification)->firstOrFail();

        $row->update([
            'status' => AppNotification::STATUS_ARCHIVED,
            'archived_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification archived.',
            'data' => [
                'notification' => $row->fresh(),
                'unread_count' => $this->unreadCountFor((int) $request->user()->id),
            ],
        ]);
    }

    private function owned(Request $request, int $notification)
    {
        return AppNotification::query()
            ->where('id', $notification)
            ->where('user_id', (int) $request->user()->id);
    }

    private function unreadCountFor(int $userId): int
    {
        return (int) AppNotification::query()
            ->where('user_id', $userId)
            ->visible()
            ->unread()
            ->count();
    }

    private function summaryFor(int $userId): array
    {
        $rows = AppNotification::query()
            ->selectRaw('type, COUNT(*) as total')
            ->where('user_id', $userId)
            ->visible()
            ->where('status', AppNotification::STATUS_UNREAD)
            ->groupBy('type')
            ->pluck('total', 'type')
            ->toArray();

        return [
            'offer' => (int) ($rows[AppNotification::TYPE_OFFER] ?? 0),
            'booking' => (int) ($rows[AppNotification::TYPE_BOOKING] ?? 0),
            'wallet' => (int) ($rows[AppNotification::TYPE_WALLET] ?? 0),
            'guarantee' => (int) ($rows[AppNotification::TYPE_GUARANTEE] ?? 0),
            'dispute' => (int) ($rows[AppNotification::TYPE_DISPUTE] ?? 0),
            'system' => (int) ($rows[AppNotification::TYPE_SYSTEM] ?? 0),
        ];
    }
}
