<?php

namespace App\Services\Agenda;

use App\Models\AgendaItem;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * The personal agenda: every user's single timeline of time commitments and
 * reminders. Bookings and appointments across services mirror themselves here
 * (so nothing double-books the same person), the patient reads their day from
 * it, and medication doses and personal tasks are pushed from it.
 */
class AgendaService
{
    public function __construct(private readonly NotificationDispatcherService $notifications)
    {
    }

    /**
     * Refuse a new blocking span if it overlaps one the user already holds.
     * `$ignoreSource` (a [type, id] pair) skips the commitment being moved so a
     * reschedule doesn't clash with its own current slot.
     */
    public function assertFree(int $userId, Carbon $start, Carbon $end, ?array $ignoreSource = null, string $field = 'scheduled_at'): void
    {
        if (! $this->isFree($userId, $start, $end, $ignoreSource)) {
            throw ValidationException::withMessages([
                $field => __('لديك حجز آخر في هذا الوقت.'),
            ]);
        }
    }

    /** True if no blocking active item overlaps the window (the non-throwing form). */
    public function isFree(int $userId, Carbon $start, Carbon $end, ?array $ignoreSource = null): bool
    {
        return ! AgendaItem::query()
            ->where('user_id', $userId)
            ->where('status', AgendaItem::STATUS_ACTIVE)
            ->where('blocking', true)
            ->whereNotNull('ends_at')
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->when($ignoreSource, fn ($q) => $q->where(function ($inner) use ($ignoreSource) {
                $inner->where('source_type', '!=', $ignoreSource[0])
                    ->orWhere('source_id', '!=', $ignoreSource[1])
                    ->orWhereNull('source_id');
            }))
            ->exists();
    }

    /** The [start, end] span a commitment occupies (all-day fills the whole day). */
    public function blockingWindow(Carbon $start, ?Carbon $end, bool $allDay = false): array
    {
        if ($allDay) {
            return [$start->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        return [$start, $end ?? $start->copy()->addHour()];
    }

    /**
     * Create or move the single agenda entry that mirrors a source commitment
     * (an appointment, a booking). Idempotent per source.
     */
    public function syncCommitment(int $userId, string $kind, Model $source, string $title, Carbon $start, ?Carbon $end): AgendaItem
    {
        return AgendaItem::query()->updateOrCreate(
            ['source_type' => $source->getMorphClass(), 'source_id' => $source->getKey()],
            [
                'user_id' => $userId,
                'kind' => $kind,
                'title' => $title,
                'starts_at' => $start,
                'ends_at' => $end,
                'blocking' => true,
                'status' => AgendaItem::STATUS_ACTIVE,
            ],
        );
    }

    /** Close a source's agenda entries (cancelled or done). */
    public function closeForSource(Model $source, string $status = AgendaItem::STATUS_CANCELLED): void
    {
        AgendaItem::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->where('status', AgendaItem::STATUS_ACTIVE)
            ->update(['status' => $status]);
    }

    /** The user's items for one day, ordered by time. */
    public function forDay(int $userId, Carbon $date): \Illuminate\Support\Collection
    {
        return AgendaItem::query()
            ->where('user_id', $userId)
            ->where('status', AgendaItem::STATUS_ACTIVE)
            ->whereBetween('starts_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->orderBy('starts_at')
            ->get();
    }

    /** A user adds their own timed task; it blocks time like any commitment. */
    public function addPersonalTask(int $userId, string $title, Carbon $start, ?Carbon $end, ?string $notes, bool $remind): AgendaItem
    {
        $end = $end ?? $start->copy()->addMinutes(30);
        $this->assertFree($userId, $start, $end);

        return AgendaItem::create([
            'user_id' => $userId,
            'kind' => AgendaItem::KIND_PERSONAL,
            'title' => $title,
            'notes' => $notes,
            'starts_at' => $start,
            'ends_at' => $end,
            'blocking' => true,
            'status' => AgendaItem::STATUS_ACTIVE,
            'remind' => $remind,
        ]);
    }

    /**
     * Add a repeating personal task over a horizon of days. `$weekdays` (Carbon
     * dayOfWeek 0=Sun..6=Sat) limits weekly recurrence; empty = every day. A day
     * whose slot clashes with an existing commitment is skipped, not failed.
     * Returns [created, skipped].
     */
    public function addRecurringTasks(
        int $userId,
        string $title,
        int $hour,
        int $minute,
        int $durationMinutes,
        array $weekdays,
        int $days,
        ?string $notes,
        bool $remind,
    ): array {
        $created = 0;
        $skipped = 0;
        $today = Carbon::today();

        for ($d = 0; $d <= $days; $d++) {
            $date = $today->copy()->addDays($d);

            if ($weekdays !== [] && ! in_array($date->dayOfWeek, $weekdays, true)) {
                continue;
            }

            $start = $date->copy()->setTime($hour, $minute, 0);
            if ($start->isPast()) {
                continue;
            }

            $end = $start->copy()->addMinutes($durationMinutes);
            if (! $this->isFree($userId, $start, $end)) {
                $skipped++;
                continue;
            }

            AgendaItem::create([
                'user_id' => $userId,
                'kind' => AgendaItem::KIND_PERSONAL,
                'title' => $title,
                'notes' => $notes,
                'starts_at' => $start,
                'ends_at' => $end,
                'blocking' => true,
                'status' => AgendaItem::STATUS_ACTIVE,
                'remind' => $remind,
            ]);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /** Add a non-blocking point reminder (used for medication doses). */
    public function addReminder(int $userId, string $kind, ?Model $source, string $title, Carbon $at): AgendaItem
    {
        return AgendaItem::create([
            'user_id' => $userId,
            'kind' => $kind,
            'title' => $title,
            'starts_at' => $at,
            'ends_at' => null,
            'blocking' => false,
            'status' => AgendaItem::STATUS_ACTIVE,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'remind' => true,
        ]);
    }

    /**
     * Push every due reminder (medication dose, personal task) whose time has
     * arrived and that has not been pushed yet. Idempotent via reminded_at.
     */
    public function sendDueReminders(int $limit = 300): int
    {
        $now = Carbon::now();

        $due = AgendaItem::query()
            ->where('status', AgendaItem::STATUS_ACTIVE)
            ->where('remind', true)
            ->whereNull('reminded_at')
            ->where('starts_at', '<=', $now)
            ->where('starts_at', '>', $now->copy()->subHours(6)) // don't shout about long-missed ones
            ->orderBy('starts_at')
            ->limit($limit)
            ->get();

        $sent = 0;
        foreach ($due as $item) {
            $isMed = $item->kind === AgendaItem::KIND_MEDICATION;
            $this->notify($isMed ? 'medication_reminder' : 'agenda_reminder', (int) $item->user_id, $item,
                $isMed ? 'تذكير بالدواء' : 'تذكير بمهمة',
                $isMed ? 'Medication reminder' : 'Task reminder',
                $item->title, $item->title);

            $item->update(['reminded_at' => $now]);
            $sent++;
        }

        return $sent;
    }

    private function notify(string $eventKey, int $userId, AgendaItem $item, string $titleAr, string $titleEn, string $bodyAr, string $bodyEn): void
    {
        try {
            $this->notifications->dispatch($eventKey, $userId, [
                'title_ar' => $titleAr,
                'title_en' => $titleEn,
                'body_ar' => $bodyAr,
                'body_en' => $bodyEn,
                'notifiable_type' => AgendaItem::class,
                'notifiable_id' => (int) $item->id,
                'source_type' => AgendaItem::class,
                'source_id' => (int) $item->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
