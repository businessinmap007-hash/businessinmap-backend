<?php

namespace App\Services\Hospitals;

use App\Models\HospitalDoctor;
use App\Models\Option;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «الأطباء تحت الأقسام» — a hospital's departments are the medical specialties it ticked on itself, and each department
 * lists its doctors. A doctor with an account joins only by accepting (so no hospital can list a doctor who never
 * agreed), and a patient can open that doctor's own page from under the department; a doctor with no account is listed
 * as text. See the create migration.
 */
class HospitalDoctorService
{
    public function __construct(private readonly NotificationDispatcherService $notifications)
    {
    }

    /** The options of «تخصصات طبية» the hospital ticked — its departments, in the platform's order. */
    private function departmentOptions(User $hospital): Collection
    {
        return Option::query()
            ->whereIn('id', DB::table('option_user')->where('user_id', (int) $hospital->id)->select('option_id'))
            ->whereIn('group_id', DB::table('option_groups')->where('name_ar', HospitalDoctor::DEPARTMENT_GROUP)->select('id'))
            ->ordered()
            ->get();
    }

    /**
     * Departments with their doctors. A patient sees active doctors only; the hospital itself also sees the ones still
     * waiting for the doctor to accept (`pending`).
     *
     * @return list<array{option_id:int,name:string,doctors:list<array<string,mixed>>}>
     */
    public function departmentsOf(User $hospital, bool $forOwner = false): array
    {
        $rows = HospitalDoctor::query()
            ->where('hospital_id', (int) $hospital->id)
            ->when(! $forOwner, fn ($q) => $q->where('status', HospitalDoctor::STATUS_ACTIVE))
            ->with('doctor:id,name,name_en,medical_title')
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->groupBy('option_id');

        return $this->departmentOptions($hospital)->map(fn (Option $o) => [
            'option_id' => (int) $o->id,
            'name' => $o->displayName(),
            'doctors' => ($rows->get($o->id) ?? collect())->map(fn (HospitalDoctor $d) => $this->row($d))->values()->all(),
        ])->values()->all();
    }

    /** @return array<string,mixed> */
    private function row(HospitalDoctor $d): array
    {
        return [
            'id' => (int) $d->id,
            'name' => $d->shownName(),
            // the doctor's own account — the page a patient can visit; null for a doctor listed as text
            'business_id' => $d->user_id ? (int) $d->user_id : null,
            'status' => $d->status,
        ];
    }

    /** Doctors with an account a hospital may invite: the individual clinics, found by name. */
    public function findDoctors(string $query): array
    {
        $q = trim($query);

        if (mb_strlen($q) < 2) {
            return [];
        }

        return User::query()
            ->where('type', User::TYPE_BUSINESS)
            ->where('category_child_id', User::DOCTOR_OWN_CLINIC_CHILD_ID)
            ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('name_en', 'like', "%{$q}%"))
            ->orderBy('name')->limit(15)
            ->get(['id', 'name', 'name_en', 'medical_title'])
            ->map(fn (User $u) => ['id' => (int) $u->id, 'name' => $u->displayName()])
            ->all();
    }

    public function add(User $hospital, int $optionId, ?int $userId, ?string $name, ?string $title): HospitalDoctor
    {
        if (! HospitalDoctor::isHospital($hospital)) {
            throw ValidationException::withMessages(['option_id' => __('الأقسام للمستشفيات والمراكز الطبية فقط.')]);
        }

        if (! $this->departmentOptions($hospital)->contains('id', $optionId)) {
            throw ValidationException::withMessages(['option_id' => __('هذا القسم غير مفعّل في حسابك.')]);
        }

        $name = trim((string) $name);
        $title = trim((string) $title) ?: null;

        if ($userId) {
            $doctor = User::query()->where('type', User::TYPE_BUSINESS)
                ->where('category_child_id', User::DOCTOR_OWN_CLINIC_CHILD_ID)->find($userId);

            if (! $doctor) {
                throw ValidationException::withMessages(['user_id' => __('هذا الحساب ليس حساب طبيب.')]);
            }

            if (HospitalDoctor::query()->where('hospital_id', $hospital->id)->where('option_id', $optionId)->where('user_id', $userId)->exists()) {
                throw ValidationException::withMessages(['user_id' => __('هذا الطبيب مضاف في هذا القسم.')]);
            }

            // a doctor who already agreed to this hospital under another department joins this one at once
            $agreed = HospitalDoctor::query()->where('hospital_id', $hospital->id)->where('user_id', $userId)
                ->where('status', HospitalDoctor::STATUS_ACTIVE)->exists();

            $row = HospitalDoctor::create([
                'hospital_id' => (int) $hospital->id, 'option_id' => $optionId, 'user_id' => $userId,
                'title' => $title, 'status' => $agreed ? HospitalDoctor::STATUS_ACTIVE : HospitalDoctor::STATUS_PENDING,
            ]);

            if (! $agreed) {
                $this->notify($row, $doctor, 'دعوة للانضمام إلى مستشفى', 'Invitation to join a hospital',
                    'يدعوك ' . $hospital->displayName() . ' لتظهر ضمن أطبائه.', $hospital->displayName() . ' invites you to be listed among its doctors.');
            }

            return $row->load('doctor:id,name,name_en,medical_title');
        }

        if ($name === '') {
            throw ValidationException::withMessages(['name' => __('اكتب اسم الطبيب أو اختر حسابه.')]);
        }

        return HospitalDoctor::create([
            'hospital_id' => (int) $hospital->id, 'option_id' => $optionId, 'name' => $name, 'title' => $title,
            'status' => HospitalDoctor::STATUS_ACTIVE,
        ]);
    }

    /** The hospital drops a doctor, or the doctor leaves — either party, whatever the state. */
    public function remove(HospitalDoctor $row, User $actor): void
    {
        abort_unless(in_array((int) $actor->id, [(int) $row->hospital_id, (int) $row->user_id], true), 404);

        $row->delete();
    }

    /** Invitations and memberships of a doctor with an account: who is waiting on this doctor, and where they are listed. */
    public function invitationsFor(User $doctor): array
    {
        $rows = HospitalDoctor::query()->where('user_id', (int) $doctor->id)
            ->with(['hospital:id,name,name_en', 'department:id,name_ar,name_en'])->orderBy('id')->get();

        $map = fn (HospitalDoctor $d) => [
            'id' => (int) $d->id,
            'hospital' => ['id' => (int) $d->hospital_id, 'name' => $d->hospital?->displayName()],
            'department' => $d->department?->displayName(),
            'status' => $d->status,
        ];

        return [
            'pending' => $rows->where('status', HospitalDoctor::STATUS_PENDING)->map($map)->values()->all(),
            'active' => $rows->where('status', HospitalDoctor::STATUS_ACTIVE)->map($map)->values()->all(),
        ];
    }

    /** Accepting one invitation also accepts the same hospital's other pending departments — it is one agreement. */
    public function accept(HospitalDoctor $row, User $doctor): HospitalDoctor
    {
        abort_unless((int) $row->user_id === (int) $doctor->id, 404);

        if ($row->status !== HospitalDoctor::STATUS_PENDING) {
            throw ValidationException::withMessages(['status' => __('هذه الدعوة لم تعد قيد الانتظار.')]);
        }

        HospitalDoctor::query()->where('hospital_id', $row->hospital_id)->where('user_id', $doctor->id)
            ->where('status', HospitalDoctor::STATUS_PENDING)->update(['status' => HospitalDoctor::STATUS_ACTIVE]);

        if ($row->hospital) {
            $this->notify($row, $row->hospital, 'قبل الطبيب الدعوة', 'A doctor accepted',
                $doctor->displayName() . ' وافق على الظهور ضمن أطباء مستشفاك.', $doctor->displayName() . ' accepted to be listed among your doctors.');
        }

        return $row->fresh();
    }

    private function notify(HospitalDoctor $row, User $to, string $titleAr, string $titleEn, string $bodyAr, string $bodyEn): void
    {
        try {
            $this->notifications->dispatch('hospital_doctor', (int) $to->id, [
                'title_ar' => $titleAr, 'title_en' => $titleEn, 'body_ar' => $bodyAr, 'body_en' => $bodyEn,
                'notifiable_type' => HospitalDoctor::class, 'notifiable_id' => (int) $row->id,
                'source_type' => HospitalDoctor::class, 'source_id' => (int) $row->id,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
