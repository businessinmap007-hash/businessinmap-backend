<?php

namespace App\Services\Agenda;

use App\Models\AgendaItem;
use App\Models\ReminderPreference;
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
        return $this->forRange($userId, $date->copy()->startOfDay(), $date->copy()->endOfDay());
    }

    /** The user's active items within a datetime range, ordered by time. */
    public function forRange(int $userId, Carbon $from, Carbon $to): \Illuminate\Support\Collection
    {
        return AgendaItem::query()
            ->where('user_id', $userId)
            ->where('status', AgendaItem::STATUS_ACTIVE)
            ->whereBetween('starts_at', [$from, $to])
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * Build an iCalendar (ICS) document of a user's active items in a range, one
     * VEVENT each, for import into an external calendar. Times are emitted in UTC.
     */
    public function icsForUser(int $userId, Carbon $from, Carbon $to): string
    {
        $items = $this->forRange($userId, $from, $to);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//BusinessInMap//Agenda//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->icsEscape(__('جدولي')),
        ];

        $stamp = Carbon::now('UTC')->format('Ymd\THis\Z');

        foreach ($items as $item) {
            $start = $item->starts_at->copy()->utc();
            $end = ($item->ends_at ?? $item->starts_at->copy()->addMinutes(15))->copy()->utc();

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:agenda-' . $item->id . '@businessinmap';
            $lines[] = 'DTSTAMP:' . $stamp;
            $lines[] = 'DTSTART:' . $start->format('Ymd\THis\Z');
            $lines[] = 'DTEND:' . $end->format('Ymd\THis\Z');
            $lines[] = $this->icsFold('SUMMARY:' . $this->icsEscape((string) $item->title));
            if ($item->notes) {
                $lines[] = $this->icsFold('DESCRIPTION:' . $this->icsEscape((string) $item->notes));
            }
            $lines[] = 'CATEGORIES:' . strtoupper((string) $item->kind);
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        // RFC 5545 wants CRLF line endings.
        return implode("\r\n", $lines) . "\r\n";
    }

    /** Escape a text value per RFC 5545 (backslash, comma, semicolon, newlines). */
    private function icsEscape(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace([';', ','], ['\\;', '\\,'], $value);

        return str_replace(["\r\n", "\n", "\r"], '\\n', $value);
    }

    /**
     * Fold a content line to 75 octets with a leading space on continuations,
     * cutting on UTF-8 boundaries so Arabic characters are never split.
     */
    private function icsFold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $folded = '';
        while (strlen($line) > 75) {
            $chunk = mb_strcut($line, 0, 75, 'UTF-8');
            $folded .= $chunk . "\r\n ";
            $line = substr($line, strlen($chunk));
        }

        return $folded . $line;
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

        // Candidates: due within the largest possible lead, not long-missed.
        $candidates = AgendaItem::query()
            ->where('status', AgendaItem::STATUS_ACTIVE)
            ->where('remind', true)
            ->whereNull('reminded_at')
            ->where('starts_at', '<=', $now->copy()->addMinutes(ReminderPreference::MAX_AGENDA_LEAD))
            ->where('starts_at', '>', $now->copy()->subHours(6)) // don't shout about long-missed ones
            ->orderBy('starts_at')
            ->limit($limit)
            ->get();

        $leads = ReminderPreference::query()
            ->whereIn('user_id', $candidates->pluck('user_id')->unique()->all())
            ->get()->keyBy('user_id');

        // Each user's item is due once now is within their lead of its time.
        $due = $candidates->filter(function (AgendaItem $item) use ($now, $leads) {
            $lead = ($leads->get((int) $item->user_id) ?? new ReminderPreference())->agendaLead();

            return $now->gte($item->starts_at->copy()->subMinutes($lead));
        });

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
