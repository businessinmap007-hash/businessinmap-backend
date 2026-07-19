<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\User;
use App\Services\Notifications\InAppNotificationService;
use App\Services\Notifications\NotificationTypeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class NotificationCenterAdminController extends Controller
{
    public function index(Request $request, NotificationTypeService $typeService)
    {
        $availableTypes = $typeService->allTypes();

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'type' => ['nullable', Rule::in($availableTypes)],
            'status' => ['nullable', Rule::in(AppNotification::statuses())],
            'priority' => ['nullable', Rule::in(AppNotification::priorities())],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        $query = AppNotification::query()
            ->with(['user:id,name,email,phone,type,logo,image', 'actor:id,name,type,logo,image']);

        $this->applyFilters($query, $data);

        $rows = $query
            ->latest('id')
            ->paginate((int) ($data['per_page'] ?? 30))
            ->withQueryString();

        $totals = [
            'all' => AppNotification::query()->count(),
            'unread' => AppNotification::query()->where('status', AppNotification::STATUS_UNREAD)->count(),
            'read' => AppNotification::query()->where('status', AppNotification::STATUS_READ)->count(),
            'archived' => AppNotification::query()->where('status', AppNotification::STATUS_ARCHIVED)->count(),
            'offers' => AppNotification::query()->where('type', AppNotification::TYPE_OFFER)->count(),
            'urgent' => AppNotification::query()->where('priority', AppNotification::PRIORITY_URGENT)->count(),
        ];

        $users = User::query()
            ->latest('id')
            ->limit(500)
            ->get(['id', 'name', 'email', 'phone', 'type']);

        return view('admin-v2.notification-center.index', [
            'rows' => $rows,
            'totals' => $totals,
            'users' => $users,
            'types' => $availableTypes,
            'typeOptions' => $typeService->options(),
            'statuses' => AppNotification::statuses(),
            'priorities' => AppNotification::priorities(),
            'filters' => [
                'q' => $data['q'] ?? '',
                'user_id' => $data['user_id'] ?? null,
                'type' => $data['type'] ?? '',
                'status' => $data['status'] ?? '',
                'priority' => $data['priority'] ?? '',
                'per_page' => (int) ($data['per_page'] ?? 30),
            ],
        ]);
    }

    public function syncOffers(InAppNotificationService $service)
    {
        $created = $service->syncOfferFollowNotifications(1000);

        return back()->with('success', __('تم مزامنة إشعارات العروض. New created: ') . $created);
    }

    private function applyFilters(Builder $query, array $data): void
    {
        $q = trim((string) ($data['q'] ?? ''));

        if ($q !== '') {
            $query->where(function (Builder $w) use ($q) {
                if (is_numeric($q)) {
                    $w->orWhere('id', (int) $q)
                        ->orWhere('user_id', (int) $q)
                        ->orWhere('notifiable_id', (int) $q)
                        ->orWhere('source_id', (int) $q);
                }

                $w->orWhere('title_ar', 'like', "%{$q}%")
                    ->orWhere('title_en', 'like', "%{$q}%")
                    ->orWhere('body_ar', 'like', "%{$q}%")
                    ->orWhere('body_en', 'like', "%{$q}%")
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

        if (! empty($data['type'])) {
            $query->where('type', (string) $data['type']);
        }

        if (! empty($data['status'])) {
            $query->where('status', (string) $data['status']);
        }

        if (! empty($data['priority'])) {
            $query->where('priority', (string) $data['priority']);
        }
    }
}
