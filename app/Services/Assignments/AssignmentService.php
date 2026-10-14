<?php

namespace App\Services\Assignments;

use App\Models\Assignment;
use App\Services\Notifications\NotificationDispatcherService;
use App\Support\Providers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * «التعيين» — hands one item (a booking, an order…) of a business to one of its providers, or takes it back. The
 * only writer of `assignments`; who is eligible is {@see Providers}.
 *
 * Any model works as long as it carries `business_id`; give it the HasAssignee trait to ask for its assignee.
 */
class AssignmentService
{
    public function __construct(private readonly NotificationDispatcherService $notifications)
    {
    }

    /**
     * Assign [$item] to [$assigneeId] (a provider of the business), or release it with null.
     *
     * @throws ValidationException when the person is not a provider of this business
     */
    public function assign(Model $item, ?int $assigneeId, int $byUserId): ?Assignment
    {
        $businessId = (int) $item->getAttribute('business_id');
        $type = $item->getMorphClass();

        if ($assigneeId === null) {
            Assignment::query()->where('assignable_type', $type)->where('assignable_id', $item->getKey())->delete();

            return null;
        }

        if (! Providers::isProviderOf($assigneeId, $businessId)) {
            throw ValidationException::withMessages(['assignee_id' => __('هذا الشخص ليس من مقدّمي الخدمة في هذا النشاط.')]);
        }

        $existing = Assignment::query()->where('assignable_type', $type)->where('assignable_id', $item->getKey())->first();

        $row = Assignment::query()->updateOrCreate(
            ['assignable_type' => $type, 'assignable_id' => (int) $item->getKey()],
            ['business_id' => $businessId, 'assignee_id' => $assigneeId, 'assigned_by' => $byUserId],
        );

        // he is told once per hand-over, and never about what he assigned to himself
        if ((int) ($existing?->assignee_id ?? 0) !== $assigneeId && $assigneeId !== $byUserId) {
            $this->tell($item, $assigneeId);
        }

        return $row;
    }

    /**
     * Who is responsible, for many items at once (one query).
     *
     * @param  iterable<int>  $ids
     * @return array<int,int> item id → user id
     */
    public function assigneesOf(string $morphClass, iterable $ids): array
    {
        $ids = collect($ids)->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();

        if ($ids === []) {
            return [];
        }

        return Assignment::query()
            ->where('assignable_type', $morphClass)
            ->whereIn('assignable_id', $ids)
            ->pluck('assignee_id', 'assignable_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /** The assignee of one item, or null. */
    public function assigneeOf(Model $item): ?int
    {
        return $this->assigneesOf($item->getMorphClass(), [$item->getKey()])[(int) $item->getKey()] ?? null;
    }

    private function tell(Model $item, int $assigneeId): void
    {
        try {
            $this->notifications->dispatch('assignment_assigned', $assigneeId, [
                // Stored bilingual content — deliberately not wrapped in __().
                'title_ar' => 'تم تعيينك على عمل جديد',
                'title_en' => 'A job was assigned to you',
                'body_ar' => 'عُيّن لك عمل جديد في النشاط، افتحه من قائمة أعمالك.',
                'body_en' => 'A new job was assigned to you — open it from your work list.',
                'notifiable_type' => $item::class,
                'notifiable_id' => (int) $item->getKey(),
                'source_type' => $item::class,
                'source_id' => (int) $item->getKey(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
