<?php

namespace App\Services\Clinics;

use App\Models\ClinicDentalService;
use App\Models\DentalService;
use App\Models\HospitalDoctor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «خدمات عيادة الأسنان» — a dental clinic offers cleaning, fillings, root canals, implants, braces… picked from the
 * platform's list (or added by itself), with its OWN price (or none: «السعر بعد الكشف»). A patient reads the list on the
 * clinic's page and books a visit the ordinary way.
 *
 * Who counts as a dental clinic: a single clinic (514) or a medical centre (515) that ticked the «أسنان» specialty. A
 * hospital has its own medical procedures, and a beauty centre never gets this list — teeth cleaning and whitening are
 * a dentist's.
 */
class DentalServicesService
{
    /** A single clinic or a medical centre; hospitals are not here. */
    public const CHILDREN = [User::DOCTOR_OWN_CLINIC_CHILD_ID, 515];

    /** The specialty option (group «تخصصات طبية») that makes a clinic a dental one. */
    public const SPECIALTY = 'أسنان';

    public function isDentalClinic(?User $user): bool
    {
        if ($user === null || ! in_array((int) ($user->category_child_id ?? 0), self::CHILDREN, true)) {
            return false;
        }

        $option = $this->specialtyOptionId();

        return $option !== null && DB::table('option_user')->where('user_id', (int) $user->id)->where('option_id', $option)->exists();
    }

    private function specialtyOptionId(): ?int
    {
        $id = DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', HospitalDoctor::DEPARTMENT_GROUP)
            ->where('o.name_ar', self::SPECIALTY)
            ->value('o.id');

        return $id === null ? null : (int) $id;
    }

    // ───────────────────────── the clinic's own catalogue ─────────────────────────

    /**
     * Every service the clinic can offer — the platform's list and its own — with what it offers now.
     *
     * @return list<array{kind:string,services:list<array<string,mixed>>}>
     */
    public function catalogOf(User $clinic): array
    {
        $offered = ClinicDentalService::query()->where('clinic_id', (int) $clinic->id)->get()->keyBy('dental_service_id');

        $rows = DentalService::query()->visibleTo((int) $clinic->id)->orderBy('sort_order')->orderBy('id')->get();

        $out = [];
        foreach (DentalService::KINDS as $kind) {
            $out[] = [
                'kind' => $kind,
                'services' => $rows->where('kind', $kind)->map(function (DentalService $s) use ($offered) {
                    $o = $offered->get($s->id);

                    return [
                        'id' => (int) $s->id,
                        'name' => $s->label(),
                        'own' => $s->owner_id !== null,
                        'offered' => $o !== null,
                        'price' => $o?->price,
                    ];
                })->values()->all(),
            ];
        }

        return $out;
    }

    /**
     * Save what the clinic offers: `service_id`, `offered`, and `price` (null/0 = after the exam). Only entries of the
     * platform list or the clinic's own are accepted — never another clinic's.
     *
     * @param  list<array{service_id:int,offered?:bool,price?:float|int|string|null}>  $items
     */
    public function save(User $clinic, array $items): void
    {
        $allowed = DentalService::query()->visibleTo((int) $clinic->id)->pluck('id')->flip();

        DB::transaction(function () use ($clinic, $items, $allowed) {
            foreach ($items as $item) {
                $id = (int) ($item['service_id'] ?? 0);

                if (! $allowed->has($id)) {
                    throw ValidationException::withMessages(['items' => __('خدمة غير موجودة في القائمة.')]);
                }

                if (! ($item['offered'] ?? true)) {
                    ClinicDentalService::query()->where('clinic_id', $clinic->id)->where('dental_service_id', $id)->delete();

                    continue;
                }

                $price = isset($item['price']) && is_numeric($item['price']) && (float) $item['price'] > 0 ? round((float) $item['price'], 2) : null;

                ClinicDentalService::query()->updateOrCreate(
                    ['clinic_id' => (int) $clinic->id, 'dental_service_id' => $id],
                    ['price' => $price],
                );
            }
        });
    }

    /** A service the platform list does not have: the clinic's own, offered at once. */
    public function addOwn(User $clinic, string $kind, string $name): DentalService
    {
        $name = trim($name);

        if (! in_array($kind, DentalService::KINDS, true) || $name === '') {
            throw ValidationException::withMessages(['name' => __('اكتب اسم الخدمة واختر قسمها.')]);
        }

        if (DentalService::query()->visibleTo((int) $clinic->id)->where('kind', $kind)->where('name_ar', $name)->exists()) {
            throw ValidationException::withMessages(['name' => __('هذه الخدمة موجودة في القائمة.')]);
        }

        $row = DentalService::create(['kind' => $kind, 'name_ar' => $name, 'owner_id' => (int) $clinic->id, 'sort_order' => 999]);
        ClinicDentalService::create(['clinic_id' => (int) $clinic->id, 'dental_service_id' => (int) $row->id]);

        return $row;
    }

    public function deleteOwn(User $clinic, DentalService $service): void
    {
        abort_unless((int) $service->owner_id === (int) $clinic->id, 404);

        $service->delete();
    }

    // ───────────────────────── what a patient sees ─────────────────────────

    /**
     * What the clinic offers, by kind; kinds with nothing offered are left out.
     *
     * @return list<array{kind:string,services:list<array<string,mixed>>}>
     */
    public function offeredBy(User $clinic): array
    {
        $rows = ClinicDentalService::query()->where('clinic_id', (int) $clinic->id)->with('service')->get()
            ->filter(fn (ClinicDentalService $o) => $o->service !== null);

        $out = [];
        foreach (DentalService::KINDS as $kind) {
            $in = $rows->filter(fn (ClinicDentalService $o) => $o->service->kind === $kind)
                ->sortBy(fn (ClinicDentalService $o) => [$o->service->sort_order, $o->id]);

            if ($in->isEmpty()) {
                continue;
            }

            $out[] = [
                'kind' => $kind,
                'services' => $in->map(fn (ClinicDentalService $o) => [
                    'id' => (int) $o->dental_service_id,
                    'name' => $o->service->label(),
                    'price' => $o->price,
                ])->values()->all(),
            ];
        }

        return $out;
    }

    public function hasOffers(User $clinic): bool
    {
        return ClinicDentalService::query()->where('clinic_id', (int) $clinic->id)->exists();
    }
}
