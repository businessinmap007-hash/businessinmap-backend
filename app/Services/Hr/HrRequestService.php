<?php

namespace App\Services\Hr;

use App\Models\Hr\Employee;
use App\Models\Hr\Holiday;
use App\Models\Hr\HrRequest;
use App\Models\StaffAttendance;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The one queue a manager answers (canvas «طلبات بانتظار اعتمادك»): leave (annual / casual / sick / unpaid), a punch
 * correction (forgot to check out), and overtime. A correction, once approved, writes the punch with who corrected it
 * and from which request — nothing is ever wiped. Annual leave is refused beyond the year's balance.
 */
final class HrRequestService
{
    public const KINDS = ['leave', 'correction', 'overtime'];
    public const LEAVE_TYPES = ['annual', 'casual', 'sick', 'unpaid'];

    public function __construct(private readonly HrRules $rules) {}

    public function create(int $businessId, int $userId, array $data, bool $byManager = false): HrRequest
    {
        $kind = (string) $data['kind'];
        $from = Carbon::parse($data['date_from']);
        $to = Carbon::parse($data['date_to'] ?? $data['date_from']);

        if ($to->lt($from)) {
            throw ValidationException::withMessages(['date_to' => __('تاريخ النهاية قبل البداية.')]);
        }

        $payload = match ($kind) {
            'leave' => ['type' => $data['leave_type'] ?? 'annual', 'days' => $this->workDays($businessId, $userId, $from, $to)],
            'correction' => ['check_in' => $data['check_in'] ?? null, 'check_out' => $data['check_out'] ?? null],
            'overtime' => ['minutes' => (int) ($data['minutes'] ?? 0), 'night_minutes' => (int) ($data['night_minutes'] ?? 0)],
        };

        if ($kind === 'leave' && $payload['type'] === 'annual') {
            $balance = $this->leaveBalance($businessId, $userId, $from);
            if ($payload['days'] > $balance['remaining']) {
                throw ValidationException::withMessages(['date_to' => __('رصيد الإجازة الاعتيادية لا يكفي (المتبقي :n يوم).', ['n' => $balance['remaining']])]);
            }
        }
        if ($kind === 'correction' && empty($payload['check_in']) && empty($payload['check_out'])) {
            throw ValidationException::withMessages(['check_in' => __('اكتب وقت الحضور أو الانصراف الصحيح.')]);
        }
        if ($kind === 'overtime' && $payload['minutes'] + $payload['night_minutes'] <= 0) {
            throw ValidationException::withMessages(['minutes' => __('اكتب دقائق الإضافي.')]);
        }

        $request = HrRequest::create([
            'business_id' => $businessId,
            'user_id' => $userId,
            'kind' => $kind,
            'date_from' => $from->toDateString(),
            'date_to' => $kind === 'leave' ? $to->toDateString() : $from->toDateString(),
            'data' => $payload,
            'reason' => $data['reason'] ?? null,
            'status' => 'pending',
        ]);

        if (! $byManager) {
            try {
                app(NotificationDispatcherService::class)->dispatch('hr_request_pending', $businessId, [
                    'body_ar' => 'طلب جديد بانتظار اعتمادك من أحد الموظفين.',
                    'body_en' => 'A staff request awaits your approval.',
                    'source_type' => 'hr_request',
                    'source_id' => $request->id,
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $request;
    }

    public function decide(HrRequest $request, bool $approve, int $actorId, ?string $note = null): HrRequest
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages(['status' => __('تم الرد على هذا الطلب من قبل.')]);
        }

        DB::transaction(function () use ($request, $approve, $actorId, $note) {
            $request->update(['status' => $approve ? 'approved' : 'rejected', 'decided_by' => $actorId, 'decided_at' => now(), 'decision_note' => $note]);

            if ($approve && $request->kind === 'correction') {
                $date = $request->date_from->toDateString();
                $punch = StaffAttendance::query()->firstOrNew(['business_id' => $request->business_id, 'user_id' => $request->user_id, 'date' => $date]);
                foreach (['check_in' => 'checked_in_at', 'check_out' => 'checked_out_at'] as $in => $column) {
                    if (! empty($request->data[$in])) {
                        $punch->{$column} = Carbon::parse($date . ' ' . $request->data[$in]);
                    }
                }
                $punch->corrected_by = $actorId;
                $punch->correction_request_id = $request->id;
                $punch->save();
            }
        });

        try {
            app(NotificationDispatcherService::class)->dispatch('hr_request_decided', (int) $request->user_id, [
                'body_ar' => $approve ? 'تم اعتماد طلبك.' : 'رُفض طلبك' . ($note ? ": {$note}" : '.'),
                'body_en' => $approve ? 'Your request was approved.' : 'Your request was declined.',
                'source_type' => 'hr_request',
                'source_id' => $request->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return $request->fresh();
    }

    /** @return array{entitlement:int,used:int,remaining:int} the year's annual leave */
    public function leaveBalance(int $businessId, int $userId, ?Carbon $on = null): array
    {
        $on ??= now();
        $employee = Employee::query()->where('business_id', $businessId)->where('user_id', $userId)->first();
        $entitlement = $employee ? $this->rules->annualLeave($employee, $on) : (int) $this->rules->law('annual_leave', $on)['first_year'];

        $used = (int) HrRequest::query()->where('business_id', $businessId)->where('user_id', $userId)->where('kind', 'leave')
            ->where('status', 'approved')->whereYear('date_from', $on->year)->get()
            ->filter(fn ($r) => ($r->data['type'] ?? 'annual') === 'annual')->sum(fn ($r) => (int) ($r->data['days'] ?? 0));

        return ['entitlement' => $entitlement, 'used' => $used, 'remaining' => max(0, $entitlement - $used)];
    }

    /** Work days in a range: the weekly rest days and the business's holidays are not leave. */
    public function workDays(int $businessId, int $userId, Carbon $from, Carbon $to): int
    {
        $settings = $this->rules->settings($businessId);
        $employee = Employee::query()->where('business_id', $businessId)->where('user_id', $userId)->first();
        $rest = $this->rules->restDays($settings, $employee);
        $holidays = Holiday::query()->where('business_id', $businessId)->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->pluck('date')->map(fn ($d) => Carbon::parse($d)->toDateString())->all();

        $count = 0;
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            if (! in_array($d->dayOfWeek, $rest, true) && ! in_array($d->toDateString(), $holidays, true)) {
                $count++;
            }
        }

        return $count;
    }
}
