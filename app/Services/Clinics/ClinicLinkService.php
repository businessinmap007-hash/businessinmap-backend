<?php

namespace App\Services\Clinics;

use App\Models\BusinessWorkingHour;
use App\Models\ClinicLink;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Validation\ValidationException;

/**
 * A doctor with more than one clinic account links them together, with both
 * sides' consent («بموافقة الطرفين» — المالك), so a patient who opens either
 * one sees a single unified schedule across all of them.
 */
class ClinicLinkService
{
    public function __construct(private readonly NotificationDispatcherService $notifications)
    {
    }

    /**
     * Request a link. If the OTHER clinic already requested one from this
     * side, that reverse request is accepted instead of creating a second,
     * mirror-image pending row.
     */
    public function request(User $requester, User $target): ClinicLink
    {
        if ((int) $requester->id === (int) $target->id) {
            throw ValidationException::withMessages(['target_id' => __('لا يمكن ربط الحساب بنفسه.')]);
        }

        foreach ([$requester, $target] as $clinic) {
            if ((int) ($clinic->category_child_id ?? 0) !== User::DOCTOR_OWN_CLINIC_CHILD_ID) {
                throw ValidationException::withMessages(['target_id' => __('ربط العيادات متاح لحسابات العيادات الفردية فقط.')]);
            }
        }

        $reverse = ClinicLink::query()
            ->where('requester_id', $target->id)
            ->where('target_id', $requester->id)
            ->first();

        if ($reverse) {
            return $this->accept($reverse, $requester);
        }

        $existing = ClinicLink::query()
            ->where('requester_id', $requester->id)
            ->where('target_id', $target->id)
            ->first();

        if ($existing) {
            return $existing;
        }

        $link = ClinicLink::create([
            'requester_id' => (int) $requester->id,
            'target_id' => (int) $target->id,
            'status' => ClinicLink::STATUS_PENDING,
        ]);

        $this->notify($link, $target, 'طلب ربط عيادة', 'Clinic link request',
            'طلب طبيب ربط حسابه بحسابك كنفس الطبيب.', 'A doctor asked to link their account with yours as the same physician.');

        return $link;
    }

    public function accept(ClinicLink $link, User $actor): ClinicLink
    {
        if ((int) $link->target_id !== (int) $actor->id) {
            abort(403, __('لست طرف الاستهداف فى هذا الطلب.'));
        }

        if ($link->status !== ClinicLink::STATUS_PENDING) {
            throw ValidationException::withMessages(['status' => __('هذا الطلب لم يعد قيد الانتظار.')]);
        }

        $link->update(['status' => ClinicLink::STATUS_ACCEPTED]);

        $this->notify($link, $link->requester, 'تم قبول ربط العيادة', 'Clinic link accepted',
            'وافق الطبيب الآخر على ربط حسابيكما.', 'The other doctor accepted the clinic link.');

        return $link;
    }

    /** Decline a still-pending request, or unlink an already-accepted one — either party, either state. */
    public function remove(ClinicLink $link, User $actor): void
    {
        if (! $link->isParty((int) $actor->id)) {
            abort(403, __('لست طرفاً فى هذا الربط.'));
        }

        $link->delete();
    }

    /** My own links: who I'm waiting on, who's waiting on me, and who I'm linked with. */
    public function linksFor(int $clinicId): array
    {
        $rows = ClinicLink::query()
            ->where('requester_id', $clinicId)
            ->orWhere('target_id', $clinicId)
            ->with(['requester:id,name,name_en,medical_title', 'target:id,name,name_en,medical_title'])
            ->get();

        return [
            'sent_pending' => $rows->where('status', ClinicLink::STATUS_PENDING)->where('requester_id', $clinicId)->values(),
            'received_pending' => $rows->where('status', ClinicLink::STATUS_PENDING)->where('target_id', $clinicId)->values(),
            'accepted' => $rows->where('status', ClinicLink::STATUS_ACCEPTED)->values(),
        ];
    }

    /**
     * The unified schedule a patient sees: this clinic plus every clinic it
     * is accepted-linked with, each with its own working days/hours
     * («سبت واتنين واربعاء عيادة دمياط... حد وتلاتة وخميس عيادة القاهرة»).
     */
    public function scheduleGroupFor(int $clinicId): array
    {
        $linkedIds = ClinicLink::query()
            ->where('status', ClinicLink::STATUS_ACCEPTED)
            ->where(fn ($q) => $q->where('requester_id', $clinicId)->orWhere('target_id', $clinicId))
            ->get()
            ->map(fn (ClinicLink $l) => $l->otherId($clinicId));

        // A plain Support Collection, not the Eloquent one `get()` returned —
        // push()/unique() on an Eloquent Collection of bare ints (not Models)
        // breaks, since it still tries to key them by getKey().
        $allIds = collect($linkedIds->all())->push($clinicId)->unique()->values();

        $clinics = User::query()->whereIn('id', $allIds)->get()->keyBy('id');
        $hours = BusinessWorkingHour::query()->whereIn('business_id', $allIds)->get()->groupBy('business_id');

        return $allIds->map(function (int $id) use ($clinics, $hours, $clinicId) {
            $clinic = $clinics->get($id);

            return [
                'id' => $id,
                'name' => $clinic?->displayName(),
                'is_self' => $id === $clinicId,
                'days' => ($hours->get($id) ?? collect())
                    ->reject(fn (BusinessWorkingHour $h) => $h->is_closed || ! $h->open_time || ! $h->close_time)
                    ->sortBy('day_of_week')
                    ->map(fn (BusinessWorkingHour $h) => [
                        'day_of_week' => (int) $h->day_of_week,
                        'open_time' => substr((string) $h->open_time, 0, 5),
                        'close_time' => substr((string) $h->close_time, 0, 5),
                    ])
                    ->values(),
            ];
        })->values()->all();
    }

    private function notify(ClinicLink $link, User $user, string $titleAr, string $titleEn, string $bodyAr, string $bodyEn): void
    {
        try {
            $this->notifications->dispatch('clinic_link', (int) $user->id, [
                'title_ar' => $titleAr,
                'title_en' => $titleEn,
                'body_ar' => $bodyAr,
                'body_en' => $bodyEn,
                'notifiable_type' => ClinicLink::class,
                'notifiable_id' => (int) $link->id,
                'source_type' => ClinicLink::class,
                'source_id' => (int) $link->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
